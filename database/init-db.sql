-- =============================================================================
-- Service d'Authentification - Base de Données
-- =============================================================================
-- 
-- Ce fichier contient le schéma complet de la base de données pour le service
-- d'authentification sécurisé.
--
-- Tables :
-- - users : Comptes utilisateurs avec statut d'activation
-- - email_verifications : Tokens de vérification d'email
-- - jwt_blacklist : Tokens JWT révoqués (pour logout sécurisé)
--
-- =============================================================================

-- Suppression des tables existantes (pour réinitialisation)
SET foreign_key_checks = 0;
DROP TABLE IF EXISTS jwt_blacklist;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS users;
SET foreign_key_checks = 1;

-- =============================================================================
-- TABLE: users
-- =============================================================================
-- Table des comptes utilisateurs
--
CREATE TABLE users (
    id CHAR(36) NOT NULL COMMENT 'UUID de l’utilisateur',
    email VARCHAR(255) NOT NULL COMMENT 'Adresse email (identifiant unique)',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hash sécurisé du mot de passe',
    is_active BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Statut d’activation du compte',
    is_verified BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Email vérifié',
    first_name VARCHAR(100) NULL COMMENT 'Prénom',
    last_name VARCHAR(100) NULL COMMENT 'Nom de famille',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de dernière modification',
    
    -- Contraintes
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_email (email),
    
    -- Index pour performance
    INDEX idx_users_email (email),
    INDEX idx_users_active (is_active),
    INDEX idx_users_verified (is_verified),
    INDEX idx_users_created (created_at)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Table des comptes utilisateurs';

-- =============================================================================
-- TABLE: email_verifications
-- =============================================================================
-- Table des tokens de vérification d'email
--
CREATE TABLE email_verifications (
    id CHAR(36) NOT NULL COMMENT 'UUID du token',
    user_id CHAR(36) NOT NULL COMMENT 'UUID de l’utilisateur',
    token VARCHAR(255) NOT NULL COMMENT 'Token de vérification (hashé)',
    expires_at TIMESTAMP NOT NULL COMMENT 'Date d’expiration du token',
    is_used BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Token utilisé',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
    used_at TIMESTAMP NULL COMMENT 'Date d’utilisation du token',
    
    -- Contraintes
    PRIMARY KEY (id),
    UNIQUE KEY uk_email_verifications_token (token),
    
    -- Clé étrangère
    FOREIGN KEY fk_email_verifications_user (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    -- Index pour performance
    INDEX idx_email_verifications_user (user_id),
    INDEX idx_email_verifications_token (token),
    INDEX idx_email_verifications_expires (expires_at),
    INDEX idx_email_verifications_used (is_used)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Table des tokens de vérification d’email';

-- =============================================================================
-- TABLE: jwt_blacklist
-- =============================================================================
-- Table des tokens JWT révoqués (pour logout sécurisé)
--
CREATE TABLE jwt_blacklist (
    id CHAR(36) NOT NULL COMMENT 'UUID de l’entrée',
    jti VARCHAR(255) NOT NULL COMMENT 'JWT ID (claim jti du token)',
    user_id CHAR(36) NOT NULL COMMENT 'UUID de l’utilisateur',
    token_hash VARCHAR(255) NOT NULL COMMENT 'Hash du token révoqué',
    expires_at TIMESTAMP NOT NULL COMMENT 'Date d’expiration du token original',
    revoked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de révocation',
    reason ENUM('logout', 'security', 'admin') DEFAULT 'logout' COMMENT 'Raison de la révocation',
    
    -- Contraintes
    PRIMARY KEY (id),
    UNIQUE KEY uk_jwt_blacklist_jti (jti),
    
    -- Clé étrangère
    FOREIGN KEY fk_jwt_blacklist_user (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    -- Index pour performance
    INDEX idx_jwt_blacklist_jti (jti),
    INDEX idx_jwt_blacklist_user (user_id),
    INDEX idx_jwt_blacklist_expires (expires_at),
    INDEX idx_jwt_blacklist_revoked (revoked_at)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Table des tokens JWT révoqués';

-- =============================================================================
-- TRIGGERS DE MAINTENANCE
-- =============================================================================

-- Trigger pour nettoyer automatiquement les tokens expirés
-- (Optionnel - peut être fait par un job cron)
DELIMITER $$

CREATE EVENT IF NOT EXISTS cleanup_expired_tokens
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Suppression des tokens de vérification expirés (plus de 7 jours)
    DELETE FROM email_verifications 
    WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Suppression des tokens JWT expirés de la blacklist
    DELETE FROM jwt_blacklist 
    WHERE expires_at < NOW();
END$$

DELIMITER ;

-- =============================================================================
-- DONNÉES D'EXEMPLE ET VÉRIFICATIONS
-- =============================================================================

-- Vérification que les tables ont été créées correctement
SELECT 
    TABLE_NAME,
    TABLE_COMMENT,
    TABLE_ROWS
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN ('users', 'email_verifications', 'jwt_blacklist');

-- Vérification des contraintes de clés étrangères
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- =============================================================================
-- NOTES TECHNIQUES
-- =============================================================================
--
-- 1. SÉCURITÉ :
--    - Tous les mots de passe doivent être hashés avec password_hash() PHP
--    - Les tokens sont stockés en version hashée pour la sécurité
--    - Utilisation d'UUID pour éviter l'énumération des IDs
--
-- 2. PERFORMANCE :
--    - Index sur les colonnes fréquemment utilisées dans les WHERE
--    - Clés étrangères avec CASCADE pour maintenir la cohérence
--    - Event scheduler pour le nettoyage automatique
--
-- 3. MAINTENANCE :
--    - Timestamps automatiques pour l'audit
--    - Event pour nettoyer les données expirées
--    - Commentaires sur toutes les tables et colonnes
--
-- 4. EXTENSIBILITÉ :
--    - Structure préparée pour d'autres types d'authentification
--    - Champs additionnels facilement ajoutables
--    - Contraintes flexibles pour l'évolution
--
-- =============================================================================