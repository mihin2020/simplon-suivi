<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pins what the WhatsApp campaign list and the recipients of one campaign report. */
class WhatsAppBroadcastReportTest extends TestCase
{
    private function message(array $attributes, string $at): WhatsAppMessage
    {
        $message = WhatsAppMessage::create([
            'phone' => '22670000000@c.us',
            'message' => '',
            'direction' => 'sent',
            'provider' => 'wwebjs',
            'status' => 'sent',
            ...$attributes,
        ]);

        // created_at is not fillable: place the message in time explicitly.
        WhatsAppMessage::whereKey($message->id)->update(['created_at' => Carbon::parse($at)]);

        return $message->fresh();
    }

    /** @return array{0: Learner, 1: Learner} */
    private function seedCampaigns(): array
    {
        $awa = Learner::factory()->create(['first_name' => 'Awa', 'last_name' => 'Zongo', 'phone' => '70000001']);
        $moussa = Learner::factory()->create(['first_name' => 'Moussa', 'last_name' => 'Kabore', 'phone' => '70000002']);

        // Campaign B1: Awa receives text + a file (counts once), Moussa's send failed.
        $this->message(['broadcast_id' => 'B1', 'learner_id' => $awa->id, 'phone' => '70000001', 'message' => 'Bonjour a tous', 'formation_name' => 'Dev Web', 'project_name' => 'Projet X'], '2026-10-10 09:00:00');
        $this->message(['broadcast_id' => 'B1', 'learner_id' => $awa->id, 'phone' => '70000001', 'message' => '', 'attachment_path' => 'whatsapp_media/f.pdf', 'formation_name' => 'Dev Web', 'project_name' => 'Projet X'], '2026-10-10 09:00:05');
        $this->message(['broadcast_id' => 'B1', 'learner_id' => $moussa->id, 'phone' => '70000002', 'message' => 'Bonjour a tous', 'status' => 'failed', 'error' => 'numero invalide', 'formation_name' => 'Dev Web', 'project_name' => 'Projet X'], '2026-10-10 09:00:10');

        // Campaign B2 (older): only a file, still sending.
        $this->message(['broadcast_id' => 'B2', 'learner_id' => $awa->id, 'phone' => '70000001', 'message' => '', 'attachment_path' => 'whatsapp_media/g.pdf', 'status' => 'sending'], '2026-10-01 08:00:00');

        // Replies: Awa answered after B1 (unread); Moussa answered before B1 (must not count for it).
        $this->message(['direction' => 'received', 'learner_id' => $awa->id, 'message' => 'Merci !', 'status' => 'received'], '2026-10-10 10:00:00');
        $this->message(['direction' => 'received', 'learner_id' => $moussa->id, 'message' => 'Ancien', 'status' => 'received'], '2026-10-09 10:00:00');

        // Not a campaign: a one-to-one message and a message of another provider.
        $this->message(['learner_id' => $awa->id, 'message' => 'seul'], '2026-10-11 09:00:00');
        $this->message(['broadcast_id' => 'OTHER', 'provider' => 'twilio', 'message' => 'autre'], '2026-10-12 09:00:00');

        return [$awa, $moussa];
    }

    public function test_the_campaign_list_summarises_each_campaign_newest_first(): void
    {
        $this->seedCampaigns();

        $response = $this->actingAsSuperAdmin()->getJson(route('whatsapp.broadcasts'))->assertOk();
        $campaigns = $response->json();

        $this->assertSame(['B1', 'B2'], array_column($campaigns, 'broadcast_id'));

        $this->assertSame('Bonjour a tous', $campaigns[0]['message']);
        $this->assertSame('Dev Web', $campaigns[0]['formation_name']);
        $this->assertSame('Projet X', $campaigns[0]['project_name']);
        $this->assertSame(2, $campaigns[0]['recipient_count']);
        $this->assertSame(1, $campaigns[0]['reply_count']);
        $this->assertSame(1, $campaigns[0]['failed_count']);
        $this->assertSame(0, $campaigns[0]['sending_count']);

        $this->assertSame('📎 Fichier(s) joint(s)', $campaigns[1]['message']);
        $this->assertSame(1, $campaigns[1]['recipient_count']);
        // Awa's reply is also later than this older campaign.
        $this->assertSame(1, $campaigns[1]['reply_count']);
        $this->assertSame(1, $campaigns[1]['sending_count']);
    }

    public function test_read_replies_are_not_counted(): void
    {
        [$awa] = $this->seedCampaigns();
        WhatsAppMessage::where('learner_id', $awa->id)->where('direction', 'received')->update(['read_at' => now()]);

        $campaigns = $this->actingAsSuperAdmin()->getJson(route('whatsapp.broadcasts'))->json();

        $this->assertSame(0, $campaigns[0]['reply_count']);
    }

    public function test_the_recipients_of_a_campaign_are_listed_once_each_with_their_replies(): void
    {
        [$awa, $moussa] = $this->seedCampaigns();

        $recipients = $this->actingAsSuperAdmin()
            ->getJson(route('whatsapp.broadcast.recipients', 'B1'))
            ->assertOk()
            ->json();

        $this->assertCount(2, $recipients);

        // Those who replied come first.
        $this->assertSame($awa->id, $recipients[0]['learner_id']);
        $this->assertSame('Awa Zongo', $recipients[0]['name']);
        $this->assertTrue($recipients[0]['has_replied']);
        $this->assertSame(1, $recipients[0]['reply_count']);
        $this->assertSame('Merci !', $recipients[0]['last_reply']['message']);
        $this->assertSame('sent', $recipients[0]['status']);

        $this->assertSame($moussa->id, $recipients[1]['learner_id']);
        $this->assertSame('Moussa Kabore', $recipients[1]['name']);
        $this->assertFalse($recipients[1]['has_replied']);
        $this->assertNull($recipients[1]['last_reply']);
        $this->assertSame('failed', $recipients[1]['status']);
        $this->assertSame('numero invalide', $recipients[1]['error']);
    }

    /** It used to crash (min() of nothing is null). */
    public function test_an_unknown_campaign_has_no_recipient(): void
    {
        $this->actingAsSuperAdmin()->getJson(route('whatsapp.broadcast.recipients', 'NOPE'))->assertOk()->assertExactJson([]);
    }

    public function test_the_reports_need_the_whatsapp_view_permission(): void
    {
        $this->actingAsTrainer()->getJson(route('whatsapp.broadcasts'))->assertForbidden();
        $this->actingAsTrainer()->getJson(route('whatsapp.broadcast.recipients', 'B1'))->assertForbidden();
    }
}
