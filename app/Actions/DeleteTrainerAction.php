<?php

namespace App\Actions;

use App\Models\Trainer;
use Illuminate\Support\Facades\DB;

class DeleteTrainerAction
{
    public function execute(Trainer $trainer): void
    {
        DB::transaction(function () use ($trainer) {
            $trainer->user?->delete();
            $trainer->delete();
        });
    }
}
