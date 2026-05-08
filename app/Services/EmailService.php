<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;

class EmailService
{
    /**
     * Synchronize inbox from IMAP to local database.
     */
    public function syncInbox(): void
    {
        $client = Client::account('default');
        $client->connect();

        $folder = $client->getFolder('INBOX');
        if (! $folder) {
            $client->disconnect();
            return;
        }

        $lastEmail = Email::received()->orderByDesc('received_at')->first();
        $since = $lastEmail?->received_at?->subDay() ?? now()->subYear();

        $messages = $folder->query()->since($since)->get();

        foreach ($messages as $message) {
            $messageId = $message->getMessageId()?->first();

            if (! $messageId) {
                continue;
            }

            // Skip already synced messages
            if (Email::where('message_id', $messageId)->exists()) {
                continue;
            }

            $fromAttr = $message->getFrom();
            $from = is_array($fromAttr) ? ($fromAttr[0] ?? null) : $fromAttr->first();

            // Validate from email address
            $fromEmail = $from?->mail ?? null;
            if ($fromEmail && ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = 'unknown';
            }

            $toList = collect($message->getTo() ?? [])
                ->map(fn ($r) => ['email' => $r->mail ?? null, 'name' => $r->personal ?? null])
                ->filter(fn ($r) => $r['email'])
                ->values()
                ->toArray();

            $ccList = collect($message->getCc() ?? [])
                ->map(fn ($r) => ['email' => $r->mail ?? null, 'name' => $r->personal ?? null])
                ->filter(fn ($r) => $r['email'])
                ->values()
                ->toArray();

            $threadId = $this->resolveThreadId($message);

            $email = Email::create([
                'message_id' => $messageId,
                'thread_id' => $threadId,
                'direction' => 'received',
                'from_email' => $fromEmail ?? 'unknown',
                'from_name' => $from?->personal ?? null,
                'to' => $toList,
                'cc' => $ccList ?: null,
                'subject' => $message->getSubject()->first() ?? '(no subject)',
                'body_html' => $message->getHTMLBody() ?? '',
                'body_text' => $message->getTextBody() ?? null,
                'is_read' => false,
                'is_archived' => false,
                'received_at' => $message->getDate()?->first()?->toDateTime() ?? now(),
            ]);

            foreach ($message->getAttachments() as $attachment) {
                $filename = $attachment->getName() ?? 'attachment';
                $path = 'email-attachments/' . $email->id . '/' . $filename;
                Storage::disk('local')->put($path, $attachment->getContent());

                EmailAttachment::create([
                    'email_id' => $email->id,
                    'filename' => $filename,
                    'path' => $path,
                    'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream',
                    'size' => strlen($attachment->getContent()),
                ]);
            }
        }

        $client->disconnect();
    }

    /**
     * Send an email via Laravel Mail and store it locally.
     */
    public function sendEmail(array $to, string $subject, string $bodyHtml, ?array $attachments = [], ?string $replyToEmail = null, ?string $sentBy = null, ?array $cc = null, ?string $replyToMessageId = null): Email
    {
        $toEmails = collect($to)->pluck('email')->toArray();
        $ccEmails = $cc ? collect($cc)->pluck('email')->toArray() : [];

        Mail::send([], [], function ($message) use ($toEmails, $ccEmails, $subject, $bodyHtml, $attachments, $replyToEmail) {
            $message->to($toEmails)
                ->subject($subject)
                ->html($bodyHtml);

            if ($ccEmails) {
                $message->cc($ccEmails);
            }

            if ($replyToEmail && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
                $message->replyTo($replyToEmail);
            }

            foreach ($attachments ?? [] as $file) {
                $message->attach($file['path'], ['as' => $file['name']]);
            }
        });

        $threadId = $replyToMessageId ? Email::where('id', $replyToMessageId)->value('thread_id') : (string) \Illuminate\Support\Str::uuid();
        $parentId = $replyToMessageId ?: null;

        $email = Email::create([
            'thread_id' => $threadId,
            'direction' => 'sent',
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => strip_tags($bodyHtml),
            'is_read' => true,
            'is_archived' => false,
            'sent_at' => now(),
            'parent_id' => $parentId,
            'sent_by' => $sentBy,
        ]);

        foreach ($attachments ?? [] as $file) {
            $storedPath = Storage::disk('local')->putFile('email-attachments/' . $email->id, $file['path']);
            EmailAttachment::create([
                'email_id' => $email->id,
                'filename' => $file['name'] ?? basename($storedPath),
                'path' => $storedPath,
                'mime_type' => mime_content_type($file['path']) ?? 'application/octet-stream',
                'size' => filesize($file['path']),
            ]);
        }

        return $email;
    }

    public function markAsRead(string $emailId): void
    {
        $email = Email::findOrFail($emailId);
        $email->update(['is_read' => true]);
    }

    public function archive(string $emailId): void
    {
        $email = Email::findOrFail($emailId);
        $email->update(['is_archived' => true]);
    }

    /**
     * Retrieve threads grouped by thread_id with latest message.
     */
    public function getThreads(string $direction = 'received', bool $archived = false)
    {
        return Email::select('thread_id')
            ->selectRaw('MAX(received_at) as last_at')
            ->when($direction === 'received', fn ($q) => $q->where('direction', 'received'))
            ->when($direction === 'sent', fn ($q) => $q->where('direction', 'sent'))
            ->when(! $archived, fn ($q) => $q->where('is_archived', false))
            ->groupBy('thread_id')
            ->orderByDesc('last_at')
            ->with(['sender'])
            ->paginate(20);
    }

    protected function resolveThreadId($message): string
    {
        $inReplyToAttr = $message->getInReplyTo();
        $inReplyTo = is_array($inReplyToAttr) ? ($inReplyToAttr[0] ?? null) : $inReplyToAttr?->first();
        if ($inReplyTo) {
            $parent = Email::where('message_id', $inReplyTo)->first();
            if ($parent) {
                return $parent->thread_id;
            }
        }

        $refsAttr = $message->getReferences();
        $references = is_array($refsAttr) ? $refsAttr : ($refsAttr?->toArray() ?? []);
        foreach ($references as $ref) {
            $parent = Email::where('message_id', $ref)->first();
            if ($parent) {
                return $parent->thread_id;
            }
        }

        return (string) \Illuminate\Support\Str::uuid();
    }
}
