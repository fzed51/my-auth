<?php

declare(strict_types=1);

namespace MyAuth\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use MyAuth\Service\AuthService;
use MyAuth\Repository\UserRepository;
use MyAuth\Service\JwtService;
use MyAuth\Repository\LoginAttemptRepository;
use MyAuth\Entity\User;
use MyAuth\Exception\AuthException;
use MyAuth\Exception\ValidationException;

/**
 * Test du service d'authentification
 */
class AuthServiceTest extends TestCase
{
    private AuthService $authService;
    private UserRepository $userRepository;
    private JwtService $jwtService;
    private LoginAttemptRepository $loginAttemptRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->jwtService = $this->createMock(JwtService::class);
        $this->loginAttemptRepository = $this->createMock(LoginAttemptRepository::class);

        $this->authService = new AuthService(
            $this->userRepository,
            $this->jwtService,
            $this->loginAttemptRepository
        );
    }

    public function testCanRegisterUser(): void
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!'
        ];

        $user = new User();
        $user->setId(1);
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setEmail('john.doe@example.com');

        $this->userService
            ->expects($this->once())
            ->method('createUser')
            ->with($userData)
            ->willReturn($user);

        $this->emailService
            ->expects($this->once())
            ->method('sendEmailVerification')
            ->with($user);

        $result = $this->authService->register($userData);

        $this->assertIsArray($result);
        $this->assertEquals('Compte créé avec succès. Veuillez vérifier votre email.', $result['message']);
        $this->assertEquals($user->getId(), $result['user_id']);
    }

    public function testRegisterThrowsExceptionForInvalidData(): void
    {
        $userData = [
            'first_name' => '',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'password' => '123'
        ];

        $this->userService
            ->expects($this->once())
            ->method('createUser')
            ->with($userData)
            ->willThrowException(new ValidationException('Données invalides'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Données invalides');

        $this->authService->register($userData);
    }

    public function testCanLoginWithValidCredentials(): void
    {
        $email = 'john.doe@example.com';
        $password = 'SecurePass123!';
        $ipAddress = '192.168.1.1';

        $user = new User();
        $user->setId(1);
        $user->setEmail($email);
        $user->setIsEmailVerified(true);
        $user->setIsActive(true);

        $this->userService
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->userService
            ->expects($this->once())
            ->method('verifyPassword')
            ->with($password, $user->getPasswordHash())
            ->willReturn(true);

        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('getFailedAttempts')
            ->with($email, $ipAddress)
            ->willReturn(0);

        $this->jwtService
            ->expects($this->once())
            ->method('generateToken')
            ->with($user)
            ->willReturn('jwt-token-123');

        $this->jwtService
            ->expects($this->once())
            ->method('generateRefreshToken')
            ->with($user->getId())
            ->willReturn('refresh-token-456');

        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('recordSuccess')
            ->with($email, $ipAddress);

        $result = $this->authService->login($email, $password, $ipAddress);

        $this->assertIsArray($result);
        $this->assertEquals('jwt-token-123', $result['access_token']);
        $this->assertEquals('refresh-token-456', $result['refresh_token']);
        $this->assertEquals(3600, $result['expires_in']);
        $this->assertArrayHasKey('user', $result);
    }

    public function testLoginThrowsExceptionForUnverifiedEmail(): void
    {
        $email = 'unverified@example.com';
        $password = 'SecurePass123!';
        $ipAddress = '192.168.1.1';

        $user = new User();
        $user->setId(1);
        $user->setEmail($email);
        $user->setIsEmailVerified(false); // Email non vérifié

        $this->userService
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('getFailedAttempts')
            ->with($email, $ipAddress)
            ->willReturn(0);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Veuillez vérifier votre email avant de vous connecter');

        $this->authService->login($email, $password, $ipAddress);
    }

    public function testLoginThrowsExceptionForTooManyFailedAttempts(): void
    {
        $email = 'blocked@example.com';
        $password = 'SecurePass123!';
        $ipAddress = '192.168.1.1';

        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('getFailedAttempts')
            ->with($email, $ipAddress)
            ->willReturn(5); // Trop de tentatives

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Trop de tentatives de connexion. Réessayez plus tard.');

        $this->authService->login($email, $password, $ipAddress);
    }

    public function testLoginThrowsExceptionForInvalidPassword(): void
    {
        $email = 'john.doe@example.com';
        $password = 'WrongPassword';
        $ipAddress = '192.168.1.1';

        $user = new User();
        $user->setId(1);
        $user->setEmail($email);
        $user->setIsEmailVerified(true);
        $user->setIsActive(true);

        $this->userService
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('getFailedAttempts')
            ->with($email, $ipAddress)
            ->willReturn(0);

        $this->userService
            ->expects($this->once())
            ->method('verifyPassword')
            ->with($password, $user->getPasswordHash())
            ->willReturn(false);

        $this->loginAttemptRepository
            ->expects($this->once())
            ->method('recordFailure')
            ->with($email, $ipAddress);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Email ou mot de passe incorrect');

        $this->authService->login($email, $password, $ipAddress);
    }

    public function testCanRefreshToken(): void
    {
        $refreshToken = 'valid-refresh-token';

        $newTokens = [
            'access_token' => 'new-jwt-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'user' => [
                'id' => 1,
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe'
            ]
        ];

        $this->jwtService
            ->expects($this->once())
            ->method('refreshAccessToken')
            ->with($refreshToken)
            ->willReturn($newTokens);

        $result = $this->authService->refreshToken($refreshToken);

        $this->assertIsArray($result);
        $this->assertEquals('new-jwt-token', $result['access_token']);
        $this->assertEquals('new-refresh-token', $result['refresh_token']);
        $this->assertEquals(3600, $result['expires_in']);
    }

    public function testRefreshTokenThrowsExceptionForInvalidToken(): void
    {
        $refreshToken = 'invalid-refresh-token';

        $this->jwtService
            ->expects($this->once())
            ->method('refreshAccessToken')
            ->with($refreshToken)
            ->willThrowException(new AuthException('Refresh token invalide'));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Refresh token invalide');

        $this->authService->refreshToken($refreshToken);
    }

    public function testCanVerifyEmail(): void
    {
        $token = 'valid-verification-token';

        $this->userService
            ->expects($this->once())
            ->method('verifyEmail')
            ->with($token)
            ->willReturn(true);

        $result = $this->authService->verifyEmail($token);

        $this->assertIsArray($result);
        $this->assertEquals('Email vérifié avec succès', $result['message']);
    }

    public function testVerifyEmailThrowsExceptionForInvalidToken(): void
    {
        $token = 'invalid-verification-token';

        $this->userService
            ->expects($this->once())
            ->method('verifyEmail')
            ->with($token)
            ->willThrowException(new AuthException('Token de vérification invalide'));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Token de vérification invalide');

        $this->authService->verifyEmail($token);
    }

    public function testCanLogout(): void
    {
        $jwtToken = 'jwt-token-to-blacklist';
        $refreshToken = 'refresh-token-to-revoke';

        $this->jwtService
            ->expects($this->once())
            ->method('blacklistToken')
            ->with($jwtToken);

        $this->jwtService
            ->expects($this->once())
            ->method('revokeRefreshToken')
            ->with($refreshToken)
            ->willReturn(true);

        $result = $this->authService->logout($jwtToken, $refreshToken);

        $this->assertIsArray($result);
        $this->assertEquals('Déconnexion réussie', $result['message']);
    }

    public function testCanRequestPasswordReset(): void
    {
        $email = 'john.doe@example.com';

        $user = new User();
        $user->setId(1);
        $user->setEmail($email);

        $this->userService
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->emailService
            ->expects($this->once())
            ->method('sendPasswordReset')
            ->with($user);

        $result = $this->authService->requestPasswordReset($email);

        $this->assertIsArray($result);
        $this->assertEquals('Email de réinitialisation envoyé', $result['message']);
    }

    public function testRequestPasswordResetDoesNotRevealNonExistentEmail(): void
    {
        $email = 'nonexistent@example.com';

        $this->userService
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->emailService
            ->expects($this->never())
            ->method('sendPasswordReset');

        $result = $this->authService->requestPasswordReset($email);

        // Même message pour ne pas révéler l'existence ou non de l'email
        $this->assertIsArray($result);
        $this->assertEquals('Email de réinitialisation envoyé', $result['message']);
    }
}
