<?php

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Repository\UserRepository;
use MyAuth\Repository\LoginAttemptRepository;
use MyAuth\Service\JwtService;
use MyAuth\Exception\AuthException;
use MyAuth\Exception\ValidationException;
use DateTime;

class AuthService
{
    private UserRepository $userRepository;
    private JwtService $jwtService;
    private LoginAttemptRepository $loginAttemptRepository;

    public function __construct(
        UserRepository $userRepository,
        JwtService $jwtService,
        LoginAttemptRepository $loginAttemptRepository
    ) {
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService;
        $this->loginAttemptRepository = $loginAttemptRepository;
    }

    /**
     * Authentifie un utilisateur et génère un token JWT
     */
    public function login(string $email, string $password, string $ipAddress, ?string $userAgent = null): array
    {
        // Valider les données d'entrée
        $this->validateLoginData($email, $password);

        // Vérifier les tentatives de brute force
        $this->checkBruteForceProtection($email, $ipAddress);

        try {
            // Rechercher l'utilisateur
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                $this->recordLoginAttempt($email, $ipAddress, false, $userAgent);
                throw new AuthException('Identifiants invalides');
            }

            // Vérifier le mot de passe
            if (!$user->verifyPassword($password)) {
                $this->recordLoginAttempt($email, $ipAddress, false, $userAgent);
                throw new AuthException('Identifiants invalides');
            }

            // Vérifier que le compte est actif
            if (!$user->isActive()) {
                $this->recordLoginAttempt($email, $ipAddress, false, $userAgent);
                throw new AuthException('Compte désactivé');
            }

            // Vérifier que l'email est vérifié
            if (!$user->isEmailVerified()) {
                $this->recordLoginAttempt($email, $ipAddress, false, $userAgent);
                throw new AuthException('Email non vérifié. Veuillez vérifier votre boîte email.');
            }

            // Générer le token JWT
            $additionalClaims = [
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'isEmailVerified' => $user->isEmailVerified(),
                'loginTime' => (new DateTime())->getTimestamp()
            ];

            $token = $this->jwtService->generateToken($user->getId(), $additionalClaims);

            // Générer un refresh token
            $refreshToken = $this->jwtService->generateRefreshToken(
                $user->getId(),
                $ipAddress,
                $userAgent
            );

            // Mettre à jour la date de dernière connexion
            $this->userRepository->updateLastLogin($user->getId());

            // Enregistrer la tentative réussie
            $this->recordLoginAttempt($email, $ipAddress, true, $userAgent);

            return [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'user' => $user->toArray(),
                'expiresIn' => $this->jwtService->getConfig()['expiration']
            ];
        } catch (AuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->recordLoginAttempt($email, $ipAddress, false, $userAgent);
            throw new AuthException('Erreur lors de l\'authentification');
        }
    }

    /**
     * Déconnecte un utilisateur en révoquant son token
     */
    public function logout(string $token): bool
    {
        try {
            return $this->jwtService->revokeToken($token);
        } catch (\Exception $e) {
            // Token déjà invalide ou expiré
            return true;
        }
    }

    /**
     * Déconnecte un utilisateur de tous ses appareils
     */
    public function logoutAllDevices(int $userId): bool
    {
        return $this->jwtService->revokeAllUserTokens($userId);
    }

    /**
     * Valide un token JWT et retourne les informations utilisateur
     */
    public function validateToken(string $token): array
    {
        try {
            $payload = $this->jwtService->validateToken($token);

            // Vérifier que l'utilisateur existe toujours et est actif
            $user = $this->userRepository->findById((int)$payload['user_id']);

            if (!$user || !$user->isActive()) {
                throw new AuthException('Utilisateur introuvable ou inactif');
            }

            return [
                'payload' => $payload,
                'user' => $user->toArray()
            ];
        } catch (\Exception $e) {
            throw new AuthException('Token invalide');
        }
    }

    /**
     * Vérifie si un token expire bientôt
     */
    public function isTokenExpiringSoon(string $token, int $thresholdMinutes = 5): bool
    {
        return $this->jwtService->isTokenExpiringSoon($token, $thresholdMinutes);
    }

    /**
     * Obtient les statistiques de connexion
     */
    public function getLoginStats(int $hours = 24): array
    {
        return $this->loginAttemptRepository->getLoginStats($hours);
    }

    /**
     * Obtient l'historique des connexions pour un utilisateur
     */
    public function getUserLoginHistory(string $email, int $hours = 24): array
    {
        return $this->loginAttemptRepository->findAttemptsByEmail($email, $hours);
    }

    /**
     * Vérifie les protections contre le brute force
     */
    private function checkBruteForceProtection(string $email, string $ipAddress): void
    {
        // Vérifier les tentatives par email
        if ($this->loginAttemptRepository->isEmailBlocked($email, 5, 15)) {
            throw new AuthException('Trop de tentatives de connexion. Réessayez dans 15 minutes.');
        }

        // Vérifier les tentatives par IP
        if ($this->loginAttemptRepository->isIpBlocked($ipAddress, 10, 15)) {
            throw new AuthException('Trop de tentatives de connexion depuis cette adresse IP. Réessayez dans 15 minutes.');
        }
    }

    /**
     * Enregistre une tentative de connexion
     */
    private function recordLoginAttempt(string $email, string $ipAddress, bool $success, ?string $userAgent = null): void
    {
        $this->loginAttemptRepository->recordAttempt($email, $ipAddress, $success, $userAgent);
    }

    /**
     * Valide les données de connexion
     */
    private function validateLoginData(string $email, string $password): void
    {
        $errors = [];

        if (empty($email)) {
            $errors['email'] = 'L\'email est obligatoire';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format d\'email invalide';
        }

        if (empty($password)) {
            $errors['password'] = 'Le mot de passe est obligatoire';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Nettoie les anciennes tentatives de connexion
     */
    public function cleanOldLoginAttempts(int $daysToKeep = 7): int
    {
        return $this->loginAttemptRepository->cleanOldAttempts($daysToKeep);
    }

    /**
     * Obtient les IPs avec le plus de tentatives échouées
     */
    public function getSuspiciousIps(int $limit = 10, int $hours = 24): array
    {
        return $this->loginAttemptRepository->findTopFailedIps($limit, $hours);
    }

    /**
     * Vérifie si une connexion est suspecte (nouvelle IP/appareil)
     */
    public function isSuspiciousLogin(string $email, string $ipAddress, string $userAgent): bool
    {
        $recentAttempts = $this->loginAttemptRepository->findAttemptsByEmail($email, 24 * 7); // 7 jours

        // Vérifier si cette IP a déjà été utilisée
        $knownIp = false;
        foreach ($recentAttempts as $attempt) {
            if ($attempt['ip_address'] === $ipAddress && $attempt['success']) {
                $knownIp = true;
                break;
            }
        }

        // Si c'est une nouvelle IP, c'est suspect
        return !$knownIp;
    }

    /**
     * Envoie une alerte pour connexion suspecte
     */
    public function sendSuspiciousLoginAlert(string $email, string $ipAddress, string $userAgent): bool
    {
        // Cette méthode nécessiterait l'injection de EmailService
        // Pour l'instant, on retourne true
        return true;
    }

    /**
     * Renouvelle un token d'accès avec un refresh token
     */
    public function refreshToken(string $refreshToken, string $ipAddress, ?string $userAgent = null): array
    {
        try {
            // Renouveler les tokens (ceci valide le refresh token et le révoque automatiquement)
            $tokenData = $this->jwtService->refreshAccessToken($refreshToken);

            // Extraire l'user_id du nouveau JWT pour obtenir les infos utilisateur
            $jwtPayload = $this->jwtService->validateToken($tokenData['access_token']);
            $userId = $jwtPayload['user_id'];
            
            $user = $this->userRepository->findById($userId);

            if (!$user) {
                throw new AuthException('Utilisateur introuvable', 404);
            }

            // Mettre à jour la date de dernière connexion
            $this->userRepository->updateLastLogin($user->getId());

            return [
                'token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'user' => $user->toArray(),
                'expiresIn' => $tokenData['expires_in'],
                'token_type' => $tokenData['token_type']
            ];

        } catch (AuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AuthException('Erreur lors du renouvellement du token', 500);
        }
    }

    /**
     * Révoque tous les refresh tokens d'un utilisateur
     */
    public function revokeAllRefreshTokens(int $userId): bool
    {
        return $this->jwtService->revokeAllRefreshTokens($userId);
    }
}
