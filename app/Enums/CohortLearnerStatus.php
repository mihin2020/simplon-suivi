<?php

namespace App\Enums;

/** Status of a learner inside one cohort (column cohort_learner.status). */
enum CohortLearnerStatus: string
{
    case Actif = 'actif';
    case Retrait = 'retrait';
    case Diplome = 'diplome';
    case Deplace = 'deplace';
}
