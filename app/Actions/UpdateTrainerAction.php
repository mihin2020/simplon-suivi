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
        ?string $profileId,
        ?string $phone,
        ?string $phone2,
        ?UploadedFile $cv,
        bool $removeCv = false,
    ): Trainer {
        $trainer = $user->trainer;
        $cvPath  = $trainer?->cv_path;

        if ($cv) {
            if ($cvPath) {
                Storage::disk('public')->delete($cvPath);
            }
            $cvPath = $cv->store('trainers/cvs', 'public');
        } elseif ($removeCv && $cvPath) {
            Storage::disk('public')->delete($cvPath);
            $cvPath = null;
        }

        return Trainer::updateOrCreate(
            ['user_id' => $user->id],
            ['profile_id' => $profileId, 'phone' => $phone, 'phone2' => $phone2, 'cv_path' => $cvPath],
        );
    }
}
