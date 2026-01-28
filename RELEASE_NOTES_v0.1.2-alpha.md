# 🚀 Agentic Plugin v0.1.2-alpha — Async Job Queue System

A major infrastructure update introducing asynchronous job processing for long-running agent tasks, eliminating timeout issues and providing real-time progress tracking.

## Major Features

### Async Job Queue Infrastructure

**Problem Solved**: Agent Builder and other long-running tasks were timing out due to PHP's max execution time limits (typically 30-60 seconds). Complex agent generation could take 2-3 minutes, causing HTTP 504 Gateway Timeout errors.

**Solution**: Complete async job queue system with:
- Background job processing via WordPress Cron
- Real-time progress updates (0-100%)
- Job status tracking (pending → processing → completed/failed)
- Automatic cleanup of old jobs (24-hour retention)
- User ownership and permission controls

### Architecture

```
User Request → Create Job (returns immediately)
    ↓
WordPress Cron processes job asynchronously
    ↓
Frontend polls for status updates
    ↓
Displays progress in real-time
    ↓
Returns final result when complete
```

## New Components

### 1. Job_Manager (`includes/class-job-manager.php`)

Core job queue infrastructure providing:

**Public API:**
- `create_job($args)` — Create and schedule a new async job
- `get_job($job_id)` — Retrieve job status and results
- `update_job($job_id, $data)` — Update job progress/status
- `cancel_job($job_id)` — Cancel a pending job
- `get_user_jobs($user_id, $status, $limit)` — List user's jobs
- `cleanup_old_jobs()` — Remove jobs older than 24 hours
- `get_stats($user_id)` — Get job statistics

**Features:**
- ✅ Automatic database table creation (`wp_agentic_jobs`)
- ✅ WP Cron integration for async processing
- ✅ Progress tracking with custom callbacks
- ✅ Automatic error handling and job failure tracking
- ✅ Hourly cleanup scheduling
- ✅ User ownership enforcement

### 2. Job_Processor_Interface (`includes/interface-job-processor.php`)

Contract that all job processors must implement:

```php
interface Job_Processor_Interface {
    public function execute(array $request_data, callable $progress_callback): array;
}
```

Ensures consistent implementation across different job types.

### 3. Jobs_API (`includes/class-jobs-api.php`)

REST API endpoints for job management:

| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `/wp-json/agentic/v1/jobs` | POST | Create new job | 202 Accepted |
| `/wp-json/agentic/v1/jobs/{id}` | GET | Get job status | Job object |
| `/wp-json/agentic/v1/jobs/{id}` | DELETE | Cancel job | Success/error |
| `/wp-json/agentic/v1/jobs/user/{user_id}` | GET | List user's jobs | Jobs array |

**Security:**
- ✅ Requires user authentication
- ✅ Users can only access their own jobs
- ✅ Admins can view all jobs
- ✅ Request data sanitized before storage

### 4. Agent_Builder_Job_Processor (`includes/class-agent-builder-job-processor.php`)

Example processor demonstrating async agent building:

**Features:**
- Implements `Job_Processor_Interface`
- Progress reporting at key milestones (5%, 10%, 20%, 30%, 50%, 95%, 100%)
- Integrates with Agent Registry and LLM Client
- Iterative tool call handling (up to 5 iterations)
- Comprehensive error handling

**Progress Stages:**
1. 5% — Initializing agent builder
2. 10% — Loading conversation history
3. 20% — Analyzing request
4. 30% — Generating agent specification
5. 50%+ — Executing agent tools (iterative)
6. 95% — Finalizing response
7. 100% — Completed

## Database Schema

New table: `wp_agentic_jobs`

