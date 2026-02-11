# Architecture

> **Vision**: AI-native WordPress ecosystem with autonomous agents for backend and frontend operations.

This page consolidates the core architectural design of Agent Builder, including the agent controller, plugin/theme integration patterns, and security model.

---

## Table of Contents

- [Core Architecture](#core-architecture)
  - [WP_Agent_Controller](#wp_agent_controller)
  - [Agent Decision Hooks](#agent-decision-hooks)
  - [Agent Memory & Context API](#agent-memory--context-api)
  - [Tool Registry System](#tool-registry-system)
- [Backend Capabilities](#backend-capabilities)
- [Frontend Capabilities](#frontend-capabilities)
- [Plugin Architecture](#plugin-architecture)
- [Theme Architecture](#theme-architecture)
- [Security & Guardrails](#security--guardrails)
- [Data Flow](#data-flow)
- [REST API](#rest-api)

---

## Core Architecture

### WP_Agent_Controller

The central orchestration layer for all AI agents in WordPress.

```php
class WP_Agent_Controller {
    private $registered_agents = [];
    private $agent_capabilities = [];
    private $agent_memory;        // Persistent context/memory store
    private $tool_registry;       // Available actions agents can take
    private $guardrails;          // Safety constraints
    
    /**
     * Register an agent with the system
     */
    public function register_agent( string $agent_id, array $capabilities, string $scope ): bool;
    
    /**
     * Dispatch a task to appropriate agent(s)
     */
    public function dispatch_task( string $task, array $context, array $constraints ): WP_Agent_Response;
    
    /**
     * Get a decision from a specific agent
     */
    public function get_agent_decision( string $agent_id, string $prompt, array $tools ): WP_Agent_Decision;
    
    /**
     * Execute an agent action with guardrails
     */
    public function execute_action( string $action, array $params, string $agent_id ): WP_Agent_Result;
}
```

---

### Agent Decision Hooks

Extension of WordPress's action/filter system for AI decision-making.

```php
// Traditional WordPress
do_action( 'save_post', $post_id );
$content = apply_filters( 'the_content', $content );

// Agent Builder - New paradigm
do_agent_decision( 'should_publish_post', [
    'post'    => $post,
    'context' => $editorial_context,
    'tools'   => ['publish', 'schedule', 'request_review', 'improve_content']
]);

$content = apply_agent_transform( 'personalize_content', $content, [
    'user_context' => wp_agent_get_user_context(),
    'constraints'  => ['maintain_seo', 'preserve_structure']
]);
```

---

### Agent Memory & Context API

Persistent memory system for maintaining context across sessions.

```php
global $wp_agent_memory;

// Store learned information
$wp_agent_memory->store( 'user_preferences', $user_id, $learned_preferences );
$wp_agent_memory->store( 'content_patterns', $site_id, $patterns );

// Recall information
$preferences = $wp_agent_memory->recall( 'user_preferences', $user_id );
$history = $wp_agent_memory->get_conversation_history( $session_id );

// Memory management
$wp_agent_memory->forget( 'user_preferences', $user_id ); // GDPR compliance
$wp_agent_memory->export( $user_id ); // Data portability
```

---

### Tool Registry System

Standardized interface for agent-callable functions.

```php
class WP_Agent_Tool_Registry {
    
    public function register_tool( string $name, array $config ): bool {
        // $config structure:
        // [
        //     'callback'    => callable,
        //     'description' => string,      // For agent understanding
        //     'parameters'  => array,       // JSON Schema format
        //     'returns'     => string,      // Return type description
        //     'requires'    => array,       // Required capabilities
        //     'guardrails'  => array,       // Safety constraints
        // ]
    }
    
    public function get_tools_for_agent( string $agent_id ): array;
    
    public function execute_tool( string $name, array $params, string $agent_id ): mixed;
}
```

---

## Backend Capabilities

### Content Management Agent

| **Capability** | **Description** | **Requires Approval** |
|----------------|-----------------|----------------------|
| Auto-drafting | Generates draft posts based on site themes, trending topics, or content calendar | No |
| SEO Optimization | Improves meta descriptions, headings, internal linking | No |
| Content Scheduling | Learns optimal publish times from analytics | No |
| Media Management | Auto-generates alt text, compresses images, suggests featured images | No |
| Content Publishing | Publishes content to the live site | **Yes** |
| Content Deletion | Removes content from the site | **Yes** |

```php
// Registration example
wp_register_agent( 'content_manager', [
    'capabilities' => [
        'read_posts',
        'edit_posts', 
        'manage_media',
        'analyze_seo'
    ],
    'tools' => [
        'generate_draft',
        'optimize_seo',
        'schedule_post',
        'generate_alt_text',
        'suggest_featured_image'
    ],
    'guardrails' => [
        'requires_approval' => ['publish_post', 'delete_post'],
        'rate_limit' => '50_drafts_per_day'
    ]
]);
```

### Site Administration Agent

| **Capability** | **Description** | **Requires Approval** |
|----------------|-----------------|----------------------|
| Security Scanning | Check plugins/themes for vulnerabilities | No |
| Update Recommendations | Suggest updates with changelog analysis | No |
| Performance Monitoring | Track and report site performance | No |
| Error Analysis | Parse logs and suggest fixes | No |
| Database Optimization | Clean transients, optimize tables | No |
| Plugin Installation | Install new plugins | **Yes** |
| Core Updates | Update WordPress core | **Yes** |
| Setting Changes | Modify site settings | **Yes** |

### Developer Agent (WP-CLI Integration)

```bash
# Scaffold a complete feature
wp agent scaffold "Create a custom post type for recipes with 
nutritional information fields, a star rating system, and 
integration with the existing theme's card layout"

# Debug an issue
wp agent debug "Users are reporting slow page loads on the shop page"

# Generate tests
wp agent test "Write integration tests for the checkout process"

# Code review
wp agent review ./wp-content/plugins/my-plugin/
```

**Agent workflow for scaffolding:**
1. Analyze existing theme/plugin structure
2. Identify patterns and conventions in use
3. Generate code following discovered patterns
4. Create necessary database migrations
5. Generate template files matching theme style
6. Add CSS following theme conventions
7. Produce documentation

---

## Frontend Capabilities

### Conversational Interface Layer

```javascript
// WordPress frontend Agent API
wp.agent.init({
    mode: 'assistant',           // 'assistant' | 'search' | 'navigate' | 'transact'
    position: 'bottom-right',    // UI position
    theme: 'auto',               // Match site theme
    capabilities: [
        'search',
        'filter', 
        'navigate',
        'add_to_cart',
        'submit_form',
        'explain_content'
    ]
});

// Programmatic interaction
const response = await wp.agent.chat({
    message: "Find articles about gardening",
    context: wp.agent.getPageContext()
});

// Event handling
wp.agent.on('action', (action) => {
    console.log('Agent performed:', action);
});
```

### Dynamic Content Personalization

```php
// In theme templates
<?php if ( wp_agent_should_personalize() ) : ?>
    <?php echo wp_agent_personalized_content([
        'base_content'  => $post->post_content,
        'user_context'  => wp_agent_get_user_context(),
        'constraints'   => [
            'tone'            => 'professional',
            'reading_level'   => 'auto',
            'preserve_seo'    => true,
            'max_length_diff' => '20%'
        ]
    ]); ?>
<?php else : ?>
    <?php the_content(); ?>
<?php endif; ?>
```

**Personalization Modes:**
- **Reading Level** - Adjust complexity based on user signals
- **Tone Matching** - Match content tone to user preferences
- **Length Optimization** - Expand or summarize based on engagement patterns
- **Language Translation** - Real-time content translation
- **Accessibility** - Enhance for screen readers, add descriptions

### Intelligent Navigation Agent

| **User Query** | **Agent Action** |
|----------------|------------------|
| "Find articles about X from last month" | Semantic search + date filtering |
| "I need to contact support" | Navigate to form + pre-fill context |
| "Compare these two products" | Dynamic comparison table generation |
| "Summarize this article" | Inline TL;DR generation |
| "What's related to this?" | Semantic similarity search |
| "Help me fill out this form" | Form assistance + validation |

### Frontend Agent Actions

```javascript
// Agent can perform these DOM actions
wp.agent.allowedActions = {
    highlight: true,        // Highlight elements on page
    scroll_to: true,        // Scroll to specific elements
    expand: true,           // Expand collapsed sections
    fill_form: true,        // Fill form fields
    navigate: true,         // Navigate to other pages
    add_to_cart: true,      // E-commerce actions
    show_modal: true,       // Display information modals
    play_media: false,      // Disabled by default
    submit_form: false      // Requires explicit user action
};
```

---

## Plugin Architecture

### Agent Plugin Base Class

```php
abstract class WP_Agent_Plugin {
    
    /**
     * Register tools this plugin provides to agents
     */
    abstract public function register_tools(): array;
    
    /**
     * Get the system prompt for this plugin's domain
     */
    abstract public function get_system_prompt(): string;
    
    /**
     * Define guardrails for this plugin's actions
     */
    public function get_guardrails(): array {
        return [
            'requires_approval' => [],
            'rate_limits' => [],
            'scope' => 'current_user'
        ];
    }
    
    /**
     * Handle agent decisions in this plugin's domain
     */
    public function handle_decision( WP_Agent_Decision $decision ): WP_Agent_Result;
}
```

### Example: WooCommerce Agent Extension

```php
/**
 * Plugin Name: WooCommerce Agent Extension
 * Agent Capabilities: order_management, inventory_prediction, customer_service
 */

class WC_Agent_Extension extends WP_Agent_Plugin {
    
    public function register_tools(): array {
        return [
            'check_inventory' => [
                'callback'    => [$this, 'tool_check_inventory'],
                'description' => 'Check current inventory levels for a product',
                'parameters'  => [
                    'product_id' => ['type' => 'integer', 'required' => true]
                ]
            ],
            'process_refund' => [
                'callback'    => [$this, 'tool_process_refund'],
                'description' => 'Process a refund for an order',
                'parameters'  => [
                    'order_id' => ['type' => 'integer', 'required' => true],
                    'amount'   => ['type' => 'number', 'required' => false],
                    'reason'   => ['type' => 'string', 'required' => true]
                ],
                'requires_approval' => true
            ],
            'recommend_products' => [
                'callback'    => [$this, 'tool_recommend_products'],
                'description' => 'Get product recommendations based on criteria'
            ],
            'track_order' => [
                'callback'    => [$this, 'tool_track_order'],
                'description' => 'Get tracking information for an order'
            ]
        ];
    }
    
    public function get_system_prompt(): string {
        return "You are a WooCommerce store assistant. Help customers track orders, process returns, find products, and answer questions.";
    }
    
    public function get_guardrails(): array {
        return [
            'requires_approval' => ['process_refund', 'cancel_order'],
            'rate_limits' => [
                'recommend_products' => '100/hour',
                'process_refund' => '10/hour'
            ],
            'scope' => 'current_user_orders'
        ];
    }
}
```

### Agent Capability Manifest (plugin.json)

```json
{
    "name": "WooCommerce Agent Extension",
    "version": "1.0.0",
    "agent": {
        "version": "1.0",
        "capabilities": [
            "order_management",
            "product_recommendation", 
            "customer_service"
        ],
        "tools": [
            {
                "name": "check_inventory",
                "description": "Check current inventory levels",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "product_id": {
                            "type": "integer",
                            "description": "The WooCommerce product ID"
                        }
                    },
                    "required": ["product_id"]
                }
            }
        ],
        "guardrails": {
            "requires_approval": ["process_refund", "cancel_order"],
            "rate_limit": "100/hour",
            "scope": "current_user_orders"
        }
    }
}
```

---

## Theme Architecture

### Agent-Aware Theme Structure

```
theme/
├── agent/
│   ├── config.json                 # Theme agent configuration
│   ├── prompts/                    # Context-specific system prompts
│   │   ├── homepage.txt
│   │   ├── single-post.txt
│   │   ├── archive.txt
│   │   └── product-page.txt
│   ├── tools/                      # Theme-specific agent tools
│   │   ├── layout-switcher.php
│   │   └── style-adjuster.php
│   └── components/                 # Agent UI components
│       ├── chat-widget.html
│       └── suggestion-panel.html
├── templates/
│   ├── agent-interface.html        # Main agent UI template
│   └── parts/
│       └── agent-trigger.php       # Agent activation button
├── assets/
│   └── js/
│       └── agent-frontend.js       # Frontend agent scripts
├── functions.php
└── style.css
```

### Theme Agent Configuration (agent/config.json)

```json
{
    "frontend_agent": {
        "enabled": true,
        "position": "bottom-right",
        "trigger": {
            "type": "floating_button",
            "icon": "chat",
            "label": "Need help?"
        },
        "initial_message": "Hi! I can help you navigate this site...",
        "personality": "friendly, professional, concise",
        "capabilities": [
            "search",
            "navigate", 
            "explain",
            "personalize",
            "translate"
        ],
        "allowed_actions": {
            "dom_manipulation": ["highlight", "scroll_to", "expand"],
            "navigation": true,
            "form_assistance": true,
            "content_transformation": ["summarize", "translate"]
        },
        "appearance": {
            "theme": "auto",
            "colors": {
                "primary": "var(--theme-primary)",
                "background": "var(--theme-surface)"
            }
        }
    },
    "content_adaptation": {
        "enabled": true,
        "reading_level_adjustment": true,
        "language_translation": true,
        "accessibility_enhancement": true
    }
}
```

### Block Editor Agent Integration

```javascript
import { registerBlockType } from '@wordpress/blocks';
import { useAgentSuggestions } from '@wordpress/agent';

registerBlockType('theme/agent-content', {
    title: 'Agent-Enhanced Content',
    category: 'widgets',
    attributes: {
        baseContent: { type: 'string', default: '' },
        personalizationRules: { type: 'object', default: {} },
        agentInstructions: { type: 'string', default: '' },
        enablePersonalization: { type: 'boolean', default: true }
    },
    
    edit: function Edit({ attributes, setAttributes }) {
        const { suggestions, requestSuggestion } = useAgentSuggestions({
            content: attributes.baseContent,
            context: 'content_improvement'
        });
        
        return (
            <div className="wp-block-agent-content">
                <RichText
                    value={attributes.baseContent}
                    onChange={(content) => setAttributes({ baseContent: content })}
                />
                
                {suggestions.length > 0 && (
                    <AgentSuggestionPanel
                        suggestions={suggestions}
                        onAccept={(suggestion) => {
                            setAttributes({ baseContent: suggestion.content });
                        }}
                    />
                )}
            </div>
        );
    }
});
```

---

## Security & Guardrails

### Agent Roles & Capabilities

```php
function wp_agent_register_capabilities() {
    
    // Site Agent Role - for autonomous backend tasks
    add_role('site_agent', 'Site AI Agent', [
        // Content capabilities
        'agent_read_content'     => true,
        'agent_create_drafts'    => true,
        'agent_modify_content'   => true,
        'agent_publish_content'  => false,  // Requires approval
        'agent_delete_content'   => false,  // Requires approval
        
        // Media capabilities  
        'agent_read_media'       => true,
        'agent_upload_media'     => true,
        'agent_modify_media'     => true,
        'agent_delete_media'     => false,
        
        // Site capabilities
        'agent_read_settings'    => true,
        'agent_modify_settings'  => false,
        'agent_install_plugins'  => false,
        'agent_execute_code'     => false,
    ]);
    
    // Frontend Agent Role - for user-facing interactions
    add_role('frontend_agent', 'Frontend AI Agent', [
        'agent_read_content'     => true,
        'agent_search_content'   => true,
        'agent_personalize'      => true,
        'agent_translate'        => true,
        'agent_assist_forms'     => true,
        'agent_read_user_data'   => false,  // Only with consent
    ]);
}
```

### Guardrails Configuration

```php
// In wp-config.php or via admin settings
define('WP_AGENT_MODE', 'supervised'); // 'autonomous' | 'supervised' | 'disabled'

define('WP_AGENT_APPROVAL_REQUIRED', [
    'publish_post'      => true,
    'delete_post'       => true,
    'send_email'        => true,
    'create_user'       => true,
    'install_plugin'    => true,
    'update_core'       => true,
    'process_refund'    => true,
]);

define('WP_AGENT_RATE_LIMITS', [
    'api_calls_per_minute'   => 60,
    'drafts_per_hour'        => 50,
    'media_uploads_per_hour' => 100,
]);

define('WP_AGENT_COST_LIMITS', [
    'daily_api_budget'    => 10.00,  // USD
    'per_request_max'     => 0.50,
    'alert_threshold'     => 0.80,   // Alert at 80%
]);
```

### Comprehensive Audit Trail

```php
class WP_Agent_Audit_Log {
    
    public function log( array $entry ): int {
        return wp_insert_post([
            'post_type'   => 'agent_audit_log',
            'post_status' => 'publish',
            'meta_input'  => [
                '_agent_id'        => $entry['agent_id'],
                '_action'          => $entry['action'],
                '_target_type'     => $entry['target_type'],
                '_target_id'       => $entry['target_id'],
                '_changes'         => json_encode($entry['changes']),
                '_reasoning'       => $entry['reasoning'],
                '_tokens_used'     => $entry['tokens_used'],
                '_cost'            => $entry['cost'],
                '_rollback_data'   => json_encode($entry['rollback_data']),
            ]
        ]);
    }
    
    public function rollback( int $log_id ): WP_Agent_Result;
    public function get_agent_history( string $agent_id ): array;
    public function get_pending_approvals(): array;
}
```

### Human-in-the-Loop Workflow

```php
class WP_Agent_Approval_Queue {
    
    public function queue( string $action, array $params, string $agent_id ): int;
    public function approve( int $queue_id, int $user_id, string $notes = '' ): WP_Agent_Result;
    public function reject( int $queue_id, int $user_id, string $reason ): bool;
    public function get_pending_for_user( int $user_id ): array;
}
```

### PII & Data Protection

```php
// Automatic PII detection and handling
add_filter('wp_agent_before_process', function($data, $context) {
    $pii_handler = new WP_Agent_PII_Handler();
    
    return $pii_handler->process($data, [
        'mask_emails'       => true,
        'mask_phones'       => true,
        'mask_addresses'    => true,
        'mask_credit_cards' => true,
        'allowed_fields'    => ['display_name', 'public_email'],
    ]);
}, 10, 2);
```

---

## Data Flow

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              FRONTEND                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────────────┐ │
│  │ Chat Widget  │  │ Smart Search │  │ Personalized Content Blocks  │ │
│  └──────┬───────┘  └──────┬───────┘  └───────────────┬───────────────┘ │
└─────────┼─────────────────┼──────────────────────────┼──────────────────┘
          │                 │                          │
          ▼                 ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         REST API Layer                                   │
│         /wp-json/agent/v1/chat  |  /task  |  /personalize               │
└─────────────────────────────────────────────────────────────────────────┘
          │                 │                          │
          ▼                 ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      WP_Agent_Controller                                 │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌────────────┐ │   │
│  │  │Tool Registry│ │Memory Store │ │ Guardrails  │ │ Audit Log  │ │   │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └────────────┘ │   │
│  │                                                                   │   │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────────────┐ │   │
│  │  │ Cost Meter  │ │Rate Limiter │ │    Approval Queue           │ │   │
│  │  └─────────────┘ └─────────────┘ └─────────────────────────────┘ │   │
│  │                                                                   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
          │                 │                          │
          ▼                 ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         BACKEND AGENTS                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐ │
│  │Content Agent │  │ Admin Agent  │  │Commerce Agent│  │ Dev Agent   │ │
│  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
          │                 │                          │
          ▼                 ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    LLM Provider Abstraction Layer                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐ │
│  │   OpenAI     │  │  Anthropic   │  │    Local     │  │   Custom    │ │
│  └──────────────┘  └──────────────┘  └──────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
          │                 │                          │
          ▼                 ▼                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│              WordPress Core + Database + Object Cache                    │
└─────────────────────────────────────────────────────────────────────────┘
```

### Key Components

**Frontend Layer:**
- Chat Widget – Conversational interface for user interactions
- Smart Search – AI-powered semantic search
- Personalized Content Blocks – Dynamic content adaptation

**Controller Layer:**
- Tool Registry – Available actions agents can take
- Memory Store – Persistent context across sessions
- Guardrails – Safety constraints and limits
- Audit Log – Complete action history
- Cost Meter – API usage tracking
- Rate Limiter – Request throttling
- Approval Queue – Human-in-the-loop workflow

**Agent Layer:**
- Content Agent – Draft creation, SEO, media management
- Admin Agent – Security, performance, maintenance
- Commerce Agent – Orders, inventory, customer service
- Dev Agent – Code scaffolding, debugging, testing

**LLM Provider Layer:**
- OpenAI (GPT-4, GPT-4o)
- Anthropic (Claude)
- Local models (Ollama, LM Studio)
- Custom providers

---

## REST API

### Endpoints

| **Method** | **Endpoint** | **Description** |
|------------|--------------|-----------------|
| POST | `/wp-json/agent/v1/chat` | Send a message to the frontend agent |
| POST | `/wp-json/agent/v1/task` | Submit a task for backend processing |
| GET | `/wp-json/agent/v1/capabilities` | List available agent capabilities |
| GET | `/wp-json/agent/v1/status` | Get agent system status |
| GET | `/wp-json/agent/v1/audit-log` | Retrieve audit log entries |
| POST | `/wp-json/agent/v1/approve/{id}` | Approve a pending action |
| POST | `/wp-json/agent/v1/reject/{id}` | Reject a pending action |
| POST | `/wp-json/agent/v1/rollback/{id}` | Rollback a previous action |
| GET | `/wp-json/agent/v1/pending` | Get pending approval queue |
| POST | `/wp-json/agent/v1/personalize` | Request content personalization |
| DELETE | `/wp-json/agent/v1/memory/{user_id}` | Delete agent memory (GDPR) |

### Chat Endpoint Schema

**Request:**
```json
{
    "message": "Find articles about gardening from last month",
    "session_id": "abc123",
    "context": {
        "current_page": "/blog/",
        "user_authenticated": true,
        "previous_interactions": 3
    },
    "capabilities": ["search", "navigate", "explain"]
}
```

**Response:**
```json
{
    "response": "I found 5 articles about gardening from December.",
    "actions": [
        {
            "type": "display_results",
            "data": {
                "posts": [
                    {"id": 123, "title": "Winter Garden Prep", "url": "/winter-garden-prep/"},
                    {"id": 124, "title": "Indoor Herb Growing", "url": "/indoor-herbs/"}
                ]
            }
        }
    ],
    "suggestions": [
        "Would you like me to filter by a specific topic?",
        "I can also show you related videos."
    ],
    "session_id": "abc123",
    "tokens_used": 450,
    "agent_id": "frontend_search"
}
```

### Task Endpoint Schema

**Request:**
```json
{
    "task": "optimize_seo",
    "target": {
        "type": "post",
        "id": 456
    },
    "parameters": {
        "focus_keyword": "winter gardening tips",
        "optimize_images": true,
        "suggest_internal_links": true
    },
    "approval_required": false
}
```

**Response:**
```json
{
    "task_id": "task_789",
    "status": "completed",
    "changes": [
        {
            "field": "meta_description",
            "old_value": "",
            "new_value": "Discover essential winter gardening tips...",
            "reasoning": "Added keyword-rich meta description"
        }
    ],
    "audit_log_id": 1001,
    "rollback_available": true
}
```

---

## Related Documentation

- [Roadmap](Roadmap.md) - Project timeline and milestones
- [Agent Licensing for Developers](Agent-Licensing-for-Developers.md) - Monetization guide
- [Use Cases](Use-Cases.md) - Real-world scenarios
- [Discussion Points](Discussion-Points.md) - Open questions and community input

---

## Implemented Agent Architecture (v1.5.0)

The following sections document the currently shipped agent infrastructure.

### Agent Base Class

All agents extend `\Agentic\Agent_Base` and may override these scheduling/event methods:

```php
abstract class Agent_Base {
    // Required
    abstract public function get_id(): string;
    abstract public function get_name(): string;
    abstract public function get_description(): string;
    abstract public function get_system_prompt(): string;
    abstract public function get_tools(): array;

    // Optional — scheduling & events
    public function get_scheduled_tasks(): array { return []; }
    public function get_event_listeners(): array { return []; }
}
```

### Three Agent Invocation Methods

| Method | Trigger | Mode | Added |
|--------|---------|------|-------|
| **Chat** | User sends a message | Interactive, real-time | v1.0.0 |
| **Cron** | WP-Cron schedule fires | Autonomous or direct | v1.4.0 |
| **Hooks** | WordPress action hook fires | Direct or async AI | v1.5.0 |

### Scheduled Tasks (v1.4.0)

Agents define recurring tasks that run on WP-Cron schedules.

```php
public function get_scheduled_tasks(): array {
    return [
        [
            'id'          => 'daily_scan',
            'name'        => 'Daily Security Scan',
            'callback'    => 'run_daily_scan',
            'schedule'    => 'daily',
            'description' => 'Run a comprehensive security scan.',
            'prompt'      => 'Analyze the site for security issues...', // Optional: routes through LLM
        ],
    ];
}
```

**Execution flow:**
1. `agentic_agents_loaded` → `bind_agent_cron_hooks()` registers WP action hooks
2. `agentic_agent_activated` → `register_scheduled_tasks()` creates `wp_schedule_event`
3. Cron fires → `execute_scheduled_task()` logs start, runs task, logs complete/error with duration
4. If `prompt` is set → `Agent_Controller::run_autonomous_task()` routes through LLM with full tool access
5. If LLM not configured → falls back to calling the `callback` method directly

### Event Listeners (v1.5.0)

Agents react to WordPress action hooks as they fire.

```php
public function get_event_listeners(): array {
    return [
        [
            'id'            => 'failed_login',
            'hook'          => 'wp_login_failed',
            'name'          => 'Failed Login Monitor',
            'callback'      => 'on_failed_login',
            'description'   => 'Logs failed login attempts.',
            'accepted_args' => 2,
            // Optional: 'prompt' => '...' for async LLM processing
        ],
    ];
}
```

**Execution flow:**
1. `agentic_agents_loaded` → `bind_agent_event_listeners()` registers WP action hooks
2. Hook fires → `execute_event_listener()` logs trigger with sanitized args
3. **Direct mode** (no `prompt`): calls agent callback synchronously
4. **AI Async mode** (`prompt` set): queues via `wp_schedule_single_event()` → `handle_async_event()` runs LLM with event context
5. All executions logged: `event_listener_triggered`, `event_listener_complete`, `event_listener_error`

**Smart serialization** of hook arguments:
- `WP_Post` → `{ID, post_title, post_type, post_status, post_author}`
- `WP_Comment` → `{comment_ID, comment_post_ID, comment_author, comment_content (truncated)}`
- `WP_User` → `{ID, user_login, display_name, roles}`
- Other objects → `{class_name only}`
- Long strings → truncated at 1000 chars

### Core Tools

Available to all agents via `Agent_Tools`:

| Tool | Description |
|------|-------------|
| `read_file` | Read file contents from the codebase |
| `list_directory` | List files in a directory |
| `search_code` | Search code by pattern |
| `get_posts` | Query WordPress posts |
| `get_comments` | Query WordPress comments |
| `create_comment` | Create a new comment |
| `update_documentation` | Update documentation files |
| `request_code_change` | Request a code modification  |
| `manage_schedules` | List, pause, or resume scheduled tasks |

### Admin Pages

| Page | Description |
|------|-------------|
| Dashboard | Stats overview: total agents, active count, recent activity |
| Installed Agents | Agent list with activate/deactivate controls |
| Add Agents | Browse library with upload (ZIP) support |
| Agent Chat | Interactive chat interface |
| Scheduled Tasks | All agent cron tasks with Run Now, mode, status, next run |
| Event Listeners | All agent hook bindings with hook name, priority, mode, status |
| Agent Tools | All tools across all agents with type, parameters, used-by |
| Audit Log | Full action history with timestamps, expandable details, dynamic filters |
| Security Log | Security events (prompt injection, PII, rate limiting) |
| Settings | API keys, model selection, feature toggles |
| System Status | PHP/WP/MySQL version checks, required extensions |

---

**Last Updated**: February 11, 2026  
**Status**: Vision document - Phased implementation in progress
