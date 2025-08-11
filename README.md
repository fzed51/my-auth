# Service d'Authentification - My Auth

Service d'authentification robuste et sécurisé développé avec Slim Framework 4, utilisant JWT pour l'authentification et MySQL pour la persistance des données.

## 🏗️ Architecture

### Technologies utilisées

- **Framework** : Slim Framework 4
- **Injection de dépendances** : PHP-DI 6
- **Base de données** : MySQL 8.0 avec PDO
- **Authentification** : JWT (Firebase PHP-JWT)
- **Email** : SwiftMailer
- **Tests** : PHPUnit 9
- **Qualité de code** : PHP_CodeSniffer (PSR-12) + PHPStan (niveau max)

### Structure du projet

```
/
├── config/                 # Configuration
│   ├── container.php      # Container DI
│   ├── database.php       # Configuration base de données
│   ├── jwt.php           # Configuration JWT
│   └── services.json     # Services autorisés
├── database/              # Base de données
│   ├── init-db.sql       # Script d'initialisation
│   └── mysql.conf        # Configuration MySQL
├── src/                   # Code source
│   ├── Controller/        # Contrôleurs
│   ├── Service/          # Services métier
│   ├── Repository/       # Accès aux données
│   ├── Middleware/       # Middlewares
│   ├── Entity/           # Entités
│   └── Exception/        # Exceptions personnalisées
├── tests/                # Tests
│   ├── Unit/            # Tests unitaires
│   └── Integration/     # Tests d'intégration
├── public/               # Point d'entrée web
│   └── index.php        # Application principale
└── vendor/              # Dépendances Composer
```

## 🚀 Installation

### Prérequis

- PHP 8.0 ou supérieur
- Composer
- MySQL 8.0
- Docker et Docker Compose (optionnel)

### Installation avec Docker (recommandé)

1. **Cloner le projet**
   ```bash
   git clone <repository-url>
   cd my-auth
   ```

2. **Installer les dépendances**
   ```bash
   composer install
   ```

3. **Configuration de l'environnement**
   ```bash
   cp .env.example .env
   # Éditer .env avec vos paramètres
   ```

4. **Démarrer MySQL avec Docker**
   ```bash
   docker-compose up -d mysql
   ```

5. **Initialiser la base de données**
   ```bash
   # La base sera automatiquement initialisée via le script init-db.sql
   # Ou manuellement :
   mysql -h localhost -P 3306 -u root -p < database/init-db.sql
   ```

6. **Démarrer le serveur de développement**
   ```bash
   php -S localhost:8080 -t public/
   ```

### Installation manuelle

1. **Installer MySQL 8.0**
2. **Créer la base de données**
   ```bash
   mysql -u root -p < database/init-db.sql
   ```
3. **Configurer .env** avec vos paramètres de base de données
4. **Installer les dépendances** : `composer install`
5. **Démarrer le serveur** : `php -S localhost:8080 -t public/`

## 🔧 Configuration

### Variables d'environnement (.env)

```env
# Base de données
DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_auth
DB_USER=root
DB_PASS=password

# JWT
JWT_SECRET=your-super-secret-jwt-key
JWT_ALGORITHM=HS256
JWT_EXPIRATION=3600

# Email
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080
```

### Services autorisés (config/services.json)

Configurez les services autorisés à utiliser l'API :

```json
[
    {
        "id": 1,
        "name": "frontend-app",
        "api_key": "your-frontend-api-key",
        "description": "Application frontend",
        "is_active": true,
        "permissions": ["auth:login", "auth:register"],
        "rate_limit": {
            "requests_per_minute": 60
        }
    }
]
```

## 🛡️ Sécurité

### Mesures de sécurité implémentées

1. **Protection contre les injections SQL** : Requêtes PDO préparées exclusivement
2. **Hachage sécurisé des mots de passe** : `password_hash()` avec `PASSWORD_DEFAULT`
3. **JWT sécurisé** : Signature cryptographique + vérification d'expiration + blacklist
4. **API Keys** : Authentification des services via API keys
5. **Rate Limiting** : Protection contre les attaques par force brute
6. **Validation stricte** : Validation des formats email et complexité des mots de passe
7. **Headers de sécurité** : X-Content-Type-Options, X-Frame-Options, etc.

### Politique des mots de passe

