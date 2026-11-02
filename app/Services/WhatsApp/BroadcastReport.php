<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Read model of the WhatsApp campaigns ("broadcasts"): one summary per campaign, and the recipients of one
 * campaign with their replies.
 */
class BroadcastReport
{
    private const NO_TEXT_PREVIEW = '📎 Fichier(s) joint(s)';

    /**
     * One entry per campaign, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summaries(): Collection
    {
        $sent = WhatsAppMessage::where('direction', 'sent')
            ->whereNotNull('broadcast_id')
            ->where('provider', 'wwebjs')
            ->orderBy('created_at', 'desc')
            ->get(['broadcast_id', 'formation_name', 'project_name', 'message', 'created_at', 'learner_id', 'status', 'attachment_path']);

        $replies = WhatsAppMessage::where('direction', 'received')
            ->whereNotNull('learner_id')
            ->get(['learner_id', 'created_at', 'read_at']);

        return $sent->groupBy('broadcast_id')
            ->map(fn (Collection $messages) => $this->summarize($messages, $replies))
            ->values()
            ->sortByDesc('sent_at')
            ->values();
    }

    /**
     * Recipients of one campaign, those who replied first. Empty for an unknown campaign.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recipients(string $broadcastId): Collection
    {
        $sent = WhatsAppMessage::with('learner:id,first_name,last_name')
            ->where('broadcast_id', $broadcastId)
            ->where('direction', 'sent')
            ->orderBy('created_at')
            ->get(['id', 'phone', 'recipient_name', 'status', 'error', 'learner_id', 'created_at']);

        if ($sent->isEmpty()) {
            return collect();
        }

        $repliesByLearner = WhatsAppMessage::where('direction', 'received')
            ->whereIn('learner_id', $sent->pluck('learner_id')->filter()->unique()->toArray())
            ->where('created_at', '>=', $sent->min('created_at'))
            ->orderBy('created_at')
            ->get(['id', 'message', 'learner_id', 'created_at', 'read_at'])
            ->groupBy('learner_id');

        // N files sent to one person are one recipient: the first message gives the name, phone and status.
        return $sent
            ->groupBy(fn ($message) => $message->learner_id ?? $message->phone)
            ->map(fn ($messages) => $this->recipient($messages->first(), $repliesByLearner))
            ->sortByDesc('has_replied')
            ->values();
    }

    /**
     * @param  Collection<int, WhatsAppMessage>  $messages  Every message of one campaign
     * @param  Collection<int, WhatsAppMessage>  $replies  Every reply received from a learner
     * @return array<string, mixed>
     */
    private function summarize(Collection $messages, Collection $replies): array
    {
        $first = $messages->first();
        $sentAt = $messages->min('created_at');
        $learnerIds = $messages->pluck('learner_id')->filter()->unique();

        // Only unread replies that arrived after this specific campaign.
        $replyCount = $learnerIds->filter(
            fn ($id) => $replies->where('learner_id', $id)
                ->where('created_at', '>=', $sentAt)
                ->whereNull('read_at')
                ->isNotEmpty()
        )->count();

        $withText = $messages->first(fn ($message) => ! empty(trim($message->message)));
        $preview = $withText ? $withText->message : ($first->attachment_path ? self::NO_TEXT_PREVIEW : '');

        $failed = $messages->where('status', 'failed');

        return [
            'broadcast_id' => $first->broadcast_id,
            'formation_name' => $first->formation_name,
            'project_name' => $first->project_name,
            'message' => $preview,
            'sent_at' => $first->created_at,
            // Unique recipients: N files to one person count once.
            'recipient_count' => $learnerIds->count() ?: $messages->pluck('phone')->unique()->count(),
            'reply_count' => $replyCount,
            'failed_count' => $failed->pluck('learner_id')->filter()->unique()->count()
                ?: $failed->pluck('phone')->unique()->count(),
            // Still going out in the background (SendWhatsAppBroadcast).
            'sending_count' => $messages->where('status', WhatsAppMessageStatus::Sending->value)->pluck('phone')->unique()->count(),
        ];
    }

    /**
     * @param  Collection<int|string, EloquentCollection<int, WhatsAppMessage>>  $repliesByLearner
     * @return array<string, mixed>
     */
    private function recipient(WhatsAppMessage $message, Collection $repliesByLearner): array
    {
        $replies = $repliesByLearner->get($message->learner_id, collect());
        $lastReply = $replies->last();

        return [
            'id' => $message->id,
            'phone' => $message->phone,
            'learner_id' => $message->learner_id,
            'name' => $message->learner
                ? "{$message->learner->first_name} {$message->learner->last_name}"
                : ($message->recipient_name ?? $message->phone),
            'status' => $message->status,
            'error' => $message->error,
            'sent_at' => $message->created_at,
            'has_replied' => $replies->isNotEmpty(),
            'reply_count' => $replies->whereNull('read_at')->count(),
            'last_reply' => $lastReply ? [
                'message' => $lastReply->message,
                'created_at' => $lastReply->created_at,
            ] : null,
        ];
    }
}
