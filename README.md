# Service d'Authentification Sécurisé

Un service d'authentification robuste et sécurisé basé sur PHP 8+, Slim Framework 4, et MySQL.

## 🚀 Technologies Utilisées

- **Backend** : PHP 8.1+
- **Framework** : Slim Framework 4
- **Base de données** : MySQL 8.0
- **Injection de dépendances** : PHP-DI 7
- **Tests** : PHPUnit 10
- **Qualité de code** : PHP_CodeSniffer (PSR-12), PHPStan (level max)
- **Conteneurisation** : Docker & Docker Compose

## 📁 Structure du Projet

```
my-auth/
├── config/                     # Configuration
│   ├── container.php          # Configuration PHP-DI
│   ├── database.php           # Configuration base de données
│   ├── services.json          # Services autorisés (API Keys)
│   └── jwt.php               # Configuration JWT
├── database/                  # Base de données
│   └── init-db.sql           # Schéma de base de données
├── src/                      # Code source
│   ├── Controller/           # Contrôleurs
│   ├── Service/             # Services métier
│   │   ├── ServiceAuthService.php     # Authentification API Key
│   │   └── *Service.php              # Autres services
│   ├── Repository/          # Couche d'accès aux données
│   │   ├── ServiceRepository.php     # Gestion services API
│   │   └── *Repository.php          # Autres repositories
│   ├── Middleware/          # Middlewares
│   │   ├── ApiKeyMiddleware.php      # Authentification API Key
│   │   └── *Middleware.php          # Autres middlewares
│   ├── Entity/             # Entités
│   │   ├── Service.php              # Entité service API
│   │   └── *Entity.php             # Autres entités
│   └── Exception/          # Exceptions personnalisées
│       ├── AuthenticationException.php
│       ├── AuthorizationException.php
│       ├── ServiceNotFoundException.php
│       └── *.php                    # Autres exceptions
├── tests/                   # Tests
│   ├── verify-database.sh   # Health check rapide de la DB
│   ├── test-database.php    # Validation structure + connexion
│   ├── test-migration.php   # Test complet end-to-end
│   ├── Integration/        # Tests d'intégration
│   └── *Test.php          # Tests unitaires (co-localisés)
├── public/                 # Point d'entrée web
│   └── index.php          # Front controller
├── vendor/                 # Dépendances Composer
├── docker-compose.yml     # Configuration Docker
├── composer.json          # Dépendances PHP
├── README.md             # Ce fichier
├── README-STEP3.md       # Documentation Authentification API Key
└── phpstan.neon          # Configuration analyse statique
```

## 🛠️ Installation et Configuration

### Prérequis

- Docker Desktop
- PHP 8.1+ (pour les tests locaux)
- Composer

### 1. Cloner le Projet

```bash
git clone <repository-url>
cd my-auth
```

### 2. Démarrer l'Environnement de Développement

```bash
# Démarrer MySQL et PHPMyAdmin
docker-compose up -d

# Attendre que MySQL soit prêt (environ 30 secondes)
sleep 30
```

### 3. Initialiser la Base de Données

```bash
# Créer le schéma de base de données
docker-compose exec -T mysql mysql -u auth_user -p'auth_password' my_auth < database/init-db.sql
```

### 4. Installer les Dépendances PHP

```bash
composer install
```

### 5. Vérifier l'Installation

```bash
# Test de la base de données
php tests/test-database.php

# Ou via le script de vérification
./tests/verify-database.sh
```

## 🧪 Tests de Base de Données

Le projet inclut une **suite complète de tests** pour valider la base de données selon différents contextes d'usage. Chaque fichier a un rôle spécifique et complémentaire :

### 📋 [`tests/verify-database.sh`](tests/verify-database.sh) - Health Check Rapide

**🎯 Objectif** : Vérification rapide de la structure via Docker
```bash
# Exécution directe via MySQL client
./tests/verify-database.sh
```

**🚀 Caractéristiques** :
- ✅ **Lecture seule** (aucune modification)
- ✅ **Ultra rapide** (2-3 secondes)
- ✅ **Indépendant** (bash + docker uniquement)
- ✅ **Monitoring-friendly** (idéal pour alertes)

**📊 Vérifications** :
- Tables existantes
- Structure des colonnes (DESCRIBE)
- Contraintes de clés étrangères

**💡 Quand l'utiliser** :
- Health check quotidien
- Scripts de monitoring
- Pipeline CI/CD (étape rapide)
- Vérification après redémarrage

### 🔍 [`tests/test-database.php`](tests/test-database.php) - Validation Structure + Connexion

**🎯 Objectif** : Test de connectivité PHP et validation détaillée
```bash
# Test avec connexion PDO native
php tests/test-database.php
```

