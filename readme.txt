=== Agent Builder ===
Build, deploy, and manage AI agents directly in WordPress. Supports multiple LLMs with secure, permission-controlled tools.
Contributors: agenticplugin
Tags: ai, llm, ai-agent, chatbot, openai, anthropic, xai
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.7.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, install, and manage AI-powered agents in WordPress. Supports OpenAI, Anthropic, xAI, Google, Mistral, and local Ollama models.

== Description ==

Agent Builder adds an AI agent system to WordPress. Agents are modular units of AI functionality that can be installed, activated, and deactivated through the admin interface — similar to how plugins work.

Each agent defines a set of tools (PHP functions the AI model can call), a system prompt, and optional scheduled tasks or event listeners. The plugin handles LLM communication, tool execution, conversation history, security scanning, and audit logging.

= What It Does =

* **Chat interface** — An admin chat screen and optional frontend shortcode (`[agentic_chat]`) for interacting with agents.
* **Agent management** — Activate, deactivate, or remove agents from the Agents admin screen. Custom agents are stored in `wp-content/agents/` and survive plugin updates.
* **Multi-provider LLM support** — Connect to OpenAI, Anthropic (Claude), xAI (Grok), Google (Gemini), Mistral, or local Ollama models. Configure your provider and API key in Settings.
* **Tool execution** — Agents can read files, query WordPress data, read posts, make suggestions and more. All tool calls go through a permission system.
* **Permissions and approvals** — Six granular permission scopes (all disabled by default). A confirmation mode creates proposals with diffs instead of executing changes directly.
* **Scheduled tasks** — Agents can define recurring tasks that run via wp_cron, with optional LLM-powered execution.
* **Event listeners** — Agents can react to WordPress action hooks (e.g., user registration, failed logins) synchronously or asynchronously.
* **Audit logging** — All agent actions, tool calls, and chat sessions are logged with timestamps and details.
* **Security** — Input scanning, rate limiting, PII detection, and a security event log.
* **Image and voice input** — Upload images for vision-capable models; use voice input via the Web Speech API.

= Bundled Agents =

The plugin ships with five agents:

1. **Onboarding Agent** — Guides new users through setup and introduces the other agents.
2. **Content Builder** — Drafts and edits WordPress posts and pages.
3. **Agent Builder** — Creates new agents from natural language descriptions.
4. **Plugin Builder** — Generates WordPress plugin scaffolding from requirements.
5. **Theme Builder** — Helps implement WordPress themes with accessibility and modern CSS practices.

= Creating Custom Agents =

Place an `agent.php` file in `wp-content/agents/your-agent-name/`. The file must contain a class extending `Agent_Base` with methods for `get_id()`, `get_name()`, `get_description()`, `get_system_prompt()`, and optionally `get_tools()` and `execute_tool()`. Register it via the `agentic_register_agents` action hook. Custom agents are sandboxed with permission checks and proposal workflows.

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

To display a chat interface on the frontend, add the `[agentic_chat]` shortcode to any page or post.

== Frequently Asked Questions ==

= Which AI providers are supported? =
OpenAI (GPT-4o, GPT-4, etc.), Anthropic (Claude), xAI (Grok), Google (Gemini), Mistral, and local models via Ollama. You choose your provider and model in Settings.

= Is my data sent to external services? =
Only when you use a cloud-based AI provider. Chat messages and tool context are sent to the provider's API endpoint to generate responses. No data is sent without your configuration — you must enter an API key and initiate a conversation. If you want to keep all data local, use Ollama. See the "External Services" section below for full details.

= Can I use this without an API key? =
Yes, if you run Ollama locally. For cloud providers (OpenAI, Anthropic, xAI, Google, Mistral), you need an API key from that provider. API usage is billed directly by the provider, not by this plugin.

= Can I create my own agents? =
Yes. Create an `agent.php` file in `wp-content/agents/your-agent-name/`, extend the `Agent_Base` class, and register via the `agentic_register_agents` hook. The plugin auto-discovers agents in that directory. Custom agents are kept separate from the plugin and survive updates.

= What permissions do agents have? =
All write permissions are disabled by default. You can enable six granular scopes (file writes, option modifications, post meta, etc.) in **Agentic → Settings → Permissions**. A "confirmation mode" shows proposed changes as diffs for you to approve or reject before execution.

= Does uninstalling remove all data? =
Yes. Uninstalling (not just deactivating) removes all plugin options, custom database tables, transients, user meta, and scheduled cron jobs. Custom agents in `wp-content/agents/` are not deleted.

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
* Fixed: Automatic API key saving when returning from provider registration.

= 1.1.1 - 2026-02-02 =
* Added: Dashboard stats widget.
* Added: Plugin license validation system.
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
* Added full i18n support (Spanish, French, German .po/.mo files).
* Achieved full WordPress Coding Standards compliance (7,246 auto-fixes).
* Security: Removed all exec() and potential vulnerabilities.
* Simplified namespace to "Agentic".
* Added System Requirements Checker.
* Brand consistency updates ("Agent Builder").

== Upgrade Notice ==

= 1.7.5 =
PHPCS compliance fixes and minor bug fixes. No breaking changes.

= 1.7.0 =
License client and update gating added. No breaking changes.

= 1.6.0 =
User-space permissions and proposals added. All write permissions disabled by default. No breaking changes.

= 1.0.0 =
First stable release. Full WordPress Coding Standards compliance and security hardening.