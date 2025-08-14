<?php

declare(strict_types=1);

/**
 * Point d'entrée principal de l'application
 * 
 * Ce fichier initialise l'application Slim avec PHP-DI et configure
 * les routes, middlewares et gestion d'erreurs.
 * 
 * @package MyAuth
 */

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Dotenv\Dotenv;

// Autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Chargement des variables d'environnement
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configuration des erreurs selon l'environnement
$displayErrorDetails = ($_ENV['APP_ENV'] ?? 'development') !== 'production';
$logErrors = true;
$logErrorDetails = $displayErrorDetails;

// Configuration du conteneur d'injection de dépendances
$containerBuilder = new ContainerBuilder();

// Configuration PHP-DI
if (!$displayErrorDetails) {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Chargement de la configuration du container
$containerConfig = require __DIR__ . '/../config/container.php';
$containerConfig($containerBuilder);

// Construction du container
$container = $containerBuilder->build();

// Configuration de Slim avec PHP-DI
AppFactory::setContainer($container);
$app = AppFactory::create();

// Configuration de la détection de la base URL
$app->setBasePath('');

// =============================================================================
// MIDDLEWARES GLOBAUX
// =============================================================================

// Middleware de gestion des erreurs
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails);

// Middleware CORS (Cross-Origin Resource Sharing)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Middleware pour les requêtes OPTIONS (preflight CORS)
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Middleware de parsing du body JSON
$app->addBodyParsingMiddleware();

// Middleware de routing
$app->addRoutingMiddleware();

// =============================================================================
// ROUTES
// =============================================================================

// Route de santé pour vérifier que l'API fonctionne
$app->get('/', function ($request, $response) {
    $data = [
        'service' => 'MyAuth API',
        'version' => '1.0.0',
        'status' => 'healthy',
        'timestamp' => date('c'),
        'environment' => $_ENV['APP_ENV'] ?? 'development',
    ];
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Route de health check
$app->get('/health', function ($request, $response) {
    // TODO: Ajouter des vérifications de santé (DB, services externes, etc.)
    $health = [
        'status' => 'healthy',
        'checks' => [
            'database' => 'pending', // Sera implémenté dans les prochaines étapes
            'memory' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
            ],
        ],
        'timestamp' => date('c'),
    ];
    
    $response->getBody()->write(json_encode($health, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Groupe de routes pour l'API d'authentification
$app->group('/api/auth', function ($group) {
    // Les routes d'authentification seront ajoutées dans les prochaines étapes
    
    // Route de test pour vérifier la structure
    $group->get('/test', function ($request, $response) {
        $data = [
            'message' => 'Auth API endpoint ready',
            'available_endpoints' => [
                'POST /api/auth/register' => 'User registration (coming soon)',
                'POST /api/auth/login' => 'User login (coming soon)',
                'GET /api/auth/verify-email/{token}' => 'Email verification (coming soon)',
            ],
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });
});

// =============================================================================
// GESTION DES ERREURS 404
// =============================================================================

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    $data = [
        'error' => 'Not Found',
        'message' => 'The requested endpoint was not found',
        'path' => $request->getUri()->getPath(),
        'method' => $request->getMethod(),
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
});

// =============================================================================
// LANCEMENT DE L'APPLICATION
// =============================================================================

try {
    // Création de la requête à partir des globals PHP
    $serverRequestCreator = ServerRequestCreatorFactory::create();
    $request = $serverRequestCreator->createServerRequestFromGlobals();
    
    // Traitement de la requête
    $response = $app->handle($request);
    
    // Envoi de la réponse avec l'émetteur de réponse
    $responseEmitter = new \Slim\ResponseEmitter();
    $responseEmitter->emit($response);
    
} catch (Throwable $e) {
    // Gestion des erreurs fatales
    if ($displayErrorDetails) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    } else {
        echo "Internal Server Error";
    }
    exit(1);
}
