<?php

namespace App\Actions;

use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateTrainerAction
{
    public function execute(
        User $user,
        array $profileIds,
        ?string $phone,
        ?string $phone2,
        ?UploadedFile $cv,
        bool $removeCv = false,
        ?string $birthDate = null,
        ?string $birthPlace = null,
        ?string $address = null,
        ?string $idNumber = null,
    ): Trainer {
        $trainer = $user->trainer;
        $cvPath  = $trainer?->cv_path;

        if ($cv) {
            if ($cvPath) {
                Storage::disk('local')->delete($cvPath);
            }
            $cvPath = $cv->store('trainers/cvs', 'local');
        } elseif ($removeCv && $cvPath) {
            Storage::disk('local')->delete($cvPath);
            $cvPath = null;
        }

        $trainer = Trainer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone'       => $phone,
                'phone2'      => $phone2,
                'birth_date'  => $birthDate,
                'birth_place' => $birthPlace,
                'address'     => $address,
                'id_number'   => $idNumber,
                'cv_path'     => $cvPath,
            ],
        );

        $trainer->profiles()->sync($profileIds);

        return $trainer;
    }
}
