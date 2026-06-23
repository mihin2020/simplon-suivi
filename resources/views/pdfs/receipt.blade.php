<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $receiptNumber }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1a1a2e;
            line-height: 1.5;
        }

        body {
            background: #eef0f3;
            padding: 24px 12px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        /* PAGE A4 — flex column pour coller le footer en bas */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 18mm 20mm;
            background: #fff;
            font-size: 11pt;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        /* Le contenu grandit pour pousser le footer en bas */
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 8mm;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f3f4f6;
            border-radius: 6px;
            padding: 8px 14px;
            flex-shrink: 0;
        }
        .logo img {
            height: 36px;
            width: auto;
        }
        .header-right { text-align: right; }
        .doc-title {
            font-size: 16pt;
            font-weight: 800;
            color: #E5004C;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-num {
            font-size: 11pt;
            font-weight: 700;
            color: #1a1a2e;
            margin-top: 4px;
        }
        .doc-date {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 2px;
        }

        .divider-red {
            border: none;
            border-top: 2px solid #E5004C;
            margin: 0 0 7mm;
        }

        .section-title {
            font-size: 9pt;
            font-weight: 700;
            color: #E5004C;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid #fce0ea;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10mm;
            margin-bottom: 8mm;
        }
        .info-line {
            display: grid;
            grid-template-columns: 25mm 1fr;
            gap: 4mm;
            padding: 1.5mm 0;
            font-size: 10pt;
        }
        .info-line .label { color: #6b7280; }
        .info-line .value {
            color: #1a1a2e;
            font-weight: 600;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .amount-box {
            background: #fdf2f7;
            border-radius: 4px;
            padding: 6mm 8mm;
            margin-bottom: 8mm;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 6mm;
        }
        .amount-label {
            font-size: 8pt;
            font-weight: 700;
            color: #9aa3b2;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 2mm;
        }
        .amount-value {
            font-size: 24pt;
            font-weight: 800;
            color: #E5004C;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .amount-right {
            text-align: right;
            font-size: 9pt;
            color: #515f74;
        }
        .amount-right .method {
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 1mm;
            font-size: 10pt;
        }
        .badge {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2mm;
        }

        .recap {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10mm;
            font-size: 10pt;
        }
        .recap td { padding: 3mm 0; }
        .recap .r-label { color: #515f74; }
        .recap .r-value {
            text-align: right;
            font-weight: 600;
            color: #1a1a2e;
            white-space: nowrap;
        }
        .recap tr.divider td { border-top: 1px dashed #d1d5db; }
        .recap tr.total td {
            border-top: 2px solid #1a1a2e;
            padding-top: 4mm;
            font-size: 12pt;
        }
        .status-pending { color: #d97706; font-weight: 700; }
        .status-paid    { color: #059669; font-weight: 700; }

        .issuer-signature {
            margin-top: auto;
            padding-top: 10mm;
            text-align: right;
            width: 65mm;
            margin-left: auto;
        }
        .issuer-line {
            border-top: 1px solid #1a1a2e;
            margin-bottom: 2mm;
        }
        .issuer-name {
            font-size: 10pt;
            font-weight: 600;
            color: #1a1a2e;
        }

        /* FOOTER — collé en bas de la page A4 */
        .footer {
            text-align: center;
            font-size: 8.5pt;
            color: #9aa3b2;
            border-top: 1px solid #e5e7eb;
            padding-top: 4mm;
            margin-top: 8mm;
        }
        .footer strong { color: #515f74; }

        @media print {
            body {
                background: #fff;
                padding: 0;
                display: block;
            }
            .page {
                width: 210mm;
                min-height: 297mm;
                box-shadow: none;
                margin: 0;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<div class="page">

    <div class="content">

        <div class="header">
            <div class="logo">
                <img src="/images/logo.jpeg" alt="Simplon BF">
            </div>
            <div class="header-right">
                <div class="doc-title">Reçu de paiement</div>
                <div class="doc-num">N° {{ $receiptNumber }}</div>
                <div class="doc-date">Émis le {{ $issuedAt }}</div>
            </div>
        </div>

        <hr class="divider-red">

        <div class="info-grid">
            <div>
                <div class="section-title">Apprenant</div>
                <div class="info-line">
                    <span class="label">Nom</span>
                    <span class="value">{{ $learner->last_name }} {{ $learner->first_name }}</span>
                </div>
                @if($learner->email)
                <div class="info-line">
                    <span class="label">Email</span>
                    <span class="value">{{ $learner->email }}</span>
                </div>
                @endif
                @if($learner->phone)
                <div class="info-line">
                    <span class="label">Téléphone</span>
                    <span class="value">{{ $learner->phone }}</span>
                </div>
                @endif
            </div>
            <div>
                <div class="section-title">Formation</div>
                <div class="info-line">
                    <span class="label">Intitulé</span>
                    <span class="value">{{ $formation?->name ?? '—' }}</span>
                </div>
                <div class="info-line">
                    <span class="label">Cohorte</span>
                    <span class="value">{{ $cohort->name }}</span>
                </div>
                <div class="info-line">
                    <span class="label">Tranche</span>
                    <span class="value">N° {{ $payment->installment_number }}</span>
                </div>
            </div>
        </div>

        <div class="amount-box">
            <div>
                <div class="amount-label">Montant encaissé</div>
                <div class="amount-value">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="amount-right">
                <div class="method">{{ $methodLabel }}</div>
                <div>{{ $paidAt }}</div>
                <span class="badge">&#10003; Payé</span>
            </div>
        </div>

        <div class="section-title">Récapitulatif financier</div>
        <table class="recap">
            <tr>
                <td class="r-label">Coût total de la formation</td>
                <td class="r-value">{{ number_format($totalCost, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="divider">
                <td class="r-label">Total encaissé à ce jour</td>
                <td class="r-value">{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="total">
                @if($remaining <= 0)
                <td class="status-paid">&#10003; Entièrement réglé</td>
                <td class="r-value status-paid">0 FCFA</td>
                @else
                <td class="status-pending">Reste à payer</td>
                <td class="r-value status-pending">{{ number_format($remaining, 0, ',', ' ') }} FCFA</td>
                @endif
            </tr>
        </table>

        @if($payment->notes)
        <div style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:4px;padding:4mm 6mm;margin-bottom:8mm;font-size:9pt;color:#78350f;">
            <div style="font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:1mm;">Note</div>
            {{ $payment->notes }}
        </div>
        @endif

        @if($issuedBy)
        <div class="issuer-signature">
            <div class="issuer-line"></div>
            <div class="issuer-name">{{ $issuedBy }}</div>
        </div>
        @endif

    </div>

    <!-- FOOTER — toujours collé en bas de la page A4 -->
    <div class="footer">
        <strong>Simplon Burkina Faso</strong> &nbsp;·&nbsp;
        Ce reçu constitue un justificatif officiel de paiement &nbsp;·&nbsp;
        À conserver
    </div>

</div>

</body>
</html>
