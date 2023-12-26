<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\InlinePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Address;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $subject;
    public $message;
    public $attachments;

    /**
     * Create a new message instance.
     *
     * @param string $name
     * @param string $email
     * @param string $subject
     * @param string $message
     * @param mixed  $attachments
     */
    public function __construct($name, $email, $subject, $message, $attachments)
    {
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
        $this->message = $message;
        $this->attachments = $attachments;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Get the message hierarchy from email view resourcses/views/emails/contact-form.blade.php
        $message = $this->markdown('emails.contact-form') 
            ->from($this->email, $this->name);

        // Attach the screenshot
        if ($this->attachments) {
            $message->attachData(
                file_get_contents($this->attachments->getRealPath()),
                $this->attachments->getClientOriginalName(),
                [
                    'mime' => $this->attachments->getClientMimeType(),
                ]
            );
        }

        return $message;
    }
}
