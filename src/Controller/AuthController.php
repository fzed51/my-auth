<?php

/**
 * AuthController Class
 *
 * This file contains the AuthController class that handles all
 * authentication-related HTTP requests including user registration,
 * email verification, profile management, and password changes.
 *
 * @category Controllers
 * @package  MyAuth\Controller
 * @author   Fabien Sanchez <fabien.sanchez@example.com>
 * @license  MIT License
 * @link     https://github.com/fabien/my-auth
 */

declare(strict_types=1);

namespace MyAuth\Controller;

use MyAuth\Service\UserService;
use MyAuth\Exception\UserAlreadyExistsException;
use MyAuth\Exception\UserNotFoundException;
use MyAuth\Exception\EmailVerificationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use InvalidArgumentException;
use Exception;
use Throwable;

/**
 * Class AuthController
 *
 * Handles all authentication-related HTTP requests including user registration,
 * email verification, profile management, and password changes.
 *
 * @category Controllers
 * @package  MyAuth\Controller
 * @author   Fabien Sanchez <fabien.sanchez@example.com>
 * @license  MIT License
 * @link     https://github.com/fabien/my-auth
 */
class AuthController
{
    /**
     * User service instance
     *
     * @var UserService
     */
    private UserService $_userService;

    /**
     * HTTP response factory instance
     *
     * @var ResponseFactoryInterface
     */
    private ResponseFactoryInterface $_responseFactory;

    /**
     * AuthController constructor
     *
     * @param UserService              $userService     The user service instance
     * @param ResponseFactoryInterface $responseFactory The response factory instance
     */
    public function __construct(
        UserService $userService,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->_userService = $userService;
        $this->_responseFactory = $responseFactory;
    }

    /**
     * Handle user registration
     *
     * Processes user registration requests, validates input data,
     * and creates a new user account.
     *
     * @param ServerRequestInterface $request The HTTP request object
     *
     * @return ResponseInterface The HTTP response object
     */
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = $this->_getJsonBody($request);

            $user = $this->_userService->register($data);

