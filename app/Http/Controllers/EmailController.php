<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\Mime\Encoder\Base64ContentEncoder;

class EmailController extends Controller
{
    public function __construct(protected EmailService $emailService)
    {
    }

    public function index(Request $request)
    {
        $threads = Email::received()
            ->when(! $request->boolean('archived'), fn ($q) => $q->where('is_archived', false))
            ->when($request->boolean('archived'), fn ($q) => $q->where('is_archived', true))
            ->select('thread_id')
            ->selectRaw('MAX(received_at) as last_at')
            ->groupBy('thread_id')
            ->orderByDesc('last_at')
            ->paginate(20)
            ->through(fn ($t) => [
                'thread_id' => $t->thread_id,
                'last_email' => Email::where('thread_id', $t->thread_id)
                    ->orderByDesc('received_at')
                    ->orderByDesc('created_at')
                    ->first()
                    ?->only(['id', 'from_name', 'from_email', 'subject', 'is_read', 'received_at', 'created_at']),
            ]);

        return Inertia::render('Communication/Index', [
            'threads' => $threads,
            'filter' => $request->input('filter', 'inbox'),
        ]);
    }

    public function sent()
    {
        $emails = Email::sent()
            ->where('is_archived', false)
            ->orderByDesc('sent_at')
            ->paginate(20);

        return Inertia::render('Communication/Sent', [
            'emails' => $emails,
        ]);
    }

    public function show(string $threadId)
    {
        $emails = Email::with('attachments', 'sender')
            ->where('thread_id', $threadId)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->get();

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
        $projects = \App\Models\Project::select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Communication/Compose', [
            'projects' => $projects,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'array', 'min:1'],
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
                    'path' => storage_path('app/private/' . $path),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        $this->emailService->sendEmail(
            $data['to'],
            $data['subject'],
            $data['body'],
            $attachments,
            null,  // replyToEmail
            auth()->id(),
            $data['cc'] ?? null,
            null  // replyToMessageId
        );

        return redirect()->route('emails.sent')->with('success', 'Email envoyé.');
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
            return redirect()->back()->with('error', 'Impossible de répondre : l\'adresse email de l\'expéditeur n\'est pas valide.');
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('temp-email-attachments');
                $attachments[] = [
                    'path' => storage_path('app/private/' . $path),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        $to = [['email' => $email->from_email, 'name' => $email->from_name]];
        $subject = 'Re: ' . preg_replace('/^(Re:\s*)+/i', '', $email->subject);

        $this->emailService->sendEmail(
            $to,
            $subject,
            $data['body'],
            $attachments,
            $email->from_email,  // replyToEmail - for reply-to header
            auth()->id(),
            null,  // cc
            $email->id  // replyToMessageId - for threading
        );

        return redirect()->route('emails.show', $email->thread_id)->with('success', 'Réponse envoyée.');
    }

    public function forward(Request $request, Email $email)
    {
        $data = $request->validate([
            'to' => ['required', 'array', 'min:1'],
            'to.*.email' => ['required', 'email'],
            'to.*.name' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
        ]);

        $subject = 'Fwd: ' . preg_replace('/^(Fwd:\s*)+/i', '', $email->subject);
        $body = ($data['body'] ?? '') . "\n\n---------- Message transféré ----------\n" . $email->body_html;

        $this->emailService->sendEmail(
            $data['to'],
            $subject,
            $body,
            [],
            null,  // replyToEmail
            auth()->id(),
            null,  // cc
            null   // replyToMessageId
        );

        return redirect()->route('emails.sent')->with('success', 'Email transféré.');
    }

    public function sync()
    {
        $this->emailService->syncInbox();
        return back()->with('success', 'Boîte de réception synchronisée.');
    }

    public function archive(Email $email)
    {
        $this->emailService->archive($email->id);
        return back()->with('success', 'Email archivé.');
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
