<?php

namespace App\Console\Commands;

use App\Models\Email;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOrphanEmails extends Command
{
    protected $signature = "emails:purge-orphans {--dry-run : Afficher uniquement ce qui serait fait, sans modifier}";
    protected $description = "Re-lie les vraies réponses à leur thread et supprime les emails non sollicités.";

    public function handle(): int
    {
        $isDry = $this->option("dry-run");

        // Thread IDs des emails envoyés depuis l'app
        $sentThreadIds = Email::where("direction", "sent")->pluck("thread_id");

        // Emails reçus dont le thread_id ne correspond à aucun envoi
        $orphans = Email::with("attachments")
            ->where("direction", "received")
            ->whereNotIn("thread_id", $sentThreadIds)
            ->get();

        if ($orphans->isEmpty()) {
            $this->info("✅ Aucun email orphelin. La base est propre.");
            return self::SUCCESS;
        }

        $this->line(
            "🔍 <comment>{$orphans->count()} email(s) orphelin(s) trouvé(s). Analyse en cours…</comment>",
        );
        $this->line("");

        $toRelink = []; // emails à rattacher à un thread existant
        $toDelete = []; // emails à supprimer (aucun parent trouvé)

        foreach ($orphans as $email) {
            $thread = $this->findThreadBySubject($email->subject);
            if ($thread) {
                $toRelink[] = [
                    "email" => $email,
                    "thread_id" => $thread->thread_id,
                    "matched_subject" => $thread->subject,
                ];
            } else {
                $toDelete[] = $email;
            }
        }

        // ── Afficher les emails à re-lier ─────────────────────────────────────
        if (!empty($toRelink)) {
            $this->info(
                "🔗 Emails à rattacher à un thread existant (" .
                    count($toRelink) .
                    ") :",
            );
            $this->table(
                ["De", "Sujet reçu", "→ Sujet envoyé"],
                array_map(
                    fn($r) => [
                        $r["email"]->from_email,
                        str($r["email"]->subject)->limit(45),
                        str($r["matched_subject"])->limit(45),
                    ],
                    $toRelink,
                ),
            );
            $this->line("");
        }

        // ── Afficher les emails à supprimer ───────────────────────────────────
        if (!empty($toDelete)) {
            $this->warn(
                "🗑  Emails à supprimer (non liés à un envoi) (" .
                    count($toDelete) .
                    ") :",
            );
            $this->table(
                ["De", "Sujet", "Reçu le"],
                array_map(
                    fn($e) => [
                        $e->from_email,
                        str($e->subject)->limit(50),
                        $e->received_at?->format("d/m/Y H:i") ?? "-",
                    ],
                    $toDelete,
                ),
            );
            $this->line("");
        }

        if ($isDry) {
            $this->info("Mode --dry-run : aucune modification effectuée.");
            $this->line(
                "Relancez sans --dry-run pour appliquer les changements.",
            );
            return self::SUCCESS;
        }

        if (!$this->confirm("Appliquer ces changements ?", true)) {
            $this->info("Annulé.");
            return self::SUCCESS;
        }

        // ── Re-lier les vraies réponses ───────────────────────────────────────
        $relinked = 0;
        foreach ($toRelink as $r) {
            $r["email"]->update(["thread_id" => $r["thread_id"]]);
            $relinked++;
        }

        // ── Supprimer les orphelins non rattachables ───────────────────────────
        $deleted = 0;
        foreach ($toDelete as $email) {
            foreach ($email->attachments as $attachment) {
                Storage::disk("local")->delete($attachment->path);
            }
            $email->delete();
            $deleted++;
        }

        $this->line("");
        if ($relinked) {
            $this->info("🔗 {$relinked} email(s) rattaché(s) à leur thread.");
        }
        if ($deleted) {
            $this->info("🗑  {$deleted} email(s) orphelin(s) supprimé(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Cherche un email envoyé dont le sujet correspond au sujet reçu
     * après normalisation des préfixes Re:/Fwd:.
     */
    protected function findThreadBySubject(string $subject): ?Email
    {
        $normalized = trim(
            preg_replace("/^(re|fwd?|réf?|tr)\s*:\s*/iu", "", $subject),
        );

        if ($normalized === "") {
            return null;
        }

        return Email::where("direction", "sent")
            ->where(function ($q) use ($normalized) {
                $q->whereRaw("LOWER(subject) = LOWER(?)", [
                    $normalized,
                ])->orWhereRaw("LOWER(subject) LIKE LOWER(?)", [
                    "%" . $normalized . "%",
                ]);
            })
            ->orderByDesc("sent_at")
            ->first();
    }
}
