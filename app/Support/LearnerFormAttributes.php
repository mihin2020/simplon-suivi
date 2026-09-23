<?php

namespace App\Support;

use App\Enums\FormFieldType;

/**
 * Catalog of Learner attributes usable as mapped form fields.
 * Import column names match LearnersImport / learners import UI.
 */
final class LearnerFormAttributes
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     type: FormFieldType,
     *     required: bool,
     *     default: bool,
     *     import_column: string|null
     * }>
     */
    public static function all(): array
    {
        return [
            ['key' => 'first_name', 'label' => 'Prénom', 'type' => FormFieldType::ShortText, 'required' => true, 'default' => true, 'import_column' => 'prenom'],
            ['key' => 'last_name', 'label' => 'Nom', 'type' => FormFieldType::ShortText, 'required' => true, 'default' => true, 'import_column' => 'nom'],
            ['key' => 'email', 'label' => 'E-mail', 'type' => FormFieldType::Email, 'required' => false, 'default' => true, 'import_column' => 'email'],
            ['key' => 'phone', 'label' => 'Téléphone', 'type' => FormFieldType::Phone, 'required' => false, 'default' => true, 'import_column' => 'telephone'],
            ['key' => 'gender', 'label' => 'Genre (M/F)', 'type' => FormFieldType::SingleChoice, 'required' => false, 'default' => false, 'import_column' => 'genre'],
            ['key' => 'birth_date', 'label' => 'Date de naissance', 'type' => FormFieldType::Date, 'required' => false, 'default' => false, 'import_column' => 'date_naissance'],
            ['key' => 'birth_place', 'label' => 'Lieu de naissance', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'lieu_naissance'],
            ['key' => 'education_level_id', 'label' => 'Niveau d\'études', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'niveau_etudes'],
            ['key' => 'talent', 'label' => 'Talent / compétences', 'type' => FormFieldType::LongText, 'required' => false, 'default' => false, 'import_column' => 'talent'],
            ['key' => 'emergency_contact_name', 'label' => 'Contact urgence · Nom', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'contact_urgence_nom'],
            ['key' => 'emergency_contact_firstname', 'label' => 'Contact urgence · Prénom', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'contact_urgence_prenom'],
            ['key' => 'emergency_contact_phone', 'label' => 'Contact urgence · Téléphone', 'type' => FormFieldType::Phone, 'required' => false, 'default' => false, 'import_column' => 'contact_urgence_telephone'],
            ['key' => 'address', 'label' => 'Adresse', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'adresse'],
            ['key' => 'location', 'label' => 'Localisation', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'localisation'],
            ['key' => 'profile', 'label' => 'Profil / métier visé', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'profil'],
            ['key' => 'organization', 'label' => 'Organisation', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'organisation'],
            ['key' => 'age_range_id', 'label' => 'Tranche d\'âge', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'tranche_age'],
            ['key' => 'study_field', 'label' => 'Domaine d\'études', 'type' => FormFieldType::ShortText, 'required' => false, 'default' => false, 'import_column' => 'domaine_etudes'],
            ['key' => 'photo_path', 'label' => 'Photo', 'type' => FormFieldType::File, 'required' => false, 'default' => false, 'import_column' => 'photo'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, type: FormFieldType, required: bool, default: bool, import_column: string|null}>
     */
    public static function defaults(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $attr) => $attr['default'] === true
        ));
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /**
     * Ordered import columns matching the learners import template.
     *
     * @return list<string>
     */
    public static function importColumns(): array
    {
        return array_values(array_filter(array_column(self::all(), 'import_column')));
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $attribute) {
            if ($attribute['key'] === $key) {
                return $attribute;
            }
        }

        return null;
    }

    public static function importColumnFor(string $key): ?string
    {
        return self::find($key)['import_column'] ?? null;
    }
}
