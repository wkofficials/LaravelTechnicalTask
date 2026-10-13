<?php

namespace App\Notifications;

use App\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app note that a requested export can be downloaded.
 */
class ExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly DataExport $export)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->export->company_id,
            'title' => 'Your export is ready',
            'message' => number_format($this->export->row_count).' '.$this->export->dataset()->name()
                .' rows. The file can be downloaded for '.DataExport::KEEP_FOR_HOURS.' hours.',
            'url' => route('exports.download', $this->export, absolute: false),
            'level' => 'success',
        ];
    }
}
