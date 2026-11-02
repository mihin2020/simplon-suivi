<?php

namespace App\Http\Requests\Insertion;

class StoreInsertionRecordRequest extends SaveInsertionRecordRequest
{
    protected function requiresStatusDetails(): bool
    {
        return true;
    }
}
