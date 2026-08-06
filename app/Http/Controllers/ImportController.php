<?php

namespace App\Http\Controllers;

use App\Enums\ImportBatchStatus;
use App\Jobs\ProcessExcelImport;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ImportController extends Controller
{
    /**
     * Show the Excel upload form.
     */
    public function create(): View
    {
        return view('imports.create');
    }

    /**
     * Store the uploaded Excel file and record it as a pending import batch.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ]);

        $uploaded = $request->file('file');
        $path = $uploaded->store('imports', 'local');

        $batch = ImportBatch::create([
            'user_id' => $request->user()->id,
            'original_filename' => $uploaded->getClientOriginalName(),
            'file_path' => $path,
            'status' => ImportBatchStatus::Pending,
        ]);

        ProcessExcelImport::dispatch($batch);

        $message = "\"{$batch->original_filename}\" uploaded successfully as import batch #{$batch->id} and queued for processing.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'batch' => [
                    'id' => $batch->id,
                    'status' => $batch->status->value,
                ],
            ]);
        }

        return redirect()->route('imports.create')->with('status', $message);
    }

    /**
     * Report the current processing status of an import batch, for the
     * upload page's polling progress bar.
     */
    public function status(Request $request, ImportBatch $importBatch): JsonResponse
    {
        Gate::authorize('view', $importBatch);

        $percentage = null;

        if ($importBatch->total_rows > 0) {
            $percentage = (int) min(100, round($importBatch->processed_rows / $importBatch->total_rows * 100));
        }

        return response()->json([
            'id' => $importBatch->id,
            'original_filename' => $importBatch->original_filename,
            'status' => $importBatch->status->value,
            'status_label' => $importBatch->status->label(),
            'is_finished' => $importBatch->status->isFinished(),
            'total_rows' => $importBatch->total_rows,
            'processed_rows' => $importBatch->processed_rows,
            'failed_rows' => $importBatch->failed_rows,
            'percentage' => $percentage,
            'row_errors' => $importBatch->row_errors,
            'error_message' => $importBatch->error_message,
        ]);
    }
}
