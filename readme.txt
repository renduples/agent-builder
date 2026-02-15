=== Agent Builder ===
Contributors: agenticplugin
Tags: AI, LLM, automation, chatbot, AI agent
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.7.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://agentic-plugin.com/donate

Build AI agents for WordPress without coding. Automate content creation, SEO, chat, and more with OpenAI, Anthropic Claude, and other LLMs.

== Description ==

Agent Builder turns WordPress into an AI-agent ecosystem — similar to how plugins and themes extend your site, but powered by large language models (LLMs).

Describe your desired AI agent in natural language, and the plugin builds it for you. Install, activate, and manage agents just like regular plugins. Browse a growing library of community agents or create your own.

Key benefits:
- No-code agent creation via chat interface
- Multi-provider support: OpenAI, Anthropic (Claude), local models (Ollama), and more
- Plugin-style management: install, activate, deactivate, delete
- Built-in safeguards: audit logs, human-in-the-loop approvals, rate limiting, cost controls
- Extensible: developers can build custom agents with tools and share them

= Agent Categories =
- **Content** — Drafting, SEO optimization, translation, alt text generation
- **Admin** — Security monitoring, backups, performance tuning
- **E-commerce** — Product management, pricing optimization, inventory
- **Frontend** — Visitor chat, comment moderation, support
- **Developer** — Code generation, debugging, theme/plugin building
- **Marketing** — Campaigns, multi-platform content

= Requirements =
- WordPress 6.4+
- PHP 8.1+
- MySQL 8.0 / MariaDB 10.6+

== Installation ==

1. Search for "Agent Builder" in **Plugins → Add New** and install it (or upload the ZIP via **Add New → Upload Plugin**).
2. Activate the plugin.
3. Go to **Agentic → Settings** in the WordPress admin menu.
4. Enter your AI provider API key (OpenAI, Anthropic, etc.) or configure local models.
5. Visit **Agentic → Agents** to browse/install pre-built agents.
6. Activate any agent to enable its features on your site.

== Frequently Asked Questions ==

= How does this differ from regular WordPress plugins? =
Agents are AI-powered automations that follow a standardized structure. They register tools the LLM can call, integrate with approval workflows, and are managed like plugins (activate/deactivate/delete).

= Can I create my own agents? =
Yes. Create an `agent.php` file with standard headers, register tools/functions, and place it in `wp-content/agents/`. The plugin auto-discovers them. Share your agents with the community!

= Where do custom agents go? =
Place custom agents in `wp-content/agents/` (outside the plugin folder). This keeps them safe during updates. Bundled demo agents live in the plugin's `library/` folder.

= Is my data sent to external AI services? =
Only if using cloud providers (OpenAI, Anthropic, etc.). Use local models via Ollama for full privacy. All external calls are logged and rate-limited.

= Is it production-ready? =
Version 1.1.0+ is stable with strong security (no exec(), nonces, escaping), GDPR-compliant uninstall, and audit logging. Test thoroughly on staging first.

= Which AI providers work? =
xAI (GROK), OpenAI (GPT models), Anthropic (Claude), local Ollama models. More coming soon.

== Screenshots ==

1. Intuitive chat interface for describing and building new AI agents.
2. Agent library screen — browse, install, and manage your agents with one click.
3. Settings page to configure your preferred AI provider, API keys, rate limits, and approvals.
4. Detailed agent controls — permissions, security, and audit log viewer.

== Changelog ==

= 1.7.5 - 2026-02-15 =
* Fixed: All remaining WordPress Coding Standards (PHPCS) violations resolved — 0 errors, 0 warnings.
* Fixed: Missing input sanitization on shortcode deployment delete action.
* Fixed: Replaced deprecated current_time('timestamp') with time() in license revalidation.
* Fixed: Reserved keyword parameter name in Agent_Proposals::generate_diff().
* Improved: PHPCS false positives suppressed with targeted inline ignores (meta_key schema defs, rename for directory moves, nonce-free GET reads).
* Improved: Test suite excluded from PHPCS (legitimate naming/DB conventions differ from plugin code).
* Improved: System prompts refined for Onboarding, Theme Builder, and Plugin Builder agents.

