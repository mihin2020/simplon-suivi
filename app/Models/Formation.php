<?php

namespace App\Models;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'started_at',
        'ended_at',
        'status',
        'capacity',
        'location',
        'referentiel_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at'   => 'date',
            'status'     => FormationStatus::class,
            'capacity'   => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class)
            ->withPivot(['status', 'enrolled_at', 'withdrawn_at', 'completed_at', 'notes']);
    }

    public function activeLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::InProgress->value);
    }

    public function withdrawnLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::Withdrawn->value);
    }

    public function completedLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::Completed->value);
    }

    public function movedLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::Moved->value);
    }

    public function inactiveLearners(): BelongsToMany
    {
        return $this->learners()
            ->wherePivotIn('status', [
                LearnerStatus::Withdrawn->value,
                LearnerStatus::Completed->value,
                LearnerStatus::Moved->value,
            ]);
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class)
            ->withPivot(['is_lead', 'assigned_at']);
    }

    public function referentiel(): BelongsTo
    {
        return $this->belongsTo(Referentiel::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FormationStatus::Active);
    }
}
