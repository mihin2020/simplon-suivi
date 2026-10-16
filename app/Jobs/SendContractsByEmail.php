<?php

namespace App\Jobs;

use App\Enums\ContractEmailStatus;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Trainer;
use App\Services\Contracts\ContractBulkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends contracts by email in the background, so a large batch neither
 * blocks the page nor the (single-threaded) development server.
 */
class SendContractsByEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  list<string>  $contractIds
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function __construct(
        public array $contractIds,
        public string $mode,
        public array $to,
        public array $cc,
        public string $subject,
        public string $message,
        public string $userId,
    ) {}

    public function handle(ContractBulkService $bulk): void
    {
        $contracts = Contract::query()
            ->whereIn('id', $this->contractIds)
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([Trainer::class => ['user']])])
            ->get();

        $result = $bulk->email($contracts, $this->mode, $this->to, $this->cc, $this->subject, $this->message, $this->userId);

        Log::info('Contracts sent by email', [
            'references' => $contracts->pluck('reference')->all(),
            'mode' => $this->mode,
            'emails_sent' => $result['sent'],
            'user_id' => $this->userId,
        ]);

        $message = $this->mode === 'single'
            ? "Email envoyé avec {$contracts->count()} contrat(s) en pièce jointe."
            : "{$result['sent']} email(s) envoyé(s), chacun avec son contrat.";

        if ($result['skipped'] !== []) {
            $message .= ' Sans adresse email, non envoyés : '.implode(', ', $result['skipped']).'.';
        }

        $this->notify('Contrats envoyés', $message);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Contracts email failed', ['contract_ids' => $this->contractIds, 'error' => $exception?->getMessage()]);

        // Those not sent before the failure are flagged, so they can be sent again.
        Contract::whereIn('id', $this->contractIds)
            ->where('email_status', ContractEmailStatus::Queued)
            ->update(['email_status' => ContractEmailStatus::Failed]);

        $this->notify('Échec de l\'envoi des contrats', 'Les contrats n\'ont pas pu être envoyés : '.($exception?->getMessage() ?? 'erreur inconnue').'.');
    }

    /** In-app notification for the person who asked for the sending. */
    private function notify(string $title, string $message): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'type' => 'contracts_emailed',
            'title' => $title,
            'message' => $message,
            'data' => ['route' => route('contracts.index')],
        ]);
    }
}
