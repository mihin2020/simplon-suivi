<?php

namespace Tests\Feature;

use App\Enums\WhatsAppMessageStatus;
use App\Models\Learner;
use App\Models\WhatsAppMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pins the answer sent from the conversation of a learner. */
class WhatsAppReplyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAsSuperAdmin();
    }

    private function reply(array $payload)
    {
        return $this->postJson(route('whatsapp.reply'), $payload);
    }

    private function learner(array $attributes = []): Learner
    {
        return Learner::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traoré', 'phone' => '+226 70 00 00 01', ...$attributes]);
    }

    public function test_a_text_is_sent_and_recorded(): void
    {
        Http::fake(['*/send-bulk' => Http::response(['success' => 1, 'failed' => 0, 'sentIds' => ['22670000001' => 'wa-123']])]);
        $learner = $this->learner();

        $this->reply(['learner_id' => $learner->id, 'message' => 'Bonjour Awa'])
            ->assertOk()->assertExactJson(['success' => true, 'error' => null]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/send-bulk')
            && $request['message'] === 'Bonjour Awa'
            && $request['recipients'] === [['phone' => '+226 70 00 00 01', 'name' => 'Awa Traoré', 'learner_id' => $learner->id]]
            && ! isset($request['attachment']));

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame('Bonjour Awa', $message->message);
        $this->assertSame('sent', $message->direction);
        $this->assertSame('wwebjs', $message->provider);
        $this->assertSame(WhatsAppMessageStatus::Sent->value, $message->status);
        $this->assertSame('Awa Traoré', $message->recipient_name);
        $this->assertSame($learner->id, $message->learner_id);
        $this->assertNull($message->broadcast_id);
        $this->assertNull($message->error);
        $this->assertSame('wa-123', $message->external_id, 'the id given by WhatsApp lets the message be revoked later');
        $this->assertNull($message->attachment_path);
    }

    public function test_a_failure_of_the_service_is_recorded_and_reported(): void
    {
        Http::fake(['*/send-bulk' => Http::response(['success' => 0, 'failed' => 1, 'errors' => [['error' => 'Numéro invalide']]])]);

        $this->reply(['learner_id' => $this->learner()->id, 'message' => 'Bonjour'])
            ->assertOk()->assertExactJson(['success' => false, 'error' => 'Numéro invalide']);

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame(WhatsAppMessageStatus::Failed->value, $message->status);
        $this->assertSame('Numéro invalide', $message->error);
        $this->assertNull($message->external_id);
    }

    public function test_an_unreachable_service_is_a_recorded_failure(): void
    {
        Http::fake(['*/send-bulk' => Http::response('', 500)]);

        $this->reply(['learner_id' => $this->learner()->id, 'message' => 'Bonjour'])
            ->assertOk()->assertJson(['success' => false, 'error' => 'Échec envoi']);

        $this->assertSame(WhatsAppMessageStatus::Failed->value, WhatsAppMessage::firstOrFail()->status);
    }

    public function test_an_attachment_is_kept_and_sent_in_base64(): void
    {
        Http::fake(['*/send-bulk' => Http::response(['success' => 1])]);
        $file = UploadedFile::fake()->createWithContent('programme.pdf', '%PDF-1.4 test');

        $this->reply(['learner_id' => $this->learner()->id, 'attachment' => $file])->assertOk()->assertJson(['success' => true]);

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame('', $message->message, 'a message made of an attachment only has an empty text');
        $this->assertSame('programme.pdf', $message->attachment_filename);
        $this->assertSame('application/pdf', $message->attachment_mimetype);
        $this->assertMatchesRegularExpression('#^whatsapp_media/[0-9a-f-]{36}\.pdf$#', $message->attachment_path);
        Storage::disk('local')->assertExists($message->attachment_path);

        Http::assertSent(fn ($request) => $request['attachment'] === [
            'data' => base64_encode('%PDF-1.4 test'), 'mimetype' => 'application/pdf', 'filename' => 'programme.pdf',
        ]);
    }

    public function test_a_message_or_an_attachment_is_required(): void
    {
        Http::fake();

        $this->reply(['learner_id' => $this->learner()->id])
            ->assertStatus(422)->assertExactJson(['success' => false, 'error' => 'Message ou pièce jointe requis.']);

        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_a_learner_without_phone_is_refused(): void
    {
        Http::fake();

        $this->reply(['learner_id' => $this->learner(['phone' => null])->id, 'message' => 'Bonjour'])
            ->assertStatus(422)->assertExactJson(['success' => false, 'error' => 'Cet apprenant n\'a pas de numéro de téléphone.']);

        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_the_input_is_validated(): void
    {
        Http::fake();

        $this->reply(['message' => 'Bonjour'])->assertStatus(422)->assertJsonValidationErrors('learner_id');
        $this->reply(['learner_id' => 'not-a-uuid', 'message' => 'x'])->assertStatus(422)->assertJsonValidationErrors('learner_id');
        $this->reply(['learner_id' => fake()->uuid(), 'message' => 'x'])->assertStatus(422)->assertJsonValidationErrors('learner_id');
        $this->reply(['learner_id' => $this->learner()->id, 'message' => str_repeat('x', 4097)])->assertStatus(422)->assertJsonValidationErrors('message');
        $this->reply(['learner_id' => $this->learner()->id, 'attachment' => UploadedFile::fake()->create('virus.exe', 10)])->assertStatus(422)->assertJsonValidationErrors('attachment');

        Http::assertNothingSent();
    }

    public function test_replying_needs_the_send_permission(): void
    {
        Http::fake();
        $this->actingAs($this->createAdmin());

        $this->reply(['learner_id' => $this->learner()->id, 'message' => 'Bonjour'])->assertForbidden();

        Http::assertNothingSent();
    }
}
