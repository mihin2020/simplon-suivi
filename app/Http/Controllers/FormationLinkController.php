<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\FormationLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FormationLinkController extends Controller
{
    public function store(Request $request, Formation $formation): JsonResponse
    {
        $this->authorize('update', $formation);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url'   => ['required', 'url', 'max:2048'],
        ]);

        $link = FormationLink::create([
            'formation_id' => $formation->id,
            'title'        => $validated['title'] ?? null,
            'url'          => $validated['url'],
            'created_by'   => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'link'    => $link->load('creator:id,first_name,last_name'),
        ]);
    }

    public function update(Request $request, Formation $formation, FormationLink $link): JsonResponse
    {
        $this->authorize('update', $formation);

        if ($link->formation_id !== $formation->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url'   => ['required', 'url', 'max:2048'],
        ]);

        $link->update($validated);

        return response()->json([
            'success' => true,
            'link'    => $link,
        ]);
    }

    public function destroy(Formation $formation, FormationLink $link): JsonResponse
    {
        $this->authorize('update', $formation);

        if ($link->formation_id !== $formation->id) {
            abort(403);
        }

        $link->delete();

        return response()->json(['success' => true]);
    }
}
