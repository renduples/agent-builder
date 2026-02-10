# Quick Start: Running Tests

Get up and running with Agent Builder tests in 5 minutes.

The plugin must be deployed to a WordPress site to run tests. Tests use the actual WordPress installation and a separate test database.

## Prerequisites

- WordPress site (e.g., `/Users/r3n13r/Code/agentic/`)

- PHP 8.1+
- Composer
- MySQL 8.0+
- Git

## 1. Navigate to Plugin Directory

```bash
cd /Users/r3n13r/Code/agentic/wp-content/plugins/agent-builder
```

## 2. Install Test Dependencies

```bash
composer install --dev
```

**Expected**: 33 packages installed (PHPUnit, Mockery, Brain/Monkey, etc.)

**Note**: This installs PHPUnit and test dependencies WITHOUT affecting your WordPress site.

## 3. Configure WordPress Test Environment

The plugin needs to know where your WordPress installation is:

```bash
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
export WP_CORE_DIR="/Users/r3n13r/Code/agentic"
```

Or add to your `~/.zshrc`:
```bash
echo 'export WP_TESTS_DIR="/tmp/wordpress-tests-lib"' >> ~/.zshrc
echo 'export WP_CORE_DIR="/Users/r3n13r/Code/agentic"' >> ~/.zshrc
source ~/.zshrc
```

## 4. Install WordPress Test Suite

**✅ Already Installed** (during initial setup at `/tmp/wordpress-tests-lib/`)

To verify installation:
```bash
ls -la /tmp/wordpress-tests-lib/
# Should show: data/, includes/, wp-tests-config.php
```

If test suite is missing, reinstall:
```bash
# Use the curl-based installer (no SVN required):
bin/install-wp-tests-curl.sh wordpress_test root '' localhost latest
```

**Parameters**:
- `wordpress_test` - Test database name
- `root` - MySQL username  
- `''` - MySQL password (empty if no password)
- `localhost` - MySQL host
- `latest` - WordPress version

**Note**: This creates a separate test database - it does NOT touch your live WordPress site.

## 5. Configure Test Database (Current Blocker)

**⚠️ MySQL Access Required** - This is the current step needed to run tests.

If your MySQL root user has a password:

```bash
# Create a test user without password
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'wp_test'@'localhost'; 
GRANT ALL ON wordpress_test.* TO 'wp_test'@'localhost'; 
FLUSH PRIVILEGES;"
```

Then update `/tmp/wordpress-tests-lib/wp-tests-config.php`:
```php
define( 'DB_USER', 'wp_test' );      // Change from 'root'
define( 'DB_PASSWORD', '' );          // Empty password
```

## 6. Run Your First Test

```bash
# Run all tests
./tests/run-tests.sh

# Run unit tests only (faster)
./tests/run-tests.sh --unit

# Run integration tests (requires WordPress)
./tests/run-tests.sh --integration
```

**Expected Output**:
```
Agent Builder Test Runner

Running unit tests...

PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

............................................ 37 / 37 (100%)

Time: XX.XXX seconds, Memory: XX.XX MB

OK (37 tests, XX assertions)

✓ All tests passed!
```

## 7. Generate Coverage Report

```bash
./tests/run-tests.sh --coverage
```

**View Report**:
```bash
open coverage-report/index.html
```

## 8. Verify Plugin Still Works

After running tests, check your WordPress site:

```bash
# Navigate to your WordPress site
cd /Users/r3n13r/Code/agentic

# Check plugin status
wp plugin list | grep agent-builder

# Visit admin dashboard
open http://agentic.test/wp-admin
```

### Key Differences: Repository vs WordPress Site

| Aspect | Development Repo | WordPress Site |
|--------|-----------------|----------------|
| **Location** | `/Users/r3n13r/Code/agent-builder/` | `/Users/r3n13r/Code/agentic/wp-content/plugins/agent-builder/` |
| **Composer** | Always installed | Run `composer install --dev` |
| **WordPress** | Loaded from temp directory | Uses actual WP installation |
| **Database** | Test DB only | Test DB + Live DB (separate) |
| **Safety** | Isolated | Tests won't touch live data |

### Safety Checklist

✅ Test database is `wordpress_test` (NOT your live DB)  
✅ Test config at `/tmp/wordpress-tests-lib/wp-tests-config.php`  
✅ Tests use temp WordPress directory, not your live site  
✅ Plugin remains active and functional after tests  
✅ No test data pollutes your live database  

### Important Notes

1. **Tests are isolated**: Test database (`wordpress_test`) is separate from your live database
2. **Composer dev dependencies**: Only installed when running tests, not in production
3. **Symlink friendly**: Tests work with symlinked plugin directory
4. **CI/CD ready**: Same test suite runs in GitHub Actions

---

## Troubleshooting

### "WordPress test suite not found"
**Solution**: Run the install script from step 2 above.

### "Database connection error"
**Solution**: 
1. Check MySQL is running: `mysql.server status`
2. Verify credentials in install script parameters
3. Test connection: `mysql -u root -p`

### "Permission denied" on scripts
**Solution**:
```bash
chmod +x bin/install-wp-tests.sh
chmod +x tests/run-tests.sh
```

### "Class not found" errors
**Solution**:
```bash
composer dump-autoload
```

### Tests fail with "table doesn't exist"
**Solution**: Reinstall test database:
```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS wordpress_test; CREATE DATABASE wordpress_test;"
bin/install-wp-tests-curl.sh wordpress_test wp_test '' localhost latest
```

### Tests pass but plugin broken on site
**Cause**: Test database corrupted live database  
**Prevention**: Always check test config uses `wordpress_test`, not your live DB name

### "Cannot redeclare function" errors
**Cause**: WordPress already loaded (testing on active site)  
**Solution**: Tests should load WordPress fresh, not use already-loaded instance

### Tests slow on WordPress site
**Cause**: WordPress loading overhead  
**Solution**: Use `--unit` flag to skip integration tests:
```bash
./tests/run-tests.sh --unit
```

## Next Steps

1. **Read Test Documentation**: `tests/README.md`
2. **Review Test Plan**: `.github/TESTING_PLAN.md`
3. **Check Implementation**: `.github/TEST_IMPLEMENTATION_SUMMARY.md`
4. **Run Manual Tests**: `tests/manual/testing-procedures.md`

## Test Structure at a Glance

```
tests/
├── unit/              # 37 tests - Fast, isolated
├── integration/       # 11 tests - With WordPress/DB
├── manual/            # 17 procedures - UI/UX
└── helpers/           # Test utilities

Current Coverage: 48 automated tests (74% automation)
Target: 90% automation (150+ tests)
```

## CI/CD Status

Tests run automatically on:
- Every push to `main` or `develop`
- Every pull request
- Daily at 2 AM UTC

**View Results**: GitHub Actions tab in repository

## Writing Your First Test

Create `tests/unit/test-my-feature.php`:

```php
<?php
namespace Agentic\Tests;

class Test_My_Feature extends TestCase {
    
    public function test_my_functionality() {
        // Arrange
        $input = 'test data';
        
        // Act
        $result = my_function($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

Run it:
```bash
./vendor/bin/phpunit tests/unit/test-my-feature.php
```

## Help & Resources

- **Documentation**: `tests/README.md`
- **PHPUnit Docs**: https://phpunit.de/documentation.html
- **WordPress Testing**: https://make.wordpress.org/core/handbook/testing/
- **GitHub Issues**: Report test failures or bugs

---

**Last Updated**: February 9, 2026  
**Version**: v1.3.0  
**Status**: ✅ Test infrastructure ready
