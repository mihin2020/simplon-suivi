<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmail;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Services\EmailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

/** Pins the e-mail module: what the inbox and sent lists show, and how messages are sent. */
class EmailMailboxTest extends TestCase
{
    private function email(array $attributes): Email
    {
        return Email::create([
            'message_id' => (string) Str::uuid(),
            'direction' => 'received',
            'from_email' => 'sender@example.test',
            'from_name' => 'Sender',
            'to' => [['email' => 'me@example.test']],
            'subject' => 'Sujet',
            'body_html' => '',
            'is_read' => false,
            'is_archived' => false,
            ...$attributes,
        ]);
    }

    /** @return array{0: Email, 1: Email} the older and the newer received e-mail of the thread */
    private function seedInbox(): array
    {
        $older = $this->email(['thread_id' => 'T1', 'subject' => 'Premier', 'body_text' => 'Ancien message', 'received_at' => Carbon::parse('2026-10-01 08:00:00'), 'is_read' => true]);
        $newer = $this->email(['thread_id' => 'T1', 'subject' => 'Re: Premier', 'body_text' => "Message   récent\navec  espaces", 'received_at' => Carbon::parse('2026-10-03 08:00:00')]);
        EmailAttachment::create(['email_id' => $newer->id, 'filename' => 'cv.pdf', 'path' => 'attachments/cv.pdf', 'mime_type' => 'application/pdf', 'size' => 10]);
        $this->email(['thread_id' => 'T1', 'direction' => 'sent', 'subject' => 'Sujet envoyé', 'sent_at' => Carbon::parse('2026-09-30 08:00:00'), 'body_text' => 'sortant']);

        // A thread with no text version: the snippet falls back on the stripped HTML.
        $this->email(['thread_id' => 'T2', 'subject' => 'Seulement HTML', 'body_text' => null, 'body_html' => '<p>Bonjour    <b>monde</b></p>', 'received_at' => Carbon::parse('2026-10-02 08:00:00')]);

        // Archived thread, shown only with the archived filter.
        $this->email(['thread_id' => 'T3', 'subject' => 'Archive', 'body_text' => 'vieux', 'is_archived' => true, 'received_at' => Carbon::parse('2026-09-01 08:00:00')]);

        return [$older, $newer];
    }

    public function test_the_inbox_lists_threads_newest_first_with_their_summary(): void
    {
        [, $newer] = $this->seedInbox();

        $this->actingAsSuperAdmin()->get(route('emails.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Communication/Index')
                ->where('filter', 'inbox')
                ->has('threads.data', 2)
                ->where('threads.data.0.thread_id', 'T1')
                ->where('threads.data.0.reply_count', 2)
                ->where('threads.data.0.sent_subject', 'Sujet envoyé')
                ->where('threads.data.0.has_attachments', true)
                ->where('threads.data.0.snippet', 'Message récent avec espaces')
                ->where('threads.data.0.last_email.id', $newer->id)
                ->where('threads.data.0.last_email.subject', 'Re: Premier')
                ->where('threads.data.0.last_email.from_email', 'sender@example.test')
                ->where('threads.data.0.last_email.is_read', false)
                ->where('threads.data.1.thread_id', 'T2')
                ->where('threads.data.1.reply_count', 1)
                ->where('threads.data.1.sent_subject', null)
                ->where('threads.data.1.has_attachments', false)
                ->where('threads.data.1.snippet', 'Bonjour monde')
                ->etc());
    }

