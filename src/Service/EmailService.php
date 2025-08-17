<?php

/**
 * Service Class for MyAuth
 *
 * @package MyAuth\Service
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Entity\User;

class EmailService extends AbstractService
{
    private array $config;

    /**


     * Constructor


     */


    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'smtp_host' => $_ENV['SMTP_HOST'] ?? 'localhost',
            'smtp_port' => $_ENV['SMTP_PORT'] ?? 1025,
            'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
            'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@example.com',
            'from_name' => $_ENV['FROM_NAME'] ?? 'MyAuth',
            'app_url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        ], $config);
    }

    public function sendVerificationEmail(User $user, string $token): void
    {
        $verificationUrl = $this->config['app_url'] . '/api/auth/verify-email/' . $token;

        $subject = 'Verify your email address';
        $body = $this->buildVerificationEmailBody($user, $verificationUrl);

        $this->sendEmail(
            to: $user->getEmail(),
            subject: $subject,
            body: $body,
            isHtml: true
        );

        $this->log('info', 'Verification email sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'token' => substr($token, 0, 8) . '...' // Log seulement le début du token
        ]);
    }

    public function sendWelcomeEmail(User $user): void
    {
        $subject = 'Welcome to MyAuth!';
        $body = $this->buildWelcomeEmailBody($user);

        $this->sendEmail(
            to: $user->getEmail(),
            subject: $subject,
            body: $body,
            isHtml: true
        );

        $this->log('info', 'Welcome email sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->config['app_url'] . '/reset-password/' . $token;

        $subject = 'Reset your password';
        $body = $this->buildPasswordResetEmailBody($user, $resetUrl);

        $this->sendEmail(
            to: $user->getEmail(),
            subject: $subject,
            body: $body,
            isHtml: true
        );

        $this->log('info', 'Password reset email sent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }

    private function sendEmail(string $to, string $subject, string $body, bool $isHtml = false): void
    {
        // En développement, on simule l'envoi d'email
        if ($_ENV['APP_ENV'] === 'development') {
            $this->simulateEmailSending($to, $subject, $body);
            return;
        }

        // En production, utiliser une vraie implémentation SMTP
        // Pour l'instant, on simule aussi
        $this->simulateEmailSending($to, $subject, $body);
    }

    private function simulateEmailSending(string $to, string $subject, string $body): void
    {
        $logMessage = sprintf(
            "EMAIL SENT:\nTo: %s\nSubject: %s\nBody:\n%s\n%s\n",
            $to,
            $subject,
            $body,
            str_repeat('=', 50)
        );

        // Log dans le fichier et affichage console en développement
        error_log($logMessage);

        if ($_ENV['APP_ENV'] === 'development') {
            echo $logMessage . "\n";
        }
    }

    private function buildVerificationEmailBody(User $user, string $verificationUrl): string
    {
        return sprintf(
            '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Verify your email</title>
            </head>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h1>Welcome to MyAuth, %s!</h1>
                
                <p>Thank you for registering with MyAuth. To complete your registration, 
                please verify your email address by clicking the button below:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%s" style="background-color: #007bff; color: white; 
                    padding: 12px 24px; text-decoration: none; border-radius: 4px; 
                    display: inline-block;">
                        Verify Email Address
                    </a>
                </div>
                
                <p>If the button doesn\'t work, you can copy and paste this link into your browser:</p>
                <p><a href="%s">%s</a></p>
                
                <p>This verification link will expire in 24 hours.</p>
                
                <hr style="margin: 30px 0;">
                <p style="color: #666; font-size: 12px;">
                    If you didn\'t create an account with MyAuth, please ignore this email.
                </p>
            </body>
            </html>
            ',
            htmlspecialchars($user->getFirstName()),
            htmlspecialchars($verificationUrl),
            htmlspecialchars($verificationUrl),
            htmlspecialchars($verificationUrl)
        );
    }

    private function buildWelcomeEmailBody(User $user): string
    {
        return sprintf(
            '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Welcome to MyAuth!</title>
            </head>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h1>Welcome to MyAuth, %s!</h1>
                
                <p>Your email has been successfully verified and your account is now active.</p>
                
                <p>You can now:</p>
                <ul>
                    <li>Sign in to your account</li>
                    <li>Update your profile</li>
                    <li>Access all our features</li>
                </ul>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%s/login" style="background-color: #28a745; color: white; 
                    padding: 12px 24px; text-decoration: none; border-radius: 4px; 
                    display: inline-block;">
                        Sign In Now
                    </a>
                </div>
                
                <p>If you have any questions, feel free to contact our support team.</p>
                
                <p>Welcome aboard!</p>
                <p>The MyAuth Team</p>
            </body>
            </html>
            ',
            htmlspecialchars($user->getFirstName()),
            htmlspecialchars($this->config['app_url'])
        );
    }

    private function buildPasswordResetEmailBody(User $user, string $resetUrl): string
    {
        return sprintf(
            '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Reset your password</title>
            </head>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h1>Password Reset Request</h1>
                
                <p>Hello %s,</p>
                
                <p>We received a request to reset your password for your MyAuth account.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="%s" style="background-color: #dc3545; color: white; 
                    padding: 12px 24px; text-decoration: none; border-radius: 4px; 
                    display: inline-block;">
                        Reset Password
                    </a>
                </div>
                
                <p>If the button doesn\'t work, you can copy and paste this link 
                into your browser:</p>
                <p><a href="%s">%s</a></p>
                
                <p>This reset link will expire in 1 hour for security reasons.</p>
                
                <hr style="margin: 30px 0;">
                <p style="color: #666; font-size: 12px;">
                    If you didn\'t request a password reset, please ignore this email. 
                    Your password will remain unchanged.
                </p>
            </body>
            </html>
            ',
            htmlspecialchars($user->getFirstName()),
            htmlspecialchars($resetUrl),
            htmlspecialchars($resetUrl),
            htmlspecialchars($resetUrl)
        );
    }
}
