<?php

/**
 * Test Class for MyAuth
 *
 * @package MyAuth\Middleware
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Middleware;

use PHPUnit\Framework\TestCase;
use MyAuth\Service\ServiceAuthService;
use MyAuth\Entity\Service;

/**
 * Tests simplifiés pour ApiKeyMiddleware
 * Note: Les tests complets nécessitent une intégration plus complexe
 */
class ApiKeyMiddlewareTest extends TestCase
{
    public function testConstructor(): void
    {
        $mockAuthService = $this->createMock(ServiceAuthService::class);
        $mockResponseFactory = $this->createMock(\Psr\Http\Message\ResponseFactoryInterface::class);

        $middleware = new ApiKeyMiddleware($mockAuthService, $mockResponseFactory);

        $this->assertInstanceOf(ApiKeyMiddleware::class, $middleware);
    }

    public function testWithDefaultPublicRoutes(): void
    {
        $mockAuthService = $this->createMock(ServiceAuthService::class);
        $mockResponseFactory = $this->createMock(\Psr\Http\Message\ResponseFactoryInterface::class);

        $middleware = ApiKeyMiddleware::withDefaultPublicRoutes($mockAuthService, $mockResponseFactory);

        $this->assertInstanceOf(ApiKeyMiddleware::class, $middleware);
    }

    public function testStrict(): void
    {
        $mockAuthService = $this->createMock(ServiceAuthService::class);
        $mockResponseFactory = $this->createMock(\Psr\Http\Message\ResponseFactoryInterface::class);

        $middleware = ApiKeyMiddleware::strict($mockAuthService, $mockResponseFactory);

        $this->assertInstanceOf(ApiKeyMiddleware::class, $middleware);
    }

    public function testGetAuthenticatedServiceWithValidService(): void
    {
        $mockRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $testService = new Service(
            '123e4567-e89b-12d3-a456-426614174000',
            'Test Service',
            'test-api-key',
            'Description',
            true
        );

        $mockRequest->method('getAttribute')
            ->with('authenticated_service')
            ->willReturn($testService);

        $result = ApiKeyMiddleware::getAuthenticatedService($mockRequest);

        $this->assertSame($testService, $result);
    }

    public function testGetAuthenticatedServiceWithInvalidData(): void
    {
        $mockRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);

        $mockRequest->method('getAttribute')
            ->with('authenticated_service')
            ->willReturn('not_a_service');

        $result = ApiKeyMiddleware::getAuthenticatedService($mockRequest);

        $this->assertNull($result);
    }

    public function testGetServiceIdWithValidString(): void
    {
        $mockRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $serviceId = '123e4567-e89b-12d3-a456-426614174000';

        $mockRequest->method('getAttribute')
            ->with('service_id')
            ->willReturn($serviceId);

        $result = ApiKeyMiddleware::getServiceId($mockRequest);

        $this->assertEquals($serviceId, $result);
    }

    public function testGetServiceIdWithInvalidData(): void
    {
        $mockRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);

        $mockRequest->method('getAttribute')
            ->with('service_id')
            ->willReturn(123);

        $result = ApiKeyMiddleware::getServiceId($mockRequest);

        $this->assertNull($result);
    }

    public function testGetServiceNameWithValidString(): void
    {
        $mockRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $serviceName = 'Test Service';

        $mockRequest->method('getAttribute')
            ->with('service_name')
            ->willReturn($serviceName);

        $result = ApiKeyMiddleware::getServiceName($mockRequest);

        $this->assertEquals($serviceName, $result);
    }

    public function testGetServiceNameWithInvalidData(): void
    {
        $mockRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);

        $mockRequest->method('getAttribute')
            ->with('service_name')
            ->willReturn(['not', 'a', 'string']);

        $result = ApiKeyMiddleware::getServiceName($mockRequest);

        $this->assertNull($result);
    }
}
