<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;

/** One row of the page listing the responses of a form. */
class FormResponseRow
{
    /** @return array<string, mixed> */
    public function toArray(FormResponse $response, Form $form): array
    {
        return [
            'id' => $response->id,
            'email' => $response->email,
            'status' => $response->status->value,
            'status_label' => $response->status->label(),
            'status_color' => $response->status->color(),
            'submitted_at' => $response->submitted_at->toIso8601String(),
            'reviewed_at' => $response->reviewed_at?->toIso8601String(),
            'review_note' => $response->review_note,
            'reviewer' => $response->reviewer
                ? trim($response->reviewer->first_name.' '.$response->reviewer->last_name)
                : null,
            'learner' => $response->learner,
            'answers' => $response->answers->map(fn (FormAnswer $answer) => $this->answer($answer, $response, $form)),
        ];
    }

    /** @return array<string, mixed> */
    private function answer(FormAnswer $answer, FormResponse $response, Form $form): array
    {
        $hasFile = filled($answer->file_path);

        return [
            'id' => $answer->id,
            'field_id' => $answer->form_field_id,
            'label' => $answer->field->label ?? null,
            'learner_attribute' => $answer->field->learner_attribute ?? null,
            // A file shows its original name; a multiple choice shows its picks joined.
            'value' => $answer->file_original_name
                ?? (is_array($answer->value_json) ? implode(', ', $answer->value_json) : $answer->value_text),
            'is_file' => $hasFile,
            'is_image' => $hasFile && $this->isImage($answer),
            'download_url' => $hasFile
                ? route('forms.responses.answers.download', ['form' => $form->id, 'response' => $response->id, 'answer' => $answer->id])
                : null,
        ];
    }

    private function isImage(FormAnswer $answer): bool
    {
        $name = strtolower((string) ($answer->file_original_name ?: $answer->file_path));

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)$/', $name);
    }
}
