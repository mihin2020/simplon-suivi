<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\GenerateContractsFromRows;
use App\Enums\FillFieldsStatus;
use App\Exports\ContractFillModelExport;
use App\Http\Requests\Contract\ImportFillModelRequest;
use App\Models\Contract;
use App\Services\Contracts\ContractFieldExtractor;
use App\Services\Contracts\ContractSubjectDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generate a contract for many people from an Excel model: download the
 * model of this contract, fill one row per person, upload it back.
 */
class ContractFillModelController extends Controller
{
    private const MAX_ROWS = 200;

    public function download(Contract $contract, ContractFieldExtractor $extractor, ContractSubjectDirectory $subjects): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $contract);

        // Normally prepared in the background at save time; done now if not ready yet.
        if ($contract->fill_fields_status !== FillFieldsStatus::Ready) {
            set_time_limit(150);
            $extractor->refresh($contract);
        }

        if (empty($contract->fill_fields)) {
            return back()->withErrors(['file' => 'Aucun passage propre à la personne n\'a été trouvé dans ce contrat : le modèle serait vide.']);
        }

        $filename = 'modele_'.str($contract->title)->slug()->limit(60, '').'.xlsx';

        return Excel::download(new ContractFillModelExport($contract->fill_fields, $subjects->contractEmail($contract)), $filename);
    }

    public function import(ImportFillModelRequest $request, Contract $contract, GenerateContractsFromRows $action): RedirectResponse
    {
        $this->authorize('view', $contract);

        if (empty($contract->fill_fields)) {
            return back()->withErrors(['file' => 'Téléchargez d\'abord le modèle de ce contrat.']);
        }

        $sheet = Excel::toArray(null, $request->file('file'))[0] ?? [];
        $headings = array_map(fn ($cell) => trim((string) $cell), array_shift($sheet) ?? []);
        $expected = array_column($contract->fill_fields, 'label');

        // The columns must be those of this contract's model, in the same order.
        if (array_slice($headings, 0, count($expected)) !== $expected) {
            return back()->withErrors(['file' => 'Ce fichier ne correspond pas au modèle de ce contrat. Téléchargez le modèle et remplissez-le sans modifier les colonnes.']);
        }

        if (count($sheet) > self::MAX_ROWS) {
            return back()->withErrors(['file' => 'Le fichier contient plus de '.self::MAX_ROWS.' lignes.']);
        }

        // Optional last column: the address each generated contract will be sent to.
        $hasEmail = ($headings[count($expected)] ?? null) === ContractFillModelExport::EMAIL_HEADING;

        $created = $action->execute($contract, $sheet, $request->user()->id, $hasEmail);

        if ($created->isEmpty()) {
            return back()->withErrors(['file' => 'Aucune ligne à traiter : ajoutez une ligne par personne (la ligne d\'exemple est ignorée).']);
        }

        Log::info('Contracts generated from Excel model', [
            'source_id' => $contract->id,
            'count' => $created->count(),
            'user_id' => $request->user()->id,
        ]);

        $message = $created->count().' contrat'.($created->count() > 1 ? 's générés' : ' généré').' depuis le fichier. Relisez-les avant de les finaliser.';

        return $contract->contract_project_id
            ? redirect()->route('contract-projects.show', $contract->contract_project_id)->with('success', $message)
            : redirect()->route('contracts.index')->with('success', $message);
    }
}
