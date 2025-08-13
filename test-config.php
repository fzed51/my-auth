<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// Chargement des variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== Test de Configuration ===\n";

// Test de chargement du container
try {
    $container = require __DIR__ . '/config/container.php';
    echo "✓ Container DI chargé avec succès\n";
} catch (Exception $e) {
    echo "✗ Erreur lors du chargement du container: " . $e->getMessage() . "\n";
    exit(1);
}

// Test de connexion à la base de données
try {
    $pdo = $container->get(PDO::class);
    $stmt = $pdo->query('SELECT 1');
    echo "✓ Connexion à la base de données réussie\n";
} catch (Exception $e) {
    echo "✗ Erreur de connexion à la base: " . $e->getMessage() . "\n";
}

// Test du ServiceRepository
try {
    $serviceRepo = $container->get(\MyAuth\Repository\ServiceRepository::class);
    $service = $serviceRepo->findByApiKey('test-api-key-frontend');
    if ($service) {
        echo "✓ Service trouvé: " . $service['name'] . "\n";
        echo "  - Permissions: " . implode(', ', $service['permissions']) . "\n";
        echo "  - Actif: " . ($service['is_active'] ? 'Oui' : 'Non') . "\n";
    } else {
        echo "✗ Service non trouvé avec l'API key test-api-key-frontend\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur ServiceRepository: " . $e->getMessage() . "\n";
}

// Test du ServiceAuthService
try {
    $serviceAuth = $container->get(\MyAuth\Service\ServiceAuthService::class);
    
    // Test d'extraction d'API key
    $headers = ['X-API-Key' => ['test-api-key-frontend']];
    $apiKey = $serviceAuth->extractApiKeyFromHeaders($headers);
    echo "✓ API Key extraite: " . ($apiKey ?: 'null') . "\n";
    
    // Test d'authentification
    $service = $serviceAuth->authenticateService('test-api-key-frontend');
    echo "✓ Service authentifié: " . $service['name'] . "\n";
    
    // Test de validation d'accès
    $canAccess = $serviceAuth->validateServiceAccess($service, '/api/auth/register', 'POST');
    echo "✓ Accès à /api/auth/register: " . ($canAccess ? 'Autorisé' : 'Refusé') . "\n";
    
} catch (Exception $e) {
    echo "✗ Erreur ServiceAuthService: " . $e->getMessage() . "\n";
}

echo "\n=== Test terminé ===\n";
