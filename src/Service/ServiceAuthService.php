<?php

/**
 * Service Class for MyAuth
 *
 * @package MyAuth\Service
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Entity\Service;
use MyAuth\Repository\ServiceRepository;
use MyAuth\Exception\AuthenticationException;
use MyAuth\Exception\AuthorizationException;
use MyAuth\Exception\ServiceNotFoundException;

/**
 * Service d'authentification des services par API Key
 */
class ServiceAuthService extends AbstractService
{
    private ServiceRepository $serviceRepository;

    /**


     * Constructor


     */


    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Authentifie un service par son API key
     *
     * @throws AuthenticationException Si l'API key est invalide
     * @throws AuthorizationException Si le service n'est pas actif
     */
    public function authenticateByApiKey(string $apiKey): Service
    {
        // Validation du format de l'API key
        if (!$this->serviceRepository->validateApiKeyFormat($apiKey)) {
            throw new AuthenticationException('Format d\'API key invalide');
        }

        // Recherche du service
        $service = $this->serviceRepository->findByApiKey($apiKey);
        if ($service === null) {
            throw new AuthenticationException('API key non reconnue');
        }

        // Vérification que le service est actif
        if (!$service->isActive()) {
            throw new AuthorizationException('Service désactivé');
        }

        return $service;
    }

    /**
     * Vérifie si un service est autorisé à accéder depuis une origine donnée
     */
    public function isOriginAllowed(Service $service, string $origin): bool
    {
        return $service->isOriginAllowed($origin);
    }

    /**
     * Valide qu'un service est autorisé à faire une requête depuis une origine
     *
     * @throws AuthorizationException Si l'origine n'est pas autorisée
     */
    public function validateOrigin(Service $service, string $origin): void
    {
        if (!$this->isOriginAllowed($service, $origin)) {
            throw new AuthorizationException(
                "Origine non autorisée pour le service '{$service->getName()}': {$origin}"
            );
        }
    }

    /**
     * Récupère les informations d'un service par son ID
     *
     * @throws ServiceNotFoundException Si le service n'existe pas
     */
    public function getServiceById(string $serviceId): Service
    {
        $service = $this->serviceRepository->findById($serviceId);
        if ($service === null) {
            throw new ServiceNotFoundException("Service non trouvé: {$serviceId}");
        }

        return $service;
    }

    /**
     * Récupère tous les services actifs
     */
    /**

     * Get the activeServices

     *

     * @return array

     */

    public function getActiveServices(): array
    {
        return $this->serviceRepository->findAllActive();
    }

    /**
     * Vérifie la santé de la configuration des services
     */
    public function validateServicesConfiguration(): array
    {
        return $this->serviceRepository->validateConfiguration();
    }

    /**
     * Rafraîchit le cache des services
     */
    public function refreshServicesCache(): void
    {
        $this->serviceRepository->clearCache();
    }

    /**
     * Génère un token temporaire pour un service (pour debug/monitoring)
     */
    public function generateTemporaryToken(Service $service, int $durationMinutes = 60): string
    {
        $payload = [
            'service_id' => $service->getId(),
            'service_name' => $service->getName(),
            'issued_at' => time(),
            'expires_at' => time() + ($durationMinutes * 60),
            'type' => 'temporary_service_token'
        ];

        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            throw new \RuntimeException('Failed to encode payload to JSON');
        }

        return base64_encode($jsonPayload);
    }

    /**
     * Valide un token temporaire
     */
    public function validateTemporaryToken(string $token): ?array
    {
        try {
            $decoded = base64_decode($token, true);
            if ($decoded === false) {
                return null;
            }

            $payload = json_decode($decoded, true);
            if (!is_array($payload)) {
                return null;
            }

            // Vérification de l'expiration
            if (!isset($payload['expires_at']) || !is_int($payload['expires_at']) || $payload['expires_at'] < time()) {
                return null;
            }

            // Vérification du type
            if (!isset($payload['type']) || $payload['type'] !== 'temporary_service_token') {
                return null;
            }

            return $payload;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Obtient des statistiques sur les services
     */
    /**

     * Get the servicesStatistics

     *

     * @return array

     */

    public function getServicesStatistics(): array
    {
        $allServices = $this->serviceRepository->findAll();
        $activeServices = $this->serviceRepository->findAllActive();

        $statistics = [
            'total_services' => count($allServices),
            'active_services' => count($activeServices),
            'inactive_services' => count($allServices) - count($activeServices),
            'services_by_rate_limit' => [],
            'origins_count' => 0,
        ];

        $rateLimits = [];
        $totalOrigins = 0;

        foreach ($allServices as $service) {
            $rateLimit = $service->getRateLimitPerMinute();
            $rateLimits[$rateLimit] = ($rateLimits[$rateLimit] ?? 0) + 1;
            $totalOrigins += count($service->getAllowedOrigins());
        }

        $statistics['services_by_rate_limit'] = $rateLimits;
        $statistics['origins_count'] = $totalOrigins;

        return $statistics;
    }
}
