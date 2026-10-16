<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contract\StoreLetterheadRequest;
use App\Http\Requests\Contract\UpdateLetterheadRequest;
use App\Models\Letterhead;
use App\Services\Contracts\LetterheadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LetterheadController extends Controller
{
    public function __construct(private readonly LetterheadService $letterheads) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Letterhead::class);

        return Inertia::render('Contracts/Layouts/Index', [
            'letterheads' => Letterhead::query()
                ->orderBy('name')
                ->paginate(24)
                ->through(fn (Letterhead $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'header_url' => $this->letterheads->url($l->header_image_path),
                    'header_height_mm' => $l->header_height_mm,
                    'footer_url' => $this->letterheads->url($l->footer_image_path),
                    'footer_height_mm' => $l->footer_height_mm,
                ]),
            'maxHeights' => [
                'header' => Letterhead::MAX_HEADER_HEIGHT_MM,
                'footer' => Letterhead::MAX_FOOTER_HEIGHT_MM,
            ],
        ]);
    }

    public function store(StoreLetterheadRequest $request): RedirectResponse
    {
        $letterhead = $this->letterheads->save(
            $request->validated('name'),
            $request->file('header_image'),
            $request->file('footer_image'),
        );

        Log::info('Letterhead created', ['letterhead_id' => $letterhead->id, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Habillage enregistré.');
    }

    public function update(UpdateLetterheadRequest $request, Letterhead $letterhead): RedirectResponse
    {
        $this->letterheads->save(
            $request->validated('name'),
            $request->file('header_image'),
            $request->file('footer_image'),
            $letterhead,
        );

        Log::info('Letterhead updated', ['letterhead_id' => $letterhead->id, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Habillage mis à jour.');
    }

    public function destroy(Letterhead $letterhead): RedirectResponse
    {
        $this->authorize('delete', $letterhead);

        $letterhead->delete();

        Log::info('Letterhead deleted', ['letterhead_id' => $letterhead->id, 'user_id' => request()->user()->id]);

        return back()->with('success', 'Habillage supprimé.');
    }
}
