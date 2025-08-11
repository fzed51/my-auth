<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use RuntimeException;

class ServiceRepository
{
    private string $configPath;
    private ?array $services = null;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? __DIR__ . '/../../config/services.json';
        $this->loadServices();
    }

    /**
     * Charge les services depuis le fichier JSON
     */
    private function loadServices(): void
    {
        if ($this->services === null) {
            if (!file_exists($this->configPath)) {
                throw new RuntimeException("Services configuration file not found: {$this->configPath}");
            }

            $content = file_get_contents($this->configPath);
            if ($content === false) {
                throw new RuntimeException("Could not read services configuration file: {$this->configPath}");
            }

            $this->services = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Invalid JSON in services configuration: " . json_last_error_msg());
            }
        }
    }

    /**
     * Trouve un service par son API key
     */
    public function findByApiKey(string $apiKey): ?array
    {
        foreach ($this->services as $service) {
            if (isset($service['api_key']) && hash_equals($service['api_key'], $apiKey)) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Trouve un service par son nom
     */
    public function findByName(string $name): ?array
    {
        foreach ($this->services as $service) {
            if (isset($service['name']) && $service['name'] === $name) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Trouve un service par son ID
     */
    public function findById(int $id): ?array
    {
        foreach ($this->services as $service) {
            if (isset($service['id']) && $service['id'] === $id) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Retourne tous les services actifs
     */
    public function findAllActive(): array
    {
        return array_filter($this->services, function ($service) {
            return isset($service['is_active']) && $service['is_active'] === true;
        });
    }

    /**
     * Retourne tous les services
     */
    public function findAll(): array
    {
        return $this->services;
    }

    /**
     * Vérifie si un service a une permission spécifique
     */
    public function hasPermission(array $service, string $permission): bool
    {
        if (!isset($service['permissions']) || !is_array($service['permissions'])) {
            return false;
        }

        // Vérification des permissions avec support des wildcards
        foreach ($service['permissions'] as $servicePermission) {
            if ($servicePermission === $permission) {
                return true;
            }

            // Support des wildcards (ex: "auth:*" permet "auth:login", "auth:register", etc.)
            if (str_ends_with($servicePermission, ':*')) {
                $prefix = substr($servicePermission, 0, -2);
                if (str_starts_with($permission, $prefix . ':')) {
                    return true;
                }
            }

            // Support du wildcard global
            if ($servicePermission === '*') {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie les limites de taux pour un service
     */
    public function checkRateLimit(array $service, string $type = 'requests_per_minute'): ?int
    {
        if (!isset($service['rate_limit'][$type])) {
            return null;
        }

        return (int)$service['rate_limit'][$type];
    }

    /**
     * Valide la structure d'un service
     */
    public function validateService(array $service): bool
    {
        $requiredFields = ['id', 'name', 'api_key', 'is_active'];
        
        foreach ($requiredFields as $field) {
            if (!isset($service[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recharge les services depuis le fichier (utile pour les tests)
     */
    public function reload(): void
    {
        $this->services = null;
        $this->loadServices();
    }
}
