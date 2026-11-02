<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\FormResponseStatus;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pins the page listing the answers of a form: the row of each response and its filters. */
class FormResponsesListTest extends TestCase
{
    private Form $form;

    private FormField $textField;

    private FormField $choiceField;

    private FormField $fileField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = Form::factory()->create(['title' => 'Candidature']);
        $this->textField = $this->field('Nom complet', FormFieldType::ShortText, 1);
        $this->choiceField = $this->field('Langages', FormFieldType::MultipleChoice, 2);
        $this->fileField = $this->field('CV', FormFieldType::File, 3);
        $this->actingAsSuperAdmin();
    }

    private function field(string $label, FormFieldType $type, int $position): FormField
    {
        return FormField::create(['form_id' => $this->form->id, 'type' => $type, 'label' => $label, 'position' => $position, 'is_required' => false]);
    }

    private function response(string $email, FormResponseStatus $status, string $at, array $extra = []): FormResponse
    {
        return FormResponse::create(['form_id' => $this->form->id, 'email' => $email, 'status' => $status, 'submitted_at' => Carbon::parse($at), ...$extra]);
    }

    private function answer(FormResponse $response, FormField $field, array $attributes): FormAnswer
    {
        return FormAnswer::create(['form_response_id' => $response->id, 'form_field_id' => $field->id, ...$attributes]);
    }

    private function props(array $query = []): array
    {
        return $this->get(route('forms.responses.index', ['form' => $this->form, ...$query]))->assertOk()->viewData('page')['props'];
    }

    public function test_the_page_describes_the_form_and_the_available_statuses(): void
    {
        $props = $this->props();

        $this->assertSame(['id' => $this->form->id, 'title' => 'Candidature', 'status' => 'draft'], array_intersect_key($props['form'], array_flip(['id', 'title', 'status'])));
        $this->assertSame(['status' => null, 'search' => null], $props['filters']);
        $this->assertSame(
            array_map(fn (FormResponseStatus $s) => $s->value, FormResponseStatus::cases()),
            array_column($props['statuses'], 'value'),
        );
    }

    public function test_responses_come_newest_first_with_their_status_and_review(): void
    {
        $reviewer = User::factory()->create(['first_name' => 'Awa', 'last_name' => 'Zongo']);
        $old = $this->response('old@example.test', FormResponseStatus::Submitted, '2026-10-01 08:00:00');
        $new = $this->response('new@example.test', FormResponseStatus::cases()[1], '2026-10-05 08:00:00', [
            'reviewed_at' => Carbon::parse('2026-10-06 09:00:00'), 'reviewed_by' => $reviewer->id, 'review_note' => 'Bon dossier',
        ]);

        $rows = $this->props()['responses']['data'];

        $this->assertSame(['new@example.test', 'old@example.test'], array_column($rows, 'email'));
        $this->assertSame($new->id, $rows[0]['id']);
        $this->assertSame(FormResponseStatus::cases()[1]->value, $rows[0]['status']);
        $this->assertSame(FormResponseStatus::cases()[1]->label(), $rows[0]['status_label']);
        $this->assertSame('Awa Zongo', $rows[0]['reviewer']);
        $this->assertSame('Bon dossier', $rows[0]['review_note']);
        $this->assertSame(Carbon::parse('2026-10-05 08:00:00')->toIso8601String(), $rows[0]['submitted_at']);
        $this->assertSame(Carbon::parse('2026-10-06 09:00:00')->toIso8601String(), $rows[0]['reviewed_at']);
        $this->assertNull($rows[1]['reviewer']);
        $this->assertNull($rows[1]['reviewed_at']);
        $this->assertSame($old->id, $rows[1]['id']);
    }

    public function test_each_answer_is_shown_as_text_list_or_file(): void
    {
        $response = $this->response('a@example.test', FormResponseStatus::Submitted, '2026-10-01 08:00:00');
        $this->answer($response, $this->textField, ['value_text' => 'Awa Zongo']);
        $this->answer($response, $this->choiceField, ['value_json' => ['PHP', 'Vue']]);
        $photo = $this->answer($response, $this->fileField, ['file_path' => 'forms/cv-1.PNG', 'file_original_name' => 'photo.png']);

        $answers = collect($this->props()['responses']['data'][0]['answers'])->keyBy('label');

        $this->assertSame('Awa Zongo', $answers['Nom complet']['value']);
        $this->assertFalse($answers['Nom complet']['is_file']);
        $this->assertNull($answers['Nom complet']['download_url']);

        $this->assertSame('PHP, Vue', $answers['Langages']['value']);

        $this->assertSame('photo.png', $answers['CV']['value'], 'a file shows its original name');
        $this->assertTrue($answers['CV']['is_file']);
        $this->assertTrue($answers['CV']['is_image']);
        $this->assertSame(
            route('forms.responses.answers.download', ['form' => $this->form->id, 'response' => $response->id, 'answer' => $photo->id]),
            $answers['CV']['download_url'],
        );
    }

    public function test_a_document_is_not_an_image(): void
    {
        $response = $this->response('a@example.test', FormResponseStatus::Submitted, '2026-10-01 08:00:00');
        $this->answer($response, $this->fileField, ['file_path' => 'forms/cv-2.pdf', 'file_original_name' => 'cv.pdf']);
        $this->answer($response, $this->textField, ['file_path' => 'forms/noname.jpeg', 'file_original_name' => null]);

        $answers = collect($this->props()['responses']['data'][0]['answers'])->keyBy('label');

        $this->assertFalse($answers['CV']['is_image']);
        $this->assertTrue($answers['Nom complet']['is_image'], 'without original name the stored path decides');
    }

    public function test_responses_are_filtered_by_status_and_by_email(): void
    {
        $this->response('awa@example.test', FormResponseStatus::Submitted, '2026-10-01 08:00:00');
        $this->response('issa@example.test', FormResponseStatus::cases()[1], '2026-10-02 08:00:00');
        $this->response('awa.second@example.test', FormResponseStatus::cases()[1], '2026-10-03 08:00:00');

        $emails = fn (array $query) => array_column($this->props($query)['responses']['data'], 'email');

        $this->assertSame(['issa@example.test', 'awa.second@example.test'], array_reverse($emails(['status' => FormResponseStatus::cases()[1]->value])));
        $this->assertEqualsCanonicalizing(['awa@example.test', 'awa.second@example.test'], $emails(['search' => 'awa']));
        $this->assertSame(['awa.second@example.test'], $emails(['status' => FormResponseStatus::cases()[1]->value, 'search' => 'awa']));
        $this->assertSame([], $emails(['search' => 'nobody']));
        $this->assertSame('awa', $this->props(['search' => 'awa'])['filters']['search']);
    }

    public function test_the_list_is_paginated_by_twenty(): void
    {
        foreach (range(1, 25) as $i) {
            $this->response("p{$i}@example.test", FormResponseStatus::Submitted, '2026-10-01 08:00:00');
        }

        $responses = $this->props()['responses'];

        $this->assertCount(20, $responses['data']);
        $this->assertSame(25, $responses['total']);
    }

    public function test_the_page_needs_the_responses_permission(): void
    {
        $this->actingAsTrainer();

        $this->get(route('forms.responses.index', $this->form))->assertForbidden();
    }
}
