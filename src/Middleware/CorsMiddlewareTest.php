<?php

declare(strict_types=1);

namespace MyAuth\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Tests unitaires pour CorsMiddleware
 * 
 * @package MyAuth\Middleware
 */
class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;
    private ServerRequestFactory $requestFactory;
    private ResponseFactory $responseFactory;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->middleware = new CorsMiddleware();
        $this->requestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
        
        // Mock du handler
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handler
            ->method('handle')
            ->willReturn($this->responseFactory->createResponse());
    }

    public function testProcessAddsBasicCorsHeaders(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
        $this->assertTrue($response->hasHeader('Access-Control-Max-Age'));
        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testProcessWithSpecificOrigin(): void
    {
        $middleware = new CorsMiddleware(['https://example.com']);
        $request = $this->requestFactory
            ->createServerRequest('GET', '/')
            ->withHeader('Origin', 'https://example.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals('https://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testProcessWithUnauthorizedOrigin(): void
    {
        $middleware = new CorsMiddleware(['https://allowed.com']);
        $request = $this->requestFactory
            ->createServerRequest('GET', '/')
            ->withHeader('Origin', 'https://malicious.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals('https://allowed.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testProcessOptionsRequest(): void
    {
        $request = $this->requestFactory
            ->createServerRequest('OPTIONS', '/')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'Content-Type');
        
        $response = $this->middleware->process($request, $this->handler);
        
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
    }

    public function testFromEnvironmentFactory(): void
    {
        $_ENV['CORS_ALLOWED_ORIGINS'] = 'https://app1.com,https://app2.com';
        $_ENV['CORS_ALLOWED_METHODS'] = 'GET,POST';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';
        
        $middleware = CorsMiddleware::fromEnvironment();
        $request = $this->requestFactory->createServerRequest('GET', '/');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
        
        // Nettoyage
        unset($_ENV['CORS_ALLOWED_ORIGINS'], $_ENV['CORS_ALLOWED_METHODS'], $_ENV['CORS_ALLOW_CREDENTIALS']);
    }

    public function testForDevelopmentFactory(): void
    {
        $middleware = CorsMiddleware::forDevelopment();
        $request = $this->requestFactory->createServerRequest('GET', '/');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testForProductionFactory(): void
    {
        $allowedOrigins = ['https://myapp.com'];
        $middleware = CorsMiddleware::forProduction($allowedOrigins);
        $request = $this->requestFactory
            ->createServerRequest('GET', '/')
            ->withHeader('Origin', 'https://myapp.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals('https://myapp.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('3600', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function testWildcardOriginPattern(): void
    {
        $middleware = new CorsMiddleware(['*.example.com']);
        $request = $this->requestFactory
            ->createServerRequest('GET', '/')
            ->withHeader('Origin', 'https://app.example.com');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals('https://app.example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testCredentialsHeader(): void
    {
        $middleware = new CorsMiddleware(['*'], [], [], true);
        $request = $this->requestFactory->createServerRequest('GET', '/');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testNoCredentialsHeader(): void
    {
        $middleware = new CorsMiddleware(['*'], [], [], false);
        $request = $this->requestFactory->createServerRequest('GET', '/');
        
        $response = $middleware->process($request, $this->handler);
        
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Credentials'));
    }
}
