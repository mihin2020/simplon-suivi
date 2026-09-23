<?php

namespace App\Enums;

enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Locked = 'locked';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Published => 'Publié',
            self::Closed => 'Fermé',
            self::Locked => 'Verrouillé',
            self::Archived => 'Archivé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'green',
            self::Closed => 'orange',
            self::Locked => 'red',
            self::Archived => 'slate',
        };
    }

    public function acceptsResponses(): bool
    {
        return $this === self::Published;
    }

    public function allowsStructureEdit(): bool
    {
        return ! in_array($this, [self::Locked, self::Archived], true);
    }
}
