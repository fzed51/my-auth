<?php

declare(strict_types=1);

namespace MyAuth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class ServiceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Récupérer les informations du service depuis la requête (ajoutées par ApiKeyMiddleware)
        $service = $request->getAttribute('service');
        
        if ($service) {
            // Ajouter des métadonnées supplémentaires ou effectuer des validations
            $request = $request->withAttribute('service_id', $service['id'] ?? null);
            $request = $request->withAttribute('service_description', $service['description'] ?? '');
            $request = $request->withAttribute('service_rate_limits', $service['rate_limit'] ?? []);
            
            // Log de l'utilisation du service (optionnel)
            $this->logServiceUsage($service, $request);
            
            // Vérification du rate limiting (optionnel)
            if (!$this->checkRateLimit($service, $request)) {
                return $this->createRateLimitResponse();
            }
        }

        // Ajouter des headers de réponse communs
        $response = $handler->handle($request);
        
        return $this->addServiceHeaders($response, $service);
    }

    /**
     * Log l'utilisation du service
     */
    private function logServiceUsage(array $service, ServerRequestInterface $request): void
    {
        // Dans un vrai projet, on utiliserait un logger approprié
        $logData = [
            'service_name' => $service['name'],
            'route' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'timestamp' => date('c'),
            'ip' => $this->getClientIp($request)
        ];
        
        // Log en mode développement
        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log('Service Usage: ' . json_encode($logData));
        }
    }

    /**
     * Vérifie les limites de taux (implémentation basique)
     */
    private function checkRateLimit(array $service, ServerRequestInterface $request): bool
    {
        // Cette implémentation est basique et ne persiste pas les données
        // Dans un vrai projet, on utiliserait Redis ou une base de données
        
        $rateLimits = $service['rate_limit'] ?? [];
        
        if (empty($rateLimits)) {
            return true; // Pas de limite
        }

        // Pour l'instant, on accepte toutes les requêtes
        // Une vraie implémentation vérifierait les compteurs dans un store persistant
        return true;
    }

    /**
     * Crée une réponse de limite de taux dépassée
     */
    private function createRateLimitResponse(): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => true,
            'message' => 'Rate limit exceeded',
            'code' => 429
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(429);
    }

    /**
     * Ajoute des headers liés au service dans la réponse
     */
    private function addServiceHeaders(ResponseInterface $response, ?array $service): ResponseInterface
    {
        $response = $response->withHeader('X-API-Version', '1.0');
        
        if ($service) {
            $response = $response->withHeader('X-Service-Name', $service['name']);
        }
        
        // Headers de sécurité
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        $response = $response->withHeader('X-Frame-Options', 'DENY');
        $response = $response->withHeader('X-XSS-Protection', '1; mode=block');
        
        return $response;
    }

    /**
     * Récupère l'IP du client
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        // Vérifier les headers de proxy
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                // Prendre la première IP si plusieurs sont présentes
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}
