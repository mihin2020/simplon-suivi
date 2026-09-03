<?php

namespace App\Http\Requests\Planning;

use App\Enums\Priority;
use App\Http\Requests\Concerns\ValidatesPlanningAssignees;
use App\Models\Project;
use App\Support\PlanningDateBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhaseRequest extends FormRequest
{
    use ValidatesPlanningAssignees;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            ...$this->assigneeRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la phase est obligatoire.',
            'started_at.required' => 'La date de début est obligatoire.',
            'ended_at.required' => 'La date de fin est obligatoire.',
            'ended_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Project $project */
            $project = $this->route('project');

            app(PlanningDateBounds::class)->within(
                $validator,
                $this->input('started_at'),
                $this->input('ended_at'),
                $project->started_at,
                $project->ended_at,
                'du projet',
            );
        });
    }
}
