<?php

namespace App\Http\Controllers;

use App\Actions\UpdateReferentiel;
use App\Http\Requests\Referentiel\StoreReferentielRequest;
use App\Http\Requests\Referentiel\UpdateReferentielRequest;
use App\Models\Referentiel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReferentielController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Referentiel::class);

        $referentiels = Referentiel::withCount('formations')
            ->with('blocks')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'formations_count' => $r->formations_count,
                'blocks_count' => $r->blocks->count(),
            ]);

        return Inertia::render('Referentiels/Index', [
            'referentiels' => $referentiels,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Referentiel::class);

        return Inertia::render('Referentiels/Create');
    }

    public function store(StoreReferentielRequest $request): RedirectResponse
    {
        $referentiel = Referentiel::create($request->validated());

        return redirect()
            ->route('referentiels.show', $referentiel)
            ->with('success', 'Référentiel créé avec succès.');
    }

    public function show(Referentiel $referentiel): Response
    {
        $this->authorize('view', $referentiel);

        $referentiel->load('blocks.competences', 'formations.project');

        return Inertia::render('Referentiels/Show', [
            'referentiel' => $referentiel,
        ]);
    }

    public function update(UpdateReferentielRequest $request, Referentiel $referentiel, UpdateReferentiel $action): RedirectResponse
    {
        $action->execute($referentiel, $request->validated());

        return redirect()
            ->route('referentiels.show', $referentiel)
            ->with('success', 'Référentiel mis à jour.');
    }

    public function destroy(Referentiel $referentiel): RedirectResponse
    {
        $this->authorize('delete', $referentiel);

        $referentiel->blocks()->each(function ($block) {
            $block->competences()->delete();
            $block->delete();
        });
        $referentiel->delete();

        return redirect()
            ->route('referentiels.index')
            ->with('success', 'Référentiel supprimé.');
    }
}
