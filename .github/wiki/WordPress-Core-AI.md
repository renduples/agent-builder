# Agentic Plugin & WordPress Core AI

> Understanding how Agentic Plugin complements the official WordPress AI initiatives

---

## Overview

The WordPress community has an active AI Team building foundational infrastructure for AI in WordPress. Agentic Plugin is designed to complement—not compete with—these efforts by focusing on the agent marketplace and ecosystem layer.

**Key Insight:** WordPress Core AI builds the plumbing. Agentic Plugin builds the marketplace of agents that use that plumbing.

---

## WordPress AI Team Initiatives

The official [WordPress AI Team](https://make.wordpress.org/ai/) (formed May 2025) is building four core components:

### 1. PHP AI Client SDK

**Status:** V0.2.0

Provider-agnostic PHP library for LLM interactions. Works with OpenAI, Anthropic, Google, and others through a unified interface.

**Repository:** [WordPress/wp-ai-client](https://github.com/WordPress/wp-ai-client)

### 2. Abilities API

**Status:** IN WP 6.9

Central registry of WordPress capabilities. Allows AI/automation to discover and interact with everything WordPress can do.

**Use Case:** External AI assistants can query "What can this WordPress site do?" and get a structured response of all available actions.

### 3. MCP Adapter

**Status:** V0.3.0

Exposes WordPress abilities as MCP (Model Context Protocol) tools/resources for external AI assistants like Claude and ChatGPT to interact with WordPress.

**Use Case:** Claude Desktop can directly manage WordPress content, plugins, and settings.

### 4. AI Experiments Plugin

**Status:** V0.2.0

Reference plugin demonstrating all building blocks. Includes title generation, excerpt generation, and the Abilities Explorer.

**Repository:** [WordPress/ai](https://github.com/WordPress/ai)

---

## Key Differences

| Aspect | WordPress Core AI | Agentic Plugin |
|--------|------------------|----------------|
| **Focus** | Infrastructure & APIs | Agent marketplace & ecosystem |
| **Primary Output** | SDK libraries, APIs, protocols | Installable agent packages |
| **Agent Model** | Single abilities/prompts | Full agent personalities with tools |
| **AI Direction** | External AI → WordPress (MCP server) | Agents living inside WordPress |
| **User Experience** | Developer-focused APIs | Plugin-like install/activate/deactivate |
| **Discovery** | Abilities Explorer (dev tool) | Agent marketplace (end-user) |
| **Target Audience** | Developers building AI features | Site owners wanting AI assistants |

### What the WordPress AI Team Says

The WordPress AI Team explicitly acknowledges this gap:

> "We hope to make it easy for developers to create AI Agents, chatbots, workflow automations and more, using the AI Building Blocks."

**They're building the blocks; Agentic Plugin builds the marketplace for assembled agents.**

---

## Synergies & Opportunities

### 🔌 Use WP AI Client

Agentic agents can use the official WP AI Client SDK for LLM communication instead of implementing custom provider integrations.

**Benefit:** 
- Unified credential management
- Automatic provider switching
- Community-maintained SDK
- Less code to maintain

### 📋 Register Abilities

Agent tools can be registered with the Abilities API, making them discoverable to the broader WordPress AI ecosystem.

**Example:**
```php
// Agent registers its tools with Core AI
wp_register_ability( 'agentic_content_optimizer', [
    'description' => 'Optimize content for SEO and readability',
    'callback'    => [ $agent, 'optimize_content' ],
    'parameters'  => [ 'post_id' => 'integer' ],
]);
```

### 🔗 MCP Integration

Agent capabilities can be exposed via MCP Adapter, allowing external AI assistants to interact with installed agents.

**Use Case:** Claude Desktop can say "Use the SEO agent to optimize this post" and invoke installed Agentic Plugin agents.

### 🔐 Shared Credentials

Leverage the WP AI Client's credential management for API keys instead of requiring separate agent-level configuration.

**User Benefit:** Configure OpenAI key once, all agents use it.

---

## Proposed Integration Path

### Phase 1: Standalone (Current) ✅

**Status:** Complete

Agentic Plugin operates independently with its own LLM integration. Validates the agent-as-plugin model with the community.

**Rationale:** Prove the concept before deep integration.

### Phase 2: WP AI Client Integration

**Timeline:** When WordPress 7.0 ships (Q3 2026)

Adopt the WP AI Client SDK, leveraging host-provided AI and unified credential management.

**Implementation:**
```php
// Instead of custom OpenAI client
$client = new OpenAI_Client( get_option('openai_key') );

// Use Core AI client
$client = wp_ai_client();
$response = $client->generate([
    'prompt' => $prompt,
    'model'  => 'gpt-4'
]);
```

### Phase 3: Abilities Registration

**Timeline:** Q4 2026

Register agent tools with the Abilities API, making agent capabilities discoverable and usable by other WordPress AI features.

**Benefit:** 
- Agents become discoverable
- External AI can orchestrate agents
- Standardized capability interface

### Phase 4: MCP Exposure

**Timeline:** 2027

Expose agents via MCP Adapter, allowing external AI to orchestrate and interact with installed WordPress agents.

**Use Case:** External AI assistants (Claude, ChatGPT) can manage and invoke agents installed on WordPress sites.

---

## Collaboration Opportunities

### Community Engagement

**Get Involved:** Join `#core-ai` on [WordPress Slack](https://make.wordpress.org/chat/) to participate in discussions about AI in WordPress.

**Meetings:** Alternate Thursdays at 16:00 UTC

### Technical Alignment

- **API Standards** - Align agent API with Core AI patterns
- **Credential Management** - Use Core AI credential system
- **Tool Registry** - Make tools compatible with Abilities API
- **MCP Protocol** - Ensure agents can be MCP-exposed

### Governance

- Agentic Plugin remains independent community project
- Respects WordPress trademark guidelines
- Collaborates with Core AI team
- No affiliation with Automattic/WordPress Foundation

---

## Summary

Agentic Plugin and WordPress Core AI are **complementary efforts** addressing different layers of the AI stack:

| Layer | Responsibility | Project |
|-------|---------------|---------|
| **Infrastructure** | SDKs, APIs, protocols | WordPress Core AI |
| **Marketplace** | Agent discovery, installation, licensing | Agentic Plugin |
| **Agents** | Pre-built, ready-to-use AI assistants | Agentic Plugin + Community |

**Together, they can deliver a complete AI-native WordPress experience**—from the low-level APIs to the user-facing agent marketplace.

---

## Related Resources

### WordPress AI Team
- [Official WordPress AI Team](https://make.wordpress.org/ai/)
- [AI Experiments Plugin](https://github.com/WordPress/ai)
- [WP AI Client SDK](https://github.com/WordPress/wp-ai-client)
- [WordPress AI Documentation](https://make.wordpress.org/ai/handbook/)

### Agentic Plugin
- [Architecture](Architecture.md) - Technical design
- [Roadmap](Roadmap.md) - Development timeline
- [Agent Licensing for Developers](Agent-Licensing-for-Developers.md) - Build agents

---

**Last Updated:** January 28, 2026  
**Status:** Active collaboration with WordPress Core AI team
