<?php

/**
 * AuthController Test Class
 *
 * This file contains unit tests for the AuthController class.
 * It tests all authentication-related endpoints including registration,
 * email verification, profile management, and password changes.
 *
 * @category Tests
 * @package  MyAuth\Controller
 * @author   Fabien Sanchez <fabien.sanchez@example.com>
 * @license  MIT License
 * @link     https://github.com/fabien/my-auth
 */

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

/**
 * Class AuthControllerTest
 *
 * Unit test class for AuthController.
 * Tests all authentication-related functionality including user registration,
 * email verification, profile management, and password changes.
 *
 * @category Tests
 * @package  MyAuth\Controller
 * @author   Fabien Sanchez <fabien.sanchez@example.com>
 * @license  MIT License
 * @link     https://github.com/fabien/my-auth
 */
class AuthControllerTest extends TestCase
{
    /**
     * Mock object for UserService
     *
     * @var UserService&MockObject
     */
    private MockObject $userService;

    /**
     * Mock object for ResponseFactoryInterface
     *
     * @var ResponseFactoryInterface&MockObject
     */
    private MockObject $responseFactory;

    /**
     * The AuthController instance being tested
     *
     * @var AuthController
     */
    private AuthController $authController;

    /**
     * Mock object for ServerRequestInterface
     *
     * @var ServerRequestInterface&MockObject
     */
    private MockObject $request;

    /**
     * Mock object for ResponseInterface
     *
     * @var ResponseInterface&MockObject
     */
    private MockObject $response;

    /**
     * Mock object for StreamInterface
     *
     * @var StreamInterface&MockObject
     */
    private MockObject $stream;

