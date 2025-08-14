<?php

declare(strict_types=1);

namespace MyAuth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware CORS (Cross-Origin Resource Sharing)
 * 
 * Ce middleware gère les en-têtes CORS pour permettre les requêtes
 * cross-origin depuis les applications front-end autorisées.
 * 
 * @package MyAuth\Middleware
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        array $allowedHeaders = [
            'X-Requested-With',
            'Content-Type',
            'Accept',
            'Origin',
            'Authorization',
            'X-API-Key'
        ],
        bool $allowCredentials = true,
        int $maxAge = 86400 // 24 heures
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
        $this->maxAge = $maxAge;
    }

    /**
     * Traite la requête et ajoute les en-têtes CORS appropriés
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        
        // Récupérer l'origine de la requête
        $origin = $request->getHeaderLine('Origin');
        
        // Traiter la requête
        $response = $handler->handle($request);
        
        // Ajouter les en-têtes CORS
        $response = $this->addCorsHeaders($response, $origin, $request);
        
        return $response;
    }

    /**
     * Ajoute les en-têtes CORS à la réponse
     */
    private function addCorsHeaders(
        ResponseInterface $response,
        string $origin,
        ServerRequestInterface $request
    ): ResponseInterface {
        
        // Déterminer l'origine autorisée
        $allowedOrigin = $this->getAllowedOrigin($origin);
        
        // En-têtes CORS de base
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        
        // Ajouter les credentials si autorisés
        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        // En-têtes additionnels pour les requêtes préflight
        if ($request->getMethod() === 'OPTIONS') {
            $requestMethod = $request->getHeaderLine('Access-Control-Request-Method');
            $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');
            
            if ($requestMethod) {
                $response = $response->withHeader('Access-Control-Allow-Methods', $requestMethod);
            }
            
            if ($requestHeaders) {
                $response = $response->withHeader('Access-Control-Allow-Headers', $requestHeaders);
            }
        }
        
        return $response;
    }

    /**
     * Détermine l'origine autorisée basée sur la configuration
     */
    private function getAllowedOrigin(string $origin): string
    {
        // Si on autorise toutes les origines
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }
        
        // Si l'origine spécifique est autorisée
        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }
        
        // Vérification avec des patterns (ex: *.example.com)
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($this->matchesOriginPattern($origin, $allowedOrigin)) {
                return $origin; // Retourner l'origine réelle, pas le pattern
            }
        }
        
        // Par défaut, retourner la première origine autorisée
        return $this->allowedOrigins[0] ?? '*';
    }

    /**
     * Vérifie si une origine correspond à un pattern
     */
    private function matchesOriginPattern(string $origin, string $pattern): bool
    {
        // Convertir le pattern en regex (ex: *.example.com -> /^.*\.example\.com$/)
        if (strpos($pattern, '*') !== false) {
            // Échapper les caractères spéciaux et remplacer * par .*
            $escapedPattern = preg_quote($pattern, '/');
            $regex = '/^' . str_replace('\*', '.*', $escapedPattern) . '$/i';
            return preg_match($regex, $origin) === 1;
        }
        
        return false;
    }

    /**
     * Factory method pour créer une instance avec configuration depuis l'environnement
     */
    public static function fromEnvironment(): self
    {
        $allowedOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
        $allowedMethods = $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,DELETE,PATCH,OPTIONS';
        $allowedHeaders = $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization,X-API-Key,X-Requested-With,Accept,Origin';
        $allowCredentials = filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $maxAge = (int) ($_ENV['CORS_MAX_AGE'] ?? 86400);

        return new self(
            $allowedOrigins === '*' ? ['*'] : explode(',', $allowedOrigins),
            explode(',', $allowedMethods),
            explode(',', $allowedHeaders),
            $allowCredentials,
            $maxAge
        );
    }

    /**
     * Factory method pour une configuration de développement permissive
     */
    public static function forDevelopment(): self
    {
        return new self(
            ['*'],
            ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            [
                'X-Requested-With',
                'Content-Type',
                'Accept',
                'Origin',
                'Authorization',
                'X-API-Key'
            ],
            true,
            86400
        );
    }

    /**
     * Factory method pour une configuration de production restrictive
     */
    public static function forProduction(array $allowedOrigins): self
    {
        return new self(
            $allowedOrigins,
            ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            [
                'Content-Type',
                'Authorization',
                'X-API-Key'
            ],
            true,
            3600 // 1 heure en production
        );
    }
}
