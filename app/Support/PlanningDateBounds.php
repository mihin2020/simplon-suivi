<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Validation\Validator;

class PlanningDateBounds
{
    public function within(
        Validator $validator,
        ?string $startedAt,
        ?string $endedAt,
        ?CarbonInterface $parentStart,
        ?CarbonInterface $parentEnd,
        string $parentLabel,
    ): void {
        if ($startedAt && $parentStart && $startedAt < $parentStart->toDateString()) {
            $validator->errors()->add(
                'started_at',
                "La date de début doit être postérieure ou égale au début {$parentLabel} ({$parentStart->format('d/m/Y')})."
            );
        }

        if ($endedAt && $parentEnd && $endedAt > $parentEnd->toDateString()) {
            $validator->errors()->add(
                'ended_at',
                "La date de fin doit être antérieure ou égale à la fin {$parentLabel} ({$parentEnd->format('d/m/Y')})."
            );
        }
    }
}
