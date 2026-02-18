# 🎁 Agentic Library – 5 Pre-Built Assistants

> **Ready-to-use AI assistants that solve real WordPress problems. Get inspired, customize, and start earning.**

---

## 📦 What's Inside

We've built and open-sourced **5 fully functional assistants** to help you get started instantly. Each assistant is production-tested, documented, and can be customized for your needs.

---

## 🎯 Assistants

### 🧭 **[WordPress Assistant](wordpress-assistant/agent.php)**
Your guide to WordPress and the AI ecosystem. Answers questions about the plugin and helps new users get started.
- ✅ Answers questions about the platform
- ✅ Helps debug issues
- ✅ Explains concepts and best practices
- ✅ Provides code examples

**Category:** Starter

---

### ✍️ **[Content Assistant](content-assistant/agent.php)**
Helps draft, edit, and optimize blog posts and pages. Suggests improvements, fixes grammar, and enhances readability.
- ✅ Drafts blog posts from outlines
- ✅ Improves tone and readability
- ✅ Generates multiple title options
- ✅ Creates compelling excerpts
- ✅ Optimizes for target keywords

**Category:** Content

---

### 🎨 **[Theme Assistant](theme-assistant/agent.php)**
Helps beginners choose and customise WordPress themes using the Site Editor. Detects your active theme, recommends themes, and guides you through visual customisation.
- ✅ Creates child themes
- ✅ Generates CSS from requirements
- ✅ Updates theme.json
- ✅ Recommends themes based on your needs
- ✅ Guides visual customisation via Site Editor

**Category:** Developer

---

### 🔌 **[Plugin Assistant](plugin-assistant/agent.php)**
Creates complete WordPress plugins from natural language descriptions. Generates WPCS-compliant code with security best practices.
- ✅ Analyzes requirements
- ✅ Generates plugin scaffolding
- ✅ Creates WPCS-compliant code
- ✅ Follows security best practices
- ✅ Generates documentation

**Category:** Developer

---

### 🤖 **[Assistant Trainer](assistant-trainer/agent.php)**
Meta-agent that trains new AI assistants from natural language descriptions.
- ✅ Analyzes requirements
- ✅ Generates assistant scaffolding
- ✅ Creates tool definitions
- ✅ Validates assistant code
- ✅ Generates system prompts

**Category:** Developer

---

## 🚀 Quick Start

### Installation

1. **Clone the repo:**
   ```bash
   git clone https://github.com/renduples/agent-builder.git
   cd agent-builder/library
   ```

2. **Activate in WordPress:**
   - Go to **Agentic → Assistants**
   - All 5 assistants should appear
   - Click **Activate** on any assistant

3. **Start using:**
   - Go to **Agentic → Chat**
   - Start typing commands!

### Example Usage

**Content Assistant:**
> "Draft a blog post outline about WordPress security, targeting developers"

**Theme Assistant:**
> "Help me choose a theme for my photography portfolio"

---

## 🔧 Customizing Assistants

Each assistant is open-source and fully customizable:

### 1. **Clone & Modify**
```bash
cp -r content-assistant/ content-assistant-pro/
cd content-assistant-pro/
# Edit agent.php to add features
```

### 2. **Add New Tools**
```php
public function get_tools(): array {
    return array_merge(
        parent::get_tools(),
        [
            'my_new_tool' => 'Description of what it does'
        ]
    );
}
```

### 3. **Test Locally**
- Upload modified assistant to WordPress
- Test in Admin → Agentic → Chat
- Check audit logs for any issues

### 4. **Deploy & Earn**
- Submit to [agentic-plugin.com/submit-agent/](https://agentic-plugin.com/submit-agent/)
- Get 70% revenue share
- Start earning on day one

---

## 🌟 Assistant Combinations

Combine assistants for powerful workflows:

### **Content Creation Pipeline**
1. Content Assistant (writes post)
2. Plugin Assistant (creates custom functionality)

### **Development Workflow**
1. Theme Assistant (creates themes)
2. Plugin Assistant (generates plugins)
3. Assistant Trainer (builds custom assistants)

---

## 🤝 Contributing

Want to improve a pre-built assistant or create a new one?

1. **Fork** the repo
2. **Create a new folder** under `library/` or edit existing
3. **Follow our standards** (WordPress coding standards, GPL v2+)
4. **Test thoroughly** in WordPress
5. **Submit a PR** with description and features

See [CONTRIBUTING.md](../CONTRIBUTING.md) for detailed guidelines.

---

## 🆘 Need Help?

- **Docs** – [agentic-plugin.com/documentation](https://agentic-plugin.com/documentation/)
- **GitHub Issues** – [github.com/renduples/agent-builder/issues](https://github.com/renduples/agent-builder/issues)
- **GitHub Discussions** – [github.com/renduples/agent-builder/discussions](https://github.com/renduples/agent-builder/discussions)

---

**Built with ❤️ by the Agentic community**
