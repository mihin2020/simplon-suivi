<?php

namespace App\Actions;

use App\Models\Referentiel;
use Illuminate\Support\Facades\DB;

class UpdateReferentiel
{
    /**
     * Rebuilds a referentiel from the submitted structure: blocks and competences are created, renamed and
     * reordered following the submitted order, and whatever is not submitted anymore is deleted.
     *
     * A submitted id is only honoured inside this referentiel: the id of a block or a competence of another
     * referentiel creates a new one instead of hijacking the other. Everything happens in one transaction.
     *
     * @param  array{name: string, description?: string|null, blocks?: array<int, array<string, mixed>>|null}  $data
     */
    public function execute(Referentiel $referentiel, array $data): void
    {
        DB::transaction(function () use ($referentiel, $data) {
            $referentiel->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $keptBlockIds = [];

            foreach ($data['blocks'] ?? [] as $position => $blockData) {
                $block = isset($blockData['id']) ? $referentiel->blocks()->whereKey($blockData['id'])->first() : null;

                if ($block) {
                    $block->update(['name' => $blockData['name'], 'order' => $position]);
                } else {
                    $block = $referentiel->blocks()->create(['name' => $blockData['name'], 'order' => $position]);
                }

                $keptBlockIds[] = $block->id;
                $keptCompetenceIds = [];

                foreach ($blockData['competences'] ?? [] as $competencePosition => $competenceData) {
                    $competence = isset($competenceData['id']) ? $block->competences()->whereKey($competenceData['id'])->first() : null;

                    if ($competence) {
                        $competence->update(['name' => $competenceData['name'], 'order' => $competencePosition]);
                    } else {
                        $competence = $block->competences()->create(['name' => $competenceData['name'], 'order' => $competencePosition]);
                    }

                    $keptCompetenceIds[] = $competence->id;
                }

                $block->competences()->whereNotIn('id', $keptCompetenceIds)->delete();
            }

            $referentiel->blocks()->whereNotIn('id', $keptBlockIds)->each(function ($block) {
                $block->competences()->delete();
                $block->delete();
            });
        });
    }
}
