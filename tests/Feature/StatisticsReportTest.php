<?php

namespace Tests\Feature;

use App\Enums\FormationStatus;
use App\Enums\Gender;
use App\Enums\LearnerStatus;
use App\Exports\FormationStatisticsSheet;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pins every figure of the statistics page on a small data set computed by hand.
 *
 * Learners (formation: status, gender, latest insertion status):
 *  Ann  F  F1 in_progress                  employed (after searching)
 *  Bob  M  F1 in_progress                  internship
 *  Cléo F  F1 withdrawn                    unemployed (after employed)
 *  Dan  M  F1 completed + F2 in_progress   employed (after internship)
 *  Eva  F  F2 completed                    -
 *  Fred M  F3 moved                        internship
 *  Gus  -  F3 in_progress                  -
 *  Hugo M  F3 in_progress, then deleted    -
 *  Iris F  no formation                    employed
 */
class StatisticsReportTest extends TestCase
{
    /** @var array<string, Learner> */
    private array $learners = [];

    /** @var array<string, Formation> */
    private array $formations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $alpha = Project::factory()->create(['name' => 'Alpha']);
        $beta = Project::factory()->create(['name' => 'Beta']);

        foreach (['F1' => $alpha, 'F2' => $alpha, 'F3' => $beta, 'F4' => $beta] as $name => $project) {
            $this->formations[$name] = Formation::factory()->create(['name' => $name, 'project_id' => $project->id, 'status' => FormationStatus::Active]);
        }

        $people = [
            'Ann' => [Gender::Female, ['F1' => LearnerStatus::InProgress]],
            'Bob' => [Gender::Male, ['F1' => LearnerStatus::InProgress]],
            'Cléo' => [Gender::Female, ['F1' => LearnerStatus::Withdrawn]],
            'Dan' => [Gender::Male, ['F1' => LearnerStatus::Completed, 'F2' => LearnerStatus::InProgress]],
            'Eva' => [Gender::Female, ['F2' => LearnerStatus::Completed]],
            'Fred' => [Gender::Male, ['F3' => LearnerStatus::Moved]],
            'Gus' => [null, ['F3' => LearnerStatus::InProgress]],
            'Hugo' => [Gender::Male, ['F3' => LearnerStatus::InProgress]],
            'Iris' => [Gender::Female, []],
        ];

        foreach ($people as $name => [$gender, $enrolments]) {
            $this->learners[$name] = Learner::factory()->create(['first_name' => $name, 'last_name' => $name, 'gender' => $gender?->value]);

            foreach ($enrolments as $formation => $status) {
                $this->formations[$formation]->learners()->attach($this->learners[$name]->id, ['status' => $status->value, 'enrolled_at' => now()]);
            }
        }

        $history = [
            'Ann' => [['searching', '2026-01-01'], ['employed', '2026-03-01']],
            'Bob' => [['internship', '2026-02-01']],
            'Cléo' => [['employed', '2026-02-01'], ['unemployed', '2026-04-01']],
            'Dan' => [['internship', '2026-02-01'], ['employed', '2026-05-01']],
            'Fred' => [['internship', '2026-02-01']],
            'Iris' => [['employed', '2026-02-01']],
        ];

        foreach ($history as $name => $records) {
            foreach ($records as [$status, $at]) {
                InsertionRecord::create(['learner_id' => $this->learners[$name]->id, 'status' => $status, 'status_changed_at' => $at.' 10:00:00']);
            }
        }

