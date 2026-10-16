<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends an email composed in the messaging module, in the background: a
 * mailing to hundreds of recipients goes out in batches of SMTP calls that
 * would otherwise hold a web worker for minutes.
 */
class SendBulkEmail implements ShouldQueue
{
    use Queueable;

    /** Never retried: a retry would send the first batches a second time. */
    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  list<array{email: string, name?: ?string}>  $to
     * @param  list<array{path: string, name: string}>  $attachments  temporary files, deleted afterwards
     * @param  list<array{email: string}>|null  $cc
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $body,
        public array $attachments,
        public ?array $cc,
        public string $userId,
    ) {}

    public function handle(EmailService $emails): void
    {
        $emails->sendEmail($this->to, $this->subject, $this->body, $this->attachments, null, $this->userId, $this->cc, null);

        $count = count($this->to);
        Log::info('Bulk email sent', ['recipients' => $count, 'user_id' => $this->userId]);

        $this->notify('Email envoyé', "« {$this->subject} » a été envoyé à {$count} destinataire(s).");
        $this->deleteAttachments();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Bulk email failed', ['recipients' => count($this->to), 'error' => $exception?->getMessage()]);

        $this->notify(
            'Échec de l\'envoi de l\'email',
            "« {$this->subject} » n'a pas pu être envoyé à tous les destinataires (".($exception?->getMessage() ?? 'erreur inconnue')
                .'). Les premiers lots ont pu partir avant l\'erreur : vérifiez avant de renvoyer.',
        );
        $this->deleteAttachments();
    }

    private function notify(string $title, string $message): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'type' => 'email_sent',
            'title' => $title,
            'message' => $message,
            'data' => ['route' => route('emails.sent')],
        ]);
    }

    /** The files were copied with the sent email; the upload copies are no longer needed. */
    private function deleteAttachments(): void
    {
        foreach ($this->attachments as $file) {
            if (is_file($file['path'])) {
                @unlink($file['path']);
            }
        }
    }
}
