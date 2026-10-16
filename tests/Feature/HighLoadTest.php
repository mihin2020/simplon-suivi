<?php

namespace Tests\Feature;

use App\Actions\Forms\CreateForm;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendBulkEmail;
use App\Jobs\SendWhatsAppBroadcast;
use App\Models\Email;
use App\Models\Form;
use App\Models\Formation;
use App\Models\FormResponse;
use App\Models\Notification;
use App\Models\Project;
use App\Models\WhatsAppMessage;
use App\Services\EmailService;
use App\Services\WhatsAppWWebService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Behaviour under a rush: public forms shared by many candidates, and mass
 * sendings handed to the queue instead of holding the web request.
 */
class HighLoadTest extends TestCase
{
    public function test_candidates_behind_one_ip_are_limited_per_browser_not_per_ip(): void
    {
        $limits = fn (string $sessionId) => collect(RateLimiter::limiter('forms.public')($this->publicRequest($sessionId)));

        $first = $limits('session-a');
        $second = $limits('session-b');

        // The shared IP gets a wide limit; the tight one belongs to each browser.
        $this->assertSame(300, $first[0]->maxAttempts);
        $this->assertSame($first[0]->key, $second[0]->key);
        $this->assertSame(5, $first[1]->maxAttempts);
        $this->assertNotSame($first[1]->key, $second[1]->key);
    }

    public function test_a_full_form_refuses_the_next_candidate(): void
    {
        [$form, $answers] = $this->publishedForm(['max_responses' => 1]);

        $this->post("/f/{$form->public_token}", ['answers' => $answers('a@example.com'), 'website' => ''])->assertRedirect();
        $this->post("/f/{$form->public_token}", ['answers' => $answers('b@example.com'), 'website' => ''])->assertSessionHasErrors('form');

        $this->assertSame(1, FormResponse::where('form_id', $form->id)->count());
    }

    public function test_the_same_email_is_saved_once(): void
    {
        [$form, $answers] = $this->publishedForm();

        $this->post("/f/{$form->public_token}", ['answers' => $answers('dup@example.com'), 'website' => ''])->assertRedirect();
        // Same address with other case and spaces: still the same candidate.
        $this->post("/f/{$form->public_token}", ['answers' => $answers(' DUP@example.com '), 'website' => ''])->assertSessionHasErrors('email');

        $this->assertSame(1, FormResponse::where('form_id', $form->id)->count());
    }

    public function test_a_mailing_is_queued_then_sent_and_the_sender_notified(): void
    {
        Queue::fake();
        $user = $this->createSuperAdmin();
        $to = collect(range(1, 40))->map(fn (int $i) => ['email' => "apprenant{$i}@example.com", 'name' => "Apprenant {$i}"])->all();

        $this->actingAs($user)
            ->post('/communication/emails', ['to' => $to, 'subject' => 'Rentrée', 'body' => '<p>Bonjour</p>'])
            ->assertRedirect(route('emails.sent'));

        Queue::assertPushed(SendBulkEmail::class, fn (SendBulkEmail $job) => count($job->to) === 40 && $job->userId === $user->id);
        $this->assertSame(0, Email::count());

        Mail::fake();
        (new SendBulkEmail($to, 'Rentrée', '<p>Bonjour</p>', [], null, $user->id))->handle(app(EmailService::class));

        $this->assertSame(1, Email::where('subject', 'Rentrée')->count());
        $this->assertSame('Email envoyé', Notification::where('user_id', $user->id)->latest()->value('title'));
    }

    public function test_a_whatsapp_broadcast_is_recorded_as_sending_then_updated_in_the_background(): void
    {
        Queue::fake();
        Http::fake([
            '*/status' => Http::response(['connected' => true]),
            '*/send-bulk' => Http::response([
                'success' => 1,
                'failed' => 1,
                'errors' => [['phone' => '22670000002', 'error' => 'Numéro inconnu']],
                'sentIds' => ['22670000001' => 'wa-1'],
            ]),
        ]);
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/communication/whatsapp/send', [
            'recipients' => [['phone' => '+226 70 00 00 01', 'name' => 'Awa'], ['phone' => '+226 70 00 00 02', 'name' => 'Issa']],
            'message' => 'Bonjour',
        ])->assertSessionHas('success');

        $this->assertSame(2, WhatsAppMessage::where('status', WhatsAppMessageStatus::Sending->value)->count());
        $broadcastId = WhatsAppMessage::value('broadcast_id');
        Queue::assertPushed(SendWhatsAppBroadcast::class, fn (SendWhatsAppBroadcast $job) => $job->broadcastId === $broadcastId);

        (new SendWhatsAppBroadcast($broadcastId, $user->id))->handle(app(WhatsAppWWebService::class));

        $this->assertSame(WhatsAppMessageStatus::Sent->value, WhatsAppMessage::where('recipient_name', 'Awa')->value('status'));
        $this->assertSame('wa-1', WhatsAppMessage::where('recipient_name', 'Awa')->value('external_id'));
        $this->assertSame('Numéro inconnu', WhatsAppMessage::where('recipient_name', 'Issa')->value('error'));
        $this->assertSame('1 envoyé(s), 1 échec(s).', Notification::where('user_id', $user->id)->latest()->value('message'));
    }

    public function test_nothing_is_queued_when_whatsapp_is_disconnected(): void
    {
        Queue::fake();
        Http::fake(['*/status' => Http::response(['connected' => false])]);

        $this->actingAs($this->createSuperAdmin())->post('/communication/whatsapp/send', [
            'recipients' => [['phone' => '+226 70 00 00 01', 'name' => 'Awa']],
            'message' => 'Bonjour',
        ])->assertSessionHas('error');

        $this->assertSame(0, WhatsAppMessage::count());
        Queue::assertNothingPushed();
    }

    private function publicRequest(string $sessionId): Request
    {
        $request = Request::create('/f/abcdefgh', 'POST', server: ['REMOTE_ADDR' => '41.138.10.10']);
        $request->setLaravelSession(tap(app('session')->driver())->setId($sessionId));
        $request->setRouteResolver(fn () => tap(new Route('POST', 'f/{publicToken}', []), fn ($route) => $route->bind($request)));

        return $request;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{0: Form, 1: \Closure(string): array<string, string>}
     */
    private function publishedForm(array $settings = []): array
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);
        $form = app(CreateForm::class)->execute($user, ['title' => 'Candidatures', 'project_id' => $project->id, 'formation_id' => $formation->id]);

        $this->actingAs($user)->post("/forms/{$form->id}/publish");
        $form->refresh();
        if ($settings !== []) {
            $form->update(['settings' => array_merge($form->settings ?? [], $settings)]);
        }
        auth()->logout();

        $answers = fn (string $email) => $form->fields->mapWithKeys(fn ($field) => [$field->id => match ($field->learner_attribute) {
            'first_name' => 'Awa',
            'last_name' => 'Ouédraogo',
            'email' => $email,
            'phone' => '70000000',
            default => 'x',
        }])->all();

        return [$form, $answers];
    }
}
