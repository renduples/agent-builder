# System Requirements Checker for Agentic Plugin

## Problem Statement

When customers install the Agentic plugin on their WordPress sites, the Agent Builder may fail with timeout errors if their server configuration doesn't meet minimum requirements. Currently, users only discover these issues **after** the plugin fails, leading to:

- Poor user experience
- Support tickets
- Refund requests
- Negative reviews

### Root Cause

The Agent Builder makes LLM API calls that can take 60-120 seconds to generate complete agent code. If the customer's server has:

- **Nginx/Apache timeout** < 100 seconds
- **PHP `max_execution_time`** < 120 seconds  
- **Cloudflare/proxy timeout** < 100 seconds
- **WordPress REST API timeout** < 120 seconds

...the request will fail with a 504 Gateway Timeout error, showing a generic "Connection error" message.

## Solution

Add a **System Requirements Checker** to the plugin admin settings page that:

1. **Tests the server configuration** before the user tries to use the Agent Builder
2. **Shows clear pass/fail status** for each requirement
3. **Provides actionable fix instructions** for failed requirements
4. **Can be re-run** after configuration changes

## Implementation Specification

### Location

Add a new section to `/admin/settings.php` after the License section and before Response Caching:

```php
<h2>System Requirements</h2>
<p>Check if your server meets the minimum requirements for the Agent Builder.</p>
```

### Requirements to Check

#### 1. PHP Configuration

| Requirement | Minimum | Check Method | Fix Instructions |
|------------|---------|--------------|------------------|
| PHP Version | 8.0+ | `phpversion()` | Update PHP via hosting control panel |
| `max_execution_time` | 120 seconds | `ini_get('max_execution_time')` | Add to wp-config.php: `@ini_set('max_execution_time', 120);` |
| `memory_limit` | 256M | `ini_get('memory_limit')` | Add to wp-config.php: `define('WP_MEMORY_LIMIT', '256M');` |
| `upload_max_filesize` | 64M | `ini_get('upload_max_filesize')` | Update php.ini or add to .htaccess |

#### 2. WordPress Configuration

| Requirement | Minimum | Check Method | Fix Instructions |
|------------|---------|--------------|------------------|
| WordPress Version | 6.0+ | `get_bloginfo('version')` | Update WordPress core |
| REST API enabled | Yes | `rest_url()` accessible | Check if REST API is blocked by security plugin |
| Permalinks | Not default | `get_option('permalink_structure')` | Go to Settings → Permalinks, choose any non-default option |

#### 3. Server Timeout Test (Critical)

**Test Method:** Make a test request to a custom REST endpoint that sleeps for 90 seconds, then returns success.

```php
// In the plugin, register a test endpoint:
register_rest_route('agentic/v1', '/timeout-test', [
    'methods' => 'GET',
    'callback' => function() {
        set_time_limit(120);
        sleep(90); // Simulate long-running request
        return ['success' => true, 'duration' => 90];
    },
    'permission_callback' => function() {
        return current_user_can('manage_options');
    }
]);
```

**Test from JavaScript:**

```javascript
const testTimeout = async () => {
    const start = Date.now();
    try {
        const response = await fetch('/wp-json/agentic/v1/timeout-test', {
            timeout: 120000 // 120 second timeout
        });
        const duration = (Date.now() - start) / 1000;
        
        if (response.ok && duration >= 85) {
            return { pass: true, duration };
        } else {
            return { pass: false, duration, error: 'Server timeout too short' };
        }
    } catch (error) {
        const duration = (Date.now() - start) / 1000;
        return { 
            pass: false, 
            duration, 
            error: duration < 85 ? 'Gateway/proxy timeout detected' : error.message 
        };
    }
};
```

#### 4. LLM API Connectivity

| Requirement | Check Method | Fix Instructions |
|------------|--------------|------------------|
| API Key configured | Check if `agentic_llm_api_key` option exists | Go to Settings, enter API key |
| API reachable | Test API call (already exists in settings.php) | Check firewall/proxy settings |
| Outbound HTTPS allowed | `wp_remote_get('https://api.openai.com')` | Contact hosting provider |

### UI Implementation

Add a button to run the checker:

