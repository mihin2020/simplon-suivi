<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour l'intégration Cloudinary - stockage médias photos/vidéos
    |
    */

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
    'api_key' => env('CLOUDINARY_API_KEY', ''),
    'api_secret' => env('CLOUDINARY_API_SECRET', ''),
    
    /*
    | Upload preset pour les médias de formations
    | Créer un preset "unsigned" dans votre dashboard Cloudinary
    */
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET', 'simplon_medias'),
    
    /*
    | Dossier par défaut pour les uploads
    */
    'folder' => env('CLOUDINARY_FOLDER', 'simplon-suivi'),
    
    /*
    | Limite de stockage gratuite (25 GB)
    */
    'free_storage_limit' => 25 * 1024 * 1024 * 1024, // 25 GB en bytes
];
