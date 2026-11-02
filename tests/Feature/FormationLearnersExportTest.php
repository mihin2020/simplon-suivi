<?php

namespace Tests\Feature;

use App\Enums\LearnerStatus;
use App\Exports\FormationLearnersExport;
use App\Models\Formation;
use App\Models\Learner;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class FormationLearnersExportTest extends TestCase
{
    public function test_export_lists_enrolled_learners_sorted_by_family_name_without_files(): void
    {
        $formation = Formation::factory()->create();

        foreach ([['Awa', 'Zongo', LearnerStatus::InProgress], ['Zacharie', 'Bambara', LearnerStatus::Withdrawn]] as [$first, $last, $status]) {
            $learner = Learner::factory()->create([
                'first_name' => $first,
                'last_name' => $last,
                'photo_path' => 'learners/secret.jpg',
                'cnib_path' => 'learners/cnib.pdf',
            ]);
            $formation->learners()->attach($learner->id, ['status' => $status->value, 'enrolled_at' => now()]);
        }
        Learner::factory()->create(['last_name' => 'NotEnrolled']);

        $export = new FormationLearnersExport($formation);
        $rows = $export->array();

        $this->assertCount(2, $rows);
        $this->assertSame(['Bambara', 'Zacharie'], [$rows[0][1], $rows[0][2]]);
        $this->assertSame('Abandonné', $rows[0][24]);
        $this->assertSame('En cours', $rows[1][24]);
        $this->assertCount(count($export->headings()), $rows[0]);
        $this->assertStringNotContainsString('secret.jpg', json_encode($rows));
        $this->assertStringNotContainsString('cnib.pdf', json_encode($rows));
    }

    public function test_download_requires_formation_access_and_returns_an_xlsx(): void
    {
        $formation = Formation::factory()->create();
        Excel::fake();

        $this->actingAs($this->createAdmin())
            ->get(route('formations.learners.export', $formation))
            ->assertForbidden();

        $this->actingAsSuperAdmin()
            ->get(route('formations.learners.export', $formation))
            ->assertOk();

        Excel::assertDownloaded(sprintf('apprenants_%s_%s.xlsx', str($formation->name)->slug(), now()->format('Y-m-d')));
    }

    public function test_text_starting_with_equals_is_stored_as_string_not_formula(): void
    {
        $export = new FormationLearnersExport(Formation::factory()->create());
        $cell = (new Spreadsheet)->getActiveSheet()->getCell('A1');

        $export->bindValue($cell, '=HYPERLINK("http://evil","x")');
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());

        $export->bindValue($cell, '+22670123456');
        $this->assertSame('+22670123456', $cell->getValue());
    }
}
