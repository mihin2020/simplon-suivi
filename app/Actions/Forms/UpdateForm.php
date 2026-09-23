<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Models\Formation;
use App\Services\Forms\FormSchemaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateForm
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    public function execute(Form $form, array $data, ?UploadedFile $headerImage = null): Form
    {
        if (! $form->status->allowsStructureEdit() && $this->touchesStructure($data)) {
            throw ValidationException::withMessages([
                'status' => 'Ce formulaire est verrouillé : la structure ne peut plus être modifiée.',
            ]);
        }

        if (isset($data['formation_id'], $data['project_id'])) {
            $formation = Formation::findOrFail($data['formation_id']);
            if ($formation->project_id !== $data['project_id']) {
                throw ValidationException::withMessages([
                    'formation_id' => 'La formation doit appartenir au projet sélectionné.',
                ]);
            }
        }

        $form->fill([
            'title' => $data['title'] ?? $form->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $form->description,
            'project_id' => $data['project_id'] ?? $form->project_id,
            'formation_id' => $data['formation_id'] ?? $form->formation_id,
        ]);

        if (isset($data['settings']) && is_array($data['settings'])) {
            $form->settings = array_merge($form->settings ?? Form::defaultSettings(), $data['settings']);
        }

        if (! empty($data['remove_header_image'])) {
            $this->deleteHeaderImage($form);
            $form->header_image_path = null;
            $form->header_image_original_name = null;
        }

        if ($headerImage instanceof UploadedFile) {
            $this->deleteHeaderImage($form);
            $ext = strtolower($headerImage->getClientOriginalExtension() ?: 'jpg');
            $path = $headerImage->storeAs(
                'forms/headers',
                Str::uuid()->toString().'.'.$ext,
                'public'
            );
            $form->header_image_path = $path;
            $form->header_image_original_name = $headerImage->getClientOriginalName();
        }

        $form->save();
        $this->schemaService->forget($form);

        return $form->fresh(['fields', 'project', 'formation']);
    }

    private function deleteHeaderImage(Form $form): void
    {
        if ($form->header_image_path) {
            Storage::disk('public')->delete($form->header_image_path);
        }
    }

    private function touchesStructure(array $data): bool
    {
        return isset($data['project_id']) || isset($data['formation_id']);
    }
}
