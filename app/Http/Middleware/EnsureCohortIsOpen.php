<?php

namespace App\Http\Middleware;

use App\Enums\CohortStatus;
use App\Models\Cohort;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A closed cohort is read-only: refuse the change before anything is validated or written.
 *
 * Usage: `cohort.open` or `cohort.open:schedule` / `cohort.open:payment` for a message that names the action.
 */
class EnsureCohortIsOpen
{
    private const MESSAGES = [
        'default' => 'Impossible de modifier une cohorte clôturée.',
        'schedule' => 'Impossible de planifier des paiements sur une cohorte clôturée.',
        'payment' => "Impossible d'enregistrer un versement sur une cohorte clôturée.",
    ];

    public function handle(Request $request, Closure $next, string $action = 'default'): Response
    {
        $cohort = $request->route('cohort');

        if ($cohort instanceof Cohort && $cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => self::MESSAGES[$action] ?? self::MESSAGES['default']]);
        }

        return $next($request);
    }
}
