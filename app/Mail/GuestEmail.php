<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A plain message a staff member wrote to a guest from the CRM
 * (see App\Domain\Crm\GuestMessenger::sendEmail()).
 */
class GuestEmail extends Mailable
{
    public function __construct(public readonly string $emailSubject, public readonly string $emailBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.guest-message');
    }
}
