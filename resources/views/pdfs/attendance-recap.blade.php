<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page {
        size: A4 landscape;
        margin: 11mm 12mm 14mm 12mm;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 8.5pt;
        color: #1a1a2e;
    }

    /* Pagination entre tranches de dates */
    .page-block { page-break-after: always; }
    .page-block:last-child { page-break-after: avoid; }

    /* ── En-tête ── */
    .header {
        width: 100%;
        border-bottom: 2px solid #E5004C;
        padding-bottom: 5px;
        margin-bottom: 8px;
    }
    .header table { width: 100%; border-collapse: collapse; }
    .header td { border: none; padding: 0; vertical-align: top; }
    .h-right { text-align: right; }

    .project {
        font-size: 7pt;
        font-weight: bold;
        color: #E5004C;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 2px;
    }
    .formation-name {
        font-size: 12pt;
        font-weight: bold;
        color: #1a1a2e;
    }
    .sheet-title {
        font-size: 9.5pt;
        font-weight: bold;
        color: #515f74;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .period-badge {
        display: inline-block;
        margin-top: 3px;
        padding: 2px 8px;
        background: #E5004C;
        color: #fff;
        font-size: 8pt;
        font-weight: bold;
        border-radius: 3px;
    }
    .page-info {
        font-size: 7pt;
        color: #9aaabb;
        margin-top: 3px;
    }

    /* ── Tableau ── */
    table.att-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    table.att-table thead tr { background: #1a1a2e; }
    table.att-table th {
        padding: 5px 2px;
        font-size: 7.5pt;
        font-weight: bold;
        color: #fff;
        text-align: center;
        border: 1px solid #1a1a2e;
        word-wrap: break-word;
    }
    table.att-table th.th-name {
        text-align: left;
        padding-left: 7px;
    }
    .th-day-date {
        font-size: 7.5pt;
        font-weight: bold;
        color: #fff;
        line-height: 1.1;
    }
    .th-day-weekday {
        font-size: 6pt;
        color: #adb5bd;
        text-transform: capitalize;
        line-height: 1.1;
        margin-top: 1px;
    }

    table.att-table td {
        border: 1px solid #c9d0d6;
        vertical-align: top;
    }
    table.att-table td.td-num {
        padding: 4px 2px;
        text-align: center;
        font-size: 7.5pt;
        color: #9aaabb;
        font-weight: bold;
        vertical-align: middle;
    }
    table.att-table td.td-name {
        padding: 4px 6px;
        font-size: 8pt;
        font-weight: 600;
        color: #1a1a2e;
        vertical-align: middle;
    }
    table.att-table td.td-day {
        padding: 3px 1px 0;
        text-align: center;
        height: 30px;
    }
    table.att-table tr:nth-child(even) td { background: #fafbfc; }

    .code-badge {
        display: inline-block;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 7.5pt;
        font-weight: bold;
        margin-bottom: 2px;
    }
    .code-P  { background: #d1fae5; color: #065f46; }
    .code-AJ { background: #dbeafe; color: #1e40af; }
    .code-AN { background: #fee2e2; color: #991b1b; }
    .code-RJ { background: #fef9c3; color: #854d0e; }
    .code-RN { background: #ffedd5; color: #9a3412; }
    .code-nd { color: #d0d5dc; font-size: 8pt; }

    .sign-line {
        display: block;
        border-top: 1px dotted #b0b8c0;
        margin-top: 3px;
        height: 12px;
    }

    /* ── Légende horizontale ── */
    .legend {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid #e0e3e5;
        font-size: 7.5pt;
        color: #515f74;
        text-align: center;
    }
    .legend .lg-title {
        font-weight: bold;
        color: #1a1a2e;
        margin-right: 6px;
    }
    .legend .lg-item {
        display: inline-block;
        margin: 0 6px;
    }

    /* ── Pied de page ── */
    .footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        font-size: 6.5pt;
        color: #9aaabb;
        text-align: center;
        border-top: 1px solid #e8eaec;
        padding-top: 3px;
    }
</style>
</head>
<body>

@php
    $totalChunks = $dateChunks->count();
    $chunkIndex  = 0;

    $frenchMonths = [
        1 => 'jan', 2 => 'fév', 3 => 'mar', 4 => 'avr',
        5 => 'mai', 6 => 'juin', 7 => 'juil', 8 => 'août',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'déc',
    ];
    $frenchWeekdays = [
        'Monday' => 'Lun', 'Tuesday' => 'Mar', 'Wednesday' => 'Mer',
        'Thursday' => 'Jeu', 'Friday' => 'Ven', 'Saturday' => 'Sam', 'Sunday' => 'Dim',
    ];

    // Calcul largeur dynamique selon nb de jours dans la page
@endphp

@foreach($dateChunks as $chunk)
    @php
        $chunkIndex++;
        $chunkDates = $chunk->values();
        $nbDays     = $chunkDates->count();
        $firstDate  = \Carbon\Carbon::parse($chunkDates->first());
        $lastDate   = \Carbon\Carbon::parse($chunkDates->last());

        // Répartition : 4% pour N°, 24% pour Nom, le reste pour les jours
        $dayWidth = round((100 - 4 - 24) / max($nbDays, 1), 2);
    @endphp

    <div class="page-block">

        <!-- En-tête -->
        <div class="header">
            <table>
                <tr>
                    <td style="width:60%">
                        <div class="project">{{ $formation->project->name }}</div>
                        <div class="formation-name">{{ $formation->name }}</div>
                    </td>
                    <td class="h-right" style="width:40%">
                        <div class="sheet-title">Récapitulatif des présences</div>
                        <span class="period-badge">
                            {{ $firstDate->format('d/m/Y') }} → {{ $lastDate->format('d/m/Y') }}
                        </span>
                        <div class="page-info">
                            Feuille {{ $chunkIndex }}/{{ $totalChunks }} · {{ $totalDates }} jours de cours
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tableau -->
        <table class="att-table">
            <colgroup>
                <col style="width:4%">
                <col style="width:24%">
                @foreach($chunkDates as $day)
                    <col style="width:{{ $dayWidth }}%">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th>N°</th>
                    <th class="th-name">Nom &amp; Prénom</th>
                    @foreach($chunkDates as $day)
                        @php $d = \Carbon\Carbon::parse($day); @endphp
                        <th>
                            <div class="th-day-date">{{ $d->format('d') }}/{{ $frenchMonths[$d->month] }}</div>
                            <div class="th-day-weekday">{{ $frenchWeekdays[$d->format('l')] }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $idx => $row)
                    <tr>
                        <td class="td-num">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="td-name">{{ $row['full_name'] }}</td>
                        @foreach($chunkDates as $day)
                            @php $code = $row['days'][$day] ?? null; @endphp
                            <td class="td-day">
                                @if($code)
                                    <span class="code-badge code-{{ $code }}">{{ $code }}</span>
                                @else
                                    <span class="code-nd">—</span>
                                @endif
                                <span class="sign-line"></span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Légende horizontale -->
        <div class="legend">
            <span class="lg-title">Codes :</span>
            <span class="lg-item"><span class="code-badge code-P">P</span> Présent</span>
            <span class="lg-item"><span class="code-badge code-AJ">AJ</span> Absence justifiée</span>
            <span class="lg-item"><span class="code-badge code-AN">AN</span> Absence non justifiée</span>
            <span class="lg-item"><span class="code-badge code-RJ">RJ</span> Retard justifié</span>
            <span class="lg-item"><span class="code-badge code-RN">RN</span> Retard non justifié</span>
        </div>

    </div>
@endforeach

<!-- Pied de page (apparaît sur chaque page DomPDF en mode fixed) -->
<div class="footer">
    {{ $formation->project->name }} — {{ $formation->name }} · {{ $rows->count() }} apprenant(s) · Généré le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
