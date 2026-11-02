<?php

namespace App\Services\Statistics;

use App\Enums\InsertionStatus;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** The learners of a formation, optionally filtered by gender, enrolment status and insertion status. */
class FormationLearnerFinder
{
    public function __construct(private readonly LatestInsertionStatuses $insertions) {}

    /**
     * @param  array{gender?: string|null, status?: string|null, insertion_status?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function find(Formation $formation, array $filters): array
    {
        $query = $formation->learners();

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['status'])) {
            $query->wherePivot('status', $filters['status']);
        }

        if (! empty($filters['insertion_status'])) {
            $this->filterByInsertion($query, $filters['insertion_status']);
        }

        return $query
            ->with('educationLevel')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['learners.id', 'first_name', 'last_name', 'email', 'gender', 'education_level_id'])
            ->map(fn ($learner) => [
                'id' => $learner->id,
                'first_name' => $learner->first_name,
                'last_name' => $learner->last_name,
                'email' => $learner->email,
                'gender' => $learner->gender?->value,
                'education_level' => $learner->educationLevel?->name,
                'status' => $learner->getRelation('pivot')->status,
            ])
            ->all();
    }

    /**
     * "unemployed" means neither in an internship nor employed, including learners with no record at all.
     *
     * @param  BelongsToMany<Learner, Formation>  $query
     */
    private function filterByInsertion($query, string $status): void
    {
        $latest = collect($this->insertions->byLearner());

        if ($status === 'unemployed') {
            $placed = $latest
                ->filter(fn (string $s) => in_array($s, [InsertionStatus::Internship->value, InsertionStatus::Employed->value], true))
                ->keys()
                ->all();

            $query->whereNotIn('learners.id', $placed);

            return;
        }

        $query->whereIn('learners.id', $latest->filter(fn (string $s) => $s === $status)->keys()->all());
    }
}
