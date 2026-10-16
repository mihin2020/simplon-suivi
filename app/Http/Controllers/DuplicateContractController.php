<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\DuplicateContract;
use App\Http\Requests\Contract\DuplicateContractRequest;
use App\Models\Contract;
use App\Services\Contracts\ContractSubjectDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class DuplicateContractController extends Controller
{
    public function __invoke(
        DuplicateContractRequest $request,
        Contract $contract,
        DuplicateContract $action,
        ContractSubjectDirectory $directory,
    ): RedirectResponse {
        $this->authorize('view', $contract);

        // Only recipients valid for the contract's type are kept.
        $subjects = $directory->findMany($contract->target, $request->validated('subject_ids'));

        if ($subjects->isEmpty()) {
            return back()->withErrors(['subject_ids' => 'Aucune personne valide pour ce type de contrat.']);
        }

        $copies = $action->execute($contract->load('subject'), $subjects, $request->user()->id);

        Log::info('Contract duplicated', [
            'source_id' => $contract->id,
            'references' => $copies->pluck('reference')->all(),
            'user_id' => $request->user()->id,
        ]);

        $message = $copies->count() === 1
            ? 'Contrat généré pour 1 personne. Relisez-le avant de le finaliser.'
            : $copies->count().' contrats générés. Relisez-les avant de les finaliser.';

        // A single copy outside a project opens directly; otherwise back to the list it belongs to.
        if ($contract->contract_project_id) {
            return redirect()->route('contract-projects.show', $contract->contract_project_id)->with('success', $message);
        }

        return $copies->count() === 1
            ? redirect()->route('contracts.show', $copies->first())->with('success', $message)
            : redirect()->route('contracts.index')->with('success', $message);
    }
}
