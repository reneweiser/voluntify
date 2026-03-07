<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Notifications\Concerns\UsesOrganizationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerPromoted extends Notification
{
    use Queueable;
    use UsesOrganizationMailer;

    public function __construct(
        public Organization $organization,
        public string $roleName,
        public string $temporaryPassword,
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
            ->subject("You've been promoted to staff at {$this->organization->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("You've been promoted to **{$this->roleName}** at **{$this->organization->name}** on Voluntify.")
            ->line("Your temporary password is: **{$this->temporaryPassword}**")
            ->line('You will be asked to change your password when you first log in.')
            ->action('Log in now', route('login'))
            ->line('Thank you for being a great volunteer!');

        return $this->applyOrgMailer($mail, $this->organization);
    }
}
