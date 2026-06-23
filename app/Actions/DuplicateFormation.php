<?php

namespace App\Actions;

use App\Enums\FormationStatus;
use App\Models\Formation;

class DuplicateFormation
{
    public function execute(Formation $formation): Formation
    {
        return Formation::create([
            'project_id' => $formation->project_id,
            'name' => $this->duplicateName($formation),
            'description' => $formation->description,
            'started_at' => $formation->started_at,
            'ended_at' => $formation->ended_at,
            'status' => FormationStatus::Active,
            'capacity' => $formation->capacity,
            'location' => $formation->location,
            'referentiel_id' => $formation->referentiel_id,
        ]);
    }

    private function duplicateName(Formation $formation): string
    {
        $base = preg_replace('/\s+\(copie(?: \d+)?\)$/u', '', $formation->name) ?: $formation->name;
        $candidate = "{$base} (copie)";
        $counter = 2;

        while (
            Formation::where('project_id', $formation->project_id)
                ->where('name', $candidate)
                ->exists()
        ) {
            $candidate = "{$base} (copie {$counter})";
            $counter++;
        }

        return $candidate;
    }
}
