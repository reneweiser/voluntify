<?php

namespace App\Notifications;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AddedToOrganization extends Notification
{
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Organization $organization,
        public StaffRole $role,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("You've been added to {$this->organization->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("You've been added to **{$this->organization->name}** as **{$this->role->value}**.")
            ->action('Log in now', route('login'))
            ->line('Thank you for joining the team!');

        return $this->applyOrgMailer($mail, $this->organization);
    }
}
