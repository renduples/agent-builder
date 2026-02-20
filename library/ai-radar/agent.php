<?php
/**
 * Agent Name: AI Radar
 * Version: 1.0.0
 * Description: Scan your site. See who can find you. Checks AI crawler access, schema markup, content structure, and technical readiness — then fixes what it can.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: SEO
 * Tags: ai-search, robots-txt, schema, visibility, chatgpt, perplexity, claude
 * Capabilities: manage_options
 * Icon: 📡
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Radar Agent
 *
 * Scans a WordPress site's readiness to be discovered, cited, and recommended
 * by AI search platforms (ChatGPT, Grok, Claude, Gemini, Perplexity).
 *
 * Read-only by default. The only write operation is an optional robots.txt
 * update — always presented as a diff for user approval before anything changes.
 */
class Agentic_AI_Radar extends \Agentic\Agent_Base {

	/** Transient key for persisting scan results between sessions. */
	const SCAN_TRANSIENT = 'agentic_ai_radar_last_scan';

	/** Admin notice option key for weekly scan alerts. */
	const NOTICE_OPTION = 'agentic_ai_radar_notice';

	/**
	 * AI bot definitions: name → label, severity, max points in crawler category.
	 * Total points for bots sum to 30 (if no blanket block).
	 */
	const AI_BOTS = array(
		'GPTBot'          => array( 'label' => 'ChatGPT / OpenAI',             'severity' => 'critical', 'points' => 10 ),
		'ChatGPT-User'    => array( 'label' => 'ChatGPT Browsing',              'severity' => 'critical', 'points' => 8  ),
		'ClaudeBot'       => array( 'label' => 'Claude / Anthropic',            'severity' => 'high',     'points' => 7  ),
		'anthropic-ai'    => array( 'label' => 'Anthropic AI (alternative UA)', 'severity' => 'high',     'points' => 0  ), // bonus — doesn't score
		'Google-Extended' => array( 'label' => 'Google AI Overview / Gemini',   'severity' => 'high',     'points' => 3  ),
		'PerplexityBot'   => array( 'label' => 'Perplexity',                    'severity' => 'high',     'points' => 2  ),
	);

