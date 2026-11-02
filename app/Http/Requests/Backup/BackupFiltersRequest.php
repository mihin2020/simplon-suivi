<?php

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;

class BackupFiltersRequest extends FormRequest
{
    /** Access is decided by the permission middleware of the route. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the size is bounded: a value that matches nothing is ignored by the listing instead of refusing the page.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'date_from' => ['nullable', 'string', 'max:20'],
            'date_to' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{type: string, status: string, date_from: string, date_to: string, search: string}
     */
    public function filters(): array
    {
        return [
            'type' => $this->string('type')->toString(),
            'status' => $this->string('status')->toString(),
            'date_from' => $this->string('date_from')->toString(),
            'date_to' => $this->string('date_to')->toString(),
            'search' => $this->string('search')->toString(),
        ];
    }
}
