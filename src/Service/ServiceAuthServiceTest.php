<?php

/**
 * Test Class for MyAuth
 *
 * @package MyAuth\Service
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Entity\Service;
use MyAuth\Exception\AuthenticationException;
use MyAuth\Exception\AuthorizationException;
use MyAuth\Repository\ServiceRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests simplifiés pour ServiceAuthService
 */
class ServiceAuthServiceTest extends TestCase
{
    public function testConstructor(): void
    {
        $mockRepository = $this->createMock(ServiceRepository::class);
        $service = new ServiceAuthService($mockRepository);

        $this->assertInstanceOf(ServiceAuthService::class, $service);
    }

    public function testGenerateTemporaryToken(): void
    {
        $mockRepository = $this->createMock(ServiceRepository::class);
        $service = new ServiceAuthService($mockRepository);

        $testService = new Service(
            '123e4567-e89b-12d3-a456-426614174000',
            'Test Service',
            'test-api-key-1234567890abcdef',
            'Service de test',
            true,
            ['https://example.com'],
            100
        );

        $token = $service->generateTemporaryToken($testService);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testValidateOriginAllowed(): void
    {
        $mockRepository = $this->createMock(ServiceRepository::class);
        $service = new ServiceAuthService($mockRepository);

        $testService = new Service(
            '123e4567-e89b-12d3-a456-426614174000',
            'Test Service',
            'test-api-key-1234567890abcdef',
            'Service de test',
            true,
            ['https://example.com'],
            100
        );

        $this->expectNotToPerformAssertions();

        $service->validateOrigin($testService, 'https://example.com');
    }

    public function testValidateOriginDenied(): void
    {
        $mockRepository = $this->createMock(ServiceRepository::class);
        $service = new ServiceAuthService($mockRepository);

        $testService = new Service(
            '123e4567-e89b-12d3-a456-426614174000',
            'Test Service',
            'test-api-key-1234567890abcdef',
            'Service de test',
            true,
            ['https://example.com'],
            100
        );

        $this->expectException(AuthorizationException::class);

        $service->validateOrigin($testService, 'https://forbidden.com');
    }
}
