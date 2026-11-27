<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NeutralNoticeMail extends Mailable
{
    public function __construct(public string $link)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ReferralFlow: an update is waiting');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.notice');
    }
}
