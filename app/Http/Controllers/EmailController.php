<?php

namespace App\Http\Controllers;

use App\Jobs\SendBulkEmail;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\Project;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmailController extends Controller
{
    public function __construct(protected EmailService $emailService) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $threads = Email::received()
            ->when(
                $request->input('filter') !== 'archived',
                fn ($q) => $q->where('is_archived', false),
            )
            ->when(
                $request->input('filter') === 'archived',
                fn ($q) => $q->where('is_archived', true),
            )
            ->when($search !== '', function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function ($sub) use ($like) {
                    $sub->where('subject', 'like', $like)
                        ->orWhere('from_name', 'like', $like)
                        ->orWhere('from_email', 'like', $like)
                        ->orWhere('body_text', 'like', $like);
                });
            })
            ->select('thread_id')
            ->selectRaw('MAX(COALESCE(received_at, created_at)) as last_at')
            ->groupBy('thread_id')
            ->orderByDesc('last_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($t) {
                // Dernier email REÇU du thread (le plus récent en premier)
                $lastEmail = Email::where('thread_id', $t->thread_id)
                    ->where('direction', 'received')
                    ->orderByDesc('received_at')
                    ->orderByDesc('created_at')
                    ->first();

                $replyCount = Email::where('thread_id', $t->thread_id)
                    ->where('direction', 'received')
                    ->count();

                $sentSubject = Email::where('thread_id', $t->thread_id)
                    ->where('direction', 'sent')
                    ->value('subject');

                $hasAttachments = $lastEmail
                    ? $lastEmail->attachments()->exists()
                    : false;

                return [
                    'thread_id' => $t->thread_id,
                    'reply_count' => $replyCount,
                    'sent_subject' => $sentSubject,
                    'has_attachments' => $hasAttachments,
                    'snippet' => $this->makeSnippet($lastEmail),
                    'last_email' => $lastEmail?->only([
                        'id',
                        'from_name',
                        'from_email',
                        'subject',
                        'is_read',
                        'received_at',
                        'created_at',
                    ]),
                ];
            });

        $inboxCount = Email::received()
            ->where('is_archived', false)
            ->distinct('thread_id')
            ->count('thread_id');
        $archivedCount = Email::received()
            ->where('is_archived', true)
            ->distinct('thread_id')
            ->count('thread_id');
        $sentCount = Email::sent()
            ->where('is_archived', false)
            ->count();
        $unreadCount = Email::received()
            ->where('is_archived', false)
            ->where('is_read', false)
            ->distinct('thread_id')
            ->count('thread_id');

        return Inertia::render('Communication/Index', [
            'threads' => $threads,
            'filter' => $request->input('filter', 'inbox'),
            'search' => $search,
            'inboxCount' => $inboxCount,
            'archivedCount' => $archivedCount,
            'sentCount' => $sentCount,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Génère un court extrait texte (snippet) à partir du corps d'un email,
     * façon aperçu Gmail dans la liste.
     */
    private function makeSnippet(?Email $email): string
    {
        if (! $email) {
            return '';
        }

        $text = $email->body_text;

        if (! $text) {
            // Repli sur le HTML nettoyé si pas de version texte
            $text = strip_tags((string) $email->body_html);
        }

        $text = preg_replace('/\s+/u', ' ', (string) $text);

        return Str::limit(trim($text), 140);
    }

    public function sent(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $emails = Email::sent()
            ->where('is_archived', false)
            ->when($search !== '', function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function ($sub) use ($like) {
                    $sub->where('subject', 'like', $like)
                        ->orWhere('body_text', 'like', $like)
                        ->orWhere('to', 'like', $like);
                });
            })
            ->orderByDesc('sent_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($e) {
                $arr = $e->only([
                    'id',
                    'subject',
                    'to',
                    'sent_at',
                ]);
                $arr['has_attachments'] = $e->attachments()->exists();
                $arr['snippet'] = $this->makeSnippet($e);

                return $arr;
            });

        $inboxCount = Email::received()
            ->where('is_archived', false)
            ->distinct('thread_id')
            ->count('thread_id');
        $archivedCount = Email::received()
            ->where('is_archived', true)
            ->distinct('thread_id')
            ->count('thread_id');
        $sentCount = Email::sent()
            ->where('is_archived', false)
            ->count();
        $unreadCount = Email::received()
            ->where('is_archived', false)
            ->where('is_read', false)
            ->distinct('thread_id')
            ->count('thread_id');

        return Inertia::render('Communication/Sent', [
            'emails' => $emails,
            'search' => $search,
            'inboxCount' => $inboxCount,
            'archivedCount' => $archivedCount,
            'sentCount' => $sentCount,
            'unreadCount' => $unreadCount,
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'array', 'min:1', 'max:300'],
            'to.*.email' => ['required', 'email'],
            'to.*.name' => ['nullable', 'string'],
            'cc' => ['nullable', 'array'],
            'cc.*.email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('temp-email-attachments');
                $attachments[] = [
                    'path' => storage_path('app/private/'.$path),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        // Sent by the queue worker: a mailing to hundreds of people takes many
        // SMTP calls; the sender is notified in the app when it is done.
        SendBulkEmail::dispatch(
            $data['to'],
            $data['subject'],
            $data['body'],
            $attachments,
            $data['cc'] ?? null,
            (string) auth()->id(),
        );

        return redirect()
            ->route('emails.sent')
            ->with('success', "Email en cours d'envoi en arrière-plan. Vous serez notifié à la fin de l'envoi.");
    }

    public function reply(Request $request, Email $email)
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        // Validate that the sender email is a valid email address
        if (! filter_var($email->from_email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Impossible de répondre : l\'adresse email de l\'expéditeur n\'est pas valide.',
                );
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('temp-email-attachments');
                $attachments[] = [
                    'path' => storage_path('app/private/'.$path),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        $to = [['email' => $email->from_email, 'name' => $email->from_name]];
        $subject = 'Re: '.preg_replace("/^(Re:\s*)+/i", '', $email->subject);

        $this->emailService->sendEmail(
            $to,
            $subject,
            $data['body'],
            $attachments,
            $email->from_email, // replyToEmail - for reply-to header
            auth()->id(),
            null, // cc
            $email->id, // replyToMessageId - for threading
        );

        return redirect()
            ->route('emails.show', $email->thread_id)
            ->with('success', 'Réponse envoyée.');
    }

    public function forward(Request $request, Email $email)
    {
        $data = $request->validate([
            'to' => ['required', 'array', 'min:1'],
            'to.*.email' => ['required', 'email'],
            'to.*.name' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
        ]);

        $subject =
            'Fwd: '.preg_replace("/^(Fwd:\s*)+/i", '', $email->subject);
        $body =
            ($data['body'] ?? '').
            "\n\n---------- Message transféré ----------\n".
            $email->body_html;

        $this->emailService->sendEmail(
            $data['to'],
            $subject,
            $body,
            [],
            null, // replyToEmail
            auth()->id(),
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
        $emailService = $this->emailService;

        // Lance le sync APRES l'envoi de la réponse HTTP
        // → le navigateur ne reste pas bloqué pendant la connexion IMAP
        dispatch(function () use ($emailService, $deep) {
            try {
                $emailService->syncInbox($deep);
            } catch (\Throwable $e) {
                // silence
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