- Minimum 8 caractères
- Au moins une majuscule
- Au moins une minuscule  
- Au moins un chiffre
- Au moins un caractère spécial

## 📊 API Documentation

### Authentification des services

Toutes les requêtes doivent inclure l'API key dans le header :
```
X-API-Key: your-api-key
```

### Endpoints publics

#### Inscription
```http
POST /api/auth/register
Content-Type: application/json
X-API-Key: your-api-key

{
    "email": "user@example.com",
    "password": "SecurePass123!",
    "firstName": "John",
    "lastName": "Doe"
}
```

#### Connexion
```http
POST /api/auth/login
Content-Type: application/json
X-API-Key: your-api-key

{
    "email": "user@example.com",
    "password": "SecurePass123!"
}
```

#### Vérification d'email
```http
GET /api/auth/verify-email/{token}
X-API-Key: your-api-key
```

### Endpoints protégés (JWT requis)

#### Profil utilisateur
```http
GET /api/auth/me
Authorization: Bearer <jwt-token>
X-API-Key: your-api-key
```

#### Déconnexion
```http
POST /api/auth/logout
Authorization: Bearer <jwt-token>
X-API-Key: your-api-key
```

#### Rafraîchissement du token
```http
POST /api/auth/refresh
Authorization: Bearer <jwt-token>
X-API-Key: your-api-key
```

### Endpoints utilitaires

#### Vérification de santé
```http
GET /health
```

#### Test de configuration
```http
GET /api/config/test
X-API-Key: your-api-key
```

## 🧪 Tests

### Exécution des tests

```bash
# Tests unitaires
./vendor/bin/phpunit

# Tests avec couverture
./vendor/bin/phpunit --coverage-html coverage/

# Tests spécifiques
./vendor/bin/phpunit tests/Unit/Service/
```

### Validation du code

```bash
# PHP CodeSniffer (PSR-12)
./vendor/bin/phpcs --standard=PSR12 src/

# Correction automatique
./vendor/bin/phpcbf --standard=PSR12 src/

# PHPStan (niveau maximum)
./vendor/bin/phpstan analyse src/ --level=max

# Validation complète
composer run quality
```

## 🔄 Processus de développement

### Workflow de base

1. **Développement** : Créer une branche feature
2. **Tests** : Écrire et exécuter les tests
3. **Validation** : Vérifier la qualité du code
4. **Review** : Code review
5. **Merge** : Fusion vers main

### Commandes utiles

```bash
# Installation
composer install

# Tests complets
composer run test

# Validation du code
composer run cs
composer run stan

# Tout valider
composer run quality
```

## 📈 Monitoring et Logs

### Logs d'utilisation

Les logs sont automatiquement générés pour :
- Tentatives de connexion
- Utilisation des services
- Erreurs d'authentification

### Nettoyage automatique

Le système nettoie automatiquement :
- Tokens de vérification expirés (toutes les heures)
- Tokens JWT blacklistés expirés
- Anciennes tentatives de connexion (> 24h)

## 🚢 Déploiement

### Préparation pour la production

1. **Configuration**
   ```bash
   # Mettre APP_ENV=production dans .env
   # Générer une JWT_SECRET sécurisée
   # Configurer les vraies credentials
   ```

2. **Optimisation**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Sécurité**
   - Changer toutes les clés secrètes
   - Configurer HTTPS
   - Restreindre les CORS
   - Activer le cache du container DI

### Serveur web

Configuration Apache/Nginx pour pointer vers `public/index.php`.

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -am 'Ajoute nouvelle fonctionnalité'`)
4. Push la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## 📝 Changelog

### Version 1.0.0
- ✅ Authentification des services par API key
- ✅ Inscription et vérification d'email
- ✅ Connexion avec JWT
- ✅ Protection contre le brute force
- ✅ Middleware de sécurité
- ✅ Tests unitaires et qualité de code
- ✅ Documentation complète

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🔗 Liens utiles

- [Documentation Slim Framework](https://www.slimframework.com/)
- [PHP-DI Documentation](https://php-di.org/)
- [Firebase JWT](https://github.com/firebase/php-jwt)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

---

**Note** : Ce service est conçu pour être robuste et sécurisé. Tous les aspects de sécurité ont été soigneusement implémentés selon les meilleures pratiques.
