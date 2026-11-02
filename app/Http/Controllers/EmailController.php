<?php

namespace App\Http\Controllers;

use App\Actions\Mail\StageEmailAttachments;
use App\Http\Requests\Email\ForwardEmailRequest;
use App\Http\Requests\Email\ReplyEmailRequest;
use App\Http\Requests\Email\StoreEmailRequest;
use App\Jobs\SendBulkEmail;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\Project;
use App\Services\EmailService;
use App\Services\Mail\MailboxCounts;
use App\Services\Mail\MailboxListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function __construct(protected EmailService $emailService) {}

    public function index(Request $request, MailboxListing $listing, MailboxCounts $counts): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filter = (string) $request->input('filter', 'inbox');

        return Inertia::render('Communication/Index', [
            'threads' => $listing->inboxThreads($filter, $search),
            'filter' => $filter,
            'search' => $search,
            ...$counts->all(),
        ]);
    }

    public function sent(Request $request, MailboxListing $listing, MailboxCounts $counts): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('Communication/Sent', [
            'emails' => $listing->sentEmails($search),
            'search' => $search,
            ...$counts->all(),
        ]);
    }

    public function show(string $threadId)
    {
        $emails = Email::with('attachments', 'sender')
            ->where('thread_id', $threadId)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->get()
            ->each->append('safe_body_html');

        // Mark all received emails in thread as read
        Email::where('thread_id', $threadId)
            ->where('direction', 'received')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return Inertia::render('Communication/Thread', [
            'threadId' => $threadId,
            'emails' => $emails,
        ]);
    }

    public function compose()
    {
        $projects = Project::select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Communication/Compose', [
            'projects' => $projects,
        ]);
    }

    public function store(StoreEmailRequest $request, StageEmailAttachments $stageAttachments): RedirectResponse
    {
        $data = $request->validated();

        // Sent by the queue worker: a mailing to hundreds of people takes many
        // SMTP calls; the sender is notified in the app when it is done.
        SendBulkEmail::dispatch(
            $data['to'],
            $data['subject'],
            $data['body'],
            $stageAttachments->execute($request->file('attachments') ?? []),
            $data['cc'] ?? null,
            (string) $request->user()->id,
        );

        return redirect()
            ->route('emails.sent')
            ->with('success', "Email en cours d'envoi en arrière-plan. Vous serez notifié à la fin de l'envoi.");
    }

    public function reply(ReplyEmailRequest $request, Email $email, StageEmailAttachments $stageAttachments): RedirectResponse
    {
        if (! filter_var($email->from_email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', "Impossible de répondre : l'adresse email de l'expéditeur n'est pas valide.");
        }

        $this->emailService->sendEmail(
            [['email' => $email->from_email, 'name' => $email->from_name]],
            'Re: '.preg_replace('/^(Re:\s*)+/i', '', $email->subject),
            $request->validated('body'),
            $stageAttachments->execute($request->file('attachments') ?? []),
            $email->from_email, // replyToEmail - for reply-to header
            $request->user()->id,
            null, // cc
            $email->id, // replyToMessageId - for threading
        );

        return redirect()
            ->route('emails.show', $email->thread_id)
            ->with('success', 'Réponse envoyée.');
    }

    public function forward(ForwardEmailRequest $request, Email $email): RedirectResponse
    {
        $data = $request->validated();

        $this->emailService->sendEmail(
            $data['to'],
            'Fwd: '.preg_replace('/^(Fwd:\s*)+/i', '', $email->subject),
            ($data['body'] ?? '')."\n\n---------- Message transféré ----------\n".$email->body_html,
            [],
            null, // replyToEmail
            $request->user()->id,
            null, // cc
            null, // replyToMessageId
        );

        return redirect()
            ->route('emails.sent')
            ->with('success', 'Email transféré.');
    }

    public function sync(Request $request)
    {
        $deep = $request->boolean('deep');

        // Runs AFTER the HTTP response: the browser does not wait for the IMAP connection.
        // Only the flag is captured; the service is resolved when the closure runs.
        dispatch(function () use ($deep) {
            try {
                app(EmailService::class)->syncInbox($deep);
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();

        $msg = $deep
            ? 'Synchronisation approfondie (6 mois) lancée en arrière-plan.'
            : 'Synchronisation lancée en arrière-plan. Actualisez dans quelques secondes.';

        return back()->with('success', $msg);
    }

    public function archive(Email $email)
    {
        $this->emailService->archive($email->id);

        return back()->with('success', 'Email archivé.');
    }

    public function unarchive(Email $email)
    {
        $email->update(['is_archived' => false]);

        return back()->with(
            'success',
            'Email restauré dans la boîte de réception.',
        );
    }

    public function showSent(Email $email)
    {
        $email->load('attachments')->append('safe_body_html');

        return Inertia::render('Communication/SentShow', [
            'email' => $email,
        ]);
    }

    public function downloadAttachment(EmailAttachment $attachment)
    {
        if (! Storage::disk('local')->exists($attachment->path)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }

    public function destroy(Email $email)
    {
        foreach ($email->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }
        $email->delete();

        return back()->with('success', 'Email supprimé.');
    }
}
