<?php

declare(strict_types=1);

namespace MyAuth\Entity;

use DateTime;
use InvalidArgumentException;

class User
{
    private string $id;
    private string $email;
    private string $passwordHash;
    private bool $isActive;
    private bool $isVerified;
    private string $firstName;
    private string $lastName;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        string $firstName,
        string $lastName,
        bool $isActive = false,
        bool $isVerified = false,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->validateEmail($email);
        $this->validateName($firstName, 'firstName');
        $this->validateName($lastName, 'lastName');

        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->isActive = $isActive;
        $this->isVerified = $isVerified;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updateTimestamp();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updateTimestamp();
    }

    public function verify(): void
    {
        $this->isVerified = true;
        $this->updateTimestamp();
    }

    public function verifyEmail(): void
    {
        $this->verify();
        $this->activate();
    }

    public function updatePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->updateTimestamp();
    }

    public function updateProfile(string $firstName, string $lastName): void
    {
        $this->validateName($firstName, 'firstName');
        $this->validateName($lastName, 'lastName');

        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->updateTimestamp();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'is_active' => $this->isActive,
            'is_verified' => $this->isVerified,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'isActive' => $this->isActive,
            'isEmailVerified' => $this->isVerified,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            email: $data['email'],
            passwordHash: $data['password_hash'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            isActive: (bool) $data['is_active'],
            isVerified: (bool) $data['is_verified'],
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        if (strlen($email) > 255) {
            throw new InvalidArgumentException('Email too long (max 255 characters)');
        }
    }

    private function validateName(string $name, string $field): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException("{$field} cannot be empty");
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException("{$field} too long (max 100 characters)");
        }
    }

    private function updateTimestamp(): void
    {
        $this->updatedAt = new DateTime();
    }
}
