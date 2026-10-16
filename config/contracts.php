<?php

/*
 * Contracts - identity of the organisation that signs them.
 *
 * Quoted by the AI in the designation of the parties and in the signature
 * block. Taken from Simplon Burkina Faso's own contracts and website
 * (simplon.bf/contacts); update here when it changes.
 */
return [
    'organisation' => [
        'name' => env('CONTRACTS_ORG_NAME', 'SIMPLON BURKINA FASO'),
        'legal_form' => env('CONTRACTS_ORG_LEGAL_FORM', 'société par actions simplifiée'),
        'activity' => env('CONTRACTS_ORG_ACTIVITY', 'la formation dans le numérique'),
        'rccm' => env('CONTRACTS_ORG_RCCM', 'BF-OUA-01-2023-B17-06061'),
        'ifu' => env('CONTRACTS_ORG_IFU', '00202814A'),
        'address' => env('CONTRACTS_ORG_ADDRESS', 'Villa Simplon, quartier Somgandé, secteur 24, lot 21, parcelle 06, section LA, Ouagadougou'),
        'phone' => env('CONTRACTS_ORG_PHONE', '+226 66 81 71 71'),
        'email' => env('CONTRACTS_ORG_EMAIL', 'simplon.burkina@simplon.co'),
        'representative' => env('CONTRACTS_ORG_REPRESENTATIVE', 'Monsieur BAGRE Dominique'),
        'representative_title' => env('CONTRACTS_ORG_REPRESENTATIVE_TITLE', 'Représentant pays'),
        'city' => env('CONTRACTS_ORG_CITY', 'Ouagadougou'),
    ],
];
