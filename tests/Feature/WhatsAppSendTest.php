<?php

namespace Tests\Feature;

use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppBroadcast;
use App\Models\Learner;
use App\Models\WhatsAppMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pins how a WhatsApp campaign is recorded before the background job sends it. */
class WhatsAppSendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');
        Http::fake(['*/status' => Http::response(['connected' => true])]);
    }

    private function send(array $payload)
    {
        return $this->actingAsSuperAdmin()->post(route('whatsapp.send'), $payload);
    }

    public function test_the_text_is_recorded_for_every_recipient_as_sending(): void
    {
        $learner = Learner::factory()->create();

        $this->send([
            'recipients' => [
                ['phone' => '+226 70 00 00 01', 'name' => 'Awa', 'learner_id' => $learner->id],
                ['phone' => '+226 70 00 00 02'],
            ],
            'message' => 'Bonjour',
            'formation_name' => 'Dev Web',
            'project_name' => 'Projet X',
        ])->assertSessionHas('success', "Envoi de 2 message(s) WhatsApp en cours. Le suivi s'affiche dans l'historique.");

        $messages = WhatsAppMessage::orderBy('phone')->get();
        $this->assertCount(2, $messages);
        $this->assertCount(1, $messages->pluck('broadcast_id')->unique());

        $first = $messages[0];
        $this->assertSame('sent', $first->direction);
        $this->assertSame('wwebjs', $first->provider);
        $this->assertSame(WhatsAppMessageStatus::Sending->value, $first->status);
        $this->assertSame('Awa', $first->recipient_name);
        $this->assertSame($learner->id, $first->learner_id);
        $this->assertSame('Dev Web', $first->formation_name);
        $this->assertSame('Projet X', $first->project_name);
        $this->assertNull($first->attachment_path);
        $this->assertNull($messages[1]->recipient_name);
        $this->assertNull($messages[1]->learner_id);

        Queue::assertPushed(SendWhatsAppBroadcast::class, fn ($job) => $job->broadcastId === $first->broadcast_id);
    }

    public function test_each_attachment_is_a_separate_part_and_only_the_first_carries_the_text(): void
    {
        $this->send([
            'recipients' => [['phone' => '70000001', 'name' => 'Awa'], ['phone' => '70000002', 'name' => 'Issa']],
            'message' => 'Voici les documents',
            'attachments' => [
                UploadedFile::fake()->create('programme.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('planning.pdf', 20, 'application/pdf'),
            ],
        ])->assertSessionHas('success');

        $messages = WhatsAppMessage::all();
        $this->assertCount(4, $messages, '2 parts x 2 recipients');
        $this->assertCount(1, $messages->pluck('broadcast_id')->unique());

        $firstPart = $messages->where('attachment_filename', 'programme.pdf');
        $secondPart = $messages->where('attachment_filename', 'planning.pdf');
        $this->assertCount(2, $firstPart);
        $this->assertCount(2, $secondPart);
        $this->assertSame(['Voici les documents'], $firstPart->pluck('message')->unique()->values()->all());
        $this->assertSame([''], $secondPart->pluck('message')->unique()->values()->all());

        // One stored file per part, shared by all recipients, on the private disk.
        $this->assertCount(1, $firstPart->pluck('attachment_path')->unique());
        $path = $firstPart->first()->attachment_path;
        $this->assertStringStartsWith('whatsapp_media/', $path);
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertSame('application/pdf', $firstPart->first()->attachment_mimetype);
        Storage::disk('local')->assertExists($path);
    }

    public function test_the_request_is_validated_before_anything_is_recorded(): void
    {
        $this->send(['recipients' => [], 'message' => 'x'])->assertSessionHasErrors('recipients');
        $this->send(['recipients' => [['name' => 'sans telephone']]])->assertSessionHasErrors('recipients.0.phone');
        $this->send(['recipients' => [['phone' => '1', 'learner_id' => 'not-a-uuid']]])->assertSessionHasErrors('recipients.0.learner_id');
        $this->send([
            'recipients' => [['phone' => '1']],
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, WhatsAppMessage::count());
        Queue::assertNothingPushed();
    }

    public function test_sending_needs_the_whatsapp_send_permission(): void
    {
        $this->actingAsTrainer()
            ->post(route('whatsapp.send'), ['recipients' => [['phone' => '1']]])
            ->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::count());
    }
}
