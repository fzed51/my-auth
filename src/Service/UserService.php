<?php

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Entity\User;
use MyAuth\Entity\EmailVerification;
use MyAuth\Repository\UserRepository;
use MyAuth\Repository\EmailVerificationRepository;
use MyAuth\Service\EmailService;
use MyAuth\Exception\ValidationException;
use MyAuth\Exception\UserNotFoundException;
use DateTime;
use InvalidArgumentException;

class UserService
{
    private UserRepository $userRepository;
    private EmailVerificationRepository $verificationRepository;
    private EmailService $emailService;

    public function __construct(
        UserRepository $userRepository,
        EmailService $emailService,
        EmailVerificationRepository $verificationRepository = null
    ) {
        $this->userRepository = $userRepository;
        $this->emailService = $emailService;
        // Injection de dépendance optionnelle pour éviter les dépendances circulaires
        if ($verificationRepository) {
            $this->verificationRepository = $verificationRepository;
        }
    }

    public function setVerificationRepository(EmailVerificationRepository $verificationRepository): void
    {
        $this->verificationRepository = $verificationRepository;
    }

    /**
     * Crée un nouvel utilisateur avec validation
     */
    public function createUser(array $userData): User
    {
        $this->validateUserData($userData);

        // Vérifier si l'email existe déjà
        if ($this->userRepository->emailExists($userData['email'])) {
            throw new ValidationException(['email' => 'Cet email est déjà utilisé']);
        }

        // Hasher le mot de passe
        $passwordHash = $this->hashPassword($userData['password']);

        // Créer l'utilisateur
        $user = new User(
            $userData['email'],
            $passwordHash,
            $userData['firstName'] ?? null,
            $userData['lastName'] ?? null
        );

        // Sauvegarder en base
        $user = $this->userRepository->save($user);

        // Envoyer l'email de vérification
        $this->sendEmailVerification($user);

        return $user;
    }

    /**
     * Envoie un email de vérification
     */
    public function sendEmailVerification(User $user): bool
    {
        if ($user->isEmailVerified()) {
            throw new InvalidArgumentException('L\'email est déjà vérifié');
        }

        // Invalider les anciens tokens
        if (isset($this->verificationRepository)) {
            $this->verificationRepository->invalidateAllForUser($user->getId());
        }

        // Créer un nouveau token
        $verification = EmailVerification::createForUser($user->getId(), 24);

        if (isset($this->verificationRepository)) {
            $this->verificationRepository->save($verification);
        }

        // Envoyer l'email
        return $this->emailService->sendEmailVerification(
            $user->getEmail(),
            $verification->getToken()
        );
    }

    /**
     * Vérifie un token d'email
     */
    public function verifyEmail(string $token): bool
    {
        if (!isset($this->verificationRepository)) {
            throw new \RuntimeException('Email verification repository not set');
        }

        $verification = $this->verificationRepository->findByToken($token);

        if (!$verification || !$verification->isValid()) {
            return false;
        }

        // Marquer le token comme utilisé
        $verification->markAsUsed();
        $this->verificationRepository->save($verification);

        // Activer l'utilisateur
        $activated = $this->userRepository->activateUser($verification->getUserId());

        if ($activated) {
            // Envoyer l'email de bienvenue
            $user = $this->userRepository->findById($verification->getUserId());
            if ($user) {
                $this->emailService->sendWelcomeEmail(
                    $user->getEmail(),
                    $user->getFirstName()
                );
            }
        }

        return $activated;
    }

    /**
     * Met à jour les informations d'un utilisateur
     */
    public function updateUser(int $userId, array $userData): User
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new UserNotFoundException();
        }

        // Valider les nouvelles données
        $this->validateUserUpdateData($userData);

        // Vérifier le changement d'email
        if (isset($userData['email']) && $userData['email'] !== $user->getEmail()) {
            if ($this->userRepository->emailExists($userData['email'])) {
                throw new ValidationException(['email' => 'Cet email est déjà utilisé']);
            }
            $user->setEmail($userData['email']);
            $user->setEmailVerified(false); // Nécessite une nouvelle vérification
        }

        // Mettre à jour les autres champs
        if (isset($userData['firstName'])) {
            $user->setFirstName($userData['firstName']);
        }

        if (isset($userData['lastName'])) {
            $user->setLastName($userData['lastName']);
        }

        // Changer le mot de passe si fourni
        if (isset($userData['password'])) {
            $this->validatePassword($userData['password']);
            $user->setPasswordHash($this->hashPassword($userData['password']));
        }

        return $this->userRepository->save($user);
    }

    /**
     * Désactive un utilisateur
     */
    public function deactivateUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new UserNotFoundException();
        }

        $user->setActive(false);
        $this->userRepository->save($user);

        return true;
    }

    /**
     * Réactive un utilisateur
     */
    public function reactivateUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new UserNotFoundException();
        }

        $user->setActive(true);
        $this->userRepository->save($user);

        return true;
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new UserNotFoundException();
        }

        // Vérifier l'ancien mot de passe
        if (!$user->verifyPassword($currentPassword)) {
            throw new ValidationException(['currentPassword' => 'Mot de passe actuel incorrect']);
        }

        // Valider le nouveau mot de passe
        $this->validatePassword($newPassword);

        // Mettre à jour
        $user->setPasswordHash($this->hashPassword($newPassword));
        $this->userRepository->save($user);

        return true;
    }

    /**
     * Recherche des utilisateurs
     */
    public function searchUsers(string $query, int $limit = 50): array
    {
        return $this->userRepository->search($query, $limit);
    }

    /**
     * Obtient les statistiques des utilisateurs
     */
    public function getUserStats(): array
    {
        return [
            'total_users' => $this->userRepository->countUsers(),
            'active_users' => $this->userRepository->countActiveUsers(),
            'verified_rate' => $this->calculateVerificationRate()
        ];
    }

    /**
     * Calcule le taux de vérification des emails
     */
    private function calculateVerificationRate(): float
    {
        $total = $this->userRepository->countUsers();
        $verified = $this->userRepository->countActiveUsers();

        return $total > 0 ? round(($verified / $total) * 100, 2) : 0;
    }

    /**
     * Valide les données utilisateur pour la création
     */
    private function validateUserData(array $data): void
    {
        $errors = [];

        // Email obligatoire et valide
        if (empty($data['email'])) {
            $errors['email'] = 'L\'email est obligatoire';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format d\'email invalide';
        }

        // Mot de passe obligatoire
        if (empty($data['password'])) {
            $errors['password'] = 'Le mot de passe est obligatoire';
        } else {
            try {
                $this->validatePassword($data['password']);
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->getErrors());
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Valide les données utilisateur pour la mise à jour
     */
    private function validateUserUpdateData(array $data): void
    {
        $errors = [];

        // Email valide si fourni
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format d\'email invalide';
        }

        // Mot de passe valide si fourni
        if (isset($data['password'])) {
            try {
                $this->validatePassword($data['password']);
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->getErrors());
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Valide un mot de passe
     */
    private function validatePassword(string $password): void
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Le mot de passe doit contenir au moins une majuscule';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Le mot de passe doit contenir au moins une minuscule';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Le mot de passe doit contenir au moins un chiffre';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors['password'] = 'Le mot de passe doit contenir au moins un caractère spécial';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Hash un mot de passe de façon sécurisée
     */
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
