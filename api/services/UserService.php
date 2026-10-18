<?php

namespace Services;

use Models\User;

class UserService
{
    private User $userModel;

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    public function createUser(array $data): array
    {
        // Validate required fields
        if (!isset($data['email'], $data['password'], $data['role'])) {
            return ['success' => false, 'error' => 'Email, password, and role are required'];
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        if (!is_string($data['password']) || strlen($data['password']) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        // Check if user already exists
        if ($this->userModel->findByEmail($data['email'])) {
            return ['success' => false, 'error' => 'User with this email already exists'];
        }

        // Validate role
        if (!in_array($data['role'], ['editor', 'admin'])) {
            return ['success' => false, 'error' => 'Invalid role specified'];
        }

        try {
            $result = $this->userModel->create($data['email'], $data['password'], $data['role']);
            
            if ($result['success']) {
                $newUser = $this->userModel->findById($result['id']);
                return ['success' => true, 'user' => $newUser];
            }
            
            return ['success' => false, 'error' => $result['error']];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    public function updateUserRole(int $userId, string $role): array
    {
        // Validate role
        if (!in_array($role, ['editor', 'admin'])) {
            return ['success' => false, 'error' => 'Invalid role specified'];
        }

        // Check if this would remove the last admin
        if ($this->isLastAdmin($userId, $role)) {
            return ['success' => false, 'error' => 'Cannot remove the last admin'];
        }

        return $this->userModel->updateRole($userId, $role);
    }

    public function deleteUser(int $userId): array
    {
        // Check if this would delete the last admin
        if ($this->isLastAdmin($userId)) {
            return ['success' => false, 'error' => 'Cannot delete the last admin'];
        }

        return $this->userModel->delete($userId);
    }

    public function listAllUsers(): array
    {
        try {
            return $this->userModel->listAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getUserById(int $userId): ?array
    {
        return $this->userModel->findById($userId);
    }

    private function isLastAdmin(int $userId, ?string $newRole = null): bool
    {
        $allUsers = $this->userModel->listAll();
        $targetUser = null;
        $adminCount = 0;

        foreach ($allUsers as $user) {
            if ($user['role'] === 'admin') {
                $adminCount++;
            }
            if ($user['id'] == $userId) {
                $targetUser = $user;
            }
        }

        // If the target user is an admin and they are the only one
        if ($targetUser && $targetUser['role'] === 'admin' && $adminCount === 1) {
            // And if the action is to delete them or change their role
            if ($newRole === null || $newRole !== 'admin') {
                return true;
            }
        }

        return false;
    }

    public function getUserByEmail(string $email): ?array
    {
        return $this->userModel->findByEmail($email);
    }
}