            $response = $this->_responseFactory->createResponse(201);
            $response->getBody()->write(
                json_encode(
                    [
                    'success' => true,
                    'message' => 'User registered successfully. Please check your email to verify your account.',
                    'data' => $user->toPublicArray()
                    ], JSON_THROW_ON_ERROR
                )
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (UserAlreadyExistsException $e) {
            return $this->_createErrorResponse(409, 'User already exists', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->_createErrorResponse(400, 'Validation error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            return $this->_createErrorResponse(500, 'Internal server error', 'An unexpected error occurred');
        }
    }

    /**
     * Handle email verification
     *
     * Verifies a user's email address using the provided token.
     *
     * @param ServerRequestInterface $request The HTTP request object
     * @param string                 $token   The verification token
     *
     * @return ResponseInterface The HTTP response object
     */
    public function verifyEmail(ServerRequestInterface $request, string $token): ResponseInterface
    {
        try {
            if (empty($token)) {
                return $this->_createErrorResponse(400, 'Invalid request', 'Token is required');
            }

            $user = $this->_userService->verifyEmail($token);

            $response = $this->_responseFactory->createResponse(200);
            $response->getBody()->write(
                json_encode(
                    [
                    'success' => true,
                    'message' => 'Email verified successfully. Your account is now active.',
                    'data' => $user->toPublicArray()
                    ], JSON_THROW_ON_ERROR
                )
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (EmailVerificationException $e) {
            $statusCode = $e->getCode() ?: 400;
            return $this->_createErrorResponse($statusCode, 'Verification error', $e->getMessage());
        } catch (UserNotFoundException $e) {
            return $this->_createErrorResponse(404, 'User not found', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Email verification error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            // En mode développement, retourner l'erreur détaillée
            if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
                return $this->_createErrorResponse(
                    500,
                    'Internal server error',
                    'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                );
            }

            return $this->_createErrorResponse(500, 'Internal server error', 'An unexpected error occurred');
        }
    }

    public function resendVerification(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = $this->_getJsonBody($request);

            if (empty($data['email'])) {
                return $this->_createErrorResponse(400, 'Validation error', 'Email is required');
            }

            $this->_userService->resendVerificationEmail($data['email']);

            $response = $this->_responseFactory->createResponse(200);
            $response->getBody()->write(
                json_encode(
                    [
                    'success' => true,
                    'message' => 'Verification email sent successfully. Please check your email.'
                    ], JSON_THROW_ON_ERROR
                )
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (UserNotFoundException $e) {
            return $this->_createErrorResponse(404, 'User not found', $e->getMessage());
        } catch (EmailVerificationException $e) {
            $statusCode = $e->getCode() ?: 400;
            return $this->_createErrorResponse($statusCode, 'Verification error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Resend verification error: ' . $e->getMessage());
            return $this->_createErrorResponse(500, 'Internal server error', 'An unexpected error occurred');
        }
    }

    public function getProfile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('user_id');

            if (empty($userId)) {
                return $this->_createErrorResponse(401, 'Unauthorized', 'User ID not found in request');
            }

            $user = $this->_userService->getUserById($userId);

            $response = $this->_responseFactory->createResponse(200);
            $response->getBody()->write(
                json_encode(
                    [
                    'success' => true,
                    'data' => $user->toPublicArray()
                    ], JSON_THROW_ON_ERROR
                )
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (UserNotFoundException $e) {
            return $this->_createErrorResponse(404, 'User not found', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Get profile error: ' . $e->getMessage());
            return $this->_createErrorResponse(500, 'Internal server error', 'An unexpected error occurred');
        }
    }

    public function updateProfile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('user_id');
            $data = $this->_getJsonBody($request);

            if (empty($userId)) {
                return $this->_createErrorResponse(401, 'Unauthorized', 'User ID not found in request');
            }

            $user = $this->_userService->updateProfile($userId, $data);

            $response = $this->_responseFactory->createResponse(200);
            $response->getBody()->write(
                json_encode(
                    [
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => $user->toPublicArray()
                    ], JSON_THROW_ON_ERROR
                )
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (UserNotFoundException $e) {
            return $this->_createErrorResponse(404, 'User not found', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->_createErrorResponse(400, 'Validation error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Update profile error: ' . $e->getMessage());
            return $this->_createErrorResponse(500, 'Internal server error', 'An unexpected error occurred');
        }
    }

    public function changePassword(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('user_id');
            $data = $this->_getJsonBody($request);

            if (empty($userId)) {
                return $this->_createErrorResponse(401, 'Unauthorized', 'User ID not found in request');
            }

            $requiredFields = ['currentPassword', 'newPassword'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return $this->_createErrorResponse(400, 'Validation error', "Field '{$field}' is required");
                }
            }

            $this->_userService->changePassword($userId, $data['currentPassword'], $data['newPassword']);

            $response = $this->_responseFactory->createResponse(200);
            $response->getBody()->write(
                json_encode(
                    [
                    'success' => true,
                    'message' => 'Password changed successfully'
                    ], JSON_THROW_ON_ERROR
                )
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (UserNotFoundException $e) {
            return $this->_createErrorResponse(404, 'User not found', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return $this->_createErrorResponse(400, 'Validation error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Change password error: ' . $e->getMessage());
            return $this->_createErrorResponse(500, 'Internal server error', 'An unexpected error occurred');
        }
    }

    private function _getJsonBody(ServerRequestInterface $request): array
    {
        $body = (string) $request->getBody();

        if (empty($body)) {
            throw new InvalidArgumentException('Request body is required');
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON in request body');
        }

        return $data ?? [];
    }

    private function _createErrorResponse(int $statusCode, string $error, string $message): ResponseInterface
    {
        $response = $this->_responseFactory->createResponse($statusCode);
        $response->getBody()->write(
            json_encode(
                [
                'success' => false,
                'error' => $error,
                'message' => $message,
                'code' => $statusCode
                ], JSON_THROW_ON_ERROR
            )
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}
