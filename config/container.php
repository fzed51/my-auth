<?php

declare(strict_types=1);

/**
 * Configuration du conteneur d'injection de dépendances PHP-DI
 * 
 * Ce fichier configure l'injection de dépendances pour l'ensemble de l'application.
 * Il définit comment instancier et configurer tous les services et repositories.
 * 
 * @package MyAuth\Config
 */

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

return function (ContainerBuilder $containerBuilder): void {
    
    // Chargement des configurations
    $databaseConfig = require __DIR__ . '/database.php';
    $jwtConfig = require __DIR__ . '/jwt.php';
    
    // Configuration du container
    $containerBuilder->addDefinitions([
        
        // =================================================================
        // CONFIGURATION
        // =================================================================
        
        'config.database' => $databaseConfig,
        'config.jwt' => $jwtConfig,
        'config.services' => function (): array {
            $servicesFile = __DIR__ . '/services.json';
            if (!file_exists($servicesFile)) {
                throw new RuntimeException('Services configuration file not found: ' . $servicesFile);
            }
            
            $content = file_get_contents($servicesFile);
            if ($content === false) {
                throw new RuntimeException('Cannot read services configuration file');
            }
            
            $services = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON in services configuration: ' . json_last_error_msg());
            }
            
            return $services;
        },
        
        // =================================================================
        // MIDDLEWARES
        // =================================================================
        
        'MyAuth\Middleware\CorsMiddleware' => function (): \MyAuth\Middleware\CorsMiddleware {
            // Utiliser la factory appropriée selon l'environnement
            $environment = $_ENV['APP_ENV'] ?? 'development';
            
            if ($environment === 'production') {
                // En production, utiliser les origines spécifiques depuis la config
                $allowedOrigins = !empty($_ENV['CORS_ALLOWED_ORIGINS']) 
                    ? explode(',', $_ENV['CORS_ALLOWED_ORIGINS'])
                    : ['https://myapp.com']; // Fallback sécurisé
                    
                return \MyAuth\Middleware\CorsMiddleware::forProduction($allowedOrigins);
            } else {
                // En développement, utiliser la factory depuis l'environnement
                return \MyAuth\Middleware\CorsMiddleware::fromEnvironment();
            }
        },
        
        'MyAuth\Middleware\ApiKeyMiddleware' => function (ContainerInterface $container): \MyAuth\Middleware\ApiKeyMiddleware {
            $serviceAuthService = $container->get('MyAuth\Service\ServiceAuthService');
            $responseFactory = $container->get('Psr\Http\Message\ResponseFactoryInterface');
            
            // Configuration des routes publiques par défaut
            return \MyAuth\Middleware\ApiKeyMiddleware::withDefaultPublicRoutes(
                $serviceAuthService,
                $responseFactory
            );
        },
        
        // =================================================================
        // BASE DE DONNÉES
        // =================================================================
        
        PDO::class => function ($container): PDO {
            // Pour les tests sans base de données, on peut retourner un mock
            if (($_ENV['APP_ENV'] ?? 'development') === 'testing-no-db') {
                throw new RuntimeException('Database disabled for testing');
            }
            
            $config = $container->get('config.database');
            $dbConfig = $config['database']; // Accès au sous-tableau 'database'
            
            // Construction du DSN
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['dbname'],
                $dbConfig['charset']
            );
            
            try {
                $pdo = new PDO(
                    $dsn,
                    $dbConfig['username'],
                    $dbConfig['password'],
                    $dbConfig['options']
                );
                
                return $pdo;
            } catch (PDOException $e) {
                // Pour les tests de développement, créer un SQLite en mémoire
                if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
                    error_log('MySQL connection failed, using SQLite for testing: ' . $e->getMessage());
                    $pdo = new PDO('sqlite::memory:');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Créer les tables de base pour les tests
                    $createTablesFunc = function(PDO $pdo) {
                        $pdo->exec("
                            CREATE TABLE users (
                                id TEXT PRIMARY KEY,
                                email TEXT UNIQUE NOT NULL,
                                password_hash TEXT NOT NULL,
                                first_name TEXT,
                                last_name TEXT,
                                is_active INTEGER DEFAULT 0,
                                is_verified INTEGER DEFAULT 0,
                                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                            )
                        ");
                        
                        $pdo->exec("
                            CREATE TABLE email_verifications (
                                id TEXT PRIMARY KEY,
                                user_id TEXT NOT NULL,
                                token TEXT UNIQUE NOT NULL,
                                expires_at TEXT NOT NULL,
                                is_used INTEGER DEFAULT 0,
                                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                                used_at TEXT,
                                FOREIGN KEY (user_id) REFERENCES users(id)
                            )
                        ");
                    };
                    
                    $createTablesFunc($pdo);
                    
                    return $pdo;
                }
                
                throw new RuntimeException(
                    'Database connection failed: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        },
        
        // =================================================================
        // REPOSITORIES
        // =================================================================
        
        'MyAuth\Repository\ServiceRepository' => function (ContainerInterface $container): \MyAuth\Repository\ServiceRepository {
            $servicesConfigPath = __DIR__ . '/services.json';
            return new \MyAuth\Repository\ServiceRepository($servicesConfigPath);
        },
        
        'MyAuth\Repository\UserRepository' => function (ContainerInterface $container): \MyAuth\Repository\UserRepository {
            $pdo = $container->get(PDO::class);
            return new \MyAuth\Repository\UserRepository($pdo);
        },
        
        'MyAuth\Repository\EmailVerificationRepository' => function (ContainerInterface $container): \MyAuth\Repository\EmailVerificationRepository {
            $pdo = $container->get(PDO::class);
            return new \MyAuth\Repository\EmailVerificationRepository($pdo);
        },
        
        // =================================================================
        // SERVICES
        // =================================================================
        
        'MyAuth\Service\ServiceAuthService' => function (ContainerInterface $container): \MyAuth\Service\ServiceAuthService {
            $serviceRepository = $container->get('MyAuth\Repository\ServiceRepository');
            return new \MyAuth\Service\ServiceAuthService($serviceRepository);
        },
        
        'MyAuth\Service\UserService' => function (ContainerInterface $container): \MyAuth\Service\UserService {
            $userRepository = $container->get('MyAuth\Repository\UserRepository');
            $emailVerificationRepository = $container->get('MyAuth\Repository\EmailVerificationRepository');
            $emailService = $container->get('MyAuth\Service\EmailService');
            return new \MyAuth\Service\UserService($userRepository, $emailVerificationRepository, $emailService);
        },
        
        'MyAuth\Service\EmailService' => function (ContainerInterface $container): \MyAuth\Service\EmailService {
            return new \MyAuth\Service\EmailService();
        },
        
        // =================================================================
        // MIDDLEWARES
        // =================================================================
        
        // Les middlewares seront automatiquement résolus via l'autoloader
        // grâce à l'autowiring de PHP-DI
        
        // =================================================================
        // CONTROLLERS
        // =================================================================
        
        'MyAuth\Controller\AuthController' => function (ContainerInterface $container): \MyAuth\Controller\AuthController {
            $userService = $container->get('MyAuth\Service\UserService');
            $responseFactory = $container->get('Psr\Http\Message\ResponseFactoryInterface');
            return new \MyAuth\Controller\AuthController($userService, $responseFactory);
        },
        
        // =================================================================
        // HTTP FACTORIES
        // =================================================================
        
        'Psr\Http\Message\ResponseFactoryInterface' => function (): \Psr\Http\Message\ResponseFactoryInterface {
            return new \Slim\Psr7\Factory\ResponseFactory();
        },
        
    ]);
    
    // Configuration avancée du container
    
    // Activer l'autowiring pour la résolution automatique
    $containerBuilder->useAutowiring(true);
    
    // Configuration de la compilation en production
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
        $containerBuilder->writeProxiesToFile(true, __DIR__ . '/../var/cache/proxies');
    }
};
