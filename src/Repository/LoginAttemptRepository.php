<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PDO;
use DateTime;

class LoginAttemptRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Enregistre une tentative de connexion
     */
    public function recordAttempt(
        string $email,
        string $ipAddress,
        bool $success,
        ?string $userAgent = null
    ): bool {
        $sql = "INSERT INTO login_attempts (email, ip_address, user_agent, success, attempted_at) 
                VALUES (:email, :ip_address, :user_agent, :success, :attempted_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':email' => $email,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':success' => $success ? 1 : 0,
            ':attempted_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Compte les tentatives échouées récentes pour un email
     */
    public function countFailedAttempts(string $email, int $timeWindowMinutes = 15): int
    {
        $sql = "SELECT COUNT(*) FROM login_attempts 
                WHERE email = :email 
                AND success = 0 
                AND attempted_at > :time_threshold";

        $timeThreshold = new DateTime();
        $timeThreshold->modify("-{$timeWindowMinutes} minutes");

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':email' => $email,
            ':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Compte les tentatives échouées récentes pour une IP
     */
    public function countFailedAttemptsByIp(string $ipAddress, int $timeWindowMinutes = 15): int
    {
        $sql = "SELECT COUNT(*) FROM login_attempts 
                WHERE ip_address = :ip_address 
                AND success = 0 
                AND attempted_at > :time_threshold";

        $timeThreshold = new DateTime();
        $timeThreshold->modify("-{$timeWindowMinutes} minutes");

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ip_address' => $ipAddress,
            ':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Vérifie si un email est bloqué (trop de tentatives échouées)
     */
    public function isEmailBlocked(string $email, int $maxAttempts = 5, int $timeWindowMinutes = 15): bool
    {
        return $this->countFailedAttempts($email, $timeWindowMinutes) >= $maxAttempts;
    }

    /**
     * Vérifie si une IP est bloquée (trop de tentatives échouées)
     */
    public function isIpBlocked(string $ipAddress, int $maxAttempts = 10, int $timeWindowMinutes = 15): bool
    {
        return $this->countFailedAttemptsByIp($ipAddress, $timeWindowMinutes) >= $maxAttempts;
    }

    /**
     * Trouve la dernière tentative réussie pour un email
     */
    public function findLastSuccessfulAttempt(string $email): ?array
    {
        $sql = "SELECT * FROM login_attempts 
                WHERE email = :email 
                AND success = 1 
                ORDER BY attempted_at DESC 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Trouve toutes les tentatives pour un email dans une période
     */
    public function findAttemptsByEmail(string $email, int $hours = 24): array
    {
        $sql = "SELECT * FROM login_attempts 
                WHERE email = :email 
                AND attempted_at > :time_threshold 
                ORDER BY attempted_at DESC";

        $timeThreshold = new DateTime();
        $timeThreshold->modify("-{$hours} hours");

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':email' => $email,
            ':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Trouve toutes les tentatives pour une IP dans une période
     */
    public function findAttemptsByIp(string $ipAddress, int $hours = 24): array
    {
        $sql = "SELECT * FROM login_attempts 
                WHERE ip_address = :ip_address 
                AND attempted_at > :time_threshold 
                ORDER BY attempted_at DESC";

        $timeThreshold = new DateTime();
        $timeThreshold->modify("-{$hours} hours");

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ip_address' => $ipAddress,
            ':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Nettoie les anciennes tentatives de connexion
     */
    public function cleanOldAttempts(int $daysToKeep = 7): int
    {
        $sql = "DELETE FROM login_attempts WHERE attempted_at < :threshold";

        $threshold = new DateTime();
        $threshold->modify("-{$daysToKeep} days");

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':threshold' => $threshold->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount();
    }

    /**
     * Obtient des statistiques sur les tentatives de connexion
     */
    public function getLoginStats(int $hours = 24): array
    {
        $timeThreshold = new DateTime();
        $timeThreshold->modify("-{$hours} hours");

        // Total des tentatives
        $sql1 = "SELECT COUNT(*) as total_attempts FROM login_attempts WHERE attempted_at > :time_threshold";
        $stmt1 = $this->pdo->prepare($sql1);
        $stmt1->execute([':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')]);
        $totalAttempts = (int)$stmt1->fetchColumn();

        // Tentatives réussies
        $sql2 = "SELECT COUNT(*) as successful_attempts FROM login_attempts 
                 WHERE attempted_at > :time_threshold AND success = 1";
        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute([':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')]);
        $successfulAttempts = (int)$stmt2->fetchColumn();

        // IPs uniques
        $sql3 = "SELECT COUNT(DISTINCT ip_address) as unique_ips FROM login_attempts 
                 WHERE attempted_at > :time_threshold";
        $stmt3 = $this->pdo->prepare($sql3);
        $stmt3->execute([':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')]);
        $uniqueIps = (int)$stmt3->fetchColumn();

        // Emails uniques
        $sql4 = "SELECT COUNT(DISTINCT email) as unique_emails FROM login_attempts 
                 WHERE attempted_at > :time_threshold";
        $stmt4 = $this->pdo->prepare($sql4);
        $stmt4->execute([':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')]);
        $uniqueEmails = (int)$stmt4->fetchColumn();

        return [
            'total_attempts' => $totalAttempts,
            'successful_attempts' => $successfulAttempts,
            'failed_attempts' => $totalAttempts - $successfulAttempts,
            'success_rate' => $totalAttempts > 0 ? round(($successfulAttempts / $totalAttempts) * 100, 2) : 0,
            'unique_ips' => $uniqueIps,
            'unique_emails' => $uniqueEmails,
            'time_window_hours' => $hours
        ];
    }

    /**
     * Trouve les IPs avec le plus de tentatives échouées
     */
    public function findTopFailedIps(int $limit = 10, int $hours = 24): array
    {
        $timeThreshold = new DateTime();
        $timeThreshold->modify("-{$hours} hours");

        $sql = "SELECT ip_address, COUNT(*) as failed_count 
                FROM login_attempts 
                WHERE attempted_at > :time_threshold 
                AND success = 0 
                GROUP BY ip_address 
                ORDER BY failed_count DESC 
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute([
            ':time_threshold' => $timeThreshold->format('Y-m-d H:i:s')
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Réinitialise le compteur pour un email (après un délai)
     */
    public function resetFailedAttemptsForEmail(string $email): bool
    {
        // On ne supprime pas les tentatives, elles seront nettoyées automatiquement
        // Cette méthode peut être utilisée pour d'autres logiques si nécessaire
        return true;
    }
}
