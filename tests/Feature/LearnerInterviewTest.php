<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerInterview;
use Tests\TestCase;

class LearnerInterviewTest extends TestCase
{
    public function test_super_admin_can_add_an_interview_without_replacing_history(): void
    {
        $user = $this->createSuperAdmin();
        $learner = Learner::factory()->create();

        LearnerInterview::factory()->create([
            'learner_id' => $learner->id,
            'conducted_by' => $user->id,
            'created_by' => $user->id,
            'subject' => 'Entretien d\'accueil',
            'conducted_at' => '2026-07-15',
        ]);

        $this->actingAs($user)
            ->post("/learners/{$learner->id}/interviews", [
                'conducted_at' => '2026-09-20',
                'subject' => 'Point de suivi pédagogique',
                'notes' => 'Difficultés sur le module web.',
                'recommendation' => 'Accompagnement supplémentaire.',
                'next_follow_up_at' => '2026-09-27',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('learner_interviews', 2);
        $this->assertDatabaseHas('learner_interviews', [
            'learner_id' => $learner->id,
            'subject' => 'Entretien d\'accueil',
        ]);
        $this->assertDatabaseHas('learner_interviews', [
            'learner_id' => $learner->id,
            'subject' => 'Point de suivi pédagogique',
            'conducted_by' => $user->id,
        ]);
    }

    public function test_interview_can_store_custom_fields(): void
    {
        $user = $this->createSuperAdmin();
        $learner = Learner::factory()->create();

        $response = $this->actingAs($user)
            ->post("/learners/{$learner->id}/interviews", [
                'conducted_at' => '2026-09-20',
                'subject' => 'Suivi avec points libres',
                'custom_fields' => [
                    ['label' => 'Motivation', 'value' => 'Très engagé'],
                    ['label' => 'Blocages', 'value' => 'Transport'],
                    ['label' => '  ', 'value' => 'ignoré'],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $interview = LearnerInterview::query()
            ->where('learner_id', $learner->id)
            ->where('subject', 'Suivi avec points libres')
            ->first();

        $this->assertNotNull($interview);
        $this->assertSame([
            ['label' => 'Motivation', 'value' => 'Très engagé'],
            ['label' => 'Blocages', 'value' => 'Transport'],
        ], $interview->custom_fields);
    }

    public function test_trainer_cannot_create_an_interview(): void
    {
        $learner = Learner::factory()->create();

        $this->actingAsTrainer()
            ->post("/learners/{$learner->id}/interviews", [
                'conducted_at' => '2026-09-20',
                'subject' => 'Interdit',
            ])
            ->assertForbidden();
    }

    public function test_interview_can_be_updated_and_soft_deleted(): void
    {
        $user = $this->createSuperAdmin();
        $learner = Learner::factory()->create();
        $interview = LearnerInterview::factory()->create([
            'learner_id' => $learner->id,
            'conducted_by' => $user->id,
            'created_by' => $user->id,
            'subject' => 'Initial',
        ]);

        $this->actingAs($user)
            ->put("/learners/{$learner->id}/interviews/{$interview->id}", [
                'conducted_at' => '2026-09-21',
                'subject' => 'Suivi mis à jour',
                'notes' => 'RAS',
            ])
            ->assertRedirect();

        $this->assertSame('Suivi mis à jour', $interview->fresh()->subject);

        $this->actingAs($user)
            ->delete("/learners/{$learner->id}/interviews/{$interview->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('learner_interviews', ['id' => $interview->id]);
    }
}
