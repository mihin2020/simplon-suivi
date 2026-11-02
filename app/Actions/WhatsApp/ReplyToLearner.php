<?php

namespace App\Actions\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use App\Exceptions\WhatsAppReplyRefused;
use App\Models\Learner;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppWWebService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReplyToLearner
{
    private const MEDIA_DIRECTORY = 'whatsapp_media';

    public function __construct(private readonly WhatsAppWWebService $service) {}

    /**
     * Sends a message to the learner and records it, whether the service accepted it or not.
     *
     * @return array{success: bool, error: string|null}
     *
     * @throws WhatsAppReplyRefused when there is nothing to send or nowhere to send it.
     */
    public function execute(Learner $learner, ?string $message, ?UploadedFile $attachment): array
    {
        if (blank($message) && ! $attachment) {
            throw WhatsAppReplyRefused::emptyReply();
        }

        if (! $learner->phone) {
            throw WhatsAppReplyRefused::learnerWithoutPhone();
        }

        $name = "{$learner->first_name} {$learner->last_name}";
        $stored = $attachment ? $this->storeAttachment($attachment) : null;

        $results = $this->service->sendBulk(
            [['phone' => $learner->phone, 'name' => $name, 'learner_id' => $learner->id]],
            $message ?? '',
            $stored['payload'] ?? null,
        );

        $hasFailed = ($results['success'] ?? 0) === 0;
        $error = $hasFailed ? ($results['errors'][0]['error'] ?? 'Échec envoi') : null;

        WhatsAppMessage::create([
            'phone' => $learner->phone,
            'recipient_name' => $name,
            'message' => $message ?? '',
            'direction' => 'sent',
            'provider' => 'wwebjs',
            'status' => $hasFailed ? WhatsAppMessageStatus::Failed->value : WhatsAppMessageStatus::Sent->value,
            'error' => $error,
            'learner_id' => $learner->id,
            'broadcast_id' => null,
            'attachment_path' => $stored['path'] ?? null,
            'attachment_mimetype' => $stored['mimetype'] ?? null,
            'attachment_filename' => $stored['filename'] ?? null,
            'external_id' => $results['sentIds'][preg_replace('/\D/', '', $learner->phone)] ?? null,
        ]);

        return ['success' => ! $hasFailed, 'error' => $error];
    }

    /**
     * Keeps the file so the conversation can show it, and prepares what the service needs to send it.
     *
     * @return array{path: string, mimetype: string|null, filename: string, payload: array{data: string, mimetype: string|null, filename: string}}
     */
    private function storeAttachment(UploadedFile $file): array
    {
        $extension = $file->extension() ?: last(explode('/', $file->getMimeType() ?? 'application/bin'));
        $storedName = Str::uuid().'.'.$extension;
        $mimetype = $file->getMimeType();
        $filename = $file->getClientOriginalName();

        Storage::disk('local')->putFileAs(self::MEDIA_DIRECTORY, $file, $storedName);

        return [
            'path' => self::MEDIA_DIRECTORY.'/'.$storedName,
            'mimetype' => $mimetype,
            'filename' => $filename,
            'payload' => [
                'data' => base64_encode(file_get_contents($file->getRealPath())),
                'mimetype' => $mimetype,
                'filename' => $filename,
            ],
        ];
    }
}
