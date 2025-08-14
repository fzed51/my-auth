<?php

declare(strict_types=1);

/**
 * Configuration JWT (JSON Web Tokens)
 * 
 * Ce fichier configure les paramètres pour la génération et validation des tokens JWT.
 * Utilise l'algorithme HS256 recommandé pour la sécurité.
 * 
 * @package MyAuth\Config
 */

return [
    'jwt' => [
        // Clé secrète de signature (DOIT être changée en production)
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-this-in-production',
        
        // Algorithme de chiffrement
        'algorithm' => 'HS256',
        
        // Durée de validité des tokens (en secondes)
        'access_token_ttl' => (int) ($_ENV['JWT_ACCESS_TTL'] ?? 3600), // 1 heure
        'refresh_token_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? 604800), // 7 jours
        
        // Issuer (émetteur du token)
        'issuer' => $_ENV['JWT_ISSUER'] ?? 'my-auth-service',
        
        // Audience (destinataire du token)
        'audience' => $_ENV['JWT_AUDIENCE'] ?? 'my-auth-clients',
        
        // Claim personnalisés
        'custom_claims' => [
            // Inclure l'ID utilisateur
            'include_user_id' => true,
            
            // Inclure l'email utilisateur
            'include_email' => true,
            
            // Inclure les rôles/permissions
            'include_roles' => true,
            
            // Inclure le statut de vérification
            'include_verification_status' => true,
        ],
        
        // Configuration de la blacklist
        'blacklist' => [
            // Activer la gestion de la blacklist
            'enabled' => true,
            
            // Vérifier la blacklist à chaque validation
            'check_on_validation' => true,
            
            // Durée de rétention des tokens expirés dans la blacklist (en secondes)
            'retention_period' => 86400, // 24 heures après expiration
        ],
        
        // Paramètres de sécurité
        'security' => [
            // Tolérance pour l'horloge (en secondes) - pour compenser les décalages d'horloge
            'clock_tolerance' => 60,
            
            // Forcer HTTPS en production
            'require_https' => $_ENV['APP_ENV'] === 'production',
            
            // Vérifier l'audience
            'verify_audience' => true,
            
            // Vérifier l'issuer
            'verify_issuer' => true,
            
            // Headers requis
            'required_headers' => ['typ', 'alg'],
        ],
        
        // Configuration pour les tests
        'test' => [
            'secret' => 'test-jwt-secret-key-for-testing-only',
            'access_token_ttl' => 300, // 5 minutes pour les tests
            'refresh_token_ttl' => 1800, // 30 minutes pour les tests
        ],
    ],
];
