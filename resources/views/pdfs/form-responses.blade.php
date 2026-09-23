<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Candidatures — {{ $form->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #191c1e; }
        h1 { font-size: 18px; color: #1F3A4D; margin: 0 0 4px; }
        .meta { color: #515f74; margin-bottom: 14px; }
        .brand { color: #E5004C; font-weight: bold; font-size: 12px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dadce0; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #1F3A4D; color: #fff; font-size: 10px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f8fafc; }
        .footer { margin-top: 12px; font-size: 9px; color: #80868b; }
    </style>
</head>
<body>
    <div class="brand">Simplon Burkina Faso</div>
    <h1>{{ $form->title }}</h1>
    <p class="meta">
        {{ $form->project?->name }} · {{ $form->formation?->name }}
        · {{ $responses->count() }} candidature(s)
        · Généré le {{ $generatedAt->format('d/m/Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>E-mail</th>
                <th>Statut</th>
                <th>Soumis le</th>
                <th>Décision</th>
                @foreach ($form->fields->filter(fn ($f) => $f->type->isAnswerable()) as $field)
                    <th>{{ $field->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($responses as $response)
                @php
                    $answersByField = $response->answers->keyBy('form_field_id');
                    $reviewer = $response->reviewer
                        ? trim($response->reviewer->first_name.' '.$response->reviewer->last_name)
                        : null;
                @endphp
                <tr>
                    <td>{{ $response->email ?? '—' }}</td>
                    <td>{{ $response->status->label() }}</td>
                    <td>{{ optional($response->submitted_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                        @if ($reviewer)
                            {{ $reviewer }}
                            @if ($response->review_note)
                                <br><em>{{ $response->review_note }}</em>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    @foreach ($form->fields->filter(fn ($f) => $f->type->isAnswerable()) as $field)
                        @php $answer = $answersByField->get($field->id); @endphp
                        <td>
                            @if (! $answer)
                                —
                            @elseif ($answer->file_original_name)
                                {{ $answer->file_original_name }}
                            @elseif (is_array($answer->value_json))
                                {{ implode(', ', $answer->value_json) }}
                            @else
                                {{ $answer->value_text }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + $form->fields->filter(fn ($f) => $f->type->isAnswerable())->count() }}">
                        Aucune candidature.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Document généré automatiquement — Simplon Burkina Faso</p>
</body>
</html>
