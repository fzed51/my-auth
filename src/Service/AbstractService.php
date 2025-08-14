<?php

declare(strict_types=1);

namespace MyAuth\Service;

use InvalidArgumentException;
use RuntimeException;

/**
 * Classe de base abstraite pour tous les services
 *
 * Fournit les fonctionnalités communes aux services :
 * - Gestion des validations
 * - Gestion des erreurs
 * - Méthodes utilitaires
 * - Logging et monitoring
 *
 * @package MyAuth\Service
 */
abstract class AbstractService
{
    /**
     * Valide que les données requises sont présentes
     *
     * @param array $data Données à valider
     * @param array $requiredFields Champs requis
     * @throws InvalidArgumentException Si des champs requis manquent
     */
    protected function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new InvalidArgumentException(
                'Champs requis manquants : ' . implode(', ', $missingFields)
            );
        }
    }

    /**
     * Valide qu'une adresse email est correcte
     *
     * @param string $email Email à valider
     * @throws InvalidArgumentException Si l'email n'est pas valide
     */
    protected function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Adresse email invalide : ' . $email);
        }
    }

    /**
     * Valide qu'un mot de passe respecte les critères de sécurité
     *
     * @param string $password Mot de passe à valider
     * @throws InvalidArgumentException Si le mot de passe ne respecte pas les critères
     */
    protected function validatePassword(string $password): void
    {
        $minLength = (int) ($_ENV['MIN_PASSWORD_LENGTH'] ?? 8);

        if (strlen($password) < $minLength) {
            throw new InvalidArgumentException(
                "Le mot de passe doit contenir au moins {$minLength} caractères"
            );
        }

        // Vérification de la complexité (au moins un chiffre et une lettre)
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).+$/', $password)) {
            throw new InvalidArgumentException(
                'Le mot de passe doit contenir au moins une lettre et un chiffre'
            );
        }
    }

    /**
     * Valide qu'un UUID a le bon format
     *
     * @param string $uuid UUID à valider
     * @throws InvalidArgumentException Si l'UUID n'est pas valide
     */
    protected function validateUuid(string $uuid): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, $uuid)) {
            throw new InvalidArgumentException('UUID invalide : ' . $uuid);
        }
    }

    /**
     * Génère un UUID v4
     *
     * @return string UUID généré
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Génère un token aléatoire sécurisé
     *
     * @param int $length Longueur du token
     * @return string Token généré
     */
    protected function generateSecureToken(int $length = 32): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('La longueur du token doit être au moins de 1');
        }

        try {
            return bin2hex(random_bytes($length));
        } catch (\Exception $e) {
            throw new RuntimeException('Impossible de générer un token sécurisé : ' . $e->getMessage());
        }
    }

    /**
     * Hash un mot de passe de manière sécurisée
     *
     * @param string $password Mot de passe à hasher
     * @return string Hash du mot de passe
     */
    protected function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);

        return $hash;
    }

    /**
     * Vérifie un mot de passe contre son hash
     *
     * @param string $password Mot de passe en clair
     * @param string $hash Hash stocké
     * @return bool True si le mot de passe correspond
     */
    protected function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Nettoie et normalise une chaîne de caractères
     *
     * @param string $input Chaîne d'entrée
     * @return string Chaîne nettoyée
     */
    protected function sanitizeString(string $input): string
    {
        // Suppression des espaces en début et fin
        $cleaned = trim($input);

        // Suppression des caractères de contrôle
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
        if ($cleaned === null) {
            throw new RuntimeException('Erreur lors du nettoyage de la chaîne');
        }

        // Normalisation des espaces multiples
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        if ($cleaned === null) {
            throw new RuntimeException('Erreur lors de la normalisation de la chaîne');
        }

        return $cleaned;
    }

    /**
     * Valide et nettoie un nom d'utilisateur
     *
     * @param string $username Nom d'utilisateur à valider
     * @return string Nom d'utilisateur nettoyé
     * @throws InvalidArgumentException Si le nom d'utilisateur n'est pas valide
     */
    protected function validateAndSanitizeUsername(string $username): string
    {
        $username = $this->sanitizeString($username);

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new InvalidArgumentException(
                'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères'
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new InvalidArgumentException(
                'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores'
            );
        }

        return $username;
    }

    /**
     * Convertit une date en timestamp Unix
     *
     * @param string $dateTime Date au format ISO 8601 ou compatible
     * @return int Timestamp Unix
     * @throws InvalidArgumentException Si la date n'est pas valide
     */
    protected function dateTimeToTimestamp(string $dateTime): int
    {
        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            throw new InvalidArgumentException('Format de date invalide : ' . $dateTime);
        }

        return $timestamp;
    }

    /**
     * Vérifie si une date est expirée
     *
     * @param string $expirationDate Date d'expiration
     * @return bool True si la date est expirée
     */
    protected function isExpired(string $expirationDate): bool
    {
        return time() > $this->dateTimeToTimestamp($expirationDate);
    }

    /**
     * Calcule une date d'expiration à partir d'une durée
     *
     * @param int $seconds Durée en secondes
     * @return string Date d'expiration au format Y-m-d H:i:s
     */
    protected function calculateExpirationDate(int $seconds): string
    {
        return date('Y-m-d H:i:s', time() + $seconds);
    }

    /**
     * Log une information (à implémenter selon le système de logging choisi)
     *
     * @param string $level Niveau de log (info, warning, error, etc.)
     * @param string $message Message à logger
     * @param array $context Contexte additionnel
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // TODO: Implémenter le logging avec Monolog ou un autre système
        // Pour l'instant, on utilise error_log en mode développement
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            error_log("[{$level}] {$message}{$contextStr}");
        }
    }

    /**
     * Méthode utilitaire pour logger des erreurs
     *
     * @param string $message Message d'erreur
     * @param array $context Contexte additionnel
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Méthode utilitaire pour logger des informations
     *
     * @param string $message Message d'information
     * @param array $context Contexte additionnel
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Méthode utilitaire pour logger des avertissements
     *
     * @param string $message Message d'avertissement
     * @param array $context Contexte additionnel
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
}
