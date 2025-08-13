<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use MyAuth\Middleware\ApiKeyMiddleware;
use MyAuth\Middleware\JwtMiddleware;
use MyAuth\Middleware\ServiceMiddleware;
use MyAuth\Controller\AuthController;

require __DIR__ . '/../vendor/autoload.php';

// Charger le container DI
$container = require __DIR__ . '/../config/container.php';

// Créer l'application Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// Configuration des erreurs
$app->addErrorMiddleware(true, true, true);

// Middleware global pour les CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Middleware pour les options CORS
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// Routes publiques (nécessitent seulement l'API key)
$app->group('/api/auth', function (RouteCollectorProxy $group) {
    $group->post('/register', [AuthController::class, 'register']);
    $group->post('/login', [AuthController::class, 'login']);
    $group->get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
    $group->post('/resend-verification', [AuthController::class, 'resendVerification']);
    $group->post('/refresh', [AuthController::class, 'refresh']);
})->add(ServiceMiddleware::class)->add(ApiKeyMiddleware::class);

// Routes protégées (nécessitent API key + JWT)
$app->group('/api/auth', function (RouteCollectorProxy $group) {
    $group->get('/me', [AuthController::class, 'me']);
    $group->post('/logout', [AuthController::class, 'logout']);
    $group->post('/logout-all', [AuthController::class, 'logoutAll']);
})->add(JwtMiddleware::class)->add(ServiceMiddleware::class)->add(ApiKeyMiddleware::class);

// Route de santé (sans authentification)
$app->get('/health', function ($request, $response) {
    $data = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0'
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Route de test de configuration
$app->get('/api/config/test', function ($request, $response) use ($container) {
    try {
        // Tester la connexion à la base de données
        $pdo = $container->get(PDO::class);
        $stmt = $pdo->query('SELECT 1');
        $dbStatus = $stmt ? 'connected' : 'error';
        
        // Tester la configuration JWT
        $jwtService = $container->get(\MyAuth\Service\JwtService::class);
        $jwtConfig = $jwtService->getConfig();
        
        // Tester la configuration email
        $emailService = $container->get(\MyAuth\Service\EmailService::class);
        $emailStatus = $emailService->testConfiguration() ? 'configured' : 'error';
        
        $data = [
            'database' => $dbStatus,
            'jwt' => [
                'algorithm' => $jwtConfig['algorithm'],
                'expiration' => $jwtConfig['expiration'] . 's'
            ],
            'email' => $emailStatus,
            'environment' => $_ENV['APP_ENV'] ?? 'unknown'
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $data = [
            'error' => true,
            'message' => $e->getMessage()
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
})->add(ServiceMiddleware::class)->add(ApiKeyMiddleware::class);

// Gestionnaire d'erreur 404
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    $data = [
        'error' => true,
        'message' => 'Route not found',
        'code' => 404
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
});

$app->run();
