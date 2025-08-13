<?php

declare(strict_types=1);

namespace MyAuth\Service;

use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;
use Exception;

class EmailService
{
    private Swift_Mailer $mailer;
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'My Auth Service';

        $this->initializeMailer();
    }

    /**
     * Initialise le mailer Swift
     */
    private function initializeMailer(): void
    {
        $transport = (new Swift_SmtpTransport(
            $_ENV['MAIL_HOST'] ?? 'localhost',
            (int)($_ENV['MAIL_PORT'] ?? 587),
            $_ENV['MAIL_ENCRYPTION'] ?? 'tls'
        ))
        ->setUsername($_ENV['MAIL_USERNAME'] ?? '')
        ->setPassword($_ENV['MAIL_PASSWORD'] ?? '');

        $this->mailer = new Swift_Mailer($transport);
    }

    /**
     * Envoie un email de vérification
     */
    public function sendEmailVerification(string $email, string $token): bool
    {
        $verificationUrl = $this->buildVerificationUrl($token);

        $subject = 'Vérification de votre adresse email';
        $body = $this->getEmailVerificationTemplate($verificationUrl);

        return $this->sendEmail($email, $subject, $body);
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordReset(string $email, string $token): bool
    {
        $resetUrl = $this->buildPasswordResetUrl($token);

        $subject = 'Réinitialisation de votre mot de passe';
        $body = $this->getPasswordResetTemplate($resetUrl);

        return $this->sendEmail($email, $subject, $body);
    }

    /**
     * Envoie un email de bienvenue
     */
    public function sendWelcomeEmail(string $email, string $firstName = ''): bool
    {
        $subject = 'Bienvenue !';
        $body = $this->getWelcomeTemplate($firstName);

        return $this->sendEmail($email, $subject, $body);
    }

    /**
     * Envoie un email de notification de connexion suspecte
     */
    public function sendSuspiciousLoginAlert(string $email, string $ipAddress, string $userAgent): bool
    {
        $subject = 'Connexion suspecte détectée';
        $body = $this->getSuspiciousLoginTemplate($ipAddress, $userAgent);

        return $this->sendEmail($email, $subject, $body);
    }

    /**
     * Méthode générique pour envoyer un email
     */
    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $message = (new Swift_Message($subject))
                ->setFrom([$this->fromAddress => $this->fromName])
                ->setTo([$to])
                ->setBody($body, 'text/html');

            $result = $this->mailer->send($message);
            return $result > 0;
        } catch (Exception $e) {
            // Log l'erreur (dans un vrai projet, utiliser un logger)
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Construit l'URL de vérification d'email
     */
    private function buildVerificationUrl(string $token): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        return $baseUrl . '/api/auth/verify-email/' . $token;
    }

    /**
     * Construit l'URL de réinitialisation de mot de passe
     */
    private function buildPasswordResetUrl(string $token): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        return $baseUrl . '/reset-password?token=' . $token;
    }

    /**
     * Template pour l'email de vérification
     */
    private function getEmailVerificationTemplate(string $verificationUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Vérification d'email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Vérification de votre adresse email</h2>
                <p>Merci de vous être inscrit ! Pour activer votre compte, veuillez cliquer sur le lien ci-dessous :</p>
                <p><a href='{$verificationUrl}' class='button'>Vérifier mon email</a></p>
                <p>Ou copiez et collez ce lien dans votre navigateur :</p>
                <p><a href='{$verificationUrl}'>{$verificationUrl}</a></p>
                <p>Ce lien expire dans 24 heures.</p>
                <div class='footer'>
                    <p>Si vous n'avez pas créé de compte, vous pouvez ignorer cet email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template pour l'email de réinitialisation de mot de passe
     */
    private function getPasswordResetTemplate(string $resetUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Réinitialisation de mot de passe</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Réinitialisation de votre mot de passe</h2>
                <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le lien ci-dessous pour procéder :</p>
                <p><a href='{$resetUrl}' class='button'>Réinitialiser mon mot de passe</a></p>
                <p>Ou copiez et collez ce lien dans votre navigateur :</p>
                <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                <p>Ce lien expire dans 1 heure.</p>
                <div class='footer'>
                    <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template pour l'email de bienvenue
     */
    private function getWelcomeTemplate(string $firstName): string
    {
        $greeting = $firstName ? "Bonjour {$firstName}" : "Bonjour";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Bienvenue</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>{$greeting} !</h2>
                <p>Votre compte a été créé avec succès et votre email a été vérifié.</p>
                <p>Vous pouvez maintenant vous connecter et utiliser tous nos services.</p>
                <p>Merci de nous faire confiance !</p>
                <div class='footer'>
                    <p>L'équipe My Auth Service</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template pour l'alerte de connexion suspecte
     */
    private function getSuspiciousLoginTemplate(string $ipAddress, string $userAgent): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Connexion suspecte</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert { padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Connexion suspecte détectée</h2>
                <div class='alert'>
                    <p><strong>Attention :</strong> Une connexion à votre compte a été détectée depuis une nouvelle localisation ou appareil.</p>
                </div>
                <p><strong>Détails de la connexion :</strong></p>
                <ul>
                    <li>Adresse IP : {$ipAddress}</li>
                    <li>Navigateur : {$userAgent}</li>
                    <li>Date : " . date('d/m/Y H:i:s') . "</li>
                </ul>
                <p>Si cette connexion était bien la vôtre, vous pouvez ignorer cet email.</p>
                <p>Si ce n'était pas vous, nous vous recommandons de changer immédiatement votre mot de passe.</p>
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement pour votre sécurité.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Teste la configuration email
     */
    public function testConfiguration(): bool
    {
        try {
            // Teste la connexion au serveur SMTP
            $transport = $this->mailer->getTransport();
            $transport->start();
            $transport->stop();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
