<?php

namespace App\Services\Statistics;

use App\Enums\Gender;
use App\Enums\InsertionStatus;
use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Models\Project;
use App\Services\FormationLearnerCounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** The figures of the statistics page: platform-wide totals, then one block per project and per formation. */
class StatisticsReport
{
    private const COUNTED_KEYS = [
        'total_learners', 'male_count', 'female_count', 'in_progress_count', 'withdrawn_count',
        'completed_count', 'moved_count', 'internship_count', 'employed_count', 'unemployed_count',
    ];

    public function __construct(
        private readonly FormationLearnerCounts $learnerCounts,
        private readonly LatestInsertionStatuses $insertions,
    ) {}

    /**
     * @return array{globalStats: array<string, mixed>, projectStats: Collection<int, array<string, mixed>>}
     */
    public function build(): array
    {
        // Computed once: both the global figures and every formation need it.
        $latestInsertion = $this->insertions->byLearner();

        return [
            'globalStats' => $this->globalStats($latestInsertion),
            'projectStats' => $this->projectStats($latestInsertion),
        ];
    }

    /**
     * @param  array<string, string>  $latestInsertion
     * @return array<string, mixed>
     */
    private function globalStats(array $latestInsertion): array
    {
        $totalLearners = Learner::count();

        // Distinct learners per enrolment status, whatever the formation.
        $byStatus = DB::table('formation_learner')
            ->select('status', DB::raw('COUNT(DISTINCT learner_id) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $insertionCounts = collect($latestInsertion)->countBy();
        $employed = $insertionCounts->get(InsertionStatus::Employed->value, 0);
        $internship = $insertionCounts->get(InsertionStatus::Internship->value, 0);

        return [
            'learners' => [
                'total' => $totalLearners,
                'male' => Learner::where('gender', Gender::Male->value)->count(),
                'female' => Learner::where('gender', Gender::Female->value)->count(),
                'by_status' => [
                    'in_progress' => (int) $byStatus->get(LearnerStatus::InProgress->value, 0),
                    'withdrawn' => (int) $byStatus->get(LearnerStatus::Withdrawn->value, 0),
                    'completed' => (int) $byStatus->get(LearnerStatus::Completed->value, 0),
                    'moved' => (int) $byStatus->get(LearnerStatus::Moved->value, 0),
                ],
            ],
            'insertion' => [
                'internship' => $internship,
                'employed' => $employed,
                // "Sans emploi": everybody who is neither in an internship nor employed.
                'unemployed' => $totalLearners - $employed - $internship,
                'rate' => $totalLearners > 0 ? round((($employed + $internship) / $totalLearners) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $latestInsertion
     */
    private function projectStats(array $latestInsertion): Collection
    {
        $projects = Project::with('formations')->orderBy('name')->get();

        // One grouped query for every count of every formation (not 7 sub-queries per formation).
        $formationIds = $projects->flatMap(fn (Project $project) => $project->formations->pluck('id'))->all();
        $counts = $this->learnerCounts->for($formationIds);
        $learnerIds = $this->learnerCounts->learnerIds($formationIds);

        return $projects->map(function (Project $project) use ($latestInsertion, $counts, $learnerIds) {
            $formations = $project->formations->map(
                fn ($formation) => $this->formationStats($formation, $counts[$formation->id], $learnerIds[$formation->id] ?? [], $latestInsertion)
            );

            return [
                'id' => $project->id,
                'name' => $project->name,
                'formations' => $formations,
                'totals' => collect(self::COUNTED_KEYS)->mapWithKeys(fn (string $key) => [$key => $formations->sum($key)])->all(),
            ];
        });
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $learnerIds
     * @param  array<string, string>  $latestInsertion
     * @return array<string, mixed>
     */
    private function formationStats($formation, array $counts, array $learnerIds, array $latestInsertion): array
    {
        $statuses = array_count_values(array_filter(array_map(fn (string $id) => $latestInsertion[$id] ?? null, $learnerIds)));
        $internship = $statuses[InsertionStatus::Internship->value] ?? 0;
        $employed = $statuses[InsertionStatus::Employed->value] ?? 0;

        return [
            'id' => $formation->id,
            'name' => $formation->name,
            'status' => $formation->status->value,
            'total_learners' => $counts['total'],
            'male_count' => $counts['male'],
            'female_count' => $counts['female'],
            'in_progress_count' => $counts['in_progress'],
            'withdrawn_count' => $counts['withdrawn'],
            'completed_count' => $counts['completed'],
            'moved_count' => $counts['moved'],
            'internship_count' => $internship,
            'employed_count' => $employed,
            'unemployed_count' => $counts['total'] - $internship - $employed,
        ];
    }
}
