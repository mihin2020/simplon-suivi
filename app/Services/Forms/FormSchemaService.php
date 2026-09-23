<?php

namespace App\Services\Forms;

use App\Models\Form;
use Illuminate\Support\Facades\Cache;

class FormSchemaService
{
    public function cacheKey(string $publicToken): string
    {
        return "form:schema:{$publicToken}";
    }

    public function forget(Form $form): void
    {
        if ($form->public_token) {
            Cache::forget($this->cacheKey($form->public_token));
        }
    }

    public function forgetToken(string $publicToken): void
    {
        Cache::forget($this->cacheKey($publicToken));
    }

    /**
     * Always resolve from DB — never cache Eloquent models (breaks public access / images).
     *
     * @return array{form: Form, fields: \Illuminate\Database\Eloquent\Collection}|null
     */
    public function resolvePublishedSchema(string $publicToken): ?array
    {
        $form = Form::query()
            ->with(['fields', 'project:id,name', 'formation:id,name,project_id'])
            ->where('public_token', $publicToken)
            ->first();

        if (! $form) {
            return null;
        }

        return [
            'form' => $form,
            'fields' => $form->fields,
        ];
    }
}
