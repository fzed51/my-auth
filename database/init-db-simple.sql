-- =================================================================
-- Service d'Authentification - Base de Données (Version Simple)
-- =================================================================

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS my_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE my_auth;

-- =================================================================
-- Table des utilisateurs
-- =================================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(320) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_is_email_verified (is_email_verified),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =================================================================
-- Table des tokens de vérification d'email
-- =================================================================
CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- =================================================================
-- Table de blacklist JWT (optionnel pour logout)
-- =================================================================
CREATE TABLE jwt_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jti VARCHAR(255) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_jti (jti),
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- =================================================================
-- Table des tentatives de connexion (protection brute force)
-- =================================================================
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(320) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email_ip (email, ip_address),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_success (success)
) ENGINE=InnoDB;

-- =================================================================
-- Index de performance supplémentaires
-- =================================================================

-- Index composite pour améliorer les performances des requêtes de login
CREATE INDEX idx_users_email_active_verified ON users(email, is_active, is_email_verified);

-- Index pour les requêtes de nettoyage
CREATE INDEX idx_email_verifications_expires_used ON email_verifications(expires_at, used_at);

-- =================================================================
-- Fin du script
-- =================================================================
