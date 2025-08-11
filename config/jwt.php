<?php

declare(strict_types=1);

return [
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-this-in-production',
        'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
        'expiration' => (int)($_ENV['JWT_EXPIRATION'] ?? 3600), // 1 heure par défaut
        'issuer' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        'audience' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        'leeway' => 60 // 60 secondes de tolérance pour l'horloge
    ]
];
