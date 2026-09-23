<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampusFormationInstallment extends Model
{
    use HasUuids;

    protected $fillable = [
        'campus_formation_id',
        'position',
        'type',
        'value',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'value' => 'integer',
        'position' => 'integer',
    ];

    public function campusFormation(): BelongsTo
    {
        return $this->belongsTo(CampusFormation::class);
    }

    public function resolveAmount(int $totalCost): int
    {
        if ($this->type === 'percentage') {
            return max(1, (int) round($this->value / 100 * $totalCost));
        }

        return max(1, $this->value);
    }
}
