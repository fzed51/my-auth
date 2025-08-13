<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use MyAuth\Entity\User;
use MyAuth\Exception\UserNotFoundException;
use PDO;
use PDOException;
use DateTime;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Trouve un utilisateur par son email
     */
    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }

        return User::fromArray($data);
    }

    /**
     * Trouve un utilisateur par son ID
     */
    public function findById(int $id): ?User
    {
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }

        return User::fromArray($data);
    }

    /**
     * Sauvegarde un nouvel utilisateur
     */
    public function save(User $user): User
    {
        if ($user->getId() === null) {
            return $this->insert($user);
        } else {
            return $this->update($user);
        }
    }

    /**
     * Insère un nouvel utilisateur
     */
    private function insert(User $user): User
    {
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, is_email_verified, is_active, created_at, updated_at) 
                VALUES (:email, :password_hash, :first_name, :last_name, :is_email_verified, :is_active, :created_at, :updated_at)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':email' => $user->getEmail(),
                ':password_hash' => $user->getPasswordHash(),
                ':first_name' => $user->getFirstName(),
                ':last_name' => $user->getLastName(),
                ':is_email_verified' => $user->isEmailVerified() ? 1 : 0,
                ':is_active' => $user->isActive() ? 1 : 0,
                ':created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                ':updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s')
            ]);

            $user->setId((int)$this->pdo->lastInsertId());
            return $user;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // Duplicate entry
                throw new \InvalidArgumentException('Un utilisateur avec cet email existe déjà');
            }
            throw $e;
        }
    }

    /**
     * Met à jour un utilisateur existant
     */
    private function update(User $user): User
    {
        $user->touch(); // Met à jour updated_at

        $sql = "UPDATE users SET 
                email = :email, 
                password_hash = :password_hash, 
                first_name = :first_name, 
                last_name = :last_name, 
                is_email_verified = :is_email_verified, 
                is_active = :is_active,
                last_login_at = :last_login_at,
                updated_at = :updated_at
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $user->getId(),
            ':email' => $user->getEmail(),
            ':password_hash' => $user->getPasswordHash(),
            ':first_name' => $user->getFirstName(),
            ':last_name' => $user->getLastName(),
            ':is_email_verified' => $user->isEmailVerified() ? 1 : 0,
            ':is_active' => $user->isActive() ? 1 : 0,
            ':last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ':updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);

        return $user;
    }

    /**
     * Supprime un utilisateur
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Active un utilisateur (vérifie son email)
     */
    public function activateUser(int $userId): bool
    {
        $sql = "UPDATE users SET is_email_verified = 1, updated_at = :updated_at WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $userId,
            ':updated_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Met à jour la date de dernière connexion
     */
    public function updateLastLogin(int $userId): bool
    {
        $sql = "UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $userId,
            ':last_login_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ':updated_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Trouve tous les utilisateurs actifs et vérifiés
     */
    public function findActiveUsers(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM users 
                WHERE is_active = 1 AND is_email_verified = 1 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch()) {
            $users[] = User::fromArray($data);
        }

        return $users;
    }

    /**
     * Compte le nombre total d'utilisateurs
     */
    public function countUsers(): int
    {
        $sql = "SELECT COUNT(*) FROM users";
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Compte le nombre d'utilisateurs actifs et vérifiés
     */
    public function countActiveUsers(): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND is_email_verified = 1";
        $stmt = $this->pdo->query($sql);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Recherche des utilisateurs par email ou nom
     */
    public function search(string $query, int $limit = 50): array
    {
        $searchTerm = '%' . $query . '%';
        $sql = "SELECT * FROM users 
                WHERE (email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)
                AND is_active = 1 
                ORDER BY created_at DESC 
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch()) {
            $users[] = User::fromArray($data);
        }

        return $users;
    }
}
