<?php

namespace App\Http\Middleware;

use App\Enums\CohortStatus;
use App\Models\Cohort;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A closed cohort is read-only: refuse the change before anything is validated or written. */
class EnsureCohortIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $cohort = $request->route('cohort');

        if ($cohort instanceof Cohort && $cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de modifier une cohorte clôturée.']);
        }

        return $next($request);
    }
}
