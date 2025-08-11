<?php

declare(strict_types=1);

namespace MyAuth\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use MyAuth\Repository\JwtBlacklistRepository;
use MyAuth\Exception\AuthException;
use DateTime;
use Exception;

class JwtService
{
    private array $config;
    private JwtBlacklistRepository $blacklistRepository;

    public function __construct(array $config, JwtBlacklistRepository $blacklistRepository)
    {
        $this->config = $config;
        $this->blacklistRepository = $blacklistRepository;
    }

    /**
     * Génère un token JWT pour un utilisateur
     */
    public function generateToken(int $userId, array $additionalClaims = []): string
    {
        $now = new DateTime();
        $expiresAt = clone $now;
        $expiresAt->modify('+' . $this->config['expiration'] . ' seconds');

        $payload = array_merge([
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience'],
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'jti' => $this->generateJti(),
            'sub' => (string)$userId,
            'user_id' => $userId
        ], $additionalClaims);

        return JWT::encode($payload, $this->config['secret'], $this->config['algorithm']);
    }

    /**
     * Valide et décode un token JWT
     */
    public function validateToken(string $token): array
    {
        try {
            // Ajout de la tolérance pour l'horloge
            JWT::$leeway = $this->config['leeway'] ?? 60;

            $decoded = JWT::decode($token, new Key($this->config['secret'], $this->config['algorithm']));
            $payload = (array)$decoded;

            // Vérification de la blacklist
            if (isset($payload['jti']) && $this->blacklistRepository->isBlacklisted($payload['jti'])) {
                throw new AuthException('Token has been revoked');
            }

            return $payload;
        } catch (ExpiredException $e) {
            throw new AuthException('Token has expired');
        } catch (SignatureInvalidException $e) {
            throw new AuthException('Invalid token signature');
        } catch (Exception $e) {
            throw new AuthException('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * Extrait le payload d'un token sans validation (pour débug uniquement)
     */
    public function decodeTokenWithoutValidation(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthException('Invalid token format');
        }

        $payload = json_decode(base64_decode($parts[1]), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AuthException('Invalid token payload');
        }

        return $payload;
    }

    /**
     * Révoque un token en l'ajoutant à la blacklist
     */
    public function revokeToken(string $token): bool
    {
        try {
            $payload = $this->decodeTokenWithoutValidation($token);
            
            if (!isset($payload['jti'], $payload['user_id'], $payload['exp'])) {
                return false;
            }

            $expiresAt = new DateTime();
            $expiresAt->setTimestamp($payload['exp']);

            return $this->blacklistRepository->addToBlacklist(
                $payload['jti'],
                (int)$payload['user_id'],
                $expiresAt
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Révoque tous les tokens d'un utilisateur
     */
    public function revokeAllUserTokens(int $userId): bool
    {
        $expiresAt = new DateTime();
        $expiresAt->modify('+' . $this->config['expiration'] . ' seconds');

        return $this->blacklistRepository->blacklistAllUserTokens($userId, $expiresAt);
    }

    /**
     * Vérifie si un token est valide sans lever d'exception
     */
    public function isTokenValid(string $token): bool
    {
        try {
            $this->validateToken($token);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Extrait l'ID utilisateur d'un token
     */
    public function getUserIdFromToken(string $token): ?int
    {
        try {
            $payload = $this->validateToken($token);
            return isset($payload['user_id']) ? (int)$payload['user_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Obtient les informations d'expiration d'un token
     */
    public function getTokenExpiration(string $token): ?DateTime
    {
        try {
            $payload = $this->decodeTokenWithoutValidation($token);
            if (isset($payload['exp'])) {
                $expiration = new DateTime();
                $expiration->setTimestamp($payload['exp']);
                return $expiration;
            }
        } catch (Exception $e) {
            // Token invalide
        }

        return null;
    }

    /**
     * Vérifie si un token expire bientôt
     */
    public function isTokenExpiringSoon(string $token, int $thresholdMinutes = 5): bool
    {
        $expiration = $this->getTokenExpiration($token);
        if (!$expiration) {
            return true; // Considérer comme expirant si on ne peut pas déterminer l'expiration
        }

        $threshold = new DateTime();
        $threshold->modify("+{$thresholdMinutes} minutes");

        return $expiration <= $threshold;
    }

    /**
     * Rafraîchit un token (génère un nouveau token avec la même durée de vie)
     */
    public function refreshToken(string $token): string
    {
        $payload = $this->validateToken($token);
        
        // Révoque l'ancien token
        $this->revokeToken($token);

        // Génère un nouveau token avec les mêmes claims
        $userId = (int)$payload['user_id'];
        $additionalClaims = array_diff_key($payload, [
            'iss' => null,
            'aud' => null,
            'iat' => null,
            'nbf' => null,
            'exp' => null,
            'jti' => null,
            'sub' => null,
            'user_id' => null
        ]);

        return $this->generateToken($userId, $additionalClaims);
    }

    /**
     * Génère un JTI unique pour le token
     */
    private function generateJti(): string
    {
        return bin2hex(random_bytes(16)) . '_' . time();
    }

    /**
     * Obtient la configuration JWT
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Nettoie les tokens expirés de la blacklist
     */
    public function cleanExpiredTokens(): int
    {
        return $this->blacklistRepository->cleanExpiredTokens();
    }
}
