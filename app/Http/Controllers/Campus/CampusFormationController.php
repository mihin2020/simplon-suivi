<?php

namespace App\Http\Controllers\Campus;

use App\Actions\Campus\SyncFormationPaymentPlan;
use App\Enums\CampusFormationMode;
use App\Http\Controllers\Controller;
use App\Models\CampusFormation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampusFormationController extends Controller
{
    public function index(Request $request): Response
    {
        $formations = CampusFormation::withCount('cohorts')
            ->withCount('installments')
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%")
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Campus/Formations/Index', [
            'formations' => $formations,
            'modes' => collect(CampusFormationMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Campus/Formations/Create', [
            'modes' => collect(CampusFormationMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    public function store(Request $request, SyncFormationPaymentPlan $syncPlan): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $installments = $data['installments'] ?? [];
        unset($data['installments']);

        $formation = CampusFormation::create($data);

        if ($installments !== []) {
            $syncPlan->execute($formation, $installments);
        }

        return redirect()->route('campus.formations.show', $formation)
            ->with('success', 'Formation créée avec succès.');
    }

    public function show(CampusFormation $campusFormation): Response
    {
        $campusFormation->load([
            'cohorts' => fn ($q) => $q->withCount('learners')->orderBy('started_at', 'desc'),
            'installments',
        ]);

        return Inertia::render('Campus/Formations/Show', [
            'formation' => $campusFormation,
        ]);
    }

    public function edit(CampusFormation $campusFormation): Response
    {
        $campusFormation->load('installments');

        return Inertia::render('Campus/Formations/Edit', [
            'formation' => $campusFormation,
            'modes' => collect(CampusFormationMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    public function update(
        Request $request,
        CampusFormation $campusFormation,
        SyncFormationPaymentPlan $syncPlan,
    ): RedirectResponse {
        $data = $request->validate($this->rules());

        $installments = $data['installments'] ?? [];
        unset($data['installments']);

        $campusFormation->update($data);
        $syncPlan->execute($campusFormation->fresh(), $installments);

        return redirect()->route('campus.formations.show', $campusFormation)
            ->with('success', 'Formation mise à jour.');
    }

    public function destroy(CampusFormation $campusFormation): RedirectResponse
    {
        if ($campusFormation->cohorts()->exists()) {
            return redirect()->back()
                ->with('error', "Impossible de supprimer « {$campusFormation->name} » : cette formation possède des cohortes. Supprimez d'abord les cohortes associées.");
        }

        $campusFormation->delete();

        return redirect()->route('campus.formations.index')
            ->with('success', 'Formation supprimée.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'mode' => ['required', 'in:presentiel,en_ligne'],
            'total_cost' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'installments' => ['nullable', 'array', 'max:24'],
            'installments.*.type' => ['required', 'in:percentage,amount'],
            'installments.*.value' => ['required', 'numeric', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }
}
