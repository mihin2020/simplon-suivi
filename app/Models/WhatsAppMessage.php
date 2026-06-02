<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'phone',
        'recipient_name',
        'message',
        'provider',
        'status',
        'external_id',
        'error',
        'formation_name',
        'project_name',
    ];
}
