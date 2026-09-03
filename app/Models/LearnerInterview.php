<?php

namespace App\Models;

use App\Support\InterviewCustomFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearnerInterview extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'learner_id',
        'conducted_by',
        'created_by',
        'conducted_at',
        'subject',
        'notes',
        'recommendation',
        'next_follow_up_at',
        'is_important',
        'meta',
    ];

    protected $appends = [
        'custom_fields',
    ];

    protected $hidden = [
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'conducted_at' => 'date',
            'next_follow_up_at' => 'date',
            'is_important' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return Attribute<array<int, array{label: string, value: string}>, never>
     */
    protected function customFields(): Attribute
    {
        return Attribute::get(
            fn (): array => InterviewCustomFields::fromMeta($this->meta),
        );
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderByDesc('conducted_at')->orderByDesc('created_at');
    }

    public function scopeUpcomingFollowUp(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', '>=', now()->toDateString())
            ->orderBy('next_follow_up_at');
    }
}
