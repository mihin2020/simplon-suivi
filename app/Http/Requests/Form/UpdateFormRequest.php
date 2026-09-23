<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('form')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remove_header_image')) {
            $this->merge([
                'remove_header_image' => filter_var($this->input('remove_header_image'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $settings = $this->input('settings');
        if (is_array($settings)) {
            foreach (['max_responses', 'closes_at', 'confirmation_message'] as $key) {
                if (array_key_exists($key, $settings) && $settings[$key] === '') {
                    $settings[$key] = null;
                }
            }
            $this->merge(['settings' => $settings]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'formation_id' => [
                'required',
                'uuid',
                Rule::exists('formations', 'id')->where(fn ($q) => $q->where('project_id', $this->input('project_id'))),
            ],
            'header_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_header_image' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.accept_responses' => ['sometimes', 'boolean'],
            'settings.one_per_email' => ['sometimes', 'boolean'],
            'settings.show_progress' => ['sometimes', 'boolean'],
            'settings.closes_at' => ['nullable', 'date'],
            'settings.max_responses' => ['nullable', 'integer', 'min:1'],
            'settings.confirmation_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
