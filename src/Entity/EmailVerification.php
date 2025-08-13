<?php

declare(strict_types=1);

namespace MyAuth\Entity;

use DateTime;

class EmailVerification
{
    private ?int $id = null;
    private int $userId;
    private string $token;
    private string $tokenHash;
    private DateTime $expiresAt;
    private ?DateTime $usedAt = null;
    private DateTime $createdAt;

    public function __construct(
        int $userId,
        string $token,
        DateTime $expiresAt
    ) {
        $this->userId = $userId;
        $this->token = $token;
        $this->tokenHash = hash('sha256', $token);
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTime();
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

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        $this->tokenHash = hash('sha256', $token);
        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function verifyToken(string $token): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $token));
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return new DateTime() > $this->expiresAt;
    }

    public function getUsedAt(): ?DateTime
    {
        return $this->usedAt;
    }

    public function setUsedAt(?DateTime $usedAt): self
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function markAsUsed(): self
    {
        $this->usedAt = new DateTime();
        return $this;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
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

    /**
     * Génère un token de vérification sécurisé
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Crée un token de vérification avec une durée de validité
     */
    public static function createForUser(int $userId, int $expirationHours = 24): self
    {
        $token = self::generateToken();
        $expiresAt = (new DateTime())->modify("+{$expirationHours} hours");

        return new self($userId, $token, $expiresAt);
    }

    /**
     * Convertit l'entité en tableau pour la sérialisation
     */
    public function toArray(bool $includeToken = false): array
    {
        $data = [
            'id' => $this->id,
            'userId' => $this->userId,
            'tokenHash' => $this->tokenHash,
            'expiresAt' => $this->expiresAt->format('Y-m-d H:i:s'),
            'usedAt' => $this->usedAt?->format('Y-m-d H:i:s'),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'isExpired' => $this->isExpired(),
            'isUsed' => $this->isUsed(),
            'isValid' => $this->isValid()
        ];

        if ($includeToken) {
            $data['token'] = $this->token;
        }

        return $data;
    }

    /**
     * Crée une entité EmailVerification à partir d'un tableau de données
     */
    public static function fromArray(array $data): self
    {
        $verification = new self(
            (int)$data['user_id'],
            $data['token'],
            new DateTime($data['expires_at'])
        );

        if (isset($data['id'])) {
            $verification->setId((int)$data['id']);
        }

        if (isset($data['used_at']) && $data['used_at']) {
            $verification->setUsedAt(new DateTime($data['used_at']));
        }

        if (isset($data['created_at'])) {
            $verification->setCreatedAt(new DateTime($data['created_at']));
        }

        return $verification;
    }
}
