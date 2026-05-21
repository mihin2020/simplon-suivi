<?php

namespace App\Http\Controllers\Campus;

use App\Enums\CampusFormationMode;
use App\Http\Controllers\Controller;
use App\Models\CampusFormation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampusFormationController extends Controller
{
    public function index(): Response
    {
        $formations = CampusFormation::withCount('cohorts')
            ->latest()
            ->paginate(15);

        return Inertia::render('Campus/Formations/Index', [
            'formations' => $formations,
            'modes'      => collect(CampusFormationMode::cases())->map(fn($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Campus/Formations/Create', [
            'modes' => collect(CampusFormationMode::cases())->map(fn($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'mode'            => ['required', 'in:presentiel,en_ligne'],
            'total_cost'      => ['required', 'integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        CampusFormation::create($data);

        return redirect()->route('campus.formations.index')
            ->with('success', 'Formation créée avec succès.');
    }

    public function show(CampusFormation $campusFormation): Response
    {
        $campusFormation->load([
            'cohorts' => fn($q) => $q->withCount('learners')->orderBy('started_at', 'desc'),
        ]);

        return Inertia::render('Campus/Formations/Show', [
            'formation' => $campusFormation,
        ]);
    }

    public function edit(CampusFormation $campusFormation): Response
    {
        return Inertia::render('Campus/Formations/Edit', [
            'formation' => $campusFormation,
            'modes'     => collect(CampusFormationMode::cases())->map(fn($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    public function update(Request $request, CampusFormation $campusFormation): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'mode'            => ['required', 'in:presentiel,en_ligne'],
            'total_cost'      => ['required', 'integer', 'min:0'],
            'is_active'       => ['boolean'],
        ]);

        $campusFormation->update($data);

        return redirect()->route('campus.formations.show', $campusFormation)
            ->with('success', 'Formation mise à jour.');
    }

    public function destroy(CampusFormation $campusFormation): RedirectResponse
    {
        $campusFormation->delete();

        return redirect()->route('campus.formations.index')
            ->with('success', 'Formation supprimée.');
    }
}
