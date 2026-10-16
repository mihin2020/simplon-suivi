<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfService;
use Illuminate\Http\Response;

class ContractPdfController extends Controller
{
    public function __construct(private readonly ContractPdfService $pdf) {}

    public function stream(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        return $this->pdf->stream($contract);
    }

    public function download(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        return $this->pdf->download($contract);
    }
}
