<?php

namespace App\Services\Mail;

use App\Models\Email;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The paginated lists of the mailbox. Everything a row needs is loaded for the whole page at once
 * (a handful of queries), not one query per row.
 */
class MailboxListing
{
    private const PER_PAGE = 15;

    private const SNIPPET_LENGTH = 140;

    /**
     * Received threads, most recently active first.
     */
    public function inboxThreads(string $filter, string $search): LengthAwarePaginator
    {
        $threads = Email::received()
            ->where('is_archived', $filter === 'archived')
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($sub) use ($like) {
                    $sub->where('subject', 'like', $like)
                        ->orWhere('from_name', 'like', $like)
                        ->orWhere('from_email', 'like', $like)
                        ->orWhere('body_text', 'like', $like);
                });
            })
            ->select('thread_id')
            // A grouped MAX of a COALESCE cannot be expressed without a raw select; no user input in it.
            ->selectRaw('MAX(COALESCE(received_at, created_at)) as last_at')
            ->groupBy('thread_id')
            ->orderByDesc('last_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $threadIds = $threads->pluck('thread_id')->all();

        // Light rows: newest received first, to find each thread's last e-mail and count the replies.
        $received = Email::received()
            ->whereIn('thread_id', $threadIds)
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->get(['id', 'thread_id'])
            ->groupBy('thread_id');

        $lastEmails = Email::whereIn('id', $received->map(fn ($emails) => $emails->first()->id)->values())
            ->withExists('attachments')
            ->get()
            ->keyBy('thread_id');

        // The subject of the first e-mail we sent in each thread (ids are time-ordered).
        $sentSubjects = Email::sent()
            ->whereIn('thread_id', $threadIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['thread_id', 'subject'])
            ->unique('thread_id')
            ->pluck('subject', 'thread_id');

        return $threads->through(function ($thread) use ($received, $lastEmails, $sentSubjects) {
            $last = $lastEmails->get($thread->thread_id);

            return [
                'thread_id' => $thread->thread_id,
                'reply_count' => $received->get($thread->thread_id)?->count() ?? 0,
                'sent_subject' => $sentSubjects->get($thread->thread_id),
                'has_attachments' => (bool) $last?->attachments_exists,
                'snippet' => $this->snippet($last),
                'last_email' => $last?->only([
                    'id',
                    'from_name',
                    'from_email',
                    'subject',
                    'is_read',
                    'received_at',
                    'created_at',
                ]),
            ];
        });
    }

    /**
     * Sent e-mails, newest first.
     */
    public function sentEmails(string $search): LengthAwarePaginator
    {
        return Email::sent()
            ->where('is_archived', false)
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($sub) use ($like) {
                    $sub->where('subject', 'like', $like)
                        ->orWhere('body_text', 'like', $like)
                        ->orWhere('to', 'like', $like);
                });
            })
            ->withExists('attachments')
            ->orderByDesc('sent_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Email $email) => [
                ...$email->only(['id', 'subject', 'to', 'sent_at']),
                'has_attachments' => (bool) $email->attachments_exists,
                'snippet' => $this->snippet($email),
            ]);
    }

    /** A short plain-text preview of the body, like the list of a webmail. */
    private function snippet(?Email $email): string
    {
        if (! $email) {
            return '';
        }

        // No text version: fall back on the HTML without its tags.
        $text = $email->body_text ?: strip_tags((string) $email->body_html);

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $text)), self::SNIPPET_LENGTH);
    }
}
