<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Billing events for company owners (invoice issued/paid, payment overdue,
 * plan ended) and upgrade requests for platform admins.
 */
class BillingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?Company $company,
        private readonly string $title,
        private readonly string $message,
        private readonly string $routeName,
        private readonly array $routeParameters = [],
        private readonly string $level = 'info',
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
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('Open', route($this->routeName, $this->routeParameters));

        return $this->level === 'danger' ? $mail->error() : $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->company?->id,
            'title' => $this->title,
            'message' => $this->message,
            'url' => route($this->routeName, $this->routeParameters, absolute: false),
            'level' => $this->level,
        ];
    }
}
