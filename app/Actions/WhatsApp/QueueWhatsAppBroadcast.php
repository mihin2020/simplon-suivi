<?php

namespace App\Actions\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppBroadcast;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QueueWhatsAppBroadcast
{
    /**
     * Records the campaign, one row per part (text, then each file) and per recipient, with the status
     * "sending", then hands it to the queue: the job sends every part and updates each status.
     *
     * The text is the caption of the first file when there are files.
     *
     * @param  array{recipients: array<int, array{phone: string, name?: string|null, learner_id?: string|null}>, message?: string|null, formation_name?: string|null, project_name?: string|null}  $data
     * @param  array<int, UploadedFile>  $files
     * @return int Number of recipients
     */
    public function execute(array $data, array $files, User $sender): int
    {
        $broadcastId = (string) Str::uuid();
        $text = $data['message'] ?? '';

        $parts = $files === []
            ? [['message' => $text, 'file' => null]]
            : collect($files)->values()->map(fn (UploadedFile $file, int $index) => ['message' => $index === 0 ? $text : '', 'file' => $file])->all();

        foreach ($parts as $part) {
            $attachment = $part['file'] ? $this->storeAttachment($part['file']) : [];

            foreach ($data['recipients'] as $recipient) {
                WhatsAppMessage::create([
                    'phone' => $recipient['phone'],
                    'recipient_name' => $recipient['name'] ?? null,
                    'message' => $part['message'],
                    'direction' => 'sent',
                    'provider' => 'wwebjs',
                    'status' => WhatsAppMessageStatus::Sending->value,
                    'learner_id' => $recipient['learner_id'] ?? null,
                    'formation_name' => $data['formation_name'] ?? null,
                    'project_name' => $data['project_name'] ?? null,
                    'broadcast_id' => $broadcastId,
                    'attachment_path' => $attachment['path'] ?? null,
                    'attachment_mimetype' => $attachment['mimetype'] ?? null,
                    'attachment_filename' => $attachment['filename'] ?? null,
                ]);
            }
        }

        SendWhatsAppBroadcast::dispatch($broadcastId, (string) $sender->id);

        return count($data['recipients']);
    }

    /**
     * @return array{path: string, mimetype: string|null, filename: string}
     */
    private function storeAttachment(UploadedFile $file): array
    {
        $extension = $file->extension() ?: last(explode('/', $file->getMimeType() ?? 'bin'));
        $storedName = Str::uuid().'.'.$extension;

        Storage::disk('local')->putFileAs('whatsapp_media', $file, $storedName);

        return [
            'path' => 'whatsapp_media/'.$storedName,
            'mimetype' => $file->getMimeType(),
            'filename' => $file->getClientOriginalName(),
        ];
    }
}
