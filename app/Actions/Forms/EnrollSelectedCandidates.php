<?php

namespace App\Actions\Forms;

use App\Actions\EnrollLearner;
use App\Enums\FormResponseStatus;
use App\Enums\Gender;
use App\Models\AgeRange;
use App\Models\EducationLevel;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;
use App\Models\Learner;
use App\Models\User;
use App\Support\LearnerFormAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollSelectedCandidates
{
    public function __construct(private readonly EnrollLearner $enrollLearner) {}

    /**
     * @param  list<string>  $responseIds
     * @return array{enrolled: int, errors: list<string>}
     */
    public function execute(Form $form, array $responseIds, User $reviewer): array
    {
        $form->loadMissing(['formation.project', 'fields']);

        $responses = FormResponse::query()
            ->with(['answers.field'])
            ->where('form_id', $form->id)
            ->whereIn('id', $responseIds)
            ->where('status', FormResponseStatus::Selected)
            ->get();

        if ($responses->isEmpty()) {
            throw ValidationException::withMessages([
                'response_ids' => 'Sélectionnez d’abord des candidatures (statut Sélectionnée).',
            ]);
        }

        $enrolled = 0;
        $errors = [];

        foreach ($responses as $response) {
            try {
                DB::transaction(function () use ($form, $response, $reviewer, &$enrolled) {
                    $payload = $this->mapLearnerAttributes($response);
                    $learner = $this->findOrCreateLearner($payload);
                    $this->enrollLearner->execute($form->formation, $learner);

                    $response->update([
                        'status' => FormResponseStatus::Enrolled,
                        'learner_id' => $learner->id,
                        'reviewed_at' => now(),
                        'reviewed_by' => $reviewer->id,
                    ]);

                    $enrolled++;
                });
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?? 'Erreur d’inscription.';
                $errors[] = ($response->email ?? $response->id).' : '.$message;
            }
        }

        if ($enrolled === 0 && $errors !== []) {
            throw ValidationException::withMessages([
                'response_ids' => implode(' ', $errors),
            ]);
        }

        return compact('enrolled', 'errors');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLearnerAttributes(FormResponse $response): array
    {
        $allowed = LearnerFormAttributes::keys();
        $data = [];
        $photoAnswer = null;

        foreach ($response->answers as $answer) {
            $attr = $answer->field?->learner_attribute;
            if (! $attr || ! in_array($attr, $allowed, true)) {
                continue;
            }

            if ($attr === 'photo_path') {
                $photoAnswer = $answer;
                continue;
            }

            $value = $answer->value_json ?? $answer->value_text;
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                continue;
            }

            if ($attr === 'gender') {
                $data[$attr] = $this->resolveGender((string) $value);
            } elseif ($attr === 'children_count') {
                $data[$attr] = is_numeric($value) ? (int) $value : null;
            } elseif ($attr === 'email') {
                $data[$attr] = strtolower(trim((string) $value));
            } elseif ($attr === 'education_level_id') {
                $data[$attr] = $this->resolveEducationLevelId((string) $value);
            } elseif ($attr === 'age_range_id') {
                $data[$attr] = $this->resolveAgeRangeId((string) $value);
            } elseif ($attr === 'birth_date') {
                try {
                    $data[$attr] = \Carbon\Carbon::parse((string) $value)->toDateString();
                } catch (\Throwable) {
                    // skip invalid date
                }
            } else {
                $data[$attr] = $value;
            }
        }

        if (empty($data['email']) && $response->email) {
            $data['email'] = strtolower(trim($response->email));
        }

        foreach (['first_name', 'last_name'] as $required) {
            if (empty($data[$required])) {
                throw ValidationException::withMessages([
                    'response_ids' => "Champ apprenant manquant : {$required}.",
                ]);
            }
        }

        if ($photoAnswer instanceof FormAnswer && filled($photoAnswer->file_path)) {
            $copied = $this->copyPhotoToLearnersDisk($photoAnswer);
            if ($copied) {
                $data['photo_path'] = $copied['path'];
                $data['photo_original_name'] = $copied['original_name'];
            }
        }

        return $data;
    }

    private function resolveGender(string $value): ?string
    {
        $g = strtolower(trim($value));

        return match (true) {
            in_array($g, ['m', 'masculin', 'homme', 'male'], true) => Gender::Male->value,
            in_array($g, ['f', 'feminin', 'féminin', 'femme', 'female'], true) => Gender::Female->value,
            default => Gender::tryFrom($value)?->value,
        };
    }

    private function resolveEducationLevelId(string $value): ?string
    {
        if (EducationLevel::query()->whereKey($value)->exists()) {
            return $value;
        }

        return EducationLevel::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
            ->value('id');
    }

    private function resolveAgeRangeId(string $value): ?string
    {
        if (AgeRange::query()->whereKey($value)->exists()) {
            return $value;
        }

        return AgeRange::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
            ->value('id');
    }

    /**
     * @return array{path: string, original_name: string}|null
     */
    private function copyPhotoToLearnersDisk(FormAnswer $answer): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($answer->file_path)) {
            return null;
        }

        $ext = pathinfo($answer->file_original_name ?: $answer->file_path, PATHINFO_EXTENSION) ?: 'jpg';
        $target = 'learners/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($target, $disk->get($answer->file_path));

        return [
            'path' => $target,
            'original_name' => $answer->file_original_name ?: basename($target),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findOrCreateLearner(array $payload): Learner
    {
        $existing = null;
        if (! empty($payload['email'])) {
            $existing = Learner::query()->where('email', $payload['email'])->first();
        }

        if ($existing) {
            $existing->fill(collect($payload)->except(['email'])->all());
            $existing->save();

            return $existing;
        }

        return Learner::create($payload);
    }
}
