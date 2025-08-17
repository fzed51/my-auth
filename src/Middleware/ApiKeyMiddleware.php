<?php

/**
 * Middleware d'authentification par API Key pour MyAuth
 *
 * @package MyAuth\Middleware
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use MyAuth\Service\ServiceAuthService;
use MyAuth\Entity\Service;
use MyAuth\Exception\AuthenticationException;
use MyAuth\Exception\AuthorizationException;

/**
 * Middleware d'authentification par API Key
 *
 * Vérifie la présence et la validité de l'API key dans le header X-API-Key
 * Injecte le service authentifié dans les attributs de la requête
 */
class ApiKeyMiddleware implements MiddlewareInterface
{
    private ServiceAuthService $serviceAuthService;
    private ResponseFactoryInterface $responseFactory;
    private array $publicRoutes;

    /**
     * Constructeur du middleware d'authentification API Key
     *
     * @param ServiceAuthService $serviceAuthService Service d'authentification
     * @param ResponseFactoryInterface $responseFactory Factory de réponses HTTP
     * @param array $publicRoutes Routes publiques sans authentification
     */
    /**

     * Constructor

     */

    public function __construct(
        ServiceAuthService $serviceAuthService,
        ResponseFactoryInterface $responseFactory,
        array $publicRoutes = []
    ) {

        $this->serviceAuthService = $serviceAuthService;
        $this->responseFactory = $responseFactory;
        $this->publicRoutes = $publicRoutes;
    }

    /**
     * Traite la requête HTTP et vérifie l'authentification API Key
     *
     * @param ServerRequestInterface $request Requête HTTP
     * @param RequestHandlerInterface $handler Gestionnaire de requête
     * @return ResponseInterface Réponse HTTP
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $uri = $request->getUri();
        $path = $uri->getPath();

        // Vérifier si la route est publique
        if ($this->isPublicRoute($path)) {
            return $handler->handle($request);
        }

        try {
            // Récupération de l'API key
            $apiKey = $this->extractApiKey($request);
            if ($apiKey === null) {
                return $this->createErrorResponse(
                    401,
                    'API key manquante',
                    'API_KEY_MISSING'
                );
            }

            // Authentification du service
            $service = $this->serviceAuthService->authenticateByApiKey($apiKey);

            // Validation de l'origine si présente
            $origin = $request->getHeaderLine('Origin');
            if (!empty($origin)) {
                $this->serviceAuthService->validateOrigin($service, $origin);
            }

            // Injection du service dans la requête
            $request = $request->withAttribute(
                'authenticated_service',
                $service
            );
            $request = $request->withAttribute('service_id', $service->getId());
            $request = $request->withAttribute(
                'service_name',
                $service->getName()
            );

            return $handler->handle($request);
        } catch (AuthenticationException $e) {
            return $this->createErrorResponse(
                401,
                $e->getMessage(),
                'AUTHENTICATION_FAILED'
            );
        } catch (AuthorizationException $e) {
            return $this->createErrorResponse(
                403,
                $e->getMessage(),
                'AUTHORIZATION_FAILED'
            );
        } catch (\Exception $e) {
            // Log de l'erreur pour debug
            error_log("ApiKeyMiddleware error: " . $e->getMessage());
            return $this->createErrorResponse(
                500,
                'Erreur interne du serveur',
                'INTERNAL_SERVER_ERROR'
            );
        }
    }

    /**
     * Extrait l'API key de la requête
     */
    private function extractApiKey(ServerRequestInterface $request): ?string
    {
        // Vérification dans le header X-API-Key
        $apiKey = $request->getHeaderLine('X-API-Key');
        if (!empty($apiKey)) {
            return $apiKey;
        }

        // Vérification dans le header Authorization (Bearer)
        $authorization = $request->getHeaderLine('Authorization');
        if (
            !empty($authorization)
            && str_starts_with($authorization, 'Bearer ')
        ) {
            return substr($authorization, 7);
        }

        // Vérification dans les paramètres de requête (debug uniquement)
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['api_key']) && !empty($queryParams['api_key'])) {
            return $queryParams['api_key'];
        }

        return null;
    }

    /**
     * Vérifie si une route est publique
     */
    private function isPublicRoute(string $path): bool
    {
        error_log(
            "Checking if path '{$path}' is public against routes: " .
            json_encode($this->publicRoutes)
        );
        foreach ($this->publicRoutes as $publicRoute) {
            if ($this->matchRoute($path, $publicRoute)) {
                error_log(
                    "Path '{$path}' matches public route '{$publicRoute}'"
                );
                return true;
            }
        }
        error_log("Path '{$path}' is NOT public");
        return false;
    }

    /**
     * Vérifie si un chemin correspond à un pattern de route
     */
    private function matchRoute(string $path, string $pattern): bool
    {
        // D'abord remplacer * par un placeholder temporaire
        $tempPattern = str_replace('*', '__WILDCARD__', $pattern);
        // Puis échapper les caractères regex
        $escaped = preg_quote($tempPattern, '/');
        // Puis remplacer le placeholder par .*
        $regex = str_replace('__WILDCARD__', '.*', $escaped);

        $matches = preg_match("/^{$regex}$/", $path) === 1;
        error_log(
            "Matching path '{$path}' against pattern '{$pattern}' " .
            "(regex: ^{$regex}$) => " . ($matches ? 'YES' : 'NO')
        );
        return $matches;
    }

    /**
     * Crée une réponse d'erreur JSON
     */
    private function createErrorResponse(
        int $statusCode,
        string $message,
        string $errorCode
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse($statusCode);

        $errorData = [
            'error' => true,
            'message' => $message,
            'error_code' => $errorCode,
            'timestamp' => date('c'),
        ];

        $jsonContent = json_encode($errorData, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            throw new \RuntimeException(
                'Failed to encode error response to JSON'
            );
        }

        $response->getBody()->write($jsonContent);

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Factory method pour créer le middleware avec des routes publiques
     * par défaut
     */
    public static function withDefaultPublicRoutes(
        ServiceAuthService $serviceAuthService,
        ResponseFactoryInterface $responseFactory
    ): self {
        $defaultPublicRoutes = [
            '/',
            '/health',
            '/debug-routes',
            '/api/auth/test',
            '/api/auth/register',
            '/api/auth/verify-email/*',
            '/api/auth/resend-verification',
            '/api/docs*',
            '/api/status*',
        ];

        return new self(
            $serviceAuthService,
            $responseFactory,
            $defaultPublicRoutes
        );
    }

    /**
     * Factory method pour un middleware strict (aucune route publique)
     */
    public static function strict(
        ServiceAuthService $serviceAuthService,
        ResponseFactoryInterface $responseFactory
    ): self {
        return new self($serviceAuthService, $responseFactory, []);
    }

    /**
     * Récupère le service authentifié depuis la requête
     */
    public static function getAuthenticatedService(
        ServerRequestInterface $request
    ): ?Service {
        $service = $request->getAttribute('authenticated_service');
        return $service instanceof Service ? $service : null;
    }

    /**
     * Récupère l'ID du service authentifié depuis la requête
     */
    public static function getServiceId(
        ServerRequestInterface $request
    ): ?string {
        $serviceId = $request->getAttribute('service_id');
        return is_string($serviceId) ? $serviceId : null;
    }

    /**
     * Récupère le nom du service authentifié depuis la requête
     */
    public static function getServiceName(
        ServerRequestInterface $request
    ): ?string {
        $serviceName = $request->getAttribute('service_name');
        return is_string($serviceName) ? $serviceName : null;
    }
}
