<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use MyAuth\Repository\ServiceRepository;

/**
 * @covers \MyAuth\Repository\ServiceRepository
 */
class ServiceRepositoryTest extends TestCase
{
    private ServiceRepository $serviceRepository;
    private string $testConfigPath;

    protected function setUp(): void
    {
        // Créer un fichier de configuration de test
        $this->testConfigPath = sys_get_temp_dir() . '/test_services.json';
        
        $testServices = [
            [
                'id' => 1,
                'name' => 'test-app',
                'api_key' => 'test-api-key-123',
                'description' => 'Application de test',
                'is_active' => true,
                'permissions' => ['auth:login', 'auth:register', 'user:read'],
                'rate_limit' => [
                    'requests_per_minute' => 60,
                    'requests_per_hour' => 1000
                ]
            ],
            [
                'id' => 2,
                'name' => 'admin-app',
                'api_key' => 'admin-api-key-456',
                'description' => 'Application admin',
                'is_active' => true,
                'permissions' => ['admin:*'],
                'rate_limit' => [
                    'requests_per_minute' => 120
                ]
            ],
            [
                'id' => 3,
                'name' => 'disabled-app',
                'api_key' => 'disabled-api-key-789',
                'description' => 'Application désactivée',
                'is_active' => false,
                'permissions' => ['auth:login']
            ]
        ];
        
        file_put_contents($this->testConfigPath, json_encode($testServices));
        
        $this->serviceRepository = new ServiceRepository($this->testConfigPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testConfigPath)) {
            unlink($this->testConfigPath);
        }
    }

    public function testFindByApiKey(): void
    {
        $service = $this->serviceRepository->findByApiKey('test-api-key-123');
        
        $this->assertNotNull($service);
        $this->assertEquals('test-app', $service['name']);
        $this->assertEquals(1, $service['id']);
        $this->assertTrue($service['is_active']);
    }

    public function testFindByApiKeyNotFound(): void
    {
        $service = $this->serviceRepository->findByApiKey('non-existent-key');
        
        $this->assertNull($service);
    }

    public function testFindByName(): void
    {
        $service = $this->serviceRepository->findByName('admin-app');
        
        $this->assertNotNull($service);
        $this->assertEquals(2, $service['id']);
        $this->assertEquals('admin-api-key-456', $service['api_key']);
    }

    public function testFindByNameNotFound(): void
    {
        $service = $this->serviceRepository->findByName('non-existent-app');
        
        $this->assertNull($service);
    }

    public function testFindById(): void
    {
        $service = $this->serviceRepository->findById(1);
        
        $this->assertNotNull($service);
        $this->assertEquals('test-app', $service['name']);
        $this->assertEquals('test-api-key-123', $service['api_key']);
    }

    public function testFindByIdNotFound(): void
    {
        $service = $this->serviceRepository->findById(999);
        
        $this->assertNull($service);
    }

    public function testFindAllActive(): void
    {
        $activeServices = $this->serviceRepository->findAllActive();
        
        $this->assertCount(2, $activeServices);
        
        $names = array_column($activeServices, 'name');
        $this->assertContains('test-app', $names);
        $this->assertContains('admin-app', $names);
        $this->assertNotContains('disabled-app', $names);
    }

    public function testFindAll(): void
    {
        $allServices = $this->serviceRepository->findAll();
        
        $this->assertCount(3, $allServices);
        
        $names = array_column($allServices, 'name');
        $this->assertContains('test-app', $names);
        $this->assertContains('admin-app', $names);
        $this->assertContains('disabled-app', $names);
    }

    public function testHasPermission(): void
    {
        $service = $this->serviceRepository->findByName('test-app');
        
        // Permission exacte
        $this->assertTrue($this->serviceRepository->hasPermission($service, 'auth:login'));
        $this->assertTrue($this->serviceRepository->hasPermission($service, 'auth:register'));
        $this->assertTrue($this->serviceRepository->hasPermission($service, 'user:read'));
        
        // Permission non accordée
        $this->assertFalse($this->serviceRepository->hasPermission($service, 'admin:users'));
        
        // Test avec wildcard
        $adminService = $this->serviceRepository->findByName('admin-app');
        $this->assertTrue($this->serviceRepository->hasPermission($adminService, 'admin:users'));
        $this->assertTrue($this->serviceRepository->hasPermission($adminService, 'admin:stats'));
        $this->assertFalse($this->serviceRepository->hasPermission($adminService, 'user:delete'));
    }

    public function testHasPermissionWithoutPermissions(): void
    {
        $service = ['name' => 'test', 'api_key' => 'test'];
        
        $this->assertFalse($this->serviceRepository->hasPermission($service, 'any:permission'));
    }

    public function testCheckRateLimit(): void
    {
        $service = $this->serviceRepository->findByName('test-app');
        
        $this->assertEquals(60, $this->serviceRepository->checkRateLimit($service, 'requests_per_minute'));
        $this->assertEquals(1000, $this->serviceRepository->checkRateLimit($service, 'requests_per_hour'));
        $this->assertNull($this->serviceRepository->checkRateLimit($service, 'requests_per_day'));
    }

    public function testCheckRateLimitWithoutRateLimit(): void
    {
        $service = ['name' => 'test', 'api_key' => 'test'];
        
        $this->assertNull($this->serviceRepository->checkRateLimit($service, 'requests_per_minute'));
    }

    public function testValidateService(): void
    {
        $validService = [
            'id' => 1,
            'name' => 'test-app',
            'api_key' => 'test-key',
            'is_active' => true
        ];
        
        $this->assertTrue($this->serviceRepository->validateService($validService));
        
        $invalidService = [
            'name' => 'test-app',
            'api_key' => 'test-key'
            // Manque 'id' et 'is_active'
        ];
        
        $this->assertFalse($this->serviceRepository->validateService($invalidService));
    }

    public function testReload(): void
    {
        // Modifier le fichier de configuration
        $newServices = [
            [
                'id' => 1,
                'name' => 'new-app',
                'api_key' => 'new-api-key',
                'is_active' => true,
                'permissions' => []
            ]
        ];
        
        file_put_contents($this->testConfigPath, json_encode($newServices));
        
        // Avant reload, on devrait encore avoir l'ancien service
        $service = $this->serviceRepository->findByName('test-app');
        $this->assertNotNull($service);
        
        // Après reload, on devrait avoir le nouveau service
        $this->serviceRepository->reload();
        
        $oldService = $this->serviceRepository->findByName('test-app');
        $this->assertNull($oldService);
        
        $newService = $this->serviceRepository->findByName('new-app');
        $this->assertNotNull($newService);
    }

    public function testLoadServicesWithInvalidFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Services configuration file not found');
        
        $invalidPath = '/path/that/does/not/exist.json';
        new ServiceRepository($invalidPath);
    }

    public function testLoadServicesWithInvalidJson(): void
    {
        $invalidJsonPath = sys_get_temp_dir() . '/invalid_services.json';
        file_put_contents($invalidJsonPath, 'invalid json content');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in services configuration');
        
        try {
            new ServiceRepository($invalidJsonPath);
        } finally {
            unlink($invalidJsonPath);
        }
    }
}
