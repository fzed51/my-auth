<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use MyAuth\Service\ServiceAuthService;
use MyAuth\Repository\ServiceRepository;
use MyAuth\Exception\AuthException;

/**
 * Test complet du ServiceAuthService
 */
class ServiceAuthServiceTest extends TestCase
{
    private ServiceAuthService $serviceAuthService;
    private ServiceRepository $serviceRepository;

    protected function setUp(): void
    {
        $this->serviceRepository = new ServiceRepository(__DIR__ . '/../../../config/services.json');
        $this->serviceAuthService = new ServiceAuthService($this->serviceRepository);
    }

    public function testCanExtractApiKeyFromHeaders(): void
    {
        $headers = ['X-API-Key' => ['test-api-key-frontend']];
        $apiKey = $this->serviceAuthService->extractApiKeyFromHeaders($headers);
        
        $this->assertEquals('test-api-key-frontend', $apiKey);
    }

    public function testCanExtractApiKeyFromDifferentHeaderFormats(): void
    {
        // Test avec X-API-Key
        $headers1 = ['X-API-Key' => ['test-key']];
        $this->assertEquals('test-key', $this->serviceAuthService->extractApiKeyFromHeaders($headers1));

        // Test avec X-Api-Key
        $headers2 = ['X-Api-Key' => ['test-key']];
        $this->assertEquals('test-key', $this->serviceAuthService->extractApiKeyFromHeaders($headers2));

        // Test avec API-Key
        $headers3 = ['API-Key' => ['test-key']];
        $this->assertEquals('test-key', $this->serviceAuthService->extractApiKeyFromHeaders($headers3));
    }

    public function testExtractApiKeyReturnsNullWhenNotFound(): void
    {
        $headers = ['Content-Type' => ['application/json']];
        $apiKey = $this->serviceAuthService->extractApiKeyFromHeaders($headers);
        
        $this->assertNull($apiKey);
    }

    public function testCanAuthenticateValidService(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        $this->assertIsArray($service);
        $this->assertEquals('frontend-app', $service['name']);
        $this->assertTrue($service['is_active']);
        $this->assertContains('auth:login', $service['permissions']);
        $this->assertContains('auth:register', $service['permissions']);
    }

    public function testAuthenticateServiceThrowsExceptionForInvalidApiKey(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('API key invalide');
        $this->expectExceptionCode(401);
        
        $this->serviceAuthService->authenticateService('invalid-api-key');
    }

    public function testAuthenticateServiceThrowsExceptionForEmptyApiKey(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('API key manquante');
        $this->expectExceptionCode(401);
        
        $this->serviceAuthService->authenticateService('');
    }

    public function testValidateServiceAccessAllowsAuthorizedRoutes(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        // Test accès autorisé à /api/auth/register
        $canAccess = $this->serviceAuthService->validateServiceAccess($service, '/api/auth/register', 'POST');
        $this->assertTrue($canAccess);

        // Test accès autorisé à /api/auth/login
        $canAccess = $this->serviceAuthService->validateServiceAccess($service, '/api/auth/login', 'POST');
        $this->assertTrue($canAccess);
    }

    public function testValidateServiceAccessAllowsUnprotectedRoutes(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        // Test accès à une route non protégée
        $canAccess = $this->serviceAuthService->validateServiceAccess($service, '/health', 'GET');
        $this->assertTrue($canAccess);
    }

    public function testHasPermissionReturnsTrueForValidPermission(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        $hasPermission = $this->serviceAuthService->hasPermission($service, 'auth:login');
        $this->assertTrue($hasPermission);
    }

    public function testHasPermissionReturnsFalseForInvalidPermission(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        $hasPermission = $this->serviceAuthService->hasPermission($service, 'admin:delete');
        $this->assertFalse($hasPermission);
    }

    public function testGetServiceByNameReturnsCorrectService(): void
    {
        $service = $this->serviceAuthService->getServiceByName('frontend-app');
        
        $this->assertIsArray($service);
        $this->assertEquals('frontend-app', $service['name']);
        $this->assertEquals('test-api-key-frontend', $service['api_key']);
    }

    public function testGetServiceByNameReturnsNullForInvalidName(): void
    {
        $service = $this->serviceAuthService->getServiceByName('invalid-service');
        
        $this->assertNull($service);
    }

    public function testGetAllActiveServicesReturnsOnlyActiveServices(): void
    {
        $services = $this->serviceAuthService->getAllActiveServices();
        
        $this->assertIsArray($services);
        $this->assertNotEmpty($services);
        
        foreach ($services as $service) {
            $this->assertTrue($service['is_active']);
        }
    }

    public function testCanAccessResourceReturnsTrueForValidPermission(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        $canAccess = $this->serviceAuthService->canAccessResource($service, 'auth', 'login');
        $this->assertTrue($canAccess);
    }

    public function testIsAdminServiceReturnsFalseForNonAdminService(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-frontend');
        
        $isAdmin = $this->serviceAuthService->isAdminService($service);
        $this->assertFalse($isAdmin);
    }

    public function testIsAdminServiceReturnsTrueForAdminService(): void
    {
        $service = $this->serviceAuthService->authenticateService('test-api-key-admin');
        
        $isAdmin = $this->serviceAuthService->isAdminService($service);
        $this->assertTrue($isAdmin);
    }
}