    /** It used to run 4 queries per thread (and one per row on the sent list). */
    public function test_the_lists_do_not_query_per_row(): void
    {
        $this->actingAsSuperAdmin();
        $queries = function (string $route): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($route)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->get(route('emails.index'))->assertOk(); // the user's permissions are loaded once, on the first request

        foreach (range(1, 2) as $i) {
            $this->email(['thread_id' => "S{$i}", 'body_text' => 'x', 'received_at' => now()]);
            $this->email(['direction' => 'sent', 'thread_id' => "S{$i}", 'sent_at' => now()]);
        }
        $inboxWithTwo = $queries(route('emails.index'));
        $sentWithTwo = $queries(route('emails.sent'));

        foreach (range(3, 15) as $i) {
            $this->email(['thread_id' => "S{$i}", 'body_text' => 'x', 'received_at' => now()]);
            $this->email(['direction' => 'sent', 'thread_id' => "S{$i}", 'sent_at' => now()]);
        }

        $this->assertSame($inboxWithTwo, $queries(route('emails.index')), 'inbox queries grow with the number of threads');
        $this->assertSame($sentWithTwo, $queries(route('emails.sent')), 'sent queries grow with the number of e-mails');
    }

    public function test_the_inbox_counters(): void
    {
        $this->seedInbox();

        $this->actingAsSuperAdmin()->get(route('emails.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inboxCount', 2)
                ->where('archivedCount', 1)
                ->where('sentCount', 1)
                ->where('unreadCount', 2)
                ->etc());
    }

