<?php

declare(strict_types=1);

namespace MyAuth\Controller;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use MyAuth\Service\UserService;
use MyAuth\Entity\User;
use MyAuth\Exception\UserException;
use MyAuth\Exception\UserAlreadyExistsException;
use MyAuth\Exception\UserNotFoundException;
use MyAuth\Exception\EmailVerificationException;
use DateTime;

class AuthControllerTest extends TestCase
{
    private UserService|MockObject $userService;
    private ResponseFactoryInterface|MockObject $responseFactory;
    private AuthController $authController;
    private ServerRequestInterface|MockObject $request;
    private ResponseInterface|MockObject $response;
    private StreamInterface|MockObject $stream;

    protected function setUp(): void
    {
        $this->userService = $this->createMock(UserService::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream = $this->createMock(StreamInterface::class);

        $this->authController = new AuthController(
            $this->userService,
            $this->responseFactory
        );

        // Setup response factory mock
        $this->responseFactory
            ->method('createResponse')
            ->willReturn($this->response);

        // Setup response mock
        $this->response
            ->method('withHeader')
            ->willReturnSelf();

        $this->response
            ->method('withStatus')
            ->willReturnSelf();

        $this->response
            ->method('getBody')
            ->willReturn($this->stream);
    }

    public function testRegisterSuccess(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->with($userData)
            ->willReturn($user);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === true &&
                       $data['message'] === 'User registered successfully. ' .
                                        'Please check your email to verify your account.' &&
                       isset($data['user']) &&
                       $data['user']['email'] === 'test@example.com' &&
                       $data['user']['first_name'] === 'John' &&
                       $data['user']['last_name'] === 'Doe' &&
                       !isset($data['user']['password_hash']);
            }));

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRegisterEmailAlreadyExists(): void
    {
        $userData = [
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->with($userData)
            ->willThrowException(new UserAlreadyExistsException('Email already exists'));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(409)
            ->willReturnSelf();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === false &&
                       $data['error'] === 'Email already exists';
            }));

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRegisterInvalidData(): void
    {
        $userData = [
            'email' => 'invalid-email',
            'password' => 'short',
            'first_name' => '',
            'last_name' => 'Doe'
        ];

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->with($userData)
            ->willThrowException(new UserException('Invalid email format'));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturnSelf();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === false &&
                       $data['error'] === 'Invalid email format';
            }));

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
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
        $user->verifyEmail(); // Mark as verified

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['token' => $token]);

        $this->userService
            ->expects($this->once())
            ->method('verifyEmail')
            ->with($token)
            ->willReturn($user);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === true &&
                       $data['message'] === 'Email verified successfully' &&
                       isset($data['user']) &&
                       $data['user']['is_verified'] === true;
            }));

        $response = $this->authController->verifyEmail($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testVerifyEmailInvalidToken(): void
    {
        $token = 'invalid-token';

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['token' => $token]);

        $this->userService
            ->expects($this->once())
            ->method('verifyEmail')
            ->with($token)
            ->willThrowException(new EmailVerificationException('Invalid or expired verification token'));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturnSelf();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === false &&
                       $data['error'] === 'Invalid or expired verification token';
            }));

        $response = $this->authController->verifyEmail($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testResendVerificationSuccess(): void
    {
        $email = 'test@example.com';

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['email' => $email]);

        $this->userService
            ->expects($this->once())
            ->method('resendVerificationEmail')
            ->with($email);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === true &&
                       $data['message'] === 'Verification email sent successfully';
            }));

        $response = $this->authController->resendVerification($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testResendVerificationRateLimit(): void
    {
        $email = 'test@example.com';

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['email' => $email]);

        $this->userService
            ->expects($this->once())
            ->method('resendVerificationEmail')
            ->with($email)
            ->willThrowException(new EmailVerificationException(
                'Verification email already sent recently. Please wait before requesting another.'
            ));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(429)
            ->willReturnSelf();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === false &&
                       $data['error'] === 'Verification email already sent recently. ' .
                                       'Please wait before requesting another.';
            }));

        $response = $this->authController->resendVerification($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testGetProfileSuccess(): void
    {
        $userId = 'user-id';
        $user = new User(
            id: $userId,
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $this->userService
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === true &&
                       isset($data['user']) &&
                       $data['user']['email'] === 'test@example.com' &&
                       !isset($data['user']['password_hash']);
            }));

        $response = $this->authController->getProfile($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testUpdateProfileSuccess(): void
    {
        $userId = 'user-id';
        $updateData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ];

        $updatedUser = new User(
            id: $userId,
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Jane',
            lastName: 'Smith'
        );

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->userService
            ->expects($this->once())
            ->method('updateProfile')
            ->with($userId, $updateData)
            ->willReturn($updatedUser);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === true &&
                       $data['message'] === 'Profile updated successfully' &&
                       isset($data['user']) &&
                       $data['user']['first_name'] === 'Jane' &&
                       $data['user']['last_name'] === 'Smith';
            }));

        $response = $this->authController->updateProfile($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testChangePasswordSuccess(): void
    {
        $userId = 'user-id';
        $passwordData = [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass123!'
        ];

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($passwordData);

        $this->userService
            ->expects($this->once())
            ->method('changePassword')
            ->with($userId, 'OldPass123!', 'NewPass123!');

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === true &&
                       $data['message'] === 'Password changed successfully';
            }));

        $response = $this->authController->changePassword($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testChangePasswordIncorrectCurrent(): void
    {
        $userId = 'user-id';
        $passwordData = [
            'current_password' => 'WrongPass123!',
            'new_password' => 'NewPass123!'
        ];

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($passwordData);

        $this->userService
            ->expects($this->once())
            ->method('changePassword')
            ->with($userId, 'WrongPass123!', 'NewPass123!')
            ->willThrowException(new UserException('Current password is incorrect'));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturnSelf();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === false &&
                       $data['error'] === 'Current password is incorrect';
            }));

        $response = $this->authController->changePassword($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMissingRequiredFields(): void
    {
        // Test register with missing data
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn(['email' => 'test@example.com']); // Missing other fields

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new UserException('Required fields missing: password, first_name, last_name'));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturnSelf();

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testInternalServerError(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($userData);

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(500)
            ->willReturnSelf();

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($content) {
                $data = json_decode($content, true);
                return $data['success'] === false &&
                       $data['error'] === 'Internal server error';
            }));

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
