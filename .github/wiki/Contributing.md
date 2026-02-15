# Contributing to Agent Builder

Welcome to the Agent Builder community! We're excited to have you here. Whether you're fixing bugs, building agents, improving docs, or spreading the word – your contribution matters.

## Ways to Contribute

### 1. 🤖 Build & Share Agents

The best way to contribute is to build amazing agents and share them with the community.

**Steps:**

1. Create a new folder in `library/your-agent-name/`
2. Build your agent using `Agent_Base` class
3. Test thoroughly in WordPress
4. Submit via [agentic-plugin.com/submit-agent/](https://agentic-plugin.com/submit-agent/)
5. Share on Twitter/GitHub Discussions when approved

**Agent Ideas:**

- WooCommerce automation
- Multi-language content translation
- Email marketing automation
- Database optimization
- Security vulnerability scanning
- Analytics & reporting

### 2. 🐛 Report & Fix Bugs

Found a bug? We'd love to hear about it!

**Reporting:**

1. Check [existing issues](https://github.com/renduples/agent-builder/issues)
2. Open a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - WordPress/PHP version
   - Any error messages

**Fixing:**

1. Fork the repo
2. Create a feature branch: `git checkout -b fix/issue-123`
3. Write tests if applicable
4. Follow WordPress coding standards
5. Submit a pull request

### 3. 📖 Improve Documentation

Great docs = more developers using Agentic = more agents = more awesome.

**Areas to improve:**

- API reference examples
- Tutorial videos or GIFs
- Security model documentation
- Architecture diagrams
- FAQ & troubleshooting
- Agent submission walkthrough

**How to contribute:**

1. Edit docs in [library/README.md](library/README.md) or create new guides
2. Submit PR with improvements
3. We'll add your name to contributors

### 4. ✨ Enhance Core Features

Help us build the fundamental platform better.

**High-impact areas:**

- Agent registry improvements
- REST API enhancements
- UI/UX improvements
- Performance optimizations
- Test coverage expansion
- Security hardening

**Process:**

1. Open an issue first to discuss
2. Get feedback from maintainers
3. Fork and develop on feature branch
4. Submit PR with tests and docs

### 5. 📣 Spread the Word

Community grows through word-of-mouth. Help us reach more developers!

**Ways to amplify:**

- **Social media**: Tweet about agents you build or cool features
- **Blog posts**: Write tutorials on agent development
- **YouTube**: Create demo videos showing agents in action
- **Communities**: Share in WordPress, dev, and AI communities
- **Presentations**: Talk about Agentic at meetups/conferences
- **Reviews**: Leave GitHub stars and positive feedback

---

## Development Setup

### Prerequisites

- WordPress 6.4+
- PHP 8.1+
- Git
- API key (OpenAI/Anthropic)

### Local Development

1. **Clone the repo:**

   ```bash
   git clone https://github.com/renduples/agent-builder.git
   cd agent-builder
   ```
2. **Install in WordPress:**

   ```bash
   cd /path/to/wordpress/wp-content/plugins
   ln -s /path/to/agent-builder
   ```
3. **Activate in WordPress Admin**
4. **Test your changes:**

   - Visit `/wp-admin/` and navigate to Agentic
   - Try the included agents
   - Check the audit log for issues

### Code Style

We follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

```php
// ✅ Good
if ( isset( $value ) ) {
    do_something();
}

// ❌ Bad
if (isset($value)){
    doSomething();
}
```

**Check your code:**

```bash
# (WordPress developers typically use PHPCS)
phpcs --standard=WordPress your-file.php
```

### File Structure

```
agent-builder/
├── agent-builder.php          # Main plugin file
├── includes/                 # Core classes
│   ├── class-agent-base.php  # Extend this
│   ├── class-*.php           # Other core
├── library/                  # Pre-built agents
│   ├── content-builder/
│   ├── your-agent/           # Your agent here
├── admin/                    # Dashboard pages
├── templates/                # Frontend templates
└── assets/                   # CSS/JS
```

### Creating a Test Agent

Quick way to test the system:

1. Create `library/test-demo/agent.php`:

   ```php
   <?php
   /**
    * Agent Name: Test Demo
    * Description: Simple test agent
    * Version: 1.0.0
    * Author: You
    * License: GPL v2 or later
    */

   namespace Agentic\Agents;

   class Test_Demo extends \Agentic\Agent_Base {
       public function get_name(): string {
           return 'Test Demo';
       }

       public function get_description(): string {
           return 'A simple test agent';
       }

       public function get_tools(): array {
           return [
               'demo_tool' => 'A test tool'
           ];
       }

       public function handle_request(string $input): string {
           return "You said: $input";
       }
   }
   ```
2. Go to Agentic → Installed Agents and enable it
3. Test in Agentic → Agent Chat

---

## PR Guidelines

### Before Submitting

- [ ] Code follows WordPress standards
- [ ] No hardcoded API keys or credentials
- [ ] Tests pass (if applicable)
- [ ] Documentation is updated
- [ ] Commit messages are clear
- [ ] No unrelated changes bundled together

### PR Title Format

```
type(scope): description

Types: feat, fix, docs, style, refactor, perf, test
Scopes: core, agents, api, ui, security

Examples:
✅ feat(api): add agent versioning endpoint
✅ fix(core): prevent race condition in approval queue
✅ docs: add agent submission tutorial
```

### PR Description Template

```markdown
## Description
Brief explanation of what this PR does.

## Related Issue
Closes #123

## Changes
- Specific change 1
- Specific change 2
- Specific change 3

## Testing
How to test this change manually.

## Screenshots (if UI change)
Attach relevant images.
```

---

## Community Guidelines

### Be Respectful

- Treat all community members with respect
- No harassment, discrimination, or hate speech
- Give credit where it's due
- Assume good intent

### Be Helpful

- Help newer developers learn
- Answer questions patiently
- Share knowledge generously
- Celebrate others' contributions

### Be Constructive

- Provide specific, actionable feedback
- Suggest improvements, not just criticism
- Help fix issues you report
- Contribute positively to discussions

### Be Secure-First

- Never suggest hardcoding secrets
- Always recommend using WordPress options or .env
- Report security issues privately to security@agentic-plugin.com
- Don't publicly disclose vulnerabilities

---

## Levels of Contribution

### Level 1: User

- Use Agentic
- Give feedback
- Report bugs
- Share your agents

### Level 2: Contributor

- Fix bugs
- Improve docs
- Build demo agents
- Help in discussions

### Level 3: Maintainer

- Manage PRs & issues
- Set roadmap
- Review agents
- Support community

Working your way up? Start anywhere and grow naturally!

---

## Recognition

Contributors are recognized in:

- **GitHub**: Added as collaborator
- **Website**: Listed in community section
- **Releases**: Mentioned in changelog
- **Updates**: Credited in agent submissions

---

## Questions?

- 💬 **GitHub Issues** – Ask technical questions
- **GitHub Discussions** – Share ideas and feedback
- 🤖 **Agent Chat** – Use the Developer Agent to ask questions
- 📧 **Email** – support@agentic-plugin.com

---

## License

By contributing, you agree that your contributions will be licensed under GPL v2 or later, consistent with the project license.

---

**Thank you for making Agent Builder better! 🚀**

Built with ❤️ by the Agent Builder community
