<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use MyAuth\Repository\RefreshTokenRepository;
use PDO;
use PDOStatement;

/**
 * Test du repository des refresh tokens
 */
class RefreshTokenRepositoryTest extends TestCase
{
    private RefreshTokenRepository $repository;
    private PDO $pdo;
    private PDOStatement $statement;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->statement = $this->createMock(PDOStatement::class);
        $this->repository = new RefreshTokenRepository($this->pdo);
    }

    public function testCanCreateRefreshToken(): void
    {
        $userId = 123;
        $tokenHash = 'abc123def456';
        $expiresAt = '2024-12-31 23:59:59';

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO refresh_tokens'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->exactly(3))
            ->method('bindValue')
            ->withConsecutive(
                [':user_id', $userId, PDO::PARAM_INT],
                [':token_hash', $tokenHash, PDO::PARAM_STR],
                [':expires_at', $expiresAt, PDO::PARAM_STR]
            );

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $result = $this->repository->create($userId, $tokenHash, $expiresAt);

        $this->assertTrue($result);
    }

    public function testCreateReturnsFalseOnFailure(): void
    {
        $userId = 123;
        $tokenHash = 'abc123def456';
        $expiresAt = '2024-12-31 23:59:59';

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(false);

        $result = $this->repository->create($userId, $tokenHash, $expiresAt);

        $this->assertFalse($result);
    }

    public function testCanFindByTokenHash(): void
    {
        $tokenHash = 'abc123def456';
        $expectedData = [
            'id' => 1,
            'user_id' => 123,
            'token_hash' => $tokenHash,
            'expires_at' => '2024-12-31 23:59:59',
            'is_revoked' => false,
            'user_email' => 'test@example.com',
            'user_first_name' => 'John',
            'user_last_name' => 'Doe',
            'user_is_email_verified' => true,
            'user_is_active' => true
        ];

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT rt.*, u.email'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':token_hash', $tokenHash, PDO::PARAM_STR);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $result = $this->repository->findByTokenHash($tokenHash);

        $this->assertEquals($expectedData, $result);
    }

    public function testFindByTokenHashReturnsNullWhenNotFound(): void
    {
        $tokenHash = 'nonexistent-token';

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = $this->repository->findByTokenHash($tokenHash);

        $this->assertNull($result);
    }

    public function testCanRevokeByTokenHash(): void
    {
        $tokenHash = 'abc123def456';

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE refresh_tokens SET is_revoked = 1'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':token_hash', $tokenHash, PDO::PARAM_STR);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = $this->repository->revokeByTokenHash($tokenHash);

        $this->assertTrue($result);
    }

    public function testRevokeByTokenHashReturnsFalseWhenNoRowsAffected(): void
    {
        $tokenHash = 'nonexistent-token';

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $result = $this->repository->revokeByTokenHash($tokenHash);

        $this->assertFalse($result);
    }

    public function testCanRevokeAllForUser(): void
    {
        $userId = 123;

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('UPDATE refresh_tokens SET is_revoked = 1 WHERE user_id = :user_id'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':user_id', $userId, PDO::PARAM_INT);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(3); // 3 tokens révoqués

        $result = $this->repository->revokeAllForUser($userId);

        $this->assertEquals(3, $result);
    }

    public function testCanDeleteExpiredTokens(): void
    {
        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM refresh_tokens WHERE expires_at < NOW()'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(5); // 5 tokens supprimés

        $result = $this->repository->deleteExpiredTokens();

        $this->assertEquals(5, $result);
    }

    public function testCanDeleteRevokedTokens(): void
    {
        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('DELETE FROM refresh_tokens WHERE is_revoked = 1'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(2); // 2 tokens supprimés

        $result = $this->repository->deleteRevokedTokens();

        $this->assertEquals(2, $result);
    }

    public function testCanCleanupTokens(): void
    {
        // Le cleanup appelle deleteExpiredTokens et deleteRevokedTokens
        $this->pdo
            ->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->exactly(2))
            ->method('rowCount')
            ->willReturnOnConsecutiveCalls(3, 2); // 3 expirés + 2 révoqués

        $result = $this->repository->cleanup();

        $this->assertEquals(5, $result); // Total des tokens supprimés
    }

    public function testCanCountActiveTokensForUser(): void
    {
        $userId = 123;

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT COUNT(*) FROM refresh_tokens'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':user_id', $userId, PDO::PARAM_INT);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('2'); // 2 tokens actifs

        $result = $this->repository->countActiveTokensForUser($userId);

        $this->assertEquals(2, $result);
    }

    public function testCanGetActiveTokensForUser(): void
    {
        $userId = 123;
        $expectedTokens = [
            [
                'id' => 1,
                'token_hash' => 'hash1',
                'created_at' => '2024-01-01 10:00:00',
                'expires_at' => '2024-12-31 23:59:59'
            ],
            [
                'id' => 2,
                'token_hash' => 'hash2',
                'created_at' => '2024-01-02 10:00:00',
                'expires_at' => '2024-12-31 23:59:59'
            ]
        ];

        $this->pdo
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, token_hash, created_at, expires_at'))
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(':user_id', $userId, PDO::PARAM_INT);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->statement
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedTokens);

        $result = $this->repository->getActiveTokensForUser($userId);

        $this->assertEquals($expectedTokens, $result);
    }
}
