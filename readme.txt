=== Agentic Plugin ===
Contributors: agentic-plugin
Tags: openai, chatgpt, gpt, anthropic, claude, llm, ai, agents, chatbot, automation, marketplace, plugins
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The marketplace for AI agents on WordPress. Imagine->Build->Sell

== Description ==

Agentic Plugin brings an agent ecosystem to WordPress just like plugins and themes, but for AI capabilities:

* **Install agents from the marketplace** — Browse and install community-built agents with one click
* **Activate and deactivate** — Control which agents run on your site, just like plugins
* **Build and sell your own** — Create agents and share or sell them to the community
* **Purchase premium agents** — Access powerful agents from trusted developers

= Agent Types =

* **Content Agents**: Draft posts, optimize SEO, generate alt text, translate content
* **Admin Agents**: Monitor security, manage backups, optimize performance
* **E-commerce Agents**: Manage products, optimize pricing, handle inventory
* **Frontend Agents**: Chat with visitors, moderate comments, handle support
* **Developer Agents**: Generate code, debug issues, build themes and plugins
* **Marketing Agents**: Create campaigns, generate platform-optimized content, and manage multi-channel presence.

= Key Features =

* Plugin like Agent management interface (install, activate, deactivate, delete)
* Multiple AI provider support (OpenAI, Anthropic, local models)
* Comprehensive audit logging
* Human-in-the-loop approval workflow
* Rate limiting and cost controls
* Open source core with commercial ecosystem support

= Requirements =

* WordPress 6.4 or higher
* PHP 8.1 or higher
* MySQL 8.0 or MariaDB 10.6

== Installation ==

1. Download the latest version from: https://github.com/renduples/agentic-plugin/releases/latest/download/agentic-plugin.zip
2. Upload via Plugins → Add New → Upload Plugin
3. Activate the plugin through the 'Plugins' menu
4. Go to 'Agentic → Settings' to add your API key
5. Go to 'Agentic → Agents' to browse and install agents
6. Activate agents to enable their capabilities

== Frequently Asked Questions ==

= How is this different from regular plugins? =

Agents are like plugins but specifically designed for AI-powered automation. They follow a standard structure, can register tools for AI models to use, and integrate with the Agentic approval and audit system.

= Can I build my own agents? =

Yes! Agents follow a simple structure similar to WordPress plugins. Create an agent.php file with headers, register your tools, and share with the community.

= Where should I install my custom agents? =

Install custom agents in `wp-content/agents/` directory (not inside the plugin folder). This ensures your agents survive plugin updates. 

The plugin comes with 10 bundled agents in `wp-content/plugins/agentic-plugin/library/` that update with the plugin, but your custom agents in `wp-content/agents/` will never be touched during updates.

= Is this production ready? =

Agentic Plugin is currently in beta. The core functionality is stable and ready for testing in production environments. We recommend thorough testing before deploying on critical sites.

= Which AI providers are supported? =

Currently supports OpenAI, Anthropic (Claude), and local models via Ollama.

= Is my data sent to external services? =

Only when using cloud AI providers. Local model support is available for privacy-sensitive deployments.

== Changelog ==

= 1.0.0 =
* **Production Release** - First stable release
* Automatic update checking via daily WordPress cron
* Comprehensive error handling (expired, activation limit, mismatch, invalid)
* Complete marketplace API documentation for developers
* All components ready for agent marketplace

= 1.0.0-beta =
* **Beta Release** - Production-ready release candidate
* WordPress Coding Standards compliance (7,246 auto-fixes applied)
* Removed shell execution (exec) - now uses safe tokenizer-based validation
* Translation files added for Spanish, French, and German (95% coverage)
* Ready for WordPress.org submission
* All security and compliance issues resolved

= 0.1.3-alpha =
* Plugin renamed to "Agentic Plugin" for clarity
* System Requirements Checker with REST API endpoints
* Namespace simplified from Agentic\Core to Agentic
* All constants renamed (AGENTIC_CORE_* → AGENTIC_*)
* Text domain standardized to 'agentic-plugin'

= 0.1.2-alpha =
* Async Job Queue System for long-running tasks
* Background job processing via WordPress Cron
* Real-time progress tracking (0-100%)
* REST API endpoints for job management
* Agent Builder Job Processor example implementation

= 0.1.0-alpha =
* Initial development release
* Agent management system (install, activate, deactivate)
* Agent library with sample agents
* REST API endpoints
* Admin interface scaffolding

== Upgrade Notice ==

= 1.0.0 =
First stable release! Smooth upgrade from beta - no database migrations required.

= 1.0.0-beta =
First beta release! Production-ready. Major improvements to code quality and security.

= 0.1.3-alpha =
Major naming changes: Namespace simplified. System Requirements Checker added.

= 0.1.2-alpha =
Async job queue system added for long-running tasks. No breaking changes.

= 0.1.0-alpha =
Initial alpha release. Not recommended for production use.
