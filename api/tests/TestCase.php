<?php

/**
 * Base Test Case Class
 * 
 * Provides common functionality for all model tests.
 */

class TestCase
{
    protected $app;
    protected $container;
    protected $db;
    
    public function __construct()
    {
        $this->app = \Core\Application::getInstance();
        $this->container = $this->app->getContainer();
        $this->db = $this->container->resolve(\PDO::class);
    }
    
    protected function assertTrue($condition, $message = '')
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: " . $message);
        }
    }
    
    protected function assertFalse($condition, $message = '')
    {
        if ($condition) {
            throw new \Exception("Assertion failed: " . $message);
        }
    }
    
    protected function assertEquals($expected, $actual, $message = '')
    {
        if ($expected != $actual) {
            throw new \Exception("Assertion failed: Expected '{$expected}', got '{$actual}'. " . $message);
        }
    }
    
    protected function assertNotNull($value, $message = '')
    {
        if ($value === null) {
            throw new \Exception("Assertion failed: Value is null. " . $message);
        }
    }
    
    protected function assertArrayHasKey($key, $array, $message = '')
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception("Assertion failed: Array does not have key '{$key}'. " . $message);
        }
    }
    
    protected function cleanupTestData()
    {
        // Last line of defence: never wipe a non-test database
        $dbName = $this->db->query('SELECT DATABASE()')->fetchColumn();
        if (substr((string)$dbName, -5) !== '_test') {
            throw new \Exception("Refusing to clean up non-test database '{$dbName}'");
        }

        // Clean up test data in reverse dependency order
        $this->db->exec("DELETE FROM kpi_entries");
        $this->db->exec("DELETE FROM kpis");
        $this->db->exec("DELETE FROM dashboard_share_tokens");
        $this->db->exec("DELETE FROM dashboard_access");
        $this->db->exec("DELETE FROM dashboards");
        $this->db->exec("DELETE FROM users WHERE email LIKE 'test_%'");
    }
    
    protected function createTestUser($email = 'test_user@example.com', $role = 'editor')
    {
        $userModel = $this->container->resolve(\Models\User::class);
        $result = $userModel->create($email, 'test_password', $role);
        
        if (!$result['success']) {
            throw new \Exception("Failed to create test user: " . $result['error']);
        }
        
        return $result['id'];
    }
    
    protected function createTestDashboard($userId, $name = 'Test Dashboard')
    {
        $dashboardModel = $this->container->resolve(\Models\Dashboard::class);
        $layout = json_encode([['id' => 1, 'type' => 'widget', 'title' => 'Test Widget']]);
        $result = $dashboardModel->create($name, 'Test Description', $layout, $userId);
        
        if (!$result['success']) {
            throw new \Exception("Failed to create test dashboard: " . $result['error']);
        }
        
        return $result['id'];
    }
    
    protected function createTestKpi($userId, $name = 'Test KPI')
    {
        $kpiModel = $this->container->resolve(\Models\Kpi::class);
        $data = [
            'name' => $name,
            'direction' => 'higher_is_better',
            'target' => 100.0,
            'rag_red' => 50.0,
            'rag_amber' => 75.0,
            'format_prefix' => '$',
            'format_suffix' => ''
        ];
        $result = $kpiModel->create($data, $userId);
        
        if (!$result['success']) {
            throw new \Exception("Failed to create test KPI: " . $result['error']);
        }
        
        return $result['id'];
    }
}