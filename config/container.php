<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

// Chargement des variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$containerBuilder = new ContainerBuilder();

// Configuration en mode production
if ($_ENV['APP_ENV'] === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Chargement des configurations
$settings = require __DIR__ . '/database.php';
$jwtSettings = require __DIR__ . '/jwt.php';

$containerBuilder->addDefinitions($settings);
$containerBuilder->addDefinitions($jwtSettings);

// Configuration des services
$containerBuilder->addDefinitions([
    // Repositories
    \MyAuth\Repository\UserRepository::class => function (ContainerInterface $container) {
        return new \MyAuth\Repository\UserRepository($container->get(PDO::class));
    },
    
    \MyAuth\Repository\EmailVerificationRepository::class => function (ContainerInterface $container) {
        return new \MyAuth\Repository\EmailVerificationRepository($container->get(PDO::class));
    },
    
    \MyAuth\Repository\ServiceRepository::class => function (ContainerInterface $container) {
        return new \MyAuth\Repository\ServiceRepository();
    },
    
    \MyAuth\Repository\JwtBlacklistRepository::class => function (ContainerInterface $container) {
        return new \MyAuth\Repository\JwtBlacklistRepository($container->get(PDO::class));
    },
    
    \MyAuth\Repository\LoginAttemptRepository::class => function (ContainerInterface $container) {
        return new \MyAuth\Repository\LoginAttemptRepository($container->get(PDO::class));
    },

    // Services
    \MyAuth\Service\UserService::class => function (ContainerInterface $container) {
        $userService = new \MyAuth\Service\UserService(
            $container->get(\MyAuth\Repository\UserRepository::class),
            $container->get(\MyAuth\Service\EmailService::class)
        );
        $userService->setVerificationRepository(
            $container->get(\MyAuth\Repository\EmailVerificationRepository::class)
        );
        return $userService;
    },
    
    \MyAuth\Service\AuthService::class => function (ContainerInterface $container) {
        return new \MyAuth\Service\AuthService(
            $container->get(\MyAuth\Repository\UserRepository::class),
            $container->get(\MyAuth\Service\JwtService::class),
            $container->get(\MyAuth\Repository\LoginAttemptRepository::class)
        );
    },
    
    \MyAuth\Service\JwtService::class => function (ContainerInterface $container) {
        return new \MyAuth\Service\JwtService(
            $container->get('jwt'),
            $container->get(\MyAuth\Repository\JwtBlacklistRepository::class)
        );
    },
    
    \MyAuth\Service\EmailService::class => function (ContainerInterface $container) {
        return new \MyAuth\Service\EmailService();
    },
    
    \MyAuth\Service\ServiceAuthService::class => function (ContainerInterface $container) {
        return new \MyAuth\Service\ServiceAuthService(
            $container->get(\MyAuth\Repository\ServiceRepository::class)
        );
    },

    // Controllers
    \MyAuth\Controller\AuthController::class => function (ContainerInterface $container) {
        return new \MyAuth\Controller\AuthController(
            $container->get(\MyAuth\Service\AuthService::class),
            $container->get(\MyAuth\Service\UserService::class)
        );
    },

    // Middleware
    \MyAuth\Middleware\ApiKeyMiddleware::class => function (ContainerInterface $container) {
        return new \MyAuth\Middleware\ApiKeyMiddleware(
            $container->get(\MyAuth\Service\ServiceAuthService::class)
        );
    },
    
    \MyAuth\Middleware\JwtMiddleware::class => function (ContainerInterface $container) {
        return new \MyAuth\Middleware\JwtMiddleware(
            $container->get(\MyAuth\Service\JwtService::class)
        );
    },
    
    \MyAuth\Middleware\ServiceMiddleware::class => function (ContainerInterface $container) {
        return new \MyAuth\Middleware\ServiceMiddleware();
    }
]);

return $containerBuilder->build();
