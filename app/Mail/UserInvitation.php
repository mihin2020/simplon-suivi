<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation à rejoindre Simplon BF',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-invitation',
            with: [
                'activationUrl' => url('/activation/' . $this->plainToken),
                'expiresIn'     => '72 heures',
            ],
        );
    }
}
