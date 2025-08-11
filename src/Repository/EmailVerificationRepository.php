<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use MyAuth\Entity\EmailVerification;
use PDO;
use DateTime;

class EmailVerificationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sauvegarde un token de vérification
     */
    public function save(EmailVerification $verification): EmailVerification
    {
        if ($verification->getId() === null) {
            return $this->insert($verification);
        } else {
            return $this->update($verification);
        }
    }

    /**
     * Insère un nouveau token de vérification
     */
    private function insert(EmailVerification $verification): EmailVerification
    {
        $sql = "INSERT INTO email_verifications (user_id, token, token_hash, expires_at, created_at) 
                VALUES (:user_id, :token, :token_hash, :expires_at, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $verification->getUserId(),
            ':token' => $verification->getToken(),
            ':token_hash' => $verification->getTokenHash(),
            ':expires_at' => $verification->getExpiresAt()->format('Y-m-d H:i:s'),
            ':created_at' => $verification->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        $verification->setId((int)$this->pdo->lastInsertId());
        return $verification;
    }

    /**
     * Met à jour un token de vérification existant
     */
    private function update(EmailVerification $verification): EmailVerification
    {
        $sql = "UPDATE email_verifications SET 
                user_id = :user_id,
                token = :token,
                token_hash = :token_hash,
                expires_at = :expires_at,
                used_at = :used_at
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $verification->getId(),
            ':user_id' => $verification->getUserId(),
            ':token' => $verification->getToken(),
            ':token_hash' => $verification->getTokenHash(),
            ':expires_at' => $verification->getExpiresAt()->format('Y-m-d H:i:s'),
            ':used_at' => $verification->getUsedAt()?->format('Y-m-d H:i:s')
        ]);

        return $verification;
    }

    /**
     * Trouve un token de vérification par son hash
     */
    public function findByTokenHash(string $tokenHash): ?EmailVerification
    {
        $sql = "SELECT * FROM email_verifications WHERE token_hash = :token_hash LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':token_hash', $tokenHash, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }

        return EmailVerification::fromArray($data);
    }

    /**
     * Trouve un token de vérification par le token lui-même
     */
    public function findByToken(string $token): ?EmailVerification
    {
        $tokenHash = hash('sha256', $token);
        return $this->findByTokenHash($tokenHash);
    }

    /**
     * Trouve tous les tokens de vérification pour un utilisateur
     */
    public function findByUserId(int $userId): array
    {
        $sql = "SELECT * FROM email_verifications WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $verifications = [];
        while ($data = $stmt->fetch()) {
            $verifications[] = EmailVerification::fromArray($data);
        }

        return $verifications;
    }

    /**
     * Trouve le token de vérification valide le plus récent pour un utilisateur
     */
    public function findValidTokenForUser(int $userId): ?EmailVerification
    {
        $sql = "SELECT * FROM email_verifications 
                WHERE user_id = :user_id 
                AND expires_at > :now 
                AND used_at IS NULL 
                ORDER BY created_at DESC 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }

        return EmailVerification::fromArray($data);
    }

    /**
     * Marque un token comme utilisé
     */
    public function markAsUsed(int $verificationId): bool
    {
        $sql = "UPDATE email_verifications SET used_at = :used_at WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $verificationId,
            ':used_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Invalide tous les tokens de vérification pour un utilisateur
     */
    public function invalidateAllForUser(int $userId): bool
    {
        $sql = "UPDATE email_verifications SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':used_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime les tokens expirés et utilisés
     */
    public function cleanExpiredTokens(): int
    {
        $sql = "DELETE FROM email_verifications 
                WHERE (expires_at < :now OR used_at IS NOT NULL) 
                AND created_at < :cleanup_date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':now' => (new DateTime())->format('Y-m-d H:i:s'),
            ':cleanup_date' => (new DateTime('-7 days'))->format('Y-m-d H:i:s') // Garde les tokens utilisés pendant 7 jours pour audit
        ]);

        return $stmt->rowCount();
    }

    /**
     * Compte les tokens de vérification en attente pour un utilisateur
     */
    public function countPendingForUser(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM email_verifications 
                WHERE user_id = :user_id 
                AND expires_at > :now 
                AND used_at IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Supprime un token de vérification
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM email_verifications WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Trouve les tokens expirés
     */
    public function findExpiredTokens(): array
    {
        $sql = "SELECT * FROM email_verifications 
                WHERE expires_at < :now 
                AND used_at IS NULL 
                ORDER BY expires_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':now' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        $verifications = [];
        while ($data = $stmt->fetch()) {
            $verifications[] = EmailVerification::fromArray($data);
        }

        return $verifications;
    }
}
