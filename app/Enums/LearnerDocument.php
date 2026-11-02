<?php

namespace App\Enums;

/** The identity and CV scans kept on a learner, which only authorised staff may open. */
enum LearnerDocument: string
{
    case Cnib = 'cnib';
    case Cv = 'cv';

    public function pathColumn(): string
    {
        return $this->value.'_path';
    }

    public function nameColumn(): string
    {
        return $this->value.'_original_name';
    }
}
