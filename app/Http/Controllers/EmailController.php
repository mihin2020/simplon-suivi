<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Formation;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\Mime\Encoder\Base64ContentEncoder;

class EmailController extends Controller
{
    public function __construct(protected EmailService $emailService) {}

    public function index(Request $request)
    {
        $threads = Email::received()
            ->when(
                $request->input("filter") !== "archived",
                fn($q) => $q->where("is_archived", false),
            )
            ->when(
                $request->input("filter") === "archived",
                fn($q) => $q->where("is_archived", true),
            )
            ->select("thread_id")
            ->selectRaw("MAX(COALESCE(received_at, created_at)) as last_at")
            ->groupBy("thread_id")
            ->orderByDesc("last_at")
            ->paginate(10)
            ->through(function ($t) {
                // Dernier email REÇU du thread (le plus récent en premier)
                $lastEmail = Email::where("thread_id", $t->thread_id)
                    ->where("direction", "received")
                    ->orderByDesc("received_at")
                    ->orderByDesc("created_at")
                    ->first();

                $replyCount = Email::where("thread_id", $t->thread_id)
                    ->where("direction", "received")
                    ->count();

                $sentSubject = Email::where("thread_id", $t->thread_id)
                    ->where("direction", "sent")
                    ->value("subject");

                return [
                    "thread_id" => $t->thread_id,
                    "reply_count" => $replyCount,
                    "sent_subject" => $sentSubject,
                    "last_email" => $lastEmail?->only([
                        "id",
                        "from_name",
                        "from_email",
                        "subject",
                        "is_read",
                        "received_at",
                        "created_at",
                    ]),
                ];
            });

        $inboxCount = \App\Models\Email::received()
            ->where("is_archived", false)
            ->distinct("thread_id")
            ->count("thread_id");
        $archivedCount = \App\Models\Email::received()
            ->where("is_archived", true)
            ->distinct("thread_id")
            ->count("thread_id");
        $sentCount = \App\Models\Email::sent()
            ->where("is_archived", false)
            ->count();
        $unreadCount = \App\Models\Email::received()
            ->where("is_archived", false)
            ->where("is_read", false)
            ->distinct("thread_id")
            ->count("thread_id");

        return Inertia::render("Communication/Index", [
            "threads" => $threads,
            "filter" => $request->input("filter", "inbox"),
            "inboxCount" => $inboxCount,
            "archivedCount" => $archivedCount,
            "sentCount" => $sentCount,
            "unreadCount" => $unreadCount,
        ]);
    }

    public function sent()
    {
        $emails = Email::sent()
            ->where("is_archived", false)
            ->orderByDesc("sent_at")
            ->paginate(5);

        $inboxCount = \App\Models\Email::received()
            ->where("is_archived", false)
            ->distinct("thread_id")
            ->count("thread_id");
        $archivedCount = \App\Models\Email::received()
            ->where("is_archived", true)
            ->distinct("thread_id")
            ->count("thread_id");
        $sentCount = \App\Models\Email::sent()
            ->where("is_archived", false)
            ->count();
        $unreadCount = \App\Models\Email::received()
            ->where("is_archived", false)
            ->where("is_read", false)
            ->distinct("thread_id")
            ->count("thread_id");

        return Inertia::render("Communication/Sent", [
            "emails" => $emails,
            "inboxCount" => $inboxCount,
            "archivedCount" => $archivedCount,
            "sentCount" => $sentCount,
            "unreadCount" => $unreadCount,
        ]);
    }

    public function show(string $threadId)
    {
        $emails = Email::with("attachments", "sender")
            ->where("thread_id", $threadId)
            ->orderBy("received_at")
            ->orderBy("created_at")
            ->get();

        // Mark all received emails in thread as read
        Email::where("thread_id", $threadId)
            ->where("direction", "received")
            ->where("is_read", false)
            ->update(["is_read" => true]);

        return Inertia::render("Communication/Thread", [
            "threadId" => $threadId,
            "emails" => $emails,
        ]);
    }

