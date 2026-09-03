<?php

namespace App\Http\Requests\Interview;

use App\Models\Learner;
use App\Models\LearnerInterview;
use App\Support\InterviewCustomFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLearnerInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Learner $learner */
        $learner = $this->route('learner');
        /** @var LearnerInterview $interview */
        $interview = $this->route('interview');

        return $interview->learner_id === $learner->id
            && $this->user()->can('update', $learner);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('custom_fields')) {
            $this->merge([
                'custom_fields' => InterviewCustomFields::normalize($this->input('custom_fields')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'conducted_at' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date', 'after_or_equal:conducted_at'],
            'is_important' => ['nullable', 'boolean'],
            'conducted_by' => ['nullable', 'uuid', 'exists:users,id'],
            'custom_fields' => ['nullable', 'array', 'max:20'],
            'custom_fields.*.label' => ['required', 'string', 'max:120'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'conducted_at.required' => 'La date de l\'entretien est obligatoire.',
            'subject.required' => 'L\'objet de l\'entretien est obligatoire.',
            'next_follow_up_at.after_or_equal' => 'La date du prochain suivi doit être postérieure ou égale à la date de l\'entretien.',
            'custom_fields.max' => 'Vous pouvez ajouter au maximum 20 champs personnalisés.',
            'custom_fields.*.label.required' => 'Le libellé du champ personnalisé est obligatoire.',
        ];
    }
}