= 1.7.4 - 2026-02-15 =
* Added: Bundled agents auto-activate on plugin activation.
* Added: Welcome messages in admin bar chat overlay for all agents.
* Added: Onboarding Agent quick-action buttons to launch Agent Builder, Content Builder, Plugin Builder, and Theme Builder directly.
* Added: "Helper" label for Onboarding Agent in admin bar menu (friendlier for new users).
* Added: Agent names map for overlay window titles (shows real agent name, not menu label).
* Fixed: Duplicate tool definition error — agent-specific tools now take priority over core tools with the same name.
* Removed: Tool tags from chat overlay (tokens/cost still visible for admins).
* Removed: Redundant read_file, list_directory, search_code tools from Onboarding Agent (uses core tools instead).
* Improved: Onboarding Agent welcome message rewritten for beginners.
* Improved: Tool deduplication in Agent Controller prevents LLM API errors.

= 1.7.3 - 2026-02-15 =
* Streamlined: Removed 6 non-essential bundled agents (Comment Moderator, Product Describer, SEO Analyzer, Security Monitor, Social Media Manager, Code Generator) to focus on the 5 most impactful agents.
* Changed: Renamed Content Assistant agent to Content Builder for clarity.
* Final bundled agents: Content Builder, Theme Builder, Agent Builder, Onboarding Agent, Plugin Builder.

= 1.7.2 - 2026-02-15 =
* Added: Admin bar "AI Agents" quick-chat overlay — chat with any active agent from any page.
* Added: Image/file upload support in all chat interfaces (admin, frontend shortcode, overlay).
* Added: Voice input via Web Speech API with graceful degradation on unsupported browsers.
* Added: Auto-switch to vision model when image is attached (e.g., grok-3 → grok-2-vision).
* Added: Vision capability indicator (👁) in Settings model dropdown.
* Added: Grok 2 Vision and Pixtral Large as selectable models for xAI and Mistral.
* Added: Multimodal LLM support across all 5 providers (OpenAI, Anthropic, xAI, Google, Mistral).
* Added: New Chat and Minimize buttons in chatbox header.
* Added: Agent Deployment page with Scheduled Tasks, Event Listeners, and Shortcodes tabs.
* Added: Run Now AJAX for scheduled tasks with real-time feedback.
* Added: Tool toggle switches with 3-layer enforcement (UI, controller, audit).
* Added: Tool usage counter on Agent Tools page.
* Improved: Chat responses render full Markdown (headers, lists, code blocks, links).
* Improved: Cache-busted assets using filemtime() instead of static version strings.
* Fixed: Removed duplicate "Agents" admin bar menu; only "AI Agents" chat menu remains.
* Security: Temp image uploads auto-cleaned after 1 hour; base64 validation on REST endpoint.

= 1.7.1 - 2026-02-15 =
* Improved: All API calls now send site identification headers (X-Agentic-Site-URL, Site-Name, Plugin-Version, WP-Version, PHP-Version, User-Agent).
* Improved: License activation error messages now include HTTP status code and server error detail.
* Fixed: License tab JS timing — changed from IIFE to DOMContentLoaded to prevent button race condition.
* Fixed: License_Client and Marketplace_Client use AGENTIC_API_BASE instead of AGENTIC_MARKETPLACE_URL.

= 1.7.0 - 2026-02-14 =
* Added: License_Client class for client-side license enforcement.
* Added: Periodic license revalidation via wp_cron (every 12 hours).
* Added: 72-hour cached fallback when license server is unreachable.
* Added: Update gating — blocks plugin updates without a valid license.
* Added: Feature degradation — user-space agents disabled when license is invalid or expired.
* Added: Admin notices for expired, revoked, or missing licenses with renewal links.
* Added: License tab in Settings with activate/deactivate UI and status display.
* Added: HMAC-signed API requests with site_hash for tamper resistance.
* Improved: Dashboard uses License_Client for status display.
* Improved: Agent Registry skips non-bundled agents when license invalid (with debug logging).
* Security: Fail-open design — cached last-known-good state, never breaks customer sites.
* Tests: 457 tests, 1,211 assertions (23 new License_Client tests).

