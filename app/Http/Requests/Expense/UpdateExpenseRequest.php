<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'amount'       => ['required', 'integer', 'min:0'],
            'expense_date' => ['required', 'date'],
            'spent_by'     => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'files'        => ['nullable', 'array'],
            'files.*'      => ['file', 'mimes:pdf,jpeg,jpg,png,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }
}
