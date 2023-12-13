<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends Notification
{
    public $verificationToken;

    public function __construct($verificationToken)
    {
        $this->verificationToken = $verificationToken;
    }

    public function toMail($notifiable)
    {
        $url = url("/api/verify-email/{$this->verificationToken}");

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->markdown('emails.verify-email', ['verificationUrl' => $url]);
    }

    public function via($notifiable)
    {
        return ['mail'];
    }
}
