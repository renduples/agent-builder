# ⚡ Agent Builder – Quick Start (5 minutes)

Welcome! Let's get you up and running with AI agents in WordPress **right now**.

---

## 🎯 Goal: Get Your First Agent Working

By the end of this, you'll have:
- ✅ Plugin installed
- ✅ First agent running
- ✅ Agent chat working
- ✅ Understanding of what's possible

**Total time: 5 minutes**

---

## Step 1: Install (1 min)

### Option A: Git Clone (Recommended)
```bash
cd wp-content/plugins
git clone https://github.com/renduples/agent-builder.git
```

### Option B: ZIP Download
1. Go to [GitHub releases](https://github.com/renduples/agent-builder/releases)
2. Download the latest `.zip` file
3. Upload via WordPress Admin → Plugins → Upload Plugin

### Option C: WordPress Admin
1. Go to WordPress Admin → Plugins
2. Click "Add New"
3. Search for "agentic"
4. Click "Install Now"
5. Click "Activate"

---

## Step 2: Activate Plugin (1 min)

1. Go to **WordPress Admin**
2. Click **Plugins** in the left menu
3. Find **Agent Builder**
4. Click **Activate**

You should now see **Agentic** in the left menu! 🎉

---

## Step 3: Configure (2 min)

1. Click **Agentic → Settings**
2. Paste your **API Key** (see below for how to get one)
3. Leave other settings as default
4. Click **Save Changes**

### Get an API Key (2 options)

#### Option 1: OpenAI (Free trial)
1. Go to [platform.openai.com](https://platform.openai.com)
2. Sign up (free account)
3. Click **API keys** in settings
4. Click **Create new secret key**
5. Copy and paste into Agentic Settings

#### Option 2: Anthropic (Free trial)
1. Go to [console.anthropic.com](https://console.anthropic.com)
2. Sign up (free account)
3. Click **API keys**
4. Click **Create key**
5. Copy and paste into Agentic Settings

**Free tier includes ~$5-10 of free credit.**

---

## Step 4: Run Your First Agent (1 min)

1. Click **Agentic → Agents**
2. You should see **10 pre-built agents** listed
3. Click **Activate** on "Content Builder"
4. Go to **Agentic → Agent Chat**
5. Type something like:

> Ask me to write a blog post about WordPress security best practices

**Hit enter and watch the magic happen!** ✨

---

## 🎉 Congratulations!

You've just run your first AI agent. Here's what happened:

1. Your message went to [OpenAI](https://platform.openai.com) or [Anthropic](https://anthropic.com)
2. The Content Builder agent reasoned about your request
3. It drafted your content
4. It returned the result
5. **Everything is logged in Agentic → Audit Log**

---

## 💡 What You Can Do Now

### 1. Try Other Pre-Built Agents

Go to **Agentic → Agents** and activate:

- **Content Builder** – Draft blog posts
- **Theme Builder** – Build WordPress themes
- **Plugin Builder** – Generate WordPress plugins

Try them in **Agentic → Agent Chat**

### 2. Explore the Audit Log

Go to **Agentic → Audit Log** to see every action the agents took. This is how you track what agents do and debug issues.

### 3. Build Your First Custom Agent

Create a file at `wp-content/agents/my-agent.php`:

```php
<?php
/**
 * Agent Name: My First Agent
 * Description: A test agent
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

class My_First_Agent extends Agent_Base {
    public function get_name() {
        return 'My First Agent';
    }

    public function get_description() {
        return 'My very first custom agent';
    }

    public function get_tools() {
        return [
            'hello_world' => 'Say hello'
        ];
    }

    public function handle_request($input) {
        return "You said: $input. I can help with that!";
    }
}
```

Go to **Agentic → Agents** and you'll see it listed!

### 4. Submit to Marketplace

When you're happy with your agent:

1. Go to [agentic-plugin.com/submit-agent](https://agentic-plugin.com/submit-agent)
2. Fill out the form
3. We review within 14 days
4. **Earn 70% on every install!**

---

## 🆘 Getting Help

### Something Broken?

1. **Check error logs** – WordPress admin shows errors
2. **Review Audit Log** – Agentic → Audit Log
3. **Google the error** – Likely covered on StackOverflow
4. **Ask in GitHub Issues** – [github.com/renduples/agent-builder/issues](https://github.com/renduples/agent-builder/issues)

### Common Issues

**"API key not working"**
- [ ] Did you copy the full key?
- [ ] Is it for the right service (OpenAI vs Anthropic)?
- [ ] Did you click Save Changes?

**"Agent not showing up"**
- [ ] Did you refresh the page?
- [ ] Check Agentic → Settings first
- [ ] Check WordPress error logs

**"Agent returns empty response"**
- [ ] Check Audit Log for errors
- [ ] Verify API key has credits left
- [ ] Try a simpler request
- [ ] Check browser console for JS errors

### Get Real Help

- 📧 **Email**: support@agentic-plugin.com
- 💬 **Discord**: [join our community](https://discord.gg/agentic)
- 🐦 **Twitter**: [@agenticplugin](https://twitter.com/agenticplugin)
- 📖 **Docs**: [agentic-plugin.com](https://agentic-plugin.com)

---

## 📚 What's Next?

Once you're comfortable, explore:

1. **[Customize an agent](./AGENT_DEVELOPMENT.md)** – Modify a pre-built agent
2. **[Build from scratch](./README.md#building-your-first-agent)** – Create a unique agent
3. **[Earn money](https://agentic-plugin.com/submit-agent)** – Submit to marketplace
4. **[Join the community](https://github.com/renduples/agent-builder/discussions)** – Share your work

---

## 🚀 Pro Tips

### Tip 1: Start Small
Try the Content Builder or Theme Builder first. They're simple and show you what's possible.

### Tip 2: Test in Admin First
Always test new agents in **Agentic → Agent Chat** (admin-only) before enabling on your site.

### Tip 3: Watch the Audit Log
Every agent action is logged. Check **Agentic → Audit Log** to understand what's happening.

### Tip 4: Read Agent Descriptions
Go to **Agentic → Agents** and click on each agent. The description shows what it can do.

### Tip 5: Join the Community
The best way to learn is from other developers. Join [GitHub Discussions](https://github.com/renduples/agent-builder/discussions) and ask questions!

---

## ✅ Checklist

- [ ] Plugin installed & activated
- [ ] API key added in Settings
- [ ] Content Builder activated
- [ ] Chat with agent works
- [ ] Audit log shows agent actions
- [ ] Tried at least 2 different agents
- [ ] Ready to build!

---

## 🎓 Learning Path

**Beginner** (done!)
- ✅ Install plugin
- ✅ Use pre-built agents
- ✅ Understand audit logs

**Intermediate** (next)
- [ ] Customize a pre-built agent
- [ ] Build your first custom agent
- [ ] Debug issues
- [ ] Join GitHub Discussions

**Advanced**
- [ ] Build complex agents
- [ ] Publish to marketplace
- [ ] Earn significant income
- [ ] Mentor others

---

## 🌟 Success Stories

**"Customized the Content Builder for our agency. Saved 10+ hours/week."** — Dev Team

**Ready to write your own success story?** Let's go! 🚀

---

## Questions?

This is meant to be quick, so we left out details. That's intentional!

For deep dives, see:
- [Main README](./README.md) – Full overview
- [Documentation](https://agentic-plugin.com/docs) – Comprehensive guide
- [Agent Development](./AGENT_DEVELOPMENT.md) – Build agents
- [Roadmap](./ROADMAP.md) – What's coming

---

**Happy coding!** 💻

And please — share your creations with the community. That's how we all grow.

**🚀 Ready to build your first agent?** Start in Step 3 above!
