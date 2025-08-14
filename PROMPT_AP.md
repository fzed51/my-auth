# Prompt pour Automate de Programmation - Service d'Authentification

## Instructions Générales Permanentes

Vous êtes chargé de créer un service d'authentification robuste et sécurisé. Ces instructions doivent être appliquées à chaque étape du développement :

### Technologies et Standards
- **Gestionnaire de packet** : composer
- **Framework** : Slim Framework 4
- **Injection de dépendances** : PHP-DI
- **Tests** : PhpUnit pour tous les tests unitaires
- **Qualité de code** : Validation PhpCs et PhpStan (niveau maximum)
- **Base de données** : MySQL avec PDO et requêtes préparées
- **Sécurité** : Protection obligatoire contre les injections SQL

### Documentation
  - Créer/mettre à jour un fichier `README.md` pour chaque étape.
    - Documenter les choix techniques et l'architecture
    - Inclure les instructions d'installation et d'utilisation
  -  Créer/mettre à jour un fichier `README-STEP<n>.md` pour chaque étape.
    - Documenter ce qui a été fait et ce qui reste à faire.

## Architecture du Projet

### Structure des Dossiers
```
/
├── config/
│   ├── container.php
│   ├── database.php
│   ├── services.json
│   └── jwt.php
├── database/
│   └── init-db.sql
├── src/
│   ├── Controller/
│   ├── Service/
│   ├── Repository/
│   ├── Middleware/
│   ├── Entity/
│   └── Exception/
├── tests/
│   └── Integration/
├── public/
│   └── index.php
├── composer.json
└── README.md
```

Les tests unitaire seront écrit dans le même dossier que le fichier testé : on trouve dans le même dossier le fichier `monService.php` et `monServiceTest.php`.

## Fonctionnalités à Implémenter

### 1. Base de Données (init-db.sql)

**Objectif** : Créer le schéma de base de données complet

**Tables requises** :
- `users` : Comptes utilisateurs avec statut d'activation
- `email_verifications` : Tokens de vérification d'email
- `jwt_blacklist` : Tokens JWT révoqués (optionnel pour logout)

**Rappel** :
- Les service ne sont pas enregistré en base de donnée.

**Implémentation technique** :
- Fichier `database/init-db.sql`
- Clés primaires, les clés primaires seront des uuid, index et contraintes de sécurité
- Champs de timestamps (created_at, updated_at)
- Hashage sécurisé des mots de passe (PASSWORD_DEFAULT)

**Préparation** :
- Mise en place de mySql en local à partir d'une image docker pour le developpement et les tests


### 2. Middleware Service

**Objectif** : Protection des routes et récupération des informations du service

**Implémentation technique** :
- **Middleware** : `src/Middleware/ServiceMiddleware.php`
- **Service** : `src/Service/ServiceService.php`

**Fonctionnalités** :
- Récupération de l'API key passé dans le header de la requete
- Vérification que l'API key est bien lié à un service enregistré dans le fichier `config/Service.json`
- Enregistrer dans le DI les information de ce service

### 3. Authentification des Services (API Key)

**Objectif** : Seuls les services enregistrés peuvent utiliser l'API

**Implémentation technique** :
- **Middleware** : `src/Middleware/ApiKeyMiddleware.php`
- **Repository** : `src/Repository/ServiceRepository.php`
- **Service** : `src/Service/ServiceAuthService.php`
- **Configuration** : `config/services.php`

**Fonctionnement** :
- Vérification de l'API key dans le header `X-API-Key`
- Validation contre la table `services`
- Injection du service authentifié dans la requête

### 4. Création de Compte Utilisateur

**Objectif** : Inscription avec vérification email

**Implémentation technique** :
- **Route** : `POST /api/auth/register`
- **Controller** : `src/Controller/AuthController::register()`
- **Service** : `src/Service/UserService.php`
- **Service** : `src/Service/EmailService.php`
- **Repository** : `src/Repository/UserRepository.php`
- **Entity** : `src/Entity/User.php`

**Fonctionnalités** :
- Validation des données (email unique, mot de passe fort)
- Hashage sécurisé du mot de passe
- Génération de token de vérification email
- Envoi d'email de confirmation
- Compte créé en statut "non activé"

### 5. Vérification Email

**Objectif** : Activer le compte utilisateur

**Implémentation technique** :
- **Route** : `GET /api/auth/verify-email/{token}`
- **Controller** : `src/Controller/AuthController::verifyEmail()`
- **Repository** : `src/Repository/EmailVerificationRepository.php`

### 6. Connexion Utilisateur

**Objectif** : Authentification et génération JWT

**Implémentation technique** :
- **Route** : `POST /api/auth/login`
- **Controller** : `src/Controller/AuthController::login()`
- **Service** : `src/Service/AuthService.php`
- **Service** : `src/Service/JwtService.php`

