# 🤖 Build AI Agents without writing code. Describe the AI agent you want and let WordPress build it for you.

[![WordPress Plugin](https://img.shields.io/badge/WordPress-6.4+-blue)](https://wordpress.org)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-purple)](https://www.php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![GitHub Stars](https://img.shields.io/github/stars/renduples/agentic-plugin?style=social)](https://github.com/renduples/agent-builder)

---

## 🚀 What is the Agent Builder?

The Agent Builder brings an ecosystem to WordPress just like plugins and themes, but for AI capabilities.

If you've built a plugin or theme before, you already know the process. Now harness AI reasoning to solve WordPress problems at scale.

With this plugin you will be able to build most of your AI Agents without writing code.

### Why the Agent Builder Matters

- **500K+ WordPress sites** waiting for AI-powered agents to enhance Wordpress
- **Open-source community** – Transparent, collaborative, GPL v2 licensed

---

## ⚡ Quick Start (5 minutes)

### 1. Install the Plugin

```bash
cd wp-content/plugins
git clone https://github.com/renduples/agent-builder.git
# OR manually upload the ZIP to your WordPress plugins
```

### 2. Activate & Configure

1. Go to WordPress Admin → Plugins
2. Activate **Agent Builder**
3. Go to **Agentic → Settings**
4. Add your xAI/OpenAI/Anthropic API key
5. Enable your primary agent

### 3. Start using your primary Agent

- **Admin Panel**: Chat with agents → **Agentic → Agent Chat**
- **Frontend**: Use `[agentic_chat]` shortcode on any page
- **REST API**: POST to `/wp-json/agentic/v1/chat`

That's it. Your primary AI agent is live.

---

## 🎯 What You Can Build

The Agent Builder comes with **10 pre-built agents** to jumpstart development:

| Agent | Use Case |
|-------|----------|
| **SEO Analyzer** | Automatic on-page SEO audits |
| **Content Assistant** | AI-powered post drafting & optimization |
| **Product Describer** | WooCommerce product descriptions |
| **Social Media Agent** | Schedule & auto-compose social posts |
| **Code Generator** | Custom code generation for devs |
| **Theme Builder** | Quick WordPress theme customization |
| **Security Monitor** | AI vulnerability scanning |
| **Comment Moderator** | Smart spam detection & responses |
| **Agent Builder** | Visual agent builder (meta) |
| **Onboarding Agent** | Your guide to the Agent Builder ecosystem |

---

## 💡 Core Features

### AI-Native Architecture
- **Multi-model support**: OpenAI, Anthropic, xAI, local LLMs
- **Built-in security**: Sandboxed execution, approval workflows
- **Reasoning engine**: Agents explain their logic, not just generate outputs
- **Response caching**: Reduce API costs while maintaining freshness

### Developer-Friendly
- **Extend Agent_Base**: Simple PHP class inheritance (like plugins)
- **Full WordPress API access**: Call any WordPress function safely
- **Rich tooling**: 20+ pre-built tools (file I/O, API calls, data queries)
- **Audit logging**: Every agent action is logged and auditable
- **Version management**: Auto-update agents across all installations
- **Usage analytics**: Track installs, ratings, active users

---

## 🏗️ Architecture

```
WordPress Installation
├── wp-content/
│   ├── plugins/
│   │   └── agent-builder/
│   │       ├── agent-builder.php (Plugin entry point)
│   │       ├── includes/
│   │       │   ├── class-agent-base.php (Extend this)
│   │       │   ├── class-agent-controller.php (Orchestration)
│   │       │   ├── class-openai-client.php (LLM integration)
│   │       │   ├── class-audit-log.php (Compliance)
│   │       │   ├── class-approval-queue.php (Safety)
│   │       │   └── ... (10+ more core classes)
│   │       ├── library/ (Bundled agents - 10 pre-built)
│   │       │   ├── seo-analyzer/agent.php
│   │       │   ├── content-assistant/agent.php
│   │       │   ├── social-media/agent.php
│   │       │   └── ... (7 more)
│   │       ├── admin/ (Dashboard UI)
│   │       └── templates/
│   │           └── chat-interface.php (User interface)
│   │
│   └── agents/ (User-installed agents - survives plugin upgrades)
│       ├── my-custom-agent/
│       │   └── agent.php
│       └── another-agent/
│           └── agent.php
```

---

## 🔧 Building Your First Agent (15 minutes)

### Create `wp-content/agents/my-custom-agent/agent.php`

**Note:** User agents are stored in `wp-content/agents/` to survive plugin updates. Bundled agents are in `wp-content/plugins/agent-builder/library/`.

```php
<?php
/**
 * Agent Name: My Custom Agent
 * Description: Does something awesome
 * Version: 1.0.0
 * Author: You
 * License: GPL-2.0-or-later
 */

namespace Agentic\Agents;

class My_Custom_Agent extends \Agentic\Agent_Base {
    
    public function get_name(): string {
        return 'My Custom Agent';
    }

    public function get_description(): string {
        return 'Solves a specific WordPress problem with AI';
    }

    public function get_tools(): array {
        return [
            'search_posts' => 'Search WordPress posts by keyword',
            'analyze_content' => 'AI analysis of post content',
            'update_post' => 'Update post with AI suggestions',
        ];
    }

    public function handle_request(string $input): string {
        // Your agent logic here
        return $this->call_llm($input);
    }
}
```

**Next steps:**
1. Add tools to handle specific tasks
2. Test in WordPress admin
3. Submit to marketplace (agentic-plugin.com/submit-agent/)

---

## 🔐 Security & Compliance

- ✅ **Sandboxed execution** – Agents run in isolated contexts
- ✅ **Capability-based permissions** – Fine-grained access control
- ✅ **Approval workflows** – Sensitive actions require review
- ✅ **Audit logging** – Every action is tracked
- ✅ **Rate limiting** – Prevent abuse
- ✅ **GDPR-ready** – Privacy by design

---

## 📚 Documentation

All documentation is now available on our [GitHub Wiki](https://github.com/renduples/agent-builder/wiki):

- **[Quick Start Guide](https://github.com/renduples/agent-builder/wiki/Quickstart)** – Get up and running in 5 minutes
- **[Contributing Guidelines](https://github.com/renduples/agent-builder/wiki/Contributing)** – Help build the future
- **[Security Policy](https://github.com/renduples/agent-builder/wiki/Security)** – Report vulnerabilities
- **[Technical Roadmap](https://github.com/renduples/agent-builder/wiki/Roadmap)** – What's next
- **[Release Notes](https://github.com/renduples/agent-builder/wiki/Release-Notes)** – Version history

### Additional Resources

- [Agents Marketplace](https://agentic-plugin.com/agents/)
- [Ask Agent](https://agentic-plugin.com/agent-chat/)
- [Discussions](https://agentic-plugin.com/discussions/)
- [Submit Agent](https://agentic-plugin.com/submit-agent/)

---

## 🤝 Contributing

We welcome contributions! See our [Contributing Guidelines](https://github.com/renduples/agent-builder/wiki/Contributing) for details.

**Quick ways to contribute:**
1. **Build an agent** and test it thoroughly
2. **Improve core** – Submit PRs to [issues](https://github.com/renduples/agent-builder/issues)
3. **Report bugs** – Open an issue with reproduction steps
4. **Suggest features** – Use GitHub Discussions
5. **Improve docs** – Help other developers succeed

## 🆘 Support & Help

- **Issues**: https://github.com/renduples/agent-builder/issues
- **Discussions**: https://github.com/renduples/agent-builder/discussions
- **Security**: See our [Security Policy](https://github.com/renduples/agent-builder/wiki/Security)

---

## 📋 Requirements

- **WordPress** 6.4 or higher
- **PHP** 8.1 or higher
- **API Key** (OpenAI, Anthropic, or XAI)
- **cURL** for external API calls

---

## 🎓 Learn By Doing

Start with these quick wins:

1. **Use a pre-built agent** (5 min) – Enable one of the 10 included agents
2. **Customize an agent** (15 min) – Modify an existing agent slightly
3. **Build your own agent** (45 min) – Create a simple custom agent
4. **Publish your agent** (10 min) – Submit to marketplace

Reference guides:
- [Roadmap Overview](https://github.com/renduples/agent-builder/wiki/Roadmap)
- [Use Cases](https://github.com/renduples/agent-builder/wiki/Use-Cases)
- [Architecture](https://github.com/renduples/agent-builder/wiki/Architecture)
- [WordPress Core AI](https://github.com/renduples/agent-builder/wiki/WordPress-Core-AI)

---

## 🌟 What Others Are Building

Community agents in the marketplace:

- **Invoice Generator** – AI creates WooCommerce invoices
- **Email Responder** – Auto-replies with AI-powered suggestions
- **Backup Monitor** – Proactive backup integrity checking
- **Customer Segmentation** – ML-based audience analysis
- **Translation Agent** – Multi-language content automation

[Browse the marketplace →](https://agentic-plugin.com/agents/)

---

## 💬 Community & Support

- **GitHub Discussions** – Ask questions, share ideas (https://github.com/renduples/agent-builder/discussions)
- **Twitter/X** – [@agenticplugin](https://twitter.com/agenticplugin)
- **Email** – support@agentic-plugin.com

---

## 📄 License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.

This is an **independent community project** — not affiliated with or endorsed by the WordPress Foundation, Automattic or any of the AI Providers.

---

## 🚀 Get Started Now

1. **Install**: cd wp-content/plugins `git clone https://github.com/renduples/agent-builder.git`
2. **Activate**: Go to WordPress admin → Plugins
3. **Configure**: Add API key in Settings
4. **Test**: Try the SEO Analyzer or Content Assistant
5. **Build**: Create your first custom agent
6. **Share**: Submit your agent to the marketplace

[Submit Your Agent →](https://agentic-plugin.com/submit-agent/)

---

**Made with ❤️ by the Agent Builder community**

[🌍 Website](https://agentic-plugin.com) • [📖 Docs](https://github.com/renduples/agent-builder/wiki) • [💬 GitHub](https://github.com/renduples/agent-builder) • [🐦 Twitter](https://twitter.com/agenticplugin)
