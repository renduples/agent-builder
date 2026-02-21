=== Agent Builder ===
AI assistants that write content, fix SEO, monitor security, and keep your WordPress site healthy — no code required.
Contributors: agenticplugin
Tags: ai, llm, ai-agent, chatbot, openai, anthropic, xai
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Agent Builder gives your WordPress site a team of AI assistants. 
Tell them what you need in plain English — they handle the rest.

= Your AI Team (included free) =

✍️ **Content Writer** — Creates, edits, and publishes your posts and
pages. Describe what you want, review the draft, and publish.

🔍 **SEO Assistant** — Audits every post for title length, meta
description, keyword density, headings, and links. Applies fixes directly.

🔒 **Security Assistant** — Monitors failed logins, flags outdated
plugins, audits admin accounts, and checks for modified files.

🩺 **Site Doctor** — Finds database bloat, broken internal links,
orphaned content, inactive plugins, and PHP errors. Scores your site.

📡 **AI Radar** — Scans your site's visibility to AI search engines.
Checks robots.txt, schema markup, content structure, and technical
readiness. Scores 0–100 with a weekly monitoring option.

🧭 **WordPress Assistant** — Your guide to WordPress and Agent Builder.
Ask questions, get help with settings, and find the right assistant.

🏗️ **Assistant Trainer** — A meta-agent that creates and train new AI assistants
from a plain job description. Tell it what you need, to deploy new assistants — without writing code.

= Requirements =

* WordPress 6.4 or higher
* PHP 8.1 or higher
* MySQL 8.0 or MariaDB 10.6+
* An API key from at least one supported LLM provider (or a local Ollama installation)

== Installation ==

1. Go to **Plugins → Add New** in your WordPress admin and search for "Agent Builder", then click **Install Now**. Alternatively, download the ZIP file and upload it via **Plugins → Add New → Upload Plugin**.
2. Click **Activate** on the Plugins screen.
3. Navigate to **Agentic → Settings** in the admin menu.
4. Select your AI provider (OpenAI, Anthropic, xAI, Google, Mistral, or Ollama) and enter your API key. For Ollama, enter your local server URL instead.
5. Go to **Agentic → Agents** to see the bundled agents. Activate the ones you want to use.
6. Visit **Agentic → Chat** to start interacting with your active agents.

= You Choose your own AI provider =

Works with the AI services you already know:
- OpenAI (ChatGPT)
- Anthropic (Claude)  
- xAI (Grok)
- Google (Gemini)
- Mistral
- Ollama (100% local — your data never leaves your server)

= Your Data Stays Yours =

- API keys stored on YOUR server, never sent to us
- All write permissions off by default
- Confirmation mode shows you exactly what will change before 
  anything happens
- Complete audit log of every action

= Want More? =

Browse the free AI Marketplace at agentic-plugin.com for additional
assistants — Theme Assistant, Plugin Assistant, Assistant Trainer,
content refreshers, speed optimizers, e-commerce helpers, and more.
Or build your own with our developer framework.

= For Developers =

Agent Builder is also a full agent framework. Create custom agents 
by extending Agent_Base, define tools as PHP functions the AI can 
call, add scheduled tasks and event listeners, and publish to the 
marketplace. See our developer documentation at 
agentic-plugin.com/documentation.

== FAQ ==

= How much does the AI cost? =
The plugin is free. You provide your own API key from your preferred 
AI provider. Typical usage costs $1-5/month depending on how active 
your assistants are. You can monitor costs in your provider's 
dashboard. If you want zero cost, run Ollama locally with free 
open-source models.

= Is my data sent anywhere? =
Only to the AI provider you choose, and only when you actively use 
the chat. No data is sent to us. If you use Ollama, nothing leaves 
your machine.

= Will this break my site? =
No. All write permissions are off by default. When enabled, a 
confirmation mode shows you proposed changes as a diff — you approve 
or reject before anything executes. The plugin includes automatic 
file backups before any changes.

= Which AI provider should I pick? =
If you're not sure, start with OpenAI — it's the most widely used 
and you can get an API key in about 5 minutes at openai.com. All 
providers work well with Agent Builder.

= Can I use this without an API key? =
Yes, if you run Ollama locally. For cloud providers (OpenAI, Anthropic, xAI, Google, Mistral), you need an API key from that provider. API usage is billed directly by the provider, not by this plugin.

= Can I create my own agents? =
Yes. Create an `agent.php` file in `wp-content/agents/your-agent-name/`, extend the `Agent_Base` class, and register via the `agentic_register_agents` hook. The plugin auto-discovers agents in that directory. Custom agents are kept separate from the plugin and survive updates.