**🚀 Caractéristiques** :
- ✅ **Connexion PDO** (test environnement réel)
- ✅ **Structure détaillée** (types, clés, contraintes)
- ✅ **Debug-oriented** (diagnostic précis)
- ✅ **Rapide** (5 secondes)

**📊 Vérifications** :
- Connexion PDO fonctionnelle
- Tables + colonnes avec types
- Index et contraintes
- Clés étrangères détaillées

**💡 Quand l'utiliser** :
- Après installation initiale
- Debug de problèmes de connexion
- Validation après modification du schéma
- Test de l'environnement PHP

### 🔄 [`tests/test-migration.php`](tests/test-migration.php) - Test Complet End-to-End

**🎯 Objectif** : Validation fonctionnelle complète avec données réelles
```bash
# Test exhaustif avec manipulation de données
php tests/test-migration.php
```

**🚀 Caractéristiques** :
- ✅ **6 tests distincts** avec rapports détaillés
- ✅ **Manipulation de données** (insertion/suppression)
- ✅ **Validation CASCADE** (contraintes en conditions réelles)
- ✅ **Test de régression** (vérification complète)

**📊 Tests réalisés** :
1. **Structure** : Vérification des tables attendues
2. **Contraintes FK** : Validation des relations
3. **Insertion** : Test avec données réelles
4. **Comptage** : Vérification des données insérées
5. **CASCADE** : Test de suppression en cascade
6. **Index** : Validation des performances

**💡 Quand l'utiliser** :
- Avant mise en production
- Après modifications importantes du schéma
- Tests de régression
- Validation finale avant commit

### 🔄 Workflow d'Utilisation Recommandé

```bash
# 1. Développement quotidien
./tests/verify-database.sh           # Check rapide (2s)

# 2. Après modification du schéma  
php tests/test-database.php          # Validation structure (5s)
php tests/test-migration.php         # Test complet (10s)

# 3. Pipeline CI/CD
./tests/verify-database.sh &&        # Health check
php tests/test-migration.php         # Validation complète

# 4. Debug d'un problème
php tests/test-database.php          # Diagnostic détaillé
```

### 📊 Comparaison des Tests

| Critère | [`verify-database.sh`](tests/verify-database.sh) | [`test-database.php`](tests/test-database.php) | [`test-migration.php`](tests/test-migration.php) |
|---------|------------------|------------------|------------------|
| **Durée** | 2-3 secondes | ~5 secondes | ~10 secondes |
| **Données** | Lecture seule | Lecture seule | Insertion/Suppression |
| **Connexion** | MySQL direct | PDO PHP | PDO PHP |
| **Contexte** | Health check | Debug/Validation | Test complet |
| **Dépendances** | Docker + Bash | PHP + PDO | PHP + PDO |
| **Side-effects** | Aucun | Aucun | Contrôlés |

### 🎯 Avantages de cette Approche

- **🔧 Granularité** : Du simple au complexe selon le besoin
- **⚡ Performance** : Test rapide pour usage fréquent
- **🛡️ Robustesse** : Validation complète quand nécessaire
- **🔄 Flexibilité** : Adapté à tous les contextes (dev, CI/CD, prod)
- **🧹 Maintenabilité** : Chaque fichier a un rôle précis

## 🗄️ Base de Données

### Configuration

- **Host** : `localhost:3306` (ou `mysql:3306` depuis Docker)
- **Base** : `my_auth`
- **User** : `auth_user`
- **Password** : `auth_password`

### Tables

#### `users` - Comptes Utilisateurs
- `id` (CHAR(36)) - UUID utilisateur
- `email` (VARCHAR(255)) - Adresse email unique
- `password_hash` (VARCHAR(255)) - Hash sécurisé du mot de passe
- `is_active` (BOOLEAN) - Compte activé
- `is_verified` (BOOLEAN) - Email vérifié
- `first_name`, `last_name` (VARCHAR(100)) - Nom et prénom
- `created_at`, `updated_at` (TIMESTAMP) - Audit

#### `email_verifications` - Tokens de Vérification
- `id` (CHAR(36)) - UUID du token
- `user_id` (CHAR(36)) - Référence utilisateur
- `token` (VARCHAR(255)) - Token de vérification hashé
- `expires_at` (TIMESTAMP) - Date d'expiration
- `is_used` (BOOLEAN) - Token utilisé
- `created_at`, `used_at` (TIMESTAMP) - Audit

#### `jwt_blacklist` - Tokens Révoqués
- `id` (CHAR(36)) - UUID de l'entrée
- `jti` (VARCHAR(255)) - JWT ID unique
- `user_id` (CHAR(36)) - Référence utilisateur
- `token_hash` (VARCHAR(255)) - Hash du token
- `expires_at` (TIMESTAMP) - Expiration du token original
- `revoked_at` (TIMESTAMP) - Date de révocation
- `reason` (ENUM) - Raison de la révocation

