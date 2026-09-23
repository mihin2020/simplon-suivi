<?php

namespace App\Http\Controllers\Form;

use App\Actions\Forms\ArchiveForm;
use App\Actions\Forms\CloseForm;
use App\Actions\Forms\CreateForm;
use App\Actions\Forms\DuplicateForm;
use App\Actions\Forms\LockForm;
use App\Actions\Forms\PublishForm;
use App\Actions\Forms\ShortenFormPublicLink;
use App\Actions\Forms\SyncFormFields;
use App\Actions\Forms\UnarchiveForm;
use App\Actions\Forms\UpdateForm;
use App\Actions\Forms\UpdateFormIdentity;
use App\Actions\Forms\UploadFormHeaderImage;
use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\StoreFormRequest;
use App\Http\Requests\Form\SyncFormFieldsRequest;
use App\Http\Requests\Form\UpdateFormIdentityRequest;
use App\Http\Requests\Form\UpdateFormRequest;
use App\Http\Requests\Form\UploadFormHeaderImageRequest;
use App\Models\Form;
use App\Models\Project;
use App\Services\Forms\FormStatsService;
use App\Support\LearnerFormAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Form::class);

        $status = $request->input('status');

        $forms = Form::query()
            ->with(['project:id,name', 'formation:id,name', 'creator:id,first_name,last_name'])
            ->withCount('responses')
            ->when(
                $status === FormStatus::Archived->value,
                fn ($q) => $q->where('status', FormStatus::Archived),
                fn ($q) => $status
                    ? $q->where('status', $status)
                    : $q->where('status', '!=', FormStatus::Archived)
            )
            ->when($request->input('project_id'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->input('formation_id'), fn ($q, $id) => $q->where('formation_id', $id))
            ->when($request->input('search'), function ($q, $search) {
                $q->where('title', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $projects = Project::query()
            ->with(['formations:id,project_id,name'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Forms/Index', [
            'forms' => $forms,
            'filters' => [
                'status' => $request->input('status'),
                'project_id' => $request->input('project_id'),
                'formation_id' => $request->input('formation_id'),
                'search' => $request->input('search'),
            ],
            'statuses' => collect(FormStatus::cases())->map(fn (FormStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Form::class);

        return Inertia::render('Forms/Create', [
            'projects' => Project::query()
                ->with(['formations:id,project_id,name'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreFormRequest $request, CreateForm $action): RedirectResponse
    {
        $form = $action->execute($request->user(), $request->validated());

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Formulaire créé. Configurez les questions puis publiez-le.');
    }

    public function edit(Form $form): Response
    {
        $this->authorize('update', $form);

        $form->load(['fields', 'project:id,name', 'formation:id,name,project_id']);
        $form->loadCount('responses');

        return Inertia::render('Forms/Edit', [
            'form' => $form,
            'headerImageUrl' => $form->headerImageUrl(),
            'publicUrl' => $form->status === FormStatus::Published || $form->status === FormStatus::Closed || $form->status === FormStatus::Locked
                ? $form->publicUrl()
                : null,
            'canShortenLink' => $form->hasLongPublicToken(),
            'projects' => Project::query()
                ->with(['formations:id,project_id,name'])
                ->orderBy('name')
                ->get(['id', 'name']),
            'fieldTypes' => collect(FormFieldType::builderTypes())->map(fn (FormFieldType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'icon' => $t->icon(),
                'requires_options' => $t->requiresOptions(),
            ]),
            'learnerAttributes' => collect(LearnerFormAttributes::all())->map(fn (array $a) => [
                'key' => $a['key'],
                'label' => $a['label'],
                'type' => $a['type']->value,
                'required' => $a['required'],
                'import_column' => $a['import_column'],
            ]),
            'statuses' => collect(FormStatus::cases())->map(fn (FormStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form, UpdateForm $action): RedirectResponse
    {
        $action->execute(
            $form,
            $request->validated(),
            $request->file('header_image'),
        );

        return back()->with('success', 'Formulaire mis à jour.');
    }

    public function updateIdentity(
        UpdateFormIdentityRequest $request,
        Form $form,
        UpdateFormIdentity $action,
    ): JsonResponse {
        $action->execute($form, $request->validated());

        session()->flash('success', 'Titre et description enregistrés.');

        return response()->json([
            'title' => $form->fresh()->title,
            'description' => $form->fresh()->description,
            'message' => 'Titre et description enregistrés.',
        ]);
    }

    public function syncFields(SyncFormFieldsRequest $request, Form $form, SyncFormFields $action): RedirectResponse
    {
        $action->execute($form, $request->validated('fields'));

        return back()->with('success', 'Questions enregistrées.');
    }

    public function publish(Form $form, PublishForm $action): RedirectResponse
    {
        $this->authorize('publish', $form);
        $action->execute($form);

        return back()->with('success', 'Formulaire publié. Le lien public est actif.');
    }

    public function close(Form $form, CloseForm $action): RedirectResponse
    {
        $this->authorize('publish', $form);
        $action->execute($form);

        return back()->with('success', 'Formulaire fermé. Les candidatures ne sont plus acceptées.');
    }

    public function lock(Form $form, LockForm $action): RedirectResponse
    {
        $this->authorize('publish', $form);
        $action->execute($form);

        return back()->with('success', 'Formulaire verrouillé.');
    }

    public function stats(Form $form, FormStatsService $statsService): Response
    {
        $this->authorize('viewStats', $form);

        $form->load(['project:id,name', 'formation:id,name']);

        return Inertia::render('Forms/Stats', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status->value,
                'status_label' => $form->status->label(),
                'project' => $form->project,
                'formation' => $form->formation,
            ],
            'stats' => $statsService->forForm($form),
        ]);
    }

    public function destroy(Form $form, ArchiveForm $action): RedirectResponse
    {
        $this->authorize('delete', $form);
        $action->execute($form);

        return redirect()
            ->route('forms.index', ['status' => FormStatus::Archived->value])
            ->with('success', 'Formulaire archivé.');
    }

    public function unarchive(Form $form, UnarchiveForm $action): RedirectResponse
    {
        $this->authorize('delete', $form);
        $action->execute($form);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Formulaire désarchivé. Il est repassé en brouillon.');
    }

    public function duplicate(Form $form, DuplicateForm $action): RedirectResponse
    {
        $this->authorize('create', Form::class);

        $copy = $action->execute($form, request()->user());

        return redirect()
            ->route('forms.edit', $copy)
            ->with('success', 'Formulaire dupliqué. La copie est en brouillon.');
    }

    public function uploadHeader(
        UploadFormHeaderImageRequest $request,
        Form $form,
        UploadFormHeaderImage $action,
    ): JsonResponse {
        $form = $action->execute($form, $request->file('header_image'));

        return response()->json([
            'header_image_url' => $form->headerImageUrl(),
            'message' => 'Image d’en-tête enregistrée.',
        ]);
    }

    public function removeHeader(Form $form, UploadFormHeaderImage $action): JsonResponse
    {
        $this->authorize('update', $form);

        $action->remove($form);

        return response()->json([
            'header_image_url' => null,
            'message' => 'Image d’en-tête supprimée.',
        ]);
    }

    public function shortenLink(Request $request, Form $form, ShortenFormPublicLink $action): RedirectResponse|JsonResponse
    {
        $this->authorize('publish', $form);

        $form = $action->execute($form);
        $publicUrl = $form->status === FormStatus::Published
            || $form->status === FormStatus::Closed
            || $form->status === FormStatus::Locked
            ? $form->publicUrl()
            : null;

        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'public_url' => $publicUrl,
                'can_shorten_link' => $form->hasLongPublicToken(),
                'message' => 'Lien public raccourci.',
            ]);
        }

        return back()->with('success', 'Lien public raccourci. Utilisez le nouveau lien affiché.');
    }
}
