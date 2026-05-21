<?php

namespace App\Imports;

use App\Models\Cohort;
use App\Models\EducationLevel;
use App\Models\Learner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CohortLearnersImport implements ToCollection, WithHeadingRow
{
    private Cohort $cohort;
    private array  $educationLevelMap = [];
    public  int    $imported          = 0;
    public  int    $skipped           = 0;

    public function __construct(Cohort $cohort)
    {
        $this->cohort            = $cohort;
        $this->educationLevelMap = EducationLevel::pluck('id', 'name')->toArray();
    }

    public function collection(Collection $rows): void
    {
        $enrolledIds = [];

        foreach ($rows as $row) {
            $row = collect($row)->mapWithKeys(function ($value, $key) {
                $normalized = strtolower(preg_replace('/[^a-z0-9_]/i', '', Str::ascii((string) $key)));
                return [$normalized => $value];
            })->all();

            $firstName = trim((string) ($row['prenom'] ?? ''));
            $lastName  = trim((string) ($row['nom']   ?? ''));

            if ($firstName === '' || $lastName === '') {
                $this->skipped++;
                continue;
            }

            $email = !empty($row['email']) ? trim((string) $row['email']) : null;

            // Si l'email existe déjà, inscrire l'apprenant existant sans créer de doublon
            if ($email) {
                $existing = Learner::where('email', $email)->first();
                if ($existing) {
                    $enrolledIds[$existing->id] = ['enrolled_at' => now(), 'status' => 'actif'];
                    $this->skipped++;
                    continue;
                }
            }

            $gender = null;
            if (!empty($row['genre'])) {
                $g = strtolower(trim((string) $row['genre']));
                $gender = match (true) {
                    in_array($g, ['m', 'masculin', 'homme', 'male'])             => 'male',
                    in_array($g, ['f', 'feminin', 'féminin', 'femme', 'female']) => 'female',
                    default                                                       => null,
                };
            }

            $birthDate = null;
            if (!empty($row['date_naissance'])) {
                try {
                    $birthDate = Carbon::parse($row['date_naissance'])->toDateString();
                } catch (\Exception) {
                }
            }

            $educationLevelId = null;
            if (!empty($row['niveau_etudes'])) {
                $educationLevelId = $this->educationLevelMap[trim((string) $row['niveau_etudes'])] ?? null;
            }

            try {
                $learner = Learner::create([
                    'first_name'                  => $firstName,
                    'last_name'                   => $lastName,
                    'email'                       => $email,
                    'phone'                       => !empty($row['telephone']) ? trim((string) $row['telephone']) : null,
                    'gender'                      => $gender,
                    'birth_date'                  => $birthDate,
                    'education_level_id'          => $educationLevelId,
                    'emergency_contact_name'      => !empty($row['contact_urgence_nom'])       ? trim((string) $row['contact_urgence_nom'])       : null,
                    'emergency_contact_firstname' => !empty($row['contact_urgence_prenom'])    ? trim((string) $row['contact_urgence_prenom'])    : null,
                    'emergency_contact_phone'     => !empty($row['contact_urgence_telephone']) ? trim((string) $row['contact_urgence_telephone']) : null,
                ]);

                $enrolledIds[$learner->id] = ['enrolled_at' => now(), 'status' => 'actif'];
                $this->imported++;
            } catch (\Exception) {
                $this->skipped++;
            }
        }

        if (!empty($enrolledIds)) {
            $this->cohort->learners()->syncWithoutDetaching($enrolledIds);
        }
    }
}
