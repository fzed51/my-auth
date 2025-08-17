<?php

/**
 * Entity Class for MyAuth
 *
 * @package MyAuth\Entity
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Entity;

use DateTime;
use InvalidArgumentException;

class EmailVerification
{
    private string $id;
    private string $userId;
    private string $token;
    private DateTime $expiresAt;
    private bool $isUsed;
    private ?DateTime $usedAt;
    private DateTime $createdAt;

    /**


     * Constructor


     */


    public function __construct(
        string $id,
        string $userId,
        string $token,
        DateTime $expiresAt,
        bool $isUsed = false,
        ?DateTime $usedAt = null,
        ?DateTime $createdAt = null
    ) {


        $this->validateToken($token);

        $this->id = $id;
        $this->userId = $userId;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->isUsed = $isUsed;
        $this->usedAt = $usedAt;
        $this->createdAt = $createdAt ?? new DateTime();
    }

    /**


     * Get the id


     *


     * @return string


     */


    public function getId(): string
    {
        return $this->id;
    }

    /**


     * Get the userId


     *


     * @return string


     */


    public function getUserId(): string
    {
        return $this->userId;
    }

    /**


     * Get the token


     *


     * @return string


     */


    public function getToken(): string
    {
        return $this->token;
    }

    /**


     * Get the expiresAt


     *


     * @return DateTime


     */


    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    /**


     * Check if used


     *


     * @return bool


     */


    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    /**


     * Get the usedAt


     *


     * @return ?DateTime


     */


    public function getUsedAt(): ?DateTime
    {
        return $this->usedAt;
    }

    /**


     * Get the createdAt


     *


     * @return DateTime


     */


    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**


     * Check if expired


     *


     * @return bool


     */


    public function isExpired(): bool
    {
        return new DateTime() > $this->expiresAt;
    }

    /**


     * Check if valid


     *


     * @return bool


     */


    public function isValid(): bool
    {
        return !$this->isUsed && !$this->isExpired();
    }

    public function markAsUsed(): void
    {
        if ($this->isUsed) {
            throw new InvalidArgumentException('Token already used');
        }

        if ($this->isExpired()) {
            throw new InvalidArgumentException('Token expired');
        }

        $this->isUsed = true;
        $this->usedAt = new DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'token' => $this->token,
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'is_used' => $this->isUsed,
            'used_at' => $this->usedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            userId: $data['user_id'],
            token: $data['token'],
            expiresAt: new DateTime($data['expires_at']),
            isUsed: (bool) $data['is_used'],
            usedAt: isset($data['used_at']) ? new DateTime($data['used_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null
        );
    }

    private function validateToken(string $token): void
    {
        if (empty($token)) {
            throw new InvalidArgumentException('Token cannot be empty');
        }

        if (strlen($token) < 32) {
            throw new InvalidArgumentException('Token too short (min 32 characters)');
        }

        if (strlen($token) > 255) {
            throw new InvalidArgumentException('Token too long (max 255 characters)');
        }
    }
}
