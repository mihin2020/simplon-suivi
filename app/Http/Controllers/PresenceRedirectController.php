<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Formation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PresenceRedirectController extends Controller
{
    public function __invoke(): Response
    {
        $user  = Auth::user();
        $today = Carbon::today();

        if ($user->role === UserRole::Trainer) {
            $trainer = $user->trainer;

            $formations = $trainer
                ? $trainer->formations()
                    ->with('project')
                    ->withCount(['attendances as today_count' => fn ($q) => $q->whereDate('date', $today)])
                    ->withCount('activeLearners')
                    ->orderByRaw("FIELD(status, 'active', 'completed', 'cancelled', 'archived')")
                    ->get()
                    ->map(fn ($f) => [
                        'id'                   => $f->id,
                        'name'                 => $f->name,
                        'project_name'         => $f->project->name,
                        'status'               => $f->status->value,
                        'status_label'         => $f->status->label(),
                        'active_learners_count'=> $f->active_learners_count,
                        'today_count'          => $f->today_count,
                        'started_at'           => $f->started_at?->toDateString(),
                        'ended_at'             => $f->ended_at?->toDateString(),
                    ])
                : collect();
        } else {
            $formations = Formation::with('project')
                ->withCount(['attendances as today_count' => fn ($q) => $q->whereDate('date', $today)])
                ->withCount('activeLearners')
                ->orderByRaw("FIELD(status, 'active', 'completed', 'cancelled', 'archived')")
                ->get()
                ->map(fn ($f) => [
                    'id'                   => $f->id,
                    'name'                 => $f->name,
                    'project_name'         => $f->project->name,
                    'status'               => $f->status->value,
                    'status_label'         => $f->status->label(),
                    'active_learners_count'=> $f->active_learners_count,
                    'today_count'          => $f->today_count,
                    'started_at'           => $f->started_at?->toDateString(),
                    'ended_at'             => $f->ended_at?->toDateString(),
                ]);
        }

        return Inertia::render('Attendances/Select', [
            'formations' => $formations,
            'today'      => $today->toDateString(),
        ]);
    }
}
