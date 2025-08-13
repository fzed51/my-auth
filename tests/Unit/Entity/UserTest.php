<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use MyAuth\Entity\User;
use DateTime;

/**
 * Test de l'entité User
 */
class UserTest extends TestCase
{
    public function testUserCanBeCreatedWithRequiredFields(): void
    {
        $email = 'test@example.com';
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        
        $user = new User($email, $passwordHash);
        
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($passwordHash, $user->getPasswordHash());
        $this->assertNull($user->getId());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());
        $this->assertFalse($user->getIsEmailVerified());
        $this->assertTrue($user->getIsActive());
        $this->assertNull($user->getLastLoginAt());
        $this->assertInstanceOf(DateTime::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTime::class, $user->getUpdatedAt());
    }

    public function testUserCanBeCreatedWithOptionalFields(): void
    {
        $email = 'test@example.com';
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        $firstName = 'John';
        $lastName = 'Doe';
        
        $user = new User($email, $passwordHash, $firstName, $lastName);
        
        $this->assertEquals($firstName, $user->getFirstName());
        $this->assertEquals($lastName, $user->getLastName());
        $this->assertEquals('John Doe', $user->getFullName());
    }

    public function testUserIdCanBeSet(): void
    {
        $user = new User('test@example.com', 'hash');
        
        $user->setId(123);
        
        $this->assertEquals(123, $user->getId());
    }

    public function testEmailCanBeUpdated(): void
    {
        $user = new User('test@example.com', 'hash');
        
        $user->setEmail('new@example.com');
        
        $this->assertEquals('new@example.com', $user->getEmail());
    }

    public function testPasswordHashCanBeUpdated(): void
    {
        $user = new User('test@example.com', 'old-hash');
        $newHash = password_hash('newpassword', PASSWORD_DEFAULT);
        
        $user->setPasswordHash($newHash);
        
        $this->assertEquals($newHash, $user->getPasswordHash());
    }

    public function testEmailVerificationStatusCanBeToggled(): void
    {
        $user = new User('test@example.com', 'hash');
        
        $this->assertFalse($user->getIsEmailVerified());
        
        $user->setIsEmailVerified(true);
        
        $this->assertTrue($user->getIsEmailVerified());
    }

    public function testActiveStatusCanBeToggled(): void
    {
        $user = new User('test@example.com', 'hash');
        
        $this->assertTrue($user->getIsActive());
        
        $user->setIsActive(false);
        
        $this->assertFalse($user->getIsActive());
    }

    public function testLastLoginCanBeSet(): void
    {
        $user = new User('test@example.com', 'hash');
        $loginTime = new DateTime();
        
        $user->setLastLoginAt($loginTime);
        
        $this->assertEquals($loginTime, $user->getLastLoginAt());
    }

    public function testUpdatedAtIsUpdatedOnChange(): void
    {
        $user = new User('test@example.com', 'hash');
        $originalUpdatedAt = $user->getUpdatedAt();
        
        // Attendre un peu pour s'assurer que le temps change
        usleep(1000);
        
        $user->setEmail('new@example.com');
        
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testFullNameWithOnlyFirstName(): void
    {
        $user = new User('test@example.com', 'hash', 'John');
        
        $this->assertEquals('John', $user->getFullName());
    }

    public function testFullNameWithOnlyLastName(): void
    {
        $user = new User('test@example.com', 'hash', null, 'Doe');
        
        $this->assertEquals('Doe', $user->getFullName());
    }

    public function testFullNameWithoutNames(): void
    {
        $user = new User('test@example.com', 'hash');
        
        $this->assertEquals('', $user->getFullName());
    }

    public function testToArrayReturnsCorrectData(): void
    {
        $user = new User('test@example.com', 'hash', 'John', 'Doe');
        $user->setId(1);
        $user->setIsEmailVerified(true);
        
        $array = $user->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('John', $array['firstName']);
        $this->assertEquals('Doe', $array['lastName']);
        $this->assertEquals('John Doe', $array['fullName']);
        $this->assertTrue($array['isEmailVerified']);
        $this->assertTrue($array['isActive']);
        $this->assertNull($array['lastLoginAt']);
        $this->assertIsString($array['createdAt']);
        $this->assertIsString($array['updatedAt']);
        
        // Le password hash ne doit pas être dans toArray()
        $this->assertArrayNotHasKey('passwordHash', $array);
        $this->assertArrayNotHasKey('password_hash', $array);
    }
}
