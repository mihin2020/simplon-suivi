<?php

namespace App\Support;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Writes every text as text. PhpSpreadsheet turns a string starting with "=" into a formula, and exports contain
 * what anonymous candidates typed in public forms: "=WEBSERVICE(...)" would run when an administrator opens the file.
 * No export of the application writes a formula on purpose.
 */
class SafeValueBinder extends DefaultValueBinder
{
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
