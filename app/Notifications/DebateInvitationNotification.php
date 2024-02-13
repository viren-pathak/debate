<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DebateInvitationNotification extends Notification
{
    use Queueable;


    protected $invitationLink;
    protected $role;

    /**
     * Create a new notification instance.
     *
     * @param string $invitationLink
     * @param string $role
     */
    public function __construct($invitationLink, $role)
    {
        $this->invitationLink = $invitationLink;
        $this->role = $role;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                ->subject('Invitation to Join Debate')
                ->line('You have been invited to join a debate.')
                ->action('Join Debate', $this->invitationLink)
                ->line('Your role in the debate will be: ' . $this->role);
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
