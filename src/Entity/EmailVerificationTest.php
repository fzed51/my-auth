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
use DateTime;
use InvalidArgumentException;

class EmailVerificationTest extends TestCase
{
    private function generateValidToken(): string
    {
        return str_repeat('a', 32); // 32 character token
    }

    public function testConstructorWithValidData(): void
    {
        $expiresAt = new DateTime('+24 hours');
        $verification = new EmailVerification(
            id: 'test-id',
            userId: 'user-id',
            token: $this->generateValidToken(),
            expiresAt: $expiresAt
        );

        $this->assertEquals('test-id', $verification->getId());
        $this->assertEquals('user-id', $verification->getUserId());
        $this->assertEquals($this->generateValidToken(), $verification->getToken());
        $this->assertEquals($expiresAt, $verification->getExpiresAt());
        $this->assertFalse($verification->isUsed());
        $this->assertNull($verification->getUsedAt());
        $this->assertInstanceOf(DateTime::class, $verification->getCreatedAt());
    }

    public function testConstructorWithShortToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token too short (min 32 characters)');

        new EmailVerification(
            id: 'test-id',
            userId: 'user-id',
            token: 'short',
            expiresAt: new DateTime('+24 hours')
        );
    }

    public function testIsValid(): void
    {
        $verification = new EmailVerification(
            id: 'test-id',
            userId: 'user-id',
            token: $this->generateValidToken(),
            expiresAt: new DateTime('+24 hours')
        );

        $this->assertTrue($verification->isValid());
    }

    public function testMarkAsUsed(): void
    {
        $verification = new EmailVerification(
            id: 'test-id',
            userId: 'user-id',
            token: $this->generateValidToken(),
            expiresAt: new DateTime('+24 hours')
        );

        $this->assertFalse($verification->isUsed());
        $verification->markAsUsed();
        $this->assertTrue($verification->isUsed());
        $this->assertInstanceOf(DateTime::class, $verification->getUsedAt());
    }

    public function testToArray(): void
    {
        $expiresAt = new DateTime('+24 hours');
        $verification = new EmailVerification(
            id: 'test-id',
            userId: 'user-id',
            token: $this->generateValidToken(),
            expiresAt: $expiresAt
        );

        $array = $verification->toArray();

        $this->assertEquals('test-id', $array['id']);
        $this->assertEquals('user-id', $array['user_id']);
        $this->assertEquals($this->generateValidToken(), $array['token']);
        $this->assertEquals($expiresAt->format('Y-m-d H:i:s'), $array['expires_at']);
        $this->assertFalse($array['is_used']);
        $this->assertNull($array['used_at']);
        $this->assertNotNull($array['created_at']);
    }
}
