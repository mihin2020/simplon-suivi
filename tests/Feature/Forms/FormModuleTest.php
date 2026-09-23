<?php

namespace Tests\Feature\Forms;

use App\Actions\Forms\CreateForm;
use App\Enums\FormFieldType;
use App\Enums\FormResponseStatus;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\Formation;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\Learner;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormModuleTest extends TestCase
{
    public function test_super_admin_can_create_form_linked_to_project_and_formation(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)
            ->post('/forms', [
                'title' => 'Candidature Web Dev',
                'description' => 'Recrutement cohorte 2026',
                'project_id' => $project->id,
                'formation_id' => $formation->id,
            ])
            ->assertRedirect();

        $form = Form::query()->where('title', 'Candidature Web Dev')->first();
        $this->assertNotNull($form);
        $this->assertSame(FormStatus::Draft, $form->status);
        $this->assertSame($project->id, $form->project_id);
        $this->assertSame($formation->id, $form->formation_id);
        $this->assertGreaterThanOrEqual(4, $form->fields()->count());
        $this->assertTrue($form->fields()->where('learner_attribute', 'email')->exists());
    }

    public function test_create_form_rejects_formation_from_another_project(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $otherFormation = Formation::factory()->create();

        $this->actingAs($user)
            ->post('/forms', [
                'title' => 'Invalide',
                'project_id' => $project->id,
                'formation_id' => $otherFormation->id,
            ])
            ->assertSessionHasErrors('formation_id');
    }

    public function test_trainer_cannot_access_forms_index(): void
    {
        $this->actingAsTrainer()
            ->get('/forms')
            ->assertForbidden();
    }

    public function test_published_form_accepts_public_submission(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);

        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Form public',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);

        $this->actingAs($user)->post("/forms/{$form->id}/publish")->assertRedirect();
        $form->refresh();

        $answers = [];
        foreach ($form->fields as $field) {
            $answers[$field->id] = match ($field->learner_attribute) {
                'first_name' => 'Awa',
                'last_name' => 'Ouédraogo',
                'email' => 'awa@example.com',
                'phone' => '+22670000000',
                default => 'valeur',
            };
        }

        $this->post("/f/{$form->public_token}", [
            'answers' => $answers,
            'website' => '',
        ])
            ->assertRedirect(route('public.forms.thanks', $form->public_token))
            ->assertSessionHas('public_form_thanks.'.$form->public_token);

        $this->assertDatabaseHas('form_responses', [
            'form_id' => $form->id,
            'email' => 'awa@example.com',
        ]);

        $this->get(route('public.forms.thanks', $form->public_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/Forms/Thanks'));
    }

    public function test_thanks_page_requires_submission_receipt(): void
    {
        $form = Form::factory()->published()->create();

        $this->get(route('public.forms.thanks', $form->public_token))
            ->assertRedirect(route('public.forms.show', $form->public_token));
    }

    public function test_closed_form_rejects_public_submission(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->closed()->create(['created_by' => $user->id]);

        FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::Email,
            'label' => 'E-mail',
            'is_required' => true,
            'position' => 0,
            'learner_attribute' => 'email',
        ]);

        $fieldId = $form->fields()->first()->id;

        $this->post("/f/{$form->public_token}", [
            'answers' => [$fieldId => 'test@example.com'],
        ])->assertSessionHasErrors('form');
    }

    public function test_locked_form_rejects_public_submission(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->locked()->create(['created_by' => $user->id]);

        FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::ShortText,
            'label' => 'Nom',
            'is_required' => true,
            'position' => 0,
        ]);

        $fieldId = $form->fields()->first()->id;

        $this->post("/f/{$form->public_token}", [
            'answers' => [$fieldId => 'Test'],
        ])->assertSessionHasErrors('form');
    }

    public function test_draft_form_public_page_returns_404(): void
    {
        $form = Form::factory()->create([
            'public_token' => Str::random(64),
        ]);

        $this->get("/f/{$form->public_token}")->assertNotFound();
    }

    public function test_invalid_public_token_returns_404(): void
    {
        $this->get('/f/'.Str::random(8))->assertNotFound();
    }

    public function test_duplicate_email_is_rejected_when_one_per_email(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);

        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Unique email',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);

        $this->actingAs($user)->post("/forms/{$form->id}/publish");
        $form->refresh();

        $answers = [];
        foreach ($form->fields as $field) {
            $answers[$field->id] = match ($field->learner_attribute) {
                'first_name' => 'Awa',
                'last_name' => 'Ouédraogo',
                'email' => 'dup@example.com',
                'phone' => '70000000',
                default => 'x',
            };
        }

        $this->post("/f/{$form->public_token}", ['answers' => $answers, 'website' => ''])
            ->assertRedirect();

        $this->post("/f/{$form->public_token}", ['answers' => $answers, 'website' => ''])
            ->assertSessionHasErrors('email');
    }

    public function test_honeypot_blocks_bots(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);
        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Bot trap',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);
        $this->actingAs($user)->post("/forms/{$form->id}/publish");
        $form->refresh();

        $answers = [];
        foreach ($form->fields as $field) {
            $answers[$field->id] = $field->learner_attribute === 'email' ? 'bot@example.com' : 'x';
        }

        $this->post("/f/{$form->public_token}", [
            'answers' => $answers,
            'website' => 'http://spam.test',
        ])->assertSessionHasErrors();
    }

    public function test_form_can_be_duplicated_as_draft(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);

        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Original',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/duplicate")
            ->assertRedirect();

        $copy = Form::query()->where('title', 'Original (copie)')->first();
        $this->assertNotNull($copy);
        $this->assertSame(FormStatus::Draft, $copy->status);
        $this->assertNotSame($form->public_token, $copy->public_token);
        $this->assertSame($form->fields()->count(), $copy->fields()->count());
    }

    public function test_locked_form_cannot_sync_fields(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->locked()->create(['created_by' => $user->id]);

        FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::ShortText,
            'label' => 'Nom',
            'is_required' => true,
            'position' => 0,
        ]);

        $this->actingAs($user)
            ->put("/forms/{$form->id}/fields", [
                'fields' => [
                    [
                        'type' => 'short_text',
                        'label' => 'Modifié',
                        'is_required' => true,
                    ],
                ],
            ])
            ->assertSessionHasErrors('fields');
    }

    public function test_public_submission_accepts_multiple_choice(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);

        $emailField = FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::Email,
            'label' => 'E-mail',
            'is_required' => true,
            'position' => 0,
            'learner_attribute' => 'email',
        ]);

        $multi = FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::MultipleChoice,
            'label' => 'Compétences',
            'is_required' => true,
            'position' => 1,
            'options' => [
                ['label' => 'PHP', 'value' => 'php'],
                ['label' => 'Vue', 'value' => 'vue'],
            ],
        ]);

        $this->post("/f/{$form->public_token}", [
            'answers' => [
                $emailField->id => 'multi@example.com',
                $multi->id => ['php', 'vue'],
            ],
            'website' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('form_responses', [
            'form_id' => $form->id,
            'email' => 'multi@example.com',
        ]);
    }

    public function test_selecting_candidate_creates_learner_and_enrolls(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create([
            'project_id' => $project->id,
            'capacity' => 10,
        ]);

        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Sélection',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);

        $this->actingAs($user)->post("/forms/{$form->id}/publish");
        $form->refresh();

        $answers = [];
        foreach ($form->fields as $field) {
            $answers[$field->id] = match ($field->learner_attribute) {
                'first_name' => 'Fatou',
                'last_name' => 'Traoré',
                'email' => 'fatou@example.com',
                'phone' => '70001122',
                default => 'x',
            };
        }

        $this->post("/f/{$form->public_token}", [
            'answers' => $answers,
            'website' => '',
        ])->assertRedirect();

        $response = $form->responses()->first();
        $this->assertNotNull($response);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/responses/select", [
                'response_ids' => [$response->id],
                'review_note' => 'Bon profil',
            ])
            ->assertRedirect();

        $response->refresh();
        $this->assertSame(FormResponseStatus::Selected, $response->status);
        $this->assertSame($user->id, $response->reviewed_by);
        $this->assertSame('Bon profil', $response->review_note);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/responses/enroll", [
                'response_ids' => [$response->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learners', [
            'email' => 'fatou@example.com',
            'first_name' => 'Fatou',
            'last_name' => 'Traoré',
        ]);

        $learner = Learner::query()->where('email', 'fatou@example.com')->first();
        $this->assertTrue(
            $formation->learners()->where('learner_id', $learner->id)->exists()
        );

        $response->refresh();
        $this->assertSame(FormResponseStatus::Enrolled, $response->status);
        $this->assertSame($learner->id, $response->learner_id);
    }

    public function test_reject_candidates(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'reject@example.com',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/responses/reject", [
                'response_ids' => [$response->id],
            ])
            ->assertRedirect();

        $response->refresh();
        $this->assertSame(FormResponseStatus::Rejected, $response->status);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/responses/unreject", [
                'response_ids' => [$response->id],
            ])
            ->assertRedirect();

        $response->refresh();
        $this->assertSame(FormResponseStatus::Submitted, $response->status);
        $this->assertNull($response->reviewed_by);
    }

    public function test_shorten_link_returns_json_and_invalidates_old_token(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create([
            'created_by' => $user->id,
            'public_token' => Str::random(64),
        ]);
        $oldToken = $form->public_token;

        $this->actingAs($user)
            ->postJson("/forms/{$form->id}/shorten-link")
            ->assertOk()
            ->assertJsonPath('can_shorten_link', false);

        $form->refresh();
        $this->assertSame(8, strlen($form->public_token));
        $this->assertNotSame($oldToken, $form->public_token);
        $this->get("/f/{$oldToken}")->assertNotFound();
        $this->get("/f/{$form->public_token}")->assertOk();
    }

    public function test_header_image_upload_is_visible_on_public_form(): void
    {
        Storage::fake('public');

        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);
        $file = UploadedFile::fake()->image('banner.jpg', 800, 200);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/header-image", [
                'header_image' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['header_image_url']);

        $form->refresh();
        $this->assertNotNull($form->header_image_path);
        Storage::disk('public')->assertExists($form->header_image_path);

        $this->get("/f/{$form->public_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Forms/Show')
                ->where('form.header_image_url', '/storage/'.$form->header_image_path)
                ->where('form.show_progress', true)
            );
    }

    public function test_public_url_is_relative_path(): void
    {
        $form = Form::factory()->published()->create();

        $this->assertSame('/f/'.$form->public_token, $form->publicUrl());
        $this->assertStringStartsWith('/f/', $form->publicUrl());
        $this->assertFalse(str_starts_with($form->publicUrl(), 'http'));
    }

    public function test_expired_form_shows_time_elapsed(): void
    {
        $form = Form::factory()->published()->create([
            'settings' => array_merge(Form::defaultSettings(), [
                'closes_at' => now()->subMinute()->toIso8601String(),
            ]),
        ]);

        $this->get("/f/{$form->public_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Forms/Show')
                ->where('form.is_expired', true)
                ->where('form.accepts_responses', false)
            );
    }

    public function test_identity_update_returns_json(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/forms/{$form->id}/identity", [
                'title' => 'Nouveau titre',
                'description' => 'Nouvelle description',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Nouveau titre');

        $form->refresh();
        $this->assertSame('Nouveau titre', $form->title);
        $this->assertSame('Nouvelle description', $form->description);
    }

    public function test_archive_form_is_visible_in_archived_filter(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id, 'title' => 'À archiver']);

        $this->actingAs($user)
            ->delete("/forms/{$form->id}")
            ->assertRedirect(route('forms.index', ['status' => 'archived']));

        $form->refresh();
        $this->assertSame(FormStatus::Archived, $form->status);
        $this->get("/f/{$form->public_token}")->assertNotFound();

        $this->actingAs($user)
            ->get('/forms?status=archived')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Forms/Index')
                ->has('forms.data', 1)
                ->where('forms.data.0.title', 'À archiver')
            );
    }

    public function test_unarchive_form_returns_to_draft(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->create([
            'created_by' => $user->id,
            'status' => FormStatus::Archived,
        ]);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/unarchive")
            ->assertRedirect(route('forms.edit', $form));

        $form->refresh();
        $this->assertSame(FormStatus::Draft, $form->status);
    }

    public function test_authorized_user_can_download_response_file(): void
    {
        Storage::fake('local');

        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);
        $field = FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::File,
            'label' => 'CV',
            'is_required' => false,
            'position' => 0,
        ]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'file@example.com',
            'submitted_at' => now(),
        ]);

        $path = 'forms/uploads/'.$form->id.'/cv.pdf';
        Storage::disk('local')->put($path, 'fake-pdf-content');

        $answer = \App\Models\FormAnswer::create([
            'form_response_id' => $response->id,
            'form_field_id' => $field->id,
            'file_path' => $path,
            'file_original_name' => 'mon-cv.pdf',
        ]);

        $this->actingAs($user)
            ->get("/forms/{$form->id}/responses/{$response->id}/answers/{$answer->id}/download")
            ->assertOk()
            ->assertDownload('mon-cv.pdf');
    }

    public function test_close_expired_forms_command(): void
    {
        $form = Form::factory()->published()->create([
            'settings' => array_merge(Form::defaultSettings(), [
                'closes_at' => now()->subMinute()->toIso8601String(),
            ]),
        ]);

        $this->artisan('forms:close-expired')
            ->assertSuccessful();

        $form->refresh();
        $this->assertSame(FormStatus::Closed, $form->status);
    }

    public function test_form_stats_page_is_available(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);

        FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'stats@example.com',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/forms/{$form->id}/stats")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Forms/Stats')
                ->where('stats.totals.responses', 1)
            );
    }

    public function test_public_submission_notifies_admins(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);
        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Notif form',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);

        $this->actingAs($user)->post("/forms/{$form->id}/publish");
        $form->refresh();

        $answers = [];
        foreach ($form->fields as $field) {
            $answers[$field->id] = match ($field->learner_attribute) {
                'first_name' => 'Awa',
                'last_name' => 'Kaboré',
                'email' => 'awa.notif@example.com',
                'phone' => '+22670001122',
                default => 'x',
            };
        }

        $this->post("/f/{$form->public_token}", [
            'answers' => $answers,
            'website' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'form_response_submitted',
        ]);
    }

    public function test_shortlist_and_unshortlist_candidates(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'short@example.com',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/responses/shortlist", [
                'response_ids' => [$response->id],
                'review_note' => 'À revoir',
            ])
            ->assertRedirect();

        $response->refresh();
        $this->assertSame(FormResponseStatus::Shortlisted, $response->status);
        $this->assertSame('À revoir', $response->review_note);

        $this->actingAs($user)
            ->post("/forms/{$form->id}/responses/unshortlist", [
                'response_ids' => [$response->id],
            ])
            ->assertRedirect();

        $response->refresh();
        $this->assertSame(FormResponseStatus::Submitted, $response->status);
    }

    public function test_export_responses_pdf(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);

        FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'pdf@example.com',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/forms/{$form->id}/responses/export-pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_responses_can_be_filtered_by_status(): void
    {
        $user = $this->createSuperAdmin();
        $form = Form::factory()->published()->create(['created_by' => $user->id]);

        FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'submitted@example.com',
            'submitted_at' => now(),
        ]);

        FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Selected,
            'email' => 'selected@example.com',
            'submitted_at' => now(),
        ]);

        FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Enrolled,
            'email' => 'enrolled@example.com',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/forms/{$form->id}/responses?status=selected")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Forms/Responses')
                ->has('responses.data', 1)
                ->where('responses.data.0.email', 'selected@example.com')
                ->where('filters.status', 'selected')
            );
    }

    public function test_export_for_import_matches_learner_import_headers(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);

        $form = app(CreateForm::class)->execute($user, [
            'title' => 'Export import',
            'project_id' => $project->id,
            'formation_id' => $formation->id,
        ]);

        $this->actingAs($user)->post("/forms/{$form->id}/publish");
        $form->refresh();

        $photoField = FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::File,
            'label' => 'Photo',
            'is_required' => false,
            'position' => 99,
            'learner_attribute' => 'photo_path',
        ]);

        $answers = [];
        foreach ($form->fields as $field) {
            $answers[$field->id] = match ($field->learner_attribute) {
                'first_name' => 'Amina',
                'last_name' => 'Sawadogo',
                'email' => 'amina.export@example.com',
                'phone' => '+22670112233',
                default => 'x',
            };
        }

        $this->post("/f/{$form->public_token}", [
            'answers' => $answers,
            'website' => '',
        ])->assertRedirect();

        $response = $form->responses()->first();
        $this->assertNotNull($response);

        Storage::fake('local');
        $path = 'forms/uploads/'.$form->id.'/photo.jpg';
        Storage::disk('local')->put($path, 'fake-image');

        \App\Models\FormAnswer::create([
            'form_response_id' => $response->id,
            'form_field_id' => $photoField->id,
            'file_path' => $path,
            'file_original_name' => 'photo.jpg',
        ]);

        $download = $this->actingAs($user)
            ->get("/forms/{$form->id}/responses/export-import")
            ->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $download->streamedContent());

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = (string) $cell->getValue();
            }
        }

        $this->assertSame(\App\Support\LearnerFormAttributes::importColumns(), $headers);
        $this->assertContains('prenom', $headers);
        $this->assertContains('photo', $headers);

        $prenomCol = array_search('prenom', $headers, true) + 1;
        $photoCol = array_search('photo', $headers, true) + 1;
        $this->assertSame('Amina', (string) $sheet->getCellByColumnAndRow($prenomCol, 2)->getValue());
        $photoUrl = (string) $sheet->getCellByColumnAndRow($photoCol, 2)->getValue();
        $this->assertStringContainsString('/signed-download', $photoUrl);
        $this->assertStringContainsString('signature=', $photoUrl);

        @unlink($tmp);
    }

    public function test_signed_file_download_works_without_auth(): void
    {
        Storage::fake('local');

        $form = Form::factory()->published()->create();
        $field = FormField::create([
            'form_id' => $form->id,
            'type' => FormFieldType::File,
            'label' => 'Photo',
            'is_required' => false,
            'position' => 0,
            'learner_attribute' => 'photo_path',
        ]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'status' => FormResponseStatus::Submitted,
            'email' => 'signed@example.com',
            'submitted_at' => now(),
        ]);

        $path = 'forms/uploads/'.$form->id.'/avatar.png';
        Storage::disk('local')->put($path, 'png-bytes');

        $answer = \App\Models\FormAnswer::create([
            'form_response_id' => $response->id,
            'form_field_id' => $field->id,
            'file_path' => $path,
            'file_original_name' => 'avatar.png',
        ]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'forms.responses.answers.signed-download',
            now()->addHour(),
            [
                'form' => $form->id,
                'response' => $response->id,
                'answer' => $answer->id,
            ]
        );

        $this->get($url)
            ->assertOk()
            ->assertDownload('avatar.png');
    }
}
