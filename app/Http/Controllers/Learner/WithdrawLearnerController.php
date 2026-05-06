<?php

namespace App\Http\Controllers\Learner;

use App\Actions\WithdrawLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\WithdrawLearnerRequest;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;

class WithdrawLearnerController extends Controller
{
    public function __invoke(WithdrawLearnerRequest $request, Formation $formation, Learner $learner, WithdrawLearner $action): RedirectResponse
    {
        $action->execute($formation, $learner, $request->validated('notes'));

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', "{$learner->full_name} a été retiré(e) de la formation.");
    }
}
