<?php

namespace App\Http\Requests\Insertion;

class UpdateInsertionRecordRequest extends SaveInsertionRecordRequest
{
    /** An entry can be corrected before its details are known, so only the recording demands them. */
    protected function requiresStatusDetails(): bool
    {
        return false;
    }
}
