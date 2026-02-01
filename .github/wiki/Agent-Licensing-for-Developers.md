# Selling Agents on the Marketplace

**Build agents, submit to the marketplace, and earn revenue - licensing is handled for you**

---

## Overview

Agent Builder makes monetization simple. **You build the agent, we handle everything else** - licensing, payments, updates, and customer support for license issues. Your only concern after submission is tracking your revenue.

---

## How It Works

**Developer Workflow:**
1. Build your agent extending `Agentic_Agent_Base`
2. Test locally with Agent Builder plugin
3. Submit to marketplace with pricing info
4. We review and approve (usually 24-48 hours)
5. Users purchase and install - **we handle licensing**
6. You track revenue in Agent Builder → Revenue
7. We pay you monthly via Stripe

**What You Do:**
- Write the agent code
- Set your price and billing model
- Provide support for agent functionality
- Track your earnings

**What We Handle:**
- License key generation
- License validation on install/update
- Payment processing (Stripe)
- License enforcement and grace periods
- Customer license support
- Refund processing
- Payout distribution

---

## Revenue Models

### Free Agents
- No payment required
- Great for building reputation
- Increases visibility for your premium agents

### Premium One-Time Purchase
- User pays once (e.g., $29)
- Lifetime access
- Best for: Productivity tools, utilities

### Premium Subscription
- Monthly or yearly billing (e.g., $9/month, $49/year)
- Recurring revenue
- Best for: API-dependent agents, SaaS integrations

---

## Agent Submission

### Required Headers

Your `agent.php` file needs these headers:

```php
<?php
/**
 * Agent Name: Invoice Generator Pro
 * Description: Automated PDF invoices for WooCommerce
 * Version: 1.2.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Category: E-commerce
 * Icon: icon.png
 * Is Premium: true
 * Price: 29.00
 * Billing Period: year
 * Requires Agent Builder: 1.0.0
 */
```

### Header Reference

| Header | Required | Description | Example |
|--------|----------|-------------|---------|
| `Agent Name` | Yes | Display name | `Invoice Generator Pro` |
| `Description` | Yes | Short description | `Automated PDF invoices` |
| `Version` | Yes | Semantic version | `1.2.0` |
| `Author` | Yes | Your name/company | `Your Name` |
| `Category` | Yes | Agent category | `E-commerce` |
| `Is Premium` | For paid | Mark as paid agent | `true` |
| `Price` | For paid | Cost in USD | `29.00` |
| `Billing Period` | For paid | `month`, `year`, or `lifetime` | `year` |

**Note:** You no longer need `Requires License`, `License Type`, or `Activation Limit` headers. The marketplace handles all licensing configuration.

---

## Pricing Guidelines

| Agent Type | Suggested Price | Billing |
|------------|-----------------|---------|
| Simple utility | $19 | Lifetime |
| Content generator | $29/year | Annual |
| E-commerce tool | $49/year | Annual |
| API integration | $9/month | Monthly |
| Enterprise suite | $99/year | Annual |

**Tips:**
- Start lower to build reviews, raise prices later
- Annual billing has better retention than monthly
- Lifetime licenses = one-time revenue, subscriptions = recurring

---

## Revenue Dashboard

Track everything at **Agent Builder → Revenue** in your WordPress admin.

**Stats Cards:**
- Agents Submitted (approved/pending)
- Total Installs (all-time and active)
- Revenue This Month
- Pending Payout

**Charts:**
- Revenue over time (30d/90d/12m views)
- Installs over time

**Tables:**
- Your agents with status, installs, revenue, ratings
- Payout history

### Connecting Your Account

1. Register at [agentic-plugin.com/developer/register](https://agentic-plugin.com/developer/register)
2. Get your API key from the developer dashboard
3. Enter it in Agent Builder → Revenue → Connect Account

---

## Revenue Share and Payouts

### Revenue Split

| Volume | You Keep | Marketplace |
|--------|----------|-------------|
| Standard | **70%** | 30% |
| High Volume (>$10k/mo) | **80%** | 20% |

**Example:** $29 sale → You receive $20.30

### Payout Schedule

- **Minimum:** $50 threshold before payout
- **Frequency:** Monthly on the 15th
- **Method:** Stripe direct deposit
- **Processing:** 2-3 business days

---

## Submission Checklist

Before submitting your agent:

- [ ] Agent extends `Agentic_Agent_Base`
- [ ] All required headers present
- [ ] Version follows semantic versioning
- [ ] Icon included (256x256 PNG recommended)
- [ ] README.md with usage instructions
- [ ] Screenshots (3-5 recommended)
- [ ] Tested on latest WordPress + Agent Builder
- [ ] No PHP errors or warnings
- [ ] Support email provided

---

## Example: Complete Premium Agent

```php
<?php
/**
 * Agent Name: Invoice Generator Pro
 * Description: Automated PDF invoices for WooCommerce
 * Version: 1.2.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Category: E-commerce
 * Icon: icon.png
 * Is Premium: true
 * Price: 29.00
 * Billing Period: year
 * Requires Agent Builder: 1.0.0
 */

class Invoice_Generator_Agent extends Agentic_Agent_Base {
    
    public function __construct() {
        parent::__construct(
            'invoice-generator',
            'Invoice Generator Pro',
            'Generate professional PDF invoices automatically'
        );
        
        add_filter( 'woocommerce_order_actions', array( $this, 'add_invoice_action' ) );
    }
    
    public function add_invoice_action( $actions ) {
        $actions['generate_invoice'] = __( 'Generate Invoice PDF', 'invoice-generator' );
        return $actions;
    }
    
    public function generate_invoice( $order_id ) {
        // Your agent logic here
        // NO license checking needed - marketplace handles it
        $pdf = $this->create_pdf( $order_id );
        return $pdf;
    }
}
```

**Notice:** No license validation code! The marketplace validates licenses during installation and updates. Your agent code is clean and focused on functionality.

---

## Updates

When you release an update:

1. Increment your version number
2. Submit the new version to marketplace
3. We review and approve
4. Users with valid licenses receive the update automatically
5. Expired licenses are blocked from updating (we handle this)

---

## Support

**For Developers:**
- Discord: [discord.gg/agentic](https://discord.gg/agentic)
- Email: developers@agentic-plugin.com
- Docs: [GitHub Wiki](https://github.com/renduples/agent-builder/wiki)

**For Your Customers:**
- License issues → Direct them to agentic-plugin.com/support
- Agent functionality → You provide support
- Refunds → Handled by marketplace (30-day policy)

---

## Quick Tips

1. **Focus on your agent** - We handle the business complexity
2. **Price confidently** - Quality agents command premium prices
3. **Gather reviews** - They drive sales more than features
4. **Update regularly** - Monthly improvements keep users happy
5. **Respond quickly** - Fast support builds loyalty

---

**Ready to earn?** Build your agent and [submit to the marketplace](https://agentic-plugin.com/developer/submit)!

Questions? Ask in [Discord](https://discord.gg/agentic) or email developers@agentic-plugin.com
