<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\UserRole;
use App\Models\Contract;
use App\Models\Letterhead;
use App\Models\Trainer;
use App\Models\User;

/**
 * Builders shared by the contract feature tests.
 */
trait ContractTestHelpers
{
    protected function trainer(array $user = [], array $trainer = []): Trainer
    {
        $account = User::factory()->create([
            'first_name' => 'Awa',
            'last_name' => 'Traoré',
            'email' => 'awa.traore@example.test',
            'role' => UserRole::Trainer,
            ...$user,
        ]);

        return Trainer::create([
            'user_id' => $account->id,
            'address' => 'Secteur 15, Ouagadougou',
            'id_number' => 'B1234567',
            'is_active' => true,
            ...$trainer,
        ]);
    }

    protected function contract(array $attributes = []): Contract
    {
        return Contract::create([
            'reference' => 'CTR-TEST-'.strtoupper(substr(md5((string) microtime(true).random_int(0, PHP_INT_MAX)), 0, 6)),
            'target' => ContractTarget::Trainer,
            'title' => 'Contrat de prestation',
            'body_html' => '<p>ENTRE LES SOUSSIGNÉS</p><h2>Article 1 : Objet</h2><p>Le présent contrat est conclu avec Awa Traoré.</p>',
            'data' => [],
            'status' => ContractStatus::Draft,
            'version' => 1,
            ...$attributes,
        ]);
    }

    protected function letterhead(array $attributes = []): Letterhead
    {
        return Letterhead::create([
            'name' => 'Officiel',
            'header_height_mm' => 30,
            'footer_height_mm' => 15,
            ...$attributes,
        ]);
    }
}
