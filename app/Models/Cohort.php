<?php

namespace App\Models;

use App\Enums\CohortStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cohort extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'campus_formation_id',
        'name',
        'started_at',
        'ended_at',
        'capacity',
        'status',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at'   => 'date',
        'status'     => CohortStatus::class,
    ];

    public function campusFormation(): BelongsTo
    {
        return $this->belongsTo(CampusFormation::class);
    }

    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class)
            ->withPivot(['enrolled_at', 'status']);
    }

    public function activeLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', 'actif');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ── Finance computed helpers ──────────────────────────────────────────
    public function getTotalCollectedAttribute(): int
    {
        return $this->payments()->where('status', 'paye')->sum('amount');
    }

    public function getTotalExpectedAttribute(): int
    {
        $this->loadMissing(['campusFormation' => fn ($q) => $q->withTrashed()]);
        return $this->activeLearners()->count() * ($this->campusFormation?->total_cost ?? 0);
    }

    public function getTotalRemainingAttribute(): int
    {
        return max(0, $this->total_expected - $this->total_collected);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CohortStatus::EnCours);
    }
}