```sql
CREATE TABLE wp_agentic_jobs (
    id VARCHAR(36) PRIMARY KEY,          -- UUID v4
    user_id BIGINT UNSIGNED NOT NULL,    -- Job owner
    agent_id VARCHAR(100),               -- Agent identifier
    status VARCHAR(20) DEFAULT 'pending', -- pending|processing|completed|failed|cancelled
    progress INT(3) DEFAULT 0,           -- 0-100 percentage
    message VARCHAR(255) DEFAULT '',     -- Current status message
    request_data LONGTEXT,               -- Input parameters (JSON)
    response_data LONGTEXT,              -- Output data (JSON)
    error_message TEXT,                  -- Error details if failed
    created_at DATETIME NOT NULL,        -- Job creation time
    updated_at DATETIME NOT NULL,        -- Last update time
    
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);
```

## Usage Examples

### Backend: Creating an Async Job

```php
// Create an async agent building job
$job_id = \Agentic\Job_Manager::create_job([
    'agent_id' => 'agent-builder',
    'request_data' => [
        'message' => 'Build an SEO optimization agent',
        'history' => []
    ],
    'processor' => 'Agentic\Agent_Builder_Job_Processor'
]);

// Returns immediately with job ID
return new WP_REST_Response([
    'job_id' => $job_id,
    'status' => 'pending'
], 202); // 202 Accepted
```

### Frontend: Polling for Job Status

```javascript
// Create the job
const response = await fetch('/wp-json/agentic/v1/jobs', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpNonce
    },
    body: JSON.stringify({
        agent_id: 'agent-builder',
        request_data: { message: 'Build an SEO agent' },
        processor: 'Agentic\\Agent_Builder_Job_Processor'
    })
});

const { job_id } = await response.json();

// Poll for updates every 2 seconds
const pollInterval = setInterval(async () => {
    const statusResponse = await fetch(`/wp-json/agentic/v1/jobs/${job_id}`, {
        headers: { 'X-WP-Nonce': wpNonce }
    });
    
    const job = await statusResponse.json();
    
    // Update progress UI
    updateProgress(job.progress, job.message);
    
    if (job.status === 'completed') {
        clearInterval(pollInterval);
        displayResult(job.response_data.response);
    } else if (job.status === 'failed') {
        clearInterval(pollInterval);
        showError(job.error_message);
    }
}, 2000);
```

### Creating Custom Job Processors

```php
namespace Agentic;

class My_Custom_Processor implements Job_Processor_Interface {
    public function execute(array $request_data, callable $progress_callback): array {
        $progress_callback(0, 'Starting custom task...');
        
        // Do work
        sleep(2);
        $progress_callback(50, 'Halfway complete...');
        
        // More work
        sleep(2);
        $progress_callback(100, 'Done!');
        
        return [
            'result' => 'success',
            'data' => ['processed' => true]
        ];
    }
}
```

## Integration

### Main Plugin File Updates

Added to `agentic-plugin.php`:

```php
// Initialize Job Manager
require_once AGENTIC_PLUGIN_DIR . 'includes/class-job-manager.php';
require_once AGENTIC_PLUGIN_DIR . 'includes/interface-job-processor.php';
require_once AGENTIC_PLUGIN_DIR . 'includes/class-jobs-api.php';

Job_Manager::init();
Jobs_API::init();
```

### Database Table Creation

Jobs table automatically created on plugin activation via `Job_Manager::create_table()`.

## Performance Considerations

### WordPress Cron Limitations

**Development**: WordPress Cron is visitor-dependent and may have delays.

**Production Recommendations**:

1. **Disable WP-Cron** in `wp-config.php`:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. **Add System Cron**:
   ```bash
   # Run every 5 minutes
   */5 * * * * curl https://yoursite.com/wp-cron.php?doing_wp_cron
   ```

### Alternative: Action Scheduler

For enterprise deployments, consider Action Scheduler plugin:

```php
// More reliable than WP Cron
as_enqueue_async_action('agentic_process_job', [$job_id]);
```

## Automatic Cleanup

**Retention Policy**: Jobs older than 24 hours are automatically deleted.

**Cleanup Schedule**: Runs hourly via `agentic_cleanup_jobs` hook.

