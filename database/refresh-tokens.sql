-- =================================================================
-- Ajout du système de Refresh Token
-- =================================================================

USE my_auth;

-- =================================================================
-- Table des refresh tokens
-- =================================================================
CREATE TABLE refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    jti VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_revoked BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_jti (jti),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_revoked (is_revoked)
) ENGINE=InnoDB;

-- =================================================================
-- Procédure de nettoyage des refresh tokens expirés
-- =================================================================
DELIMITER $$
CREATE PROCEDURE CleanExpiredRefreshTokens()
BEGIN
    -- Supprimer les refresh tokens expirés
    DELETE FROM refresh_tokens 
    WHERE expires_at < NOW() OR is_revoked = TRUE;
END$$
DELIMITER ;

-- =================================================================
-- Mettre à jour la procédure de nettoyage existante
-- =================================================================
DROP PROCEDURE IF EXISTS CleanExpiredTokens;

DELIMITER $$
CREATE PROCEDURE CleanExpiredTokens()
BEGIN
    -- Nettoyage des tokens de vérification email expirés
    DELETE FROM email_verifications 
    WHERE expires_at < NOW() AND used_at IS NULL;
    
    -- Nettoyage des tokens JWT blacklistés expirés
    DELETE FROM jwt_blacklist 
    WHERE expires_at < NOW();
    
    -- Nettoyage des sessions expirées
    DELETE FROM user_sessions 
    WHERE expires_at < NOW();
    
    -- Nettoyage des tentatives de connexion anciennes (> 24h)
    DELETE FROM login_attempts 
    WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Nettoyage des refresh tokens expirés
    CALL CleanExpiredRefreshTokens();
END$$
DELIMITER ;

-- =================================================================
-- Index de performance supplémentaires
-- =================================================================

-- Index composite pour les requêtes de validation
CREATE INDEX idx_refresh_tokens_validation ON refresh_tokens(token_hash, is_revoked, expires_at);

-- Index pour les requêtes par utilisateur
CREATE INDEX idx_refresh_tokens_user_active ON refresh_tokens(user_id, is_revoked, expires_at);
