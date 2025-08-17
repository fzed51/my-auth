<?php

/**
 * Repository Class for MyAuth
 *
 * @package MyAuth\Repository
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Repository;

use PDO;
use MyAuth\Entity\EmailVerification;
use MyAuth\Exception\EmailVerificationException;

class EmailVerificationRepository extends AbstractRepository
{
    protected string $tableName = 'email_verifications';

    /**


     * Get the tableName


     *


     * @return string


     */


    public function getTableName(): string
    {
        return 'email_verifications';
    }

    public function findValidByToken(string $token): ?EmailVerification
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM email_verifications 
             WHERE token = :token 
             AND expires_at > datetime('now') 
             AND is_used = 0"
        );
        $stmt->execute(['token' => $token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return EmailVerification::fromArray($data);
    }

    public function findPendingByUserId(string $userId): ?EmailVerification
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM email_verifications 
             WHERE user_id = :user_id 
             AND expires_at > datetime('now') 
             AND is_used = 0 
             ORDER BY created_at DESC 
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return EmailVerification::fromArray($data);
    }

    public function create(EmailVerification $verification): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_verifications (id, user_id, token, expires_at, is_used, created_at) 
             VALUES (:id, :user_id, :token, :expires_at, :is_used, :created_at)"
        );

        $data = $verification->toArray();
        $stmt->execute([
            'id' => $data['id'],
            'user_id' => $data['user_id'],
            'token' => $data['token'],
            'expires_at' => $data['expires_at'],
            'is_used' => $data['is_used'] ? 1 : 0,
            'created_at' => $data['created_at']
        ]);
    }

    public function markAsUsed(string $token): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_verifications 
             SET is_used = 1, used_at = datetime('now') 
             WHERE token = :token"
        );
        $stmt->execute(['token' => $token]);

        if ($stmt->rowCount() === 0) {
            throw new EmailVerificationException('Email verification token not found');
        }
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM email_verifications 
             WHERE expires_at <= datetime('now')"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function hasRecentVerification(string $userId, int $minutesAgo): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM email_verifications 
             WHERE user_id = :user_id 
             AND created_at > datetime('now', '-' || :minutes || ' minutes')"
        );
        $stmt->execute([
            'user_id' => $userId,
            'minutes' => $minutesAgo
        ]);

        return $stmt->fetchColumn() > 0;
    }

    public function deleteByUserId(string $userId): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM email_verifications WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }
}
