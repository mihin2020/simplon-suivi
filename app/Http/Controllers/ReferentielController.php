<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use App\Models\CompetenceBlock;
use App\Models\Referentiel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'id'               => $r->id,
                'name'             => $r->name,
                'description'      => $r->description,
                'formations_count' => $r->formations_count,
                'blocks_count'     => $r->blocks->count(),
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Referentiel::class);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $referentiel = Referentiel::create($data);

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

    public function update(Request $request, Referentiel $referentiel): RedirectResponse
    {
        $this->authorize('update', $referentiel);

        $data = $request->validate([
            'name'                           => ['required', 'string', 'max:255'],
            'description'                    => ['nullable', 'string'],
            'blocks'                         => ['nullable', 'array'],
            'blocks.*.id'                    => ['nullable', 'uuid'],
            'blocks.*.name'                  => ['required', 'string', 'max:255'],
            'blocks.*.order'                 => ['nullable', 'integer'],
            'blocks.*.competences'           => ['nullable', 'array'],
            'blocks.*.competences.*.id'          => ['nullable', 'uuid'],
            'blocks.*.competences.*.name'        => ['required', 'string', 'max:255'],
            'blocks.*.competences.*.order'       => ['nullable', 'integer'],
        ]);

        $referentiel->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $existingBlockIds = [];
        foreach ($data['blocks'] ?? [] as $idx => $blockData) {
            $block = isset($blockData['id']) ? CompetenceBlock::find($blockData['id']) : null;

            if ($block) {
                $block->update(['name' => $blockData['name'], 'order' => $idx]);
            } else {
                $block = $referentiel->blocks()->create([
                    'name'        => $blockData['name'],
                    'order'       => $idx,
                ]);
            }
            $existingBlockIds[] = $block->id;

            $existingCompIds = [];
            foreach ($blockData['competences'] ?? [] as $cidx => $compData) {
                $comp = isset($compData['id']) ? Competence::find($compData['id']) : null;
                if ($comp) {
                    $comp->update(['name' => $compData['name'], 'order' => $cidx]);
                } else {
                    $comp = $block->competences()->create([
                        'name'        => $compData['name'],
                        'order'       => $cidx,
                    ]);
                }
                $existingCompIds[] = $comp->id;
            }
            $block->competences()->whereNotIn('id', $existingCompIds)->delete();
        }

        $referentiel->blocks()->whereNotIn('id', $existingBlockIds)->each(function ($block) {
            $block->competences()->delete();
            $block->delete();
        });

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
