<?php

/**
 * Repository Class for MyAuth
 *
 * @package MyAuth\Repository
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Repository;

use MyAuth\Entity\User;
use MyAuth\Exception\UserNotFoundException;
use MyAuth\Exception\UserAlreadyExistsException;
use PDO;
use PDOException;

class UserRepository extends AbstractRepository
{
    protected string $tableName = 'users';

    /**


     * Get the tableName


     *


     * @return string


     */


    public function getTableName(): string
    {
        return 'users';
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return User::fromArray($data);
    }

    public function findByIdOrFail(string $id): User
    {
        $user = $this->findUserById($id);

        if (!$user) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }

    public function findUserById(string $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return User::fromArray($data);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() > 0;
    }

    public function create(User $user): void
    {
        if ($this->emailExists($user->getEmail())) {
            throw new UserAlreadyExistsException($user->getEmail());
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (id, email, password_hash, first_name, last_name, ' .
                'is_active, is_verified, created_at, updated_at) 
                 VALUES (:id, :email, :password_hash, :first_name, :last_name, ' .
                ':is_active, :is_verified, :created_at, :updated_at)'
            );

            $stmt->execute([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'password_hash' => $user->getPasswordHash(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'is_active' => $user->isActive() ? 1 : 0,
                'is_verified' => $user->isVerified() ? 1 : 0,
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // Integrity constraint violation
                throw new UserAlreadyExistsException($user->getEmail());
            }
            throw $e;
        }
    }

    public function updateUser(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users 
             SET email = :email, password_hash = :password_hash, first_name = :first_name, 
                 last_name = :last_name, is_active = :is_active, is_verified = :is_verified, 
                 updated_at = :updated_at 
             WHERE id = :id'
        );

        $result = $stmt->execute([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'password_hash' => $user->getPasswordHash(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'is_active' => $user->isActive() ? 1 : 0,
            'is_verified' => $user->isVerified() ? 1 : 0,
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        if (!$result || $stmt->rowCount() === 0) {
            throw new UserNotFoundException($user->getId());
        }
    }

    public function deleteUser(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $result = $stmt->execute(['id' => $id]);

        if (!$result || $stmt->rowCount() === 0) {
            throw new UserNotFoundException($id);
        }
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE is_active = 1');
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countVerified(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE is_verified = 1');
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findRecentUsers(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $users = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = User::fromArray($data);
        }

        return $users;
    }
}
