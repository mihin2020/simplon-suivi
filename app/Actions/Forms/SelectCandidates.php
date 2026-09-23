<?php

namespace App\Actions\Forms;

use App\Enums\FormResponseStatus;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SelectCandidates
{
    /**
     * Mark candidates as selected (not enrolled yet).
     *
     * @param  list<string>  $responseIds
     */
    public function execute(Form $form, array $responseIds, User $reviewer, ?string $note = null): int
    {
        $responses = FormResponse::query()
            ->where('form_id', $form->id)
            ->whereIn('id', $responseIds)
            ->whereNotIn('status', [
                FormResponseStatus::Enrolled->value,
                FormResponseStatus::Rejected->value,
                FormResponseStatus::Selected->value,
            ])
            ->get();

        if ($responses->isEmpty()) {
            throw ValidationException::withMessages([
                'response_ids' => 'Aucune candidature éligible à la sélection.',
            ]);
        }

        foreach ($responses as $response) {
            $response->update([
                'status' => FormResponseStatus::Selected,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'review_note' => $note,
            ]);
        }

        return $responses->count();
    }
}
