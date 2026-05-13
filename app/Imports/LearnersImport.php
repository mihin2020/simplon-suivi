<?php

namespace App\Imports;

use App\Models\EducationLevel;
use App\Models\Learner;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class LearnersImport implements ToModel, WithHeadingRow, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    private ?string $formationId;
    private array $educationLevelMap = [];
    private int $importedCount = 0;
    private int $skippedCount = 0;

    public function __construct(?string $formationId = null)
    {
        $this->formationId = $formationId;
        $this->educationLevelMap = EducationLevel::pluck('id', 'name')->toArray();
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function skippedCount(): int
    {
        return $this->skippedCount;
    }

    public function model(array $row): ?Learner
    {
        $row = collect($row)->mapWithKeys(function ($value, $key) {
            $normalized = Str::ascii((string) $key);
            $normalized = strtolower($normalized);
            $normalized = preg_replace('/[^a-z0-9_]/i', '', $normalized);

            return [$normalized => $value];
        })->all();

        $aliases = [
            'first_name' => 'prenom',
            'firstname' => 'prenom',
            'prenom' => 'prenom',
            'last_name' => 'nom',
            'lastname' => 'nom',
            'nom' => 'nom',
            'phone' => 'telephone',
            'telephone' => 'telephone',
            'date_de_naissance' => 'date_naissance',
            'niveau_d_etudes' => 'niveau_etudes',
            'niveauetudes' => 'niveau_etudes',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $row) && !array_key_exists($to, $row)) {
                $row[$to] = $row[$from];
            }
        }

        if (empty($row['prenom']) || empty($row['nom'])) {
            $this->skippedCount++;
            return null;
        }

        if (!empty($row['email']) && Learner::where('email', $row['email'])->exists()) {
            $this->skippedCount++;
            return null;
        }

        $educationLevelId = null;
        if (!empty($row['niveau_etudes'])) {
            $educationLevelId = $this->educationLevelMap[$row['niveau_etudes']] ?? null;
        }

        $gender = null;
        if (!empty($row['genre'])) {
            $g = strtolower(trim((string) $row['genre']));
            $gender = match (true) {
                in_array($g, ['m', 'masculin', 'homme', 'male'])                  => 'male',
                in_array($g, ['f', 'feminin', 'féminin', 'femme', 'female'])      => 'female',
                default                                                            => null,
            };
        }

        $birthDate = null;
        if (!empty($row['date_naissance'])) {
            try {
                $birthDate = \Carbon\Carbon::parse($row['date_naissance'])->toDateString();
            } catch (\Exception) {
                $birthDate = null;
            }
        }

        $learner = new Learner([
            'first_name'                  => trim((string) $row['prenom']),
            'last_name'                   => trim((string) $row['nom']),
            'email'                       => !empty($row['email']) ? trim((string) $row['email']) : null,
            'phone'                       => !empty($row['telephone']) ? trim((string) $row['telephone']) : null,
            'birth_date'                  => $birthDate,
            'birth_place'                 => !empty($row['lieu_naissance']) ? trim((string) $row['lieu_naissance']) : null,
            'gender'                      => $gender,
            'education_level_id'          => $educationLevelId,
            'talent'                      => !empty($row['talent']) ? trim((string) $row['talent']) : null,
            'emergency_contact_name'      => !empty($row['contact_urgence_nom']) ? trim((string) $row['contact_urgence_nom']) : null,
            'emergency_contact_firstname' => !empty($row['contact_urgence_prenom']) ? trim((string) $row['contact_urgence_prenom']) : null,
            'emergency_contact_phone'     => !empty($row['contact_urgence_telephone']) ? trim((string) $row['contact_urgence_telephone']) : null,
            'address'                     => !empty($row['adresse']) ? trim((string) $row['adresse']) : null,
            'location'                    => !empty($row['localisation']) ? trim((string) $row['localisation']) : null,
            'profile'                     => !empty($row['profil']) ? trim((string) $row['profil']) : null,
            'study_field'                 => !empty($row['domaine_etudes']) ? trim((string) $row['domaine_etudes']) : null,
        ]);

        $learner->save();

        if ($this->formationId) {
            $learner->formations()->attach($this->formationId, [
                'status'      => 'in_progress',
                'enrolled_at' => now(),
            ]);
        }

        $this->importedCount++;
        return null;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
