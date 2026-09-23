<?php

namespace App\Mail;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupCompletedMail extends Mailable implements ShouldQueue
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
            subject: 'Sauvegarde réussie — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        $sizeMb = $this->backup->size_bytes
            ? round($this->backup->size_bytes / 1024 / 1024, 2)
            : null;

        return new Content(
            markdown: 'emails.backup-completed',
            with: [
                'backup' => $this->backup,
                'sizeMb' => $sizeMb,
                'remoteSynced' => $this->backup->remote_synced_at !== null,
                'backupsUrl' => url('/configuration/backups'),
            ],
        );
    }
}
