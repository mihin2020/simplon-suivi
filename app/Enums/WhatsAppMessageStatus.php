<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    /** Queued: the broadcast is going out in the background. */
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
