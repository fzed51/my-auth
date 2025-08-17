<?php

/**
 * Service Class for MyAuth
 *
 * @package MyAuth\Entity
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Entity;

/**
 * Entité Service - Représente un service autorisé à utiliser l'API
 */
class Service
{
    private string $id;
    private string $name;
    private string $apiKey;
    private string $description;
    private bool $isActive;
    private array $allowedOrigins;
    private int $rateLimitPerMinute;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    /**


     * Constructor


     */


    public function __construct(
        string $id,
        string $name,
        string $apiKey,
        string $description,
        bool $isActive = true,
        array $allowedOrigins = [],
        int $rateLimitPerMinute = 60
    ) {


        $this->id = $id;
        $this->name = $name;
        $this->apiKey = $apiKey;
        $this->description = $description;
        $this->isActive = $isActive;
        $this->allowedOrigins = $allowedOrigins;
        $this->rateLimitPerMinute = $rateLimitPerMinute;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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


     * Get the name


     *


     * @return string


     */


    public function getName(): string
    {
        return $this->name;
    }

    /**


     * Get the apiKey


     *


     * @return string


     */


    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**


     * Get the description


     *


     * @return string


     */


    public function getDescription(): string
    {
        return $this->description;
    }

    /**


     * Check if active


     *


     * @return bool


     */


    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**


     * Set the active


     *


     * @param bool $isActive


     */


    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();
    }

    /**


     * Get the allowedOrigins


     *


     * @return array


     */


    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**


     * Set the allowedOrigins


     *


     * @param array $allowedOrigins


     */


    public function setAllowedOrigins(array $allowedOrigins): void
    {
        $this->allowedOrigins = $allowedOrigins;
        $this->updatedAt = new \DateTime();
    }

    /**


     * Get the rateLimitPerMinute


     *


     * @return int


     */


    public function getRateLimitPerMinute(): int
    {
        return $this->rateLimitPerMinute;
    }

    /**


     * Set the rateLimitPerMinute


     *


     * @param int $rateLimitPerMinute


     */


    public function setRateLimitPerMinute(int $rateLimitPerMinute): void
    {
        $this->rateLimitPerMinute = $rateLimitPerMinute;
        $this->updatedAt = new \DateTime();
    }

    /**


     * Get the createdAt


     *


     * @return ?\DateTime


     */


    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**


     * Get the updatedAt


     *


     * @return ?\DateTime


     */


    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Vérifie si une origine est autorisée pour ce service
     */
    public function isOriginAllowed(string $origin): bool
    {
        if (empty($this->allowedOrigins)) {
            return true; // Pas de restriction
        }

        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($allowedOrigin === '*' || $allowedOrigin === $origin) {
                return true;
            }

            // Support des wildcards (*.example.com)
            if (str_starts_with($allowedOrigin, '*.')) {
                $domain = substr($allowedOrigin, 2);
                if (str_ends_with($origin, $domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Crée une instance Service à partir d'un tableau de données
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['name'],
            $data['api_key'],
            $data['description'],
            $data['is_active'] ?? true,
            $data['allowed_origins'] ?? [],
            $data['rate_limit_per_minute'] ?? 60
        );
    }

    /**
     * Convertit l'entité en tableau
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'api_key' => $this->apiKey,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'allowed_origins' => $this->allowedOrigins,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
