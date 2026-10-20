<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A plain operational notice for the platform owner (see AdminAlert).
 */
class OperationalAlert extends Mailable
{
    public function __construct(public readonly string $alertSubject, public readonly string $alertBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.config('app.name').'] '.$this->alertSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.operational-alert');
    }
}
