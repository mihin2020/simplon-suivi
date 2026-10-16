<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractProject;
use Illuminate\Support\Facades\DB;

class DeleteContractProject
{
    /**
     * Delete a project together with every contract it holds (soft deletes:
     * references and archived PDFs stay recoverable). All or nothing.
     *
     * @return list<string> references of the deleted contracts
     */
    public function execute(ContractProject $project): array
    {
        return DB::transaction(function () use ($project) {
            $contracts = $project->contracts()->get();
            $contracts->each(fn (Contract $contract) => $contract->delete());
            $project->delete();

            return $contracts->pluck('reference')->values()->all();
        });
    }
}