**Fonctionnalités** :
- Vérification email/mot de passe
- Vérification que le compte est activé
- Génération JWT avec payload personnalisé
- Configuration de la durée de validité

### 7. Middleware JWT

**Objectif** : Protection des routes authentifiées

**Implémentation technique** :
- **Middleware** : `src/Middleware/JwtMiddleware.php`
- **Service** : `src/Service/JwtService.php`

**Fonctionnalités** :
- Validation du token JWT
- Vérification de l'expiration
- Extraction des données utilisateur
- Gestion de la blacklist (optionnel)

## Configuration Requise

### 1. Container DI (config/container.php)
- Configuration PHP-DI 5
- Injection des dépendances
- Configuration des repositories et services

### 2. Base de Données (config/database.php)
- Configuration PDO MySQL
- Gestion des erreurs
- Pool de connexions

### 3. JWT (config/jwt.php)
- Clé secrète de signature
- Algorithme de chiffrement (HS256 recommandé)
- Durée de validité des tokens
- Issuer et audience

### 4. Services (config/services.json)
- Liste des services autorisés
- API keys associées
- Métadonnées des services
- La structure d'un service est :
    ```
    id: string (UUID),
    name: string,
    api_key : string,
    description: string,
    is_active: boolean,
    ```

## Sécurité Obligatoire

### Mesures de Protection
1. **Injections SQL** : Requêtes PDO préparées exclusivement
2. **Mots de passe** : Hashage avec `password_hash()` et `PASSWORD_DEFAULT`
3. **JWT** : Signature cryptographique et vérification d'expiration
4. **Rate Limiting** : Protection contre les attaques par force brute
5. **CORS** : Configuration appropriée pour les origines autorisées
6. **HTTPS** : Headers de sécurité appropriés

### Validation des Données
- Validation stricte des formats email
- Complexité minimale des mots de passe
- Sanitisation des entrées utilisateur
- Limitation de la taille des payloads

## Tests Unitaires Obligatoires

### Couverture Requise
- Tous les services : 100% de couverture
- Tous les repositories : Tests avec base de données en mémoire
- Tous les controllers : Tests des réponses HTTP
- Tous les middlewares : Tests des cas d'authentification

### Types de Tests
- **Unit Tests** : Logique métier isolée
- **Integration Tests** : Interaction avec la base de données
- **Functional Tests** : Tests end-to-end des API

## Livrables par Étape

### Étape 1 : Base de Données
- [ ] `database/init-db.sql` complet
- [ ] Tests de création/migration
- [ ] README avec instructions de setup
- [ ] README-STEP1 Action effectué et rest à faire

### Étape 2 : Infrastructure
- [ ] Configuration complète (DI, DB, JWT)
- [ ] Structure des dossiers
- [ ] Autoloader et dépendances
- [ ] Validation PhpCs et PhpStan
- [ ] README-STEP2 Action effectué et rest à faire

### Étape 3 : Authentification Services
- [ ] Middleware API Key
- [ ] Repository et Service
- [ ] Tests unitaires complets
- [ ] Validation PhpCs et PhpStan
- [ ] README-STEP3 Action effectué et rest à faire

### Étape 4 : Gestion Utilisateurs
- [ ] Création de compte
- [ ] Vérification email
- [ ] Tests complets
- [ ] Validation PhpCs et PhpStan
- [ ] README-STEP4 Action effectué et rest à faire

### Étape 5 : Authentification JWT
- [ ] Login et génération JWT
- [ ] Middleware JWT
- [ ] Tests de sécurité
- [ ] Validation PhpCs et PhpStan
- [ ] README-STEP5 Action effectué et rest à faire

### Étape 6 : Finalisation
- [ ] Validation PhpCs et PhpStan
- [ ] Documentation complète
- [ ] Guide de déploiement

## Commandes de Validation

À chaque étape, exécuter :
```bash
# Tests unitaires
./vendor/bin/phpunit

# Validation du code
./vendor/bin/phpcs --standard=PSR12 src/
./vendor/bin/phpstan analyse src/ --level=max

# Couverture de tests
./vendor/bin/phpunit --coverage-html coverage/
```

## Points d'Attention Critiques

1. **Sécurité** : Aucun compromis sur les mesures de sécurité
2. **Tests** : Couverture maximale obligatoire
3. **Standards** : Respect strict de PSR-12 et niveau PhpStan max
4. **Documentation** : README détaillé à chaque étape
5. **Performance** : Optimisation des requêtes et utilisation de l'index
6. **Maintenance** : Code modulaire et extensible

## Critères de Validation

Chaque livrable doit respecter :
- ✅ Tous les tests passent
- ✅ PhpCs sans erreur
- ✅ PhpStan niveau max sans erreur
- ✅ Couverture de tests > 95%
- ✅ Documentation complète
- ✅ Sécurité validée

---

**Important** : Ces instructions constituent le socle technique permanent. Toute déviation doit être justifiée et documentée.
