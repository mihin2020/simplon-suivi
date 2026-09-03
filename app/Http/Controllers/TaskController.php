<?php

namespace App\Http\Controllers;

use App\Actions\CreateTask;
use App\Actions\DeleteTask;
use App\Actions\UpdateTask;
use App\Actions\UpdateTaskStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\Planning\StoreTaskRequest;
use App\Http\Requests\Planning\UpdateTaskRequest;
use App\Http\Requests\Planning\UpdateTaskStatusRequest;
use App\Models\Activity;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Activity $activity, CreateTask $action): RedirectResponse
    {
        $action->execute($activity, $request->validated(), $request->user());

        return back()->with('success', 'Tâche créée avec succès.');
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $action): RedirectResponse
    {
        $action->execute($task, $request->validated(), $request->user());

        return back()->with('success', 'Tâche mise à jour.');
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task, UpdateTaskStatus $action): RedirectResponse
    {
        $action->execute($task, TaskStatus::from($request->validated('status')));

        return back()->with('success', 'Statut de la tâche mis à jour.');
    }

    public function destroy(Task $task, DeleteTask $action): RedirectResponse
    {
        $task->loadMissing('activity.phase.project');
        $this->authorize('update', $task->activity->phase->project);

        $action->execute($task);

        return back()->with('success', 'Tâche supprimée.');
    }
}
