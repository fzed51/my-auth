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

class UserTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: password_hash('password123', PASSWORD_DEFAULT),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->assertEquals('test-id', $user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->isVerified());
        $this->assertInstanceOf(DateTime::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTime::class, $user->getUpdatedAt());
    }

    public function testConstructorWithInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        new User(
            id: 'test-id',
            email: 'invalid-email',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );
    }

    public function testConstructorWithEmptyFirstName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('firstName cannot be empty');

        new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: '',
            lastName: 'Doe'
        );
    }

    public function testConstructorWithEmptyLastName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lastName cannot be empty');

        new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: ''
        );
    }

    public function testConstructorWithTooLongEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $longEmail = str_repeat('a', 250) . '@example.com'; // > 255 chars

        new User(
            id: 'test-id',
            email: $longEmail,
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );
    }

    public function testConstructorWithTooLongFirstName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('firstName too long (max 100 characters)');

        new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: str_repeat('a', 101),
            lastName: 'Doe'
        );
    }

    public function testActivate(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->assertFalse($user->isActive());
        $user->activate();
        $this->assertTrue($user->isActive());
    }

    public function testDeactivate(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe',
            isActive: true
        );

        $this->assertTrue($user->isActive());
        $user->deactivate();
        $this->assertFalse($user->isActive());
    }

    public function testVerifyEmail(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->assertFalse($user->isVerified());
        $user->verifyEmail();
        $this->assertTrue($user->isVerified());
        $this->assertTrue($user->isActive()); // Should auto-activate
    }

    public function testUpdateProfile(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $user->updateProfile('Jane', 'Smith');

        $this->assertEquals('Jane', $user->getFirstName());
        $this->assertEquals('Smith', $user->getLastName());
    }

    public function testUpdateProfileWithInvalidData(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->expectException(InvalidArgumentException::class);
        $user->updateProfile('', 'Smith');
    }

    public function testUpdatePassword(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'old-hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $newHash = password_hash('newpassword', PASSWORD_DEFAULT);
        $user->updatePassword($newHash);

        $this->assertEquals($newHash, $user->getPasswordHash());
    }

    public function testToArray(): void
    {
        $now = new DateTime();
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe',
            isActive: true,
            isVerified: true,
            createdAt: $now,
            updatedAt: $now
        );

        $array = $user->toArray();

        $this->assertEquals('test-id', $array['id']);
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('hash', $array['password_hash']);
        $this->assertEquals('John', $array['first_name']);
        $this->assertEquals('Doe', $array['last_name']);
        $this->assertTrue($array['is_active']);
        $this->assertTrue($array['is_verified']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $array['created_at']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $array['updated_at']);
    }

    public function testToPublicArray(): void
    {
        $now = new DateTime();
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe',
            isActive: true,
            isVerified: true,
            createdAt: $now,
            updatedAt: $now
        );

        $publicArray = $user->toPublicArray();

        $this->assertEquals('test-id', $publicArray['id']);
        $this->assertEquals('test@example.com', $publicArray['email']);
        $this->assertEquals('John', $publicArray['firstName']);
        $this->assertEquals('Doe', $publicArray['lastName']);
        $this->assertTrue($publicArray['isActive']);
        $this->assertTrue($publicArray['isEmailVerified']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $publicArray['createdAt']);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $publicArray['updatedAt']);

        // Password should NOT be in public array
        $this->assertArrayNotHasKey('password', $publicArray);
        $this->assertArrayNotHasKey('passwordHash', $publicArray);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'test-id',
            'email' => 'test@example.com',
            'password_hash' => 'hash',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => 1,
            'is_verified' => 1,
            'created_at' => '2025-08-14 12:00:00',
            'updated_at' => '2025-08-14 12:00:00'
        ];

        $user = User::fromArray($data);

        $this->assertEquals('test-id', $user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('hash', $user->getPasswordHash());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->isVerified());
        $this->assertEquals('2025-08-14 12:00:00', $user->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-14 12:00:00', $user->getUpdatedAt()->format('Y-m-d H:i:s'));
    }

    public function testFromArrayWithMissingDates(): void
    {
        $data = [
            'id' => 'test-id',
            'email' => 'test@example.com',
            'password_hash' => 'hash',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => 0,
            'is_verified' => 0
        ];

        $user = User::fromArray($data);

        $this->assertEquals('test-id', $user->getId());
        $this->assertInstanceOf(DateTime::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTime::class, $user->getUpdatedAt());
    }
}
