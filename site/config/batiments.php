<?php

/*
 * Bâtiments connus, utilisés par l'import de l'ancien site (groupes:importer).
 *
 * - « regroupements » : sur l'ancien site, « Court-Champ 1 » à « 4 » sont des salles réparties dans 3 bâtiments.
 * - « coordonnees » : position GPS (latitude, longitude) pour pointer la carte Google sur le bâtiment.
 */

return [

    'regroupements' => [
        'Echallens, Court-Champ' => [
            'Salle 1' => 'Echallens, Court-Champ 1',
            'Salle 2' => 'Echallens, Court-Champ 2',
            'Salle 3' => 'Echallens, Court-Champ 3-4',
            'Salle 4' => 'Echallens, Court-Champ 3-4',
        ],
    ],

    'coordonnees' => [
        'Echallens, Court-Champ 1' => [46.636370, 6.633660],
        'Echallens, Court-Champ 2' => [46.637020, 6.632850],
        'Echallens, Court-Champ 3-4' => [46.636380, 6.632780],
        'Echallens, château' => [46.641340, 6.630150],
        'Poliez-le-Grand' => [46.636520, 6.669635],
    ],

];
