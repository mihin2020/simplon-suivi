<?php

namespace App\Http\Requests\Planning;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Task $task */
        $task = $this->route('task');
        $task->loadMissing(['activity.phase.project', 'assignees']);

        $user = $this->user();

        return $user->can('update', $task->activity->phase->project)
            || $task->isAssignedTo($user);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }
}
