=== Agentic Plugin ===
Contributors: agenticplugin
Donate link: https://agentic-plugin.com/donate
Tags: ai, agents, llm, automation, chatbot
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build and manage AI agents directly in WordPress — no coding required. Describe what you want, and let AI create powerful automations for content, admin, e-commerce, and more.

== Description ==

Agentic Plugin turns WordPress into an AI-agent ecosystem — similar to how plugins and themes extend your site, but powered by large language models (LLMs).

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

1. Search for "Agentic Plugin" in **Plugins → Add New** and install it (or upload the ZIP via **Add New → Upload Plugin**).
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
* Brand consistency updates ("Agentic Plugin").

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

= 1.1.0 =
Important: GDPR-compliant uninstall added. No data loss or breaking changes. Recommended update for all users.

= 1.0.0 =
First stable release. Smooth upgrade — no migrations needed. Full standards compliance and security hardening.

= 0.1.x-alpha =
Early development versions. Upgrade to 1.0.0+ for stability, i18n, and security fixes.