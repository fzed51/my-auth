<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PHPUnit\Framework\TestCase;
use MyAuth\Entity\Service;

class ServiceRepositoryTest extends TestCase
{
    private ServiceRepository $repository;
    private string $tempConfigPath;

    protected function setUp(): void
    {
        // Création d'un fichier de configuration temporaire
        $this->tempConfigPath = tempnam(sys_get_temp_dir(), 'services_test_');

        $testConfig = [
            'services' => [
                [
                    'id' => '123e4567-e89b-12d3-a456-426614174000',
                    'name' => 'Test Service 1',
                    'api_key' => 'test-api-key-1234567890abcdef',
                    'description' => 'Premier service de test',
                    'is_active' => true,
                    'allowed_origins' => ['https://example.com'],
                    'rate_limit_per_minute' => 100,
                ],
                [
                    'id' => '456e7890-e89b-12d3-a456-426614174001',
                    'name' => 'Test Service 2',
                    'api_key' => 'test-api-key-abcdef1234567890',
                    'description' => 'Deuxième service de test',
                    'is_active' => false,
                    'allowed_origins' => ['*.test.com'],
                    'rate_limit_per_minute' => 50,
                ],
                [
                    'id' => '789e0123-e89b-12d3-a456-426614174002',
                    'name' => 'Test Service 3',
                    'api_key' => 'test-api-key-fedcba0987654321',
                    'description' => 'Troisième service de test',
                    'is_active' => true,
                    'allowed_origins' => [],
                    'rate_limit_per_minute' => 200,
                ],
            ],
        ];

        file_put_contents($this->tempConfigPath, json_encode($testConfig, JSON_PRETTY_PRINT));

        $this->repository = new ServiceRepository($this->tempConfigPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfigPath)) {
            unlink($this->tempConfigPath);
        }
    }

    public function testFindByApiKey(): void
    {
        $service = $this->repository->findByApiKey('test-api-key-1234567890abcdef');

        $this->assertInstanceOf(Service::class, $service);
        $this->assertEquals('Test Service 1', $service->getName());
        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $service->getId());
    }

    public function testFindByApiKeyNotFound(): void
    {
        $service = $this->repository->findByApiKey('inexistent-api-key');

        $this->assertNull($service);
    }

    public function testFindById(): void
    {
        $service = $this->repository->findById('123e4567-e89b-12d3-a456-426614174000');

        $this->assertInstanceOf(Service::class, $service);
        $this->assertEquals('Test Service 1', $service->getName());
        $this->assertEquals('test-api-key-1234567890abcdef', $service->getApiKey());
    }

    public function testFindByIdNotFound(): void
    {
        $service = $this->repository->findById('inexistent-id');

        $this->assertNull($service);
    }

    public function testFindByName(): void
    {
        $service = $this->repository->findByName('Test Service 2');

        $this->assertInstanceOf(Service::class, $service);
        $this->assertEquals('456e7890-e89b-12d3-a456-426614174001', $service->getId());
        $this->assertFalse($service->isActive());
    }

    public function testFindByNameNotFound(): void
    {
        $service = $this->repository->findByName('Inexistent Service');

        $this->assertNull($service);
    }

    public function testFindAllActive(): void
    {
        $services = $this->repository->findAllActive();

        $this->assertCount(2, $services);
        foreach ($services as $service) {
            $this->assertInstanceOf(Service::class, $service);
            $this->assertTrue($service->isActive());
        }
    }

    public function testFindAll(): void
    {
        $services = $this->repository->findAll();

        $this->assertCount(3, $services);
        foreach ($services as $service) {
            $this->assertInstanceOf(Service::class, $service);
        }
    }

    public function testIsServiceActiveByApiKey(): void
    {
        $this->assertTrue($this->repository->isServiceActiveByApiKey('test-api-key-1234567890abcdef'));
        $this->assertFalse($this->repository->isServiceActiveByApiKey('test-api-key-abcdef1234567890'));
        $this->assertFalse($this->repository->isServiceActiveByApiKey('inexistent-api-key'));
    }

    public function testCountActive(): void
    {
        $count = $this->repository->countActive();

        $this->assertEquals(2, $count);
    }

    public function testClearCache(): void
    {
        // Charge les services une première fois
        $services1 = $this->repository->findAll();
        $this->assertCount(3, $services1);

        // Modifie le fichier de configuration
        $newConfig = ['services' => []];
        file_put_contents($this->tempConfigPath, json_encode($newConfig));

        // Sans vider le cache, les anciens services sont toujours là
        $services2 = $this->repository->findAll();
        $this->assertCount(3, $services2);

        // Après avoir vidé le cache, les nouveaux services sont chargés
        $this->repository->clearCache();
        $services3 = $this->repository->findAll();
        $this->assertCount(0, $services3);
    }

    public function testValidateConfigurationWithErrors(): void
    {
        // Création d'une configuration invalide
        $invalidConfig = [
            'services' => [
                [
                    'id' => 'invalid-uuid',
                    'name' => 'Test Service',
                    'api_key' => 'short',
                    'description' => '',
                    'is_active' => 'not_boolean',
                    'allowed_origins' => 'not_array',
                    'rate_limit_per_minute' => -1,
                ],
                [
                    'id' => 'invalid-uuid', // Dupliqué
                    'name' => 'Test Service', // Dupliqué
                    'api_key' => 'short', // Dupliqué
                ],
            ],
        ];

        file_put_contents($this->tempConfigPath, json_encode($invalidConfig));
        $this->repository->clearCache();

        $errors = $this->repository->validateConfiguration();

        $this->assertNotEmpty($errors);
        $this->assertContains('Service index 0: Champ requis manquant ou vide: description', $errors);
        $this->assertStringContainsString('ID doit être un UUID valide', implode(' ', $errors));
        $this->assertStringContainsString('Format d\'API key invalide', implode(' ', $errors));
        $this->assertStringContainsString('is_active\' doit être un booléen', implode(' ', $errors));
        $this->assertStringContainsString('allowed_origins\' doit être un tableau', implode(' ', $errors));
        $this->assertStringContainsString('rate_limit_per_minute\' doit être un entier positif', implode(' ', $errors));
    }

    public function testLoadServicesWithMissingFile(): void
    {
        $invalidRepository = new ServiceRepository('/path/to/nonexistent/file.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration des services introuvable');

        $invalidRepository->findAll();
    }

    public function testLoadServicesWithInvalidJson(): void
    {
        file_put_contents($this->tempConfigPath, 'invalid json content');
        $this->repository->clearCache();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fichier de configuration des services invalide');

        $this->repository->findAll();
    }

    public function testLoadServicesWithInvalidStructure(): void
    {
        file_put_contents($this->tempConfigPath, json_encode(['invalid' => 'structure']));
        $this->repository->clearCache();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Structure de configuration des services invalide');

        $this->repository->findAll();
    }
}
