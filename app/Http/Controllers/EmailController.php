<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;
use App\Services\EmailService;
use App\Services\WhatsAppWWebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\Mime\Encoder\Base64ContentEncoder;

class EmailController extends Controller
{
    public function __construct(protected EmailService $emailService) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input("search", ""));

        $threads = Email::received()
            ->when(
                $request->input("filter") !== "archived",
                fn($q) => $q->where("is_archived", false),
            )
            ->when(
                $request->input("filter") === "archived",
                fn($q) => $q->where("is_archived", true),
            )
            ->when($search !== "", function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function ($sub) use ($like) {
                    $sub->where("subject", "like", $like)
                        ->orWhere("from_name", "like", $like)
                        ->orWhere("from_email", "like", $like)
                        ->orWhere("body_text", "like", $like);
                });
            })
            ->select("thread_id")
            ->selectRaw("MAX(COALESCE(received_at, created_at)) as last_at")
            ->groupBy("thread_id")
            ->orderByDesc("last_at")
            ->paginate(15)
            ->withQueryString()
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

                $hasAttachments = $lastEmail
                    ? $lastEmail->attachments()->exists()
                    : false;

                return [
                    "thread_id" => $t->thread_id,
                    "reply_count" => $replyCount,
                    "sent_subject" => $sentSubject,
                    "has_attachments" => $hasAttachments,
                    "snippet" => $this->makeSnippet($lastEmail),
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
            "search" => $search,
            "inboxCount" => $inboxCount,
            "archivedCount" => $archivedCount,
            "sentCount" => $sentCount,
            "unreadCount" => $unreadCount,
        ]);
    }

    /**
     * Génère un court extrait texte (snippet) à partir du corps d'un email,
     * façon aperçu Gmail dans la liste.
     */
    private function makeSnippet(?Email $email): string
    {
        if (! $email) {
            return "";
        }

        $text = $email->body_text;

        if (! $text) {
            // Repli sur le HTML nettoyé si pas de version texte
            $text = strip_tags((string) $email->body_html);
        }

        $text = preg_replace('/\s+/u', " ", (string) $text);

        return Str::limit(trim($text), 140);
    }

    public function sent(Request $request)
    {
        $search = trim((string) $request->input("search", ""));

        $emails = Email::sent()
            ->where("is_archived", false)
            ->when($search !== "", function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(function ($sub) use ($like) {
                    $sub->where("subject", "like", $like)
                        ->orWhere("body_text", "like", $like)
                        ->orWhere("to", "like", $like);
                });
            })
            ->orderByDesc("sent_at")
            ->paginate(15)
            ->withQueryString()
            ->through(function ($e) {
                $arr = $e->only([
                    "id",
                    "subject",
                    "to",
                    "sent_at",
                ]);
                $arr["has_attachments"] = $e->attachments()->exists();
                $arr["snippet"] = $this->makeSnippet($e);

                return $arr;
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

        return Inertia::render("Communication/Sent", [
            "emails" => $emails,
            "search" => $search,
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

    public function whatsapp(WhatsAppWWebService $service)
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
            ->map(fn($f) => [
                "id"       => $f->id,
                "name"     => $f->name,
                "project"  => ["id" => $f->project->id, "name" => $f->project->name],
                "learners" => $f->learners->map(fn($l) => [
                    "id"         => $l->id,
                    "first_name" => $l->first_name,
                    "last_name"  => $l->last_name,
                    "phone"      => $l->phone,
                ]),
            ]);

        $waStatus = $service->getStatus();

        return Inertia::render("Communication/WhatsApp", [
            "formations"      => $formations,
            "initialWaStatus" => [
                'connected'     => $waStatus['connected']     ?? false,
                'authenticating' => $waStatus['authenticating'] ?? false,
                'qr'            => $waStatus['qr']             ?? null,
                'available'     => !isset($waStatus['unavailable']),
                'phone'         => $waStatus['phone']          ?? null,
            ],
        ]);
    }

    public function whatsappStatus(WhatsAppWWebService $service): JsonResponse
    {
        $status = $service->getStatus();

        return response()->json([
            'connected'     => $status['connected'] ?? false,
            'authenticating' => $status['authenticating'] ?? false,
            'qr'            => $status['qr'] ?? null,
            'available'     => ! isset($status['unavailable']),
        ]);
    }

    public function sendWhatsAppBulk(Request $request, WhatsAppWWebService $service)
    {
        // L'envoi peut prendre N×1s pour N apprenants — on lève la limite PHP
        set_time_limit(300);

        $validated = $request->validate([
            'recipients'              => 'required|array|min:1',
            'recipients.*.phone'      => 'required|string',
            'recipients.*.name'       => 'nullable|string',
            'recipients.*.learner_id' => 'nullable|uuid|exists:learners,id',
            'message'                 => 'nullable|string|max:4096',
            'formation_name'          => 'nullable|string|max:255',
            'project_name'            => 'nullable|string|max:255',
            'attachments'             => 'nullable|array|max:10',
            'attachments.*'           => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,mp4,mp3,ogg|max:16384',
        ]);

        $messageText = $validated['message'] ?? '';
        $broadcastId = (string) Str::uuid();
        $uploadedFiles = $request->file('attachments') ?? [];

        // Construire la liste des envois : texte puis chaque pièce jointe séparément
        // Le texte sert de légende sur la 1ère pièce jointe s'il y en a une.
        $sends = [];
        if (empty($uploadedFiles)) {
            $sends[] = ['message' => $messageText, 'file' => null];
        } else {
            foreach ($uploadedFiles as $i => $file) {
                $sends[] = ['message' => ($i === 0 ? $messageText : ''), 'file' => $file];
            }
        }

        $totalSuccess = 0;
        $totalFailed  = 0;

        foreach ($sends as $send) {
            $attachmentPayload = null;
            $attachmentPath    = null;
            $attachmentMime    = null;
            $attachmentName    = null;

            if ($send['file']) {
                $file           = $send['file'];
                $ext            = $file->extension() ?: last(explode('/', $file->getMimeType() ?? 'bin'));
                $storedName     = \Illuminate\Support\Str::uuid() . '.' . $ext;
                $attachmentPath = 'whatsapp_media/' . $storedName;
                $attachmentMime = $file->getMimeType();
                $attachmentName = $file->getClientOriginalName();
                \Illuminate\Support\Facades\Storage::disk('local')->putFileAs('whatsapp_media', $file, $storedName);
                $attachmentPayload = [
                    'data'     => base64_encode(file_get_contents($file->getRealPath())),
                    'mimetype' => $attachmentMime,
                    'filename' => $attachmentName,
                ];
            }

            $results = $service->sendBulk($validated['recipients'], $send['message'], $attachmentPayload);
            $totalSuccess += $results['success'] ?? 0;
            $totalFailed  += $results['failed']  ?? 0;

            $errorsByPhone = collect($results['errors'] ?? [])
                ->mapWithKeys(fn($e) => [preg_replace('/\D/', '', $e['phone'] ?? '') => $e['error'] ?? 'Échec inconnu'])
                ->toArray();

            $sentIds = $results['sentIds'] ?? [];

            foreach ($validated['recipients'] as $r) {
                $normalizedPhone = preg_replace('/\D/', '', $r['phone']);
                $failed          = array_key_exists($normalizedPhone, $errorsByPhone);
                $waId            = $sentIds[$normalizedPhone] ?? null;

                WhatsAppMessage::create([
                    'phone'               => $r['phone'],
                    'recipient_name'      => $r['name'] ?? null,
                    'message'             => $send['message'],
                    'direction'           => 'sent',
                    'provider'            => 'wwebjs',
                    'status'              => $failed ? 'failed' : 'sent',
                    'error'               => $failed ? $errorsByPhone[$normalizedPhone] : null,
                    'learner_id'          => $r['learner_id'] ?? null,
                    'formation_name'      => $validated['formation_name'] ?? null,
                    'project_name'        => $validated['project_name'] ?? null,
                    'broadcast_id'        => $broadcastId,
                    'attachment_path'     => $attachmentPath,
                    'attachment_mimetype' => $attachmentMime,
                    'attachment_filename' => $attachmentName,
                    'external_id'         => $waId,
                ]);
            }
        }

        if ($totalFailed === 0) {
            return back()->with('success', "{$totalSuccess} message(s) WhatsApp envoyé(s) !");
        } elseif ($totalSuccess === 0) {
            return back()->with('error', 'Aucun message envoyé. Vérifiez la connexion WhatsApp.');
        }

        return back()->with('warning', "{$totalSuccess} envoyé(s), {$totalFailed} échec(s)");
    }

    public function whatsappMessages(): JsonResponse
    {
        $messages = WhatsAppMessage::with('learner:id,first_name,last_name')
            ->where('provider', 'wwebjs')
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get(['id', 'phone', 'recipient_name', 'message', 'direction', 'status', 'learner_id', 'formation_name', 'project_name', 'created_at']);

        return response()->json($messages);
    }

    public function whatsappSyncReplies(WhatsAppWWebService $service): JsonResponse
    {
        // IDs des apprenants à qui on a déjà envoyé un message depuis cette plateforme
        $messagedLearnerIds = WhatsAppMessage::where('direction', 'sent')
            ->whereNotNull('learner_id')
            ->pluck('learner_id')
            ->unique()
            ->toArray();

        if (empty($messagedLearnerIds)) {
            return response()->json(['synced' => 0]);
        }

        // ── Fenêtre de synchro basée sur le VRAI timestamp WhatsApp ──
        // Bug corrigé : on utilisait auparavant created_at (heure d'enregistrement
        // serveur), POSTÉRIEURE au timestamp d'envoi WhatsApp à cause du délai de
        // synchro. La fenêtre était donc poussée trop en avant et les réponses
        // envoyées peu après un message déjà enregistré étaient filtrées puis perdues.
        // On garde désormais le dernier timestamp WhatsApp réellement vu (même
        // référentiel que m.timestamp côté microservice).
        $lastTs = (int) \Illuminate\Support\Facades\Cache::get('wa_last_incoming_ts', 0);
        // Marge de 15 s : la déduplication par wa_id évite les doublons et garantit
        // qu'aucune réponse rapprochée n'est sautée.
        $since  = $lastTs > 0 ? max(0, $lastTs - 15) : 0;

        $incoming = $service->getIncoming($since);

        $maxTs = $lastTs;
        $saved = 0;
        foreach ($incoming as $msg) {
            // Avance la fenêtre sur TOUS les messages vus (même ceux ignorés ensuite)
            $ts = (int) ($msg['timestamp'] ?? 0);
            if ($ts > $maxTs) {
                $maxTs = $ts;
            }

            $from = (string) ($msg['from'] ?? '');

            // ── Garde-fous : ne JAMAIS enregistrer un statut/groupe/broadcast ──
            // Même si le microservice laisse passer du bruit, on bloque ici.
            if ($from === '' || str_contains($from, 'broadcast') || str_contains($from, 'status') || str_contains($from, '@g.us')) {
                continue;
            }

            // Numéro réduit aux chiffres, sans préfixe pays 226
            $shortPhone = preg_replace('/^226/', '', preg_replace('/\D/', '', $from));

            // Un numéro valide a au moins 8 chiffres — sinon le LIKE matcherait n'importe qui
            if (strlen($shortPhone) < 8) {
                continue;
            }

            // Déduplication par wa_id (unique) ; fallback sur timestamp si ancien message
            $externalId = $msg['wa_id'] ?? (string) $msg['timestamp'];
            $alreadyExists = WhatsAppMessage::where('external_id', $externalId)
                ->where('direction', 'received')
                ->exists();

            if ($alreadyExists) continue;

            // Retrouver l'apprenant — UNIQUEMENT parmi ceux à qui on a écrit.
            // On essaie plusieurs variantes du numéro car les téléphones peuvent être
            // stockés en base sous différents formats (local, national, international).
            $withPrefix    = '226' . ltrim($shortPhone, '0');
            $withPlusPrefix = '+226' . ltrim($shortPhone, '0');

            $learner = Learner::whereIn('id', $messagedLearnerIds)
                ->where(function ($q) use ($shortPhone, $from, $withPrefix, $withPlusPrefix) {
                    $q->where('phone', 'like', "%{$shortPhone}")
                      ->orWhere('phone', $from)
                      ->orWhere('phone', $withPrefix)
                      ->orWhere('phone', $withPlusPrefix);
                })
                ->first();

            // Ignorer les messages de personnes inconnues ou à qui on n'a pas écrit
            if (!$learner) continue;

            // Stocker la pièce jointe si présente
            $recvAttachmentPath = null;
            $recvAttachmentMime = null;
            $recvAttachmentName = null;
            if (!empty($msg['attachment_data']) && !empty($msg['attachment_mimetype'])) {
                // Extraire l'extension depuis le mimetype (ignorer les paramètres ex: "audio/ogg; codecs=opus")
                $mimeBase = trim(explode(';', $msg['attachment_mimetype'])[0]);
                $ext      = preg_replace('/[^a-z0-9]/i', '', last(explode('/', $mimeBase))) ?: 'bin';
                $name     = \Illuminate\Support\Str::uuid() . '.' . $ext;
                \Illuminate\Support\Facades\Storage::disk('local')->put('whatsapp_media/' . $name, base64_decode($msg['attachment_data']));
                $recvAttachmentPath = 'whatsapp_media/' . $name;
                $recvAttachmentMime = $mimeBase;
                $recvAttachmentName = $msg['attachment_filename'] ?? $name;
            }

            WhatsAppMessage::create([
                'phone'               => $from,
                'recipient_name'      => "{$learner->first_name} {$learner->last_name}",
                'message'             => $msg['body'] ?? '',
                'direction'           => 'received',
                'provider'            => 'wwebjs',
                'status'              => 'received',
                'external_id'         => $externalId,
                'learner_id'          => $learner->id,
                'attachment_path'     => $recvAttachmentPath,
                'attachment_mimetype' => $recvAttachmentMime,
                'attachment_filename' => $recvAttachmentName,
            ]);
            $saved++;
        }

        // Mémorise le dernier timestamp WhatsApp vu pour la prochaine fenêtre
        if ($maxTs > $lastTs) {
            \Illuminate\Support\Facades\Cache::put('wa_last_incoming_ts', $maxTs, now()->addDays(7));
        }

        // Sync des révocations (messages supprimés côté apprenant)
        $lastRevoke = \Illuminate\Support\Facades\Cache::get('wa_last_revoke_sync', '');
        $revoked = $service->getRevokedMessages($lastRevoke);
        $revokedCount = 0;
        foreach ($revoked as $rev) {
            $waId = $rev['wa_id'] ?? null;
            if (!$waId) continue;
            $toDelete = WhatsAppMessage::where('external_id', $waId)
                ->whereNull('deleted_at')
                ->first();
            if ($toDelete) {
                $toDelete->delete();
                $revokedCount++;
            }
        }
        if (!empty($revoked)) {
            \Illuminate\Support\Facades\Cache::put('wa_last_revoke_sync', now()->toISOString(), now()->addDay());
        }

        return response()->json(['synced' => $saved, 'revoked' => $revokedCount]);
    }

    public function whatsappBroadcasts(): JsonResponse
    {
        // Tous les envois groupés (sent avec broadcast_id)
        $sent = WhatsAppMessage::where('direction', 'sent')
            ->whereNotNull('broadcast_id')
            ->where('provider', 'wwebjs')
            ->orderBy('created_at', 'desc')
            ->get(['broadcast_id', 'formation_name', 'project_name', 'message', 'created_at', 'learner_id', 'status', 'attachment_path']);

        // IDs des apprenants qui ont répondu (une seule requête)
        $repliedLearnerIds = WhatsAppMessage::where('direction', 'received')
            ->whereNotNull('learner_id')
            ->pluck('learner_id')
            ->unique()
            ->flip(); // flip pour lookup O(1)

        // Toutes les réponses reçues, indexées par learner_id + timestamp
        $allReplies = WhatsAppMessage::where('direction', 'received')
            ->whereNotNull('learner_id')
            ->get(['learner_id', 'created_at', 'read_at']);

        $broadcasts = $sent->groupBy('broadcast_id')
            ->map(function ($msgs) use ($allReplies) {
                $first          = $msgs->first();
                $broadcastSentAt = $msgs->min('created_at');
                $learnerIds     = $msgs->pluck('learner_id')->filter()->unique();

                // Ne compter que les réponses non lues arrivées APRÈS cet envoi spécifique
                $replyCount = $learnerIds->filter(function ($id) use ($allReplies, $broadcastSentAt) {
                    return $allReplies->where('learner_id', $id)
                        ->where('created_at', '>=', $broadcastSentAt)
                        ->whereNull('read_at')
                        ->isNotEmpty();
                })->count();

                // Aperçu : premier message non vide, sinon label du fichier
                $preview = $msgs->first(fn($m) => !empty(trim($m->message)))?->message
                    ?? ($msgs->first()->attachment_path ? '📎 Fichier(s) joint(s)' : '');

                return [
                    'broadcast_id'    => $first->broadcast_id,
                    'formation_name'  => $first->formation_name,
                    'project_name'    => $first->project_name,
                    'message'         => $preview,
                    'sent_at'         => $first->created_at,
                    // Destinataires uniques (N fichiers à 1 personne = 1 destinataire)
                    'recipient_count' => $learnerIds->count() ?: $msgs->pluck('phone')->unique()->count(),
                    'reply_count'     => $replyCount,
                    // Échecs uniques (ne pas compter 3× le même destinataire)
                    'failed_count'    => $msgs->where('status', 'failed')
                        ->pluck('learner_id')->filter()->unique()->count()
                        ?: $msgs->where('status', 'failed')->pluck('phone')->unique()->count(),
                ];
            })
            ->values()
            ->sortByDesc('sent_at')
            ->values();

        return response()->json($broadcasts);
    }

    public function whatsappBroadcastRecipients(string $broadcastId): JsonResponse
    {
        $sent = WhatsAppMessage::with('learner:id,first_name,last_name')
            ->where('broadcast_id', $broadcastId)
            ->where('direction', 'sent')
            ->orderBy('created_at')
            ->get(['id', 'phone', 'recipient_name', 'status', 'error', 'learner_id', 'created_at']);

        $learnerIds = $sent->pluck('learner_id')->filter()->unique()->toArray();

        // Timestamp du premier message de cette campagne.
        $broadcastSentAt = $sent->min('created_at');

        $replies = WhatsAppMessage::where('direction', 'received')
            ->whereIn('learner_id', $learnerIds)
            ->where('created_at', '>=', $broadcastSentAt)
            ->orderBy('created_at')
            ->get(['id', 'message', 'learner_id', 'created_at', 'read_at']);

        $repliesByLearner = $replies->groupBy('learner_id');

        // Dédupliquer par destinataire : N fichiers envoyés à 1 personne = 1 ligne
        // On prend le premier message par destinataire pour les infos (nom, téléphone, statut)
        $sentByRecipient = $sent
            ->groupBy(fn($m) => $m->learner_id ?? $m->phone)
            ->map(fn($msgs) => $msgs->first());

        $recipients = $sentByRecipient->map(function ($msg) use ($repliesByLearner) {
            $learnerReplies = $repliesByLearner->get($msg->learner_id, collect());
            $lastReply      = $learnerReplies->last();
            $name           = $msg->learner
                ? "{$msg->learner->first_name} {$msg->learner->last_name}"
                : ($msg->recipient_name ?? $msg->phone);

            return [
                'id'          => $msg->id,
                'phone'       => $msg->phone,
                'learner_id'  => $msg->learner_id,
                'name'        => $name,
                'status'      => $msg->status,
                'error'       => $msg->error,
                'sent_at'     => $msg->created_at,
                'has_replied' => $learnerReplies->isNotEmpty(),
                'reply_count' => $learnerReplies->whereNull('read_at')->count(),
                'last_reply'  => $lastReply ? [
                    'message'    => $lastReply->message,
                    'created_at' => $lastReply->created_at,
                ] : null,
            ];
        })
        ->sortByDesc('has_replied')
        ->values();

        return response()->json($recipients);
    }

    public function whatsappThread(string $learnerId): JsonResponse
    {
        // withTrashed() pour afficher les messages supprimés avec indicateur visuel
        $messages = WhatsAppMessage::withTrashed()
            ->where('learner_id', $learnerId)
            ->where('provider', 'wwebjs')
            ->orderBy('created_at')
            ->get(['id', 'message', 'direction', 'status', 'error', 'broadcast_id', 'created_at',
                   'deleted_at', 'attachment_path', 'attachment_mimetype', 'attachment_filename']);

        return response()->json($messages);
    }

    public function serveWhatsAppMedia(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = 'whatsapp_media/' . basename($filename);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path);
    }

    public function replyWhatsApp(Request $request, WhatsAppWWebService $service): JsonResponse
    {
        set_time_limit(60);

        $validated = $request->validate([
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
            'message'    => ['nullable', 'string', 'max:4096'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,mp3,ogg,doc,docx', 'max:16384'],
        ]);

        if (empty($validated['message']) && !$request->hasFile('attachment')) {
            return response()->json(['success' => false, 'error' => 'Message ou pièce jointe requis.'], 422);
        }

        $learner = \App\Models\Learner::findOrFail($validated['learner_id']);

        if (!$learner->phone) {
            return response()->json(['success' => false, 'error' => 'Cet apprenant n\'a pas de numéro de téléphone.'], 422);
        }

        $recipient = [
            'phone'      => $learner->phone,
            'name'       => "{$learner->first_name} {$learner->last_name}",
            'learner_id' => $learner->id,
        ];

        $attachmentPayload = null;
        $attachmentPath    = null;
        $attachmentMime    = null;
        $attachmentName    = null;

        if ($request->hasFile('attachment')) {
            $file          = $request->file('attachment');
            $ext           = $file->extension() ?: last(explode('/', $file->getMimeType() ?? 'application/bin'));
            $storedName    = \Illuminate\Support\Str::uuid() . '.' . $ext;
            $attachmentPath = 'whatsapp_media/' . $storedName;
            $attachmentMime = $file->getMimeType();
            $attachmentName = $file->getClientOriginalName();

            \Illuminate\Support\Facades\Storage::disk('local')->putFileAs('whatsapp_media', $file, $storedName);

            $attachmentPayload = [
                'data'     => base64_encode(file_get_contents($file->getRealPath())),
                'mimetype' => $attachmentMime,
                'filename' => $attachmentName,
            ];
        }

        $message = $validated['message'] ?? '';
        $results = $service->sendBulk([$recipient], $message, $attachmentPayload);
        $failed  = ($results['success'] ?? 0) === 0;
        $errMsg  = $failed ? ($results['errors'][0]['error'] ?? 'Échec envoi') : null;

        $normalizedPhone = preg_replace('/\D/', '', $learner->phone ?? '');
        $waId = $results['sentIds'][$normalizedPhone] ?? null;

        WhatsAppMessage::create([
            'phone'               => $learner->phone,
            'recipient_name'      => "{$learner->first_name} {$learner->last_name}",
            'message'             => $message ?: ($attachmentPath ? '' : ''),
            'direction'           => 'sent',
            'provider'            => 'wwebjs',
            'status'              => $failed ? 'failed' : 'sent',
            'error'               => $errMsg,
            'learner_id'          => $learner->id,
            'broadcast_id'        => null,
            'attachment_path'     => $attachmentPath,
            'attachment_mimetype' => $attachmentMime,
            'attachment_filename' => $attachmentName,
            'external_id'         => $waId,
        ]);

        return response()->json(['success' => !$failed, 'error' => $errMsg]);
    }

    public function markThreadRead(string $learnerId): JsonResponse
    {
        WhatsAppMessage::where('learner_id', $learnerId)
            ->where('direction', 'received')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function deleteWhatsAppBroadcast(string $broadcastId): JsonResponse
    {
        $count = WhatsAppMessage::where('broadcast_id', $broadcastId)->count();

        WhatsAppMessage::where('broadcast_id', $broadcastId)->delete();

        return response()->json(['deleted' => $count]);
    }

    public function deleteWhatsAppMessage(int $id, WhatsAppWWebService $service): JsonResponse
    {
        $msg = WhatsAppMessage::findOrFail($id);

        // Tenter la révocation côté WhatsApp si on a le wa_id
        if ($msg->external_id) {
            $service->revokeMessage($msg->external_id);
        }

        $msg->delete();

        return response()->json(['deleted' => true]);
    }

    public function whatsappDiag(WhatsAppWWebService $service): JsonResponse
    {
        // 1. État du microservice
        $debug = $service->debug();

        // 2. Derniers messages envoyés
        $sentMessages = WhatsAppMessage::where('direction', 'sent')
            ->where('provider', 'wwebjs')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'phone', 'learner_id', 'status', 'created_at']);

        // 3. IDs apprenants à qui on a écrit
        $messagedLearnerIds = WhatsAppMessage::where('direction', 'sent')
            ->whereNotNull('learner_id')
            ->pluck('learner_id')
            ->unique()
            ->toArray();

        // 4. Phones des apprenants concernés
        $learnerPhones = \App\Models\Learner::whereIn('id', $messagedLearnerIds)
            ->pluck('phone', 'id');

        // 5. Derniers messages reçus en base
        $receivedInDb = WhatsAppMessage::where('direction', 'received')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'phone', 'learner_id', 'external_id', 'created_at']);

        // 6. Dernier message reçu synchronisé
        $lastReceivedAt = WhatsAppMessage::where('direction', 'received')
            ->orderByDesc('created_at')
            ->value('created_at');
        $lastTimestamp = $lastReceivedAt ? \Carbon\Carbon::parse($lastReceivedAt)->timestamp : 0;

        return response()->json([
            'node_buffer'         => $debug,
            'last_sent'           => $sentMessages,
            'messaged_learners'   => $messagedLearnerIds,
            'learner_phones'      => $learnerPhones,
            'received_in_db'      => $receivedInDb,
            'last_synced_ts'      => $lastTimestamp,
        ]);
    }

    public function whatsappLogout(WhatsAppWWebService $service): JsonResponse
    {
        $service->logout();

        return response()->json(['success' => true]);
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
