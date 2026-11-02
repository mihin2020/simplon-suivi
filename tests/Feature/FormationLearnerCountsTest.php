<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use App\Services\FormationLearnerCounts;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormationLearnerCountsTest extends TestCase
{
    private function enroll(Formation $formation, ?Gender $gender, LearnerStatus $status, bool $deleted = false): Learner
    {
        $learner = Learner::factory()->create(['gender' => $gender?->value]);
        $formation->learners()->attach($learner->id, ['status' => $status->value, 'enrolled_at' => now()]);

        if ($deleted) {
            $learner->delete();
        }

        return $learner;
    }

    public function test_counts_match_the_eloquent_relation_counts(): void
    {
        $a = Formation::factory()->create();
        $b = Formation::factory()->create();
        $empty = Formation::factory()->create();

        $this->enroll($a, Gender::Male, LearnerStatus::InProgress);
        $this->enroll($a, Gender::Male, LearnerStatus::InProgress);
        $this->enroll($a, Gender::Female, LearnerStatus::Withdrawn);
        $this->enroll($a, Gender::Female, LearnerStatus::Completed);
        $this->enroll($a, null, LearnerStatus::Moved);
        $this->enroll($a, Gender::Male, LearnerStatus::InProgress, deleted: true);
        $this->enroll($b, Gender::Female, LearnerStatus::InProgress);
        $this->enroll($b, Gender::Male, LearnerStatus::Completed);

        $counts = (new FormationLearnerCounts)->for([$a->id, $b->id, $empty->id]);

        $expected = Formation::query()
            ->whereIn('id', [$a->id, $b->id, $empty->id])
            ->withCount([
                'learners as total',
                'learners as male' => fn ($q) => $q->where('learners.gender', Gender::Male->value),
                'learners as female' => fn ($q) => $q->where('learners.gender', Gender::Female->value),
                'activeLearners as in_progress',
                'withdrawnLearners as withdrawn',
                'completedLearners as completed',
                'movedLearners as moved',
            ])
            ->get()
            ->keyBy('id');

        foreach ([$a, $b, $empty] as $formation) {
            $this->assertSame(
                $expected[$formation->id]->only(['total', 'male', 'female', 'in_progress', 'withdrawn', 'completed', 'moved']),
                $counts[$formation->id],
                $formation->name,
            );
        }

        $this->assertSame(5, $counts[$a->id]['total']);
        $this->assertSame(0, $counts[$empty->id]['total']);
    }

    public function test_an_empty_list_costs_no_query_and_gives_nothing(): void
    {
        $this->assertSame([], (new FormationLearnerCounts)->for([]));
        $this->assertSame([], (new FormationLearnerCounts)->learnerIds([]));
    }

    public function test_learner_ids_skip_deleted_learners(): void
    {
        $formation = Formation::factory()->create();
        $kept = $this->enroll($formation, Gender::Male, LearnerStatus::InProgress);
        $this->enroll($formation, Gender::Female, LearnerStatus::Withdrawn, deleted: true);

        $this->assertSame([$formation->id => [$kept->id]], (new FormationLearnerCounts)->learnerIds([$formation->id]));
    }

    public function test_pages_shared_by_every_request_are_backed_by_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('emails', 'emails_inbox_state_index'));
        $this->assertTrue(Schema::hasIndex('notifications', 'notifications_user_unread_index'));
        $this->assertTrue(Schema::hasIndex('formation_learner', 'formation_learner_formation_status_index'));
        $this->assertTrue(Schema::hasIndex('formation_learner', 'formation_learner_learner_status_index'));
    }
}
