<?php

namespace App\Http\Controllers;

use App\Enums\ContractTypeContext;
use App\Enums\LearnerDocument;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Trainer;
use App\Services\PrivateFileResponder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves the files that are not public: identity documents, CVs, contract scans and receipts. */
class ProtectedFileController extends Controller
{
    public function __construct(private readonly PrivateFileResponder $files) {}

    public function learnerDocument(Learner $learner, LearnerDocument $document): StreamedResponse
    {
        $this->authorize('view', $learner);

        return $this->files->respond($learner->{$document->pathColumn()}, $learner->{$document->nameColumn()});
    }

    public function insertionContract(Learner $learner, InsertionRecord $record, ContractTypeContext $kind): StreamedResponse
    {
        $this->authorize('view', $learner);
        abort_unless($record->learner_id === $learner->id, 404);

        return $this->files->respond(
            $record->{$kind->value.'_contract_path'},
            $record->{$kind->value.'_contract_original_name'},
        );
    }

    public function trainerCv(Trainer $trainer): StreamedResponse
    {
        $this->authorize('view', $trainer);

        return $this->files->respond($trainer->cv_path);
    }

    public function expenseAttachment(ExpenseAttachment $attachment): StreamedResponse
    {
        $this->authorize('viewAny', Expense::class);

        return $this->files->respond($attachment->file_path, $attachment->original_name);
    }
}
