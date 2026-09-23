<?php

namespace App\Mail;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Backup $backup)
    {
        $this->onConnection('database');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Échec de sauvegarde — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.backup-failed',
            with: [
                'backup' => $this->backup,
                'backupsUrl' => url('/configuration/backups'),
            ],
        );
    }
}
