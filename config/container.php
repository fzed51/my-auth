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
        // BASE DE DONNÉES
        // =================================================================
        
        PDO::class => function ($container): PDO {
            $config = $container->get('config.database');
            
            // Construction du DSN
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            
            try {
                $pdo = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
                
                return $pdo;
            } catch (PDOException $e) {
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
        
        // Les repositories seront automatiquement résolus via l'autoloader
        // grâce à l'autowiring de PHP-DI
        
        // =================================================================
        // SERVICES
        // =================================================================
        
        // Les services seront automatiquement résolus via l'autoloader
        // grâce à l'autowiring de PHP-DI
        
        // =================================================================
        // MIDDLEWARES
        // =================================================================
        
        // Les middlewares seront automatiquement résolus via l'autoloader
        // grâce à l'autowiring de PHP-DI
        
        // =================================================================
        // CONTROLLERS
        // =================================================================
        
        // Les contrôleurs seront automatiquement résolus via l'autoloader
        // grâce à l'autowiring de PHP-DI
        
    ]);
    
    // Configuration avancée du container
    
    // Activer l'autowiring pour la résolution automatique
    $containerBuilder->useAutowiring(true);
    
    // Activer l'autowiring
    $containerBuilder->useAutowiring(true);
    
    // Configuration de la compilation en production
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
        $containerBuilder->writeProxiesToFile(true, __DIR__ . '/../var/cache/proxies');
    }
};