	// -------------------------------------------------------------------------
	// Agent_Base implementation
	// -------------------------------------------------------------------------

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? (string) file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'ai-radar';
	}

	public function get_name(): string {
		return 'AI Radar';
	}

	public function get_description(): string {
		return "Scan your site. See who can find you. Checks AI crawler access, schema markup, content structure, and technical readiness — then fixes what it can.";
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '📡';
	}

	public function get_category(): string {
		return 'SEO';
	}

	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	public function get_welcome_message(): string {
		return "📡 **AI Radar is ready.**\n\n" .
			"Want me to scan your site and show you which AI search engines can see you — and which ones can't? Takes about 30 seconds.\n\n" .
			"**What I check:**\n" .
			"- 🤖 **AI Crawler Access** — Can ChatGPT, Claude, Perplexity, and Grok actually read your site?\n" .
			"- 📊 **Schema Markup** — Does your site tell AI platforms who you are and what your content means?\n" .
			"- 📝 **Content Structure** — Is your content formatted for AI extraction (headings, FAQ content, freshness)?\n" .
			"- ⚙️ **Technical Readiness** — HTTPS, sitemap, noindex issues, llms.txt?\n\n" .
			'Hit **📡 Scan My Site** to get your AI visibility score.';
	}

	public function get_suggested_prompts(): array {
		return array(
			'📡 Scan My Site',
			'🔍 Check robots.txt',
			'📊 View Last Scan',
			'Fix my robots.txt to allow AI crawlers',
		);
	}

	// -------------------------------------------------------------------------
	// Scheduled tasks
	// -------------------------------------------------------------------------

	public function get_scheduled_tasks(): array {
		return array(
			array(
				'id'          => 'weekly_scan',
				'name'        => 'Weekly AI Radar Scan',
				'schedule'    => 'weekly',
				'callback'    => 'run_weekly_scan_task',
				'description' => 'Full AI visibility scan every 7 days. Sends an admin notice only if the score changed.',
				'prompt'      => "Run a full AI Radar scan using run_ai_radar_scan. Then call get_last_scan to retrieve the previous scan result. Compare the new score to the previous score. If the score improved by 5+ points, post a positive admin notice. If the score dropped by 5+ points, post an urgent admin notice explaining what changed. If the score is stable, do not post a notice. Store the new scan as the current result.",
			),
		);
	}

	// -------------------------------------------------------------------------
	// Event listeners
	// -------------------------------------------------------------------------

	public function get_event_listeners(): array {
		return array(
			array(
				'id'            => 'robots_option_changed',
				'hook'          => 'updated_option',
				'name'          => 'Robots.txt option changed',
				'callback'      => 'on_robots_option_updated',
				'priority'      => 20,
				'accepted_args' => 3,
				'description'   => 'Re-checks AI crawler access when an SEO plugin updates robots.txt settings.',
			),
			array(
				'id'            => 'plugin_activated',
				'hook'          => 'activated_plugin',
				'name'          => 'Plugin activated',
				'callback'      => 'on_plugin_state_changed',
				'priority'      => 20,
				'accepted_args' => 1,
				'description'   => 'Checks if a schema plugin was installed or removed.',
			),
			array(
				'id'            => 'plugin_deactivated',
				'hook'          => 'deactivated_plugin',
				'name'          => 'Plugin deactivated',
				'callback'      => 'on_plugin_state_changed',
				'priority'      => 20,
				'accepted_args' => 1,
				'description'   => 'Checks if a schema plugin was removed.',
			),
		);
	}

	// -------------------------------------------------------------------------
	// Event listener callbacks (direct — no LLM involvement)
	// -------------------------------------------------------------------------

	public function on_robots_option_updated( string $option, mixed $old_value, mixed $new_value ): void {
		// Only react to robots.txt-related option changes.
		$robots_options = array(
			'disallow_crawl',
			'blog_public',
			'wpseo',
			'wpseo_titles',
			'rank_math_robots_extra_directives',
			'all-in-one-seo-pack',
		);

		if ( ! in_array( $option, $robots_options, true ) ) {
			return;
		}

		// Delete transient so next scan is fresh.
		delete_transient( self::SCAN_TRANSIENT );

		// Quick robots.txt re-check and store an admin notice if bots got blocked.
		$robots = $this->read_robots_txt();
		$result = $this->parse_robots_bots( $robots['content'] );

		$newly_blocked = array();
		foreach ( array( 'GPTBot', 'ChatGPT-User', 'ClaudeBot' ) as $bot ) {
			if ( 'allowed' !== ( $result['bot_access'][ $bot ] ?? 'allowed' ) ) {
				$newly_blocked[] = $bot;
			}
		}

		if ( ! empty( $newly_blocked ) ) {
			update_option(
				self::NOTICE_OPTION,
				array(
					'type'    => 'warning',
					'message' => '⚡ AI Radar: A recent settings change may have blocked AI crawlers (' . implode( ', ', $newly_blocked ) . ') from reading your site. ' .
						'<a href="/wp-admin/admin.php?page=agentic-chat">Open AI Radar to check →</a>',
					'time'    => time(),
				)
			);
		}
	}

	public function on_plugin_state_changed( string $plugin ): void {
		$schema_plugins = array( 'wordpress-seo/wp-seo.php', 'seo-by-rank-math/rank-math.php', 'schema-and-structured-data-for-wp/index.php', 'wp-schema-pro/wp-schema-pro.php', 'all-in-one-seo-pack/all_in_one_seo_pack.php' );
		foreach ( $schema_plugins as $sp ) {
			if ( str_contains( $plugin, dirname( $sp ) ) ) {
				delete_transient( self::SCAN_TRANSIENT );
				break;
			}
		}
	}

	// -------------------------------------------------------------------------
	// Scheduled task callback (direct, non-LLM fallback)
	// -------------------------------------------------------------------------

	public function run_weekly_scan_task(): void {
		// This is the fallback — the LLM prompt runs first via get_scheduled_tasks().
		// If LLM is unavailable, run a silent scan and update the transient.
		$scan = $this->tool_run_ai_radar_scan();
		set_transient( self::SCAN_TRANSIENT, $scan, WEEK_IN_SECONDS * 4 );
	}

	// -------------------------------------------------------------------------
	// Tool definitions
	// -------------------------------------------------------------------------

	public function get_tools(): array {
		return array(

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'run_ai_radar_scan',
					'description' => 'Run a full AI visibility scan across all four categories: AI crawler access (30 pts), schema markup (25 pts), content structure (25 pts), and technical readiness (20 pts). Returns a score 0–100 with a letter grade, prioritised critical/important/passing lists, and per-category breakdowns. The result is cached for get_last_scan.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_robots_txt',
					'description' => 'Read and parse the site\'s robots.txt. Returns the access status for each AI bot (GPTBot, ChatGPT-User, ClaudeBot, Google-Extended, PerplexityBot), whether a blanket block exists, the raw file content, and the file source (physical file or WordPress virtual).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_schema_markup',
					'description' => 'Fetch the homepage HTML and analyse JSON-LD structured data. Returns detected schema types (Organization, Article, Product, FAQ, BreadcrumbList, etc.), installed schema plugins, and a score out of 25 with specific gaps identified.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_content_structure',
					'description' => 'Analyse published posts and pages for AI extraction readiness: FAQ-formatted content, heading hierarchy, content freshness, entity clarity on the homepage, and thin content pages under 200 words. Returns a score out of 25 with specific issues.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_limit' => array(
								'type'        => 'integer',
								'description' => 'Max published posts to analyse (10–50). Defaults to 20.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_technical_readiness',
					'description' => 'Check technical AI readiness signals: HTTPS, sitemap.xml, noindex settings, llms.txt presence. Returns a score out of 20 with pass/fail per check.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'update_robots_txt',
					'description' => 'Propose or apply updates to robots.txt that allow AI search bots. With dry_run=true (default) shows the exact before/after diff so the user can review. With dry_run=false writes the change to disk. ALWAYS call with dry_run=true first and show the user the proposed changes before applying.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'dry_run'    => array(
								'type'        => 'boolean',
								'description' => 'When true (default), returns a diff/preview without writing. When false, writes the updated robots.txt to disk. Always use true first.',
							),
							'allow_bots' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Bot names to explicitly allow. Defaults to all major AI bots: GPTBot, ChatGPT-User, ClaudeBot, Google-Extended, PerplexityBot, anthropic-ai.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_last_scan',
					'description' => 'Retrieve the most recent AI Radar scan results from cache. Returns null if no scan has been run yet.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

		);
	}

	// -------------------------------------------------------------------------
	// Tool dispatch
	// -------------------------------------------------------------------------

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'run_ai_radar_scan'       => $this->tool_run_ai_radar_scan(),
			'check_robots_txt'        => $this->tool_check_robots_txt(),
			'check_schema_markup'     => $this->tool_check_schema_markup(),
			'check_content_structure' => $this->tool_check_content_structure( $arguments ),
			'check_technical_readiness' => $this->tool_check_technical_readiness(),
			'update_robots_txt'       => $this->tool_update_robots_txt( $arguments ),
			'get_last_scan'           => $this->tool_get_last_scan(),
			default                   => null,
		};
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	/**
	 * Composite scan: runs all four categories and aggregates a 0-100 score.
	 */
	private function tool_run_ai_radar_scan(): array {
		$robots   = $this->tool_check_robots_txt();
		$schema   = $this->tool_check_schema_markup();
		$content  = $this->tool_check_content_structure( array( 'post_limit' => 20 ) );
		$tech     = $this->tool_check_technical_readiness();

		$total_score = ( $robots['score'] ?? 0 )
			+ ( $schema['score'] ?? 0 )
			+ ( $content['score'] ?? 0 )
			+ ( $tech['score'] ?? 0 );

		// Build prioritised action list.
		$critical  = array();
		$important = array();
		$passing   = array();

		// Crawler issues.
		foreach ( $robots['bots'] ?? array() as $bot => $info ) {
			if ( 'allowed' !== $info['access'] ) {
				$label = self::AI_BOTS[ $bot ]['label'] ?? $bot;
				if ( 'critical' === ( self::AI_BOTS[ $bot ]['severity'] ?? 'high' ) ) {
					$critical[] = "{$label} ({$bot}) is BLOCKED — cannot read your site.";
				} else {
					$important[] = "{$label} ({$bot}) is BLOCKED.";
				}
			} else {
				$passing[] = "{$bot}: allowed";
			}
		}
		if ( ! empty( $robots['blanket_block'] ) ) {
			$critical[] = 'Blanket block (User-agent: * Disallow: /) detected — ALL crawlers including AI bots are blocked.';
		}

		// Schema issues.
		foreach ( $schema['issues'] ?? array() as $issue ) {
			if ( in_array( $issue['severity'] ?? 'important', array( 'critical' ), true ) ) {
				$critical[] = $issue['message'];
			} else {
				$important[] = $issue['message'];
			}
		}
		foreach ( $schema['passing'] ?? array() as $p ) {
			$passing[] = $p;
		}

		// Content issues.
		foreach ( $content['issues'] ?? array() as $issue ) {
			if ( 'critical' === ( $issue['severity'] ?? 'important' ) ) {
				$critical[] = $issue['message'];
			} else {
				$important[] = $issue['message'];
			}
		}
		foreach ( $content['passing'] ?? array() as $p ) {
			$passing[] = $p;
		}

		// Technical issues.
		foreach ( $tech['issues'] ?? array() as $issue ) {
			if ( 'critical' === ( $issue['severity'] ?? 'medium' ) ) {
				$critical[] = $issue['message'];
			} else {
				$important[] = $issue['message'];
			}
		}
		foreach ( $tech['passing'] ?? array() as $p ) {
			$passing[] = $p;
		}

		$result = array(
			'score'      => $total_score,
			'grade'      => $this->score_to_grade( $total_score ),
			'scanned_at' => wp_date( 'Y-m-d H:i:s' ),
			'categories' => array(
				'ai_crawler_access' => array(
					'score'    => $robots['score'] ?? 0,
					'max'      => 30,
					'label'    => 'AI Crawler Access',
				),
				'schema_markup' => array(
					'score'    => $schema['score'] ?? 0,
					'max'      => 25,
					'label'    => 'Schema Markup',
				),
				'content_structure' => array(
					'score'    => $content['score'] ?? 0,
					'max'      => 25,
					'label'    => 'Content Structure',
				),
				'technical_readiness' => array(
					'score'    => $tech['score'] ?? 0,
					'max'      => 20,
					'label'    => 'Technical Readiness',
				),
			),
			'actions'     => array(
				'critical'  => $critical,
				'important' => $important,
				'passing'   => $passing,
			),
			'site_url'   => home_url(),
		);

		// Cache the result.
		set_transient( self::SCAN_TRANSIENT, $result, WEEK_IN_SECONDS * 4 );

		return $result;
	}

	/**
	 * Read and parse robots.txt for AI bot access.
	 */
	private function tool_check_robots_txt(): array {
		$robots  = $this->read_robots_txt();
		$parsed  = $this->parse_robots_bots( $robots['content'] );
		$score   = 0;
		$bots    = array();

		foreach ( self::AI_BOTS as $bot => $cfg ) {
			$access = $parsed['bot_access'][ $bot ] ?? 'allowed';

			// Blanket block overrides per-bot status if no specific rule exists.
			if ( $parsed['blanket_block'] && ! isset( $parsed['explicit_bots'][ strtolower( $bot ) ] ) ) {
				$access = 'blocked_by_wildcard';
			}

			$bots[ $bot ] = array(
				'label'  => $cfg['label'],
				'access' => $access,
				'points' => $cfg['points'],
			);

			if ( 'allowed' === $access && $cfg['points'] > 0 ) {
				$score += $cfg['points'];
			}
		}

		// Hard floor: if blanket block present, score is 0.
		if ( $parsed['blanket_block'] ) {
			$score = 0;
		}

		return array(
			'score'         => $score,
			'max'           => 30,
			'bots'          => $bots,
			'blanket_block' => $parsed['blanket_block'],
			'raw_content'   => $robots['content'],
			'source'        => $robots['source'],
			'source_path'   => $robots['path'],
			'issues'        => $this->bots_to_issues( $bots, $parsed['blanket_block'] ),
			'passing'       => $this->bots_to_passing( $bots ),
		);
	}

	/**
	 * Fetch homepage HTML and detect schema markup.
	 */
	private function tool_check_schema_markup(): array {
		$url      = home_url( '/' );
		$response = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => false ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'score'   => 0,
				'max'     => 25,
				'error'   => 'Could not fetch homepage: ' . $response->get_error_message(),
				'issues'  => array( array( 'message' => 'Could not fetch homepage HTML to check schema.', 'severity' => 'important' ) ),
				'passing' => array(),
			);
		}

		$html = wp_remote_retrieve_body( $response );

		// Extract all JSON-LD blocks.
		preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches );

		$schema_types = array();
		foreach ( $matches[1] as $json_str ) {
			$data = json_decode( trim( $json_str ), true );
			if ( ! $data ) {
				continue;
			}
			if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
				foreach ( $data['@graph'] as $item ) {
					$types        = (array) ( $item['@type'] ?? array() );
					$schema_types = array_merge( $schema_types, $types );
				}
			} else {
				$types        = (array) ( $data['@type'] ?? array() );
				$schema_types = array_merge( $schema_types, $types );
			}
		}
		$schema_types = array_unique( array_filter( $schema_types ) );

		// Detect known schema plugins.
		$active_plugins   = get_option( 'active_plugins', array() );
		$has_rank_math    = $this->is_plugin_active( $active_plugins, 'rank-math' );
		$has_yoast        = $this->is_plugin_active( $active_plugins, 'wordpress-seo' );
		$has_aioseo       = $this->is_plugin_active( $active_plugins, 'all-in-one-seo-pack' );
		$has_schema_pro   = $this->is_plugin_active( $active_plugins, 'wp-schema-pro' );
		$has_any_schema_plugin = $has_rank_math || $has_yoast || $has_aioseo || $has_schema_pro;

		$score   = 0;
		$issues  = array();
		$passing = array();

		// Check schema types.
		$has_org = ! empty( array_intersect( $schema_types, array( 'Organization', 'LocalBusiness', 'Corporation', 'NGO', 'EducationalOrganization' ) ) );
		if ( $has_org ) {
			$score += 8;
			$passing[] = 'Organization/LocalBusiness schema present — AI knows who you are';
		} else {
			$issues[] = array( 'message' => 'No Organization or LocalBusiness schema — AI platforms don\'t know who you are as a business. This is the highest-impact missing schema.', 'severity' => 'important' );
		}

		$has_article = ! empty( array_intersect( $schema_types, array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage' ) ) );
		if ( $has_article ) {
			$score += 5;
			$passing[] = 'Article/BlogPosting schema detected on homepage';
		} else {
			$issues[] = array( 'message' => 'No Article or BlogPosting schema on homepage — blog posts may not be recognised as articles by AI platforms.', 'severity' => 'important' );
		}

		$has_breadcrumb = in_array( 'BreadcrumbList', $schema_types, true );
		if ( $has_breadcrumb ) {
			$score += 4;
			$passing[] = 'BreadcrumbList schema present';
		} else {
			$issues[] = array( 'message' => 'No BreadcrumbList schema — site hierarchy is not machine-readable.', 'severity' => 'low' );
		}

		$has_faq = in_array( 'FAQPage', $schema_types, true );
		if ( $has_faq ) {
			$score += 5;
			$passing[] = 'FAQPage schema detected — AI can extract direct answers';
		} else {
			$issues[] = array( 'message' => 'No FAQPage schema — missing the easiest way to get cited by AI as a direct answer source.', 'severity' => 'important' );
		}

		// Partial credit if schema plugin present but no types detected.
		if ( $has_any_schema_plugin && count( $schema_types ) === 0 ) {
			$score  += 3;
			$issues[] = array( 'message' => 'Schema plugin detected but no JSON-LD found on homepage — check plugin configuration.', 'severity' => 'important' );
		}

		// Bonus: WooCommerce + Product schema.
		if ( $this->is_plugin_active( $active_plugins, 'woocommerce' ) ) {
			$has_product = in_array( 'Product', $schema_types, true );
			if ( $has_product ) {
				$score    = min( 25, $score + 3 );
				$passing[] = 'Product schema detected (WooCommerce)';
			} else {
				$issues[] = array( 'message' => 'WooCommerce detected but no Product schema found — AI shopping assistants can\'t see your product data.', 'severity' => 'important' );
			}
		}

		// Detect schema plugin info for the report.
		$schema_plugins_installed = array_filter( array(
			$has_rank_math  ? 'Rank Math SEO' : null,
			$has_yoast      ? 'Yoast SEO'     : null,
			$has_aioseo     ? 'All-in-One SEO' : null,
			$has_schema_pro ? 'WP Schema Pro'  : null,
		) );

		return array(
			'score'                   => min( 25, $score ),
			'max'                     => 25,
			'detected_schema_types'   => array_values( $schema_types ),
			'schema_plugins_detected' => array_values( $schema_plugins_installed ),
			'issues'                  => $issues,
			'passing'                 => $passing,
		);
	}

	/**
	 * Analyse published posts and pages for AI content extraction readiness.
	 */
	private function tool_check_content_structure( array $args ): array {
		$post_limit = min( max( (int) ( $args['post_limit'] ?? 20 ), 5 ), 50 );

		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $post_limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		$score   = 0;
		$issues  = array();
		$passing = array();

		if ( empty( $posts ) ) {
			return array( 'score' => 0, 'max' => 25, 'note' => 'No published content found.', 'issues' => array(), 'passing' => array() );
		}

		// --- Content freshness (6 pts) ---
		$most_recent_date  = max( array_map( fn( $p ) => strtotime( $p->post_modified ), $posts ) );
		$days_since_update = (int) floor( ( time() - $most_recent_date ) / DAY_IN_SECONDS );

		if ( $days_since_update <= 30 ) {
			$score += 6;
			$passing[] = 'Content updated recently (' . $days_since_update . ' days ago)';
		} elseif ( $days_since_update <= 90 ) {
			$score += 4;
			$passing[] = 'Content updated ' . $days_since_update . ' days ago — keep publishing';
		} else {
			$issues[] = array(
				'message'  => "Most recent content update: {$days_since_update} days ago — AI platforms deprioritise stale sites. Publish or update content regularly.",
				'severity' => $days_since_update > 180 ? 'important' : 'low',
			);
		}

		// --- FAQ-formatted content (7 pts) ---
		$faq_pages   = 0;
		$thin_pages  = array();
		$no_headings = array();
		$total_words = 0;

		foreach ( $posts as $post ) {
			$content      = wp_strip_all_tags( $post->post_content );
			$word_count   = str_word_count( $content );
			$total_words += $word_count;

			if ( $word_count < 200 && 'page' !== $post->post_type ) {
				$thin_pages[] = $post->post_title;
			}

			// FAQ detection: looks for question patterns (H2/H3 ending in ?, or "Q:" prefix, or explicit question words).
			if ( preg_match( '/(<h[23][^>]*>.*?\?.*?<\/h[23]>|Q:\s*\w|\bfrequ.*?ask|\bfaq\b)/si', $post->post_content ) ) {
				++$faq_pages;
			}

			// Heading hierarchy: posts should have at least one H2.
			if ( 'post' === $post->post_type && $word_count > 400 ) {
				if ( ! preg_match( '/<h2[^>]*>/i', $post->post_content ) ) {
					$no_headings[] = $post->post_title;
				}
			}
		}

		if ( $faq_pages > 0 ) {
			$score += 7;
			$passing[] = "{$faq_pages} page(s) with FAQ-formatted content — good for AI direct-answer citations";
		} else {
			$issues[] = array(
				'message'  => 'No FAQ-formatted content detected — question-and-answer content is the easiest way for AI platforms to cite your site. Add FAQ sections to your top pages. Your Content Writer agent can draft them.',
				'severity' => 'important',
			);
		}

		// --- Heading hierarchy (4 pts) ---
		if ( empty( $no_headings ) ) {
			$score += 4;
			$passing[] = 'Posts use H2 headings — good content hierarchy for AI extraction';
		} else {
			$count = count( $no_headings );
			if ( $count <= 2 ) {
				$score += 2;
			}
			$issues[] = array(
				'message'  => "{$count} post(s) longer than 400 words with no H2 headings — AI platforms struggle to extract meaning from unstructured text. Use your SEO Assistant to fix heading structure.",
				'severity' => 'important',
			);
		}

		// --- Entity clarity: does the homepage define what the site is? (5 pts) ---
		$homepage_id = get_option( 'page_on_front' );
		$homepage    = $homepage_id ? get_post( (int) $homepage_id ) : null;

		$homepage_content = $homepage
			? wp_strip_all_tags( $homepage->post_content )
			: wp_strip_all_tags( ( get_posts( array( 'post_type' => 'page', 'posts_per_page' => 1, 'post_status' => 'publish' ) )[0]->post_content ?? '' ) );

		$first_200 = substr( $homepage_content, 0, 800 ); // ~200 words
		// Entity clarity check: contains who/what/serve/help keywords in the first portion
		if ( preg_match( '/\b(we|our|help|offer|specialist|service|solution|platform|tool|software|agency|studio|shop|store|expert|provider|based in|located)\b/i', $first_200 ) ) {
			$score += 5;
			$passing[] = 'Homepage clearly states what the site does — good entity clarity for AI';
		} else {
			$issues[] = array(
				'message'  => 'Homepage doesn\'t clearly identify what the site is or who it serves in the first 200 words — AI platforms may misclassify or skip your site.',
				'severity' => 'important',
			);
		}

		// --- Thin content penalty ---
		if ( ! empty( $thin_pages ) ) {
			$count = count( $thin_pages );
			$score = max( 0, $score - min( 6, $count * 2 ) );
			$issues[] = array(
				'message'  => "{$count} post(s) with under 200 words — these are effectively invisible to AI systems. Either expand or depublish them.",
				'severity' => 'low',
			);
		}

		return array(
			'score'            => min( 25, $score ),
			'max'              => 25,
			'posts_analysed'   => count( $posts ),
			'faq_pages'        => $faq_pages,
			'thin_pages'       => count( $thin_pages ),
			'thin_page_titles' => array_slice( $thin_pages, 0, 5 ),
			'days_since_update'=> $days_since_update,
			'issues'           => $issues,
			'passing'          => $passing,
		);
	}

	/**
	 * Check technical AI readiness signals.
	 */
	private function tool_check_technical_readiness(): array {
		$score   = 0;
		$issues  = array();
		$passing = array();

		// --- HTTPS (6 pts) ---
		$is_https = str_starts_with( home_url(), 'https://' );
		if ( $is_https ) {
			$score += 6;
			$passing[] = 'Site is served over HTTPS — trust signal for AI crawlers';
		} else {
			$issues[] = array(
				'message'  => 'Site is not using HTTPS — a strong negative signal for AI trust and indexing.',
				'severity' => 'critical',
			);
		}

		// --- Sitemap (6 pts) ---
		$sitemap_url  = null;
		$sitemap_ok   = false;
		$sitemap_urls = array( '/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml' );

		// Check if Rank Math or Yoast have sitemaps.
		if ( get_option( 'rank_math_modules' ) || get_option( 'wpseo_xml_sitemap_enabled' ) ) {
			$sitemap_ok = true;
			$sitemap_url = home_url( '/sitemap_index.xml' );
		} else {
			foreach ( $sitemap_urls as $path ) {
				$response = wp_remote_head( home_url( $path ), array( 'timeout' => 7, 'sslverify' => false ) );
				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
					$sitemap_ok  = true;
					$sitemap_url = home_url( $path );
					break;
				}
			}
		}

		if ( $sitemap_ok ) {
			$score += 6;
			$passing[] = 'Sitemap found at ' . ( $sitemap_url ?? 'known location' ) . ' — crawlers can discover all your content';
		} else {
			$issues[] = array(
				'message'  => 'No sitemap.xml found — AI crawlers may miss pages that aren\'t linked internally. Install Rank Math or Yoast SEO to generate one automatically.',
				'severity' => 'important',
			);
		}

		// --- Noindex / blog_public (5 pts) ---
		$search_allowed = (bool) get_option( 'blog_public', 1 );
		if ( $search_allowed ) {
			$score += 5;
			$passing[] = 'Site is not set to discourage search engines (Settings → Reading)';
		} else {
			$issues[] = array(
				'message'  => '"Discourage search engines" is enabled (Settings → Reading) — this adds a noindex signal that AI crawlers may honour. Disable it unless this is intentional.',
				'severity' => 'critical',
			);
		}

		// --- llms.txt (3 pts) ---
		$llms_path = rtrim( ABSPATH, '/' ) . '/llms.txt';
		if ( file_exists( $llms_path ) ) {
			$score += 3;
			$passing[] = 'llms.txt found — good future-proofing for AI platforms that support it';
		} else {
			$issues[] = array(
				'message'  => 'No llms.txt file. This is an emerging standard (not yet mainstream) — low priority but worth adding. It signals to AI platforms how they should use your content.',
				'severity' => 'low',
			);
		}

		return array(
			'score'   => min( 20, $score ),
			'max'     => 20,
			'checks'  => array(
				'https'      => $is_https,
				'sitemap'    => $sitemap_ok,
				'searchable' => $search_allowed,
				'llms_txt'   => file_exists( $llms_path ),
			),
			'issues'  => $issues,
			'passing' => $passing,
		);
	}

	/**
	 * Propose or apply a robots.txt update to allow AI crawlers.
	 */
	private function tool_update_robots_txt( array $args ): array {
		$dry_run    = (bool) ( $args['dry_run'] ?? true );
		$allow_bots = $args['allow_bots'] ?? array( 'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'Google-Extended', 'PerplexityBot', 'anthropic-ai' );

		$robots = $this->read_robots_txt();

		if ( ! $robots['writable'] ) {
			return array(
				'success' => false,
				'error'   => 'The robots.txt location is not writable. Path: ' . $robots['path'],
			);
		}

		$current   = $robots['content'];
		$parsed    = $this->parse_robots_bots( $current );
		$additions = array();

		// Build addition: only for bots that are currently blocked or not mentioned.
		foreach ( $allow_bots as $bot ) {
			$access = $parsed['bot_access'][ $bot ] ?? 'allowed';
			if ( 'allowed' !== $access ) {
				$additions[] = "User-agent: {$bot}";
				$additions[] = 'Allow: /';
				$additions[] = '';
			}
		}

		if ( empty( $additions ) ) {
			return array(
				'success'  => true,
				'message'  => 'All specified AI bots are already allowed. No changes needed.',
				'dry_run'  => $dry_run,
				'changed'  => false,
			);
		}

		// Build new content: add AI bot rules at the top (before any wildcard rules).
		$header   = "# AI Search Engine Access\n# Added by AI Radar (Agent Builder — agentic-plugin.com)\n";
		$addition = $header . implode( "\n", $additions );

		if ( ! empty( $current ) ) {
			$new_content = $addition . "\n" . ltrim( $current );
		} else {
			$new_content = $addition . "\nUser-agent: *\nDisallow:\n";
		}

		if ( $dry_run ) {
			return array(
				'success'      => true,
				'dry_run'      => true,
				'current'      => $current ?: '(no robots.txt — will be created)',
				'proposed'     => $new_content,
				'added_bots'   => array_values( array_filter( $additions, fn( $l ) => str_starts_with( $l, 'User-agent' ) ) ),
				'source'       => $robots['source'],
				'write_path'   => $robots['path'],
				'note'         => 'Review the proposed content above, then call update_robots_txt with dry_run=false to apply. The user MUST explicitly approve.',
			);
		}

		// Apply: write the file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $robots['path'], $new_content );

		if ( false === $written ) {
			return array(
				'success' => false,
				'error'   => 'File write failed. Check server permissions on: ' . $robots['path'],
			);
		}

		// Invalidate cached scan.
		delete_transient( self::SCAN_TRANSIENT );

		return array(
			'success'    => true,
			'dry_run'    => false,
			'applied'    => true,
			'bytes'      => $written,
			'path'       => $robots['path'],
			'new_content'=> $new_content,
			'message'    => 'robots.txt updated. AI crawlers (' . implode( ', ', array_filter( $additions, fn( $l ) => str_starts_with( $l, 'User-agent' ) ) ) . ') are now explicitly allowed.',
		);
	}

	/**
	 * Retrieve the cached last scan result.
	 */
	private function tool_get_last_scan(): array {
		$scan = get_transient( self::SCAN_TRANSIENT );

		if ( false === $scan ) {
			return array(
				'has_scan'  => false,
				'message'   => 'No previous scan found. Run run_ai_radar_scan to generate your first AI visibility score.',
			);
		}

		return array(
			'has_scan'   => true,
			'scan'       => $scan,
			'scanned_at' => $scan['scanned_at'] ?? null,
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Read robots.txt from disk or via HTTP.
	 *
	 * @return array{content: string, source: string, path: string, writable: bool}
	 */
	private function read_robots_txt(): array {
		$physical_path = rtrim( ABSPATH, '/' ) . '/robots.txt';

		if ( file_exists( $physical_path ) ) {
			return array(
				'source'   => 'physical_file',
				'path'     => $physical_path,
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'content'  => (string) file_get_contents( $physical_path ),
				'writable' => is_writable( $physical_path ),
			);
		}

		// Fetch virtual robots.txt via HTTP.
		$response = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 10, 'sslverify' => false ) );
		$content  = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );

		return array(
			'source'   => 'virtual',
			'path'     => $physical_path, // Where we'd create it.
			'content'  => $content,
			'writable' => is_writable( ABSPATH ),
			'note'     => 'WordPress generates this file dynamically. Creating a physical robots.txt overrides it.',
		);
	}

	/**
	 * Parse robots.txt content and determine per-bot access.
	 *
	 * @param  string $content Raw robots.txt content.
	 * @return array{bot_access: array<string,string>, blanket_block: bool, explicit_bots: array<string,bool>}
	 */
	private function parse_robots_bots( string $content ): array {
		$lines           = preg_split( '/\r\n|\n|\r/', $content );
		$groups          = array();   // agent(lowercase) => array of {type,path}
		$current_agents  = array();
		$current_rules   = array();
		$explicit_bots   = array();   // bots mentioned explicitly (lowercase)

		$flush_group = function () use ( &$groups, &$current_agents, &$current_rules, &$explicit_bots ) {
			foreach ( $current_agents as $agent ) {
				$key = strtolower( $agent );
				if ( '*' !== $key ) {
					$explicit_bots[ $key ] = true;
				}
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array();
				}
				$groups[ $key ] = array_merge( $groups[ $key ], $current_rules );
			}
			$current_agents = array();
			$current_rules  = array();
		};

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || '#' === ( $line[0] ?? '' ) ) {
				if ( ! empty( $current_agents ) ) {
					$flush_group();
				}
				continue;
			}

			if ( preg_match( '/^User-agent:\s*(.+)$/i', $line, $m ) ) {
				if ( ! empty( $current_rules ) ) {
					$flush_group();
				}
				$current_agents[] = trim( $m[1] );
			} elseif ( preg_match( '/^Disallow:\s*(.*)$/i', $line, $m ) ) {
				$current_rules[] = array( 'type' => 'disallow', 'path' => trim( $m[1] ) );
			} elseif ( preg_match( '/^Allow:\s*(.*)$/i', $line, $m ) ) {
				$current_rules[] = array( 'type' => 'allow', 'path' => trim( $m[1] ) );
			}
		}
		if ( ! empty( $current_agents ) ) {
			$flush_group();
		}

		// Determine blanket block: User-agent: * has Disallow: /
		$blanket_block = false;
		foreach ( $groups['*'] ?? array() as $rule ) {
			if ( 'disallow' === $rule['type'] && '/' === $rule['path'] ) {
				$blanket_block = true;
				break;
			}
		}

		// Determine per-bot access.
		$bot_access = array();
		foreach ( array_keys( self::AI_BOTS ) as $bot ) {
			$bot_lower     = strtolower( $bot );
			$specific      = $groups[ $bot_lower ] ?? null;
			$wild          = $groups['*'] ?? array();
			$applicable    = $specific ?? $wild;

			$access = 'allowed'; // Default is permissive.
			foreach ( $applicable as $rule ) {
				if ( 'disallow' === $rule['type'] && '/' === $rule['path'] ) {
					$access = 'blocked';
					break;
				}
				if ( 'disallow' === $rule['type'] && '' === $rule['path'] ) {
					$access = 'allowed'; // Empty Disallow = allow all.
					break;
				}
			}
			$bot_access[ $bot ] = $access;
		}

		return array(
			'bot_access'    => $bot_access,
			'blanket_block' => $blanket_block,
			'explicit_bots' => $explicit_bots,
		);
	}

	/**
	 * Convert bot access map to issue list.
	 *
	 * @param  array<string, array> $bots     Bot status map.
	 * @param  bool                 $blanket  True if blanket block present.
	 * @return array<int, array>
	 */
	private function bots_to_issues( array $bots, bool $blanket ): array {
		$issues = array();
		if ( $blanket ) {
			$issues[] = array(
				'message'  => 'Blanket block detected (User-agent: * / Disallow: /) — all AI crawlers are blocked.',
				'severity' => 'critical',
			);
		}
		foreach ( $bots as $bot => $info ) {
			if ( 'allowed' !== $info['access'] ) {
				$severity = self::AI_BOTS[ $bot ]['severity'] ?? 'high';
				$issues[] = array(
					'message'  => "{$info['label']} ({$bot}) is BLOCKED — " . ( 'critical' === $severity ? 'ChatGPT cannot see or cite your site.' : 'This AI platform cannot read your content.' ),
					'severity' => $severity,
				);
			}
		}
		return $issues;
	}

	/**
	 * Build passing items list for bots that are allowed.
	 *
	 * @param  array<string, array> $bots Bot status map.
	 * @return array<int, string>
	 */
	private function bots_to_passing( array $bots ): array {
		$passing = array();
		foreach ( $bots as $bot => $info ) {
			if ( 'allowed' === $info['access'] ) {
				$passing[] = "{$bot} ({$info['label']}) is allowed";
			}
		}
		return $passing;
	}

	/**
	 * Check if a plugin slug is in the active plugins list.
	 *
	 * @param  array  $active_plugins Active plugin file paths.
	 * @param  string $slug           Plugin folder slug to match.
	 * @return bool
	 */
	private function is_plugin_active( array $active_plugins, string $slug ): bool {
		foreach ( $active_plugins as $plugin ) {
			if ( str_starts_with( $plugin, $slug . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert numeric score to letter grade with description.
	 *
	 * @param  int $score 0–100.
	 * @return string
	 */
	private function score_to_grade( int $score ): string {
		return match ( true ) {
			$score >= 90 => 'A — Your site is well-optimised for AI search',
			$score >= 75 => 'B — Good foundation, a few gaps to close',
			$score >= 50 => 'C — Partially visible, significant improvements needed',
			$score >= 25 => 'D — Mostly invisible to AI search engines',
			default      => 'F — AI search engines cannot see your site',
		};
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_AI_Radar() );
	}
);
