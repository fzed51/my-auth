<?php

declare(strict_types=1);

/**
 * Configuration de la base de données
 * 
 * Ce fichier configure la connexion PDO MySQL pour le service d'authentification.
 * Les paramètres sont chargés depuis les variables d'environnement pour la sécurité.
 * 
 * @package MyAuth\Config
 */

return [
    'database' => [
        // Configuration principale
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'dbname' => $_ENV['DB_NAME'] ?? 'my_auth',
        'username' => $_ENV['DB_USER'] ?? 'auth_user',
        'password' => $_ENV['DB_PASSWORD'] ?? 'auth_password',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        
        // Options PDO pour la sécurité et performance
        'options' => [
            // Gestion des erreurs
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Mode de récupération par défaut
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Émulation des requêtes préparées (désactivée pour la sécurité)
            PDO::ATTR_EMULATE_PREPARES => false,
            
            // Conversion automatique des types
            PDO::ATTR_STRINGIFY_FETCHES => false,
            
            // Timeout de connexion
            PDO::ATTR_TIMEOUT => 30,
            
            // Connexion persistante (désactivée par défaut)
            PDO::ATTR_PERSISTENT => false,
            
            // MySQL spécifique : utilisation du jeu de caractères natif
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            
            // MySQL spécifique : mode SQL strict
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'",
        ],
        
        // Pool de connexions (pour usage avancé)
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'idle_timeout' => 300, // 5 minutes
        ],
        
        // Configuration pour les tests
        'test' => [
            'host' => $_ENV['TEST_DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['TEST_DB_PORT'] ?? 3306),
            'dbname' => $_ENV['TEST_DB_NAME'] ?? 'my_auth_test',
            'username' => $_ENV['TEST_DB_USER'] ?? 'auth_user',
            'password' => $_ENV['TEST_DB_PASSWORD'] ?? 'auth_password',
        ],
    ],
];
