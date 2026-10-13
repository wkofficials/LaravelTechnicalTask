<?php

namespace App\Notifications;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the uploader an import ended (completed / with errors / failed).
 * Counts and the file name only — never row contents.
 */
class ImportFinishedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ImportBatch $import)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->import->company->email_import_notifications ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->line($this->summary())
            ->action('View import', route('imports.show', $this->import));

        return $this->import->status === ImportBatchStatus::Failed ? $mail->error() : $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->import->company_id,
            'title' => $this->title(),
            'message' => $this->summary(),
            'url' => route('imports.show', $this->import, absolute: false),
            'level' => match ($this->import->status) {
                ImportBatchStatus::Completed => 'success',
                ImportBatchStatus::CompletedWithErrors => 'warning',
                default => 'danger',
            },
        ];
    }

    private function title(): string
    {
        return match ($this->import->status) {
            ImportBatchStatus::Completed => "Import #{$this->import->id} completed",
            ImportBatchStatus::CompletedWithErrors => "Import #{$this->import->id} completed with errors",
            default => "Import #{$this->import->id} failed",
        };
    }

    private function summary(): string
    {
        $import = $this->import;
        $company = $import->company->name;

        if ($import->status === ImportBatchStatus::Failed) {
            return "\"{$import->original_filename}\" ({$company}) could not be imported: {$import->error_message}";
        }

        return "\"{$import->original_filename}\" ({$company}): ".number_format($import->inserted_rows).' added, '
            .number_format($import->updated_rows).' updated, '.number_format($import->skipped_rows).' skipped, '
            .number_format($import->failed_rows).' rejected.';
    }
}
