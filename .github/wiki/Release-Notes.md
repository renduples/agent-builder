# Release Notes

This page contains all release notes for Agent Builder versions.

## Table of Contents

- [v1.5.0](#v150) - Event Listeners
- [v1.4.0](#v140) - Autonomous Scheduled Tasks & Tools Admin
- [v1.3.0](#v130) - Test Suite, Add Agents Overhaul, Rename
- [v1.0.0-beta](#v100-beta) - Production-Ready Beta Release
- [v0.1.3-alpha](#v013-alpha) - Naming Standardization & System Requirements
- [v0.1.2-alpha](#v012-alpha) - Async Job Queue System
- [v0.1.0-alpha](#v010-alpha) - Initial Release

---

## v1.5.0

**Release Date:** February 11, 2026

### Event Listeners — Agents React to WordPress Action Hooks

Agents can now respond to WordPress events in real time. This is the third invocation method alongside chat (interactive) and cron (scheduled).

### Highlights

- **`Agent_Base::get_event_listeners()`** — Agents define which WordPress action hooks they listen to
- **Direct mode** — Synchronous PHP callback when a hook fires (fast, no LLM needed)
- **AI Async mode** — Queues an LLM task via `wp_schedule_single_event()` so it never blocks page loads
- **Smart serialization** — `WP_Post`, `WP_Comment`, `WP_User` objects auto-converted to clean arrays; large strings truncated; unknown objects reduced to class name
- **Outcome logging** — Every event trigger logged with `event_listener_triggered`, `event_listener_complete`, `event_listener_error` entries including timing
- **Event Listeners admin page** — Table showing Agent, Listener, WordPress Hook, Priority, Mode (AI Async / Direct), Status
- Agents can define event listeners for WordPress hooks like `wp_login_failed` and `user_register`

### Three Agent Invocation Methods

| Method | Trigger | Added In |
|--------|---------|----------|
| **Chat** | User sends a message | v1.0.0 |
| **Cron** | WP-Cron schedule fires | v1.4.0 |
| **Hooks** | WordPress action hook fires | v1.5.0 |

### Technical Details

- Listeners are bound on `agentic_agents_loaded` via `Plugin::bind_agent_event_listeners()`
- AI-mode events are processed by `Plugin::handle_async_event()` which calls `Agent_Controller::run_autonomous_task()` with serialized hook context
- If LLM is not configured, AI-mode falls back to calling the direct callback method
- Hook arguments are sanitized before serialization to prevent oversized payloads

---

## v1.4.0

**Release Date:** February 10, 2026

### Autonomous Scheduled Tasks & Tools Admin

Major release adding time-based autonomy to agents.

### Highlights

- **Scheduled tasks infrastructure** — Agents define recurring tasks via `get_scheduled_tasks()` with `id`, `name`, `callback`, `schedule`, optional `prompt`
- **Autonomous cron** — Tasks with a `prompt` field route through the LLM via `Agent_Controller::run_autonomous_task()` with full tool access
- **Generic outcome logging** — Every task execution wrapped with `scheduled_task_start` / `scheduled_task_complete` / `scheduled_task_error` entries including duration
- **Schedule management tool** — Core `manage_schedules` tool (list / pause / resume) available to all agents
- **Tools admin page** — Shows all tools across all agents with type (Core / Agent), parameters, used-by columns
- **Scheduled Tasks admin page** — Table with Agent, Task, Schedule, Mode (AI / Direct), Status, Next Run, Run Now button
- **Audit log improvements** — Actual timestamps via `wp_date()`, expandable details (600×300px scrollable), dynamic agent/action filter dropdowns, 30-day retention, agent responses logged in `chat_complete` entries
- Agents can define autonomous scheduled tasks with LLM reasoning and tool calls

### Cron Architecture

1. `agentic_agents_loaded` → `bind_agent_cron_hooks()` binds WP actions
2. `agentic_agent_activated` → `register_scheduled_tasks()` creates `wp_schedule_event`
3. `agentic_agent_deactivated` → `unregister_scheduled_tasks()` removes events
4. Plugin adds custom `weekly` schedule via `cron_schedules` filter

---

## v1.3.0

**Release Date:** February 9, 2026

### Test Suite, Add Agents Overhaul, Developer Agent Rename

### Highlights

- **Comprehensive test suite** — 346 tests, 881 assertions, all passing (PHPUnit 9.6, PHP 8.4)
- **8 source bugs found and fixed** during test implementation
- **Add Agents page overhaul** — Upload Agent feature (ZIP handler), WP-style card layout with emoji icons, responsive grid, installed state detection
- **Rename** — "Developer Agent" → "Onboarding Agent" (display name only, folder unchanged)
- **URL fix** — All `/roadmap/` links → `/documentation/`
- **CI/CD** — GitHub Actions workflow for automated testing

---

## v1.0.0-beta

[Full Release Notes](https://github.com/renduples/agent-builder/blob/main/RELEASE_NOTES_v1.0.0-beta.md)

**Release Date:** January 28, 2026

### Highlights
- Production-ready beta release
- WordPress Coding Standards compliance (7,246 auto-fixes)
- Security hardening (removed exec() calls)
- Internationalization (Spanish, French, German)
- WordPress.org submission ready

---

## v0.1.3-alpha

[Full Release Notes](https://github.com/renduples/agent-builder/blob/main/RELEASE_NOTES_v0.1.3-alpha.md)

**Release Date:** January 28, 2026

### Highlights
- Plugin renamed to "Agent Builder"
- System Requirements Checker
- Namespace simplified to Agentic
- Version synchronization

---

## v0.1.2-alpha

[Full Release Notes](https://github.com/renduples/agent-builder/blob/main/RELEASE_NOTES_v0.1.2-alpha.md)

**Release Date:** January 2026

### Highlights
- Async Job Queue System
- Background processing
- Real-time progress tracking
- Job management API

---

## v0.1.0-alpha

[Full Release Notes](https://github.com/renduples/agent-builder/blob/main/RELEASE_NOTES_v0.1.0-alpha.md)

**Release Date:** January 2026

### Highlights
- Initial public release
- 10 pre-built agents
- Admin dashboard
- Multi-LLM support
