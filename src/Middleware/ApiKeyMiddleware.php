<?php

declare(strict_types=1);

namespace MyAuth\Middleware;

use MyAuth\Service\ServiceAuthService;
use MyAuth\Exception\AuthException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class ApiKeyMiddleware implements MiddlewareInterface
{
    private ServiceAuthService $serviceAuthService;

    public function __construct(ServiceAuthService $serviceAuthService)
    {
        $this->serviceAuthService = $serviceAuthService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Extraire l'API key des headers
            $apiKey = $this->serviceAuthService->extractApiKeyFromHeaders($request->getHeaders());
            
            if (!$apiKey) {
                return $this->createErrorResponse('API key manquante', 401);
            }

            // Authentifier le service
            $service = $this->serviceAuthService->authenticateService($apiKey);

            // Vérifier l'accès à la route
            $route = $request->getUri()->getPath();
            $method = $request->getMethod();
            
            if (!$this->serviceAuthService->validateServiceAccess($service, $route, $method)) {
                return $this->createErrorResponse('Accès non autorisé pour ce service', 403);
            }

            // Ajouter les informations du service à la requête
            $request = $request->withAttribute('service', $service);
            $request = $request->withAttribute('service_name', $service['name']);
            $request = $request->withAttribute('service_permissions', $service['permissions'] ?? []);

            return $handler->handle($request);

        } catch (AuthException $e) {
            return $this->createErrorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return $this->createErrorResponse('Erreur d\'authentification du service', 500);
        }
    }

    /**
     * Crée une réponse d'erreur JSON
     */
    private function createErrorResponse(string $message, int $statusCode): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => true,
            'message' => $message,
            'code' => $statusCode
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
