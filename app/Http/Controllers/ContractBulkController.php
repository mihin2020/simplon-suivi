<?php

namespace App\Http\Controllers;

use App\Enums\ContractEmailStatus;
use App\Http\Requests\Contract\BulkContractRequest;
use App\Http\Requests\Contract\SendContractsEmailRequest;
use App\Jobs\SendContractsByEmail;
use App\Models\Contract;
use App\Models\Trainer;
use App\Services\Contracts\ContractBulkService;
use App\Services\Contracts\ContractSubjectDirectory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Actions on several selected contracts at once.
 */
class ContractBulkController extends Controller
{
    public function __construct(
        private readonly ContractBulkService $bulk,
        private readonly ContractSubjectDirectory $subjects,
    ) {}

    public function download(BulkContractRequest $request): BinaryFileResponse
    {
        $contracts = $this->selected($request, 'view');
        set_time_limit(300);

        $path = $this->bulk->zip($contracts);

        return response()
            ->download($path, 'contrats_'.now()->format('Y-m-d_His').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function destroy(BulkContractRequest $request): RedirectResponse
    {
        $contracts = $this->selected($request, 'delete');

        $contracts->each->delete();

        Log::info('Contracts deleted in bulk', [
            'references' => $contracts->pluck('reference')->all(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', $contracts->count().' contrat'.($contracts->count() > 1 ? 's supprimés.' : ' supprimé.'));
    }

    public function email(SendContractsEmailRequest $request): RedirectResponse
    {
        $contracts = $this->selected($request, 'view');
        $data = $request->validated();

        // An address typed for a contract is remembered: next time it is known.
        foreach ($contracts as $contract) {
            $typed = trim((string) ($data['recipients'][$contract->id] ?? ''));
            if ($typed !== '' && $typed !== $this->subjects->contractEmail($contract)) {
                $contract->update(['recipient_email' => $typed]);
            }
        }

        // Shown as "being sent" in the lists until the worker is done.
        $contracts
            ->filter(fn (Contract $contract) => $data['mode'] === 'single' || $this->subjects->contractEmail($contract) !== null)
            ->each(fn (Contract $contract) => $contract->update(['email_status' => ContractEmailStatus::Queued]));

        // Sending can take minutes for a large batch: done by the queue worker,
        // with an in-app notification when finished.
        SendContractsByEmail::dispatch(
            $contracts->modelKeys(),
            $data['mode'],
            $data['to'] ?? [],
            $data['cc'] ?? [],
            $data['subject'],
            $data['message'],
            $request->user()->id,
        );

        return back()->with('success', 'Envoi lancé en arrière-plan. Vous recevrez une notification une fois les emails partis.');
    }

    /**
     * The selected contracts, each one checked against the policy.
     *
     * @return Collection<int, Contract>
     */
    private function selected(BulkContractRequest $request, string $ability): Collection
    {
        $contracts = Contract::query()
            ->whereIn('id', $request->validated('ids'))
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([Trainer::class => ['user']])])
            ->get();

        $contracts->each(fn (Contract $contract) => $this->authorize($ability, $contract));

        return $contracts;
    }
}
