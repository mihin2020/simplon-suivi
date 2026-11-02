<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pins the behaviour of "synchronise WhatsApp replies": which incoming messages are stored, which
 * are ignored, and how revoked messages are handled.
 */
class WhatsAppSyncRepliesTest extends TestCase
{
    private function learnerWhoWasMessaged(string $phone = '70123456'): Learner
    {
        $learner = Learner::factory()->create(['phone' => $phone, 'first_name' => 'Awa', 'last_name' => 'Zongo']);

        WhatsAppMessage::create([
            'phone' => $phone,
            'recipient_name' => 'Awa Zongo',
            'message' => 'Bonjour',
            'direction' => 'sent',
            'provider' => 'wwebjs',
            'status' => 'sent',
            'learner_id' => $learner->id,
        ]);

        return $learner;
    }

    private function fakeMicroservice(array $incoming = [], array $revoked = []): void
    {
        Http::fake([
            '*/messages/incoming*' => Http::response($incoming),
            '*/messages/revoked*' => Http::response($revoked),
        ]);
    }

    private function sync()
    {
        return $this->actingAsSuperAdmin()->postJson(route('whatsapp.sync'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('wa_last_incoming_ts');
        Cache::forget('wa_last_revoke_sync');
    }

    public function test_nothing_is_synchronised_when_no_message_was_ever_sent(): void
    {
        $this->fakeMicroservice([['from' => '22670123456@c.us', 'body' => 'Salut', 'wa_id' => 'A1', 'timestamp' => 100]]);

        $this->sync()->assertOk()->assertExactJson(['synced' => 0]);

        $this->assertSame(0, WhatsAppMessage::where('direction', 'received')->count());
    }

    public function test_a_reply_from_a_messaged_learner_is_stored(): void
    {
        $learner = $this->learnerWhoWasMessaged('+22670123456');
        $this->fakeMicroservice([['from' => '22670123456@c.us', 'body' => 'Merci', 'wa_id' => 'A1', 'timestamp' => 1000]]);

        $this->sync()->assertOk()->assertExactJson(['synced' => 1, 'revoked' => 0]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'received',
            'provider' => 'wwebjs',
            'status' => 'received',
            'external_id' => 'A1',
            'learner_id' => $learner->id,
            'recipient_name' => 'Awa Zongo',
            'message' => 'Merci',
            'phone' => '22670123456@c.us',
        ]);
        $this->assertSame(1000, Cache::get('wa_last_incoming_ts'));
    }

    public function test_the_same_message_is_never_stored_twice(): void
    {
        $this->learnerWhoWasMessaged('70123456');
        $this->fakeMicroservice([['from' => '22670123456@c.us', 'body' => 'Merci', 'wa_id' => 'A1', 'timestamp' => 1000]]);

        $this->sync()->assertExactJson(['synced' => 1, 'revoked' => 0]);
        $this->sync()->assertExactJson(['synced' => 0, 'revoked' => 0]);

        $this->assertSame(1, WhatsAppMessage::where('direction', 'received')->count());
    }

    public function test_noise_and_strangers_are_ignored_but_the_window_still_advances(): void
    {
        $this->learnerWhoWasMessaged('70123456');
        $this->fakeMicroservice([
            ['from' => '120363@g.us', 'body' => 'groupe', 'wa_id' => 'G1', 'timestamp' => 2000],
            ['from' => 'status@broadcast', 'body' => 'statut', 'wa_id' => 'S1', 'timestamp' => 2001],
            ['from' => '', 'body' => 'vide', 'wa_id' => 'E1', 'timestamp' => 2002],
            ['from' => '1234567@c.us', 'body' => 'trop court', 'wa_id' => 'C1', 'timestamp' => 2003],
            ['from' => '22699999999@c.us', 'body' => 'inconnu', 'wa_id' => 'U1', 'timestamp' => 2004],
        ]);

        $this->sync()->assertExactJson(['synced' => 0, 'revoked' => 0]);

        $this->assertSame(0, WhatsAppMessage::where('direction', 'received')->count());
        $this->assertSame(2004, Cache::get('wa_last_incoming_ts'));
    }

    public function test_the_next_window_starts_fifteen_seconds_before_the_last_message_seen(): void
    {
        $this->learnerWhoWasMessaged('70123456');
        Cache::put('wa_last_incoming_ts', 5000);
        $this->fakeMicroservice();

        $this->sync()->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages/incoming') && $request['since'] == 4985);
    }

    public function test_an_attachment_is_saved_on_the_private_disk(): void
    {
        Storage::fake('local');
        $this->learnerWhoWasMessaged('70123456');
        $this->fakeMicroservice([[
            'from' => '22670123456@c.us', 'body' => '', 'wa_id' => 'M1', 'timestamp' => 3000,
            'attachment_data' => base64_encode('voice-bytes'), 'attachment_mimetype' => 'audio/ogg; codecs=opus',
        ]]);

        $this->sync()->assertExactJson(['synced' => 1, 'revoked' => 0]);

        $message = WhatsAppMessage::where('external_id', 'M1')->first();
        $this->assertSame('audio/ogg', $message->attachment_mimetype);
        $this->assertStringStartsWith('whatsapp_media/', $message->attachment_path);
        $this->assertStringEndsWith('.ogg', $message->attachment_path);
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_revoked_messages_are_deleted(): void
    {
        $learner = $this->learnerWhoWasMessaged('70123456');
        $received = WhatsAppMessage::create([
            'phone' => '22670123456@c.us', 'message' => 'a supprimer', 'direction' => 'received',
            'provider' => 'wwebjs', 'status' => 'received', 'external_id' => 'R1', 'learner_id' => $learner->id,
        ]);
        $this->fakeMicroservice([], [['wa_id' => 'R1'], ['wa_id' => 'UNKNOWN'], ['no_id' => true]]);

        $this->sync()->assertExactJson(['synced' => 0, 'revoked' => 1]);

        $this->assertSoftDeleted('whatsapp_messages', ['id' => $received->id]);
        $this->assertNotNull(Cache::get('wa_last_revoke_sync'));
    }

    public function test_it_requires_the_whatsapp_manage_permission(): void
    {
        $this->actingAsTrainer()->postJson(route('whatsapp.sync'))->assertForbidden();
    }
}
