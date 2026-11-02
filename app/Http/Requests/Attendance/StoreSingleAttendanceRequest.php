<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceCode;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreSingleAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attendance::class, $this->route('formation')]);
    }

    public function rules(): array
    {
        return [
            'learner_id' => [
                'required',
                'uuid',
                Rule::exists('formation_learner', 'learner_id')->where('formation_id', $this->route('formation')->id),
            ],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'code' => ['required', 'string', Rule::in(array_column(AttendanceCode::cases(), 'value'))],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $formation = $this->route('formation');
                $date = Carbon::parse($this->input('date'));

                if (! app(AttendanceService::class)->isWithinFormation($formation, $date)) {
                    $validator->errors()->add('date', 'Cette date est en dehors de la période de la formation.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'learner_id.exists' => "Cet apprenant n'est pas inscrit à cette formation.",
            'date.before_or_equal' => 'Impossible de saisir les présences d\'un jour à venir.',
            'comment.max' => 'Le motif ne peut pas dépasser 255 caractères.',
        ];
    }
}
