# Security Policy for Agentic Plugin

**Agentic Plugin is built with security as a core principle.** We take security seriously and appreciate the community's help in keeping it safe.

---

## Security Overview

### Core Design Principles

1. **Sandboxed Execution** – Agents run in isolated contexts with limited permissions
2. **Capability-Based Access** – Fine-grained permission controls (what can each agent do?)
3. **Approval Workflows** – Sensitive actions require human review before execution
4. **Audit Logging** – Every agent action is logged for compliance and investigation
5. **Defense in Depth** – Multiple layers of security, not just one
6. **Privacy First** – User data stays on their server by default

### What We Protect

- ✅ **Your WordPress database** – Agents can only access authorized data
- ✅ **API keys & credentials** – Never leaked or logged in plain text
- ✅ **User data** – Governed by WordPress capabilities and roles
- ✅ **Site integrity** – Agents can't modify files without approval
- ✅ **Performance** – Rate limiting prevents abuse

---

## Reporting Security Issues

**DO NOT open public GitHub issues for security vulnerabilities.**

If you discover a security vulnerability, please email:

📧 **security@agentic-plugin.com**

### What to Include

1. **Detailed description** of the vulnerability
2. **Steps to reproduce** the issue
3. **Potential impact** (low/medium/high/critical)
4. **Proof of concept** (if possible)
5. **Your name/handle** (for credit, optional)

### What to Expect

- 🔄 **Acknowledgment** within 24 hours
- 🔍 **Investigation** within 48 hours
- 🚀 **Fix** within 7-14 days depending on severity
- 📢 **Public disclosure** 30 days after fix (or later by agreement)
- 🙏 **Credit** in security advisory (if desired)

---

## Security Features

### 1. Agent Sandboxing

Agents cannot:
- ❌ Access the filesystem directly
- ❌ Execute arbitrary shell commands
- ❌ Access API keys or credentials
- ❌ Modify WordPress database
- ❌ Call admin functions without permission

Agents can:
- ✅ Call approved tools
- ✅ Read published content
- ✅ Use OpenAI/Anthropic APIs
- ✅ Interact with REST API endpoints
- ✅ Log actions for audit trail

### 2. Permission System

Similar to WordPress capabilities, but for agents:

```php
// Agent is granted specific permissions
'read_posts' => true,
'publish_posts' => false,  // Requires approval
'delete_posts' => false,   // Requires approval + admin confirmation
'call_external_api' => true,
'access_user_data' => false,
```

### 3. Approval Queue

Sensitive operations require approval:

- **Publishing content** – Requires editor/admin review
- **Deleting items** – Requires admin confirmation
- **API calls** – Logged and can be reviewed
- **Data modifications** – Visible in audit log before execution

Approvals happen in real-time and can be:
- ✅ **Approved** – Execute immediately
- ❌ **Rejected** – Agent receives feedback
- ⏳ **Deferred** – Review later

### 4. Audit Logging

Everything is logged:

```
{
  "timestamp": "2026-01-25 10:30:45",
  "agent_id": "seo-analyzer",
  "action": "analyze_post",
  "target_id": 123,
  "reasoning": "User requested SEO check for homepage",
  "status": "success",
  "tokens_used": 1250,
  "cost": 0.04,
  "user_id": 1
}
```

Logs are:
- ✅ **Immutable** – Once written, cannot be changed
- ✅ **Searchable** – Query by agent, action, date, user
- ✅ **Exportable** – Download for compliance/investigation
- ✅ **Encrypted** – At rest (if configured)

### 5. Rate Limiting

Prevents abuse:

- **Per-agent limits** – Max 100 requests/hour per agent
- **Per-user limits** – Max 1000 requests/hour per user
- **Per-API limits** – Respects OpenAI/Anthropic rate limits
- **Cost controls** – Set monthly budget limits

### 6. API Key Management

Credentials are:
- ✅ **Encrypted** – Using WordPress secret key
- ✅ **Not logged** – Never appears in audit logs
- ✅ **Masked** – Shows only last 4 chars in UI
- ✅ **Rotatable** – Can be updated without stopping agents
- ✅ **Scoped** – Separate keys per LLM provider

---

## Best Practices for Instance Security

### For Site Admins

1. **Keep WordPress updated** – Critical for overall security
2. **Update Agentic Plugin** – Receive security patches immediately
3. **Limit agent access** – Only enable agents you need
4. **Review audit logs** – Check weekly for suspicious activity
5. **Use strong passwords** – For admin accounts that approve agents
6. **Monitor costs** – Set budget alerts for API spending
7. **Backup regularly** – In case of compromise

### For Agent Developers

1. **Don't hardcode secrets** – Use WordPress options or .env
2. **Validate all inputs** – Even from WordPress functions
3. **Sanitize outputs** – Before returning to users
4. **Follow WordPress security** – Use nonces, capabilities, escape
5. **Test with malicious data** – Try to break your agent
6. **Minimize permissions** – Ask for minimum required
7. **Document security** – Explain what your agent accesses

### For Marketplace Publishers

