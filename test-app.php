<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// Test simple sans container DI
echo "=== Test de l'application My Auth ===\n";

// Test 1: Vérifier que les classes existent
echo "1. Test des classes principales...\n";

$classes = [
    'MyAuth\Entity\User',
    'MyAuth\Service\JwtService', 
    'MyAuth\Service\UserService',
    'MyAuth\Repository\UserRepository',
    'MyAuth\Controller\AuthController'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "  ✓ $class\n";
    } else {
        echo "  ✗ $class - MANQUANTE\n";
    }
}

// Test 2: Test des variables d'environnement
echo "\n2. Test des variables d'environnement...\n";
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "  ✓ Fichier .env chargé\n";
    echo "  - APP_ENV: " . ($_ENV['APP_ENV'] ?? 'non défini') . "\n";
    echo "  - DB_HOST: " . ($_ENV['DB_HOST'] ?? 'non défini') . "\n";
} else {
    echo "  ✗ Fichier .env manquant\n";
}

echo "\n=== Test terminé ===\n";
