<?php

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Repository\ServiceRepository;
use MyAuth\Exception\AuthException;

class ServiceAuthService
{
    private ServiceRepository $serviceRepository;

    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Authentifie un service par son API key
     */
    public function authenticateService(string $apiKey): array
    {
        if (empty($apiKey)) {
            throw new AuthException('API key manquante', 401);
        }

        $service = $this->serviceRepository->findByApiKey($apiKey);

        if (!$service) {
            throw new AuthException('API key invalide', 401);
        }

        if (!$service['is_active']) {
            throw new AuthException('Service désactivé', 403);
        }

        return $service;
    }

    /**
     * Vérifie si un service a une permission spécifique
     */
    public function hasPermission(array $service, string $permission): bool
    {
        return $this->serviceRepository->hasPermission($service, $permission);
    }

    /**
     * Vérifie les limites de taux pour un service
     */
    public function checkRateLimit(array $service, string $type = 'requests_per_minute'): ?int
    {
        return $this->serviceRepository->checkRateLimit($service, $type);
    }

    /**
     * Valide qu'un service peut accéder à une route spécifique
     */
    public function validateServiceAccess(array $service, string $route, string $method = 'GET'): bool
    {
        // Mapper les routes vers les permissions
        $routePermissions = $this->getRoutePermissions();

        $routeKey = strtoupper($method) . ' ' . $route;

        if (!isset($routePermissions[$routeKey])) {
            // Route non protégée ou permission par défaut
            return true;
        }

        $requiredPermission = $routePermissions[$routeKey];
        return $this->hasPermission($service, $requiredPermission);
    }

    /**
     * Obtient la configuration des permissions par route
     */
    private function getRoutePermissions(): array
    {
        return [
            'POST /api/auth/register' => 'auth:register',
            'POST /api/auth/login' => 'auth:login',
            'GET /api/auth/verify-email' => 'auth:verify',
            'POST /api/auth/logout' => 'auth:logout',
            'POST /api/auth/refresh' => 'auth:refresh',
            'GET /api/user/profile' => 'user:read',
            'PUT /api/user/profile' => 'user:write',
            'DELETE /api/user/profile' => 'user:delete',
            'GET /api/admin/users' => 'admin:users:read',
            'GET /api/admin/stats' => 'admin:stats:read'
        ];
    }

    /**
     * Obtient les informations d'un service par son nom
     */
    public function getServiceByName(string $name): ?array
    {
        return $this->serviceRepository->findByName($name);
    }

    /**
     * Obtient tous les services actifs
     */
    public function getAllActiveServices(): array
    {
        return $this->serviceRepository->findAllActive();
    }

    /**
     * Valide la structure d'un service
     */
    public function validateService(array $service): bool
    {
        return $this->serviceRepository->validateService($service);
    }

    /**
     * Extrait l'API key depuis les headers de la requête
     */
    public function extractApiKeyFromHeaders(array $headers): ?string
    {
        // Chercher dans différents formats possibles
        $possibleHeaders = [
            'X-API-Key',
            'X-Api-Key',
            'API-Key',
            'Authorization'
        ];

        foreach ($possibleHeaders as $header) {
            if (isset($headers[$header])) {
                $value = is_array($headers[$header]) ? $headers[$header][0] : $headers[$header];

                // Pour Authorization, extraire la partie après "Bearer " ou "ApiKey "
                if ($header === 'Authorization') {
                    if (preg_match('/^Bearer\s+(.+)$/i', $value, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/^ApiKey\s+(.+)$/i', $value, $matches)) {
                        return $matches[1];
                    }
                } else {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Génère les statistiques d'utilisation des services
     */
    public function getServiceUsageStats(): array
    {
        $services = $this->getAllActiveServices();
        $stats = [];

        foreach ($services as $service) {
            $stats[] = [
                'name' => $service['name'],
                'description' => $service['description'] ?? '',
                'permissions' => $service['permissions'] ?? [],
                'rate_limits' => $service['rate_limit'] ?? [],
                'is_active' => $service['is_active']
            ];
        }

        return $stats;
    }

    /**
     * Vérifie si un service peut effectuer une action sur une ressource
     */
    public function canAccessResource(array $service, string $resource, string $action): bool
    {
        $permission = $resource . ':' . $action;
        return $this->hasPermission($service, $permission);
    }

    /**
     * Obtient les permissions d'un service sous forme de liste
     */
    public function getServicePermissions(array $service): array
    {
        return $service['permissions'] ?? [];
    }

    /**
     * Vérifie si un service a des permissions d'administrateur
     */
    public function isAdminService(array $service): bool
    {
        return $this->hasPermission($service, 'admin:*') || $this->hasPermission($service, '*');
    }

    /**
     * Recharge la configuration des services (utile pour les tests ou le rechargement à chaud)
     */
    public function reloadServices(): void
    {
        $this->serviceRepository->reload();
    }
}
