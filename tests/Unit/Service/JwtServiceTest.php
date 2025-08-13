<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use MyAuth\Service\JwtService;
use MyAuth\Repository\JwtBlacklistRepository;
use MyAuth\Repository\RefreshTokenRepository;

/**
 * @covers \MyAuth\Service\JwtService
 */
class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;
    private JwtBlacklistRepository $blacklistRepository;
    private RefreshTokenRepository $refreshTokenRepository;

    protected function setUp(): void
    {
        $this->blacklistRepository = $this->createMock(JwtBlacklistRepository::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        
        $config = [
            'secret' => 'test-secret-key-for-testing-only',
            'algorithm' => 'HS256',
            'expiration' => 3600,
            'issuer' => 'test-issuer',
            'audience' => 'test-audience',
            'leeway' => 60
        ];
        
        $this->jwtService = new JwtService($config, $this->blacklistRepository, $this->refreshTokenRepository);
    }

    public function testGenerateToken(): void
    {
        $userId = 123;
        $additionalClaims = ['role' => 'user'];
        
        $token = $this->jwtService->generateToken($userId, $additionalClaims);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        
        // Vérifier que le token contient 3 parties (header.payload.signature)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testValidateToken(): void
    {
        $userId = 123;
        $additionalClaims = ['role' => 'user', 'email' => 'test@example.com'];
        
        // Mock du repository pour indiquer que le token n'est pas blacklisté
        $this->blacklistRepository
            ->expects($this->once())
            ->method('isBlacklisted')
            ->willReturn(false);
        
        $token = $this->jwtService->generateToken($userId, $additionalClaims);
        $payload = $this->jwtService->validateToken($token);
        
        $this->assertEquals($userId, $payload['user_id']);
        $this->assertEquals('user', $payload['role']);
        $this->assertEquals('test@example.com', $payload['email']);
        $this->assertEquals('test-issuer', $payload['iss']);
        $this->assertEquals('test-audience', $payload['aud']);
    }

    public function testValidateTokenWithBlacklistedToken(): void
    {
        $userId = 123;
        
        // Mock du repository pour indiquer que le token est blacklisté
        $this->blacklistRepository
            ->expects($this->once())
            ->method('isBlacklisted')
            ->willReturn(true);
        
        $token = $this->jwtService->generateToken($userId);
        
        $this->expectException(\MyAuth\Exception\AuthException::class);
        $this->expectExceptionMessage('Token has been revoked');
        
        $this->jwtService->validateToken($token);
    }

    public function testValidateTokenWithInvalidToken(): void
    {
        $this->blacklistRepository
            ->expects($this->never())
            ->method('isBlacklisted');
        
        $this->expectException(\MyAuth\Exception\AuthException::class);
        
        $this->jwtService->validateToken('invalid.token.here');
    }

    public function testIsTokenValid(): void
    {
        $userId = 123;
        
        $this->blacklistRepository
            ->expects($this->once())
            ->method('isBlacklisted')
            ->willReturn(false);
        
        $token = $this->jwtService->generateToken($userId);
        
        $this->assertTrue($this->jwtService->isTokenValid($token));
        $this->assertFalse($this->jwtService->isTokenValid('invalid.token'));
    }

    public function testGetUserIdFromToken(): void
    {
        $userId = 123;
        
        $this->blacklistRepository
            ->expects($this->once())
            ->method('isBlacklisted')
            ->willReturn(false);
        
        $token = $this->jwtService->generateToken($userId);
        
        $this->assertEquals($userId, $this->jwtService->getUserIdFromToken($token));
        $this->assertNull($this->jwtService->getUserIdFromToken('invalid.token'));
    }

    public function testRevokeToken(): void
    {
        $userId = 123;
        $token = $this->jwtService->generateToken($userId);
        
        $this->blacklistRepository
            ->expects($this->once())
            ->method('addToBlacklist')
            ->willReturn(true);
        
        $result = $this->jwtService->revokeToken($token);
        
        $this->assertTrue($result);
    }

    public function testRevokeAllUserTokens(): void
    {
        $userId = 123;
        
        $this->blacklistRepository
            ->expects($this->once())
            ->method('blacklistAllUserTokens')
            ->with($userId, $this->isInstanceOf(\DateTime::class))
            ->willReturn(true);
        
        $result = $this->jwtService->revokeAllUserTokens($userId);
        
        $this->assertTrue($result);
    }

    public function testGetTokenExpiration(): void
    {
        $userId = 123;
        $token = $this->jwtService->generateToken($userId);
        
        $expiration = $this->jwtService->getTokenExpiration($token);
        
        $this->assertInstanceOf(\DateTime::class, $expiration);
        
        // Vérifier que l'expiration est dans le futur
        $now = new \DateTime();
        $this->assertGreaterThan($now, $expiration);
        
        // Vérifier que l'expiration est environ dans 1 heure (avec une marge de 5 minutes)
        $expectedExpiration = clone $now;
        $expectedExpiration->modify('+3600 seconds');
        $diff = abs($expiration->getTimestamp() - $expectedExpiration->getTimestamp());
        $this->assertLessThan(300, $diff); // Moins de 5 minutes de différence
    }

    public function testIsTokenExpiringSoon(): void
    {
        $userId = 123;
        $token = $this->jwtService->generateToken($userId);
        
        // Un token qui vient d'être créé ne devrait pas expirer bientôt
        $this->assertFalse($this->jwtService->isTokenExpiringSoon($token, 5));
        
        // Test avec un token invalide
        $this->assertTrue($this->jwtService->isTokenExpiringSoon('invalid.token', 5));
    }

    public function testDecodeTokenWithoutValidation(): void
    {
        $userId = 123;
        $additionalClaims = ['role' => 'admin'];
        $token = $this->jwtService->generateToken($userId, $additionalClaims);
        
        $payload = $this->jwtService->decodeTokenWithoutValidation($token);
        
        $this->assertEquals($userId, $payload['user_id']);
        $this->assertEquals('admin', $payload['role']);
    }

    public function testDecodeTokenWithoutValidationInvalidToken(): void
    {
        $this->expectException(\MyAuth\Exception\AuthException::class);
        $this->expectExceptionMessage('Invalid token format');
        
        $this->jwtService->decodeTokenWithoutValidation('invalid-token');
    }
}
