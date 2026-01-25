<?php
/**
 * Agentic Settings Page
 *
 * @package Agentic_WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle form submission

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'agentic-core' ) );
}

if ( isset( $_POST['agentic_save_settings'] ) && check_admin_referer( 'agentic_settings_nonce' ) ) {
    // Core settings
    update_option( 'agentic_xai_api_key', sanitize_text_field( $_POST['agentic_xai_api_key'] ?? '' ) );
    update_option( 'agentic_model', sanitize_text_field( $_POST['agentic_model'] ?? 'grok-3' ) );
    update_option( 'agentic_repo_path', sanitize_text_field( $_POST['agentic_repo_path'] ?? ABSPATH ) );
    update_option( 'agentic_agent_mode', sanitize_text_field( $_POST['agentic_agent_mode'] ?? 'supervised' ) );
    
    // Cache settings
    update_option( 'agentic_response_cache_enabled', isset( $_POST['agentic_response_cache_enabled'] ) );
    update_option( 'agentic_response_cache_ttl', absint( $_POST['agentic_response_cache_ttl'] ?? 3600 ) );
    
    // Security settings
    update_option( 'agentic_security_enabled', isset( $_POST['agentic_security_enabled'] ) );
    update_option( 'agentic_rate_limit_authenticated', absint( $_POST['agentic_rate_limit_authenticated'] ?? 30 ) );
    update_option( 'agentic_rate_limit_anonymous', absint( $_POST['agentic_rate_limit_anonymous'] ?? 10 ) );
    update_option( 'agentic_allow_anonymous_chat', isset( $_POST['agentic_allow_anonymous_chat'] ) );
    
    // Stripe settings (only on marketplace site)
    if ( defined( 'AGENTIC_IS_MARKETPLACE' ) && AGENTIC_IS_MARKETPLACE ) {
        update_option( 'agentic_stripe_test_mode', isset( $_POST['agentic_stripe_test_mode'] ) );
        update_option( 'agentic_stripe_test_publishable_key', sanitize_text_field( $_POST['agentic_stripe_test_publishable_key'] ?? '' ) );
        update_option( 'agentic_stripe_test_secret_key', sanitize_text_field( $_POST['agentic_stripe_test_secret_key'] ?? '' ) );
        update_option( 'agentic_stripe_live_publishable_key', sanitize_text_field( $_POST['agentic_stripe_live_publishable_key'] ?? '' ) );
        update_option( 'agentic_stripe_live_secret_key', sanitize_text_field( $_POST['agentic_stripe_live_secret_key'] ?? '' ) );
        update_option( 'agentic_stripe_webhook_secret', sanitize_text_field( $_POST['agentic_stripe_webhook_secret'] ?? '' ) );
    }
    
    // Handle cache clear
    if ( isset( $_POST['agentic_clear_cache'] ) ) {
        $cleared = \Agentic\Response_Cache::clear_all();
        echo '<div class="notice notice-info"><p>Cleared ' . esc_html( $cleared ) . ' cached responses.</p></div>';
    }
    
    // Social Auth settings
    if ( isset( $_POST['agentic_social_auth'] ) && is_array( $_POST['agentic_social_auth'] ) ) {
        $social_auth = [];
        foreach ( [ 'google', 'github', 'wordpress', 'twitter' ] as $provider ) {
            $social_auth[ $provider ] = [
                'client_id'     => sanitize_text_field( $_POST['agentic_social_auth'][ $provider ]['client_id'] ?? '' ),
                'client_secret' => sanitize_text_field( $_POST['agentic_social_auth'][ $provider ]['client_secret'] ?? '' ),
                'enabled'       => ! empty( $_POST['agentic_social_auth'][ $provider ]['enabled'] ),
            ];
        }
        update_option( 'agentic_social_auth', $social_auth );
        
        // Flush rewrite rules when social auth settings change
        flush_rewrite_rules();
    }
    
    echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
}

// Get current values
$api_key = get_option( 'agentic_xai_api_key', '' );
$model = get_option( 'agentic_model', 'grok-3' );
$repo_path = get_option( 'agentic_repo_path', ABSPATH );
$agent_mode = get_option( 'agentic_agent_mode', 'supervised' );

// Cache settings
$cache_enabled = get_option( 'agentic_response_cache_enabled', true );
$cache_ttl = get_option( 'agentic_response_cache_ttl', 3600 );
$cache_stats = \Agentic\Response_Cache::get_stats();

// Security settings
$security_enabled = get_option( 'agentic_security_enabled', true );
$rate_limit_auth = get_option( 'agentic_rate_limit_authenticated', 30 );
$rate_limit_anon = get_option( 'agentic_rate_limit_anonymous', 10 );
$allow_anon_chat = get_option( 'agentic_allow_anonymous_chat', false );
?>
<div class="wrap">
    <h1>Agentic Settings</h1>

    <form method="post" action="">
        <?php wp_nonce_field( 'agentic_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="agentic_xai_api_key">xAI API Key</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        name="agentic_xai_api_key" 
                        id="agentic_xai_api_key" 
                        value="<?php echo esc_attr( $api_key ); ?>" 
                        class="regular-text"
                    />
                    <p class="description">
                        Your xAI API key for Grok. Get one from <a href="https://console.x.ai/" target="_blank">console.x.ai</a>
                    </p>
                    <?php if ( ! empty( $api_key ) ) : ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> API key is set</p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_model">Grok Model</label>
                </th>
                <td>
                    <select name="agentic_model" id="agentic_model">
                        <option value="grok-3" <?php selected( $model, 'grok-3' ); ?>>Grok 3 (Recommended)</option>
                        <option value="grok-3-fast" <?php selected( $model, 'grok-3-fast' ); ?>>Grok 3 Fast (Lower latency)</option>
                        <option value="grok-3-mini" <?php selected( $model, 'grok-3-mini' ); ?>>Grok 3 Mini (Efficient)</option>
                        <option value="grok-3-mini-fast" <?php selected( $model, 'grok-3-mini-fast' ); ?>>Grok 3 Mini Fast (Fastest)</option>
                    </select>
                    <p class="description">
                        The Grok model to use for agent responses. Grok 3 provides the best results.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_repo_path">Repository Path</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        name="agentic_repo_path" 
                        id="agentic_repo_path" 
                        value="<?php echo esc_attr( $repo_path ); ?>" 
                        class="large-text"
                    />
                    <p class="description">
                        Absolute path to the git repository the agent can access.
                        Default: <code><?php echo esc_html( ABSPATH ); ?></code>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_agent_mode">Agent Mode</label>
                </th>
                <td>
                    <select name="agentic_agent_mode" id="agentic_agent_mode">
                        <option value="disabled" <?php selected( $agent_mode, 'disabled' ); ?>>Disabled</option>
                        <option value="supervised" <?php selected( $agent_mode, 'supervised' ); ?>>Supervised (Recommended)</option>
                        <option value="autonomous" <?php selected( $agent_mode, 'autonomous' ); ?>>Autonomous</option>
                    </select>
                    <p class="description">
                        <strong>Disabled:</strong> Agent chat only, no file/database actions<br>
                        <strong>Supervised:</strong> Documentation updates are autonomous, code changes require approval<br>
                        <strong>Autonomous:</strong> All actions are executed immediately (use with caution)
                    </p>
                </td>
            </tr>
        </table>

        <h2>Response Caching</h2>
        <p>Cache identical queries to save tokens and reduce latency.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="agentic_response_cache_enabled">Enable Response Cache</label>
                </th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            name="agentic_response_cache_enabled" 
                            id="agentic_response_cache_enabled" 
                            value="1"
                            <?php checked( $cache_enabled ); ?>
                        />
                        Cache identical messages to avoid repeated LLM calls
                    </label>
                    <p class="description">
                        When enabled, exact-match queries return cached responses. Saves tokens and improves response time.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_response_cache_ttl">Cache TTL</label>
                </th>
                <td>
                    <select name="agentic_response_cache_ttl" id="agentic_response_cache_ttl">
                        <option value="900" <?php selected( $cache_ttl, 900 ); ?>>15 minutes</option>
                        <option value="1800" <?php selected( $cache_ttl, 1800 ); ?>>30 minutes</option>
                        <option value="3600" <?php selected( $cache_ttl, 3600 ); ?>>1 hour (Recommended)</option>
                        <option value="7200" <?php selected( $cache_ttl, 7200 ); ?>>2 hours</option>
                        <option value="21600" <?php selected( $cache_ttl, 21600 ); ?>>6 hours</option>
                        <option value="86400" <?php selected( $cache_ttl, 86400 ); ?>>24 hours</option>
                    </select>
                    <p class="description">
                        How long to keep cached responses before they expire.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">Cache Statistics</th>
                <td>
                    <p>
                        <strong>Cached entries:</strong> <?php echo esc_html( $cache_stats['entry_count'] ); ?><br>
                        <strong>Status:</strong> <?php echo $cache_stats['enabled'] ? '<span style="color: #22c55e;">Active</span>' : '<span style="color: #b91c1c;">Disabled</span>'; ?>
                    </p>
                    <label>
                        <input type="checkbox" name="agentic_clear_cache" value="1" />
                        Clear all cached responses on save
                    </label>
                </td>
            </tr>
        </table>

        <h2>Security Settings</h2>
        <p>Protect against prompt injection and abuse.</p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="agentic_security_enabled">Enable Security Filter</label>
                </th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            name="agentic_security_enabled" 
                            id="agentic_security_enabled" 
                            value="1"
                            <?php checked( $security_enabled ); ?>
                        />
                        Scan messages for prompt injection and malicious content
                    </label>
                    <p class="description">
                        Blocks common injection patterns, rate limits requests, and flags PII. Adds &lt;1ms overhead.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_rate_limit_authenticated">Rate Limit (Authenticated)</label>
                </th>
                <td>
                    <input 
                        type="number" 
                        name="agentic_rate_limit_authenticated" 
                        id="agentic_rate_limit_authenticated" 
                        value="<?php echo esc_attr( $rate_limit_auth ); ?>" 
                        min="5"
                        max="100"
                        class="small-text"
                    /> requests per minute
                    <p class="description">
                        Maximum chat requests per minute for logged-in users.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_rate_limit_anonymous">Rate Limit (Anonymous)</label>
                </th>
                <td>
                    <input 
                        type="number" 
                        name="agentic_rate_limit_anonymous" 
                        id="agentic_rate_limit_anonymous" 
                        value="<?php echo esc_attr( $rate_limit_anon ); ?>" 
                        min="1"
                        max="30"
                        class="small-text"
                    /> requests per minute
                    <p class="description">
                        Maximum chat requests per minute for anonymous visitors (by IP).
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_allow_anonymous_chat">Allow Anonymous Chat</label>
                </th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            name="agentic_allow_anonymous_chat" 
                            id="agentic_allow_anonymous_chat" 
                            value="1"
                            <?php checked( $allow_anon_chat ); ?>
                        />
                        Allow non-logged-in users to chat via frontend shortcodes
                    </label>
                    <p class="description">
                        When disabled, users must log in to use [agentic_chat] on the frontend.
                    </p>
                </td>
            </tr>
        </table>

        <h2>Permissions</h2>
        <p>Configure what actions the agent can perform autonomously vs. with approval.</p>
        
        <table class="widefat" style="max-width: 600px;">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Current Setting</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Read files from repository</td>
                    <td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Always allowed</td>
                </tr>
                <tr>
                    <td>Search code</td>
                    <td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Always allowed</td>
                </tr>
                <tr>
                    <td>Query WordPress database</td>
                    <td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Always allowed</td>
                </tr>
                <tr>
                    <td>Post comments</td>
                    <td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Autonomous</td>
                </tr>
                <tr>
                    <td>Update documentation (.md files)</td>
                    <td><span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span> Autonomous</td>
                </tr>
                <tr>
                    <td>Modify code files</td>
                    <td><span class="dashicons dashicons-warning" style="color: #f59e0b;"></span> Requires approval</td>
                </tr>
            </tbody>
        </table>

        <?php if ( defined( 'AGENTIC_IS_MARKETPLACE' ) && AGENTIC_IS_MARKETPLACE ) : ?>
        
        <h2>Social Login</h2>
        <p>Configure OAuth credentials for social login providers. Users can sign in with these accounts.</p>
        
        <?php
        $social_auth_options = get_option( 'agentic_social_auth', [] );
        $providers = [
            'google' => [
                'name' => 'Google',
                'docs' => 'https://console.developers.google.com/',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
            ],
            'github' => [
                'name' => 'GitHub',
                'docs' => 'https://github.com/settings/developers',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="#24292e"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>',
            ],
            'wordpress' => [
                'name' => 'WordPress.com',
                'docs' => 'https://developer.wordpress.com/apps/',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="#21759b"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z"/></svg>',
            ],
            'twitter' => [
                'name' => 'X (Twitter)',
                'docs' => 'https://developer.twitter.com/en/portal/dashboard',
                'icon' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="#000"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            ],
        ];
        ?>
        
        <?php foreach ( $providers as $provider_key => $provider_info ) : ?>
            <?php $settings = $social_auth_options[ $provider_key ] ?? []; ?>
            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                <h3 style="display: flex; align-items: center; gap: 8px; margin: 0 0 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                    <?php echo $provider_info['icon']; ?>
                    <?php echo esc_html( $provider_info['name'] ); ?>
                </h3>
                
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th scope="row">Enabled</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="agentic_social_auth[<?php echo esc_attr( $provider_key ); ?>][enabled]" 
                                       value="1" 
                                       <?php checked( ! empty( $settings['enabled'] ) ); ?>>
                                Enable <?php echo esc_html( $provider_info['name'] ); ?> login
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client ID</th>
                        <td>
                            <input type="text" 
                                   name="agentic_social_auth[<?php echo esc_attr( $provider_key ); ?>][client_id]" 
                                   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="password" 
                                   name="agentic_social_auth[<?php echo esc_attr( $provider_key ); ?>][client_secret]" 
                                   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Callback URL</th>
                        <td>
                            <code><?php echo esc_html( home_url( '/auth/callback/' . $provider_key . '/' ) ); ?></code>
                            <p class="description">
                                Use this URL in your <a href="<?php echo esc_url( $provider_info['docs'] ); ?>" target="_blank"><?php echo esc_html( $provider_info['name'] ); ?> developer console</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
        
        <p class="description" style="margin-bottom: 20px;">
            <strong>Login URL:</strong> <code><?php echo esc_html( home_url( '/login/' ) ); ?></code><br>
            <strong>Register URL:</strong> <code><?php echo esc_html( home_url( '/register/' ) ); ?></code>
        </p>
        
        <?php endif; ?>

        <?php if ( defined( 'AGENTIC_IS_MARKETPLACE' ) && AGENTIC_IS_MARKETPLACE ) : ?>
        <?php
        // Stripe settings
        $stripe_test_mode = get_option( 'agentic_stripe_test_mode', true );
        $stripe_test_pk = get_option( 'agentic_stripe_test_publishable_key', '' );
        $stripe_test_sk = get_option( 'agentic_stripe_test_secret_key', '' );
        $stripe_live_pk = get_option( 'agentic_stripe_live_publishable_key', '' );
        $stripe_live_sk = get_option( 'agentic_stripe_live_secret_key', '' );
        $stripe_webhook_secret = get_option( 'agentic_stripe_webhook_secret', '' );
        ?>
        <h2>Stripe Payment Settings</h2>
        <p>Configure Stripe for processing premium agent purchases.</p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="agentic_stripe_test_mode">Test Mode</label>
                </th>
                <td>
                    <label>
                        <input 
                            type="checkbox" 
                            name="agentic_stripe_test_mode" 
                            id="agentic_stripe_test_mode" 
                            value="1"
                            <?php checked( $stripe_test_mode ); ?>
                        />
                        Enable test mode (use test API keys)
                    </label>
                    <p class="description">
                        When enabled, uses test keys. Disable for production.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row" colspan="2"><strong>Test Keys</strong></th>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_stripe_test_publishable_key">Test Publishable Key</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        name="agentic_stripe_test_publishable_key" 
                        id="agentic_stripe_test_publishable_key" 
                        value="<?php echo esc_attr( $stripe_test_pk ); ?>" 
                        class="regular-text"
                        placeholder="pk_test_..."
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_stripe_test_secret_key">Test Secret Key</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        name="agentic_stripe_test_secret_key" 
                        id="agentic_stripe_test_secret_key" 
                        value="<?php echo esc_attr( $stripe_test_sk ); ?>" 
                        class="regular-text"
                        placeholder="sk_test_..."
                    />
                </td>
            </tr>

            <tr>
                <th scope="row" colspan="2"><strong>Live Keys</strong></th>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_stripe_live_publishable_key">Live Publishable Key</label>
                </th>
                <td>
                    <input 
                        type="text" 
                        name="agentic_stripe_live_publishable_key" 
                        id="agentic_stripe_live_publishable_key" 
                        value="<?php echo esc_attr( $stripe_live_pk ); ?>" 
                        class="regular-text"
                        placeholder="pk_live_..."
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_stripe_live_secret_key">Live Secret Key</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        name="agentic_stripe_live_secret_key" 
                        id="agentic_stripe_live_secret_key" 
                        value="<?php echo esc_attr( $stripe_live_sk ); ?>" 
                        class="regular-text"
                        placeholder="sk_live_..."
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="agentic_stripe_webhook_secret">Webhook Secret</label>
                </th>
                <td>
                    <input 
                        type="password" 
                        name="agentic_stripe_webhook_secret" 
                        id="agentic_stripe_webhook_secret" 
                        value="<?php echo esc_attr( $stripe_webhook_secret ); ?>" 
                        class="regular-text"
                        placeholder="whsec_..."
                    />
                    <p class="description">
                        Webhook URL: <code><?php echo esc_html( rest_url( 'agentic-marketplace/v1/webhook' ) ); ?></code><br>
                        Configure this URL in your Stripe Dashboard &gt; Webhooks.
                    </p>
                </td>
            </tr>
        </table>
        <?php endif; ?>

        <p class="submit">
            <input type="submit" name="agentic_save_settings" class="button-primary" value="Save Settings" />
        </p>
    </form>

    <hr>

    <h2>API Test</h2>
    <p>Test the connection to OpenAI:</p>
    <button type="button" id="agentic-test-api" class="button">Test API Connection</button>
    <div id="agentic-test-result" style="margin-top: 10px;"></div>

    <script>
    document.getElementById('agentic-test-api').addEventListener('click', async function() {
        const result = document.getElementById('agentic-test-result');
        result.innerHTML = '<span class="spinner is-active" style="float: none;"></span> Testing...';
        
        try {
            const response = await fetch('<?php echo esc_js( rest_url( 'agentic/v1/status' ) ); ?>', {
                headers: {
                    'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
                }
            });
            const data = await response.json();
            
            if (data.configured) {
                result.innerHTML = '<span style="color: #22c55e;">✓ API is configured and ready!</span>';
            } else {
                result.innerHTML = '<span style="color: #b91c1c;">✗ API key not configured</span>';
            }
        } catch (error) {
            result.innerHTML = '<span style="color: #b91c1c;">✗ Error: ' + error.message + '</span>';
        }
    });
    </script>
</div>
