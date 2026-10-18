<?php

namespace Controllers;

use Core\BaseController;
use Services\DashboardService;
use Services\ShareTokenService;
use Services\AuthService;

class DashboardController extends BaseController
{
    private DashboardService $dashboardService;
    private ShareTokenService $shareTokenService;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->dashboardService = $this->getService(\Services\DashboardService::class);
        $this->shareTokenService = $this->getService(\Services\ShareTokenService::class);
        $this->authService = $this->getService(\Services\AuthService::class);
    }

    public function create(): void
    {
        try {
            $this->authService->requireRole('editor');
            
            $data = $this->getRequestData();
            
            if (!$this->validateRequired($data, ['name', 'layout'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing required fields: name, layout'
                ], 400);
                return;
            }

            $currentUser = $this->getCurrentUser();
            
            $result = $this->dashboardService->create($currentUser, $data);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dashboard created successfully',
                'data' => ['id' => $result['id']]
            ], 201);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function get(int $id): void
    {
        try {
            $this->authService->requireAuth();
            
            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing dashboard ID'
                ], 400);
                return;
            }

            // Check dashboard access permission
            $this->authService->requireDashboardPermission($id, 'viewer');

            $currentUser = $this->getCurrentUser();
            
            $dashboard = $this->dashboardService->get($currentUser, $id);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dashboard retrieved successfully',
                'data' => ['dashboard' => $dashboard]
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 403);
        }
    }

    public function listAll(): void
    {
        try {
            $this->authService->requireAuth();
            
            $currentUser = $this->getCurrentUser();
            
            $dashboards = $this->dashboardService->list($currentUser);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dashboards retrieved successfully',
                'data' => ['dashboards' => $dashboards]
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function update(int $id): void
    {
        try {
            $this->authService->requireAuth();
            
            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing dashboard ID'
                ], 400);
                return;
            }

            // Check dashboard edit permission
            $this->authService->requireDashboardPermission($id, 'editor');

            $data = $this->getRequestData();
            
            if (empty($data)) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'No update data provided'
                ], 400);
                return;
            }

            $currentUser = $this->getCurrentUser();
            
            $this->dashboardService->update($currentUser, $id, $data);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dashboard updated successfully'
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function delete(int $id): void
    {
        try {
            $this->authService->requireRole('editor');
            
            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing dashboard ID'
                ], 400);
                return;
            }

            $currentUser = $this->getCurrentUser();
            
            $this->dashboardService->delete($currentUser, $id);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dashboard deleted successfully'
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function assignViewer(int $dashboardId): void
    {
        try {
            $this->authService->requireAuth();
            
            // Check if user has permission to manage dashboard access
            $this->authService->requireDashboardPermission($dashboardId, 'owner');
            
            $data = $this->getRequestData();
            
            if (!$this->validateRequired($data, ['user_id'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing required field: user_id'
                ], 400);
                return;
            }

            // Optional permission level, defaults to 'viewer'
            $permissionLevel = $data['permission_level'] ?? 'viewer';
            
            // Validate permission level
            if (!in_array($permissionLevel, ['owner', 'editor', 'viewer'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Invalid permission level. Must be owner, editor, or viewer'
                ], 400);
                return;
            }

            $currentUser = $this->getCurrentUser();
            
            $this->dashboardService->addUserAccess($currentUser, $dashboardId, (int)$data['user_id'], $permissionLevel);

            $this->jsonResponse([
                'success' => true,
                'message' => 'User access granted successfully',
                'data' => ['permission_level' => $permissionLevel]
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function generateShareLink(int $dashboardId): void
    {
        try {
            $this->authService->requireAuth();
            
            // Check if user has permission to manage dashboard access (owner only)
            $this->authService->requireDashboardPermission($dashboardId, 'owner');
            
            $currentUser = $this->getCurrentUser();
            
            $result = $this->shareTokenService->generate($currentUser, $dashboardId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Share token created successfully',
                'data' => ['token' => $result['token']]
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 403);
        }
    }

    public function redeemShareLink(string $token): void
    {
        try {
            // Debug logging
            error_log("RedeemShareLink called with token: " . $token);
            
            $this->authService->requireAuth();
            
            $currentUser = $this->getCurrentUser();
            error_log("Current user: " . json_encode($currentUser));
            
            $dashboardId = $this->shareTokenService->redeem($currentUser, $token);
            error_log("Dashboard ID returned: " . $dashboardId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Share token redeemed successfully',
                'data' => ['dashboard_id' => $dashboardId]
            ]);

        } catch (\Exception $e) {
            error_log("RedeemShareLink error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function getReportData(int $id): void
    {
        try {
            $this->authService->requireAuth();
            
            if (!$id) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Missing dashboard ID'
                ], 400);
                return;
            }

            $currentUser = $this->getCurrentUser();
            
            $dashboard = $this->dashboardService->get($currentUser, $id);
            
            // Parse the layout to get widget information
            $layout = [];
            if (isset($dashboard['layout'])) {
                $layoutData = $dashboard['layout'];
                if (is_string($layoutData)) {
                    $layout = json_decode($layoutData, true) ?: [];
                } elseif (is_array($layoutData)) {
                    $layout = $layoutData;
                }
            }

            // Prepare report data structure
            $reportData = [
                'name' => $dashboard['name'],
                'description' => $dashboard['description'] ?? '',
                'widgets' => $layout
            ];

            $this->jsonResponse([
                'success' => true,
                'message' => 'Report data retrieved successfully',
                'data' => $reportData
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 403);
        }
    }

    public function removeViewer(int $dashboardId, int $userId): void
    {
        try {
            $this->authService->requireAuth();
            
            // Check if user has permission to manage dashboard access
            $this->authService->requireDashboardPermission($dashboardId, 'owner');
            
            $currentUser = $this->getCurrentUser();
            
            $this->dashboardService->removeUserAccess($currentUser, $dashboardId, $userId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'User access removed successfully'
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function getUsers(int $dashboardId): void
    {
        try {
            $this->authService->requireAuth();

            $currentUser = $this->getCurrentUser();
            $users = $this->dashboardService->getDashboardUsers($currentUser, $dashboardId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Dashboard users retrieved successfully',
                'data' => ['users' => $users]
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }
}