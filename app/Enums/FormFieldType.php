<?php

namespace App\Enums;

enum FormFieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Dropdown = 'dropdown';
    case File = 'file';
    case LinearScale = 'linear_scale';
    case SectionHeader = 'section_header';
    case Paragraph = 'paragraph';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Texte court',
            self::LongText => 'Texte long',
            self::Email => 'E-mail',
            self::Phone => 'Téléphone',
            self::Number => 'Nombre',
            self::Date => 'Date',
            self::SingleChoice => 'Choix unique',
            self::MultipleChoice => 'Choix multiples',
            self::Dropdown => 'Liste déroulante',
            self::File => 'Fichier',
            self::LinearScale => 'Échelle',
            self::SectionHeader => 'Section',
            self::Paragraph => 'Paragraphe',
        };
    }

    public function isAnswerable(): bool
    {
        return ! in_array($this, [self::SectionHeader, self::Paragraph], true);
    }

    /**
     * Field types available in the form builder.
     *
     * @return list<self>
     */
    public static function builderTypes(): array
    {
        return [
            self::ShortText,
            self::LongText,
            self::Email,
            self::Phone,
            self::Number,
            self::Date,
            self::SingleChoice,
            self::MultipleChoice,
            self::Dropdown,
            self::File,
            self::LinearScale,
            self::SectionHeader,
            self::Paragraph,
        ];
    }

    /**
     * @deprecated Use builderTypes()
     *
     * @return list<self>
     */
    public static function v1Types(): array
    {
        return self::builderTypes();
    }

    public function requiresOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice, self::Dropdown], true);
    }

    public function icon(): string
    {
        return match ($this) {
            self::ShortText => 'short_text',
            self::LongText => 'notes',
            self::Email => 'mail',
            self::Phone => 'call',
            self::Number => 'pin',
            self::Date => 'calendar_today',
            self::SingleChoice => 'radio_button_checked',
            self::MultipleChoice => 'check_box',
            self::Dropdown => 'arrow_drop_down_circle',
            self::File => 'upload_file',
            self::LinearScale => 'linear_scale',
            self::SectionHeader => 'title',
            self::Paragraph => 'subject',
        };
    }
}
