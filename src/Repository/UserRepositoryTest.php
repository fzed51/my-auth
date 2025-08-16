<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PHPUnit\Framework\TestCase;
use PDO;
use MyAuth\Entity\User;
use MyAuth\Exception\UserAlreadyExistsException;
use MyAuth\Exception\UserNotFoundException;

class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repository;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $this->pdo->exec("
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                first_name TEXT,
                last_name TEXT,
                is_active INTEGER DEFAULT 0,
                is_verified INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->repository = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        // Fermer proprement la connexion PDO
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
    }

    public function testGetTableName(): void
    {
        $this->assertEquals('users', $this->repository->getTableName());
    }

    public function testFindByEmail(): void
    {
        // Insert test user
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name, is_active, is_verified)
            VALUES ('test-id', 'test@example.com', 'hash', 'John', 'Doe', 1, 1)
        ");

        $user = $this->repository->findByEmail('test@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test-id', $user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->isVerified());
    }

    public function testFindByEmailNotFound(): void
    {
        $user = $this->repository->findByEmail('nonexistent@example.com');
        $this->assertNull($user);
    }

    public function testFindUserById(): void
    {
        // Insert test user
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name)
            VALUES ('test-id', 'test@example.com', 'hash', 'John', 'Doe')
        ");

        $user = $this->repository->findUserById('test-id');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test-id', $user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
    }

    public function testFindUserByIdNotFound(): void
    {
        $user = $this->repository->findUserById('nonexistent-id');
        $this->assertNull($user);
    }

    public function testEmailExists(): void
    {
        // Insert test user
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name)
            VALUES ('test-id', 'test@example.com', 'hash', 'John', 'Doe')
        ");

        $this->assertTrue($this->repository->emailExists('test@example.com'));
        $this->assertFalse($this->repository->emailExists('nonexistent@example.com'));
    }

    public function testCreate(): void
    {
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->repository->create($user);

        // Verify user was created
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute(['test-id']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals('test-id', $row['id']);
        $this->assertEquals('test@example.com', $row['email']);
        $this->assertEquals('hash', $row['password_hash']);
        $this->assertEquals('John', $row['first_name']);
        $this->assertEquals('Doe', $row['last_name']);
        $this->assertEquals(0, $row['is_active']);
        $this->assertEquals(0, $row['is_verified']);
    }

    public function testCreateWithDuplicateEmail(): void
    {
        // Insert first user
        $user1 = new User(
            id: 'test-id-1',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );
        $this->repository->create($user1);

        // Try to create second user with same email
        $user2 = new User(
            id: 'test-id-2',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Jane',
            lastName: 'Smith'
        );

        $this->expectException(UserAlreadyExistsException::class);
        $this->repository->create($user2);
    }

    public function testUpdateUser(): void
    {
        // Insert test user
        $user = new User(
            id: 'test-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );
        $this->repository->create($user);

        // Update user
        $user->updateProfile('Jane', 'Smith');
        $user->activate();
        $user->verifyEmail();

        $this->repository->updateUser($user);

        // Verify user was updated
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute(['test-id']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Jane', $row['first_name']);
        $this->assertEquals('Smith', $row['last_name']);
        $this->assertEquals(1, $row['is_active']);
        $this->assertEquals(1, $row['is_verified']);
    }

    public function testUpdateUserNotFound(): void
    {
        $user = new User(
            id: 'nonexistent-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->expectException(UserNotFoundException::class);
        $this->repository->updateUser($user);
    }

    public function testDeleteUser(): void
    {
        // Insert test user
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name)
            VALUES ('test-id', 'test@example.com', 'hash', 'John', 'Doe')
        ");

        $this->repository->deleteUser('test-id');

        // Verify user was deleted
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute(['test-id']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertFalse($row);
    }

    public function testDeleteUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->repository->deleteUser('nonexistent-id');
    }

    public function testCountActive(): void
    {
        // Insert test users
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name, is_active)
            VALUES 
                ('id1', 'user1@example.com', 'hash', 'User', 'One', 1),
                ('id2', 'user2@example.com', 'hash', 'User', 'Two', 1),
                ('id3', 'user3@example.com', 'hash', 'User', 'Three', 0)
        ");

        $count = $this->repository->countActive();
        $this->assertEquals(2, $count);
    }

    public function testCountVerified(): void
    {
        // Insert test users
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name, is_verified)
            VALUES 
                ('id1', 'user1@example.com', 'hash', 'User', 'One', 1),
                ('id2', 'user2@example.com', 'hash', 'User', 'Two', 1),
                ('id3', 'user3@example.com', 'hash', 'User', 'Three', 0)
        ");

        $count = $this->repository->countVerified();
        $this->assertEquals(2, $count);
    }

    public function testFindRecentUsers(): void
    {
        // Insert test users with different creation dates
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name, created_at)
            VALUES 
                ('id1', 'user1@example.com', 'hash', 'User', 'One', datetime('now', '-1 day')),
                ('id2', 'user2@example.com', 'hash', 'User', 'Two', datetime('now', '-2 days')),
                ('id3', 'user3@example.com', 'hash', 'User', 'Three', datetime('now', '-8 days'))
        ");

        $recentUsers = $this->repository->findRecentUsers(2);

        $this->assertCount(2, $recentUsers);
        $this->assertEquals('id1', $recentUsers[0]->getId());
        $this->assertEquals('id2', $recentUsers[1]->getId());
    }

    public function testFindRecentUsersWithDefaultLimit(): void
    {
        // Insert multiple test users
        for ($i = 1; $i <= 15; $i++) {
            $this->pdo->exec("
                INSERT INTO users (id, email, password_hash, first_name, last_name)
                VALUES ('id{$i}', 'user{$i}@example.com', 'hash', 'User', 'Number{$i}')
            ");
        }

        $recentUsers = $this->repository->findRecentUsers();

        $this->assertCount(10, $recentUsers); // Default limit
    }
}
