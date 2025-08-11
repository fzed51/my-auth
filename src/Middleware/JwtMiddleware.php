<?php

declare(strict_types=1);

namespace MyAuth\Middleware;

use MyAuth\Service\JwtService;
use MyAuth\Exception\AuthException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class JwtMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Extraire le token JWT du header Authorization
            $token = $this->extractTokenFromRequest($request);
            
            if (!$token) {
                return $this->createErrorResponse('Token JWT manquant', 401);
            }

            // Valider le token
            $payload = $this->jwtService->validateToken($token);

            // Ajouter les informations utilisateur à la requête
            $request = $request->withAttribute('jwt_payload', $payload);
            $request = $request->withAttribute('user_id', (int)$payload['user_id']);
            $request = $request->withAttribute('user_email', $payload['email'] ?? null);
            $request = $request->withAttribute('jwt_token', $token);

            // Vérifier si le token expire bientôt (optionnel : ajouter un header d'avertissement)
            if ($this->jwtService->isTokenExpiringSoon($token, 10)) {
                $request = $request->withAttribute('token_expiring_soon', true);
            }

            return $handler->handle($request);

        } catch (AuthException $e) {
            return $this->createErrorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return $this->createErrorResponse('Token invalide', 401);
        }
    }

    /**
     * Extrait le token JWT de la requête
     */
    private function extractTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return null;
        }

        // Format attendu: "Bearer <token>"
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
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
            'code' => $statusCode,
            'timestamp' => date('c')
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
