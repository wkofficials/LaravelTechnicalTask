<?php

namespace App\Http\Controllers;

use App\Datasets\Dataset;
use App\Datasets\RecordQuery;
use App\Jobs\ExportRecords;
use App\Models\DataExport;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Background CSV exports. Requires "export records"; a file can only be
 * downloaded by the user who requested it (route binding is tenant-scoped,
 * so other companies' exports are 404s anyway).
 */
class DataExportController extends Controller
{
    /**
     * Running exports one user may have at a time.
     */
    private const MAX_RUNNING_PER_USER = 3;

    public function store(Request $request, Dataset $dataset): RedirectResponse
    {
        $running = DataExport::where('user_id', $request->user()->id)
            ->whereIn('status', [DataExport::STATUS_PENDING, DataExport::STATUS_PROCESSING])
            ->count();

        if ($running >= self::MAX_RUNNING_PER_USER) {
            return back()->withErrors(['export' => 'You already have '.self::MAX_RUNNING_PER_USER.' exports in progress. Please wait for them to finish.']);
        }

        // Same allow-listed options as the list the user was looking at.
        $query = RecordQuery::fromInput($dataset, $request->only(['q', 'filter', 'sort', 'direction']));

        $export = new DataExport(['dataset_key' => $dataset->key(), 'options' => $query->toArray()]);
        $export->forceFill(['user_id' => $request->user()->id, 'status' => DataExport::STATUS_PENDING])->save();

        ExportRecords::dispatch($export);

        // The free-text search is often a person's name or email, so only
        // whether one was used is logged — filters and sort are not personal.
        Audit::log('export.requested', $export, [
            'dataset' => $dataset->key(),
            'searched' => $query->search !== '',
            'filters' => $query->filters,
            'sort' => $query->sort.' '.$query->direction,
        ]);

        return back()->with('status', 'Export started. It will appear below with a download link when ready.');
    }

    public function status(Request $request, DataExport $export): JsonResponse
    {
        $this->ensureOwnExport($request, $export);

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'row_count' => $export->row_count,
            'download_url' => $export->isDownloadable() ? route('exports.download', $export) : null,
            'error_message' => $export->error_message,
        ]);
    }

    public function download(Request $request, DataExport $export): StreamedResponse
    {
        $this->ensureOwnExport($request, $export);

        abort_unless($export->isDownloadable() && Storage::disk('local')->exists($export->file_path), 404, 'This export is no longer available.');

        $name = Str::slug($export->dataset()->name()).'-export-'.$export->created_at->format('Y-m-d-His').'.csv';

        Audit::log('export.downloaded', $export, ['rows' => $export->row_count]);

        return Storage::disk('local')->download($export->file_path, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ensureOwnExport(Request $request, DataExport $export): void
    {
        abort_unless($export->user_id === $request->user()->id, 403);
    }
}
