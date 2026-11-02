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

/**
 * @property-read int|null $active_learners_count Set by withCount() on pages that list formations
 * @property-read int|null $attendances_today_count Set by withCount() on the trainer dashboard
 */
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
        'venue',
        'referentiel_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'status' => FormationStatus::class,
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsToMany<Learner, $this>
     */
    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class)
            ->withPivot(['status', 'enrolled_at', 'withdrawn_at', 'completed_at', 'notes']);
    }

    /**
     * @return BelongsToMany<Learner, $this>
     */
    public function activeLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::InProgress->value);
    }

    /**
     * @return BelongsToMany<Learner, $this>
     */
    public function withdrawnLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::Withdrawn->value);
    }

    /**
     * @return BelongsToMany<Learner, $this>
     */
    public function completedLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::Completed->value);
    }

    /**
     * @return BelongsToMany<Learner, $this>
     */
    public function movedLearners(): BelongsToMany
    {
        return $this->learners()->wherePivot('status', LearnerStatus::Moved->value);
    }

    /**
     * @return BelongsToMany<Learner, $this>
     */
    public function inactiveLearners(): BelongsToMany
    {
        return $this->learners()
            ->wherePivotIn('status', array_map(
                fn (LearnerStatus $status) => $status->value,
                LearnerStatus::inactiveCases()
            ));
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

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FormationStatus::Active);
    }
}
