<?php

namespace App\Http\Controllers\Form;

use App\Actions\Forms\DeselectCandidates;
use App\Actions\Forms\EnrollSelectedCandidates;
use App\Actions\Forms\RejectCandidates;
use App\Actions\Forms\SelectCandidates;
use App\Actions\Forms\ShortlistCandidates;
use App\Actions\Forms\UnrejectCandidates;
use App\Actions\Forms\UnshortlistCandidates;
use App\Enums\FormResponseStatus;
use App\Exports\FormResponsesExport;
use App\Exports\FormResponsesLearnerExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\SelectFormResponsesRequest;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormResponseController extends Controller
{
    public function index(Request $request, Form $form): Response
    {
        $this->authorize('viewResponses', $form);

        $form->load(['project:id,name', 'formation:id,name']);

        $responses = $form->responses()
            ->with([
                'answers.field',
                'learner:id,first_name,last_name,email',
                'reviewer:id,first_name,last_name',
            ])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('search'), function ($q, $search) {
                $q->where('email', 'like', '%'.$search.'%');
            })
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (FormResponse $response) use ($form) {
                return [
                    'id' => $response->id,
                    'email' => $response->email,
                    'status' => $response->status->value,
                    'status_label' => $response->status->label(),
                    'status_color' => $response->status->color(),
                    'submitted_at' => optional($response->submitted_at)->toIso8601String(),
                    'reviewed_at' => optional($response->reviewed_at)->toIso8601String(),
                    'review_note' => $response->review_note,
                    'reviewer' => $response->reviewer
                        ? trim($response->reviewer->first_name.' '.$response->reviewer->last_name)
                        : null,
                    'learner' => $response->learner,
                    'answers' => $response->answers->map(fn ($a) => [
                        'id' => $a->id,
                        'field_id' => $a->form_field_id,
                        'label' => $a->field?->label,
                        'learner_attribute' => $a->field?->learner_attribute,
                        'value' => $a->file_original_name
                            ?? (is_array($a->value_json) ? implode(', ', $a->value_json) : $a->value_text),
                        'is_file' => filled($a->file_path),
                        'is_image' => filled($a->file_path) && $this->isImageAnswer($a),
                        'download_url' => filled($a->file_path)
                            ? route('forms.responses.answers.download', [
                                'form' => $form->id,
                                'response' => $response->id,
                                'answer' => $a->id,
                            ])
                            : null,
                    ]),
                ];
            });

        return Inertia::render('Forms/Responses', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status->value,
                'project' => $form->project,
                'formation' => $form->formation,
            ],
            'responses' => $responses,
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ],
            'statuses' => collect(FormResponseStatus::cases())->map(fn (FormResponseStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
        ]);
    }

    public function select(SelectFormResponsesRequest $request, Form $form, SelectCandidates $action): RedirectResponse
    {
        $count = $action->execute(
            $form,
            $request->validated('response_ids'),
            $request->user(),
            $request->validated('review_note'),
        );

        return back()->with('success', "{$count} candidature(s) sélectionnée(s).");
    }

    public function shortlist(SelectFormResponsesRequest $request, Form $form, ShortlistCandidates $action): RedirectResponse
    {
        $count = $action->execute(
            $form,
            $request->validated('response_ids'),
            $request->user(),
            $request->validated('review_note'),
        );

        return back()->with('success', "{$count} candidature(s) présélectionnée(s).");
    }

    public function unshortlist(SelectFormResponsesRequest $request, Form $form, UnshortlistCandidates $action): RedirectResponse
    {
        $count = $action->execute($form, $request->validated('response_ids'));

        return back()->with('success', "{$count} candidature(s) retirée(s) de la présélection.");
    }

    public function deselect(SelectFormResponsesRequest $request, Form $form, DeselectCandidates $action): RedirectResponse
    {
        $count = $action->execute($form, $request->validated('response_ids'));

        return back()->with('success', "{$count} candidature(s) désélectionnée(s).");
    }

    public function reject(SelectFormResponsesRequest $request, Form $form, RejectCandidates $action): RedirectResponse
    {
        $count = $action->execute(
            $form,
            $request->validated('response_ids'),
            $request->user(),
            $request->validated('review_note'),
        );

        return back()->with('success', "{$count} candidature(s) refusée(s).");
    }

    public function unreject(SelectFormResponsesRequest $request, Form $form, UnrejectCandidates $action): RedirectResponse
    {
        $count = $action->execute($form, $request->validated('response_ids'));

        return back()->with('success', "{$count} candidature(s) rétablie(s) en « Soumise ».");
    }

    public function enroll(SelectFormResponsesRequest $request, Form $form, EnrollSelectedCandidates $action): RedirectResponse
    {
        $result = $action->execute(
            $form,
            $request->validated('response_ids'),
            $request->user(),
        );

        $message = "{$result['enrolled']} candidat(s) inscrit(s) à la formation.";
        if ($result['errors'] !== []) {
            $message .= ' Attention : '.implode(' ', $result['errors']);
        }

        return back()->with($result['errors'] !== [] ? 'warning' : 'success', $message);
    }

    public function export(Form $form): BinaryFileResponse
    {
        $this->authorize('export', $form);

        $filename = 'reponses-'.str($form->title)->slug().'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new FormResponsesExport($form), $filename);
    }

    public function exportForImport(Request $request, Form $form): BinaryFileResponse
    {
        $this->authorize('export', $form);

        $status = $request->input('status');
        $allowed = collect(FormResponseStatus::cases())->map->value->all();
        if ($status && ! in_array($status, $allowed, true)) {
            $status = null;
        }

        $filename = 'import-apprenants-'.str($form->title)->slug().'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new FormResponsesLearnerExport($form, $status), $filename);
    }

    public function exportPdf(Form $form, PdfService $pdfService): HttpResponse
    {
        $this->authorize('export', $form);

        return $pdfService->formResponses($form);
    }

    public function downloadAnswer(
        Form $form,
        FormResponse $response,
        FormAnswer $answer,
    ): StreamedResponse|BinaryFileResponse {
        $this->authorize('viewResponses', $form);

        return $this->streamAnswerFile($form, $response, $answer);
    }

    public function signedDownloadAnswer(
        Form $form,
        FormResponse $response,
        FormAnswer $answer,
    ): StreamedResponse|BinaryFileResponse {
        return $this->streamAnswerFile($form, $response, $answer);
    }

    private function streamAnswerFile(
        Form $form,
        FormResponse $response,
        FormAnswer $answer,
    ): StreamedResponse|BinaryFileResponse {
        abort_unless($response->form_id === $form->id, 404);
        abort_unless($answer->form_response_id === $response->id, 404);
        abort_unless(filled($answer->file_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($answer->file_path), 404);

        return $disk->download(
            $answer->file_path,
            $answer->file_original_name ?: basename($answer->file_path),
        );
    }

    private function isImageAnswer(FormAnswer $answer): bool
    {
        $name = strtolower((string) ($answer->file_original_name ?: $answer->file_path));

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)$/', $name);
    }
}
