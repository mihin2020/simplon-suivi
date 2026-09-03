<?php

namespace App\Support;

final class InterviewCustomFields
{
    /**
     * @param  array<int, array{label?: mixed, value?: mixed}>|null  $fields
     * @return array<int, array{label: string, value: string}>
     */
    public static function normalize(?array $fields): array
    {
        if ($fields === null) {
            return [];
        }

        return collect($fields)
            ->map(fn (array $field): array => [
                'label' => trim((string) ($field['label'] ?? '')),
                'value' => trim((string) ($field['value'] ?? '')),
            ])
            ->filter(fn (array $field): bool => $field['label'] !== '')
            ->values()
            ->take(20)
            ->all();
    }

    /**
     * @param  array<int, array{label?: mixed, value?: mixed}>|null  $fields
     * @return array{custom_fields: array<int, array{label: string, value: string}>}|null
     */
    public static function toMeta(?array $fields): ?array
    {
        $normalized = self::normalize($fields);

        return $normalized === [] ? null : ['custom_fields' => $normalized];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<int, array{label: string, value: string}>
     */
    public static function fromMeta(?array $meta): array
    {
        $fields = $meta['custom_fields'] ?? null;

        return is_array($fields) ? self::normalize($fields) : [];
    }
}
