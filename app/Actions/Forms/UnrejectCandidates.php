<?php

namespace App\Actions\Forms;

use App\Enums\FormResponseStatus;
use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Validation\ValidationException;

class UnrejectCandidates
{
    /**
     * Restore rejected candidatures back to submitted.
     *
     * @param  list<string>  $responseIds
     */
    public function execute(Form $form, array $responseIds): int
    {
        $responses = FormResponse::query()
            ->where('form_id', $form->id)
            ->whereIn('id', $responseIds)
            ->where('status', FormResponseStatus::Rejected)
            ->get();

        if ($responses->isEmpty()) {
            throw ValidationException::withMessages([
                'response_ids' => 'Aucune candidature refusée à rétablir.',
            ]);
        }

        foreach ($responses as $response) {
            $response->update([
                'status' => FormResponseStatus::Submitted,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'review_note' => null,
            ]);
        }

        return $responses->count();
    }
}
