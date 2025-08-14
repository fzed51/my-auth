<?php

declare(strict_types=1);

namespace MyAuth\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MyAuth\Repository\UserRepository;
use MyAuth\Repository\EmailVerificationRepository;
use MyAuth\Entity\User;
use MyAuth\Entity\EmailVerification;
use MyAuth\Exception\UserException;
use MyAuth\Exception\UserAlreadyExistsException;
use MyAuth\Exception\UserNotFoundException;
use MyAuth\Exception\EmailVerificationException;
use DateTime;

class UserServiceTest extends TestCase
{
    private UserRepository|MockObject $userRepository;
    private EmailVerificationRepository|MockObject $emailVerificationRepository;
    private EmailService|MockObject $emailService;
    private UserService $userService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->emailVerificationRepository = $this->createMock(EmailVerificationRepository::class);
        $this->emailService = $this->createMock(EmailService::class);

        $this->userService = new UserService(
            $this->userRepository,
            $this->emailVerificationRepository,
            $this->emailService
        );
    }

    public function testRegisterSuccess(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ];

        $this->userRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('test@example.com')
            ->willReturn(false);

        $this->userRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (User $user) {
                return $user->getEmail() === 'test@example.com' &&
                       $user->getFirstName() === 'John' &&
                       $user->getLastName() === 'Doe' &&
                       password_verify('SecurePass123!', $user->getPasswordHash()) &&
                       !$user->isActive() &&
                       !$user->isVerified();
            }));

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf(EmailVerification::class));

        $this->emailService
            ->expects($this->once())
            ->method('sendVerificationEmail')
            ->with(
                $this->isInstanceOf(User::class),
                $this->isType('string')
            );

        $user = $this->userService->register($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->isVerified());
    }

    public function testRegisterEmailAlreadyExists(): void
    {
        $userData = [
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ];

        $this->userRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('existing@example.com')
            ->willReturn(true);

        $this->userRepository
            ->expects($this->never())
            ->method('create');

        $this->expectException(UserAlreadyExistsException::class);
        $this->expectExceptionMessage('Email already exists');

        $this->userService->register($userData);
    }

    public function testRegisterInvalidEmail(): void
    {
        $userData = [
            'email' => 'invalid-email',
            'password' => 'SecurePass123!',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ];

        $this->expectException(UserException::class);

        $this->userService->register($userData);
    }

    public function testRegisterMissingRequiredFields(): void
    {
        $userData = [
            'email' => 'test@example.com',
            // Missing password
            'firstName' => 'John',
            'lastName' => 'Doe'
        ];

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Required fields missing: password');

        $this->userService->register($userData);
    }

    public function testVerifyEmailSuccess(): void
    {
        $token = 'valid-token';
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $verification = new EmailVerification(
            id: 'verification-id',
            userId: 'user-id',
            token: $token,
            expiresAt: new DateTime('+24 hours')
        );

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('findValidByToken')
            ->with($token)
            ->willReturn($verification);

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with('user-id')
            ->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('updateUser')
            ->with($this->callback(function (User $user) {
                return $user->isVerified() && $user->isActive();
            }));

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('markAsUsed')
            ->with($token);

        $this->emailService
            ->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with($this->isInstanceOf(User::class));

        $verifiedUser = $this->userService->verifyEmail($token);

        $this->assertInstanceOf(User::class, $verifiedUser);
        $this->assertTrue($verifiedUser->isVerified());
        $this->assertTrue($verifiedUser->isActive());
    }

    public function testVerifyEmailInvalidToken(): void
    {
        $token = 'invalid-token';

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('findValidByToken')
            ->with($token)
            ->willReturn(null);

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage('Invalid or expired verification token');

        $this->userService->verifyEmail($token);
    }

    public function testVerifyEmailUserNotFound(): void
    {
        $token = 'valid-token';
        $verification = new EmailVerification(
            id: 'verification-id',
            userId: 'nonexistent-user',
            token: $token,
            expiresAt: new DateTime('+24 hours')
        );

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('findValidByToken')
            ->with($token)
            ->willReturn($verification);

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with('nonexistent-user')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->verifyEmail($token);
    }

    public function testResendVerificationEmailSuccess(): void
    {
        $email = 'test@example.com';
        $user = new User(
            id: 'user-id',
            email: $email,
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('hasRecentVerification')
            ->with('user-id', 15)
            ->willReturn(false);

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf(EmailVerification::class));

        $this->emailService
            ->expects($this->once())
            ->method('sendVerificationEmail')
            ->with($user, $this->isType('string'));

        $this->userService->resendVerificationEmail($email);
    }

    public function testResendVerificationEmailUserNotFound(): void
    {
        $email = 'nonexistent@example.com';

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->resendVerificationEmail($email);
    }

    public function testResendVerificationEmailRateLimit(): void
    {
        $email = 'test@example.com';
        $user = new User(
            id: 'user-id',
            email: $email,
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->emailVerificationRepository
            ->expects($this->once())
            ->method('hasRecentVerification')
            ->with('user-id', 15)
            ->willReturn(true);

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage(
            'Verification email already sent recently. Please wait before requesting another.'
        );

        $this->userService->resendVerificationEmail($email);
    }

    public function testUpdateProfileSuccess(): void
    {
        $userId = 'user-id';
        $updateData = [
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ];

        $user = new User(
            id: $userId,
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('updateUser')
            ->with($this->callback(function (User $user) {
                return $user->getFirstName() === 'Jane' &&
                       $user->getLastName() === 'Smith';
            }));

        $updatedUser = $this->userService->updateProfile($userId, $updateData);

        $this->assertEquals('Jane', $updatedUser->getFirstName());
        $this->assertEquals('Smith', $updatedUser->getLastName());
    }

    public function testUpdateProfileUserNotFound(): void
    {
        $userId = 'nonexistent-user';
        $updateData = ['firstName' => 'Jane'];

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->updateProfile($userId, $updateData);
    }

    public function testChangePasswordSuccess(): void
    {
        $userId = 'user-id';
        $oldPassword = 'OldPass123!';
        $newPassword = 'NewPass123!';

        $user = new User(
            id: $userId,
            email: 'test@example.com',
            passwordHash: password_hash($oldPassword, PASSWORD_DEFAULT),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $this->userRepository
            ->expects($this->once())
            ->method('updateUser')
            ->with($this->callback(function (User $user) use ($newPassword) {
                return password_verify($newPassword, $user->getPasswordHash());
            }));

        $this->userService->changePassword($userId, $oldPassword, $newPassword);
    }

    public function testChangePasswordUserNotFound(): void
    {
        $userId = 'nonexistent-user';

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->changePassword($userId, 'old', 'new');
    }

    public function testChangePasswordIncorrectOldPassword(): void
    {
        $userId = 'user-id';
        $user = new User(
            id: $userId,
            email: 'test@example.com',
            passwordHash: password_hash('correct-password', PASSWORD_DEFAULT),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        $this->userService->changePassword($userId, 'wrong-password', 'new-password');
    }

    public function testChangePasswordInvalidNewPassword(): void
    {
        $userId = 'user-id';
        $oldPassword = 'OldPass123!';

        $user = new User(
            id: $userId,
            email: 'test@example.com',
            passwordHash: password_hash($oldPassword, PASSWORD_DEFAULT),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters long');

        $this->userService->changePassword($userId, $oldPassword, 'short');
    }
}
