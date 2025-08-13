<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PDO;
use DateTime;

class JwtBlacklistRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ajoute un token JWT à la blacklist
     */
    public function addToBlacklist(string $jti, int $userId, DateTime $expiresAt): bool
    {
        $sql = "INSERT INTO jwt_blacklist (jti, user_id, expires_at, created_at) 
                VALUES (:jti, :user_id, :expires_at, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':jti' => $jti,
            ':user_id' => $userId,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ':created_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un token JWT est dans la blacklist
     */
    public function isBlacklisted(string $jti): bool
    {
        $sql = "SELECT COUNT(*) FROM jwt_blacklist WHERE jti = :jti AND expires_at > :now";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':jti' => $jti,
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Blacklist tous les tokens d'un utilisateur
     */
    public function blacklistAllUserTokens(int $userId, DateTime $expiresAt): bool
    {
        // Cette méthode est conceptuelle car nous n'avons pas de registre de tous les tokens émis
        // En pratique, on pourrait soit :
        // 1. Maintenir une table des tokens émis
        // 2. Utiliser une approche où on blacklist par date (tous les tokens avant une certaine date)

        $sql = "INSERT INTO jwt_blacklist (jti, user_id, expires_at, created_at) 
                VALUES (:jti, :user_id, :expires_at, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':jti' => 'user_logout_' . $userId . '_' . time(), // Token spécial pour logout global
            ':user_id' => $userId,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ':created_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime les tokens expirés de la blacklist
     */
    public function cleanExpiredTokens(): int
    {
        $sql = "DELETE FROM jwt_blacklist WHERE expires_at < :now";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount();
    }

    /**
     * Compte le nombre de tokens blacklistés pour un utilisateur
     */
    public function countBlacklistedForUser(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM jwt_blacklist WHERE user_id = :user_id AND expires_at > :now";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Trouve tous les tokens blacklistés pour un utilisateur
     */
    public function findBlacklistedForUser(int $userId): array
    {
        $sql = "SELECT * FROM jwt_blacklist 
                WHERE user_id = :user_id 
                AND expires_at > :now 
                ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Supprime manuellement un token de la blacklist
     */
    public function removeFromBlacklist(string $jti): bool
    {
        $sql = "DELETE FROM jwt_blacklist WHERE jti = :jti";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':jti', $jti, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Compte le nombre total de tokens blacklistés actifs
     */
    public function countActiveBlacklisted(): int
    {
        $sql = "SELECT COUNT(*) FROM jwt_blacklist WHERE expires_at > :now";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Trouve les tokens qui expirent bientôt (pour le nettoyage)
     */
    public function findExpiringSoon(int $hours = 1): array
    {
        $sql = "SELECT * FROM jwt_blacklist 
                WHERE expires_at BETWEEN :now AND :future 
                ORDER BY expires_at ASC";

        $future = new DateTime();
        $future->modify("+{$hours} hours");

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':now' => (new DateTime())->format('Y-m-d H:i:s'),
            ':future' => $future->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll();
    }
}