= 1.6.3 - 2026-02-14 =
* Improved: All 11 bundled agent system prompts rewritten with ecosystem context and scope boundaries.
* Improved: Each agent now defers out-of-scope tasks to the appropriate sibling agent.
* Improved: Prompts reference actual available tools and instruct agents to use real data.
* Improved: Removed vague personality sections; replaced with concrete behavioural instructions.
* Improved: Onboarding Agent prompt now lists all key classes, files, and bundled agents.
* Improved: SEO Analyzer prompt adds specific meta length targets and schema markup guidance.
* Improved: Theme Builder prompt adds accessibility (WCAG 2.1) and modern CSS layout guidance.
* Improved: Dashboard marketplace stats now derived from /agents endpoint (fixes N/A display).
* Changed: All bundled agents bumped from v1.0.0 to v1.1.0.

= 1.6.2 - 2026-02-14 =
* Fixed: 3 risky tests now include unconditional assertions (search_code_result_fields, get_error_log_line_limit, get_error_log_max_cap).
* Fixed: Added missing v1.2.0 changelog entry.
* Fixed: Added missing git tags for v1.4.0 and v1.5.0 releases.
* Tests: 434 tests, 1,167 assertions, 0 failures, 0 risky.

= 1.6.1 - 2026-02-12 =
* Fixed: Added missing require_once for Agent_Builder_Job_Processor class (caused "invalid or missing job processor" error).
* Fixed: Corrected Agent_Registry class reference in job processor (namespaced vs global class).
* Fixed: Ensured agent-builder job processor loads during plugin bootstrap.

= 1.6.0 - 2026-02-11 =
* Added: Two-zone architecture — plugin/repo code vs user-space changes.
* Added: Agent_Permissions class with 6 granular permission scopes (all disabled by default).
* Added: Agent_Proposals class for pending change confirmation with diff view.
* Added: write_file tool — create/modify files in active theme or agentic-custom sandbox.
* Added: modify_option tool — set/delete WordPress options (sensitive options blocked).
* Added: manage_transients tool — list, delete, or flush transients.
* Added: modify_postmeta tool — get, set, or delete post meta fields.
* Added: Settings Permissions tab with scope toggles and confirmation mode selector.
* Added: Chat proposal cards with syntax-highlighted diff view and Approve/Reject buttons.
* Added: REST API POST /proposals/{id} endpoint for proposal approve/reject.
* Added: File backup system (wp-content/agentic-backups/) before user-space writes.
* Security: Sensitive core options blocked (siteurl, home, admin_email, passwords, API keys).
* Security: All user-space permissions disabled by default with opt-in confirmation mode.
* Tests: 434 tests, 1,172 assertions (48 new tests for permissions, proposals, user-space tools).

= 1.5.0 - 2026-02-11 =
* Added: Event listeners — agents react to WordPress action hooks in real time.
* Added: Agent_Base::get_event_listeners() for defining hook-triggered behaviour.
* Added: Direct mode event listeners — synchronous PHP callback on hook fire.
* Added: AI Async mode — queues LLM task via wp_schedule_single_event() (non-blocking).
* Added: Smart serialization of hook arguments (WP_Post, WP_Comment, WP_User auto-converted).
* Added: Event Listeners admin page showing agent, hook, priority, mode, status.
* Added: Outcome logging for events (triggered/complete/error with timing).
* Added: Security Monitor event listeners for failed logins and new user registration.

= 1.4.0 - 2026-02-10 =
* Added: Scheduled tasks infrastructure — agents define recurring tasks via get_scheduled_tasks().
* Added: Autonomous cron execution — tasks with prompt field route through LLM with full tool access.
* Added: Generic outcome logging — every task execution wrapped with start/complete/error timing.
* Added: Schedule management core tool (list/pause/resume) available to all agents.
* Added: Agent Tools admin page showing all tools across all agents.
* Added: Scheduled Tasks admin page with Run Now button, mode (AI/Direct), and status.
* Improved: Audit log timestamps, expandable details, dynamic filters, 30-day retention.
* Improved: Agent responses logged in chat_complete audit entries.
* Added: Security Monitor autonomous daily security scan with LLM reasoning.

= 1.3.0 - 2026-02-09 =
* Added: Comprehensive test suite — 346 tests, 881 assertions, all passing.
* Added: Add Agents page overhaul — upload feature (ZIP handler), WP-style card layout.
* Added: CI/CD via GitHub Actions workflow.
* Fixed: 8 source bugs found during test implementation.
* Fixed: All WordPress naming convention violations — 100% compliant.
* Fixed: Prefixed all global variables in admin templates.
* Improved: Code quality and WordPress.org submission readiness.