== Screenshots ==

1. Chat interface for interacting with AI agents, with quick-action buttons and markdown rendering.
2. Agents screen showing installed agents with activate, deactivate, and status controls.
3. Settings page for selecting AI provider, entering API keys, and configuring rate limits.
4. Audit log and security controls for reviewing agent actions and tool usage.

== External Services ==

This plugin connects to third-party AI services to process chat messages and execute agent tasks. **No data is transmitted unless you configure an API key and actively use the chat interface.** The plugin sends conversation messages (user input and system context) to your selected provider's API to receive AI-generated responses.

= OpenAI =
* **Endpoint:** `https://api.openai.com/v1/chat/completions`
* **When used:** When OpenAI is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://openai.com/terms](https://openai.com/terms)
* **Privacy Policy:** [https://openai.com/privacy](https://openai.com/privacy)

= Anthropic =
* **Endpoint:** `https://api.anthropic.com/v1/messages`
* **When used:** When Anthropic is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://www.anthropic.com/terms](https://www.anthropic.com/terms)
* **Privacy Policy:** [https://www.anthropic.com/privacy](https://www.anthropic.com/privacy)

= xAI =
* **Endpoint:** `https://api.x.ai/v1/chat/completions`
* **When used:** When xAI is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://x.ai/legal/terms-of-service](https://x.ai/legal/terms-of-service)
* **Privacy Policy:** [https://x.ai/legal/privacy-policy](https://x.ai/legal/privacy-policy)

= Google (Gemini) =
* **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/`
* **When used:** When Google is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://ai.google.dev/terms](https://ai.google.dev/terms)
* **Privacy Policy:** [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Mistral =
* **Endpoint:** `https://api.mistral.ai/v1/chat/completions`
* **When used:** When Mistral is selected as the AI provider in Settings.
* **Data sent:** Chat messages, system prompts, tool definitions, and tool call results.
* **Terms of Service:** [https://mistral.ai/terms/](https://mistral.ai/terms/)
* **Privacy Policy:** [https://mistral.ai/terms/#privacy-policy](https://mistral.ai/terms/#privacy-policy)

= Ollama (Local) =
* **Endpoint:** User-configured local URL (default: `http://localhost:11434`)
* **When used:** When Ollama is selected as the AI provider in Settings.
* **Data sent:** All data stays on your local machine. No external network requests are made.

You provide your own API key for each cloud provider. The plugin does not collect, store, or transmit API keys to any third party. API usage costs are billed directly by each provider according to their pricing.

== Changelog ==

= 1.9.2 - 2026-02-20 =
* Fixed: Release workflow corrected to use proper plugin filename.

= 1.9.1 - 2026-02-20 =
* Added: First-run onboarding wizard — guides new users through AI provider selection, account creation, and API key setup with step-by-step instructions and screenshots.
* Changed: Admin menu item "Code Proposals" renamed to "Approval Queue".
* Fixed: Fatal error on activation — `Site_Auditor::execute_tool()` return type incompatible with `Agent_Base` signature (`mixed` → `?array`).

= 1.9.0 - 2026-02-19 =
* Added: License verification for downloaded marketplace agents — agents are domain-bound to the installing site and verify their license on each run.
* Added: Automatic license activation during ZIP install — clear error message and rollback if activation fails.
* Changed: License enforcement updated — bundled and user-created agents always run; marketplace agents require a valid license tied to the site.
* Changed: Core bundled agents are now protected from accidental deletion via the admin UI.
* Removed: `content-assistant`, `plugin-assistant`, `theme-assistant` bundled agents (moved to marketplace or retired).
* Added: `content-writer`, `seo-assistant`, `security-assistant`, `site-auditor`, `site-doctor`, `ai-radar` as bundled library agents.
* Improved: `wordpress-assistant` system prompt expanded with full ecosystem context.
* Improved: `assistant-trainer` prompt and agent refined.

= 1.8.1 - 2026-02-17 =
* Added: Agent origin tracking to distinguish uploaded marketplace agents from user-created ones.
* Changed: License enforcement updated — bundled and user-created agents always run; only uploaded marketplace agents require a valid license.
* Added: Agent Builder job processor now tracks created agent slug on successful agent creation.
* Tests: 428 tests, 1,072 assertions.

= 1.8.0 - 2026-02-17 =
* Changed: Core tools rebuilt — added 11 focused database tools (db_get_option, db_update_option, db_get_posts, db_get_post, db_create_post, db_update_post, db_delete_post, db_get_users, db_get_terms, db_get_post_meta, db_get_comments).
* Changed: Agent Tools page simplified to 6 tabs (All, Plugins, Themes, Agents, WordPress, Database).
* Added: Agent mode validation — mode values are validated against an allow-list.
* Added: Audit log retention cron — daily cleanup of entries older than 90 days (filterable).
* Added: DB schema versioning — version-gated migrations for safer upgrades.
* Added: Composite database indexes for audit_log, approval_queue, and memory tables (6 new indexes).
* Changed: Admin-only hooks (admin_init, admin_menu, admin_bar_menu, admin_enqueue_scripts, AJAX handlers) now load behind is_admin() guard.
* Removed: Per-directory scope toggle system (UI panel, AJAX handler, and JS).
* Removed: Onboarding Agent evaluate_feature_request tool.
* Improved: Agent_Tools class reduced from 2,616 to ~310 lines.
* Tests: 427 tests, 1,071 assertions — 22 new tests for schema versioning, indexes, audit retention, hook splitting, and enum sanitizer.

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
* Added: Agent names map for overlay window titles (shows real agent name, not menu label).
* Fixed: Duplicate tool definition error — agent-specific tools now take priority over core tools with the same name.
* Removed: Tool tags from chat overlay (tokens/cost still visible for admins).
* Removed: Redundant read_file, list_directory, search_code tools from Onboarding Agent (uses core tools instead).
* Improved: Tool deduplication in Agent Controller prevents LLM API errors.

= 1.7.3 - 2026-02-15 =
* Streamlined: Removed non-essential agents 

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
* Improved: All API calls now send improved headers (X-Agentic-Site-URL, Site-Name, Plugin-Version, WP-Version, PHP-Version, User-Agent).

= 1.7.0 - 2026-02-14 =
* Added: Tamper-resistant signed API requests for license validation.
* Security: Fail-open design — cached last-known-good state, never breaks customer sites.
* Tests: 457 tests, 1,211 assertions (23 new License_Client tests).

= 1.6.3 - 2026-02-14 =
* Improved: All bundled agent system prompts rewritten with ecosystem context and scope boundaries.
* Improved: Each agent now defers out-of-scope tasks to the appropriate sibling agent.
* Improved: Prompts reference actual available tools and instruct agents to use real data.
* Improved: Removed vague personality sections; replaced with concrete behavioural instructions.
* Improved: Onboarding Agent prompt now lists all key classes, files, and bundled agents.
* Improved: Theme Builder prompt adds accessibility (WCAG 2.1) and modern CSS layout guidance.
* Improved: Dashboard stats now derived from /agents endpoint (fixes N/A display).

= 1.6.2 - 2026-02-14 =
* Fixed: Added missing v1.2.0 changelog entry.
* Fixed: Added missing git tags for v1.4.0 and v1.5.0 releases.
* Tests: 434 tests, 1,172 assertions, 0 failures, 0 risky.

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
* Added: Security log cleanup functionality (30-day retention).
* Improved: Centralized security logging — replaced error_log() calls with Security_Log class.
* Improved: Full PHPCS compliance for WordPress.org standards.
* Fixed: Naming convention violations in uninstall.php.

= 1.1.2 - 2026-02-02 =
* Fixed: Automatic API key saving when returning from provider registration.

= 1.1.1 - 2026-02-02 =
* Added: Dashboard stats widget.
* Added: Developer API key management in Settings.
* Updated: Dashboard with improved UI and proper timestamps.
* Fixed: Active agent count accuracy.

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
* Achieved full WordPress Coding Standards compliance (7,246 auto-fixes).
* Security: Removed all exec() and potential vulnerabilities.
* Simplified namespace to "Agentic".
* Added System Requirements Checker.

== Upgrade Notice ==

= 1.9.0 =
Per-agent license activation system. New bundled agents added (content-writer, seo-assistant, security-assistant, site-auditor, site-doctor, ai-radar). No breaking changes for existing free agents.

= 1.8.1 =
Uploaded agent licensing enforcement. No breaking changes.

= 1.8.0 =
Core tools rebuilt — 11 focused database tools added.

= 1.7.5 =
PHPCS compliance fixes and minor bug fixes. No breaking changes.

= 1.7.0 =
License client and update gating added. No breaking changes.

= 1.6.0 =
User-space permissions and proposals added. All write permissions disabled by default. No breaking changes.

= 1.0.0 =
First stable release. Full WordPress Coding Standards compliance and security hardening.

== Legal ==

By using this plugin you agree to our Terms of Service: https://agentic-plugin.com/terms-of-service/