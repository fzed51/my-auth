<?php

declare(strict_types=1);

namespace MyAuth\Controller;

use MyAuth\Service\AuthService;
use MyAuth\Service\UserService;
use MyAuth\Exception\AuthException;
use MyAuth\Exception\ValidationException;
use MyAuth\Exception\UserNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class AuthController
{
    private AuthService $authService;
    private UserService $userService;

    public function __construct(AuthService $authService, UserService $userService)
    {
        $this->authService = $authService;
        $this->userService = $userService;
    }

    /**
     * Inscription d'un nouvel utilisateur
     * POST /api/auth/register
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'JSON invalide'
                ], 400);
            }

            // Créer l'utilisateur
            $user = $this->userService->createUser($data);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Utilisateur créé avec succès. Veuillez vérifier votre email.',
                'user' => $user->toArray()
            ], 201);

        } catch (ValidationException $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Données invalides',
                'errors' => $e->getErrors()
            ], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de la création du compte'
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     * POST /api/auth/login
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'JSON invalide'
                ], 400);
            }

            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $ipAddress = $this->getClientIp($request);
            $userAgent = $request->getHeaderLine('User-Agent');

            // Authentifier
            $authResult = $this->authService->login($email, $password, $ipAddress, $userAgent);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => $authResult
            ]);

        } catch (ValidationException $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Données invalides',
                'errors' => $e->getErrors()
            ], 400);
        } catch (AuthException $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de la connexion'
            ], 500);
        }
    }

    /**
     * Vérification d'email
     * GET /api/auth/verify-email/{token}
     */
    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $token = $args['token'] ?? '';
            
            if (empty($token)) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Token manquant'
                ], 400);
            }

            $verified = $this->userService->verifyEmail($token);

            if ($verified) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Email vérifié avec succès'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Token invalide ou expiré'
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de la vérification'
            ], 500);
        }
    }

    /**
     * Déconnexion
     * POST /api/auth/logout
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $token = $request->getAttribute('jwt_token');
            
            if (!$token) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Token manquant'
                ], 400);
            }

            $this->authService->logout($token);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Déconnexion de tous les appareils
     * POST /api/auth/logout-all
     */
    public function logoutAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = $request->getAttribute('user_id');
            
            if (!$userId) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Utilisateur non identifié'
                ], 400);
            }

            $this->authService->logoutAllDevices($userId);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Rafraîchissement du token
     * POST /api/auth/refresh
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $token = $request->getAttribute('jwt_token');
            
            if (!$token) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Token manquant'
                ], 400);
            }

            $refreshResult = $this->authService->refreshToken($token);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Token rafraîchi avec succès',
                'data' => $refreshResult
            ]);

        } catch (AuthException $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => $e->getMessage()
            ], $e->getCode());
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors du rafraîchissement'
            ], 500);
        }
    }

    /**
     * Profil utilisateur
     * GET /api/auth/me
     */
    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = $request->getAttribute('jwt_payload');
            
            if (!$payload) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Token invalide'
                ], 401);
            }

            // Récupérer les informations à jour de l'utilisateur
            $authResult = $this->authService->validateToken($request->getAttribute('jwt_token'));

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'user' => $authResult['user'],
                    'tokenExpiringSoon' => $request->getAttribute('token_expiring_soon', false)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de la récupération du profil'
            ], 500);
        }
    }

    /**
     * Renvoie un email de vérification
     * POST /api/auth/resend-verification
     */
    public function resendVerification(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $email = $data['email'] ?? '';

            if (empty($email)) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Email manquant'
                ], 400);
            }

            // Trouver l'utilisateur via le repository
            // Pour simplifier, on renvoie toujours le même message
            // Dans un vrai projet, on injecterait UserRepository
            /*
            if (!$user) {
                // Ne pas révéler si l'email existe ou non
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Si cet email existe, un nouveau lien de vérification a été envoyé'
                ]);
            }

            if ($user->isEmailVerified()) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'message' => 'Email déjà vérifié'
                ], 400);
            }

            $this->userService->sendEmailVerification($user);
            */

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Email de vérification envoyé'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'message' => 'Erreur lors de l\'envoi'
            ], 500);
        }
    }

    /**
     * Utilitaire pour créer une réponse JSON
     */
    private function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Récupère l'IP du client
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
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
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}
