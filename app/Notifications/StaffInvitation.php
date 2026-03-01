<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public Organization $organization,
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
        return (new MailMessage)
            ->subject("You've been invited to {$this->organization->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("You've been invited to join **{$this->organization->name}** on Voluntify.")
            ->line("Your temporary password is: **{$this->temporaryPassword}**")
            ->line('You will be asked to change your password when you first log in.')
            ->action('Log in now', url('/login'))
            ->line('Thank you for joining the team!');
    }
}
