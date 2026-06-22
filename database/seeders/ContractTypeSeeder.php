<?php

namespace Database\Seeders;

use App\Enums\ContractTypeContext;
use App\Models\ContractType;
use Illuminate\Database\Seeder;

class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ContractTypeContext::Internship->value => [
                'Contrat de stage',
                'Stage étudiant',
                "Contrat d'apprentissage",
            ],
            ContractTypeContext::Employment->value => [
                'CDI',
                'CDD',
                'Freelance',
                'Autre',
            ],
        ];

        foreach ($types as $context => $names) {
            foreach ($names as $order => $name) {
                ContractType::firstOrCreate(
                    ['name' => $name, 'context' => $context],
                    ['order' => $order],
                );
            }
        }
    }
}