```php
<table class="form-table">
    <tr>
        <th scope="row">System Check</th>
        <td>
            <button type="button" id="agentic-system-check" class="button">
                <span class="dashicons dashicons-admin-tools" style="vertical-align: -2px; margin-right: 4px;"></span>
                Run System Check
            </button>
            <span id="agentic-check-spinner" class="spinner" style="float: none; margin-left: 8px; display: none;"></span>
            <p class="description">
                Test your server configuration to ensure the Agent Builder will work properly.
            </p>
        </td>
    </tr>
</table>

<div id="agentic-system-results" style="display: none; margin-top: 20px;">
    <!-- Results populated via JavaScript -->
</div>
```

### Results Display

Show results in a table with color-coded status:

```html
<table class="widefat" style="max-width: 800px;">
    <thead>
        <tr>
            <th>Requirement</th>
            <th>Status</th>
            <th>Details</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <tr class="pass">
            <td>PHP Version</td>
            <td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Pass</td>
            <td>8.2.10 (required: 8.0+)</td>
            <td>—</td>
        </tr>
        <tr class="fail">
            <td>Server Timeout</td>
            <td><span class="dashicons dashicons-no-alt" style="color: #b91c1c;"></span> Fail</td>
            <td>Timeout at 60s (required: 100s+)</td>
            <td>
                <details>
                    <summary>Show fix</summary>
                    <p>Contact your hosting provider to increase these settings:</p>
                    <ul>
                        <li>Nginx: <code>fastcgi_read_timeout 300;</code></li>
                        <li>Apache: <code>TimeOut 300</code></li>
                        <li>Cloudflare: Upgrade to Business plan or use orange cloud bypass</li>
                    </ul>
                </details>
            </td>
        </tr>
    </tbody>
</table>
```

### Status Indicators

Use WordPress admin color scheme:

- ✅ **Pass** (green `#22c55e`): `<span class="dashicons dashicons-yes-alt"></span>`
- ❌ **Fail** (red `#b91c1c`): `<span class="dashicons dashicons-no-alt"></span>`
- ⚠️ **Warning** (yellow `#f59e0b`): `<span class="dashicons dashicons-warning"></span>`

### JavaScript Implementation

```javascript
document.getElementById('agentic-system-check').addEventListener('click', async function() {
    const btn = this;
    const spinner = document.getElementById('agentic-check-spinner');
    const resultsDiv = document.getElementById('agentic-system-results');
    
    btn.disabled = true;
    spinner.style.display = 'inline-block';
    
    try {
        // Run all checks
        const results = await fetch('/wp-json/agentic/v1/system-check', {
            headers: { 'X-WP-Nonce': agenticSettings.nonce }
        });
        const data = await results.json();
        
        // Display results table
        resultsDiv.innerHTML = buildResultsTable(data.checks);
        resultsDiv.style.display = 'block';
        
        // Show summary
        const passed = data.checks.filter(c => c.status === 'pass').length;
        const total = data.checks.length;
        
        if (passed === total) {
            resultsDiv.insertAdjacentHTML('afterbegin', 
                '<div class="notice notice-success inline"><p><strong>All checks passed!</strong> Your server is ready for the Agent Builder.</p></div>'
            );
        } else {
            resultsDiv.insertAdjacentHTML('afterbegin', 
                '<div class="notice notice-error inline"><p><strong>Some checks failed.</strong> Please fix the issues below before using the Agent Builder.</p></div>'
            );
        }
        
    } catch (error) {
        resultsDiv.innerHTML = '<div class="notice notice-error inline"><p>System check failed: ' + error.message + '</p></div>';
        resultsDiv.style.display = 'block';
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
    }
});
```

### Backend Endpoint

Create a new REST endpoint in the plugin:

**File:** `/includes/class-system-checker.php`

