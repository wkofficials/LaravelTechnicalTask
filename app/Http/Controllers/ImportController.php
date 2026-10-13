<?php

namespace App\Http\Controllers;

use App\Datasets\Dataset;
use App\Datasets\DatasetRegistry;
use App\Enums\DuplicatePolicy;
use App\Enums\ImportBatchStatus;
use App\Http\Requests\ConfirmImportRequest;
use App\Jobs\AnalyzeImport;
use App\Jobs\ProcessExcelImport;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportTemplate;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\PlanLimits;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly DatasetRegistry $datasets,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * How many row errors the status endpoint returns for a quick preview.
     */
    private const STATUS_ERROR_PREVIEW = 20;

    /**
     * Imports per company that may be uploaded but not yet started.
     */
    private const MAX_OPEN_IMPORTS = 20;

    /**
     * Import history: the user's own imports, or the whole company's with the
     * "view company imports" permission. Paginated server-side.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, array_column(ImportBatchStatus::cases(), 'value'), true) ? $status : null;

        $imports = ImportBatch::query()
            ->visibleTo($request->user())
            ->with('user:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('imports.index', [
            'imports' => $imports,
            'status' => $status,
            'statuses' => ImportBatchStatus::cases(),
        ]);
    }

    /**
     * Show the Excel upload form.
     */
    public function create(): View
    {
        $limits = PlanLimits::for($this->currentCompany->get());

        return view('imports.create', [
            'datasets' => $this->datasets->all(),
            'maxFileSizeMb' => intdiv($limits->maxFileSizeKb(), 1024),
            'maxRowsPerImport' => $limits->plan()->max_rows_per_import,
            'rowsRemaining' => $limits->importRowsRemaining(),
        ]);
    }

    /**
     * A ready-to-fill CSV for a dataset: the headings (which the mapping
     * screen recognises automatically) and a couple of example rows.
     */
    public function sample(Dataset $dataset): StreamedResponse
    {
        return response()->streamDownload(function () use ($dataset) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            Csv::writeRow($out, array_map(fn ($field) => $field->label, $dataset->fields()));

            foreach ($dataset->exampleRows() as $example) {
                Csv::writeRow($out, array_map(fn ($key) => $example[$key] ?? '', $dataset->fieldKeys()));
            }

            fclose($out);
        }, Str::slug($dataset->name()).'-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Store the uploaded file privately and queue its analysis. Nothing is
     * imported until the user has confirmed the column mapping.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $maxKb = PlanLimits::for($this->currentCompany->get())->maxFileSizeKb();

        $validated = $request->validate([
            'dataset' => ['required', Rule::in($this->datasets->keys())],
            // Content check + file-name check. Many CSVs (e.g. ";"-separated, as
            // Excel saves them in many locales) are detected as text/plain, so
            // "txt" content is allowed; the CSV reader handles both.
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'extensions:xlsx,xls,csv,txt', 'max:'.$maxKb],
        ], [
            'file.max' => 'Your plan allows files up to '.($maxKb / 1024).' MB.',
        ]);

        // Unconfirmed uploads occupy disk and the shared queue; cap them so one
        // company can't flood either.
        $open = ImportBatch::whereIn('status', array_map(fn ($status) => $status->value, ImportBatchStatus::notStarted()))->count();

        if ($open >= self::MAX_OPEN_IMPORTS) {
            throw ValidationException::withMessages([
                'file' => 'Your company already has '.self::MAX_OPEN_IMPORTS.' imports waiting to be confirmed or processed. Confirm or cancel some first.',
            ]);
        }

        $uploaded = $request->file('file');

        $batch = ImportBatch::create([
            'user_id' => $request->user()->id,
            'dataset_key' => $validated['dataset'],
            'original_filename' => $uploaded->getClientOriginalName(),
            'status' => ImportBatchStatus::Analyzing,
        ]);

        // Private disk, per-company folder, random file name.
        $batch->forceFill(['file_path' => $uploaded->store("imports/{$batch->company_id}", 'local')])->save();

        AnalyzeImport::dispatch($batch);

        Audit::log('import.uploaded', $batch, ['file' => $batch->original_filename, 'dataset' => $batch->dataset_key]);

        $message = "\"{$batch->original_filename}\" uploaded. Checking its columns…";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'batch' => [
                    'id' => $batch->id,
                    'status' => $batch->status->value,
                    'details_url' => route('imports.show', $batch),
                ],
            ]);
        }

        return redirect()->route('imports.show', $batch)->with('status', $message);
    }

    /**
     * Import details: counters, progress and the rejected rows (paginated).
     */
    public function show(Request $request, ImportBatch $importBatch): View
    {
        Gate::authorize('view', $importBatch);

        return view('imports.show', [
            'import' => $importBatch->load('user:id,name', 'template:id,name'),
            'rowErrors' => $importBatch->errors()->orderBy('row_number')->paginate(25, ['*'], 'errors_page'),
            'mappingForm' => $request->user()->can('confirm', $importBatch)
                ? $this->mappingForm($importBatch, $request->integer('template') ?: null)
                : null,
        ]);
    }

    /**
     * Save the confirmed mapping and duplicate policy (optionally as a
     * reusable template), then queue the actual import.
     */
    public function confirm(ConfirmImportRequest $request, ImportBatch $importBatch): RedirectResponse
    {
        $dataset = $importBatch->dataset();
        $mapping = $request->mapping();
        $policy = DuplicatePolicy::from($request->validated('duplicate_policy'));

        $confirmed = DB::transaction(function () use ($request, $importBatch, $dataset, $mapping, $policy) {
            // Serialize confirmations per company: the quota check below and
            // the status change that reserves the rows happen atomically.
            $company = Company::whereKey($importBatch->company_id)->lockForUpdate()->firstOrFail();

            if ($quotaProblem = PlanLimits::for($company)->checkMonthlyRows($importBatch->total_rows)) {
                throw ValidationException::withMessages(['quota' => $quotaProblem]);
            }

            $templateId = $request->validated('template_id');

            if ($request->boolean('save_template')) {
                $template = new ImportTemplate([
                    'dataset_key' => $dataset->key(),
                    'name' => $request->validated('template_name'),
                    // Stored by header name so it fits future files with moved columns.
                    'mapping' => array_map(
                        fn ($column) => $importBatch->headers[$column],
                        array_filter($mapping, fn ($column) => $column !== null)
                    ),
                    'duplicate_policy' => $policy,
                    'schema_version' => $dataset->schemaVersion(),
                ]);
                $template->forceFill(['created_by' => $request->user()->id])->save();
                $templateId = $template->id;

                Audit::log('template.created', $template, ['name' => $template->name]);
            }

            // Conditional on the status, so a double submit can't queue it twice.
            return ImportBatch::whereKey($importBatch->id)
                ->where('status', ImportBatchStatus::AwaitingConfirmation->value)
                ->update([
                    'mapping' => json_encode($mapping),
                    'duplicate_policy' => $policy->value,
                    'template_id' => $templateId,
                    'status' => ImportBatchStatus::Pending->value,
                ]);
        });

        if ($confirmed) {
            ProcessExcelImport::dispatch($importBatch);

            Audit::log('import.confirmed', $importBatch, [
                'duplicate_policy' => $policy->value,
                'mapped_fields' => array_keys(array_filter($mapping, fn ($column) => $column !== null)),
            ]);
        }

        return redirect()->route('imports.show', $importBatch)
            ->with('status', $confirmed ? 'Mapping confirmed. The import has been queued.' : 'This import was already confirmed.');
    }

    /**
     * Report the current processing status of an import batch, for the
     * upload page's polling progress bar.
     */
    public function status(Request $request, ImportBatch $importBatch): JsonResponse
    {
        Gate::authorize('view', $importBatch);

        $errors = $importBatch->errors()
            ->orderBy('row_number')
            ->limit(self::STATUS_ERROR_PREVIEW)
            ->get(['row_number', 'error_code', 'message']);

        return response()->json([
            'id' => $importBatch->id,
            'original_filename' => $importBatch->original_filename,
            'status' => $importBatch->status->value,
            'status_label' => $importBatch->status->label(),
            'is_finished' => $importBatch->status->isFinished(),
            'needs_confirmation' => $importBatch->status === ImportBatchStatus::AwaitingConfirmation,
            'total_rows' => $importBatch->total_rows,
            'processed_rows' => $importBatch->processed_rows,
            'imported_rows' => $importBatch->importedRows(),
            'inserted_rows' => $importBatch->inserted_rows,
            'updated_rows' => $importBatch->updated_rows,
            'skipped_rows' => $importBatch->skipped_rows,
            'failed_rows' => $importBatch->failed_rows,
            'percentage' => $importBatch->percentage(),
            'errors' => $errors->map(fn ($error) => [
                'row' => $error->row_number,
                'code' => $error->error_code,
                'error' => $error->message,
            ]),
            'error_message' => $importBatch->error_message,
            'cancel_requested' => $importBatch->cancel_requested_at !== null,
            'can_cancel' => $request->user()->can('cancel', $importBatch),
            'details_url' => route('imports.show', $importBatch),
            'errors_download_url' => $importBatch->failed_rows > 0 ? route('imports.errors', $importBatch) : null,
        ]);
    }

    /**
     * Stop an import. If it hasn't started it's cancelled at once; if it is
     * running, the worker stops before its next chunk. Rows already
     * committed are kept (and reported), not rolled back.
     */
    public function cancel(Request $request, ImportBatch $importBatch): JsonResponse|RedirectResponse
    {
        Gate::authorize('cancel', $importBatch);

        $now = now();

        $cancelledBeforeStart = ImportBatch::whereKey($importBatch->id)
            ->whereIn('status', array_map(fn ($status) => $status->value, ImportBatchStatus::notStarted()))
            ->update([
                'status' => ImportBatchStatus::Cancelled->value,
                'cancel_requested_at' => $now,
                'finished_at' => $now,
                'error_message' => 'Cancelled before any rows were imported.',
            ]);

        if (! $cancelledBeforeStart) {
            ImportBatch::whereKey($importBatch->id)
                ->whereNull('cancel_requested_at')
                ->update(['cancel_requested_at' => $now]);
        }

        Audit::log('import.cancelled', $importBatch, ['before_start' => (bool) $cancelledBeforeStart]);

        $message = $cancelledBeforeStart
            ? 'Import cancelled.'
            : 'Cancellation requested. The import will stop after the current chunk.';

        return $request->wantsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /**
     * Download every rejected row as CSV: row number, reason, and the row's
     * original values, so it can be fixed and uploaded again.
     */
    public function downloadErrors(ImportBatch $importBatch): StreamedResponse
    {
        Gate::authorize('view', $importBatch);

        $columns = $importBatch->errors()->value('row_data');
        $columns = array_keys(is_array($columns) ? $columns : (json_decode((string) $columns, true) ?? []));

        $filename = Str::slug(pathinfo($importBatch->original_filename, PATHINFO_FILENAME)) ?: 'import';

        // The report contains the rejected rows' original values.
        Audit::log('import.errors_downloaded', $importBatch, ['rows' => $importBatch->failed_rows]);

        return response()->streamDownload(function () use ($importBatch, $columns) {
            $out = fopen('php://output', 'w');

            Csv::writeRow($out, ['row_number', 'error_code', 'error', ...$columns]);

            $importBatch->errors()->orderBy('row_number')->lazyById(1000)->each(function ($error) use ($out, $columns) {
                $values = array_map(fn ($column) => $error->row_data[$column] ?? '', $columns);

                Csv::writeRow($out, [$error->row_number, $error->error_code, $error->message, ...$values]);
            });

            fclose($out);
        }, "{$filename}-import-{$importBatch->id}-errors.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Everything the mapping screen needs: the columns with sample values,
     * a suggested mapping (from a chosen template, else from header names),
     * and warnings when a template doesn't fit this file.
     *
     * @return array<string, mixed>
     */
    private function mappingForm(ImportBatch $importBatch, ?int $templateId): array
    {
        $dataset = $importBatch->dataset();
        $headers = $importBatch->headers ?? [];

        $templates = ImportTemplate::where('dataset_key', $dataset->key())->orderBy('name')->get();
        $template = $templateId ? $templates->firstWhere('id', $templateId) : null;

        $warnings = [];
        $policy = DuplicatePolicy::Reject;

        if ($template) {
            ['mapping' => $suggested, 'missing' => $missing] = $template->resolveAgainst($headers, $dataset);
            $policy = $template->duplicate_policy;

            if ($missing !== []) {
                $warnings[] = 'These columns from the template are not in this file: '.implode(', ', $missing).'.';
            }

            if ($template->isOutdatedFor($dataset)) {
                $warnings[] = "The template was saved for an older version of {$dataset->name()}. Check every field before confirming.";
            }
        } else {
            $suggested = $dataset->suggestMapping($headers);
        }

        $columns = [];

        foreach (array_keys($headers) as $index) {
            $columns[$index] = [
                'label' => $importBatch->columnLabel($index),
                'samples' => collect($importBatch->preview_rows ?? [])
                    ->pluck($index)
                    ->filter(fn ($value) => trim((string) $value) !== '')
                    ->take(3)
                    ->values()
                    ->all(),
            ];
        }

        return [
            'dataset' => $dataset,
            'columns' => $columns,
            'suggested' => $suggested,
            'templates' => $templates,
            'template' => $template,
            'warnings' => $warnings,
            'policy' => $policy,
            'policies' => DuplicatePolicy::cases(),
        ];
    }
}
