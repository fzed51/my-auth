<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * Test de la configuration de l'environnement
 */
class EnvironmentConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Chargement des variables d'environnement pour les tests
        if (!isset($_ENV['APP_ENV'])) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
            $dotenv->load();
        }
    }

    public function testEnvFileExists(): void
    {
        $envFile = __DIR__ . '/../../../.env';
        $this->assertFileExists($envFile, 'Le fichier .env doit exister');
    }

    public function testRequiredEnvironmentVariablesAreSet(): void
    {
        $requiredVars = [
            'APP_ENV',
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'JWT_SECRET',
            'JWT_ALGORITHM',
            'JWT_EXPIRATION'
        ];

        foreach ($requiredVars as $var) {
            $this->assertArrayHasKey($var, $_ENV, "La variable d'environnement {$var} doit être définie");
            $this->assertNotEmpty($_ENV[$var], "La variable d'environnement {$var} ne doit pas être vide");
        }
    }

    public function testDatabaseConfiguration(): void
    {
        $this->assertIsString($_ENV['DB_HOST']);
        $this->assertIsNumeric($_ENV['DB_PORT']);
        $this->assertGreaterThan(0, (int)$_ENV['DB_PORT']);
        $this->assertLessThanOrEqual(65535, (int)$_ENV['DB_PORT']);
    }

    public function testJwtConfiguration(): void
    {
        $this->assertIsString($_ENV['JWT_SECRET']);
        $this->assertGreaterThanOrEqual(32, strlen($_ENV['JWT_SECRET']), 'Le secret JWT doit faire au moins 32 caractères');
        
        $this->assertContains($_ENV['JWT_ALGORITHM'], ['HS256', 'HS384', 'HS512'], 'Algorithme JWT invalide');
        
        $this->assertIsNumeric($_ENV['JWT_EXPIRATION']);
        $this->assertGreaterThan(0, (int)$_ENV['JWT_EXPIRATION']);
    }

    public function testAppEnvironment(): void
    {
        $validEnvs = ['development', 'testing', 'production'];
        $this->assertContains($_ENV['APP_ENV'], $validEnvs, 'APP_ENV doit être development, testing ou production');
    }
}
