<?php

return [
    // Mot de passe du back-office. Vide = back-office fermé.
    'admin_password' => env('ADMIN_PASSWORD', ''),

    // Relances de la boucle de retour, en jours après la réponse.
    'relances_jours' => [3, 10],
];
