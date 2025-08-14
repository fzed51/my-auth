<?php

declare(strict_types=1);

namespace MyAuth\Service;

use PHPUnit\Framework\TestCase;
use MyAuth\Entity\User;
use Swift_Mailer;
use Swift_MemorySpool;
use Swift_SpoolTransport;
use Swift_Transport_SpoolTransport;
use DateTime;

class EmailServiceTest extends TestCase
{
    private EmailService $emailService;
    private Swift_MemorySpool $spool;

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

        // In test mode, emails are simulated
        $this->assertStringContainsString('📧 Email simulated', $output);
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('test@example.com', $output);
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

        $this->assertStringContainsString('📧 Email simulated', $output);
        $this->assertStringContainsString('Jane Smith', $output);
        $this->assertStringContainsString('test@example.com', $output);
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

        $this->assertStringContainsString('📧 Email simulated', $output);
        $this->assertStringContainsString('Bob Wilson', $output);
        $this->assertStringContainsString('test@example.com', $output);
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

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildVerificationEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user, $token);

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('<body>', $body);

        // Check content
        $this->assertStringContainsString('Alice', $body);
        $this->assertStringContainsString('Vérifiez votre adresse email', $body);
        $this->assertStringContainsString($token, $body);
        $this->assertStringContainsString('verification', $body);

        // Check styling
        $this->assertStringContainsString('font-family:', $body);
        $this->assertStringContainsString('background-color:', $body);
        $this->assertStringContainsString('text-align:', $body);

        // Check button/link
        $this->assertStringContainsString('href=', $body);
        $this->assertStringContainsString('button', $body);
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
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildWelcomeEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user);

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('<body>', $body);

        // Check content
        $this->assertStringContainsString('Charlie', $body);
        $this->assertStringContainsString('Bienvenue', $body);
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

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildPasswordResetEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user, $resetToken);

        // Check HTML structure
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('<body>', $body);

        // Check content
        $this->assertStringContainsString('Diana', $body);
        $this->assertStringContainsString('Réinitialisation', $body);
        $this->assertStringContainsString('mot de passe', $body);
        $this->assertStringContainsString($resetToken, $body);

        // Check security message
        $this->assertStringContainsString('sécurité', $body);
        $this->assertStringContainsString('demandé', $body);

        // Check button/link
        $this->assertStringContainsString('href=', $body);
        $this->assertStringContainsString('button', $body);
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

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('buildVerificationEmailBody');
        $method->setAccessible(true);

        $body = $method->invoke($this->emailService, $user, $token);

        // Check that HTML is properly escaped
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('onerror=', $body);
        $this->assertStringNotContainsString('alert(', $body);

        // Check that content is still present but escaped
        $this->assertStringContainsString('&lt;script&gt;', $body);
        $this->assertStringContainsString('&lt;img', $body);
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
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('simulateEmailSending');
        $method->setAccessible(true);

        // Capture output
        ob_start();
        $method->invoke($this->emailService, 'Test Subject', 'test@example.com', 'Test User');
        $output = ob_get_clean();

        $this->assertStringContainsString('📧 Email simulated', $output);
        $this->assertStringContainsString('Test Subject', $output);
        $this->assertStringContainsString('test@example.com', $output);
        $this->assertStringContainsString('Test User', $output);
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

        $this->emailService->sendWelcomeEmail($user);

        $messages = $this->spool->getMessages();
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $to = $message->getTo();
        $this->assertArrayHasKey('test@example.com', $to);

        // Check that the message body contains the long names
        $body = $message->getBody();
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

        $this->emailService->sendVerificationEmail($user, 'token-123');

        $messages = $this->spool->getMessages();
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $body = $message->getBody();

        // Check that special characters are preserved
        $this->assertStringContainsString('José', $body);
        $this->assertStringContainsString('Müller', $body);
    }
}
