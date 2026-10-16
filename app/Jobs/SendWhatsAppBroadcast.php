<?php

namespace App\Jobs;

use App\Enums\WhatsAppMessageStatus;
use App\Models\Notification;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppWWebService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sends a WhatsApp broadcast in the background. The messages are recorded
 * beforehand with the status "sending"; each one becomes "sent" or "failed"
 * as the WhatsApp service answers, which the page follows by polling.
 */
class SendWhatsAppBroadcast implements ShouldQueue
{
    use Queueable;

    /** Never retried: people already reached would get the message twice. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public string $broadcastId,
        public string $userId,
    ) {}

    public function handle(WhatsAppWWebService $service): void
    {
        $sent = 0;
        $failed = 0;

        // One send per part: the text (caption of the first file), then each file.
        foreach ($this->pendingParts() as $messages) {
            $first = $messages->first();
            $results = $service->sendBulk(
                $messages->map(fn (WhatsAppMessage $m) => ['phone' => $m->phone, 'name' => $m->recipient_name, 'learner_id' => $m->learner_id])->values()->all(),
                (string) $first->message,
                $this->attachmentPayload($first),
            );

            $errorsByPhone = collect($results['errors'] ?? [])
                ->mapWithKeys(fn ($e) => [preg_replace('/\D/', '', $e['phone'] ?? '') => $e['error'] ?? 'Échec inconnu'])
                ->all();
            // A failure of the whole call carries no phone: every message of the part failed.
            $globalError = isset($errorsByPhone['']) && ($results['success'] ?? 0) === 0 ? $errorsByPhone[''] : null;
            $sentIds = $results['sentIds'] ?? [];

            foreach ($messages as $message) {
                $phone = preg_replace('/\D/', '', $message->phone);
                $error = $errorsByPhone[$phone] ?? $globalError;

                $message->update([
                    'status' => $error ? WhatsAppMessageStatus::Failed->value : WhatsAppMessageStatus::Sent->value,
                    'error' => $error,
                    'external_id' => $sentIds[$phone] ?? null,
                ]);
                $error ? $failed++ : $sent++;
            }
        }

        Log::info('WhatsApp broadcast sent', ['broadcast_id' => $this->broadcastId, 'sent' => $sent, 'failed' => $failed]);

        $this->notify(
            $failed === 0 ? 'Messages WhatsApp envoyés' : 'Envoi WhatsApp terminé avec des échecs',
            $failed === 0 ? "{$sent} message(s) WhatsApp envoyé(s)." : "{$sent} envoyé(s), {$failed} échec(s).",
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('WhatsApp broadcast failed', ['broadcast_id' => $this->broadcastId, 'error' => $exception?->getMessage()]);

        WhatsAppMessage::where('broadcast_id', $this->broadcastId)
            ->where('status', WhatsAppMessageStatus::Sending->value)
            ->update(['status' => WhatsAppMessageStatus::Failed->value, 'error' => 'Envoi interrompu : '.($exception?->getMessage() ?? 'erreur inconnue')]);

        $this->notify('Échec de l\'envoi WhatsApp', 'L\'envoi groupé a été interrompu. Les messages non partis sont marqués en échec.');
    }

    /**
     * Messages still to send, grouped by part, in the order they were recorded.
     *
     * @return Collection<string, Collection<int, WhatsAppMessage>>
     */
    private function pendingParts(): Collection
    {
        return WhatsAppMessage::where('broadcast_id', $this->broadcastId)
            ->where('status', WhatsAppMessageStatus::Sending->value)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WhatsAppMessage $m) => (string) $m->attachment_path);
    }

    /**
     * @return array{data: string, mimetype: ?string, filename: ?string}|null
     */
    private function attachmentPayload(WhatsAppMessage $message): ?array
    {
        if (! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path)) {
            return null;
        }

        return [
            'data' => base64_encode(Storage::disk('local')->get($message->attachment_path)),
            'mimetype' => $message->attachment_mimetype,
            'filename' => $message->attachment_filename,
        ];
    }

    private function notify(string $title, string $message): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'type' => 'whatsapp_broadcast_sent',
            'title' => $title,
            'message' => $message,
            'data' => ['route' => route('whatsapp.index')],
        ]);
    }
}
