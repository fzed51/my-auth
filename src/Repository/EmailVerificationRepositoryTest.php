<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PHPUnit\Framework\TestCase;
use PDO;
use MyAuth\Entity\EmailVerification;
use MyAuth\Exception\EmailVerificationException;
use DateTime;

class EmailVerificationRepositoryTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?EmailVerificationRepository $repository = null;

    private function generateValidToken(): string
    {
        return str_repeat('a', 32); // Token de 32 caractères
    }

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create tables
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

        $this->pdo->exec("
            CREATE TABLE email_verifications (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                token TEXT UNIQUE NOT NULL,
                expires_at TEXT NOT NULL,
                is_used INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                used_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Insert test user
        $this->pdo->exec("
            INSERT INTO users (id, email, password_hash, first_name, last_name)
            VALUES ('user-id', 'test@example.com', 'hash', 'John', 'Doe')
        ");

        $this->repository = new EmailVerificationRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    public function testGetTableName(): void
    {
        $this->assertEquals('email_verifications', $this->repository->getTableName());
    }

    public function testFindValidByToken(): void
    {
        // Insert valid token
        $validToken = $this->generateValidToken();
        $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used)
            VALUES ('test-id', 'user-id', '{$validToken}', '{$expiresAt}', 0)
        ");

        $verification = $this->repository->findValidByToken($validToken);

        $this->assertInstanceOf(EmailVerification::class, $verification);
        $this->assertEquals('test-id', $verification->getId());
        $this->assertEquals('user-id', $verification->getUserId());
        $this->assertEquals($validToken, $verification->getToken());
        $this->assertFalse($verification->isUsed());
    }

    public function testFindValidByTokenExpired(): void
    {
        // Insert expired token
        $expiresAt = (new DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used, created_at)
            VALUES ('test-id', 'user-id', '$this->generateValidToken()', '{$expiresAt}', 0, datetime('now', '-2 hours'))
        ");

        $verification = $this->repository->findValidByToken('$this->generateValidToken()');
        $this->assertNull($verification);
    }

    public function testFindValidByTokenUsed(): void
    {
        // Insert used token
        $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used)
            VALUES ('test-id', 'user-id', '$this->generateValidToken()', '{$expiresAt}', 1)
        ");

        $verification = $this->repository->findValidByToken('$this->generateValidToken()');
        $this->assertNull($verification);
    }

    public function testFindValidByTokenNotFound(): void
    {
        $verification = $this->repository->findValidByToken('nonexistent-token');
        $this->assertNull($verification);
    }

    public function testFindPendingByUserId(): void
    {
        // Insert pending verification
        $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used)
            VALUES ('test-id', 'user-id', '$this->generateValidToken()', '{$expiresAt}', 0)
        ");

        $verification = $this->repository->findPendingByUserId('user-id');

        $this->assertInstanceOf(EmailVerification::class, $verification);
        $this->assertEquals('test-id', $verification->getId());
        $this->assertEquals('user-id', $verification->getUserId());
        $this->assertFalse($verification->isUsed());
    }

    public function testFindPendingByUserIdNotFound(): void
    {
        $verification = $this->repository->findPendingByUserId('nonexistent-user');
        $this->assertNull($verification);
    }

    public function testCreate(): void
    {
        $verification = new EmailVerification(
            id: 'test-id',
            userId: 'user-id',
            token: '$this->generateValidToken()',
            expiresAt: new DateTime('+24 hours')
        );

        $this->repository->create($verification);

        // Verify creation
        $stmt = $this->pdo->prepare('SELECT * FROM email_verifications WHERE id = ?');
        $stmt->execute(['test-id']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals('test-id', $row['id']);
        $this->assertEquals('user-id', $row['user_id']);
        $this->assertEquals('$this->generateValidToken()', $row['token']);
        $this->assertEquals(0, $row['is_used']);
        $this->assertNull($row['used_at']);
    }

    public function testMarkAsUsed(): void
    {
        // Insert verification
        $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used)
            VALUES ('test-id', 'user-id', '$this->generateValidToken()', '{$expiresAt}', 0)
        ");

        $this->repository->markAsUsed('$this->generateValidToken()');

        // Verify it was marked as used
        $stmt = $this->pdo->prepare('SELECT * FROM email_verifications WHERE token = ?');
        $stmt->execute(['$this->generateValidToken()']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1, $row['is_used']);
        $this->assertNotNull($row['used_at']);
    }

    public function testMarkAsUsedNotFound(): void
    {
        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage('Email verification token not found');

        $this->repository->markAsUsed('nonexistent-token');
    }

    public function testDeleteExpired(): void
    {
        // Insert expired and valid tokens
        $expiredAt = (new DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $validAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used)
            VALUES 
                ('expired-id', 'user-id', '$this->generateValidToken()', '{$expiredAt}', 0),
                ('valid-id', 'user-id', '$this->generateValidToken()', '{$validAt}', 0)
        ");

        $deletedCount = $this->repository->deleteExpired();

        $this->assertEquals(1, $deletedCount);

        // Verify only expired token was deleted
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_verifications');
        $stmt->execute();
        $count = $stmt->fetchColumn();

        $this->assertEquals(1, $count);

        // Verify valid token still exists
        $stmt = $this->pdo->prepare('SELECT * FROM email_verifications WHERE token = ?');
        $stmt->execute(['$this->generateValidToken()']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
    }

    public function testHasRecentVerification(): void
    {
        // Insert recent verification
        $recentTime = (new DateTime('-30 minutes'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used, created_at)
            VALUES (
                'recent-id', 
                'user-id', 
                '$this->generateValidToken()', 
                datetime('now', '+24 hours'), 
                0, 
                '{$recentTime}'
            )
        ");

        $this->assertTrue($this->repository->hasRecentVerification('user-id', 60)); // 60 minutes
        $this->assertFalse($this->repository->hasRecentVerification('user-id', 15)); // 15 minutes
    }

    public function testHasRecentVerificationNotFound(): void
    {
        $this->assertFalse($this->repository->hasRecentVerification('nonexistent-user', 60));
    }

    public function testHasRecentVerificationWithOldVerification(): void
    {
        // Insert old verification
        $oldTime = (new DateTime('-2 hours'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used, created_at)
            VALUES ('old-id', 'user-id', '$this->generateValidToken()', datetime('now', '+24 hours'), 0, '{$oldTime}')
        ");

        $this->assertFalse($this->repository->hasRecentVerification('user-id', 60)); // 60 minutes
    }

    public function testDeleteByUserId(): void
    {
        // Insert multiple verifications for same user
        $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $this->pdo->exec("
            INSERT INTO email_verifications (id, user_id, token, expires_at, is_used)
            VALUES 
                ('id1', 'user-id', '$this->generateValidToken()', '{$expiresAt}', 0),
                ('id2', 'user-id', '$this->generateValidToken()', '{$expiresAt}', 0)
        ");

        $deletedCount = $this->repository->deleteByUserId('user-id');

        $this->assertEquals(2, $deletedCount);

        // Verify all verifications were deleted
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_verifications WHERE user_id = ?');
        $stmt->execute(['user-id']);
        $count = $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    public function testDeleteByUserIdNotFound(): void
    {
        $deletedCount = $this->repository->deleteByUserId('nonexistent-user');
        $this->assertEquals(0, $deletedCount);
    }
}
