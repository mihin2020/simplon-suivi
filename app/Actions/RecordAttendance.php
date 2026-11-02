<?php

namespace App\Actions;

use App\Enums\AttendanceCode;
use App\Models\Attendance;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordAttendance
{
    /**
     * Upsert attendance records for a day. A reason is only kept for justified codes.
     *
     * @param  array<string, array{code: string, comment?: string|null}>  $records  Indexed by learner uuid
     */
    public function execute(Formation $formation, Carbon $date, array $records, User $recorder): void
    {
        DB::transaction(function () use ($formation, $date, $records, $recorder) {
            foreach ($records as $learnerId => $record) {
                $code = AttendanceCode::from($record['code']);
                $comment = isset($record['comment']) ? trim($record['comment']) : null;

                Attendance::updateOrCreate(
                    [
                        'formation_id' => $formation->id,
                        'learner_id' => $learnerId,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'code' => $code->value,
                        'comment' => $code->acceptsReason() && $comment !== '' ? $comment : null,
                        'recorded_by' => $recorder->id,
                    ]
                );
            }
        });
    }
}
