<?php

namespace App\Notifications;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CompanyInvitation $invitation,
        private readonly string $token,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = $this->invitation->company->name;

        return (new MailMessage)
            ->subject("You're invited to join {$company}")
            ->line("You have been invited to join {$company} as {$this->invitation->role}.")
            ->action('Accept invitation', route('invitations.show', $this->token))
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.')
            ->line('If you were not expecting it, you can ignore this email.');
    }
}
