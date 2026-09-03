<?php

namespace App\Http\Requests\Planning;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Requests\Concerns\ValidatesPlanningAssignees;
use App\Models\Task;
use App\Support\PlanningDateBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    use ValidatesPlanningAssignees;

    public function authorize(): bool
    {
        /** @var Task $task */
        $task = $this->route('task');
        $task->loadMissing('activity.phase.project');

        return $this->user()->can('update', $task->activity->phase->project);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            ...$this->assigneeRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de la tâche est obligatoire.',
            'started_at.required' => 'La date de début est obligatoire.',
            'ended_at.required' => 'La date de fin est obligatoire.',
            'ended_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Task $task */
            $task = $this->route('task');
            $task->loadMissing('activity');
            $activity = $task->activity;

            app(PlanningDateBounds::class)->within(
                $validator,
                $this->input('started_at'),
                $this->input('ended_at'),
                $activity->started_at,
                $activity->ended_at,
                'de l\'activité',
            );
        });
    }
}
