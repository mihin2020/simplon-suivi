<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;

class EmailService
{
    /**
     * Synchronize inbox from IMAP to local database.
     */
    /**
     * Synchronise la boîte IMAP.
     *
     * @param bool $deep  true  = remonte 6 mois (premier sync ou sync manuel approfondi)
     *                    false = remonte 7 jours seulement (sync courant)
     */
    public function syncInbox(bool $deep = false): void
    {
        // Pas de limite de temps : le sync tourne hors requête HTTP (afterResponse)
        @set_time_limit(0);

        $client = Client::account("default");
        $client->connect();

        // Timeout sur les opérations IMAP (lecture des messages, etc.)
        // À appeler après connect() — le protocole n'est disponible qu'une fois connecté
        try {
            $client->setTimeout(60);
        } catch (\Throwable) {
            /* ignore si non supporté */
        }

        $folder = $client->getFolder("INBOX");
        if (!$folder) {
            $client->disconnect();
            return;
        }

        $lastEmail = Email::received()->orderByDesc("received_at")->first();
        $sinceFromLast = $lastEmail?->received_at?->subDay();

        if ($deep) {
            // Deep sync : 6 mois pour rattraper toutes les réponses manquées
            $since = now()->subMonths(6);
        } else {
            // Sync courant : 7 jours maximum (rapide)
            $sinceNormal = now()->subDays(7);
            $since =
                !$sinceFromLast || $sinceFromLast->gt($sinceNormal)
                    ? $sinceNormal
                    : $sinceFromLast;
        }

        $messages = $folder->query()->since($since)->get();

        foreach ($messages as $message) {
            $messageId = $message->getMessageId()?->first();

            if (!$messageId) {
                continue;
            }

            // Skip already synced messages
            if (Email::where("message_id", $messageId)->exists()) {
                continue;
            }

            $fromAttr = $message->getFrom();
            $from = is_array($fromAttr)
                ? $fromAttr[0] ?? null
                : $fromAttr->first();

            // Validate from email address
            $fromEmail = $from?->mail ?? null;
            if ($fromEmail && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = "unknown";
            }

            $toList = collect($message->getTo() ?? [])
                ->map(
                    fn($r) => [
                        "email" => $r->mail ?? null,
                        "name" => $r->personal ?? null,
                    ],
                )
                ->filter(fn($r) => $r["email"])
                ->values()
                ->toArray();

            $ccList = collect($message->getCc() ?? [])
                ->map(
                    fn($r) => [
                        "email" => $r->mail ?? null,
                        "name" => $r->personal ?? null,
                    ],
                )
                ->filter(fn($r) => $r["email"])
                ->values()
                ->toArray();

            $threadId = $this->resolveThreadId($message);

            // On n'importe que les réponses à des emails envoyés depuis l'app
            if ($threadId === null) {
                continue;
            }

            $email = Email::create([
                "message_id" => $messageId,
                "thread_id" => $threadId,
                "direction" => "received",
                "from_email" => $fromEmail ?? "unknown",
                "from_name" => $from?->personal ?? null,
                "to" => $toList,
                "cc" => $ccList ?: null,
                "subject" => $message->getSubject()->first() ?? "(no subject)",
                "body_html" => $message->getHTMLBody() ?? "",
                "body_text" => $message->getTextBody() ?? null,
                "is_read" => false,
                "is_archived" => false,
                "received_at" =>
                    $message->getDate()?->first()?->toDateTime() ?? now(),
            ]);

            foreach ($message->getAttachments() as $attachment) {
                $filename = $attachment->getName() ?? "attachment";
                $path = "email-attachments/" . $email->id . "/" . $filename;
                Storage::disk("local")->put($path, $attachment->getContent());

                EmailAttachment::create([
                    "email_id" => $email->id,
                    "filename" => $filename,
                    "path" => $path,
                    "mime_type" =>
                        $attachment->getMimeType() ??
                        "application/octet-stream",
                    "size" => strlen($attachment->getContent()),
                ]);
            }
        }

        $client->disconnect();
    }