**Manual Cleanup**:
```php
$deleted_count = Job_Manager::cleanup_old_jobs();
```

## Error Handling

Processors automatically handle exceptions:

```php
public function execute($data, $progress) {
    if (!isset($data['required_field'])) {
        throw new \Exception('Missing required field');
    }
    
    try {
        // Process the job
    } catch (\Exception $e) {
        // Exception auto-caught by Job_Manager
        // Job marked as 'failed' with error_message set
        throw new \Exception('Processing failed: ' . $e->getMessage());
    }
}
```

## Security Features

- ✅ **User Ownership**: All jobs require `user_id`
- ✅ **Permission Checks**: Users can only access their own jobs
- ✅ **Admin Override**: Admins can view/manage all jobs
- ✅ **Input Sanitization**: Request data validated before storage
- ✅ **Authentication Required**: All REST endpoints require login

## API Endpoints (Complete List)

### Existing Endpoints
- `POST /wp-json/agent/v1/chat` — Agent chat
- `GET /wp-json/agent/v1/status` — System status
- `GET /wp-json/agent/v1/capabilities` — Available capabilities

### NEW in v0.1.2-alpha
- `POST /wp-json/agentic/v1/jobs` — Create async job (202 Accepted)
- `GET /wp-json/agentic/v1/jobs/{id}` — Get job status and progress
- `DELETE /wp-json/agentic/v1/jobs/{id}` — Cancel pending job
- `GET /wp-json/agentic/v1/jobs/user/{user_id}` — List user's jobs

## Upgrade Notes

### No Breaking Changes
This release is fully backward compatible. Existing functionality remains unchanged.

### Automatic Migration
- Database table created automatically on activation
- No manual intervention required
- No data migration needed

### Recommended Actions
1. Update any long-running agent implementations to use async jobs
2. Consider migrating Agent Builder to async processing
3. Test job creation and status polling in development
4. Review WP Cron configuration for production environments

## Documentation

**NEW**: `docs/ASYNC_JOBS.md` — Complete implementation guide with:
- Architecture overview
- Component details
- Usage examples
- Custom processor creation
- Production deployment guidelines
- Performance optimization tips

## Files Changed

### New Files (4)
- `includes/class-job-manager.php` — Core job queue manager
- `includes/interface-job-processor.php` — Processor interface contract
- `includes/class-jobs-api.php` — REST API endpoints
- `includes/class-agent-builder-job-processor.php` — Example processor
- `docs/ASYNC_JOBS.md` — Complete documentation

### Modified Files (1)
- `agentic-plugin.php` — Initialize job system

## What's Next

This infrastructure enables:
- ✅ Timeout-free agent building
- ✅ Long-running batch operations
- ✅ User-friendly progress tracking
- ✅ Scalable background processing

### Potential Use Cases
- Agent building (Agent Builder)
- Bulk content generation
- Site-wide SEO analysis
- Batch image optimization
- Database migrations
- Report generation
- Email campaigns

## Testing

```php
// Create test job
$job_id = Job_Manager::create_job([
    'agent_id' => 'test',
    'request_data' => ['test' => true],
    'processor' => 'Your_Test_Processor'
]);

// Wait for processing (WP Cron runs on next page load in dev)
// Or manually trigger: do_action('agentic_process_job', $job_id);

// Check result
$job = Job_Manager::get_job($job_id);
assert($job->status === 'completed');
```

## Known Limitations

- WP Cron is visitor-dependent (not true cron)
- Jobs limited to 24-hour retention
- No built-in job prioritization
- No job scheduling for future execution
- Polling interval hardcoded in frontend (easily customizable)

## Future Enhancements (Planned)

- Job prioritization system
- Scheduled jobs (run at specific time)
- Job dependencies/chaining
- Webhook notifications on completion
- Admin UI for job monitoring
- Extended retention periods for premium users

---

**Release Date**: January 2026  
**Status**: ✅ Infrastructure complete, ready for integration

— Built with 💪 by the Agentic community
