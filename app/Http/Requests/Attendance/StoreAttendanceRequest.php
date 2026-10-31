<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceCode;
use App\Models\Attendance;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attendance::class, $this->route('formation')]);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array'],
            'records.*.code' => ['required', 'string', Rule::in(array_column(AttendanceCode::cases(), 'value'))],
            'records.*.comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $learnerIds = array_keys((array) $this->input('records', []));
                $enrolled = $this->route('formation')->learners()
                    ->whereIn('learners.id', $learnerIds)
                    ->count();

                if ($enrolled !== count($learnerIds)) {
                    $validator->errors()->add('records', "Un apprenant n'est pas inscrit à cette formation.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Impossible de saisir les présences d\'un jour à venir.',
            'records.*.comment.max' => 'Le motif ne peut pas dépasser 255 caractères.',
        ];
    }
}
