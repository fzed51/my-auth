<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use MyAuth\Repository\ServiceRepository;
use MyAuth\Service\ServiceAuthService;
use MyAuth\Repository\UserRepository;
use MyAuth\Service\JwtService;
use MyAuth\Service\UserService;
use MyAuth\Controller\AuthController;
use PDO;
use DI\Container;

/**
 * Test de la configuration du conteneur DI
 */
class ContainerConfigTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        // Chargement des variables d'environnement pour les tests
        if (!isset($_ENV['APP_ENV'])) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
            $dotenv->load();
        }

        // Chargement du container
        $this->container = require __DIR__ . '/../../../config/container.php';
    }

    public function testContainerCanBeLoaded(): void
    {
        $this->assertInstanceOf(Container::class, $this->container);
    }

    public function testPdoConnectionCanBeResolved(): void
    {
        $pdo = $this->container->get(PDO::class);
        $this->assertInstanceOf(PDO::class, $pdo);
        
        // Test de connexion simple
        $stmt = $pdo->query('SELECT 1 as test');
        $result = $stmt->fetch();
        $this->assertEquals(1, $result['test']);
    }

    public function testServiceRepositoryCanBeResolved(): void
    {
        $serviceRepo = $this->container->get(ServiceRepository::class);
        $this->assertInstanceOf(ServiceRepository::class, $serviceRepo);
    }

    public function testServiceAuthServiceCanBeResolved(): void
    {
        $serviceAuth = $this->container->get(ServiceAuthService::class);
        $this->assertInstanceOf(ServiceAuthService::class, $serviceAuth);
    }

    public function testUserRepositoryCanBeResolved(): void
    {
        $userRepo = $this->container->get(UserRepository::class);
        $this->assertInstanceOf(UserRepository::class, $userRepo);
    }

    public function testJwtServiceCanBeResolved(): void
    {
        $jwtService = $this->container->get(JwtService::class);
        $this->assertInstanceOf(JwtService::class, $jwtService);
    }

    public function testUserServiceCanBeResolved(): void
    {
        $userService = $this->container->get(UserService::class);
        $this->assertInstanceOf(UserService::class, $userService);
    }

    public function testAuthControllerCanBeResolved(): void
    {
        $authController = $this->container->get(AuthController::class);
        $this->assertInstanceOf(AuthController::class, $authController);
    }
}
