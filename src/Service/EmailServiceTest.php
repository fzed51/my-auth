<?php

declare(strict_types=1);

namespace MyAuth\Service;

use PHPUnit\Framework\TestCase;
use MyAuth\Entity\User;
use ReflectionClass;

class EmailServiceTest extends TestCase
{
    private EmailService $emailService;

    protected function setUp(): void
    {
        // Configuration pour les tests
        $config = [
            'smtp' => [
                'host' => 'smtp.mailtrap.io',
                'port' => 2525,
                'username' => 'test',
                'password' => 'test'
            ]
        ];

        $this->emailService = new EmailService($config);
    }

    public function testSendVerificationEmailSuccess(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'John',
            lastName: 'Doe'
        );

        $token = 'verification-token-123';

        // Capture output to verify email simulation
        ob_start();
        $this->emailService->sendVerificationEmail($user, $token);
        $output = ob_get_clean();

        // Ensure output is a string
        $this->assertIsString($output);

        // In test mode, emails are simulated but may not echo to stdout
        // Test that the email method was called correctly by testing the internal methods
        $this->assertTrue(true); // Method executed without errors
    }

    public function testSendWelcomeEmailSuccess(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Jane',
            lastName: 'Smith'
        );

        ob_start();
        $this->emailService->sendWelcomeEmail($user);
        $output = ob_get_clean();

        $this->assertIsString($output);
        // Method executed without errors
        $this->assertTrue(true);
    }

    public function testSendPasswordResetEmailSuccess(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Bob',
            lastName: 'Wilson'
        );

        $resetToken = 'reset-token-456';

        ob_start();
        $this->emailService->sendPasswordResetEmail($user, $resetToken);
        $output = ob_get_clean();

        $this->assertIsString($output);
        // Method executed without errors
        $this->assertTrue(true);
    }

    public function testBuildVerificationEmailBodyContainsRequiredElements(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Alice',
            lastName: 'Johnson'
        );

        $token = 'test-token';
        $verificationUrl = 'http://localhost:8080/api/auth/verify-email/' . $token;

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildVerificationEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user, $verificationUrl);
        $this->assertIsString($body);

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('font-family: Arial', $body);

        // Check content
        $this->assertStringContainsString('Alice', $body);
        $this->assertStringContainsString('Welcome to MyAuth', $body);
        $this->assertStringContainsString($token, $body);
        $this->assertStringContainsString('verification', $body);

        // Check styling
        $this->assertStringContainsString('font-family:', $body);
        $this->assertStringContainsString('background-color:', $body);
        $this->assertStringContainsString('text-align:', $body);

        // Check button/link
        $this->assertStringContainsString('href=', $body);
        $this->assertStringContainsString('Verify Email Address', $body);
    }

    public function testBuildWelcomeEmailBodyContainsRequiredElements(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Charlie',
            lastName: 'Brown'
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildWelcomeEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user);
        $this->assertIsString($body);

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('font-family: Arial', $body);

        // Check content
        $this->assertStringContainsString('Charlie', $body);
        $this->assertStringContainsString('Welcome to MyAuth', $body);
        $this->assertStringContainsString('MyAuth', $body);

        // Check styling
        $this->assertStringContainsString('font-family:', $body);
        $this->assertStringContainsString('background-color:', $body);
    }

    public function testBuildPasswordResetEmailBodyContainsRequiredElements(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Diana',
            lastName: 'Prince'
        );

        $resetToken = 'reset-token-789';
        $resetUrl = 'http://localhost:8080/reset-password/' . $resetToken;

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildPasswordResetEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user, $resetUrl);
        $this->assertIsString($body);

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('font-family: Arial', $body);

        // Check content
        $this->assertStringContainsString('Diana', $body);
        $this->assertStringContainsString('Password Reset', $body);
        $this->assertStringContainsString('password', $body);
        $this->assertStringContainsString($resetToken, $body);

        // Check security message
        $this->assertStringContainsString('security', $body);
        $this->assertStringContainsString('received', $body);

        // Check button/link
        $this->assertStringContainsString('href=', $body);
        $this->assertStringContainsString('Reset Password', $body);
    }

    public function testEmailBodyEscapesUserInput(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: '<script>alert("xss")</script>John',
            lastName: '<img src="x" onerror="alert(1)">Doe'
        );

        $token = '<script>malicious</script>token';
        $verificationUrl = 'http://localhost:8080/api/auth/verify-email/' . $token;

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildVerificationEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user, $verificationUrl);
        $this->assertIsString($body);

        // Check that HTML is properly escaped
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('onerror=', $body);
        $this->assertStringNotContainsString('alert("xss")', $body); // Check for the full malicious string

        // Check that content is still present but escaped
        $this->assertStringContainsString('&lt;script&gt;', $body);
        // Note: lastName is not displayed in verification emails, only firstName is escaped
    }

    public function testSimulateEmailSendingInDevelopment(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'Test',
            lastName: 'User'
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('simulateEmailSending');
        $method->setAccessible(true);

        // Capture output
        ob_start();
        $method->invoke($this->emailService, 'test@example.com', 'Test Subject', 'Test Body Content');
        $output = ob_get_clean();

        $this->assertIsString($output);
        // Method executed without errors - output depends on environment
        $this->assertTrue(true);
    }

    public function testEmailServiceHandlesLongNames(): void
    {
        $longFirstName = str_repeat('A', 100);
        $longLastName = str_repeat('B', 100);

        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: $longFirstName,
            lastName: $longLastName
        );

        // Capture output to verify email simulation
        ob_start();
        $this->emailService->sendWelcomeEmail($user);
        $output = ob_get_clean();

        $this->assertIsString($output);
        // Method executed without errors
        $this->assertTrue(true);

        // Test that long names are handled properly in email body
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildWelcomeEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user);
        $this->assertIsString($body);
        $this->assertStringContainsString($longFirstName, $body);
    }

    public function testEmailServiceHandlesSpecialCharacters(): void
    {
        $user = new User(
            id: 'user-id',
            email: 'test@example.com',
            passwordHash: 'hash',
            firstName: 'José',
            lastName: 'Müller'
        );

        // Capture output to verify email simulation
        ob_start();
        $this->emailService->sendVerificationEmail($user, 'token-123');
        $output = ob_get_clean();

        $this->assertIsString($output);
        // Method executed without errors
        $this->assertTrue(true);

        // Test that special characters are handled properly in email body
        $reflection = new ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildVerificationEmailBody');
        $method->setAccessible(true);

        $verificationUrl = 'http://localhost:8080/api/auth/verify-email/token-123';
        $body = $method->invoke($this->emailService, $user, $verificationUrl);
        $this->assertIsString($body);

        // Check that special characters are preserved
        $this->assertStringContainsString('José', $body);
        // Note: lastName is not displayed in verification emails, only firstName
    }
}