    /**
     * Helper method to safely decode JSON for testing
     *
     * @param string $content The JSON content to decode
     *
     * @return array<string, mixed> The decoded JSON as an associative array
     */
    private function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded, 'JSON decode should return an array');
        return $decoded;
    }

    /**
     * Helper method to create a mock stream with JSON data
     *
     * @param array<string, mixed> $data The data to encode as JSON
     *
     * @return MockObject The mock stream object
     */
    private function createJsonBodyStream(array $data): MockObject
    {
        $bodyStream = $this->createMock(StreamInterface::class);
        $bodyStream->method('__toString')->willReturn(json_encode($data));
        return $bodyStream;
    }

    /**
     * Set up the test environment before each test
     *
     * Initializes all mock objects and the AuthController instance
     *
     * @return void
     */
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

    /**
     * Test successful user registration
     *
     * Tests that a user can be successfully registered with valid data
     * and that the correct response is returned.
     *
     * @return void
     */
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

        $bodyStream = $this->createJsonBodyStream($userData);

        $this->request
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($bodyStream);

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->with($userData)
            ->willReturn($user);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === true &&
                           $data['message'] === 'User registered successfully. ' .
                                            'Please check your email to verify your account.' &&
                           isset($data['data']) &&
                           is_array($data['data']) &&
                           $data['data']['email'] === 'test@example.com' &&
                           $data['data']['firstName'] === 'John' &&
                           $data['data']['lastName'] === 'Doe' &&
                           !isset($data['data']['password_hash']);
                    }
                )
            );

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

        /**
         * Test user registration with existing email
         *
         * Tests that registration fails when email already exists
         * and returns appropriate error response.
         *
         * @return void
         */
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
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream($userData));

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->with($userData)
            ->willThrowException(new UserAlreadyExistsException('existing@example.com'));

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'User already exists' &&
                           $data['message'] === "User with email 'existing@example.com' already exists" &&
                           $data['code'] === 409;
                    }
                )
            );

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * Test user registration with invalid data
     *
     * Tests that registration fails when provided with invalid data
     * and returns appropriate validation error response.
     *
     * @return void
     */
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
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream($userData));

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->with($userData)
            ->willThrowException(new \InvalidArgumentException('Invalid email format'));

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'Validation error' &&
                           $data['message'] === 'Invalid email format' &&
                           $data['code'] === 400;
                    }
                )
            );

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * Test successful email verification
     *
     * Tests that email verification works correctly with valid token
     * and returns appropriate success response.
     *
     * @return void
     */
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

        $this->userService
            ->expects($this->once())
            ->method('verifyEmail')
            ->with($token)
            ->willReturn($user);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === true &&
                           $data['message'] === 'Email verified successfully. Your account is now active.' &&
                           isset($data['data']) &&
                           is_array($data['data']) &&
                           $data['data']['isEmailVerified'] === true;
                    }
                )
            );

        $response = $this->authController->verifyEmail($this->request, $token);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testVerifyEmailInvalidToken(): void
    {
        $token = 'invalid-token';

        $this->userService
            ->expects($this->once())
            ->method('verifyEmail')
            ->with($token)
            ->willThrowException(new EmailVerificationException('Invalid or expired verification token'));

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'Verification error' &&
                           $data['message'] === 'Invalid or expired verification token' &&
                           $data['code'] === 400;
                    }
                )
            );

        $response = $this->authController->verifyEmail($this->request, $token);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testResendVerificationSuccess(): void
    {
        $email = 'test@example.com';

        $this->request
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream(['email' => $email]));

        $this->userService
            ->expects($this->once())
            ->method('resendVerificationEmail')
            ->with($email);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === true &&
                           $data['message'] === 'Verification email sent successfully. Please check your email.';
                    }
                )
            );

        $response = $this->authController->resendVerification($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testResendVerificationRateLimit(): void
    {
        $email = 'test@example.com';

        $this->request
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream(['email' => $email]));

        $this->userService
            ->expects($this->once())
            ->method('resendVerificationEmail')
            ->with($email)
            ->willThrowException(
                new EmailVerificationException(
                    'Verification email already sent recently. Please wait before requesting another.'
                )
            );

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'Verification error' &&
                           $data['message'] === 'Verification email already sent recently. ' .
                                            'Please wait before requesting another.' &&
                           $data['code'] === 400;
                    }
                )
            );

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
            ->method('getUserById')
            ->with($userId)
            ->willReturn($user);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === true &&
                           isset($data['data']) &&
                           $data['data']['email'] === 'test@example.com' &&
                           !isset($data['data']['password_hash']);
                    }
                )
            );

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
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream($updateData));

        $this->userService
            ->expects($this->once())
            ->method('updateProfile')
            ->with($userId, $updateData)
            ->willReturn($updatedUser);

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === true &&
                           $data['message'] === 'Profile updated successfully' &&
                           isset($data['data']) &&
                           $data['data']['firstName'] === 'Jane' &&
                           $data['data']['lastName'] === 'Smith';
                    }
                )
            );

        $response = $this->authController->updateProfile($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testChangePasswordSuccess(): void
    {
        $userId = 'user-id';
        $passwordData = [
            'currentPassword' => 'OldPass123!',
            'newPassword' => 'NewPass123!'
        ];

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $this->request
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream($passwordData));

        $this->userService
            ->expects($this->once())
            ->method('changePassword')
            ->with($userId, 'OldPass123!', 'NewPass123!');

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === true &&
                           $data['message'] === 'Password changed successfully';
                    }
                )
            );

        $response = $this->authController->changePassword($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testChangePasswordIncorrectCurrent(): void
    {
        $userId = 'user-id';
        $passwordData = [
            'currentPassword' => 'WrongPass123!',
            'newPassword' => 'NewPass123!'
        ];

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('user_id')
            ->willReturn($userId);

        $this->request
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream($passwordData));

        $this->userService
            ->expects($this->once())
            ->method('changePassword')
            ->with($userId, 'WrongPass123!', 'NewPass123!')
            ->willThrowException(new UserException('Current password is incorrect'));

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'Internal server error' &&
                           $data['message'] === 'An unexpected error occurred' &&
                           $data['code'] === 500;
                    }
                )
            );

        $response = $this->authController->changePassword($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMissingRequiredFields(): void
    {
        // Test register with missing data
        $this->request
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream(['email' => 'test@example.com'])); // Missing other fields

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new UserException('Required fields missing: password, first_name, last_name'));

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'Internal server error' &&
                           $data['message'] === 'An unexpected error occurred' &&
                           $data['code'] === 500;
                    }
                )
            );

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
            ->method('getBody')
            ->willReturn($this->createJsonBodyStream($userData));

        $this->userService
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->stream
            ->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(
                    function ($content) {
                        $data = $this->decodeJson($content);
                        return $data['success'] === false &&
                           $data['error'] === 'Internal server error' &&
                           $data['message'] === 'An unexpected error occurred' &&
                           $data['code'] === 500;
                    }
                )
            );

        $response = $this->authController->register($this->request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}
