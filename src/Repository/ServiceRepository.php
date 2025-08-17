<?php

/**
 * Service Class for MyAuth
 *
 * @package MyAuth\Repository
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Repository;

use MyAuth\Entity\Service;
use MyAuth\Exception\ServiceNotFoundException;

/**
 * Repository pour la gestion des services autorisés
 *
 * Note: Les services sont stockés dans config/services.json plutôt qu'en base de données
 * selon les spécifications du prompt
 */
class ServiceRepository
{
    private string $servicesConfigPath;
    private ?array $servicesCache = null;

    /**


     * Constructor


     */


    public function __construct(string $servicesConfigPath)
    {
        $this->servicesConfigPath = $servicesConfigPath;
    }

    /**
     * Charge les services depuis le fichier de configuration
     */
    private function loadServices(): array
    {
        if ($this->servicesCache !== null) {
            return $this->servicesCache;
        }

        if (!file_exists($this->servicesConfigPath)) {
            throw new \RuntimeException("Configuration des services introuvable : {$this->servicesConfigPath}");
        }

        $content = file_get_contents($this->servicesConfigPath);
        if ($content === false) {
            throw new \RuntimeException("Impossible de lire le fichier de configuration des services");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Fichier de configuration des services invalide (JSON malformé)");
        }

        if (!isset($data['services']) || !is_array($data['services'])) {
            throw new \RuntimeException("Structure de configuration des services invalide");
        }

        $this->servicesCache = $data['services'];
        return $this->servicesCache;
    }

    /**
     * Trouve un service par son API key
     */
    public function findByApiKey(string $apiKey): ?Service
    {
        $services = $this->loadServices();

        foreach ($services as $serviceData) {
            if (isset($serviceData['api_key']) && $serviceData['api_key'] === $apiKey) {
                return Service::fromArray($serviceData);
            }
        }

        return null;
    }

    /**
     * Trouve un service par son ID
     */
    public function findById(string $id): ?Service
    {
        $services = $this->loadServices();

        foreach ($services as $serviceData) {
            if (isset($serviceData['id']) && $serviceData['id'] === $id) {
                return Service::fromArray($serviceData);
            }
        }

        return null;
    }

    /**
     * Trouve un service par son nom
     */
    public function findByName(string $name): ?Service
    {
        $services = $this->loadServices();

        foreach ($services as $serviceData) {
            if (isset($serviceData['name']) && $serviceData['name'] === $name) {
                return Service::fromArray($serviceData);
            }
        }

        return null;
    }

    /**
     * Récupère tous les services actifs
     */
    public function findAllActive(): array
    {
        $services = $this->loadServices();
        $activeServices = [];

        foreach ($services as $serviceData) {
            if (($serviceData['is_active'] ?? true) === true) {
                $activeServices[] = Service::fromArray($serviceData);
            }
        }

        return $activeServices;
    }

    /**
     * Récupère tous les services
     */
    public function findAll(): array
    {
        $services = $this->loadServices();
        $allServices = [];

        foreach ($services as $serviceData) {
            $allServices[] = Service::fromArray($serviceData);
        }

        return $allServices;
    }

    /**
     * Vérifie si un service existe et est actif
     */
    public function isServiceActiveByApiKey(string $apiKey): bool
    {
        $service = $this->findByApiKey($apiKey);
        return $service !== null && $service->isActive();
    }

    /**
     * Compte le nombre de services actifs
     */
    public function countActive(): int
    {
        return count($this->findAllActive());
    }

    /**
     * Valide qu'un API key a le bon format
     */
    public function validateApiKeyFormat(string $apiKey): bool
    {
        // API key doit faire au moins 32 caractères et contenir uniquement des caractères alphanumériques et tirets
        return strlen($apiKey) >= 32 && preg_match('/^[a-zA-Z0-9\-_]+$/', $apiKey) === 1;
    }

    /**
     * Invalide le cache des services
     */
    public function clearCache(): void
    {
        $this->servicesCache = null;
    }

    /**
     * Vérifie si la configuration des services est valide
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        try {
            $services = $this->loadServices();
            $apiKeys = [];
            $ids = [];
            $names = [];

            foreach ($services as $index => $serviceData) {
                $prefix = "Service index {$index}";

                // Vérification des champs requis
                $requiredFields = ['id', 'name', 'api_key', 'description'];
                foreach ($requiredFields as $field) {
                    if (!isset($serviceData[$field]) || empty($serviceData[$field])) {
                        $errors[] = "{$prefix}: Champ requis manquant ou vide: {$field}";
                    }
                }

                if (isset($serviceData['id'])) {
                    // Vérification de l'unicité de l'ID
                    if (in_array($serviceData['id'], $ids, true)) {
                        $errors[] = "{$prefix}: ID dupliqué: {$serviceData['id']}";
                    }
                    $ids[] = $serviceData['id'];

                    // Vérification du format UUID
                    if (!$this->isValidUuid($serviceData['id'])) {
                        $errors[] = "{$prefix}: ID doit être un UUID valide: {$serviceData['id']}";
                    }
                }

                if (isset($serviceData['name'])) {
                    // Vérification de l'unicité du nom
                    if (in_array($serviceData['name'], $names, true)) {
                        $errors[] = "{$prefix}: Nom dupliqué: {$serviceData['name']}";
                    }
                    $names[] = $serviceData['name'];
                }

                if (isset($serviceData['api_key'])) {
                    // Vérification de l'unicité de l'API key
                    if (in_array($serviceData['api_key'], $apiKeys, true)) {
                        $errors[] = "{$prefix}: API key dupliquée: {$serviceData['api_key']}";
                    }
                    $apiKeys[] = $serviceData['api_key'];

                    // Vérification du format de l'API key
                    if (!$this->validateApiKeyFormat($serviceData['api_key'])) {
                        $errors[] = "{$prefix}: Format d'API key invalide: {$serviceData['api_key']}";
                    }
                }

                // Vérification des types de données
                if (isset($serviceData['is_active']) && !is_bool($serviceData['is_active'])) {
                    $errors[] = "{$prefix}: 'is_active' doit être un booléen";
                }

                if (isset($serviceData['allowed_origins']) && !is_array($serviceData['allowed_origins'])) {
                    $errors[] = "{$prefix}: 'allowed_origins' doit être un tableau";
                }

                if (
                    isset($serviceData['rate_limit_per_minute']) && (
                        !is_int($serviceData['rate_limit_per_minute']) ||
                        $serviceData['rate_limit_per_minute'] < 0
                    )
                ) {
                    $errors[] = "{$prefix}: 'rate_limit_per_minute' doit être un entier positif";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Erreur lors de la validation de la configuration: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Vérifie si une chaîne est un UUID valide
     */
    private function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }
}
