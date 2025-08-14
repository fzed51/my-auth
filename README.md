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
│   ├── services.json          # Services autorisés
│   └── jwt.php               # Configuration JWT
├── database/                  # Base de données
│   └── init-db.sql           # Schéma de base de données
├── src/                      # Code source
│   ├── Controller/           # Contrôleurs
│   ├── Service/             # Services métier
│   ├── Repository/          # Couche d'accès aux données
│   ├── Middleware/          # Middlewares
│   ├── Entity/             # Entités
│   └── Exception/          # Exceptions personnalisées
├── tests/                   # Tests
│   ├── Integration/        # Tests d'intégration
│   └── *Test.php          # Tests unitaires (co-localisés)
├── public/                 # Point d'entrée web
│   └── index.php          # Front controller
├── vendor/                 # Dépendances Composer
├── docker-compose.yml     # Configuration Docker
├── composer.json          # Dépendances PHP
└── README.md             # Ce fichier
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

# Couverture de tests
./vendor/bin/phpunit --coverage-html coverage/
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

## 📈 Roadmap

### ✅ Étape 1 - Base de Données (Terminée)
- [x] Schéma de base de données complet
- [x] Docker MySQL configuré
- [x] Tests de vérification
- [x] Documentation

### 🔄 Étapes Suivantes
- [ ] Étape 2 - Infrastructure (Configuration DI, autoloader)
- [ ] Étape 3 - Authentification Services (API Key)
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

**Status** : ⚠️ En développement - Étape 1 terminée
