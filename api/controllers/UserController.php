<?php

namespace Controllers;

use Core\BaseController;
use Services\UserService;
use Services\AuthService;

class UserController extends BaseController
{
    private UserService $userService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = $this->getService(\Services\UserService::class);
        $this->authService = $this->getService(\Services\AuthService::class);
    }

    public function register(): void
    {
        try {
            $data = $this->getRequestData();
            
            if (!$this->validateRequired($data, ['email', 'password'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing required fields: email, password'
                ], 400);
                return;
            }

            // Public registration always creates editors; admins are created by admins
            $data['role'] = 'editor';

            $result = $this->userService->createUser($data);

            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User registered successfully',
                    'data' => ['id' => $result['user']['id']]
                ], 201);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function login(): void
    {
        try {
            $data = $this->getRequestData();
            
            if (!$this->validateRequired($data, ['email', 'password'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing email or password'
                ], 400);
                return;
            }

            $result = $this->authService->login($data['email'], $data['password']);

            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => ['user' => $result['user']]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 401);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 401);
        }
    }

    public function me(): void
    {
        try {
            $this->authService->requireAuth();
            
            $user = $this->getCurrentUser();

            $this->jsonResponse([
                'success' => true,
                'message' => 'Session is valid',
                'data' => ['user' => $user]
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'No active session'
            ], 401);
        }
    }

    public function listAll(): void
    {
        try {
            $this->authService->requireRole('admin');
            
            $users = $this->userService->listAllUsers();

            $this->jsonResponse([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 403);
        }
    }

    public function updateRole(int $userId): void
    {
        try {
            $this->authService->requireRole('admin');
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing user ID'
                ], 400);
                return;
            }

            $data = $this->getRequestData();
            
            if (!isset($data['role'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing role field'
                ], 400);
                return;
            }

            $result = $this->userService->updateUserRole($userId, $data['role']);

            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => $result['message']
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function delete(int $userId): void
    {
        try {
            $this->authService->requireRole('admin');
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing user ID'
                ], 400);
                return;
            }

            $result = $this->userService->deleteUser($userId);

            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => $result['message']
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function getOne(int $userId): void
    {
        try {
            $this->authService->requireRole('admin');
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing user ID'
                ], 400);
                return;
            }

            $user = $this->userService->getUserById($userId);

            if ($user) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User retrieved successfully',
                    'data' => $user
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'User not found'
                ], 404);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 404);
        }
    }

    public function findByEmail(): void
    {
        try {
            $this->authService->requireAuth();

            $data = $this->getRequestData();
            
            if (empty($data['email'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing required field: email'
                ], 400);
                return;
            }

            $user = $this->userService->getUserByEmail($data['email']);

            if ($user) {
                // Only what's needed to share a dashboard with this user
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User found',
                    'data' => ['id' => $user['id'], 'email' => $user['email']]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'User not found'
                ], 404);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function logout(): void
    {
        try {
            $this->authService->logout();

            $this->jsonResponse([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
}