<?php

namespace App\Services\Mail;

use App\Models\Email;

/** The four counters shown beside the mailbox folders (inbox, archived, sent, unread). */
class MailboxCounts
{
    /**
     * @return array{inboxCount: int, archivedCount: int, sentCount: int, unreadCount: int}
     */
    public function all(): array
    {
        return [
            'inboxCount' => $this->receivedThreads(archived: false),
            'archivedCount' => $this->receivedThreads(archived: true),
            'sentCount' => Email::sent()->where('is_archived', false)->count(),
            'unreadCount' => Email::received()
                ->where('is_archived', false)
                ->where('is_read', false)
                ->distinct('thread_id')
                ->count('thread_id'),
        ];
    }

    private function receivedThreads(bool $archived): int
    {
        return Email::received()
            ->where('is_archived', $archived)
            ->distinct('thread_id')
            ->count('thread_id');
    }
}