    /**
     * Send an email via Laravel Mail and store it locally.
     */
    public function sendEmail(
        array $to,
        string $subject,
        string $bodyHtml,
        ?array $attachments = [],
        ?string $replyToEmail = null,
        ?string $sentBy = null,
        ?array $cc = null,
        ?string $replyToMessageId = null,
    ): Email {
        $toEmails = collect($to)->pluck("email")->toArray();
        $ccEmails = $cc ? collect($cc)->pluck("email")->toArray() : [];

        // Évite le timeout PHP sur les gros envois en masse
        @set_time_limit(0);

        $isBulk = count($toEmails) > 1;

        // Pour les envois individuels on génère un Message-ID custom et on l'injecte
        // dans les headers SMTP → les réponses sont ensuite détectables via In-Reply-To.
        // Pour les envois en masse (multi-BCC), on s'appuie sur le matching par sujet.
        $customMessageId = null;
        if (!$isBulk) {
            $host = parse_url(config("app.url"), PHP_URL_HOST) ?? "localhost";
            $customMessageId = sprintf(
                "<%s@%s>",
                \Illuminate\Support\Str::uuid(),
                $host,
            );
        }

        // LWS limite à 25 destinataires par message au total (To + BCC).
        // On réserve 1 place pour le champ To (adresse expéditrice),
        // donc on envoie par lots de 24 en BCC.
        $chunkSize = $isBulk ? 18 : 18;
        $chunks = array_chunk($toEmails, $chunkSize);

        foreach ($chunks as $index => $chunk) {
            try {
                Mail::send([], [], function ($message) use (
                    $chunk,
                    $ccEmails,
                    $subject,
                    $bodyHtml,
                    $attachments,
                    $replyToEmail,
                    $isBulk,
                    $customMessageId,
                ) {
                    if ($isBulk) {
                        // En masse : expéditeur en To, destinataires en BCC (invisibles entre eux)
                        $message
                            ->to(
                                config("mail.from.address"),
                                config("mail.from.name"),
                            )
                            ->bcc($chunk)
                            ->subject($subject)
                            ->html($bodyHtml);
                    } else {
                        $message
                            ->to($chunk)
                            ->subject($subject)
                            ->html($bodyHtml);
                    }

                    if ($ccEmails) {
                        $message->cc($ccEmails);
                    }

                    if (
                        $replyToEmail &&
                        filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)
                    ) {
                        $message->replyTo($replyToEmail);
                    }

                    foreach ($attachments ?? [] as $file) {
                        $message->attach($file["path"], [
                            "as" => $file["name"],
                        ]);
                    }

                    // Injecter le Message-ID custom pour les envois individuels
                    if ($customMessageId) {
                        try {
                            $sym = $message->getSymfonyMessage();
                            $sym->getHeaders()->remove("Message-ID");
                            $sym->getHeaders()->addIdHeader(
                                "Message-ID",
                                trim($customMessageId, "<>"),
                            );
                        } catch (\Throwable) {
                            /* ignore si non supporté */
                        }
                    }
                });
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        $threadId = $replyToMessageId
            ? Email::where("id", $replyToMessageId)->value("thread_id")
            : (string) \Illuminate\Support\Str::uuid();
        $parentId = $replyToMessageId ?: null;

        $email = Email::create([
            "message_id" => $customMessageId, // null pour les envois en masse
            "thread_id" => $threadId,
            "direction" => "sent",
            "from_email" => config("mail.from.address"),
            "from_name" => config("mail.from.name"),
            "to" => $to,
            "cc" => $cc,
            "subject" => $subject,
            "body_html" => $bodyHtml,
            "body_text" => strip_tags($bodyHtml),
            "is_read" => true,
            "is_archived" => false,
            "sent_at" => now(),
            "parent_id" => $parentId,
            "sent_by" => $sentBy,
        ]);

        foreach ($attachments ?? [] as $file) {
            $storedPath = Storage::disk("local")->putFile(
                "email-attachments/" . $email->id,
                $file["path"],
            );
            EmailAttachment::create([
                "email_id" => $email->id,
                "filename" => $file["name"] ?? basename($storedPath),
                "path" => $storedPath,
                "mime_type" =>
                    mime_content_type($file["path"]) ??
                    "application/octet-stream",
                "size" => filesize($file["path"]),
            ]);
        }

        return $email;
    }

    public function markAsRead(string $emailId): void
    {
        $email = Email::findOrFail($emailId);
        $email->update(["is_read" => true]);
    }

    public function archive(string $emailId): void
    {
        $email = Email::findOrFail($emailId);
        $email->update(["is_archived" => true]);
    }

    /**
     * Retrieve threads grouped by thread_id with latest message.
     */
    public function getThreads(
        string $direction = "received",
        bool $archived = false,
    ) {
        return Email::select("thread_id")
            ->selectRaw("MAX(received_at) as last_at")
            ->when(
                $direction === "received",
                fn($q) => $q->where("direction", "received"),
            )
            ->when(
                $direction === "sent",
                fn($q) => $q->where("direction", "sent"),
            )
            ->when(!$archived, fn($q) => $q->where("is_archived", false))
            ->groupBy("thread_id")
            ->orderByDesc("last_at")
            ->with(["sender"])
            ->paginate(20);
    }

    /**
     * Retourne le thread_id d'un message reçu s'il est une réponse
     * à un email présent en base (envoyé depuis l'app).
     * Retourne null si aucun parent n'est trouvé (message non sollicité).
     *
     * Stratégies, dans l'ordre :
     *   1. In-Reply-To  → cherche le message_id dans la base
     *   2. References   → cherche chaque référence dans la base
     *   3. Sujet        → normalise (supprime Re:/Fwd:) et cherche un email envoyé avec ce sujet
     */
    protected function resolveThreadId($message): ?string
    {
        // ── 1. In-Reply-To ───────────────────────────────────────────────────────
        $inReplyToAttr = $message->getInReplyTo();
        $inReplyTo = is_array($inReplyToAttr)
            ? $inReplyToAttr[0] ?? null
            : $inReplyToAttr?->first();
        if ($inReplyTo) {
            $parent = Email::where("message_id", $inReplyTo)->first();
            if ($parent) {
                return $parent->thread_id;
            }
        }

        // ── 2. References ────────────────────────────────────────────────────────
        $refsAttr = $message->getReferences();
        $references = is_array($refsAttr)
            ? $refsAttr
            : $refsAttr?->toArray() ?? [];
        foreach ($references as $ref) {
            $parent = Email::where("message_id", $ref)->first();
            if ($parent) {
                return $parent->thread_id;
            }
        }

        // ── 3. Fallback : matching par sujet ─────────────────────────────────────
        // Supprime les préfixes Re: / Fwd: / Fw: (multi-niveaux) pour retrouver
        // le sujet original et le comparer aux emails envoyés depuis l'app.
        $rawSubject = $message->getSubject()?->first() ?? "";
        $normalizedSubject = trim(
            preg_replace("/^(re|fwd?|réf?|tr)\s*:\s*/iu", "", $rawSubject),
        );

        if ($normalizedSubject !== "") {
            $parent = Email::where("direction", "sent")
                ->where(function ($q) use ($normalizedSubject) {
                    // Sujet exact ou sujet de l'envoi se terminant par le sujet normalisé
                    $q->whereRaw("LOWER(subject) = LOWER(?)", [
                        $normalizedSubject,
                    ])->orWhereRaw("LOWER(subject) LIKE LOWER(?)", [
                        "%" . $normalizedSubject . "%",
                    ]);
                })
                ->orderByDesc("sent_at")
                ->first();

            if ($parent) {
                return $parent->thread_id;
            }
        }

        // Aucun parent trouvé : ce message n'est pas une réponse à un email envoyé depuis l'app
        return null;
    }
}
