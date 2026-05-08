<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'message_id',
        'thread_id',
        'direction',
        'from_email',
        'from_name',
        'to',
        'cc',
        'subject',
        'body_html',
        'body_text',
        'is_read',
        'is_archived',
        'sent_at',
        'received_at',
        'parent_id',
        'sent_by',
    ];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
        'is_read' => 'boolean',
        'is_archived' => 'boolean',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Email::class, 'parent_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function scopeSent($query)
    {
        return $query->where('direction', 'sent');
    }

    public function scopeReceived($query)
    {
        return $query->where('direction', 'received');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeThread($query, $threadId)
    {
        return $query->where('thread_id', $threadId);
    }
}
