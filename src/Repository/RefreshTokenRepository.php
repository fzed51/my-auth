<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PDO;
use DateTime;

class RefreshTokenRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Créer un nouveau refresh token (surcharge pour les tests)
     */
    public function create(int $userId, string $tokenHash, string $expiresAt): bool
    {
        $sql = "INSERT INTO refresh_tokens 
                (user_id, token_hash, jti, expires_at) 
                VALUES (:user_id, :token_hash, :jti, :expires_at)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $tokenHash, PDO::PARAM_STR);
        $stmt->bindValue(':jti', uniqid('jti_', true), PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Créer un nouveau refresh token (version complète)
     */
    public function createToken(
        int $userId,
        string $tokenHash,
        string $jti,
        DateTime $expiresAt,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        $sql = "INSERT INTO refresh_tokens 
                (user_id, token_hash, jti, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $tokenHash,
            $jti,
            $expiresAt->format('Y-m-d H:i:s'),
            $ipAddress,
            $userAgent
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Trouver un refresh token par son hash
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $sql = "SELECT rt.*, u.email, u.is_active as user_is_active, u.is_email_verified as user_is_email_verified
                FROM refresh_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.token_hash = ? AND rt.is_revoked = FALSE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tokenHash]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Trouver un refresh token par son JTI
     */
    public function findByJti(string $jti): ?array
    {
        $sql = "SELECT * FROM refresh_tokens WHERE jti = ? AND is_revoked = FALSE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$jti]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Marquer un refresh token comme utilisé
     */
    public function markAsUsed(string $tokenHash): bool
    {
        $sql = "UPDATE refresh_tokens 
                SET last_used_at = NOW(), updated_at = NOW() 
                WHERE token_hash = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$tokenHash]);
    }

    /**
     * Révoquer un refresh token
     */
    public function revoke(string $tokenHash): bool
    {
        $sql = "UPDATE refresh_tokens 
                SET is_revoked = TRUE, updated_at = NOW() 
                WHERE token_hash = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$tokenHash]);
    }

    /**
     * Révoquer tous les refresh tokens d'un utilisateur
     */
    public function revokeAllForUser(int $userId): bool
    {
        $sql = "UPDATE refresh_tokens 
                SET is_revoked = TRUE, updated_at = NOW() 
                WHERE user_id = ? AND is_revoked = FALSE";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Obtenir tous les refresh tokens actifs d'un utilisateur
     */
    public function getActiveTokensForUser(int $userId): array
    {
        $sql = "SELECT id, jti, ip_address, user_agent, last_used_at, created_at, expires_at
                FROM refresh_tokens 
                WHERE user_id = ? AND is_revoked = FALSE AND expires_at > NOW()
                ORDER BY last_used_at DESC, created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Nettoyer les tokens expirés et révoqués
     */
    public function cleanExpiredTokens(): int
    {
        $sql = "DELETE FROM refresh_tokens 
                WHERE expires_at < NOW() OR is_revoked = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Vérifier si un token est valide
     */
    public function isValidToken(string $tokenHash): bool
    {
        $sql = "SELECT COUNT(*) 
                FROM refresh_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.token_hash = ? 
                AND rt.is_revoked = FALSE 
                AND rt.expires_at > NOW()
                AND u.is_active = TRUE
                AND u.is_email_verified = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tokenHash]);
        
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Obtenir les statistiques des refresh tokens
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_tokens,
                    COUNT(CASE WHEN is_revoked = FALSE AND expires_at > NOW() THEN 1 END) as active_tokens,
                    COUNT(CASE WHEN is_revoked = TRUE THEN 1 END) as revoked_tokens,
                    COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_tokens,
                    COUNT(DISTINCT user_id) as unique_users
                FROM refresh_tokens";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch() ?: [];
    }

    /**
     * Révoquer un refresh token par son hash
     */
    public function revokeByTokenHash(string $tokenHash): bool
    {
        return $this->revoke($tokenHash);
    }

    /**
     * Supprimer les tokens expirés
     */
    public function deleteExpiredTokens(): int
    {
        $sql = "DELETE FROM refresh_tokens WHERE expires_at < NOW()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Supprimer les tokens révoqués
     */
    public function deleteRevokedTokens(): int
    {
        $sql = "DELETE FROM refresh_tokens WHERE is_revoked = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Nettoyer les tokens (expirés et révoqués)
     */
    public function cleanup(): int
    {
        $expired = $this->deleteExpiredTokens();
        $revoked = $this->deleteRevokedTokens();
        
        return $expired + $revoked;
    }

    /**
     * Compter les tokens actifs pour un utilisateur
     */
    public function countActiveTokensForUser(int $userId): int
    {
        $sql = "SELECT COUNT(*) 
                FROM refresh_tokens 
                WHERE user_id = ? AND is_revoked = FALSE AND expires_at > NOW()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Limiter le nombre de refresh tokens par utilisateur
     */
    public function limitTokensPerUser(int $userId, int $maxTokens = 5): int
    {
        // Garder seulement les N tokens les plus récents
        $sql = "UPDATE refresh_tokens 
                SET is_revoked = TRUE, updated_at = NOW()
                WHERE user_id = ? 
                AND is_revoked = FALSE
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id 
                        FROM refresh_tokens 
                        WHERE user_id = ? AND is_revoked = FALSE 
                        ORDER BY last_used_at DESC, created_at DESC 
                        LIMIT ?
                    ) as recent_tokens
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $maxTokens]);
        
        return $stmt->rowCount();
    }
}
