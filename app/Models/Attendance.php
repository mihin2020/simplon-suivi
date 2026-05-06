<?php

namespace App\Models;

use App\Enums\AttendanceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'formation_id',
        'learner_id',
        'date',
        'code',
        'comment',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'code' => AttendanceCode::class,
        ];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeAbsences(Builder $query): Builder
    {
        return $query->whereIn('code', [
            AttendanceCode::AbsentJustified->value,
            AttendanceCode::AbsentNotJustified->value,
        ]);
    }
}
