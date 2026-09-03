<?php

namespace App\Http\Requests\Planning;

use App\Enums\Priority;
use App\Http\Requests\Concerns\ValidatesPlanningAssignees;
use App\Models\Phase;
use App\Support\PlanningDateBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    use ValidatesPlanningAssignees;

    public function authorize(): bool
    {
        /** @var Phase $phase */
        $phase = $this->route('phase');
        $phase->loadMissing('project');

        return $this->user()->can('update', $phase->project);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'resources' => ['nullable', 'array'],
            'resources.*' => ['string', 'max:100'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            ...$this->assigneeRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de l\'activité est obligatoire.',
            'started_at.required' => 'La date de début est obligatoire.',
            'ended_at.required' => 'La date de fin est obligatoire.',
            'ended_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Phase $phase */
            $phase = $this->route('phase');

            app(PlanningDateBounds::class)->within(
                $validator,
                $this->input('started_at'),
                $this->input('ended_at'),
                $phase->started_at,
                $phase->ended_at,
                'de la phase',
            );
        });
    }
}
