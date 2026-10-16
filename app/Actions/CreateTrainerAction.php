<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Trainer;
use Illuminate\Http\UploadedFile;

class CreateTrainerAction
{
    public function __construct(private InviteUser $inviteUser)
    {
    }

    public function execute(
        string $firstName,
        string $lastName,
        string $email,
        ?string $profileId,
        ?string $phone,
        ?string $phone2,
        ?UploadedFile $cv,
        ?string $birthDate = null,
        ?string $birthPlace = null,
        ?string $address = null,
        ?string $idNumber = null,
    ): Trainer {
        $user = $this->inviteUser->execute(
            firstName: $firstName,
            lastName:  $lastName,
            email:     $email,
            role:      UserRole::Trainer,
        );

        $cvPath = $cv?->store('trainers/cvs', 'public');

        return Trainer::create([
            'user_id'     => $user->id,
            'profile_id'  => $profileId,
            'phone'       => $phone,
            'phone2'      => $phone2,
            'birth_date'  => $birthDate,
            'birth_place' => $birthPlace,
            'address'     => $address,
            'id_number'   => $idNumber,
            'cv_path'     => $cvPath,
        ]);
    }
}
