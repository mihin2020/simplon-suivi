<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Services\Forms\FormSchemaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadFormHeaderImage
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    public function execute(Form $form, UploadedFile $headerImage): Form
    {
        if ($form->header_image_path) {
            Storage::disk('public')->delete($form->header_image_path);
        }

        $ext = strtolower($headerImage->getClientOriginalExtension() ?: 'jpg');
        $path = $headerImage->storeAs(
            'forms/headers',
            Str::uuid()->toString().'.'.$ext,
            'public'
        );

        $form->update([
            'header_image_path' => $path,
            'header_image_original_name' => $headerImage->getClientOriginalName(),
        ]);

        $this->schemaService->forget($form);

        return $form->fresh();
    }

    public function remove(Form $form): Form
    {
        if ($form->header_image_path) {
            Storage::disk('public')->delete($form->header_image_path);
        }

        $form->update([
            'header_image_path' => null,
            'header_image_original_name' => null,
        ]);

        $this->schemaService->forget($form);

        return $form->fresh();
    }
}
