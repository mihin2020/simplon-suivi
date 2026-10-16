<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\CopyContract;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CopyContractController extends Controller
{
    public function __invoke(Request $request, Contract $contract, CopyContract $action): RedirectResponse
    {
        $this->authorize('view', $contract);
        $this->authorize('create', Contract::class);

        $copy = $action->execute($contract, $request->user()->id);

        Log::info('Contract copied', [
            'source_id' => $contract->id,
            'reference' => $copy->reference,
            'user_id' => $request->user()->id,
        ]);

        $message = "Copie créée ({$copy->reference}). Modifiez-la, l'original reste inchangé.";

        // Straight to the editor when the user may change it.
        return $request->user()->can('update', $copy)
            ? redirect()->route('contracts.edit', $copy)->with('success', $message)
            : redirect()->route('contracts.show', $copy)->with('success', $message);
    }
}
