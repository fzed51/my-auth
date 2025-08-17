<?php

/**
 * Test Class for MyAuth
 *
 * @package MyAuth\Entity
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Entity;

use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    private array $validServiceData;

    protected function setUp(): void
    {
        $this->validServiceData = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Service',
            'api_key' => 'test-api-key-1234567890abcdef',
            'description' => 'Service de test',
            'is_active' => true,
            'allowed_origins' => ['https://example.com', '*.test.com'],
            'rate_limit_per_minute' => 100,
        ];
    }

    public function testConstructor(): void
    {
        $service = new Service(
            $this->validServiceData['id'],
            $this->validServiceData['name'],
            $this->validServiceData['api_key'],
            $this->validServiceData['description'],
            $this->validServiceData['is_active'],
            $this->validServiceData['allowed_origins'],
            $this->validServiceData['rate_limit_per_minute']
        );

        $this->assertEquals($this->validServiceData['id'], $service->getId());
        $this->assertEquals($this->validServiceData['name'], $service->getName());
        $this->assertEquals($this->validServiceData['api_key'], $service->getApiKey());
        $this->assertEquals($this->validServiceData['description'], $service->getDescription());
        $this->assertTrue($service->isActive());
        $this->assertEquals($this->validServiceData['allowed_origins'], $service->getAllowedOrigins());
        $this->assertEquals($this->validServiceData['rate_limit_per_minute'], $service->getRateLimitPerMinute());
        $this->assertInstanceOf(\DateTime::class, $service->getCreatedAt());
        $this->assertInstanceOf(\DateTime::class, $service->getUpdatedAt());
    }

    public function testFromArray(): void
    {
        $service = Service::fromArray($this->validServiceData);

        $this->assertEquals($this->validServiceData['id'], $service->getId());
        $this->assertEquals($this->validServiceData['name'], $service->getName());
        $this->assertEquals($this->validServiceData['api_key'], $service->getApiKey());
        $this->assertEquals($this->validServiceData['description'], $service->getDescription());
        $this->assertTrue($service->isActive());
        $this->assertEquals($this->validServiceData['allowed_origins'], $service->getAllowedOrigins());
        $this->assertEquals($this->validServiceData['rate_limit_per_minute'], $service->getRateLimitPerMinute());
    }

    public function testFromArrayWithDefaults(): void
    {
        $minimalData = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Service',
            'api_key' => 'test-api-key-1234567890abcdef',
            'description' => 'Service de test',
        ];

        $service = Service::fromArray($minimalData);

        $this->assertTrue($service->isActive());
        $this->assertEquals([], $service->getAllowedOrigins());
        $this->assertEquals(60, $service->getRateLimitPerMinute());
    }

    public function testToArray(): void
    {
        $service = Service::fromArray($this->validServiceData);
        $array = $service->toArray();

        $this->assertEquals($this->validServiceData['id'], $array['id']);
        $this->assertEquals($this->validServiceData['name'], $array['name']);
        $this->assertEquals($this->validServiceData['api_key'], $array['api_key']);
        $this->assertEquals($this->validServiceData['description'], $array['description']);
        $this->assertEquals($this->validServiceData['is_active'], $array['is_active']);
        $this->assertEquals($this->validServiceData['allowed_origins'], $array['allowed_origins']);
        $this->assertEquals($this->validServiceData['rate_limit_per_minute'], $array['rate_limit_per_minute']);
        $this->assertIsString($array['created_at']);
        $this->assertIsString($array['updated_at']);
    }

    public function testSetActive(): void
    {
        $service = Service::fromArray($this->validServiceData);
        $initialUpdatedAt = $service->getUpdatedAt();

        // Petite pause pour s'assurer que l'updated_at change
        usleep(1000);

        $service->setActive(false);

        $this->assertFalse($service->isActive());
        $this->assertGreaterThan($initialUpdatedAt, $service->getUpdatedAt());
    }

    public function testSetAllowedOrigins(): void
    {
        $service = Service::fromArray($this->validServiceData);
        $newOrigins = ['https://newdomain.com'];
        $initialUpdatedAt = $service->getUpdatedAt();

        usleep(1000);

        $service->setAllowedOrigins($newOrigins);

        $this->assertEquals($newOrigins, $service->getAllowedOrigins());
        $this->assertGreaterThan($initialUpdatedAt, $service->getUpdatedAt());
    }

    public function testSetRateLimitPerMinute(): void
    {
        $service = Service::fromArray($this->validServiceData);
        $newRateLimit = 200;
        $initialUpdatedAt = $service->getUpdatedAt();

        usleep(1000);

        $service->setRateLimitPerMinute($newRateLimit);

        $this->assertEquals($newRateLimit, $service->getRateLimitPerMinute());
        $this->assertGreaterThan($initialUpdatedAt, $service->getUpdatedAt());
    }

    public function testIsOriginAllowedWithEmptyOrigins(): void
    {
        $serviceData = $this->validServiceData;
        $serviceData['allowed_origins'] = [];
        $service = Service::fromArray($serviceData);

        $this->assertTrue($service->isOriginAllowed('https://any-domain.com'));
    }

    public function testIsOriginAllowedWithSpecificOrigin(): void
    {
        $service = Service::fromArray($this->validServiceData);

        $this->assertTrue($service->isOriginAllowed('https://example.com'));
        $this->assertFalse($service->isOriginAllowed('https://other.com'));
    }

    public function testIsOriginAllowedWithWildcard(): void
    {
        $service = Service::fromArray($this->validServiceData);

        $this->assertTrue($service->isOriginAllowed('https://api.test.com'));
        $this->assertTrue($service->isOriginAllowed('https://app.test.com'));
        $this->assertFalse($service->isOriginAllowed('https://api.other.com'));
    }

    public function testIsOriginAllowedWithWildcardAll(): void
    {
        $serviceData = $this->validServiceData;
        $serviceData['allowed_origins'] = ['*'];
        $service = Service::fromArray($serviceData);

        $this->assertTrue($service->isOriginAllowed('https://any-domain.com'));
        $this->assertTrue($service->isOriginAllowed('http://localhost:3000'));
    }
}
