<?php

namespace App\Actions;

use App\Enums\AttendanceCode;
use App\Models\Attendance;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecordAttendance
{
    /**
     * Upsert attendance records for a full day.
     *
     * @param  array<string, string>  $records  ['learner_uuid' => 'P'|'AJ'|'AN'|'RJ'|'RN']
     */
    public function execute(Formation $formation, Carbon $date, array $records, User $recorder): void
    {
        foreach ($records as $learnerId => $code) {
            Attendance::updateOrCreate(
                [
                    'formation_id' => $formation->id,
                    'learner_id'   => $learnerId,
                    'date'         => $date->toDateString(),
                ],
                [
                    'code'        => AttendanceCode::from($code)->value,
                    'recorded_by' => $recorder->id,
                ]
            );
        }
    }
}
