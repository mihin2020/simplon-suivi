<?php

namespace App\Enums;

enum LetterheadScope: string
{
    case AllPages = 'all_pages';
    case FirstPage = 'first_page';

    public function label(): string
    {
        return match ($this) {
            self::AllPages => 'Toutes les pages',
            self::FirstPage => 'Première page uniquement',
        };
    }
}
