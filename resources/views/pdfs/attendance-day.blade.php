<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page {
        size: A4 landscape;
        margin: 12mm 14mm;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9pt;
        color: #1a1a2e;
    }

    /* ── En-tête ── */
    .header {
        width: 100%;
        border-bottom: 2px solid #E5004C;
        padding-bottom: 6px;
        margin-bottom: 9px;
    }
    .header table { width: 100%; border-collapse: collapse; }
    .header td { border: none; padding: 0; vertical-align: top; }
    .header .h-left { text-align: left; }
    .header .h-right { text-align: right; }
    .project {
        font-size: 7.5pt;
        font-weight: bold;
        color: #E5004C;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 2px;
    }
    .formation-name {
        font-size: 13pt;
        font-weight: bold;
        color: #1a1a2e;
    }
    .sheet-title {
        font-size: 10pt;
        font-weight: bold;
        color: #515f74;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .date-badge {
        display: inline-block;
        margin-top: 4px;
        padding: 3px 10px;
        background: #E5004C;
        color: #fff;
        font-size: 9pt;
        font-weight: bold;
        border-radius: 3px;
    }

    /* ── Tableau présences ── */
    table.att-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    table.att-table thead tr { background: #1a1a2e; }
    table.att-table th {
        padding: 6px 6px;
        font-size: 8pt;
        font-weight: bold;
        color: #fff;
        text-align: center;
        border: 1px solid #1a1a2e;
    }
    table.att-table th.th-name { text-align: left; padding-left: 8px; }
    table.att-table td {
        border: 1px solid #c9d0d6;
        padding: 5px 6px;
        vertical-align: middle;
    }
    table.att-table td.td-num {
        text-align: center;
        font-size: 8pt;
        color: #9aaabb;
        font-weight: bold;
    }
    table.att-table td.td-name {
        font-size: 9pt;
        font-weight: 600;
        color: #1a1a2e;
    }
    table.att-table td.td-code {
        text-align: center;
        padding: 4px 6px;
    }
    table.att-table td.td-sign {
        height: 30px;
    }
    table.att-table tr:nth-child(even) td { background: #fafbfc; }

    .code-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 3px;
        font-size: 9pt;
        font-weight: bold;
    }
    .code-P  { background: #d1fae5; color: #065f46; }
    .code-AJ { background: #dbeafe; color: #1e40af; }
    .code-AN { background: #fee2e2; color: #991b1b; }
    .code-RJ { background: #fef9c3; color: #854d0e; }
    .code-RN { background: #ffedd5; color: #9a3412; }
    .code-nd { color: #c0c8d0; font-size: 9pt; }

    /* ── Légende horizontale ── */
    .legend {
        margin-top: 10px;
        padding-top: 7px;
        border-top: 1px solid #e0e3e5;
        font-size: 8pt;
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
        margin: 0 8px;
    }

    /* ── Pied de page ── */
    .footer {
        position: fixed;
        bottom: -8mm;
        left: 0;
        right: 0;
        font-size: 7pt;
        color: #9aaabb;
        text-align: center;
        border-top: 1px solid #e8eaec;
        padding-top: 4px;
    }
</style>
</head>
<body>

    <!-- En-tête -->
    <div class="header">
        <table>
            <tr>
                <td class="h-left" style="width:60%">
                    <div class="project">{{ $formation->project->name }}</div>
                    <div class="formation-name">{{ $formation->name }}</div>
                </td>
                <td class="h-right" style="width:40%">
                    <div class="sheet-title">Feuille d'émargement</div>
                    <span class="date-badge">
                        {{ \Carbon\Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tableau présences -->
    <table class="att-table">
        <colgroup>
            <col style="width:5%">
            <col style="width:45%">
            <col style="width:15%">
            <col style="width:35%">
        </colgroup>
        <thead>
            <tr>
                <th>N°</th>
                <th class="th-name">Nom &amp; Prénom</th>
                <th>Présence</th>
                <th>Signature de l'apprenant</th>
            </tr>
        </thead>
        <tbody>
            @foreach($learners as $idx => $learner)
                @php
                    $att  = $attendances->get($learner->id);
                    $code = $att?->code?->value;
                @endphp
                <tr>
                    <td class="td-num">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="td-name">{{ $learner->full_name }}</td>
                    <td class="td-code">
                        @if($code)
                            <span class="code-badge code-{{ $code }}">{{ $code }}</span>
                        @else
                            <span class="code-nd">—</span>
                        @endif
                    </td>
                    <td class="td-sign"></td>
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

    <!-- Pied de page -->
    <div class="footer">
        {{ $formation->project->name }} — {{ $formation->name }} · {{ $learners->count() }} apprenant(s) · Généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</body>
</html>
