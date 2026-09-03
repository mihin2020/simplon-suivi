<?php

namespace App\Actions;

use App\Models\Phase;
use Illuminate\Support\Facades\DB;

class DeletePhase
{
    public function __construct(private DeleteActivity $deleteActivity) {}

    public function execute(Phase $phase): void
    {
        DB::transaction(function () use ($phase) {
            $phase->loadMissing('activities');

            foreach ($phase->activities as $activity) {
                $this->deleteActivity->execute($activity);
            }

            $phase->assignees()->detach();
            $phase->delete();
        });
    }
}
