# Model Testing Suite

This directory contains comprehensive tests for our KPI Analytics system models.

## Test Structure

- `bootstrap.php` - Test environment setup
- `TestCase.php` - Base test case with common functionality
- `ModelIntegrationTest.php` - Tests all models work together
- `UserModelTest.php` - User model specific tests
- `DashboardModelTest.php` - Dashboard model specific tests
- `KpiModelTest.php` - KPI model specific tests
- `KpiEntryModelTest.php` - KPI Entry model specific tests
- `ShareTokenModelTest.php` - Share Token model specific tests

## Running Tests

Tests delete data, so they refuse to run unless the database name ends in `_test`.
They use `clairvoyance_v3_test` by default; override `DB_NAME` (or other DB settings)
in `api/.env.test`. Create that database and run the migrations against it first.

### Run All Tests
```bash
php tests/ModelIntegrationTest.php
```

### Run Individual Model Tests
```bash
php tests/UserModelTest.php
php tests/DashboardModelTest.php
php tests/KpiModelTest.php
php tests/KpiEntryModelTest.php
php tests/ShareTokenModelTest.php
```

### Run All Tests at Once
```bash
php tests/ModelIntegrationTest.php && \
php tests/UserModelTest.php && \
php tests/DashboardModelTest.php && \
php tests/KpiModelTest.php && \
php tests/KpiEntryModelTest.php && \
php tests/ShareTokenModelTest.php
```

## Test Environment

Tests use a separate test database configuration. Create a `.env.test` file:

```env
DB_HOST=localhost
DB_NAME=clairvoyance_v3_test
DB_USER=root
DB_PASS=
```

## What Tests Cover

### Model Integration Tests
- ✅ Dependency injection resolution
- ✅ Database connection
- ✅ Model relationships
- ✅ Transaction handling

### User Model Tests
- ✅ User creation
- ✅ User authentication
- ✅ Data validation
- ✅ Password hashing

### Dashboard Model Tests
- ✅ Dashboard CRUD operations
- ✅ Layout validation
- ✅ Access management
- ✅ Owner relationships

### KPI Model Tests
- ✅ KPI CRUD operations
- ✅ RAG calculations
- ✅ Data validation
- ✅ Statistics

### KPI Entry Model Tests
- ✅ Entry CRUD operations
- ✅ Data aggregation
- ✅ Date range queries
- ✅ Bulk operations

### Share Token Model Tests
- ✅ Token generation
- ✅ Expiration handling
- ✅ Validation
- ✅ Cleanup operations

## Test Data Cleanup

All tests automatically clean up test data after running to ensure a clean state for subsequent tests.

## Test Results

When you run the tests, you'll see output like this:

```
🚀 Starting Model Integration Tests
=====================================

🔗 Testing Dependency Injection...
✅ All models resolved successfully

🗄️  Testing Database Connection...
✅ Database connection successful

🔗 Testing Model Relationships...
✅ Model relationships working correctly

💾 Testing Transaction Handling...
✅ Transaction handling working correctly

🎉 All Model Integration Tests Passed!
=====================================
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure your test database exists
   - Check your `.env.test` file configuration
   - Verify database credentials

2. **Model Resolution Error**
   - Check that all models have proper namespaces
   - Verify Application.php has correct DI registration
   - Ensure Composer autoloader is working

3. **Test Data Cleanup Issues**
   - Check foreign key constraints
   - Verify cleanup order (reverse dependency order)
   - Ensure test data uses unique identifiers

### Debug Mode

To enable debug mode, modify `bootstrap.php`:

```php
// Set up test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/test_errors.log');
```

## Adding New Tests

To add new tests:

1. **Create a new test file** following the naming convention: `{ModelName}ModelTest.php`
2. **Extend TestCase** class for common functionality
3. **Add test methods** with descriptive names starting with `test`
4. **Use assertion methods** from TestCase class
5. **Clean up test data** in the `finally` block

Example:

```php
<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

class NewModelTest extends TestCase
{
    public function testNewFunctionality()
    {
        echo "🧪 Testing New Functionality...\n";
        
        $model = $this->container->resolve(\Models\NewModel::class);
        
        // Your test logic here
        $this->assertTrue($condition, "Description of what should be true");
        
        echo "✅ New functionality test passed\n\n";
    }
    
    public function runAllTests()
    {
        echo "🚀 Starting New Model Tests\n";
        echo "============================\n\n";
        
        try {
            $this->testNewFunctionality();
            
            echo "🎉 All New Model Tests Passed!\n";
            echo "============================\n";
            
        } catch (\Exception $e) {
            echo "❌ Test Failed: " . $e->getMessage() . "\n";
            exit(1);
        } finally {
            $this->cleanupTestData();
        }
    }
}

// Run the tests
$test = new NewModelTest();
$test->runAllTests();
```

## Performance Testing

For performance testing, you can add timing to your tests:

```php
public function testPerformance()
{
    echo "⏱️  Testing Performance...\n";
    
    $startTime = microtime(true);
    
    // Your test logic here
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    echo "✅ Performance test completed in " . round($executionTime, 4) . " seconds\n\n";
    
    // Assert performance requirements
    $this->assertTrue($executionTime < 1.0, "Test should complete in less than 1 second");
}
```

## Continuous Integration

These tests are designed to be run in CI/CD pipelines. They:

- Exit with proper status codes (0 for success, 1 for failure)
- Provide clear output for logging
- Clean up after themselves
- Don't require external dependencies beyond the database

To run in CI:

```bash
# Install dependencies
composer install

# Run migrations on test database
php scripts/migrate.php

# Run all tests
php tests/ModelIntegrationTest.php && \
php tests/UserModelTest.php && \
php tests/DashboardModelTest.php && \
php tests/KpiModelTest.php && \
php tests/KpiEntryModelTest.php && \
php tests/ShareTokenModelTest.php
```