    public function compose()
    {
        $projects = \App\Models\Project::select("id", "name")
            ->orderBy("name")
            ->get();

        return Inertia::render("Communication/Compose", [
            "projects" => $projects,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "to" => ["required", "array", "min:1", "max:300"],
            "to.*.email" => ["required", "email"],
            "to.*.name" => ["nullable", "string"],
            "cc" => ["nullable", "array"],
            "cc.*.email" => ["required", "email"],
            "subject" => ["required", "string", "max:255"],
            "body" => ["required", "string"],
            "attachments" => ["nullable", "array"],
            "attachments.*" => ["file", "max:10240"],
        ]);

        $attachments = [];
        if ($request->hasFile("attachments")) {
            foreach ($request->file("attachments") as $file) {
                $path = $file->store("temp-email-attachments");
                $attachments[] = [
                    "path" => storage_path("app/private/" . $path),
                    "name" => $file->getClientOriginalName(),
                ];
            }
        }

        // Extraire uniquement les valeurs scalaires pour éviter la sérialisation
        // des objets UploadedFile présents dans $data
        $to = $data["to"];
        $subject = $data["subject"];
        $body = $data["body"];
        $cc = $data["cc"] ?? null;
        $emailService = $this->emailService;
        $sentBy = auth()->id();

        // Envoi après la réponse HTTP (non bloquant, sans worker)
        dispatch(function () use (
            $emailService,
            $to,
            $subject,
            $body,
            $attachments,
            $cc,
            $sentBy,
        ) {
            try {
                $emailService->sendEmail(
                    $to,
                    $subject,
                    $body,
                    $attachments,
                    null,
                    $sentBy,
                    $cc,
                    null,
                );
            } catch (\Throwable $e) {
                // silence
            }
        })->afterResponse();

        return redirect()
            ->route("emails.sent")
            ->with("success", "Email en cours d'envoi.");
    }

