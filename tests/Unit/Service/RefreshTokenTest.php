<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use MyAuth\Service\JwtService;
use MyAuth\Repository\JwtBlacklistRepository;
use MyAuth\Repository\RefreshTokenRepository;
use MyAuth\Exception\AuthException;
use PDO;

/**
 * Test du système de refresh token
 */
class RefreshTokenTest extends TestCase
{
    private JwtService $jwtService;
    private JwtBlacklistRepository $blacklistRepository;
    private RefreshTokenRepository $refreshTokenRepository;
    private PDO $pdo;

    protected function setUp(): void
    {
        // Configuration JWT pour les tests
        $jwtConfig = [
            'secret' => 'test-secret-key-for-unit-tests-minimum-32-chars',
            'algorithm' => 'HS256',
            'expiration' => 3600,
            'issuer' => 'test-issuer',
            'audience' => 'test-audience',
            'leeway' => 60
        ];

        // Mock PDO pour les tests
        $this->pdo = $this->createMock(PDO::class);
        
        // Création des repositories avec mocks
        $this->blacklistRepository = $this->createMock(JwtBlacklistRepository::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        
        $this->jwtService = new JwtService($jwtConfig, $this->blacklistRepository, $this->refreshTokenRepository);
    }

    public function testCanGenerateRefreshToken(): void
    {
        $userId = 123;
        
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('create')
            ->with($userId, $this->isType('string'), $this->isType('string'))
            ->willReturn(true);

        $refreshToken = $this->jwtService->generateRefreshToken($userId);
        
        $this->assertIsString($refreshToken);
        $this->assertEquals(64, strlen($refreshToken)); // 32 bytes en hex = 64 caractères
    }

    public function testCanValidateValidRefreshToken(): void
    {
        $refreshToken = 'valid-refresh-token-123';
        $tokenHash = hash('sha256', $refreshToken);
        
        $tokenData = [
            'id' => 1,
            'user_id' => 123,
            'token_hash' => $tokenHash,
            'expires_at' => (new \DateTime('+30 days'))->format('Y-m-d H:i:s'),
            'is_revoked' => false,
            'user_email' => 'test@example.com',
            'user_first_name' => 'John',
            'user_last_name' => 'Doe',
            'user_is_email_verified' => true,
            'user_is_active' => true
        ];

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findByTokenHash')
            ->with($tokenHash)
            ->willReturn($tokenData);

        $result = $this->jwtService->validateRefreshToken($refreshToken);
        
        $this->assertIsArray($result);
        $this->assertEquals(123, $result['user_id']);
        $this->assertEquals('test@example.com', $result['user_email']);
    }

    public function testValidateRefreshTokenThrowsExceptionForInvalidToken(): void
    {
        $refreshToken = 'invalid-refresh-token';
        $tokenHash = hash('sha256', $refreshToken);
        
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findByTokenHash')
            ->with($tokenHash)
            ->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Refresh token invalide');
        
        $this->jwtService->validateRefreshToken($refreshToken);
    }

    public function testValidateRefreshTokenThrowsExceptionForExpiredToken(): void
    {
        $refreshToken = 'expired-refresh-token';
        $tokenHash = hash('sha256', $refreshToken);
        
        $tokenData = [
            'id' => 1,
            'user_id' => 123,
            'token_hash' => $tokenHash,
            'expires_at' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), // Expiré
            'is_revoked' => false,
            'user_email' => 'test@example.com',
            'user_is_email_verified' => true,
            'user_is_active' => true
        ];

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findByTokenHash')
            ->with($tokenHash)
            ->willReturn($tokenData);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Refresh token expiré');
        
        $this->jwtService->validateRefreshToken($refreshToken);
    }

    public function testValidateRefreshTokenThrowsExceptionForRevokedToken(): void
    {
        $refreshToken = 'revoked-refresh-token';
        $tokenHash = hash('sha256', $refreshToken);
        
        $tokenData = [
            'id' => 1,
            'user_id' => 123,
            'token_hash' => $tokenHash,
            'expires_at' => (new \DateTime('+30 days'))->format('Y-m-d H:i:s'),
            'is_revoked' => true, // Révoqué
            'user_email' => 'test@example.com',
            'user_is_email_verified' => true,
            'user_is_active' => true
        ];

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findByTokenHash')
            ->with($tokenHash)
            ->willReturn($tokenData);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Refresh token révoqué');
        
        $this->jwtService->validateRefreshToken($refreshToken);
    }

    public function testValidateRefreshTokenThrowsExceptionForInactiveUser(): void
    {
        $refreshToken = 'valid-refresh-token-inactive-user';
        $tokenHash = hash('sha256', $refreshToken);
        
        $tokenData = [
            'id' => 1,
            'user_id' => 123,
            'token_hash' => $tokenHash,
            'expires_at' => (new \DateTime('+30 days'))->format('Y-m-d H:i:s'),
            'is_revoked' => false,
            'user_email' => 'test@example.com',
            'user_is_email_verified' => true,
            'user_is_active' => false // Utilisateur inactif
        ];

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findByTokenHash')
            ->with($tokenHash)
            ->willReturn($tokenData);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Compte utilisateur inactif');
        
        $this->jwtService->validateRefreshToken($refreshToken);
    }

    public function testCanRevokeRefreshToken(): void
    {
        $refreshToken = 'token-to-revoke';
        $tokenHash = hash('sha256', $refreshToken);
        
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('revokeByTokenHash')
            ->with($tokenHash)
            ->willReturn(true);

        $result = $this->jwtService->revokeRefreshToken($refreshToken);
        
        $this->assertTrue($result);
    }

    public function testCanRevokeAllRefreshTokensForUser(): void
    {
        $userId = 123;
        
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('revokeAllForUser')
            ->with($userId)
            ->willReturn(3); // 3 tokens révoqués

        $result = $this->jwtService->revokeAllRefreshTokensForUser($userId);
        
        $this->assertEquals(3, $result);
    }

    public function testRefreshAccessTokenReturnsNewTokens(): void
    {
        $refreshToken = 'valid-refresh-token';
        $tokenHash = hash('sha256', $refreshToken);
        
        $tokenData = [
            'id' => 1,
            'user_id' => 123,
            'token_hash' => $tokenHash,
            'expires_at' => (new \DateTime('+30 days'))->format('Y-m-d H:i:s'),
            'is_revoked' => false,
            'user_email' => 'test@example.com',
            'user_first_name' => 'John',
            'user_last_name' => 'Doe',
            'user_is_email_verified' => true,
            'user_is_active' => true
        ];

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('findByTokenHash')
            ->with($tokenHash)
            ->willReturn($tokenData);

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('revokeByTokenHash')
            ->with($tokenHash)
            ->willReturn(true);

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $result = $this->jwtService->refreshAccessToken($refreshToken);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('user', $result);
        
        $this->assertIsString($result['access_token']);
        $this->assertIsString($result['refresh_token']);
        $this->assertEquals(3600, $result['expires_in']);
        
        // Vérifier que le nouveau refresh token est différent de l'ancien
        $this->assertNotEquals($refreshToken, $result['refresh_token']);
    }
}