= 1.2.0 - 2026-02-08 =
* Added: Security Log system with database-backed event tracking (Security_Log class).
* Added: Security Log admin page for viewing blocked messages, rate limits, PII warnings.
* Added: Security statistics and analytics (top patterns, top IPs).
* Added: Plugin Builder agent with full template system for generating WordPress plugins.
* Added: Security log cleanup functionality (30-day retention).
* Improved: Centralized security logging — replaced error_log() calls with Security_Log class.
* Improved: Full PHPCS compliance for WordPress.org standards.
* Fixed: Naming convention violations in uninstall.php.

= 1.1.2 - 2026-02-02 =
* Fixed: Automatic API key saving when returning from marketplace registration
* Improved: Revenue page messaging with link to licensing documentation

= 1.1.1 - 2026-02-02 =
* Added marketplace dashboard stats widget with latest/popular agents and developer revenue.
* Added plugin license validation system (Free/Pro/Enterprise tiers).
* Added developer API key management in Settings with Update Key and Disconnect options.
* Added automatic API key pre-fill when returning from marketplace registration.
* Added support ticketing system integration across all admin pages.
* Implemented Revenue page with full marketplace API integration and Chart.js visualizations.
* Updated dashboard with improved UI, proper timestamps, and marketplace stats.
* Fixed active agent count to show accurate numbers from database.
* Removed obsolete license validation code from marketplace access.
* Created comprehensive API specification documents for marketplace team.

= 1.1.0 - 2026-02-01 =
* Added full GDPR-compliant uninstall handler (deletes options, tables, transients, user meta, crons).
* Added phpcs.xml and VS Code settings for WordPress Coding Standards.
* Improved: Complete @package/@since/@license headers in all PHP files.
* Improved: Renamed classes/files for better naming consistency (e.g., class-llm-client.php).
* Fixed: SQL formatting, unused parameters, nonce ignores, reserved keywords.
* Removed: Obsolete Python fix scripts.

= 1.0.1 - 2026-01-30 =
* Fixed remaining PHPCS issues.
* Improved documentation and inline comments.

= 1.0.0 - 2026-01-28 =
* Added full i18n support (Spanish, French, German .po/.mo files).
* Achieved full WordPress Coding Standards compliance (7,246 auto-fixes).
* Security: Removed all exec() and potential vulnerabilities.
* Simplified namespace to "Agentic".
* Added System Requirements Checker.
* Brand consistency updates ("Agent Builder").

= 0.1.3-alpha - 2026-01-28 =
* Added System Requirements Checker.
* Simplified namespace and plugin naming.

= 0.1.2-alpha - 2026-01-15 =
* Introduced Async Job Queue for background tasks.
* Added real-time progress tracking and job management API.

= 0.1.0-alpha - 2026-01-01 =
* Initial public alpha.
* Core no-code agent builder.
* 10 bundled agents.
* Admin dashboard and multi-LLM support.
* Human-in-the-loop approvals and audit trails.
* Marketplace foundation.

== Upgrade Notice ==

= 1.6.3 =
Improved: All 11 agent system prompts rewritten with scope boundaries and ecosystem context. Dashboard marketplace stats fixed.

= 1.6.2 =
Patch: Fixed 3 risky tests, added missing changelog and git tag entries. Full green test suite.

= 1.6.1 =
Patch: Fixed job processor loading and class reference bugs that prevented the agent builder from working.

= 1.6.0 =
New: Two-zone user-space permissions and proposals. Agents can now write theme files, modify options, manage transients, and update post meta — all permission-gated with confirmation diffs. No breaking changes.

= 1.5.0 =
New: Event listeners let agents react to WordPress hooks (login failures, new users, etc.). No breaking changes.

= 1.4.0 =
New: Scheduled tasks with autonomous AI execution, tools admin, and audit log improvements. No breaking changes.

= 1.3.0 =
Test suite added (346 tests). Add Agents page overhauled. 8 bugs fixed. No breaking changes.

= 1.1.0 =
Important: GDPR-compliant uninstall added. No data loss or breaking changes. Recommended update for all users.

= 1.0.0 =
First stable release. Smooth upgrade — no migrations needed. Full standards compliance and security hardening.

= 0.1.x-alpha =
Early development versions. Upgrade to 1.0.0+ for stability, i18n, and security fixes.