    public function test_the_archived_filter_and_the_search(): void
    {
        $this->seedInbox();
        $this->actingAsSuperAdmin();

        $this->get(route('emails.index', ['filter' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', 'archived')
                ->has('threads.data', 1)
                ->where('threads.data.0.thread_id', 'T3')
                ->etc());

        $this->get(route('emails.index', ['search' => 'Seulement']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('search', 'Seulement')
                ->has('threads.data', 1)
                ->where('threads.data.0.thread_id', 'T2')
                ->etc());

        $this->get(route('emails.index', ['search' => 'introuvable']))
            ->assertInertia(fn (Assert $page) => $page->has('threads.data', 0)->etc());
    }

    /** The search reads subject, sender and the text version only: an HTML-only mail is not found by its body. */
    public function test_the_search_does_not_look_inside_html_only_bodies(): void
    {
        $this->email(['thread_id' => 'TH', 'subject' => 'x', 'body_text' => null, 'body_html' => '<p>contenu-unique</p>', 'received_at' => now()]);

        $this->actingAsSuperAdmin()->get(route('emails.index', ['search' => 'contenu-unique']))
            ->assertInertia(fn (Assert $page) => $page->has('threads.data', 0)->etc());
    }

    public function test_a_long_snippet_is_cut(): void
    {
        $this->email(['thread_id' => 'TL', 'body_text' => str_repeat('mot ', 100), 'received_at' => now()]);

        $this->actingAsSuperAdmin()->get(route('emails.index'))
            ->assertInertia(function (Assert $page) {
                $snippet = $page->toArray()['props']['threads']['data'][0]['snippet'];
                $this->assertLessThanOrEqual(143, mb_strlen($snippet));
                $this->assertStringEndsWith('...', $snippet);
            });
    }

    public function test_the_sent_list_shows_attachments_and_snippets(): void
    {
        $withFile = $this->email(['direction' => 'sent', 'subject' => 'Avec fichier', 'body_text' => 'Voir pièce jointe', 'sent_at' => Carbon::parse('2026-10-05 10:00:00')]);
        EmailAttachment::create(['email_id' => $withFile->id, 'filename' => 'a.pdf', 'path' => 'attachments/a.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
        $this->email(['direction' => 'sent', 'subject' => 'Sans fichier', 'body_text' => 'Rien', 'sent_at' => Carbon::parse('2026-10-04 10:00:00'), 'to' => [['email' => 'awa@example.test']]]);
        $this->email(['direction' => 'sent', 'subject' => 'Archive', 'body_text' => 'x', 'sent_at' => now(), 'is_archived' => true]);

        $this->actingAsSuperAdmin()->get(route('emails.sent'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Communication/Sent')
                ->has('emails.data', 2)
                ->where('emails.data.0.subject', 'Avec fichier')
                ->where('emails.data.0.has_attachments', true)
                ->where('emails.data.0.snippet', 'Voir pièce jointe')
                ->where('emails.data.1.subject', 'Sans fichier')
                ->where('emails.data.1.has_attachments', false)
                ->where('sentCount', 2)
                ->etc());

        $this->get(route('emails.sent', ['search' => 'awa@example']))
            ->assertInertia(fn (Assert $page) => $page->has('emails.data', 1)->where('emails.data.0.subject', 'Sans fichier')->etc());
    }

    public function test_composing_queues_a_bulk_send_with_its_attachments(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post(route('emails.store'), [
            'to' => [['email' => 'awa@example.test', 'name' => 'Awa'], ['email' => 'issa@example.test']],
            'cc' => [['email' => 'boss@example.test']],
            'subject' => 'Convocation',
            'body' => '<p>Bonjour</p>',
            'attachments' => [UploadedFile::fake()->create('programme.pdf', 20, 'application/pdf')],
        ])->assertRedirect(route('emails.sent'))->assertSessionHas('success');

        Queue::assertPushed(SendBulkEmail::class, function (SendBulkEmail $job) use ($user) {
            return count($job->to) === 2
                && $job->to[0]['email'] === 'awa@example.test'
                && $job->cc === [['email' => 'boss@example.test']]
                && $job->subject === 'Convocation'
                && $job->body === '<p>Bonjour</p>'
                && $job->userId === (string) $user->id
                && count($job->attachments) === 1
                && $job->attachments[0]['name'] === 'programme.pdf'
                && str_contains($job->attachments[0]['path'], 'temp-email-attachments');
        });
    }

    public function test_composing_without_cc_or_files(): void
    {
        Queue::fake();

        $this->actingAsSuperAdmin()->post(route('emails.store'), [
            'to' => [['email' => 'awa@example.test']], 'subject' => 'S', 'body' => 'B',
        ])->assertRedirect();

        Queue::assertPushed(SendBulkEmail::class, fn ($job) => $job->cc === null && $job->attachments === []);
    }

    public function test_composing_is_validated(): void
    {
        Queue::fake();
        $this->actingAsSuperAdmin();

        $this->post(route('emails.store'), ['to' => [], 'subject' => 'S', 'body' => 'B'])->assertSessionHasErrors('to');
        $this->post(route('emails.store'), ['to' => [['email' => 'pas-un-email']], 'subject' => 'S', 'body' => 'B'])->assertSessionHasErrors('to.0.email');
        $this->post(route('emails.store'), ['to' => [['email' => 'a@b.test']], 'body' => 'B'])->assertSessionHasErrors('subject');
        $this->post(route('emails.store'), ['to' => [['email' => 'a@b.test']], 'subject' => str_repeat('x', 256), 'body' => 'B'])->assertSessionHasErrors('subject');
        $this->post(route('emails.store'), ['to' => [['email' => 'a@b.test']], 'subject' => 'S'])->assertSessionHasErrors('body');
        $this->post(route('emails.store'), [
            'to' => array_fill(0, 301, ['email' => 'a@b.test']), 'subject' => 'S', 'body' => 'B',
        ])->assertSessionHasErrors('to');

        Queue::assertNothingPushed();
    }

    public function test_replying_sends_to_the_sender_in_the_same_thread(): void
    {
        $received = $this->email(['thread_id' => 'T9', 'from_email' => 'awa@example.test', 'from_name' => 'Awa', 'subject' => 'Re: Re: Question']);
        $user = $this->createSuperAdmin();

        $this->mock(EmailService::class, function (MockInterface $mock) use ($received, $user) {
            $mock->shouldReceive('sendEmail')->once()->withArgs(function (...$args) use ($received, $user) {
                return $args[0] === [['email' => 'awa@example.test', 'name' => 'Awa']]
                    && $args[1] === 'Re: Question'
                    && $args[2] === '<p>Merci</p>'
                    && $args[3] === []
                    && $args[4] === 'awa@example.test'
                    && (string) $args[5] === (string) $user->id
                    && $args[6] === null
                    && $args[7] === $received->id;
            })->andReturn(new Email);
        });

        $this->actingAs($user)->post(route('emails.reply', $received), ['body' => '<p>Merci</p>'])
            ->assertRedirect(route('emails.show', 'T9'))
            ->assertSessionHas('success', 'Réponse envoyée.');
    }

    public function test_replying_to_an_invalid_sender_address_is_refused(): void
    {
        $received = $this->email(['thread_id' => 'T9', 'from_email' => 'pas-valide']);
        $this->mock(EmailService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('sendEmail'));

        $this->actingAsSuperAdmin()->post(route('emails.reply', $received), ['body' => 'x'])
            ->assertRedirect()
            ->assertSessionHas('error', "Impossible de répondre : l'adresse email de l'expéditeur n'est pas valide.");
    }

    public function test_replying_needs_a_body(): void
    {
        $received = $this->email(['thread_id' => 'T9']);

        $this->actingAsSuperAdmin()->post(route('emails.reply', $received), [])->assertSessionHasErrors('body');
    }

    public function test_forwarding_prefixes_the_subject_once_and_quotes_the_original(): void
    {
        $received = $this->email(['subject' => 'Fwd: Fwd: Info', 'body_html' => '<p>Original</p>']);

        $this->mock(EmailService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendEmail')->once()->withArgs(function (...$args) {
                return $args[0] === [['email' => 'issa@example.test', 'name' => 'Issa']]
                    && $args[1] === 'Fwd: Info'
                    && $args[2] === "Pour info\n\n---------- Message transféré ----------\n<p>Original</p>"
                    && $args[3] === []
                    && $args[4] === null;
            })->andReturn(new Email);
        });

        $this->actingAsSuperAdmin()->post(route('emails.forward', $received), [
            'to' => [['email' => 'issa@example.test', 'name' => 'Issa']],
            'body' => 'Pour info',
        ])->assertRedirect(route('emails.sent'))->assertSessionHas('success', 'Email transféré.');
    }

    public function test_forwarding_needs_a_recipient(): void
    {
        $received = $this->email([]);

        $this->actingAsSuperAdmin()->post(route('emails.forward', $received), ['to' => []])->assertSessionHasErrors('to');
    }

    public function test_a_normal_synchronisation_runs_after_the_response(): void
    {
        $this->mock(EmailService::class, fn (MockInterface $mock) => $mock->shouldReceive('syncInbox')->once()->with(false));

        $this->actingAsSuperAdmin()->post(route('emails.sync'))
            ->assertSessionHas('success', 'Synchronisation lancée en arrière-plan. Actualisez dans quelques secondes.');
    }

    public function test_a_deep_synchronisation_runs_after_the_response(): void
    {
        $this->mock(EmailService::class, fn (MockInterface $mock) => $mock->shouldReceive('syncInbox')->once()->with(true));

        $this->actingAsSuperAdmin()->post(route('emails.sync', ['deep' => 1]))
            ->assertSessionHas('success', 'Synchronisation approfondie (6 mois) lancée en arrière-plan.');
    }

    public function test_a_failing_synchronisation_does_not_break_the_page(): void
    {
        $this->mock(EmailService::class, fn (MockInterface $mock) => $mock->shouldReceive('syncInbox')->once()->andThrow(new \RuntimeException('IMAP down')));

        $this->actingAsSuperAdmin()->post(route('emails.sync'))->assertSessionHas('success');
    }

    public function test_the_mailbox_needs_its_permissions(): void
    {
        $received = $this->email(['thread_id' => 'T9']);
        $this->actingAsTrainer();

        $this->get(route('emails.index'))->assertForbidden();
        $this->get(route('emails.sent'))->assertForbidden();
        $this->post(route('emails.store'), [])->assertForbidden();
        $this->post(route('emails.reply', $received), [])->assertForbidden();
        $this->post(route('emails.forward', $received), [])->assertForbidden();
        $this->post(route('emails.sync'))->assertForbidden();
    }
}
