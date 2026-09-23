<?php

namespace App\Enums;

enum FormResponseStatus: string
{
    case Submitted = 'submitted';
    case Shortlisted = 'shortlisted';
    case Selected = 'selected';
    case Rejected = 'rejected';
    case Enrolled = 'enrolled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Soumise',
            self::Shortlisted => 'Présélectionnée',
            self::Selected => 'Sélectionnée',
            self::Rejected => 'Rejetée',
            self::Enrolled => 'Inscrite',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::Shortlisted => 'blue',
            self::Selected => 'green',
            self::Rejected => 'red',
            self::Enrolled => 'purple',
        };
    }
}
