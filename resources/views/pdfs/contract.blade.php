@php
    // Page geometry. The letterhead bands (heights in mm, 0/null = none) sit in
    // the page margins; a band printed on the first page only leaves the other
    // pages with the plain margin. Keep the typography below in sync with
    // resources/js/Components/Contracts/ContractDocumentEditor.vue.
    $side = '20mm';
    $plain = '20mm';
    $gap = 6;
    $headerMm = (float) ($headerHeightMm ?? 0);
    $footerMm = (float) ($footerHeightMm ?? 0);
    $topMargin = $headerMm && ! $firstPageOnly ? ($headerMm + $gap).'mm' : $plain;
    $bottomMargin = $footerMm && ! $firstPageOnly ? ($footerMm + $gap).'mm' : $plain;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title }}</title>
    <style>
        @page {
            margin: {{ $topMargin }} {{ $side }} {{ $bottomMargin }} {{ $side }};
        }

        @if ($footerMm && $firstPageOnly)
            /* Only the first page reserves room for its footer. */
            @page :first {
                margin-bottom: {{ $footerMm + $gap }}mm;
            }
        @endif

        @if ($font->hasFiles())
            @foreach (\App\Enums\ContractFont::FACES as $suffix => $face)
                @php [$weight, $style] = explode(' ', $face); @endphp
                @font-face {
                    font-family: '{{ $font->family() }}';
                    font-weight: {{ $weight }};
                    font-style: {{ $style }};
                    src: url('{{ public_path(\App\Enums\ContractFont::DIRECTORY.'/'.$font->value.'-'.$suffix.'.ttf') }}') format('truetype');
                }
            @endforeach
        @endif

        body {
            font-family: '{{ $font->family() }}', {{ $font->isSerif() ? 'DejaVu Serif, serif' : 'DejaVu Sans, sans-serif' }};
            font-size: 11pt;
            line-height: {{ $lineSpacing->factor() }};
            color: #111827;
        }

        .band { width: 210mm; left: -{{ $side }}; }

        .doc-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            line-height: 1.3;
            margin: 0 0 18pt;
            text-decoration: underline;
        }

        .signatures { width: 100%; margin-top: 28pt; border-collapse: collapse; page-break-inside: avoid; }
        .signatures td { width: 50%; vertical-align: top; padding: 0; }
        .signatures .right { text-align: right; }
        .signatures .title { font-weight: bold; }
        .signatures .name { padding-top: 52pt; font-weight: bold; }

        .body-content p { margin: 0 0 8pt; text-align: justify; }
        .body-content h1, .body-content h2 { font-size: 11pt; font-weight: bold; text-decoration: underline; margin: 14pt 0 6pt; page-break-after: avoid; }
        .body-content h3, .body-content h4 { font-size: 11pt; font-weight: bold; margin: 10pt 0 4pt; page-break-after: avoid; }
        .body-content ul, .body-content ol { margin: 0 0 8pt; padding-left: 18pt; }
        .body-content li { margin-bottom: 3pt; text-align: justify; }
        .body-content li p { margin: 0; }
        .body-content blockquote { margin: 0 0 8pt 14pt; }
        .body-content table { width: 100%; border-collapse: collapse; margin: 0 0 8pt; }
        .body-content td, .body-content th { border: 0.5pt solid #6b7280; padding: 3pt 5pt; vertical-align: top; }
    </style>
</head>
<body>
    @if ($headerMm && ! $firstPageOnly)
        <div class="band" style="position:fixed; top:-{{ $headerMm + $gap }}mm; height:{{ $headerMm }}mm;">{!! $header !!}</div>
    @endif

    @if ($footerMm)
        {{-- Absolute (not fixed) prints once, at the bottom of the first page --}}
        <div class="band" style="position:{{ $firstPageOnly ? 'absolute' : 'fixed' }}; bottom:-{{ $footerMm + $gap }}mm; height:{{ $footerMm }}mm;">{!! $footer !!}</div>
    @endif
    {{-- Without a footer image, page numbers are drawn by ContractPdfService. --}}

    @if ($headerMm && $firstPageOnly)
        {{-- In the flow, so it exists on the first page only and pushes the body down --}}
        <div style="margin:-{{ $plain }} 0 {{ $gap }}mm -{{ $side }}; width:210mm; height:{{ $headerMm }}mm;">{!! $header !!}</div>
    @endif

    <main>
        <div class="doc-title">{{ $contract->title }}</div>
        <div class="body-content">
            {!! $body !!}
        </div>

        @if (! empty($contract->signatories))
            <table class="signatures">
                <tr>
                    <td class="title">{{ $contract->signatories['left_title'] ?? '' }}</td>
                    <td class="title right">{{ $contract->signatories['right_title'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="name">{{ $contract->signatories['left_name'] ?? '' }}</td>
                    <td class="name right">{{ $contract->signatories['right_name'] ?? '' }}</td>
                </tr>
            </table>
        @endif
    </main>
</body>
</html>
