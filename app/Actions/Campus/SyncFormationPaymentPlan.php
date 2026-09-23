<?php

namespace App\Actions\Campus;

use App\Models\CampusFormation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncFormationPaymentPlan
{
    /**
     * @param  list<array{type: string, value: int|float, due_date: string}>  $installments
     */
    public function execute(CampusFormation $formation, array $installments): void
    {
        $totalCost = (int) $formation->total_cost;
        $planned = 0;

        foreach (array_values($installments) as $index => $row) {
            $type = $row['type'];
            $value = (float) $row['value'];

            if (! in_array($type, ['percentage', 'amount'], true)) {
                throw ValidationException::withMessages([
                    "installments.{$index}.type" => 'Type de tranche invalide.',
                ]);
            }

            if ($value < 1) {
                throw ValidationException::withMessages([
                    "installments.{$index}.value" => 'La valeur doit être au moins 1.',
                ]);
            }

            if ($type === 'percentage' && $value > 100) {
                throw ValidationException::withMessages([
                    "installments.{$index}.value" => 'Le pourcentage ne peut pas dépasser 100 %.',
                ]);
            }

            $amount = $type === 'percentage'
                ? (int) round($value / 100 * $totalCost)
                : (int) $value;

            $planned += $amount;
        }

        if ($totalCost > 0 && $planned > $totalCost) {
            throw ValidationException::withMessages([
                'installments' => "Le total planifié ({$planned} FCFA) dépasse le coût de la formation ({$totalCost} FCFA).",
            ]);
        }

        DB::transaction(function () use ($formation, $installments) {
            $formation->installments()->delete();

            foreach (array_values($installments) as $index => $row) {
                $formation->installments()->create([
                    'position' => $index + 1,
                    'type' => $row['type'],
                    'value' => (int) $row['value'],
                    'due_date' => $row['due_date'],
                ]);
            }
        });
    }
}
