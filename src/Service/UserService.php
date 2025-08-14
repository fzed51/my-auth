<?php

declare(strict_types=1);

namespace MyAuth\Service;

use MyAuth\Entity\User;
use MyAuth\Entity\EmailVerification;
use MyAuth\Repository\UserRepository;
use MyAuth\Repository\EmailVerificationRepository;
use MyAuth\Exception\UserAlreadyExistsException;
use MyAuth\Exception\UserNotFoundException;
use MyAuth\Exception\EmailVerificationException;
use DateTime;
use InvalidArgumentException;

class UserService extends AbstractService
{
    private UserRepository $userRepository;
    private EmailVerificationRepository $verificationRepository;
    private EmailService $emailService;

    public function __construct(
        UserRepository $userRepository,
        EmailVerificationRepository $verificationRepository,
        EmailService $emailService
    ) {
        $this->userRepository = $userRepository;
        $this->verificationRepository = $verificationRepository;
        $this->emailService = $emailService;
    }

    public function findById(string $userId): ?User
    {
        return $this->userRepository->findUserById($userId);
    }

    public function register(array $userData): User
    {
        $this->validateRegistrationData($userData);

        // Vérifier que l'email n'existe pas déjà
        if ($this->userRepository->emailExists($userData['email'])) {
            throw new UserAlreadyExistsException($userData['email']);
        }

        // Créer l'utilisateur
        $user = new User(
            id: $this->generateUuid(),
            email: strtolower(trim($userData['email'])),
            passwordHash: $this->hashPassword($userData['password']),
            firstName: trim($userData['firstName']),
            lastName: trim($userData['lastName']),
            isActive: false,  // Désactivé jusqu'à vérification email
            isVerified: false
        );

        // Sauvegarder en base
        $this->userRepository->create($user);

        // Créer et envoyer le token de vérification
        $this->createAndSendEmailVerification($user);

        $this->log('info', 'User registered successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $user;
    }

    public function verifyEmail(string $token): User
    {
        // Trouver le token de vérification
        $verification = $this->verificationRepository->findValidByToken($token);

        if (!$verification) {
            throw new EmailVerificationException('Invalid or expired verification token', 400);
        }

        // Récupérer l'utilisateur
        $user = $this->userRepository->findByIdOrFail($verification->getUserId());

        // Vérifier que l'utilisateur n'est pas déjà vérifié
        if ($user->isVerified()) {
            throw new EmailVerificationException('Email already verified', 409);
        }

        // Marquer le token comme utilisé
        $verification->markAsUsed();
        $this->verificationRepository->markAsUsed($verification);

        // Activer et vérifier l'utilisateur
        $user->verify();
        $user->activate();
        $this->userRepository->update($user);

        $this->log('info', 'Email verified successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $user;
    }

    public function resendVerificationEmail(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new UserNotFoundException($email);
        }

        if ($user->isVerified()) {
            throw new EmailVerificationException('Email already verified', 409);
        }

        // Vérifier qu'il n'y a pas eu d'envoi récent
        if ($this->verificationRepository->hasRecentVerification($user->getId(), 5)) {
            throw new EmailVerificationException('Verification email sent recently. Please wait 5 minutes.', 429);
        }

        $this->createAndSendEmailVerification($user);

        $this->log('info', 'Verification email resent', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);
    }

    public function updateProfile(string $userId, array $data): User
    {
        $user = $this->userRepository->findUserById($userId);
        if (!$user) {
            throw new UserNotFoundException("User not found with ID: {$userId}");
        }

        if (isset($data['firstName'])) {
            $user->updateProfile($data['firstName'], $user->getLastName());
        }

        if (isset($data['lastName'])) {
            $user->updateProfile($user->getFirstName(), $data['lastName']);
        }

        if (isset($data['firstName']) && isset($data['lastName'])) {
            $user->updateProfile($data['firstName'], $data['lastName']);
        }

        $this->userRepository->updateUser($user);

        return $user;
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->userRepository->findUserById($userId);
        if (!$user) {
            throw new UserNotFoundException("User not found with ID: {$userId}");
        }

        if (!password_verify($currentPassword, $user->getPasswordHash())) {
            throw new InvalidArgumentException('Current password is incorrect');
        }

        $hashedNewPassword = $this->hashPassword($newPassword);
        $user->updatePassword($hashedNewPassword);

        $this->userRepository->updateUser($user);
    }

    public function getUserById(string $userId): User
    {
        $user = $this->userRepository->findUserById($userId);
        if (!$user) {
            throw new UserNotFoundException("User not found with ID: {$userId}");
        }

        return $user;
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    public function deleteUser(string $userId): void
    {
        $user = $this->userRepository->findByIdOrFail($userId);

        // Supprimer les tokens de vérification
        $this->verificationRepository->deleteByUserId($userId);

        // Supprimer l'utilisateur
        $this->userRepository->delete($userId);

        $this->log('info', 'User deleted', [
            'user_id' => $userId,
            'email' => $user->getEmail()
        ]);
    }

    private function createAndSendEmailVerification(User $user): void
    {
        // Générer le token de vérification
        $verification = new EmailVerification(
            id: $this->generateUuid(),
            userId: $user->getId(),
            token: $this->generateSecureToken(64),
            expiresAt: new DateTime('+24 hours')
        );

        // Sauvegarder le token
        $this->verificationRepository->create($verification);

        // Envoyer l'email
        $this->emailService->sendVerificationEmail($user, $verification->getToken());
    }

    private function validateRegistrationData(array $data): void
    {
        $requiredFields = ['email', 'password', 'firstName', 'lastName'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                throw new InvalidArgumentException("Field '{$field}' is required");
            }
        }

        $this->validateEmail($data['email']);
        $this->validatePassword($data['password']);
        $this->validateName($data['firstName'], 'firstName');
        $this->validateName($data['lastName'], 'lastName');
    }

    private function validateName(string $name, string $field): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException("{$field} cannot be empty");
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException("{$field} too long (max 100 characters)");
        }
    }
}
