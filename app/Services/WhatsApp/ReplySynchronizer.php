<?php

namespace App\Services\WhatsApp;

use App\Models\Learner;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppWWebService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pulls the replies received by the WhatsApp microservice into the platform, and applies the messages
 * the learners revoked on their side.
 */
class ReplySynchronizer
{
    private const LAST_INCOMING_KEY = 'wa_last_incoming_ts';

    private const LAST_REVOKE_KEY = 'wa_last_revoke_sync';

    /** Overlap with the previous window: the wa_id de-duplication makes it harmless and loses nothing. */
    private const WINDOW_MARGIN_SECONDS = 15;

    /** A shorter number would make the LIKE match almost anybody. */
    private const MIN_PHONE_DIGITS = 8;

    public function __construct(private readonly WhatsAppWWebService $whatsApp) {}

    /**
     * @return array{synced: int, revoked?: int}
     */
    public function synchronize(): array
    {
        // Only learners we already wrote to from this platform can reply into it.
        $messagedLearnerIds = WhatsAppMessage::where('direction', 'sent')
            ->whereNotNull('learner_id')
            ->pluck('learner_id')
            ->unique()
            ->toArray();

        if (empty($messagedLearnerIds)) {
            return ['synced' => 0];
        }

        return [
            'synced' => $this->storeIncomingReplies($messagedLearnerIds),
            'revoked' => $this->applyRevocations(),
        ];
    }

    /**
     * @param  array<int, string>  $messagedLearnerIds
     */
    private function storeIncomingReplies(array $messagedLearnerIds): int
    {
        // The window follows the real WhatsApp timestamp (same clock as the microservice), not the time the
        // server saved the message: that delay used to push the window too far ahead and lose quick replies.
        $lastTimestamp = (int) Cache::get(self::LAST_INCOMING_KEY, 0);
        $since = $lastTimestamp > 0 ? max(0, $lastTimestamp - self::WINDOW_MARGIN_SECONDS) : 0;

        $maxTimestamp = $lastTimestamp;
        $saved = 0;

        foreach ($this->whatsApp->getIncoming($since) as $message) {
            // The window advances on every message seen, including the ones ignored below.
            $maxTimestamp = max($maxTimestamp, (int) ($message['timestamp'] ?? 0));

            if ($this->storeReply($message, $messagedLearnerIds)) {
                $saved++;
            }
        }

        if ($maxTimestamp > $lastTimestamp) {
            Cache::put(self::LAST_INCOMING_KEY, $maxTimestamp, now()->addDays(7));
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<int, string>  $messagedLearnerIds
     */
    private function storeReply(array $message, array $messagedLearnerIds): bool
    {
        $from = (string) ($message['from'] ?? '');

        // Never record a status, a group or a broadcast, even if the microservice lets some noise through.
        if ($from === '' || str_contains($from, 'broadcast') || str_contains($from, 'status') || str_contains($from, '@g.us')) {
            return false;
        }

        $shortPhone = preg_replace('/^226/', '', preg_replace('/\D/', '', $from));

        if (strlen($shortPhone) < self::MIN_PHONE_DIGITS) {
            return false;
        }

        // wa_id is unique; old messages only have a timestamp.
        $externalId = $message['wa_id'] ?? (string) $message['timestamp'];

        if (WhatsAppMessage::where('external_id', $externalId)->where('direction', 'received')->exists()) {
            return false;
        }

        $learner = $this->findMessagedLearner($messagedLearnerIds, $shortPhone, $from);

        if (! $learner) {
            return false;
        }

        WhatsAppMessage::create([
            'phone' => $from,
            'recipient_name' => "{$learner->first_name} {$learner->last_name}",
            'message' => $message['body'] ?? '',
            'direction' => 'received',
            'provider' => 'wwebjs',
            'status' => 'received',
            'external_id' => $externalId,
            'learner_id' => $learner->id,
            ...$this->storeAttachment($message),
        ]);

        return true;
    }

    /**
     * Phones are stored in several formats (local, national, international): try each variant.
     *
     * @param  array<int, string>  $messagedLearnerIds
     */
    private function findMessagedLearner(array $messagedLearnerIds, string $shortPhone, string $from): ?Learner
    {
        $withPrefix = '226'.ltrim($shortPhone, '0');
        $withPlusPrefix = '+226'.ltrim($shortPhone, '0');

        return Learner::whereIn('id', $messagedLearnerIds)
            ->where(function ($query) use ($shortPhone, $from, $withPrefix, $withPlusPrefix) {
                $query->where('phone', 'like', "%{$shortPhone}")
                    ->orWhere('phone', $from)
                    ->orWhere('phone', $withPrefix)
                    ->orWhere('phone', $withPlusPrefix);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{attachment_path: string|null, attachment_mimetype: string|null, attachment_filename: string|null}
     */
    private function storeAttachment(array $message): array
    {
        $none = ['attachment_path' => null, 'attachment_mimetype' => null, 'attachment_filename' => null];

        if (empty($message['attachment_data']) || empty($message['attachment_mimetype'])) {
            return $none;
        }

        // "audio/ogg; codecs=opus" -> "audio/ogg"
        $mime = trim(explode(';', $message['attachment_mimetype'])[0]);
        $extension = preg_replace('/[^a-z0-9]/i', '', last(explode('/', $mime))) ?: 'bin';
        $name = Str::uuid().'.'.$extension;

        Storage::disk('local')->put('whatsapp_media/'.$name, base64_decode($message['attachment_data']));

        return [
            'attachment_path' => 'whatsapp_media/'.$name,
            'attachment_mimetype' => $mime,
            'attachment_filename' => $message['attachment_filename'] ?? $name,
        ];
    }

    /** Messages deleted by the learner on their phone are deleted here too. */
    private function applyRevocations(): int
    {
        $revoked = $this->whatsApp->getRevokedMessages(Cache::get(self::LAST_REVOKE_KEY, ''));
        $deleted = 0;

        foreach ($revoked as $revocation) {
            $waId = $revocation['wa_id'] ?? null;

            if (! $waId) {
                continue;
            }

            $message = WhatsAppMessage::where('external_id', $waId)->whereNull('deleted_at')->first();

            if ($message) {
                $message->delete();
                $deleted++;
            }
        }

        if (! empty($revoked)) {
            Cache::put(self::LAST_REVOKE_KEY, now()->toISOString(), now()->addDay());
        }

        return $deleted;
    }
}
