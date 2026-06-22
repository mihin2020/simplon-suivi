<?php

namespace App\Support;

use App\Models\AppSetting;

class AttendanceSettings
{
    public const ABSENCE_ALERT_THRESHOLD_KEY = 'attendance_absence_alert_threshold';

    public static function absenceAlertThreshold(): ?int
    {
        $value = AppSetting::get(self::ABSENCE_ALERT_THRESHOLD_KEY);

        if ($value === null || $value === '') {
            return null;
        }

        $threshold = (int) $value;

        return $threshold > 0 ? $threshold : null;
    }

    public static function setAbsenceAlertThreshold(?int $threshold): void
    {
        if ($threshold === null || $threshold <= 0) {
            AppSetting::set(self::ABSENCE_ALERT_THRESHOLD_KEY, '');

            return;
        }

        AppSetting::set(self::ABSENCE_ALERT_THRESHOLD_KEY, (string) $threshold);
    }
}
