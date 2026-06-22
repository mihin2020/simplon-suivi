<?php

namespace App\Models;

use App\Enums\InsertionStatus;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsertionRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'learner_id',
        'status',
        'status_changed_at',
        'status_notes',
        'internship_start_date',
        'internship_end_date',
        'internship_company',
        'internship_paid',
        'internship_contract_type',
        'internship_work_mode',
        'internship_contract_path',
        'internship_contract_original_name',
        'employment_company',
        'employment_start_date',
        'employment_contract_type',
        'employment_work_mode',
        'employment_contract_path',
        'employment_contract_original_name',
        'employment_position',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => InsertionStatus::class,
            'status_changed_at' => 'datetime',
            'internship_start_date' => 'date',
            'internship_end_date' => 'date',
            'internship_paid' => 'boolean',
            'internship_work_mode' => WorkMode::class,
            'employment_start_date' => 'date',
            'employment_work_mode' => WorkMode::class,
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeLatestForLearner($query, string $learnerId)
    {
        return $query->where('learner_id', $learnerId)
            ->orderBy('status_changed_at', 'desc')
            ->orderBy('created_at', 'desc');
    }
}