1. **Code review** – We audit every submission
2. **Security scanning** – Automated checks for vulnerabilities
3. **Sandboxing** – Your agents run in isolated environments
4. **Secrets management** – We handle API key encryption
5. **Rate limiting** – We prevent abuse automatically
6. **Monitoring** – We detect unusual patterns
7. **Takedown** – We remove malicious agents within hours

---

## Vulnerability Disclosure

### Timeline

1. **Day 0**: Researcher reports vulnerability
2. **Day 1**: We acknowledge and begin investigation
3. **Day 3**: We confirm and create fix
4. **Day 7-14**: Fix is tested and released
5. **Day 30**: Public disclosure (unless more time needed)

### Severity Ratings

| Severity | CVSS | Response Time | Example |
|----------|------|----------------|---------|
| **Critical** | 9.0-10.0 | 24 hours | RCE, authentication bypass |
| **High** | 7.0-8.9 | 48 hours | Data exposure, privilege escalation |
| **Medium** | 4.0-6.9 | 7 days | Information disclosure, XSS |
| **Low** | 0.1-3.9 | 30 days | Minor issues, DoS edge cases |

---

## Known Limitations

### Current Risk Areas

1. **Beta Status** – Code not yet production-hardened
2. **New Platform** – Less battle-tested than mature plugins
3. **Agent Ecosystem** – Third-party agents may have vulnerabilities
4. **LLM Integration** – We depend on OpenAI/Anthropic security
5. **Approval Workflow** – Only effective if admins review carefully

### Mitigations

- ✅ Regular security audits
- ✅ Community vulnerability reports
- ✅ Sandboxing limits blast radius
- ✅ Audit logging allows investigation
- ✅ Rate limiting prevents cascading failures

---

## Compliance

We're working toward:

- ⏳ **GDPR** – Privacy-by-design, data export/deletion
- ⏳ **CCPA** – User data controls and transparency
- ⏳ **ISO 27001** – Information security management
- ⏳ **SOC 2** – Security controls assessment
- ⏳ **WordPress VIP** – Enterprise security standards

Current status: **Pre-compliance** (features in place, certification pending)

---

## Security Headers & Best Practices

### Recommended WordPress Configuration

```php
// wp-config.php

// Enable security logging
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG', false );  // Don't expose errors publicly

// Use strong authentication
define( 'AUTH_KEY', 'unique-random-string-here' );
define( 'SECURE_AUTH_KEY', 'unique-random-string-here' );
define( 'LOGGED_IN_KEY', 'unique-random-string-here' );
define( 'NONCE_KEY', 'unique-random-string-here' );

// Enable HTTPS
define( 'FORCE_SSL_ADMIN', true );
define( 'FORCE_SSL_LOGIN', true );

// Hide WordPress version
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
```

### Server-Level Security

```apache
# .htaccess

# Prevent direct access to sensitive files
<Files "wp-config.php">
    Order allow,deny
    Deny from all
</Files>

# Disable PHP execution in uploads
<Directory wp-content/uploads>
    php_flag engine off
</Directory>

# Enable security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

---

## Responsible Disclosure Examples

### ✅ Good Report

> **Subject**: XSS Vulnerability in Agent Registration Form
>
> I found a stored XSS vulnerability in the agent name field at /admin/agents-add.php.
>
> **Steps to reproduce:**
> 1. Log in as admin
> 2. Go to Agentic → Add Agent
> 3. Enter `<img src=x onerror="alert('xss')">` in Agent Name
> 4. Save agent
> 5. Visit Agentic → Agents
> 6. JavaScript executes
>
> **Impact:** Medium – Allows attackers to inject code that runs for admins
>
> **Fix suggestion:** Sanitize agent name with `wp_kses()` or `strip_tags()`

### ❌ Bad Report

> Found a bug lol
>
> Go to [random URL] and something bad happens. I don't want to say more publicly.

---

## Bug Bounty Program

We don't currently have a paid bug bounty program, but we recognize security researchers with:

- 🏅 **Credit** – Your name/handle in security advisory
- 🎁 **Swag** – Agentic Plugin merchandise
- 📝 **Case study** – Feature your findings (if you're interested)
- 🎯 **Priority** – Your agents get priority support

If you find critical vulnerabilities regularly, let's talk about sponsorship.

---

## Security Checklist for Updates

When we release updates, we verify:

- ✅ No security vulnerabilities introduced
- ✅ Dependencies updated to latest secure versions
- ✅ Audit logs still immutable
- ✅ Sandboxing still effective
- ✅ No new information disclosure pathways
- ✅ Backward compatibility maintained (no surprise breaks)

---

## Questions & Support

- **Security questions**: security@agentic-plugin.com
- **General help**: support@agentic-plugin.com
- **Emergency**: contact us directly in Discord
- **GitHub Issues**: Use for non-security bugs

---

## References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Security Handbook](https://developer.wordpress.org/plugins/security/)
- [CWE Top 25](https://cwe.mitre.org/top25/)
- [Responsible Disclosure Guidelines](https://securitytxt.org/)

---

**Last updated**: January 2026  
**Next review**: April 2026

For the latest security information, check [security.md on GitHub](https://github.com/renduples/agentic-plugin/blob/main/SECURITY.md).
