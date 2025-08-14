# Étape 3 : Authentification des Services (API Key)

## Vue d'ensemble

L'étape 3 implémente un système complet d'authentification par clé API pour les services externes. Ce système permet aux applications autorisées de s'authentifier auprès de votre API en utilisant des clés API configurées.

## Architecture implémentée

### Composants principaux

1. **Entity/Service** - Représentation d'un service autorisé
2. **Repository/ServiceRepository** - Gestion des services via configuration JSON
3. **Service/ServiceAuthService** - Logique métier d'authentification
4. **Middleware/ApiKeyMiddleware** - Middleware PSR-15 pour Slim Framework
5. **Exceptions spécialisées** - Gestion d'erreurs typées

## Fonctionnalités

### 1. Authentification par API Key

Le système supporte trois méthodes d'envoi de la clé API :

```bash
# 1. Header X-API-Key (recommandé)
curl -H "X-API-Key: votre-cle-api" https://api.example.com/protected

# 2. Authorization Bearer
curl -H "Authorization: Bearer votre-cle-api" https://api.example.com/protected

# 3. Paramètre de requête (déconseillé en production)
curl "https://api.example.com/protected?api_key=votre-cle-api"
```

### 2. Validation des origines

Le système valide automatiquement l'origine des requêtes :

```json
{
  "allowed_origins": [
    "https://app.example.com",     // Correspondance exacte
    "*.example.com",               // Wildcard pour sous-domaines
    "https://localhost:3000"       // Développement local
  ]
}
```

### 3. Gestion des routes publiques

Configurez des routes qui ne nécessitent pas d'authentification :

```php
// Routes publiques par défaut
$middleware = ApiKeyMiddleware::withDefaultPublicRoutes($authService, $responseFactory);

// Configuration personnalisée
$middleware = new ApiKeyMiddleware($authService, $responseFactory, [
    '/health',
    '/status',
    '/public/*'
]);

// Mode strict (pas de routes publiques)
$middleware = ApiKeyMiddleware::strict($authService, $responseFactory);
```

## Configuration

### Fichier de services (config/services.json)

```json
{
  "services": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "name": "Frontend Application",
      "api_key": "frontend-api-key-1234567890abcdef",
      "description": "Application frontend principale",
      "is_active": true,
      "allowed_origins": [
        "https://myapp.com",
        "*.myapp.com"
      ],
      "rate_limit_per_minute": 1000
    }
  ]
}
```

### Configuration du container DI (config/container.php)

```php
// Services d'authentification
ServiceRepository::class => function() {
    return new ServiceRepository(__DIR__ . '/services.json');
},

ServiceAuthService::class => DI\autowire(),

// Middleware
ApiKeyMiddleware::class => function (Container $container) {
    return ApiKeyMiddleware::withDefaultPublicRoutes(
        $container->get(ServiceAuthService::class),
        $container->get(ResponseFactoryInterface::class)
    );
},
```

## Utilisation dans l'application

### 1. Application du middleware

```php
// Dans votre configuration Slim
$app->add(ApiKeyMiddleware::class);

// Ou pour des routes spécifiques
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->get('/users', [UserController::class, 'list']);
    $group->post('/users', [UserController::class, 'create']);
})->add(ApiKeyMiddleware::class);
```

### 2. Accès aux informations du service authentifié

```php
class UserController
{
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        // Récupération du service authentifié
        $service = ApiKeyMiddleware::getAuthenticatedService($request);
        $serviceId = ApiKeyMiddleware::getServiceId($request);
        $serviceName = ApiKeyMiddleware::getServiceName($request);

        // Votre logique métier
        // ...
    }
}
```

### 3. Génération de tokens temporaires

```php
class TokenController
{
    public function __construct(private ServiceAuthService $authService) {}

    public function createTemporaryToken(ServerRequestInterface $request): ResponseInterface
    {
        $service = ApiKeyMiddleware::getAuthenticatedService($request);
        $temporaryToken = $this->authService->generateTemporaryToken($service);

        return new JsonResponse(['token' => $temporaryToken]);
    }
}
```

## Gestion d'erreurs

Le système retourne des codes HTTP appropriés :

- **401 Unauthorized** - Clé API manquante ou invalide
- **403 Forbidden** - Service inactif ou origine non autorisée
- **404 Not Found** - Service non trouvé
- **500 Internal Server Error** - Erreur serveur

### Réponses d'erreur

```json
{
  "error": "Authentication required",
  "message": "API key is required",
  "code": 401
}
```

## Tests

Le système inclut une suite de tests complète :

```bash
# Lancer tous les tests
composer run test

# Tests spécifiques
vendor/bin/phpunit src/Entity/ServiceTest.php
vendor/bin/phpunit src/Repository/ServiceRepositoryTest.php
vendor/bin/phpunit src/Service/ServiceAuthServiceTest.php
vendor/bin/phpunit src/Middleware/ApiKeyMiddlewareTest.php
```

## Sécurité

### Bonnes pratiques implémentées

1. **Validation stricte** - Format et longueur des clés API
2. **Vérification d'origine** - Protection CORS intégrée
3. **Services inactifs** - Désactivation temporaire sans suppression
4. **Tokens temporaires** - Alternative sécurisée pour certains cas
5. **Logs d'audit** - Traçabilité des authentifications

### Recommandations

- Utilisez des clés API longues et aléatoires (minimum 32 caractères)
- Implémentez une rotation régulière des clés
- Surveillez les tentatives d'authentification échouées
- Utilisez HTTPS en production
- Configurez des origines spécifiques (évitez `*`)

## Performance

### Optimisations implémentées

- **Cache en mémoire** - Les services sont mis en cache lors du premier chargement
- **Validation rapide** - Vérifications optimisées
- **Chargement paresseux** - Configuration chargée à la demande

### Monitoring

```php
// Statistiques d'authentification
$stats = $authService->getAuthenticationStats($service);

// Exemple de réponse
[
    'service_id' => '123e4567-e89b-12d3-a456-426614174000',
    'service_name' => 'Frontend Application',
    'last_authentication' => '2025-08-14T15:08:30Z',
    'total_requests' => 1250,
    'is_active' => true
]
```

## Maintenance

### Gestion des services

```bash
# Vider le cache après modification de services.json
# Le cache se recharge automatiquement au prochain accès
```

### Logs

Les authentifications sont loggées automatiquement :

```
[2025-08-14 15:08:30] INFO: API Key authentication successful [service: Frontend Application]
[2025-08-14 15:08:31] WARNING: Invalid API key attempt [key: invalid-key-***]
[2025-08-14 15:08:32] ERROR: Origin not allowed [service: Mobile App, origin: https://malicious.com]
```

## Évolution

Cette implémentation pose les bases pour :

- Rate limiting par service
- Métriques d'utilisation avancées
- Système de quotas
- Authentification multi-facteurs pour services critiques
- Intégration avec des systèmes de gestion de clés externes

## Conformité

L'implémentation respecte :

- **PSR-15** - HTTP Server Request Handlers
- **PSR-7** - HTTP Message Interface
- **PSR-12** - Extended Coding Style
- **PHPStan Level Max** - Analyse statique stricte
- **Architecture Clean** - Séparation des responsabilités
