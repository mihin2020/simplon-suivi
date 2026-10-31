<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page { size: A4 landscape; margin: 11mm 12mm 14mm 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1a1a2e; }

    .page-block + .page-block { page-break-before: always; }

    /* ── En-tête ── */
    .header { width: 100%; border-bottom: 2px solid #E5004C; padding-bottom: 5px; margin-bottom: 8px; }
    .header table { width: 100%; border-collapse: collapse; }
    .header td { border: none; padding: 0; vertical-align: top; }
    .h-right { text-align: right; }
    .project { font-size: 7pt; font-weight: bold; color: #E5004C; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px; }
    .formation-name { font-size: 12pt; font-weight: bold; }
    .sheet-title { font-size: 9.5pt; font-weight: bold; color: #515f74; text-transform: uppercase; letter-spacing: 0.06em; }
    .week-badge { display: inline-block; margin-top: 3px; padding: 3px 10px; background: #E5004C; color: #fff; font-size: 9pt; font-weight: bold; border-radius: 3px; }
    .week-sub { font-size: 7.5pt; color: #515f74; margin-top: 3px; }

    /* ── Tableaux ── */
    table.att-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.att-table thead tr { background: #1a1a2e; }
    table.att-table th { padding: 5px 3px; font-size: 7.5pt; font-weight: bold; color: #fff; text-align: center; border: 1px solid #1a1a2e; }
    table.att-table th.th-name { text-align: left; padding-left: 7px; }
    table.att-table th.th-total { background: #515f74; border-color: #515f74; }
    .th-day-date { font-size: 8pt; font-weight: bold; }
    .th-day-weekday { font-size: 6.5pt; color: #adb5bd; text-transform: capitalize; margin-top: 1px; }

    table.att-table td { border: 1px solid #c9d0d6; vertical-align: middle; height: 22px; text-align: center; }
    table.att-table td.td-num { font-size: 7.5pt; color: #9aaabb; font-weight: bold; }
    table.att-table td.td-name { padding: 3px 6px; font-size: 8pt; font-weight: bold; text-align: left; }
    table.att-table td.td-total { font-size: 8pt; font-weight: bold; background: #f3f5f7; }
    table.att-table td.td-off { background: #eceff1; color: #b0b8c0; font-size: 7pt; }
    table.att-table tr:nth-child(even) td { background: #fafbfc; }
    table.att-table tr:nth-child(even) td.td-total { background: #f3f5f7; }
    table.att-table tr:nth-child(even) td.td-off { background: #eceff1; }
    table.att-table tr.row-alert td { background: #fee2e2 !important; }
    table.att-table tr.row-alert td.td-name { color: #991b1b; }
    table.att-table tfoot td { background: #e8ebee !important; font-weight: bold; font-size: 7.5pt; }

    .code-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8pt; font-weight: bold; }
    .code-P  { background: #d1fae5; color: #065f46; }
    .code-AJ { background: #dbeafe; color: #1e40af; }
    .code-AN { background: #fee2e2; color: #991b1b; }
    .code-RJ { background: #fef9c3; color: #854d0e; }
    .code-RN { background: #ffedd5; color: #9a3412; }
    .code-nd { color: #d0d5dc; font-size: 8pt; }
    .note-mark { color: #1e40af; font-weight: bold; font-size: 8pt; }

    .section-title { font-size: 9pt; font-weight: bold; color: #1a1a2e; margin: 12px 0 5px; text-transform: uppercase; letter-spacing: 0.05em; }
    table.reasons td.td-reason { text-align: left; padding: 3px 6px; }

    /* ── Légende ── */
    .legend { margin-top: 8px; padding-top: 6px; border-top: 1px solid #e0e3e5; font-size: 7.5pt; color: #515f74; text-align: center; }
    .legend .lg-title { font-weight: bold; color: #1a1a2e; margin-right: 6px; }
    .legend .lg-item { display: inline-block; margin: 0 6px; }

    .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 6.5pt; color: #9aaabb; text-align: center; border-top: 1px solid #e8eaec; padding-top: 3px; }
</style>
</head>
<body>

@use('Illuminate\Support\Carbon')

@php
    $rows = $recap['rows'];
    $weeks = $recap['weeks'];
    $codeLabels = ['P' => 'Présent', 'AJ' => 'Absence justifiée', 'AN' => 'Absence non justifiée', 'RJ' => 'Retard justifié', 'RN' => 'Retard non justifié'];
    $totalDays = count($recap['days']);

    // Week totals of one learner: presents (P + late), absences (AJ + AN).
    $weekTotals = function (array $row, array $days): array {
        $present = 0; $absent = 0;
        foreach ($days as $day) {
            $code = $row['days'][$day]['code'] ?? null;
            if (in_array($code, ['P', 'RJ', 'RN'], true)) { $present++; }
            if (in_array($code, ['AJ', 'AN'], true)) { $absent++; }
        }
        return [$present, $absent];
    };
@endphp

@if($totalDays === 0)
    <div class="page-block">
        <div class="header"><table><tr>
            <td style="width:60%"><div class="project">{{ $formation->project->name }}</div><div class="formation-name">{{ $formation->name }}</div></td>
            <td class="h-right"><div class="sheet-title">Récapitulatif des présences</div></td>
        </tr></table></div>
        <p style="margin-top:20px">Aucun jour de cours sur cette période.</p>
    </div>
@endif

{{-- ═════ Une fiche par semaine ═════ --}}
@foreach($weeks as $week)
    @php
        $start = Carbon::parse($week['start'])->locale('fr');
        $end   = Carbon::parse($week['end'])->locale('fr');
        $nbDays = count($week['days']);
        $dayWidth = round((100 - 4 - 27 - 14) / max($nbDays, 1), 2);
    @endphp
    <div class="page-block">
        <div class="header">
            <table><tr>
                <td style="width:60%">
                    <div class="project">{{ $formation->project->name }}</div>
                    <div class="formation-name">{{ $formation->name }}</div>
                </td>
                <td class="h-right" style="width:40%">
                    <div class="sheet-title">Fiche de présence hebdomadaire</div>
                    <span class="week-badge">
                        {{ $week['number'] ? 'Semaine '.$week['number'].' · ' : '' }}du {{ $start->isoFormat('D MMM') }} au {{ $end->isoFormat('D MMM YYYY') }}
                    </span>
                    <div class="week-sub">{{ $nbDays }} jour(s) de cours</div>
                </td>
            </tr></table>
        </div>

        <table class="att-table">
            <colgroup>
                <col style="width:4%">
                <col style="width:27%">
                @foreach($week['days'] as $day)<col style="width:{{ $dayWidth }}%">@endforeach
                <col style="width:7%">
                <col style="width:7%">
            </colgroup>
            <thead>
                <tr>
                    <th>N°</th>
                    <th class="th-name">Nom &amp; Prénom</th>
                    @foreach($week['days'] as $day)
                        @php $d = Carbon::parse($day)->locale('fr'); @endphp
                        <th>
                            <div class="th-day-weekday">{{ $d->isoFormat('dddd') }}</div>
                            <div class="th-day-date">{{ $d->isoFormat('D MMM') }}</div>
                        </th>
                    @endforeach
                    <th class="th-total">Prés.</th>
                    <th class="th-total">Abs.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $idx => $row)
                    @php [$presentWeek, $absentWeek] = $weekTotals($row, $week['days']); @endphp
                    <tr @class(['row-alert' => $absenceAlertThreshold && $row['absences'] >= $absenceAlertThreshold])>
                        <td class="td-num">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="td-name">{{ $row['name'] }}</td>
                        @foreach($week['days'] as $day)
                            @php $cell = $row['days'][$day]; @endphp
                            @if(! $cell['enrolled'])
                                <td class="td-off">N/A</td>
                            @else
                                <td>
                                    @if($cell['code'])
                                        <span class="code-badge code-{{ $cell['code'] }}">{{ $cell['code'] }}</span>@if($cell['comment'])<span class="note-mark">*</span>@endif
                                    @else
                                        <span class="code-nd">-</span>
                                    @endif
                                </td>
                            @endif
                        @endforeach
                        <td class="td-total">{{ $presentWeek }}</td>
                        <td class="td-total">{{ $absentWeek }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td style="text-align:left;padding-left:6px">Présents / effectif</td>
                    @foreach($week['days'] as $day)
                        <td>{{ $recap['dayStats'][$day]['present'] }}/{{ $recap['dayStats'][$day]['expected'] }}</td>
                    @endforeach
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="legend">
            <span class="lg-title">Codes :</span>
            @foreach($codeLabels as $code => $label)
                <span class="lg-item"><span class="code-badge code-{{ $code }}">{{ $code }}</span> {{ $label }}</span>
            @endforeach
            <span class="lg-item"><span class="note-mark">*</span> motif en fin de document</span>
            <span class="lg-item"><span class="code-nd">N/A</span> pas encore inscrit</span>
            <span class="lg-item"><span class="code-nd">-</span> non saisi</span>
        </div>
    </div>
@endforeach

{{-- ═════ Synthèse de la période ═════ --}}
@if($totalDays > 0)
    <div class="page-block">
        <div class="header">
            <table><tr>
                <td style="width:60%">
                    <div class="project">{{ $formation->project->name }}</div>
                    <div class="formation-name">{{ $formation->name }}</div>
                </td>
                <td class="h-right" style="width:40%">
                    <div class="sheet-title">Synthèse des présences</div>
                    <span class="week-badge">{{ $periodLabel }}</span>
                    <div class="week-sub">{{ $totalDays }} jour(s) de cours</div>
                </td>
            </tr></table>
        </div>

        <table class="att-table">
            <colgroup>
                <col style="width:4%"><col style="width:30%">
                <col style="width:8%"><col style="width:8%">
                <col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%">
                <col style="width:8%">
            </colgroup>
            <thead>
                <tr>
                    <th>N°</th><th class="th-name">Nom &amp; Prénom</th>
                    <th>Jours</th><th>Saisis</th>
                    <th>P</th><th>AJ</th><th>AN</th><th>RJ</th><th>RN</th>
                    <th class="th-total">Taux</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $idx => $row)
                    <tr @class(['row-alert' => $absenceAlertThreshold && $row['absences'] >= $absenceAlertThreshold])>
                        <td class="td-num">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="td-name">{{ $row['name'] }}</td>
                        <td>{{ $row['expected'] }}</td>
                        <td>{{ $row['recorded'] }}</td>
                        @foreach(['P', 'AJ', 'AN', 'RJ', 'RN'] as $code)
                            <td>{{ $row['counts'][$code] ?: '' }}</td>
                        @endforeach
                        <td class="td-total">{{ $row['rate'] !== null ? $row['rate'].' %' : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="week-sub" style="margin-top:4px">
            « Jours » = jours de cours depuis l'inscription de l'apprenant. Les absences (AJ + AN) sont toutes comptées comme absences, justifiées ou non.
            @if($absenceAlertThreshold) Ligne en rouge à partir de {{ $absenceAlertThreshold }} absence(s). @endif
        </p>

        @if(count($recap['reasons']) > 0)
            <div class="section-title">Motifs renseignés</div>
            <table class="att-table reasons">
                <colgroup><col style="width:14%"><col style="width:28%"><col style="width:8%"><col style="width:50%"></colgroup>
                <thead><tr><th>Date</th><th class="th-name">Apprenant</th><th>Code</th><th class="th-name">Motif</th></tr></thead>
                <tbody>
                    @foreach($recap['reasons'] as $reason)
                        <tr>
                            <td>{{ Carbon::parse($reason['date'])->locale('fr')->isoFormat('ddd D MMM') }}</td>
                            <td class="td-name">{{ $reason['learner'] }}</td>
                            <td><span class="code-badge code-{{ $reason['code'] }}">{{ $reason['code'] }}</span></td>
                            <td class="td-reason">{{ $reason['reason'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

<div class="footer">
    {{ $formation->project->name }} · {{ $formation->name }} · {{ count($rows) }} apprenant(s) · Généré le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