## 🔧 Outils de Développement

### Accès à la Base de Données

- **PHPMyAdmin** : http://localhost:8081
  - Utilisateur : `root`
  - Mot de passe : `password`

### Commandes de Validation

```bash
# Tests unitaires
./vendor/bin/phpunit

# Validation du code (PSR-12)
./vendor/bin/phpcs --standard=PSR12 src/

# Analyse statique (niveau max)
./vendor/bin/phpstan analyse src/ --level=max

# Validation complète (format + analyse + tests)
composer run quality

# Couverture de tests
./vendor/bin/phpunit --coverage-html coverage/
```

### Commandes Spécifiques Étape 3

```bash
# Tests API Key uniquement
vendor/bin/phpunit src/Entity/ServiceTest.php
vendor/bin/phpunit src/Repository/ServiceRepositoryTest.php
vendor/bin/phpunit src/Service/ServiceAuthServiceTest.php
vendor/bin/phpunit src/Middleware/ApiKeyMiddlewareTest.php

# Vérification configuration services
php -c "echo json_decode(file_get_contents('config/services.json'), true) ? 'Valid JSON' : 'Invalid JSON';"
```

## 🛡️ Sécurité

### Mesures Implémentées

- **Protection SQL Injection** : Requêtes préparées PDO exclusivement
- **Hashage des mots de passe** : `password_hash()` avec `PASSWORD_DEFAULT`
- **UUID** : Prévention de l'énumération des identifiants
- **Tokens sécurisés** : Stockage en version hashée
- **Contraintes de base** : Clés étrangères avec CASCADE
- **Nettoyage automatique** : Event scheduler pour les données expirées

### Standards de Qualité

- **PSR-12** : Standard de codage respecté
- **PHPStan Level Max** : Analyse statique maximale
- **Couverture de tests** : Objectif > 95%
- **Documentation** : Commentaires sur toutes les tables et colonnes

## 🔐 Authentification API Key

Le système inclut un mécanisme complet d'authentification par clé API pour les services externes.

### Configuration Rapide

1. **Configurer les services autorisés** dans `config/services.json`
2. **Appliquer le middleware** sur vos routes protégées
3. **Utiliser l'API** avec votre clé dans les headers

```php
// Application du middleware
$app->add(ApiKeyMiddleware::class);

// Ou pour des routes spécifiques
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->get('/users', [UserController::class, 'list']);
})->add(ApiKeyMiddleware::class);
```

### Utilisation

```bash
# Authentification via header (recommandé)
curl -H "X-API-Key: votre-cle-api" https://api.example.com/protected

# Authentification via Bearer token
curl -H "Authorization: Bearer votre-cle-api" https://api.example.com/protected
```

### Fonctionnalités

- ✅ **Multiple méthodes** d'authentification (header, bearer, query)
- ✅ **Validation des origines** avec support wildcards
- ✅ **Routes publiques** configurables
- ✅ **Tokens temporaires** pour cas spéciaux
- ✅ **Gestion d'erreurs** avec codes HTTP appropriés
- ✅ **Tests complets** et documentation détaillée

**📖 Documentation complète** : [README-STEP3.md](README-STEP3.md)

## 📈 Roadmap

### ✅ Étape 1 - Base de Données (Terminée)
- [x] Schéma de base de données complet
- [x] Docker MySQL configuré
- [x] Tests de vérification
- [x] Documentation

### ✅ Étape 2 - Infrastructure (Terminée)
- [x] Configuration PHP-DI
- [x] Autoloader Composer
- [x] Structure des services
- [x] Middleware de base

### ✅ Étape 3 - Authentification Services (Terminée)
- [x] Système d'API Key complet
- [x] Middleware PSR-15 pour authentification
- [x] Validation des origines (CORS)
- [x] Configuration JSON des services
- [x] Tests unitaires complets
- [x] Documentation détaillée
- [x] **Voir [README-STEP3.md](README-STEP3.md) pour les détails**

### 🔄 Étapes Suivantes
- [ ] Étape 4 - Gestion Utilisateurs (Register, Email verification)
- [ ] Étape 5 - Authentification JWT (Login, Middleware)
- [ ] Étape 6 - Finalisation (Documentation, Déploiement)

## 🤝 Contribution

1. Respecter les standards PSR-12
2. Maintenir la couverture de tests > 95%
3. Documenter toutes les modifications
4. Valider avec PHPStan niveau max

## 📄 Licence

MIT License - Voir le fichier LICENSE pour plus de détails.

---

**Status** : 🚀 En développement actif - Étapes 1, 2 et 3 terminées

**Dernière mise à jour** : 14 août 2025 - Authentification API Key opérationnelle
