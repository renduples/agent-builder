# Agent Builder Test Suite

Comprehensive test suite for the Agent Builder WordPress plugin with 90% test automation.

## Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Install WordPress Test Suite

```bash
bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Parameters:
- `wordpress_test` - Database name for tests
- `root` - MySQL username
- `''` - MySQL password (empty if no password)
- `localhost` - MySQL host
- `latest` - WordPress version (or specific version like `6.4`)

## Running Tests

### All Tests

```bash
./tests/run-tests.sh
```

### Unit Tests Only

```bash
./tests/run-tests.sh --unit
```

### Integration Tests Only

```bash
./tests/run-tests.sh --integration
```

### With Code Coverage

```bash
./tests/run-tests.sh --coverage
```

Coverage report will be generated in `coverage-report/index.html`

### Filter Specific Tests

```bash
./tests/run-tests.sh --filter test_llm_client
```

### Direct PHPUnit Commands

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/unit/test-llm-client.php

# Run specific test method
./vendor/bin/phpunit --filter test_client_instantiation
```

## Test Structure

```
tests/
├── unit/              # Unit tests (isolated components)
│   ├── test-llm-client.php
│   ├── test-chat-security.php
│   ├── test-job-manager.php
│   └── ...
├── integration/       # Integration tests (with WordPress/DB)
│   ├── test-agent-lifecycle.php
│   ├── test-rest-api.php
│   └── ...
├── manual/            # Manual test procedures (markdown)
│   ├── frontend-testing.md
│   └── admin-ui-testing.md
├── helpers/           # Test utilities
│   ├── TestCase.php
│   ├── MockWPFunctions.php
│   └── TestDataFactory.php
└── bootstrap.php      # Test initialization
```

## Test Coverage Goals

- **Unit Tests**: 100% coverage for core components
  - LLM_Client
  - Chat_Security
  - Job_Manager
  - Audit_Log
  - Agent_Base
  - Agent_Controller
  
- **Integration Tests**: 90-95% coverage
  - REST API endpoints
  - Database operations
  - Agent lifecycle
  - Chat flow
  
- **Manual Tests**: 10% (UI/UX only)
  - Frontend chat interface
  - Admin dashboard

## Writing Tests

### Unit Test Example

```php
<?php
namespace Agentic\Tests;

class Test_My_Class extends TestCase {
    
    public function test_my_method() {
        $instance = new My_Class();
        $result = $instance->my_method();
        
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Example

```php
<?php
namespace Agentic\Tests;

class Test_My_Integration extends TestCase {
    
    public function test_database_operation() {
        global $wpdb;
        
        // Your test code...
        
        $this->assertGreaterThan(0, $wpdb->insert_id);
    }
}
```

### Using Test Helpers

```php
// Create test data
$config = TestDataFactory::llm_config();
$message = TestDataFactory::chat_message();
$agent = TestDataFactory::agent_metadata();

// Create test agent
$agent_id = 'my-test-agent';
$this->create_test_agent($agent_id);

// Cleanup happens automatically in tearDown()
```

## Continuous Integration

Tests run automatically on GitHub Actions for:
- Every push to `main`
- Every pull request
- Daily at 2 AM UTC

See `.github/workflows/tests.yml` for configuration.

## Troubleshooting

### WordPress Test Suite Not Found

```bash
bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database Connection Errors

Check your MySQL credentials in the install script parameters.

### Permission Errors

```bash
chmod +x bin/install-wp-tests.sh
chmod +x tests/run-tests.sh
```

### Clear Test Data

```bash
# Drop and recreate test database
mysql -u root -e "DROP DATABASE IF EXISTS wordpress_test; CREATE DATABASE wordpress_test;"
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Mockery Documentation](http://docs.mockery.io/)
- [Testing Plan](../.github/TESTING_PLAN.md)