        $this->learners['Hugo']->delete();
        $this->actingAsSuperAdmin();
    }

    private function props(): array
    {
        return $this->get(route('statistics.index'))->viewData('page')['props'];
    }

    public function test_the_global_figures(): void
    {
        $global = $this->props()['globalStats'];

        // Deleted learners (Hugo) are not counted in the totals, but their enrolment still is in the pivot counts.
        $this->assertSame(8, $global['learners']['total']);
        $this->assertSame(3, $global['learners']['male']);
        $this->assertSame(4, $global['learners']['female']);
        $this->assertSame(['in_progress' => 5, 'withdrawn' => 1, 'completed' => 2, 'moved' => 1], $global['learners']['by_status']);
        $this->assertSame(['internship' => 2, 'employed' => 3, 'unemployed' => 3, 'rate' => 62.5], $global['insertion']);
    }

    public function test_the_figures_of_each_project_and_formation(): void
    {
        $projects = $this->props()['projectStats'];

        $this->assertSame(['Alpha', 'Beta'], array_column($projects, 'name'));

        $row = fn (array $formation) => array_diff_key($formation, ['id' => 1]);

        $alpha = $projects[0];
        $this->assertSame(['F1', 'F2'], array_column($alpha['formations'], 'name'));
        $this->assertSame(
            ['name' => 'F1', 'status' => 'active', 'total_learners' => 4, 'male_count' => 2, 'female_count' => 2, 'in_progress_count' => 2, 'withdrawn_count' => 1, 'completed_count' => 1, 'moved_count' => 0, 'internship_count' => 1, 'employed_count' => 2, 'unemployed_count' => 1],
            $row($alpha['formations'][0]),
        );
        $this->assertSame(
            ['name' => 'F2', 'status' => 'active', 'total_learners' => 2, 'male_count' => 1, 'female_count' => 1, 'in_progress_count' => 1, 'withdrawn_count' => 0, 'completed_count' => 1, 'moved_count' => 0, 'internship_count' => 0, 'employed_count' => 1, 'unemployed_count' => 1],
            $row($alpha['formations'][1]),
        );
        $this->assertSame(
            ['total_learners' => 6, 'male_count' => 3, 'female_count' => 3, 'in_progress_count' => 3, 'withdrawn_count' => 1, 'completed_count' => 2, 'moved_count' => 0, 'internship_count' => 1, 'employed_count' => 3, 'unemployed_count' => 2],
            $alpha['totals'],
        );

        $beta = $projects[1];
        $this->assertSame(
            ['name' => 'F3', 'status' => 'active', 'total_learners' => 2, 'male_count' => 1, 'female_count' => 0, 'in_progress_count' => 1, 'withdrawn_count' => 0, 'completed_count' => 0, 'moved_count' => 1, 'internship_count' => 1, 'employed_count' => 0, 'unemployed_count' => 1],
            $row($beta['formations'][0]),
        );
        $this->assertSame(
            ['name' => 'F4', 'status' => 'active', 'total_learners' => 0, 'male_count' => 0, 'female_count' => 0, 'in_progress_count' => 0, 'withdrawn_count' => 0, 'completed_count' => 0, 'moved_count' => 0, 'internship_count' => 0, 'employed_count' => 0, 'unemployed_count' => 0],
            $row($beta['formations'][1]),
        );
        $this->assertSame(
            ['total_learners' => 2, 'male_count' => 1, 'female_count' => 0, 'in_progress_count' => 1, 'withdrawn_count' => 0, 'completed_count' => 0, 'moved_count' => 1, 'internship_count' => 1, 'employed_count' => 0, 'unemployed_count' => 1],
            $beta['totals'],
        );
    }

    public function test_an_empty_platform_gives_zeros(): void
    {
        DB::table('formation_learner')->delete();
        DB::table('insertion_records')->delete();
        Learner::withTrashed()->forceDelete();

        $global = $this->props()['globalStats'];

        $this->assertSame(0, $global['learners']['total']);
        $this->assertSame(0, $global['insertion']['rate']);
        $this->assertSame(0, $global['insertion']['unemployed']);
    }

    public function test_the_latest_insertion_status_wins_and_the_most_recent_date_decides(): void
    {
        // Cléo was employed then became unemployed: she must be counted unemployed, not employed.
        InsertionRecord::create(['learner_id' => $this->learners['Ann']->id, 'status' => 'internship', 'status_changed_at' => '2026-02-01 10:00:00']);

        $this->assertSame(3, $this->props()['globalStats']['insertion']['employed']);
    }

    // ── Learners of a formation ────────────────────────────────────────────

    private function learnersOf(string $formation, array $filters = []): array
    {
        return $this->getJson(route('api.statistics.learners', [$this->formations[$formation], ...$filters]))->assertOk()->json();
    }

    public function test_the_learners_of_a_formation_are_listed_by_name(): void
    {
        $response = $this->learnersOf('F1');

        $this->assertSame(['id' => $this->formations['F1']->id, 'name' => 'F1'], $response['formation']);
        $this->assertSame(4, $response['count']);
        $this->assertSame(['Ann', 'Bob', 'Cléo', 'Dan'], array_column($response['learners'], 'last_name'));
        $this->assertSame(['in_progress', 'in_progress', 'withdrawn', 'completed'], array_column($response['learners'], 'status'));
        $this->assertSame(['female', 'male', 'female', 'male'], array_column($response['learners'], 'gender'));
    }

    public function test_the_learners_can_be_filtered_by_gender_status_and_insertion(): void
    {
        $names = fn (array $filters) => array_column($this->learnersOf('F1', $filters)['learners'], 'last_name');

        $this->assertSame(['Ann', 'Cléo'], $names(['gender' => 'female']));
        $this->assertSame(['Ann', 'Bob'], $names(['status' => 'in_progress']));
        $this->assertSame(['Ann', 'Dan'], $names(['insertion_status' => 'employed']));
        $this->assertSame(['Bob'], $names(['insertion_status' => 'internship']));
        $this->assertSame(['Ann'], $names(['gender' => 'female', 'status' => 'in_progress', 'insertion_status' => 'employed']));
    }

    public function test_unemployed_means_neither_in_an_internship_nor_employed(): void
    {
        $names = fn (string $formation) => array_column($this->learnersOf($formation, ['insertion_status' => 'unemployed'])['learners'], 'last_name');

        // Cléo's latest record says unemployed; Gus (F3) has no record at all: both count.
        $this->assertSame(['Cléo'], $names('F1'));
        $this->assertSame(['Gus'], $names('F3'));
    }

    public function test_an_unknown_formation_is_a_404_and_access_needs_the_statistics_permission(): void
    {
        $this->getJson('/api/statistics/formation/'.fake()->uuid().'/learners')->assertNotFound();

        $this->actingAsTrainer();
        $this->get(route('statistics.index'))->assertForbidden();
        $this->getJson(route('api.statistics.learners', $this->formations['F1']))->assertForbidden();
    }

    public function test_the_page_renders_through_inertia(): void
    {
        $this->get(route('statistics.index'))->assertInertia(fn (Assert $page) => $page->component('Statistics/Index')->has('globalStats')->has('projectStats', 2)->etc());
    }

    public function test_unknown_filter_values_are_refused(): void
    {
        $url = route('api.statistics.learners', $this->formations['F1']);

        $this->getJson($url.'?gender=robot')->assertUnprocessable()->assertJsonValidationErrors('gender');
        $this->getJson($url.'?status=zombie')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson($url.'?insertion_status=retired')->assertUnprocessable()->assertJsonValidationErrors('insertion_status');
        $this->getJson($url.'?gender=&status=&insertion_status=')->assertOk();
    }

    public function test_the_export_uses_the_same_latest_status_as_the_page(): void
    {
        $ids = [$this->learners['Ann']->id, $this->learners['Cléo']->id, $this->learners['Eva']->id];

        $this->assertSame(
            [$this->learners['Ann']->id => 'employed', $this->learners['Cléo']->id => 'unemployed'],
            FormationStatisticsSheet::latestInsertionRecordsFor($ids),
        );
        $this->assertSame([], FormationStatisticsSheet::latestInsertionRecordsFor([]));
    }
}
