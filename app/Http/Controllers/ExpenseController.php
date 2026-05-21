<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\Formation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Formation $formation): Response
    {
        $formation->load('project:id,name,budget');

        $expenses = Expense::where('formation_id', $formation->id)
            ->with(['attachments', 'creator:id,first_name,last_name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->get();

        $totalSpent = (int) $expenses->sum('amount');

        // Total dépensé sur tout le projet (toutes les formations)
        $projectTotalSpent = (int) Expense::whereIn(
            'formation_id',
            Formation::where('project_id', $formation->project_id)->pluck('id')
        )->sum('amount');

        return Inertia::render('Formations/Expenses', [
            'formation' => [
                'id'         => $formation->id,
                'name'       => $formation->name,
                'project'    => $formation->project,
            ],
            'expenses'           => $expenses,
            'totalSpent'         => $totalSpent,
            'projectTotalSpent'  => $projectTotalSpent,
            'projectBudget'      => $formation->project->budget ?? 0,
        ]);
    }

    public function store(StoreExpenseRequest $request, Formation $formation): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('files', []);
        unset($data['files']);

        DB::transaction(function () use ($data, $files, $formation, $request) {
            $expense = Expense::create([
                'formation_id' => $formation->id,
                'title'        => $data['title'],
                'amount'       => $data['amount'],
                'expense_date' => $data['expense_date'],
                'spent_by'     => $data['spent_by'],
                'description'  => $data['description'] ?? null,
                'created_by'   => $request->user()->id,
            ]);

            $this->storeAttachments($expense, $files, $formation);
        });

        return back()->with('success', 'Dépense ajoutée avec succès.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('files', []);
        unset($data['files']);

        DB::transaction(function () use ($data, $files, $expense) {
            $expense->update([
                'title'        => $data['title'],
                'amount'       => $data['amount'],
                'expense_date' => $data['expense_date'],
                'spent_by'     => $data['spent_by'],
                'description'  => $data['description'] ?? null,
            ]);

            $this->storeAttachments($expense, $files, $expense->formation);
        });

        return back()->with('success', 'Dépense mise à jour.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        // Soft delete (les fichiers restent en cas de restauration)
        $expense->delete();

        return back()->with('success', 'Dépense supprimée.');
    }

    public function destroyAttachment(ExpenseAttachment $attachment): RedirectResponse
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Pièce justificative supprimée.');
    }

    /**
     * Stocker les fichiers uploadés
     */
    private function storeAttachments(Expense $expense, array $files, ?Formation $formation): void
    {
        $formationId = $formation?->id ?? $expense->formation_id;

        foreach ($files as $file) {
            $path = $file->store("expenses/{$formationId}", 'public');

            ExpenseAttachment::create([
                'expense_id'    => $expense->id,
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);
        }
    }
}
