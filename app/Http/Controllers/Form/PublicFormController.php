<?php

namespace App\Http\Controllers\Form;

use App\Actions\Forms\SubmitPublicResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\SubmitPublicFormRequest;
use App\Models\Form;
use App\Services\Forms\FormSchemaService;
use App\Services\Forms\PublicFormPresenter;
use App\Support\PhoneCountries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    public function show(string $publicToken, FormSchemaService $schemaService, PublicFormPresenter $presenter): Response
    {
        /** @var Form|null $form */
        $form = $schemaService->resolvePublishedSchema($publicToken)['form'] ?? null;

        abort_unless($form?->hasPublicPage(), 404);

        return Inertia::render('Public/Forms/Show', [
            'form' => $presenter->form($form),
            'fields' => $presenter->fields($form),
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
