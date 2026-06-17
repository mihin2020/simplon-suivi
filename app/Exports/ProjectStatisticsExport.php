<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProjectStatisticsExport implements WithMultipleSheets
{
    public function __construct(private Project $project)
    {
    }

    public function sheets(): array
    {
        $this->project->loadMissing('formations.learners');

        $learnerIds = $this->project->formations
            ->flatMap(fn ($formation) => $formation->learners->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $latestInsertionMap = FormationStatisticsSheet::latestInsertionRecordsFor($learnerIds);

        $sheets    = [new ProjectSummarySheet($this->project, $latestInsertionMap)];
        $usedTitles = ['Résumé projet' => true];

        foreach ($this->project->formations as $formation) {
            $sheet = new FormationStatisticsSheet($formation, $latestInsertionMap);

            $baseTitle = $sheet->title();
            $title     = $baseTitle;
            $suffix    = 2;
            while (isset($usedTitles[$title])) {
                $title = mb_substr($baseTitle, 0, 28) . ' (' . $suffix . ')';
                $suffix++;
            }
            $usedTitles[$title] = true;
            $sheet->setUniqueTitle($title);

            $sheets[] = $sheet;
        }

        return $sheets;
    }
}
