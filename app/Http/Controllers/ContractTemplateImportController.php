<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contract\ImportContractTemplateRequest;
use App\Services\Contracts\ContractTemplateImporter;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Analyses an uploaded contract model and returns an editable draft.
 * Nothing is persisted: the result pre-fills the template editor for review.
 */
class ContractTemplateImportController extends Controller
{
    public function __invoke(
        ImportContractTemplateRequest $request,
        ContractTemplateImporter $importer,
    ): JsonResponse {
        // Parsing a long PDF can exceed PHP's default 30 s / 128 MB.
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        try {
            $document = $importer->toCleanDocument($request->file('model_file'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($document);
    }
}
