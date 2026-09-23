<?php

namespace App\Actions\Forms;

use App\Enums\FormFieldType;
use App\Enums\FormResponseStatus;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Services\Forms\NotifyFormResponseSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitPublicResponse
{
    public function __construct(
        private readonly NotifyFormResponseSubmitted $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $answers  keyed by field id
     * @param  array<string, UploadedFile>  $files  keyed by field id
     */
    public function execute(
        Form $form,
        array $answers,
        array $files,
        ?string $honeypot,
        string $ip,
        ?string $userAgent,
    ): FormResponse {
        if (filled($honeypot)) {
            throw ValidationException::withMessages([
                'form' => 'Soumission invalide.',
            ]);
        }

        if (! $form->acceptsResponses()) {
            throw ValidationException::withMessages([
                'form' => 'Ce formulaire n\'accepte plus les réponses.',
            ]);
        }

        $fields = $form->fields()->get()->keyBy('id');
        $email = $this->extractEmail($fields, $answers);

        $settings = $form->settings ?? [];
        if (($settings['one_per_email'] ?? true) && $email) {
            $exists = $form->responses()->where('email', $email)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Une candidature avec cet e-mail a déjà été enregistrée.',
                ]);
            }
        }

        $response = DB::transaction(function () use ($form, $fields, $answers, $files, $email, $ip, $userAgent) {
            $response = FormResponse::create([
                'form_id' => $form->id,
                'status' => FormResponseStatus::Submitted,
                'email' => $email,
                'submitted_at' => now(),
                'ip_hash' => hash_hmac('sha256', $ip, (string) config('app.key')),
                'user_agent_hash' => $userAgent
                    ? hash_hmac('sha256', $userAgent, (string) config('app.key'))
                    : null,
            ]);

            foreach ($fields as $field) {
                /** @var FormField $field */
                if (! $field->type->isAnswerable()) {
                    continue;
                }

                if ($field->type === FormFieldType::File) {
                    $this->storeFileAnswer($response, $field, $files[$field->id] ?? null);

                    continue;
                }

                $raw = $answers[$field->id] ?? null;

                if ($field->is_required && $this->isBlankAnswer($raw)) {
                    throw ValidationException::withMessages([
                        "answers.{$field->id}" => "Le champ « {$field->label} » est obligatoire.",
                    ]);
                }

                if ($this->isBlankAnswer($raw)) {
                    continue;
                }

                $this->assertType($field, $raw);

                FormAnswer::create([
                    'form_response_id' => $response->id,
                    'form_field_id' => $field->id,
                    'value_text' => is_array($raw) ? null : (string) $raw,
                    'value_json' => is_array($raw) ? array_values($raw) : null,
                ]);
            }

            return $response->load('answers');
        });

        $this->notifier->execute($form, $response);

        return $response;
    }

    private function storeFileAnswer(FormResponse $response, FormField $field, mixed $file): void
    {
        $key = "answers.{$field->id}";

        if (! $file instanceof UploadedFile) {
            if ($field->is_required) {
                throw ValidationException::withMessages([
                    $key => "Le fichier « {$field->label} » est obligatoire.",
                ]);
            }

            return;
        }

        $maxKb = (int) ($field->settings['max_size_kb'] ?? 5120);
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                $key => "Le fichier dépasse la taille maximale ({$maxKb} Ko).",
            ]);
        }

        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                $key => 'Type de fichier non autorisé.',
            ]);
        }

        $mime = (string) $file->getMimeType();
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                $key => 'Type MIME de fichier non autorisé.',
            ]);
        }

        $path = $file->storeAs(
            'forms/uploads/'.$response->form_id,
            Str::uuid()->toString().'.'.$ext,
            'local'
        );

        FormAnswer::create([
            'form_response_id' => $response->id,
            'form_field_id' => $field->id,
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
        ]);
    }

    private function isBlankAnswer(mixed $raw): bool
    {
        if (is_array($raw)) {
            return count(array_filter($raw, fn ($v) => filled($v))) === 0;
        }

        return blank($raw);
    }

    /**
     * @param  Collection<string, FormField>  $fields
     * @param  array<string, mixed>  $answers
     */
    private function extractEmail($fields, array $answers): ?string
    {
        foreach ($fields as $field) {
            if ($field->type === FormFieldType::Email || $field->learner_attribute === 'email') {
                $value = $answers[$field->id] ?? null;
                if (filled($value) && is_string($value)) {
                    return strtolower(trim($value));
                }
            }
        }

        return null;
    }

    private function assertType(FormField $field, mixed $raw): void
    {
        $key = "answers.{$field->id}";

        match ($field->type) {
            FormFieldType::Email => filter_var($raw, FILTER_VALIDATE_EMAIL) !== false
                || throw ValidationException::withMessages([$key => 'Adresse e-mail invalide.']),
            FormFieldType::Number => is_numeric($raw)
                || throw ValidationException::withMessages([$key => 'Nombre invalide.']),
            FormFieldType::Date => (bool) strtotime((string) $raw)
                || throw ValidationException::withMessages([$key => 'Date invalide.']),
            FormFieldType::SingleChoice, FormFieldType::Dropdown => $this->assertOption($field, $raw, $key),
            FormFieldType::MultipleChoice => $this->assertOptions($field, $raw, $key),
            FormFieldType::LinearScale => $this->assertScale($field, $raw, $key),
            default => null,
        };
    }

    private function assertOption(FormField $field, mixed $raw, string $key): void
    {
        $options = collect($field->options ?? [])->pluck('value')->all();
        if ($options !== [] && ! in_array($raw, $options, true)) {
            throw ValidationException::withMessages([$key => 'Option invalide.']);
        }
    }

    private function assertOptions(FormField $field, mixed $raw, string $key): void
    {
        if (! is_array($raw)) {
            throw ValidationException::withMessages([$key => 'Sélection invalide.']);
        }

        $options = collect($field->options ?? [])->pluck('value')->all();
        foreach ($raw as $value) {
            if ($options !== [] && ! in_array($value, $options, true)) {
                throw ValidationException::withMessages([$key => 'Option invalide.']);
            }
        }
    }

    private function assertScale(FormField $field, mixed $raw, string $key): void
    {
        $min = (int) ($field->settings['min'] ?? 1);
        $max = (int) ($field->settings['max'] ?? 5);
        if (! is_numeric($raw) || (int) $raw < $min || (int) $raw > $max) {
            throw ValidationException::withMessages([$key => "Valeur hors échelle ({$min}–{$max})."]);
        }
    }
}
