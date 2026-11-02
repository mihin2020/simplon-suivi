<?php

namespace App\Exceptions;

use RuntimeException;

/** A reply cannot be sent; the message is meant to be shown to the user. */
class WhatsAppReplyRefused extends RuntimeException
{
    public static function emptyReply(): self
    {
        return new self('Message ou pièce jointe requis.');
    }

    public static function learnerWithoutPhone(): self
    {
        return new self('Cet apprenant n\'a pas de numéro de téléphone.');
    }
}
