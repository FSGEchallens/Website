<?php

/*
 * Réglages Livewire propres au site (les autres valeurs restent celles par défaut de Livewire).
 */

return [

    /*
    | Envoi temporaire des fichiers (photos, logos, PDF) : limite relevée à 20 Mo
    | (12 Mo par défaut, trop juste pour certaines photos de téléphone).
    | Les limites précises par type de fichier sont vérifiées ensuite par chaque formulaire.
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:20480'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
