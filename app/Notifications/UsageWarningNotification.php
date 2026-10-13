<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to company owners once a month when imports pass 80% of the plan's
 * monthly row allowance.
 */
class UsageWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Company $company,
        private readonly int $used,
        private readonly int $limit,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->company->name} has used {$this->percent()}% of its monthly imports")
            ->line($this->message())
            ->action('View usage', route('usage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->company->id,
            'title' => "{$this->percent()}% of monthly imports used",
            'message' => $this->message(),
            'url' => route('usage', absolute: false),
            'level' => 'warning',
        ];
    }

    private function percent(): int
    {
        return (int) floor($this->used / max(1, $this->limit) * 100);
    }

    private function message(): string
    {
        return "{$this->company->name} has imported ".number_format($this->used).' of '.number_format($this->limit)
            .' rows allowed this month. New imports will be blocked once the limit is reached.';
    }
}
