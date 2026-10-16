<?php

namespace App\Enums;

/**
 * Line spacing of a contract, as in a word processor. Applied to the PDF and
 * to the on-screen editor alike.
 */
enum ContractLineSpacing: string
{
    case Single = '1';
    case OneFifteen = '1.15';
    case OneHalf = '1.5';
    case Double = '2';

    public static function default(): self
    {
        return self::OneHalf;
    }

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Simple (1,0)',
            self::OneFifteen => '1,15',
            self::OneHalf => '1,5',
            self::Double => 'Double (2,0)',
        };
    }

    /** CSS line-height factor. */
    public function factor(): float
    {
        return (float) $this->value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
