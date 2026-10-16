<?php

namespace App\Enums;

enum FillFieldsStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
