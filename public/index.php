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

// Suppression des warnings de dépréciation PHP-DI (doit être fait en premier)
// Ces warnings ne sont pas critiques et viennent de la version de PHP-DI
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
if (!$isProduction) {
    // Masquer les warnings de dépréciation tout en gardant les autres erreurs
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Dotenv\Dotenv;

// Autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Vérification de la version minimale de PHP requise
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "Erreur : PHP 8.1.0 ou supérieur est requis. Version actuelle : " . PHP_VERSION;
    exit(1);
}

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
$corsMiddleware = $container->get('MyAuth\Middleware\CorsMiddleware');
$app->add($corsMiddleware);

// Middleware d'authentification par API Key
// Note: Les routes publiques sont configurées dans le container
$apiKeyMiddleware = $container->get('MyAuth\Middleware\ApiKeyMiddleware');
$app->add($apiKeyMiddleware);

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

// Route de débogage pour tester le middleware
$app->get('/debug-routes', function ($request, $response) {
    $path = '/api/auth/verify-email/test-token';
    $patterns = [
        '/',
        '/health',
        '/api/auth/test',
        '/api/auth/register',
        '/api/auth/verify-email/*',
        '/api/auth/resend-verification',
        '/api/docs*',
        '/api/status*',
    ];
    
    $results = [];
    foreach ($patterns as $pattern) {
        // Nouvelle logique de matching
        $tempPattern = str_replace('*', '__WILDCARD__', $pattern);
        $escaped = preg_quote($tempPattern, '/');
        $regex = str_replace('__WILDCARD__', '.*', $escaped);
        $matches = preg_match("/^{$regex}$/", $path) === 1;
        
        $results[] = [
            'pattern' => $pattern,
            'regex' => "^{$regex}$",
            'matches' => $matches
        ];
    }
    
    $data = [
        'test_path' => $path,
        'results' => $results
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
$app->group('/api/auth', function ($group) use ($container) {
    // User registration
    $group->post('/register', function ($request, $response) use ($container) {
        $authController = $container->get('MyAuth\Controller\AuthController');
        return $authController->register($request);
    });
    
    // Email verification
    $group->get('/verify-email/{token}', function ($request, $response, $args) use ($container) {
        $authController = $container->get('MyAuth\Controller\AuthController');
        // Ajouter le token aux attributs de la requête
        $request = $request->withAttribute('token', $args['token']);
        return $authController->verifyEmail($request);
    });
    
    // Resend verification email
    $group->post('/resend-verification', function ($request, $response) use ($container) {
        $authController = $container->get('MyAuth\Controller\AuthController');
        return $authController->resendVerification($request);
    });
    
    // Protected routes (require authentication)
    $group->group('', function ($group) use ($container) {
        // Get user profile
        $group->get('/profile', function ($request, $response) use ($container) {
            $authController = $container->get('MyAuth\Controller\AuthController');
            return $authController->getProfile($request);
        });
        
        // Update user profile
        $group->put('/profile', function ($request, $response) use ($container) {
            $authController = $container->get('MyAuth\Controller\AuthController');
            return $authController->updateProfile($request);
        });
        
        // Change password
        $group->post('/change-password', function ($request, $response) use ($container) {
            $authController = $container->get('MyAuth\Controller\AuthController');
            return $authController->changePassword($request);
        });
    }); // TODO: Add authentication middleware here when JWT auth is implemented
});

// Groupe de routes protégées pour tester l'authentification API Key
$app->group('/api/secure', function ($group) {
    
    // Route de test pour vérifier l'authentification par API Key
    $group->get('/test', function ($request, $response) {
        // Récupération du service authentifié depuis le middleware
        $authenticatedService = \MyAuth\Middleware\ApiKeyMiddleware::getAuthenticatedService($request);
        $serviceId = \MyAuth\Middleware\ApiKeyMiddleware::getServiceId($request);
        $serviceName = \MyAuth\Middleware\ApiKeyMiddleware::getServiceName($request);
        
        $data = [
            'message' => 'API Key authentication successful',
            'authenticated_service' => [
                'id' => $serviceId,
                'name' => $serviceName,
                'description' => $authenticatedService?->getDescription(),
                'rate_limit_per_minute' => $authenticatedService?->getRateLimitPerMinute(),
                'allowed_origins' => $authenticatedService?->getAllowedOrigins(),
            ],
            'timestamp' => date('c'),
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Route pour obtenir les statistiques des services
    $group->get('/services/stats', function ($request, $response) use ($container) {
        $serviceAuthService = $container->get('MyAuth\Service\ServiceAuthService');
        $stats = $serviceAuthService->getServicesStatistics();
        
        $data = [
            'message' => 'Services statistics',
            'statistics' => $stats,
            'timestamp' => date('c'),
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
