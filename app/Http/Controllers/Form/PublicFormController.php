<?php

namespace App\Http\Controllers\Form;

use App\Actions\Forms\SubmitPublicResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\SubmitPublicFormRequest;
use App\Models\Form;
use App\Services\Forms\FormSchemaService;
use App\Support\PhoneCountries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    public function show(string $publicToken, FormSchemaService $schemaService): Response
    {
        $payload = $schemaService->resolvePublishedSchema($publicToken);

        if (! $payload) {
            abort(404);
        }

        /** @var Form $form */
        $form = $payload['form'];

        if ($form->status === \App\Enums\FormStatus::Draft || $form->status === \App\Enums\FormStatus::Archived) {
            abort(404);
        }

        if ($form->trashed()) {
            abort(404);
        }

        $expired = $form->hasExpired();

        return Inertia::render('Public/Forms/Show', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'header_image_url' => $form->headerImageUrl(),
                'status' => $form->status->value,
                'status_label' => $form->status->label(),
                'accepts_responses' => ! $expired && $form->acceptsResponses(),
                'is_expired' => $expired,
                'project_name' => $form->project?->name,
                'formation_name' => $form->formation?->name,
                'confirmation_message' => $form->settings['confirmation_message'] ?? null,
                'show_progress' => (bool) ($form->settings['show_progress'] ?? true),
            ],
            'fields' => $form->fields->values()->map(fn ($f) => [
                'id' => $f->id,
                'type' => $f->type->value,
                'label' => $f->label,
                'help_text' => $f->help_text,
                'is_required' => $f->is_required,
                'options' => $f->options,
                'settings' => $f->settings,
                'learner_attribute' => $f->learner_attribute,
                'is_answerable' => $f->type->isAnswerable(),
            ]),
            'publicToken' => $publicToken,
            'phoneCountries' => PhoneCountries::all(),
        ]);
    }

    public function store(
        string $publicToken,
        SubmitPublicFormRequest $request,
        FormSchemaService $schemaService,
        SubmitPublicResponse $action,
    ): RedirectResponse {
        $payload = $schemaService->resolvePublishedSchema($publicToken);

        if (! $payload) {
            abort(404);
        }

        /** @var Form $form */
        $form = $payload['form']->fresh(['fields']);

        if (! $form->isPubliclyVisible()) {
            abort(404);
        }

        /** @var array<string, UploadedFile> $files */
        $files = [];
        foreach ($request->file('files', []) as $fieldId => $file) {
            if ($file instanceof UploadedFile) {
                $files[$fieldId] = $file;
            }
        }

        $action->execute(
            form: $form,
            answers: $request->input('answers', []),
            files: $files,
            honeypot: $request->input('website'),
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        $schemaService->forget($form);

        $request->session()->put($this->thanksSessionKey($publicToken), [
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return redirect()
            ->route('public.forms.thanks', $publicToken);
    }

    public function thanks(string $publicToken): Response|RedirectResponse
    {
        $form = Form::query()->where('public_token', $publicToken)->firstOrFail();

        $receipt = request()->session()->get($this->thanksSessionKey($publicToken));
        $expiresAt = is_array($receipt) ? (int) ($receipt['expires_at'] ?? 0) : 0;

        if ($expiresAt < now()->getTimestamp()) {
            request()->session()->forget($this->thanksSessionKey($publicToken));

            return redirect()->route('public.forms.show', $publicToken);
        }

        return Inertia::render('Public/Forms/Thanks', [
            'title' => $form->title,
            'message' => filled($form->settings['confirmation_message'] ?? null)
                ? $form->settings['confirmation_message']
                : 'Votre candidature a bien été envoyée.',
        ]);
    }

    private function thanksSessionKey(string $publicToken): string
    {
        return 'public_form_thanks.'.$publicToken;
    }
}