```php
<?php
namespace Agentic;

class System_Checker {
    
    public static function register_routes() {
        register_rest_route('agentic/v1', '/system-check', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'run_system_check'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('agentic/v1', '/timeout-test', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'test_timeout'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    public static function run_system_check() {
        $checks = [];
        
        // PHP Version
        $php_version = phpversion();
        $checks[] = [
            'name' => 'PHP Version',
            'status' => version_compare($php_version, '8.0', '>=') ? 'pass' : 'fail',
            'value' => $php_version,
            'required' => '8.0+',
            'fix' => 'Update PHP via your hosting control panel'
        ];
        
        // Max Execution Time
        $max_exec = ini_get('max_execution_time');
        $checks[] = [
            'name' => 'Max Execution Time',
            'status' => ($max_exec == 0 || $max_exec >= 120) ? 'pass' : 'fail',
            'value' => $max_exec . 's',
            'required' => '120s+',
            'fix' => 'Add to wp-config.php: @ini_set(\'max_execution_time\', 120);'
        ];
        
        // Memory Limit
        $memory = ini_get('memory_limit');
        $memory_bytes = wp_convert_hr_to_bytes($memory);
        $checks[] = [
            'name' => 'Memory Limit',
            'status' => $memory_bytes >= 256 * 1024 * 1024 ? 'pass' : 'warning',
            'value' => $memory,
            'required' => '256M+',
            'fix' => 'Add to wp-config.php: define(\'WP_MEMORY_LIMIT\', \'256M\');'
        ];
        
        // WordPress Version
        $wp_version = get_bloginfo('version');
        $checks[] = [
            'name' => 'WordPress Version',
            'status' => version_compare($wp_version, '6.0', '>=') ? 'pass' : 'fail',
            'value' => $wp_version,
            'required' => '6.0+',
            'fix' => 'Update WordPress core'
        ];
        
        // Permalinks
        $permalink_structure = get_option('permalink_structure');
        $checks[] = [
            'name' => 'Permalinks',
            'status' => !empty($permalink_structure) ? 'pass' : 'fail',
            'value' => !empty($permalink_structure) ? 'Custom' : 'Default',
            'required' => 'Custom (not default)',
            'fix' => 'Go to Settings → Permalinks, choose any non-default option'
        ];
        
        // LLM API Key
        $api_key = get_option('agentic_llm_api_key', '');
        $checks[] = [
            'name' => 'LLM API Key',
            'status' => !empty($api_key) ? 'pass' : 'warning',
            'value' => !empty($api_key) ? 'Configured' : 'Not set',
            'required' => 'Required for Agent Builder',
            'fix' => 'Enter your API key in the settings above'
        ];
        
        return [
            'checks' => $checks,
            'overall' => !in_array('fail', array_column($checks, 'status'))
        ];
    }
    
    public static function test_timeout() {
        set_time_limit(120);
        $start = time();
        sleep(90); // Sleep for 90 seconds
        $duration = time() - $start;
        
        return [
            'success' => true,
            'duration' => $duration,
            'message' => 'Server can handle long requests'
        ];
    }
}
```

**Register in main plugin file:**

```php
add_action('rest_api_init', ['Agentic\System_Checker', 'register_routes']);
```

## Expected Outcomes

After implementation, users will:

1. ✅ **Know before they try** if their server can run the Agent Builder
2. ✅ **Get specific fix instructions** for each failed requirement
3. ✅ **Reduce support tickets** related to timeout errors
4. ✅ **Improve user experience** with proactive issue detection

## Testing Checklist

- [ ] Run system check on local development (should pass all)
- [ ] Run on shared hosting with 60s timeout (should fail timeout test)
- [ ] Run on VPS with proper config (should pass all)
- [ ] Test fix instructions are accurate
- [ ] Test timeout endpoint doesn't actually timeout
- [ ] Test results display correctly for all pass/fail/warning states

## Additional Considerations

### Auto-run on First Activation

Consider running the system check automatically when the plugin is first activated, and showing a dismissible admin notice if critical requirements fail:

```php
add_action('admin_notices', function() {
    if (!get_option('agentic_system_check_done')) {
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>Agentic Plugin:</strong> Please run the system check to ensure your server meets requirements.</p>
            <p><a href="' . admin_url('admin.php?page=agentic-settings') . '" class="button">Go to Settings</a></p>
        </div>';
    }
});
```

### Save Last Check Results

Store the last check results in options table so users can see status without re-running:

```php
update_option('agentic_last_system_check', [
    'timestamp' => time(),
    'results' => $checks
]);
```

### Display Status Badge

Show a small badge in the admin menu indicating system status:

- Green dot: All checks pass
- Yellow dot: Warnings only
- Red dot: Critical failures

---

## Files to Modify/Create

1. **CREATE:** `/includes/class-system-checker.php` - Backend logic
2. **MODIFY:** `/admin/settings.php` - Add UI section and JavaScript
3. **MODIFY:** Main plugin file - Register REST routes
4. **MODIFY:** `/admin/settings.js` (if separate) - Add JavaScript logic

## Priority

**HIGH** - This addresses a critical user experience issue that causes failures and support burden.

## Estimated Effort

- **Development:** 4-6 hours
- **Testing:** 2 hours
- **Documentation:** 1 hour

**Total:** ~1 day
