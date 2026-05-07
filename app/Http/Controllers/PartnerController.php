<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::orderBy('name')->paginate(20);

        return Inertia::render('Partners/Index', [
            'partners' => $partners,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Partner::class);

        return Inertia::render('Partners/Create');
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('partners/logos', 'public');
        }
        unset($data['logo']);

        Partner::create($data);

        return redirect()
            ->route('partners.index')
            ->with('success', 'Partenaire créé avec succès.');
    }

    public function edit(Partner $partner): Response
    {
        $this->authorize('update', $partner);

        return Inertia::render('Partners/Edit', [
            'partner' => $partner,
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($partner->logo_path) {
                Storage::disk('public')->delete($partner->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('partners/logos', 'public');
        }
        unset($data['logo']);

        $partner->update($data);

        return redirect()
            ->route('partners.index')
            ->with('success', 'Partenaire mis à jour avec succès.');
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return redirect()
            ->route('partners.index')
            ->with('success', 'Partenaire supprimé.');
    }
}
