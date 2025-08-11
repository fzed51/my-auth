<?php

declare(strict_types=1);

namespace MyAuth\Entity;

use DateTime;

class User
{
    private ?int $id = null;
    private string $email;
    private string $passwordHash;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private bool $isEmailVerified = false;
    private bool $isActive = true;
    private ?DateTime $lastLoginAt = null;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $email,
        string $passwordHash,
        ?string $firstName = null,
        ?string $lastName = null
    ) {
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setEmailVerified(bool $isEmailVerified): self
    {
        $this->isEmailVerified = $isEmailVerified;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastLoginAt(): ?DateTime
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?DateTime $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function recordLogin(): self
    {
        $this->lastLoginAt = new DateTime();
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function touch(): self
    {
        $this->updatedAt = new DateTime();
        return $this;
    }

    /**
     * Convertit l'entité en tableau pour la sérialisation JSON
     */
    public function toArray(bool $includePassword = false): array
    {
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->getFullName(),
            'isEmailVerified' => $this->isEmailVerified,
            'isActive' => $this->isActive,
            'lastLoginAt' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s')
        ];

        if ($includePassword) {
            $data['passwordHash'] = $this->passwordHash;
        }

        return $data;
    }

    /**
     * Crée une entité User à partir d'un tableau de données
     */
    public static function fromArray(array $data): self
    {
        $user = new self(
            $data['email'],
            $data['password_hash'],
            $data['first_name'] ?? null,
            $data['last_name'] ?? null
        );

        if (isset($data['id'])) {
            $user->setId((int)$data['id']);
        }

        $user->setEmailVerified((bool)($data['is_email_verified'] ?? false));
        $user->setActive((bool)($data['is_active'] ?? true));

        if (isset($data['last_login_at']) && $data['last_login_at']) {
            $user->setLastLoginAt(new DateTime($data['last_login_at']));
        }

        if (isset($data['created_at'])) {
            $user->setCreatedAt(new DateTime($data['created_at']));
        }

        if (isset($data['updated_at'])) {
            $user->setUpdatedAt(new DateTime($data['updated_at']));
        }

        return $user;
    }
}
