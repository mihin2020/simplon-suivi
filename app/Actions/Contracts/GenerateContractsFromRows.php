<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Enums\FillFieldsStatus;
use App\Models\Contract;
use App\Services\Contracts\ContractReferenceGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateContractsFromRows
{
    public function __construct(private readonly ContractReferenceGenerator $references) {}

    /**
     * One contract per row of the filled Excel model: the passages of the
     * source contract are replaced by the row's values, column by column.
     * Rows left empty, or identical to the source (the example row), are skipped.
     * All or nothing.
     *
     * @param  list<list<mixed>>  $rows  data rows, columns in the order of the source's fill fields
     * @return Collection<int, Contract>
     */
    public function execute(Contract $source, array $rows, string $userId, bool $withEmail = false): Collection
    {
        $fields = $source->fill_fields ?? [];
        $originals = array_map(fn (array $f) => trim($f['value']), $fields);

        return DB::transaction(function () use ($source, $rows, $fields, $originals, $userId, $withEmail) {
            $created = collect();

            foreach ($rows as $row) {
                $values = array_map(fn ($cell) => trim((string) $cell), array_slice(array_pad($row, count($fields), ''), 0, count($fields)));
                $email = $withEmail ? trim((string) ($row[count($fields)] ?? '')) : '';

                if (implode('', $values) === '' || $values === $originals) {
                    continue;
                }

                $replacements = [];
                foreach ($fields as $index => $field) {
                    // An empty cell leaves the original passage, so nothing silently disappears.
                    if ($values[$index] !== '' && $values[$index] !== $originals[$index]) {
                        $replacements[e($originals[$index])] = e($values[$index]);
                    }
                }

                $created->push(Contract::create([
                    'reference' => $this->references->next(),
                    'contract_project_id' => $source->contract_project_id,
                    'target' => $source->target,
                    'recipient_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                    'title' => $source->title.' : '.$this->displayName($fields, $values),
                    // strtr(): longest passages first, never re-replaces its own output.
                    'body_html' => strtr((string) $source->body_html, $replacements),
                    'signatories' => $source->signatories
                        ? array_map(fn (string $text) => html_entity_decode(strtr(e($text), $replacements), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $source->signatories)
                        : null,
                    'header_html' => $source->header_html,
                    'footer_html' => $source->footer_html,
                    'header_height_mm' => $source->header_height_mm,
                    'footer_height_mm' => $source->footer_height_mm,
                    'letterhead_scope' => $source->letterhead_scope,
                    'letterhead_id' => $source->letterhead_id,
                    'font' => $source->font,
                    'line_spacing' => $source->line_spacing,
                    'data' => array_combine(array_column($fields, 'label'), $values),
                    // Same passages, new values: the copy can itself serve as a model.
                    'fill_fields' => array_map(fn (array $f, string $v) => [...$f, 'value' => $v !== '' ? $v : $f['value']], $fields, $values),
                    'fill_fields_status' => FillFieldsStatus::Ready,
                    'status' => ContractStatus::Draft,
                    'version' => 1,
                    'created_by' => $userId,
                ]));
            }

            return $created;
        });
    }

    /**
     * Name shown in the title: the column holding a name, else the first filled value.
     *
     * @param  list<array{key: string, label: string, value: string}>  $fields
     * @param  list<string>  $values
     */
    private function displayName(array $fields, array $values): string
    {
        foreach ($fields as $index => $field) {
            if ($values[$index] !== '' && preg_match('/full_name|\bname$|nom complet|raison sociale/i', $field['key'].' '.$field['label'])) {
                return $values[$index];
            }
        }

        return collect($values)->first(fn (string $v) => $v !== '') ?? '';
    }
}
