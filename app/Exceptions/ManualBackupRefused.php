<?php

namespace App\Exceptions;

use RuntimeException;

/** A manual backup cannot be launched now; the message is meant to be shown to the user. */
class ManualBackupRefused extends RuntimeException
{
    public static function tooSoon(int $secondsToWait): self
    {
        return new self("Veuillez patienter {$secondsToWait} s avant de relancer un backup.");
    }

    public static function alreadyRunning(): self
    {
        return new self('Un backup est déjà en cours.');
    }
}
