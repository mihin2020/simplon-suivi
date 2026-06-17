<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $receiptNumber }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            line-height: 1.5;
        }

        .page { width: 100%; padding: 14mm 18mm; }

        /* ── Header ── */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .header-table td { vertical-align: middle; }
        .logo-cell { width: 45mm; }
        .logo-box { background: #f3f4f6; border-radius: 4px; padding: 6px 10px; display: inline-block; }
        .logo-box img { height: 32px; width: auto; }
        .title-cell { text-align: right; }
        .doc-title { font-size: 15pt; font-weight: bold; color: #E5004C; text-transform: uppercase; letter-spacing: 1px; }
        .doc-num  { font-size: 10pt; font-weight: bold; color: #1a1a2e; margin-top: 3px; }
        .doc-date { font-size: 8pt; color: #6b7280; margin-top: 2px; }

        hr.red { border: none; border-top: 2pt solid #E5004C; margin: 0 0 6mm; }

        /* ── Info grid ── */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 7mm; }
        .info-table td { vertical-align: top; width: 50%; padding-right: 8mm; }
        .info-table td:last-child { padding-right: 0; }

        .section-title {
            font-size: 8pt; font-weight: bold; color: #E5004C;
            text-transform: uppercase; letter-spacing: 1.5px;
            border-bottom: 1pt solid #fce0ea;
            padding-bottom: 2mm; margin-bottom: 3mm;
        }

        .info-row { width: 100%; border-collapse: collapse; margin-bottom: 1mm; }
        .info-row td { font-size: 9pt; padding: 1mm 0; }
        .info-row .lbl { color: #6b7280; width: 26mm; }
        .info-row .val { font-weight: bold; color: #1a1a2e; }

        /* ── Amount box ── */
        .amount-table { width: 100%; border-collapse: collapse; background: #fdf2f7; border-radius: 4px; margin-bottom: 7mm; }
        .amount-table td { vertical-align: middle; padding: 5mm 7mm; }
        .amount-lbl { font-size: 7pt; font-weight: bold; color: #9aa3b2; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 2mm; }
        .amount-val { font-size: 22pt; font-weight: bold; color: #E5004C; }
        .amount-right { text-align: right; }
        .amount-method { font-weight: bold; font-size: 10pt; color: #1a1a2e; }
        .amount-date   { font-size: 9pt; color: #515f74; }
        .badge-paid {
            display: inline-block; background: #d1fae5; color: #065f46;
            padding: 2px 9px; border-radius: 10px;
            font-size: 7.5pt; font-weight: bold; text-transform: uppercase;
        }

        /* ── Recap ── */
        .recap { width: 100%; border-collapse: collapse; margin-bottom: 8mm; }
        .recap td { padding: 2.5mm 0; font-size: 10pt; }
        .recap .r-lbl { color: #515f74; }
        .recap .r-val { text-align: right; font-weight: bold; color: #1a1a2e; white-space: nowrap; }
        .recap .divider td { border-top: 1pt dashed #d1d5db; }
        .recap .total td { border-top: 2pt solid #1a1a2e; padding-top: 3mm; font-size: 11pt; }
        .s-pending { color: #d97706; font-weight: bold; }
        .s-paid    { color: #059669; font-weight: bold; }

        /* ── Note ── */
        .note-box { background: #fffbeb; border-left: 4pt solid #f59e0b; padding: 3mm 5mm; margin-bottom: 7mm; font-size: 9pt; color: #78350f; }
        .note-lbl  { font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1mm; }

        /* ── Footer ── */
        .footer { text-align: center; font-size: 8pt; color: #9aa3b2; border-top: 1pt solid #e5e7eb; padding-top: 3mm; margin-top: 6mm; }
        .footer strong { color: #515f74; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <div class="logo-box">
                    <img src="{{ public_path('images/logo.jpeg') }}" alt="Simplon BF">
                </div>
            </td>
            <td class="title-cell">
                <div class="doc-title">Reçu de paiement</div>
                <div class="doc-num">N° {{ $receiptNumber }}</div>
                <div class="doc-date">Émis le {{ $issuedAt }}</div>
            </td>
        </tr>
    </table>

    <hr class="red">

    {{-- Apprenant / Formation --}}
    <table class="info-table">
        <tr>
            <td>
                <div class="section-title">Apprenant</div>
                <table class="info-row">
                    <tr><td class="lbl">Nom</td><td class="val">{{ $learner->last_name }} {{ $learner->first_name }}</td></tr>
                    @if($learner->email)
                    <tr><td class="lbl">Email</td><td class="val">{{ $learner->email }}</td></tr>
                    @endif
                    @if($learner->phone)
                    <tr><td class="lbl">Tél.</td><td class="val">{{ $learner->phone }}</td></tr>
                    @endif
                </table>
            </td>
            <td>
                <div class="section-title">Formation</div>
                <table class="info-row">
                    <tr><td class="lbl">Intitulé</td><td class="val">{{ $formation?->name ?? '—' }}</td></tr>
                    <tr><td class="lbl">Cohorte</td><td class="val">{{ $cohort->name }}</td></tr>
                    <tr><td class="lbl">Tranche</td><td class="val">N° {{ $payment->installment_number }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Montant --}}
    <table class="amount-table">
        <tr>
            <td>
                <div class="amount-lbl">Montant encaissé</div>
                <div class="amount-val">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</div>
            </td>
            <td class="amount-right">
                <div class="amount-method">{{ $methodLabel }}</div>
                <div class="amount-date">{{ $paidAt }}</div>
                <div style="margin-top:2mm;"><span class="badge-paid">&#10003; Payé</span></div>
            </td>
        </tr>
    </table>

    {{-- Récapitulatif --}}
    <div class="section-title">Récapitulatif financier</div>
    <table class="recap">
        <tr>
            <td class="r-lbl">Coût total de la formation</td>
            <td class="r-val">{{ number_format($totalCost, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr class="divider">
            <td class="r-lbl">Total encaissé à ce jour</td>
            <td class="r-val">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr class="total">
            @if($remaining <= 0)
            <td class="s-paid">&#10003; Entièrement réglé</td>
            <td class="r-val s-paid">0 FCFA</td>
            @else
            <td class="s-pending">Reste à payer</td>
            <td class="r-val s-pending">{{ number_format($remaining, 0, ',', ' ') }} FCFA</td>
            @endif
        </tr>
    </table>

    @if($payment->notes)
    <div class="note-box">
        <div class="note-lbl">Note</div>
        {{ $payment->notes }}
    </div>
    @endif

    <div class="footer">
        <strong>Simplon Burkina Faso</strong> &nbsp;·&nbsp;
        Ce reçu constitue un justificatif officiel de paiement &nbsp;·&nbsp; À conserver
    </div>

</div>
</body>
</html>