    public function reply(Request $request, Email $email)
    {
        $data = $request->validate([
            "body" => ["required", "string"],
            "attachments" => ["nullable", "array"],
            "attachments.*" => ["file", "max:10240"],
        ]);

        // Validate that the sender email is a valid email address
        if (!filter_var($email->from_email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->back()
                ->with(
                    "error",
                    'Impossible de répondre : l\'adresse email de l\'expéditeur n\'est pas valide.',
                );
        }

        $attachments = [];
        if ($request->hasFile("attachments")) {
            foreach ($request->file("attachments") as $file) {
                $path = $file->store("temp-email-attachments");
                $attachments[] = [
                    "path" => storage_path("app/private/" . $path),
                    "name" => $file->getClientOriginalName(),
                ];
            }
        }

        $to = [["email" => $email->from_email, "name" => $email->from_name]];
        $subject = "Re: " . preg_replace("/^(Re:\s*)+/i", "", $email->subject);

        $this->emailService->sendEmail(
            $to,
            $subject,
            $data["body"],
            $attachments,
            $email->from_email, // replyToEmail - for reply-to header
            auth()->id(),
            null, // cc
            $email->id, // replyToMessageId - for threading
        );

        return redirect()
            ->route("emails.show", $email->thread_id)
            ->with("success", "Réponse envoyée.");
    }

    public function forward(Request $request, Email $email)
    {
        $data = $request->validate([
            "to" => ["required", "array", "min:1"],
            "to.*.email" => ["required", "email"],
            "to.*.name" => ["nullable", "string"],
            "body" => ["nullable", "string"],
        ]);

        $subject =
            "Fwd: " . preg_replace("/^(Fwd:\s*)+/i", "", $email->subject);
        $body =
            ($data["body"] ?? "") .
            "\n\n---------- Message transféré ----------\n" .
            $email->body_html;

        $this->emailService->sendEmail(
            $data["to"],
            $subject,
            $body,
            [],
            null, // replyToEmail
            auth()->id(),
            null, // cc
            null, // replyToMessageId
        );

        return redirect()
            ->route("emails.sent")
            ->with("success", "Email transféré.");
    }

    public function sync(Request $request)
    {
        $deep = $request->boolean("deep");
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
            ? "Synchronisation approfondie (6 mois) lancée en arrière-plan."
            : "Synchronisation lancée en arrière-plan. Actualisez dans quelques secondes.";

        return back()->with("success", $msg);
    }

    public function whatsapp()
    {
        $formations = Formation::with([
            "project",
            "learners" => function ($query) {
                $query->whereNotNull("phone")
                      ->where("formation_learner.status", \App\Enums\LearnerStatus::InProgress);
            },
        ])
            ->whereHas("learners", function ($query) {
                $query->whereNotNull("phone")
                      ->where("formation_learner.status", \App\Enums\LearnerStatus::InProgress);
            })
            ->get()
            ->map(
                fn($f) => [
                    "id" => $f->id,
                    "name" => $f->name,
                    "project" => [
                        "id" => $f->project->id,
                        "name" => $f->project->name,
                    ],
                    "learners" => $f->learners->map(
                        fn($l) => [
                            "id" => $l->id,
                            "first_name" => $l->first_name,
                            "last_name" => $l->last_name,
                            "phone" => $l->phone,
                            "email" => $l->email,
                        ],
                    ),
                ],
            );

        $waConfig = WhatsAppService::getConfig();

        return Inertia::render("Communication/WhatsApp", [
            "formations"       => $formations,
            "whatsappConfig"   => $waConfig,
        ]);
    }

    public function sendWhatsAppBulk(
        Request $request,
        WhatsAppService $whatsAppService,
    ) {
        $validated = $request->validate([
            "recipients" => "required|array|min:1",
            "recipients.*.phone" => "required|string",
            "recipients.*.name" => "nullable|string",
            "message" => "required|string|max:1600", // WhatsApp limite
        ]);

        // Vérifier si WhatsApp est configuré
        if (!$whatsAppService->isConfigured()) {
            $provider = $whatsAppService->getProvider();
            $configMsg = $provider === 'meta'
                ? "Meta Cloud API non configurée. Vérifiez vos paramètres WhatsApp."
                : "Twilio non configuré. Ajoutez vos credentials dans la Configuration.";
            return back()->with("error", $configMsg);
        }

        $results = $whatsAppService->sendBulk(
            $validated["recipients"],
            $validated["message"],
        );

        if ($results["failed"] === 0) {
            return back()->with(
                "success",
                "✅ {$results["success"]} message(s) WhatsApp envoyé(s) avec succès !",
            );
        } elseif ($results["success"] === 0) {
            $firstError = $results["errors"][0]["error"] ?? "Erreur inconnue";
            return back()->with(
                "error",
                "❌ Échec de l'envoi. Erreur: {$firstError}",
            );
        } else {
            $msg = "⚠️ {$results["success"]} envoyé(s), {$results["failed"]} échec(s)";
            return back()->with("warning", $msg);
        }
    }

    public function archive(Email $email)
    {
        $this->emailService->archive($email->id);
        return back()->with("success", "Email archivé.");
    }

    public function unarchive(Email $email)
    {
        $email->update(["is_archived" => false]);
        return back()->with(
            "success",
            "Email restauré dans la boîte de réception.",
        );
    }

    public function showSent(Email $email)
    {
        $email->load("attachments");
        return Inertia::render("Communication/SentShow", [
            "email" => $email,
        ]);
    }

    public function downloadAttachment(\App\Models\EmailAttachment $attachment)
    {
        if (! Storage::disk('local')->exists($attachment->path)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }

    public function destroy(Email $email)
    {
        foreach ($email->attachments as $attachment) {
            Storage::disk("local")->delete($attachment->path);
        }
        $email->delete();
        return back()->with("success", "Email supprimé.");
    }
}
