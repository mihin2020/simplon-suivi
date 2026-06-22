<?php

namespace App\Http\Controllers;

use App\Enums\ContractTypeContext;
use App\Models\ContractType;
use App\Models\InsertionRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'context' => ['required', 'string', Rule::enum(ContractTypeContext::class)],
        ]);

        ContractType::create([
            'name' => $validated['name'],
            'context' => $validated['context'],
            'order' => ContractType::where('context', $validated['context'])->max('order') + 1,
        ]);

        return back()->with('success', 'Type de contrat ajouté.');
    }

    public function update(Request $request, ContractType $contractType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contract_types', 'name')
                    ->where('context', $contractType->context->value)
                    ->ignore($contractType->id),
            ],
        ]);

        $oldName = $contractType->name;
        $contractType->update($validated);

        $this->syncUsageName($contractType->context, $oldName, $validated['name']);

        return back()->with('success', 'Type de contrat mis à jour.');
    }

    public function destroy(ContractType $contractType): RedirectResponse
    {
        if ($this->isInUse($contractType)) {
            return back()->withErrors([
                'message' => 'Impossible de supprimer ce type car il est utilisé par des enregistrements de stage ou d\'emploi.',
            ]);
        }

        $contractType->delete();

        return back()->with('success', 'Type de contrat supprimé.');
    }

    private function isInUse(ContractType $contractType): bool
    {
        return match ($contractType->context) {
            ContractTypeContext::Internship => InsertionRecord::query()
                ->where('internship_contract_type', $contractType->name)
                ->exists(),
            ContractTypeContext::Employment => InsertionRecord::query()
                ->where('employment_contract_type', $contractType->name)
                ->exists(),
        };
    }

    private function syncUsageName(ContractTypeContext $context, string $oldName, string $newName): void
    {
        if ($oldName === $newName) {
            return;
        }

        match ($context) {
            ContractTypeContext::Internship => InsertionRecord::query()
                ->where('internship_contract_type', $oldName)
                ->update(['internship_contract_type' => $newName]),
            ContractTypeContext::Employment => InsertionRecord::query()
                ->where('employment_contract_type', $oldName)
                ->update(['employment_contract_type' => $newName]),
        };
    }
}
