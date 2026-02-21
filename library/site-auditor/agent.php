<?php
/**
 * Agent Name: Site Auditor
 * Version: 1.0.0
 * Description: Comprehensive audit of your website's commercial performance across 8 dimensions: UX, accessibility, GDPR, web standards, SEO, AI visibility, content quality, and commercial viability. Scores 0–100 and identifies your top 3 roadblocks.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Audit
 * Tags: audit, accessibility, gdpr, seo, ux, performance, content, commercial, compliance
 * Capabilities: manage_options
 * Icon: 📋
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Auditor Agent
 *
 * Runs a comprehensive 8-dimension audit of a WordPress site's commercial
 * performance. Fully read-only — no write permissions required.
 * Stores audit history for trend tracking.
 */
class Agentic_Site_Auditor extends \Agentic\Agent_Base {

	/** Option key for storing audit history (last 12 results). */
	const HISTORY_OPTION = 'agentic_site_auditor_history';

	/** Option key for scheduling preference. */
	const SCHEDULE_OPTION = 'agentic_site_auditor_schedule';

	/** Admin notice option key. */
	const NOTICE_OPTION = 'agentic_site_auditor_notice';

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? (string) file_get_contents( $prompt_file ) : '';
	}

	// -------------------------------------------------------------------------
	// Agent_Base implementation
	// -------------------------------------------------------------------------

	public function get_id(): string {
		return 'site-auditor';
	}

	public function get_name(): string {
		return 'Site Auditor';
	}

	public function get_description(): string {
		return 'Comprehensive audit of your website\'s commercial performance across 8 dimensions: UX, accessibility, GDPR, web standards, SEO, AI visibility, content quality, and commercial viability. Scores 0–100 and identifies your top 3 roadblocks.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '📋';
	}

	public function get_category(): string {
		return 'Audit';
	}

	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	public function get_welcome_message(): string {
		return "📋 **Site Auditor**\n\n" .
			"I run a comprehensive audit of your website's commercial performance across 8 dimensions and tell you exactly what to fix first.\n\n" .
			"**What I audit:**\n" .
			"- 🎯 **User Experience** — navigation, mobile, Core Web Vitals, CTAs\n" .
			"- ♿ **Accessibility** — alt text, contrast, ARIA, keyboard navigation\n" .
			"- 🔐 **GDPR & Privacy** — cookie consent, privacy policy, trackers\n" .
			"- 🌐 **Web Standards** — HTML, sitemap, Open Graph, mobile viewport\n" .
			"- 🔍 **SEO Health** — titles, meta, schema, internal linking, indexability\n" .
			"- 📡 **AI Visibility** — AI crawler access, structured data, content extractability\n" .
			"- 📝 **Content Quality** — freshness, depth, readability, media richness\n" .
			"- 💰 **Commercial Viability** — CTAs, trust signals, contact info, lead capture\n\n" .
			"Hit **📋 Run Full Audit** to get your score and Top 3 Roadblocks.";
	}

	public function get_suggested_prompts(): array {
		return array(
			'📋 Run Full Audit',
			'📊 View Last Report',
			'🔍 Audit Accessibility Only',
			'📈 Show Score History',
		);
	}

	// -------------------------------------------------------------------------
	// Scheduled tasks
	// -------------------------------------------------------------------------

	public function get_scheduled_tasks(): array {
		return array(
			array(
				'id'          => 'monthly_audit',
				'name'        => 'Monthly Site Audit',
				'schedule'    => 'monthly',
				'callback'    => 'run_scheduled_audit',
				'description' => 'Full 8-dimension audit once a month. Posts an admin notice comparing to the previous scan.',
				'prompt'      => "Run a full site audit using run_full_audit. Then retrieve the audit history using get_audit_history to find the previous score. Compare the new overall score to the previous score. If the score improved by 5 or more points, post a positive admin notice saying the score improved and what drove it. If the score dropped by 5 or more points, post an urgent admin notice explaining which dimensions declined. If the score changed by less than 5 points, post a brief stable notice. Always store the new audit result in history using save_audit_result.",
			),
		);
	}

	// -------------------------------------------------------------------------
	// Tool definitions
	// -------------------------------------------------------------------------

	public function get_tools(): array {
		return array(

			// -----------------------------------------------------------------
			// Core audit tools
			// -----------------------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'run_full_audit',
					'description' => 'Run the complete 8-dimension site audit. Returns scores for all dimensions, the overall score (0–100), a letter grade, individual check results with pass/fail/warning status, and the top 3 prioritised roadblocks. Takes 30–90 seconds for larger sites.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'audit_dimension',
					'description' => 'Run the audit for a single dimension only. Useful for re-checking a specific area after making fixes without running the full scan.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'dimension' => array(
								'type'        => 'string',
								'enum'        => array( 'ux', 'accessibility', 'gdpr', 'web_standards', 'seo', 'ai_visibility', 'content_quality', 'commercial' ),
								'description' => 'The dimension to audit.',
							),
						),
						'required' => array( 'dimension' ),
					),
				),
			),

			// -----------------------------------------------------------------
			// History / trend tools
			// -----------------------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_audit_history',
					'description' => 'Retrieve stored audit history (up to 12 past reports). Returns date, overall score, grade, and per-dimension scores for each entry. Used to show score trends over time.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'save_audit_result',
					'description' => 'Save an audit result to the history store. Pass the full audit result object returned by run_full_audit. Keeps the last 12 results and rotates older ones out.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'result' => array(
								'type'        => 'object',
								'description' => 'The full audit result object from run_full_audit.',
							),
						),
						'required' => array( 'result' ),
					),
				),
			),

			// -----------------------------------------------------------------
			// Site data tools (read-only)
			// -----------------------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_site_overview',
					'description' => 'Get high-level site metadata: WordPress version, active theme, active plugins list, permalink structure, homepage URL, site title, tagline, total published post count, total page count, and whether search is discouraged.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_content_stats',
					'description' => 'Analyse all published posts and pages: total count, average word count, count of thin posts (under 300 words), freshness breakdown (posts by age: 0-3 months, 3-6 months, 6-12 months, 12+ months), most recent publish date, publishing cadence (average days between posts over the last 6 months), posts without featured images, posts without categories or tags.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_seo_stats',
					'description' => 'Scan all published posts and pages for SEO issues. Returns: count of pages missing title tags, count missing meta descriptions, count with duplicate titles, count with duplicate meta descriptions, count with no H1, count with multiple H1s, count with no internal links (orphaned), count of images missing alt text (site-wide total), and a list of pages with noindex set.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_accessibility_stats',
					'description' => 'Analyse accessibility issues across all published pages: total images missing alt text, count of alt attributes that are empty vs missing, heading hierarchy violations (pages with no H1, pages with multiple H1s, pages where H3 appears without H2), count of pages with generic link text ("click here", "read more", "here"), form elements without labels (based on post content scan).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_privacy_compliance_status',
					'description' => 'Check GDPR/privacy compliance signals: whether a privacy policy page exists and is linked, whether a terms of service page exists, whether the site is served over HTTPS, which known third-party script domains are detected in theme/plugin assets (Google Analytics, Facebook Pixel, Hotjar, Google Tag Manager, etc.), whether a cookie consent plugin is active, and whether consent-related text patterns appear in the privacy policy content.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_web_standards_status',
					'description' => 'Check web standards compliance: robots.txt presence and content, sitemap.xml presence and post coverage percentage, favicon presence, Open Graph meta tag coverage across pages, canonical tag coverage, mobile viewport meta tag on all pages, whether images use srcset for responsive sizing, and mixed-content (HTTP resources on HTTPS pages) indicators.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_ai_visibility_status',
					'description' => 'Check AI search visibility: which AI bots (GPTBot, ClaudeBot, ChatGPT-User, Google-Extended, PerplexityBot) are allowed or blocked in robots.txt, whether an llms.txt file exists, schema markup types present site-wide (Organization, Article, FAQPage, BreadcrumbList), FAQ-formatted content patterns in posts, and content freshness signal (most recent post date).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_commercial_signals',
					'description' => 'Check commercial conversion signals: presence of contact forms (CF7, Gravity Forms, WPForms, native), presence of email signup forms (Mailchimp, ConvertKit, Mailerlite, etc.), phone number presence on homepage and contact page, physical address presence, count of pages with no clear CTA text patterns ("contact us", "get started", "buy now", "book a call", "sign up", "get a quote"), count of testimonial/review elements detected, whether WooCommerce or Easy Digital Downloads is active.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			// -----------------------------------------------------------------
			// Admin notice
			// -----------------------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'post_admin_notice',
					'description' => 'Store an admin notice to be shown in the WordPress dashboard. Used by scheduled audits to alert the site owner about score changes.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'message' => array(
								'type'        => 'string',
								'description' => 'The notice message. Plain text or basic HTML.',
							),
							'type'    => array(
								'type'        => 'string',
								'enum'        => array( 'info', 'success', 'warning', 'error' ),
								'description' => 'Notice severity. Use success for improvements, warning for drops, info for stable.',
							),
						),
						'required' => array( 'message', 'type' ),
					),
				),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Tool execution
	// -------------------------------------------------------------------------

	public function execute_tool( string $tool_name, array $params ): ?array {
		switch ( $tool_name ) {
			case 'run_full_audit':
				return $this->run_full_audit();
			case 'audit_dimension':
				return $this->audit_dimension( $params['dimension'] ?? '' );
			case 'get_audit_history':
				return $this->get_audit_history();
			case 'save_audit_result':
				return $this->save_audit_result( $params['result'] ?? array() );
			case 'get_site_overview':
				return $this->get_site_overview();
			case 'get_content_stats':
				return $this->get_content_stats();
			case 'get_seo_stats':
				return $this->get_seo_stats();
			case 'get_accessibility_stats':
				return $this->get_accessibility_stats();
			case 'get_privacy_compliance_status':
				return $this->get_privacy_compliance_status();
			case 'get_web_standards_status':
				return $this->get_web_standards_status();
			case 'get_ai_visibility_status':
				return $this->get_ai_visibility_status();
			case 'get_commercial_signals':
				return $this->get_commercial_signals();
			case 'post_admin_notice':
				return $this->post_admin_notice( $params['message'] ?? '', $params['type'] ?? 'info' );
			default:
				return array( 'error' => "Unknown tool: $tool_name" );
		}
	}

	// =========================================================================
	// Tool implementations
	// =========================================================================

	// -------------------------------------------------------------------------
	// run_full_audit
	// -------------------------------------------------------------------------

	private function run_full_audit(): array {
		$site    = $this->get_site_overview();
		$content = $this->get_content_stats();
		$seo     = $this->get_seo_stats();
		$access  = $this->get_accessibility_stats();
		$privacy = $this->get_privacy_compliance_status();
		$web     = $this->get_web_standards_status();
		$ai      = $this->get_ai_visibility_status();
		$comm    = $this->get_commercial_signals();

		// Score each dimension
		$scores = array(
			'ux'             => $this->score_ux( $site, $seo, $web, $comm ),
			'accessibility'  => $this->score_accessibility( $access ),
			'gdpr'           => $this->score_gdpr( $privacy ),
			'web_standards'  => $this->score_web_standards( $web ),
			'seo'            => $this->score_seo( $seo ),
			'ai_visibility'  => $this->score_ai_visibility( $ai ),
			'content_quality'=> $this->score_content_quality( $content ),
			'commercial'     => $this->score_commercial( $comm, $seo ),
		);

		$overall = array_sum( array_column( $scores, 'score' ) );
		$grade   = $this->score_to_grade( $overall );

		// Collect all individual checks for roadblock ranking
		$all_checks = array();
		foreach ( $scores as $dim => $result ) {
			foreach ( $result['checks'] as $check ) {
				$check['dimension'] = $dim;
				$all_checks[]       = $check;
			}
		}

		// Top 3 roadblocks: failed/warning checks ranked by impact_score desc
		$roadblocks = array_filter( $all_checks, fn( $c ) => $c['status'] === 'fail' || $c['status'] === 'warn' );
		usort( $roadblocks, fn( $a, $b ) => ( $b['impact'] ?? 0 ) <=> ( $a['impact'] ?? 0 ) );
		$top3 = array_slice( array_values( $roadblocks ), 0, 3 );

		$result = array(
			'scanned_at'  => gmdate( 'Y-m-d H:i:s' ),
			'site_url'    => home_url(),
			'overall'     => $overall,
			'grade'       => $grade,
			'label'       => $this->grade_label( $grade ),
			'dimensions'  => $scores,
			'roadblocks'  => $top3,
			'raw'         => compact( 'site', 'content', 'seo', 'access', 'privacy', 'web', 'ai', 'comm' ),
		);

		// Auto-save
		$this->save_audit_result( $result );

		return $result;
	}

	// -------------------------------------------------------------------------
	// audit_dimension
	// -------------------------------------------------------------------------

	private function audit_dimension( string $dimension ): array {
		switch ( $dimension ) {
			case 'ux':
				$site = $this->get_site_overview();
				$seo  = $this->get_seo_stats();
				$web  = $this->get_web_standards_status();
				$comm = $this->get_commercial_signals();
				return $this->score_ux( $site, $seo, $web, $comm );
			case 'accessibility':
				return $this->score_accessibility( $this->get_accessibility_stats() );
			case 'gdpr':
				return $this->score_gdpr( $this->get_privacy_compliance_status() );
			case 'web_standards':
				return $this->score_web_standards( $this->get_web_standards_status() );
			case 'seo':
				return $this->score_seo( $this->get_seo_stats() );
			case 'ai_visibility':
				return $this->score_ai_visibility( $this->get_ai_visibility_status() );
			case 'content_quality':
				return $this->score_content_quality( $this->get_content_stats() );
			case 'commercial':
				$comm = $this->get_commercial_signals();
				$seo  = $this->get_seo_stats();
				return $this->score_commercial( $comm, $seo );
			default:
				return array( 'error' => 'Unknown dimension.' );
		}
	}

	// =========================================================================
	// Dimension scorers
	// The overall score weights per the brief:
	//   UX=15, Accessibility=15, GDPR=10, Web Standards=10,
	//   SEO=15, AI Visibility=10, Content Quality=15, Commercial=10
	// =========================================================================

	private function score_ux( array $site, array $seo, array $web, array $comm ): array {
		$checks = array();
		$score  = 0;

		// Navigation clarity (3 pts) — approximate from menu item count if available
		$menu_count = $site['menu_item_count'] ?? null;
		if ( $menu_count === null ) {
			$checks[] = $this->check( 'navigation_clarity', 'Navigation clarity', 'warn', 'Could not determine menu item count — review manually.', 1, 2 );
			$score   += 2;
		} elseif ( $menu_count <= 7 ) {
			$checks[] = $this->check( 'navigation_clarity', 'Navigation clarity', 'pass', "Menu has $menu_count items (ideal: 7 or fewer).", 0, 3 );
			$score   += 3;
		} else {
			$checks[] = $this->check( 'navigation_clarity', 'Navigation clarity', 'fail', "Menu has $menu_count items. More than 7 increases cognitive load. Group items into dropdowns.", 8, 0 );
		}

		// Mobile responsiveness (3 pts)
		if ( ! empty( $web['viewport_meta_present'] ) ) {
			$checks[] = $this->check( 'mobile_responsive', 'Mobile viewport meta', 'pass', 'Viewport meta tag is present.', 0, 3 );
			$score   += 3;
		} else {
			$checks[] = $this->check( 'mobile_responsive', 'Mobile viewport meta', 'fail', 'No viewport meta tag detected. Mobile layout will be broken.', 9, 0 );
		}

		// Page load / Core Web Vitals (3 pts) — proxy: check if caching plugin active
		$has_cache = $site['has_caching_plugin'] ?? false;
		if ( $has_cache ) {
			$checks[] = $this->check( 'page_load', 'Page performance', 'pass', 'A caching plugin is active — good signal for load speed.', 0, 3 );
			$score   += 3;
		} else {
			$checks[] = $this->check( 'page_load', 'Page performance', 'warn', 'No caching plugin detected. Install WP Super Cache, W3 Total Cache, or LiteSpeed Cache to improve load times.', 6, 1 );
			$score   += 1;
		}

		// CTA presence (3 pts) — from commercial signals
		$pages_without_cta = $comm['pages_without_cta_count'] ?? 0;
		$total_key_pages   = max( 1, $comm['key_page_count'] ?? 5 );
		$cta_coverage      = 1 - ( $pages_without_cta / $total_key_pages );
		if ( $cta_coverage >= 0.8 ) {
			$checks[] = $this->check( 'cta_presence', 'Call-to-action presence', 'pass', 'CTAs present on most key pages.', 0, 3 );
			$score   += 3;
		} elseif ( $cta_coverage >= 0.4 ) {
			$checks[] = $this->check( 'cta_presence', 'Call-to-action presence', 'warn', "{$pages_without_cta} key pages have no clear CTA. Add a next step for visitors.", 7, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'cta_presence', 'Call-to-action presence', 'fail', 'Most key pages lack a clear call-to-action. Visitors arrive and have no obvious next step.', 9, 0 );
		}

		// 404 / broken links (2 pts) — proxy: check for 404 page
		$has_404 = ! empty( $site['has_404_page'] );
		if ( $has_404 ) {
			$checks[] = $this->check( '404_page', '404 error page', 'pass', 'A custom 404 page is present.', 0, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( '404_page', '404 error page', 'warn', 'No custom 404 page found. A helpful 404 page retains visitors who hit dead links.', 3, 1 );
			$score   += 1;
		}

		// Search (1 pt) — WordPress native search enabled by default unless disabled
		$checks[] = $this->check( 'search', 'Site search', 'pass', 'WordPress native search is available.', 0, 1 );
		$score   += 1;

		return array( 'dimension' => 'ux', 'max' => 15, 'score' => min( $score, 15 ), 'checks' => $checks );
	}

	private function score_accessibility( array $access ): array {
		$checks = array();
		$score  = 0;

		// Alt text (3 pts)
		$missing_alt   = $access['images_missing_alt'] ?? 0;
		$total_images  = $access['total_images'] ?? 0;
		if ( $total_images === 0 || $missing_alt === 0 ) {
			$checks[] = $this->check( 'alt_text', 'Image alt text', 'pass', $total_images === 0 ? 'No images found.' : 'All images have alt text.', 0, 3 );
			$score   += 3;
		} elseif ( $missing_alt <= 5 ) {
			$checks[] = $this->check( 'alt_text', 'Image alt text', 'warn', "{$missing_alt} images are missing alt text.", 5, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'alt_text', 'Image alt text', 'fail', "{$missing_alt} images missing alt text. Hurts accessibility, SEO, and AI understanding.", 8, 0 );
		}

		// Heading hierarchy (2 pts)
		$h1_issues = ( $access['pages_no_h1'] ?? 0 ) + ( $access['pages_multiple_h1'] ?? 0 );
		if ( $h1_issues === 0 ) {
			$checks[] = $this->check( 'heading_hierarchy', 'Heading hierarchy', 'pass', 'All pages have a valid single H1.', 0, 2 );
			$score   += 2;
		} elseif ( $h1_issues <= 3 ) {
			$checks[] = $this->check( 'heading_hierarchy', 'Heading hierarchy', 'warn', "{$h1_issues} pages have H1 issues (missing or multiple).", 4, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'heading_hierarchy', 'Heading hierarchy', 'fail', "{$h1_issues} pages have invalid heading structure.", 6, 0 );
		}

		// Color contrast (3 pts) — cannot check from PHP without rendering; note as manual
		$checks[] = $this->check( 'color_contrast', 'Color contrast', 'warn', 'Color contrast cannot be checked automatically. Use https://webaim.org/resources/contrastchecker/ to verify text meets WCAG AA (4.5:1 ratio).', 5, 2 );
		$score   += 2;

		// Form labels (2 pts) — approximate from contact form plugin presence
		$has_form_plugin = $access['has_form_plugin'] ?? false;
		if ( $has_form_plugin ) {
			$checks[] = $this->check( 'form_labels', 'Form labels', 'pass', 'A form plugin is active. Verify form inputs have labels in your form settings.', 0, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'form_labels', 'Form labels', 'warn', 'No form plugin detected. If you have custom HTML forms, ensure all inputs have associated <label> elements.', 3, 1 );
			$score   += 1;
		}

		// Keyboard navigation (2 pts) — proxy: no known keyboard-trap plugins or themes
		$checks[] = $this->check( 'keyboard_nav', 'Keyboard navigation', 'warn', 'Keyboard accessibility requires manual testing. Use Tab to navigate your site and verify all interactive elements are reachable.', 4, 1 );
		$score   += 1;

		// ARIA / semantic HTML (2 pts) — approximated from theme structure
		$checks[] = $this->check( 'aria_semantic', 'Semantic HTML & ARIA', 'warn', 'ARIA compliance requires manual or automated testing (axe DevTools, WAVE). Consider running a free WAVE scan at https://wave.webaim.org/', 3, 1 );
		$score   += 1;

		// Link text quality (1 pt)
		$generic_links = $access['generic_link_count'] ?? 0;
		if ( $generic_links === 0 ) {
			$checks[] = $this->check( 'link_text', 'Link text quality', 'pass', 'No generic "click here" or "read more" link text detected.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'link_text', 'Link text quality', 'warn', "{$generic_links} instances of generic link text found. Replace with descriptive text.", 3, 0 );
		}

		return array( 'dimension' => 'accessibility', 'max' => 15, 'score' => min( $score, 15 ), 'checks' => $checks );
	}

	private function score_gdpr( array $privacy ): array {
		$checks = array();
		$score  = 0;

		// Cookie consent (3 pts)
		if ( ! empty( $privacy['has_cookie_consent_plugin'] ) ) {
			$checks[] = $this->check( 'cookie_consent', 'Cookie consent banner', 'pass', 'Cookie consent plugin detected: ' . $privacy['consent_plugin_name'], 0, 3 );
			$score   += 3;
		} else {
			$checks[] = $this->check( 'cookie_consent', 'Cookie consent banner', 'fail', 'No cookie consent plugin detected. Under GDPR, tracking cookies require explicit consent before firing.', 9, 0 );
		}

		// Privacy policy (2 pts)
		if ( ! empty( $privacy['has_privacy_policy'] ) ) {
			$checks[] = $this->check( 'privacy_policy', 'Privacy policy', 'pass', 'Privacy policy page exists: ' . $privacy['privacy_policy_url'], 0, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'privacy_policy', 'Privacy policy', 'fail', 'No privacy policy page found. This is required under GDPR, CCPA, and most privacy regulations.', 8, 0 );
		}

		// Third-party trackers (2 pts)
		$undisclosed = $privacy['undisclosed_trackers'] ?? array();
		if ( empty( $undisclosed ) ) {
			$checks[] = $this->check( 'trackers', 'Third-party trackers', 'pass', 'All detected third-party scripts appear to be disclosed in the privacy policy.', 0, 2 );
			$score   += 2;
		} else {
			$list     = implode( ', ', $undisclosed );
			$checks[] = $this->check( 'trackers', 'Third-party trackers', 'warn', "Trackers detected but possibly not disclosed: {$list}. Add them to your privacy policy.", 6, 1 );
			$score   += 1;
		}

		// Contact forms (1 pt)
		$checks[] = $this->check( 'form_consent', 'Form data consent', 'warn', 'Verify contact forms explain how submitted data is used and include a consent checkbox where required.', 3, 1 );
		$score   += 1;

		// HTTPS (1 pt)
		if ( is_ssl() || strpos( home_url(), 'https://' ) === 0 ) {
			$checks[] = $this->check( 'https', 'HTTPS / SSL', 'pass', 'Site is served over HTTPS.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'https', 'HTTPS / SSL', 'fail', 'Site is not using HTTPS. This is a trust, security, and GDPR compliance issue.', 9, 0 );
		}

		// Terms of service (1 pt)
		if ( ! empty( $privacy['has_terms_of_service'] ) ) {
			$checks[] = $this->check( 'terms', 'Terms of service', 'pass', 'Terms of service page found.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'terms', 'Terms of service', 'warn', 'No Terms of Service page found. Recommended for any site accepting registrations or purchases.', 4, 0 );
		}

		return array( 'dimension' => 'gdpr', 'max' => 10, 'score' => min( $score, 10 ), 'checks' => $checks );
	}

	private function score_web_standards( array $web ): array {
		$checks = array();
		$score  = 0;

		// HTML validity (2 pts) — approximate; cannot validate without external service
		$checks[] = $this->check( 'html_validity', 'HTML validity', 'warn', 'HTML validation requires an external validator (validator.w3.org). Run a check and fix critical errors.', 3, 1 );
		$score   += 1;

		// Viewport meta (1 pt) — already checked in UX; include here too
		if ( ! empty( $web['viewport_meta_present'] ) ) {
			$checks[] = $this->check( 'viewport', 'Mobile viewport meta', 'pass', 'Viewport meta tag present.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'viewport', 'Mobile viewport meta', 'fail', 'Missing viewport meta tag. Site will not render correctly on mobile.', 9, 0 );
		}

		// HTTPS (1 pt)
		if ( strpos( home_url(), 'https://' ) === 0 ) {
			$checks[] = $this->check( 'https_ws', 'HTTPS', 'pass', 'Site is served over HTTPS.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'https_ws', 'HTTPS', 'fail', 'Site is not on HTTPS.', 8, 0 );
		}

		// Canonical URLs (1 pt)
		$canonical_pct = $web['canonical_coverage_pct'] ?? 0;
		if ( $canonical_pct >= 90 ) {
			$checks[] = $this->check( 'canonical', 'Canonical URLs', 'pass', "Canonical tags present on {$canonical_pct}% of pages.", 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'canonical', 'Canonical URLs', 'warn', "Only {$canonical_pct}% of pages have canonical tags. Missing canonicals can cause duplicate content issues.", 5, 0 );
		}

		// Robots.txt + Sitemap (2 pts)
		$has_robots  = ! empty( $web['robots_txt_exists'] );
		$has_sitemap = ! empty( $web['sitemap_exists'] );
		if ( $has_robots && $has_sitemap ) {
			$sitemap_pct = $web['sitemap_coverage_pct'] ?? 0;
			$checks[]    = $this->check( 'robots_sitemap', 'robots.txt & sitemap', 'pass', "robots.txt and sitemap.xml both present. Sitemap covers {$sitemap_pct}% of published content.", 0, 2 );
			$score      += 2;
		} elseif ( $has_robots || $has_sitemap ) {
			$missing  = ! $has_robots ? 'robots.txt' : 'sitemap.xml';
			$checks[] = $this->check( 'robots_sitemap', 'robots.txt & sitemap', 'warn', "Missing: {$missing}. Both are recommended for crawler discovery.", 5, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'robots_sitemap', 'robots.txt & sitemap', 'fail', 'Both robots.txt and sitemap.xml are missing. Crawlers will struggle to discover your content.', 7, 0 );
		}

		// Open Graph (1 pt)
		$og_pct = $web['og_coverage_pct'] ?? 0;
		if ( $og_pct >= 80 ) {
			$checks[] = $this->check( 'open_graph', 'Open Graph meta', 'pass', "OG tags present on {$og_pct}% of pages.", 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'open_graph', 'Open Graph meta', 'warn', "Only {$og_pct}% of pages have Open Graph tags — social media previews will be broken.", 4, 0 );
		}

		// Favicon (1 pt)
		if ( ! empty( $web['favicon_present'] ) ) {
			$checks[] = $this->check( 'favicon', 'Favicon', 'pass', 'Favicon is present.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'favicon', 'Favicon', 'warn', 'No favicon detected. Every visit generates a 404 for the favicon and it looks unprofessional.', 2, 0 );
		}

		// Responsive images (1 pt)
		if ( ! empty( $web['uses_srcset'] ) ) {
			$checks[] = $this->check( 'responsive_images', 'Responsive images', 'pass', 'Images use srcset for responsive delivery.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'responsive_images', 'Responsive images', 'warn', 'Images may not be using srcset. Modern themes and WordPress core should handle this — verify your theme outputs responsive images.', 3, 0 );
		}

		return array( 'dimension' => 'web_standards', 'max' => 10, 'score' => min( $score, 10 ), 'checks' => $checks );
	}

	private function score_seo( array $seo ): array {
		$checks = array();
		$score  = 0;
		$total  = max( 1, $seo['total_pages'] ?? 1 );

		// Title tags (2 pts)
		$missing_titles = $seo['missing_titles'] ?? 0;
		if ( $missing_titles === 0 ) {
			$checks[] = $this->check( 'title_tags', 'Title tags', 'pass', 'All pages have title tags.', 0, 2 );
			$score   += 2;
		} else {
			$pct      = round( ( $missing_titles / $total ) * 100 );
			$checks[] = $this->check( 'title_tags', 'Title tags', 'fail', "{$missing_titles} pages ({$pct}%) are missing title tags.", 8, 0 );
		}

		// Meta descriptions (2 pts)
		$missing_meta = $seo['missing_meta_descriptions'] ?? 0;
		if ( $missing_meta === 0 ) {
			$checks[] = $this->check( 'meta_descriptions', 'Meta descriptions', 'pass', 'All pages have meta descriptions.', 0, 2 );
			$score   += 2;
		} elseif ( $missing_meta <= round( $total * 0.2 ) ) {
			$checks[] = $this->check( 'meta_descriptions', 'Meta descriptions', 'warn', "{$missing_meta} pages missing meta descriptions.", 5, 1 );
			$score   += 1;
		} else {
			$pct      = round( ( $missing_meta / $total ) * 100 );
			$checks[] = $this->check( 'meta_descriptions', 'Meta descriptions', 'fail', "{$missing_meta} pages ({$pct}%) missing meta descriptions.", 7, 0 );
		}

		// Heading structure (2 pts)
		$heading_issues = ( $seo['pages_no_h1'] ?? 0 ) + ( $seo['pages_multiple_h1'] ?? 0 );
		if ( $heading_issues === 0 ) {
			$checks[] = $this->check( 'heading_seo', 'Heading structure', 'pass', 'All pages have correct H1 structure.', 0, 2 );
			$score   += 2;
		} elseif ( $heading_issues <= 5 ) {
			$checks[] = $this->check( 'heading_seo', 'Heading structure', 'warn', "{$heading_issues} pages have H1 issues.", 4, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'heading_seo', 'Heading structure', 'fail', "{$heading_issues} pages have H1 issues (missing or duplicate).", 6, 0 );
		}

		// Internal linking (2 pts)
		$orphaned = $seo['orphaned_pages'] ?? 0;
		if ( $orphaned === 0 ) {
			$checks[] = $this->check( 'internal_links', 'Internal linking', 'pass', 'No orphaned pages detected.', 0, 2 );
			$score   += 2;
		} elseif ( $orphaned <= 5 ) {
			$checks[] = $this->check( 'internal_links', 'Internal linking', 'warn', "{$orphaned} pages have no internal links pointing to them.", 5, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'internal_links', 'Internal linking', 'fail', "{$orphaned} orphaned pages not linked from anywhere in the site.", 7, 0 );
		}

		// Schema markup (2 pts)
		$schema_types = $seo['schema_types_detected'] ?? array();
		if ( count( $schema_types ) >= 3 ) {
			$checks[] = $this->check( 'schema', 'Schema markup', 'pass', 'Rich schema detected: ' . implode( ', ', $schema_types ), 0, 2 );
			$score   += 2;
		} elseif ( ! empty( $schema_types ) ) {
			$checks[] = $this->check( 'schema', 'Schema markup', 'warn', 'Partial schema: ' . implode( ', ', $schema_types ) . '. Add Organization, FAQPage, or Article schema to improve AI and search understanding.', 5, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'schema', 'Schema markup', 'fail', 'No schema markup detected. Structured data helps search engines and AI platforms understand your content.', 7, 0 );
		}

		// Image optimization (2 pts) — alt text already scored; check filenames
		$bad_alt = $seo['images_missing_alt'] ?? 0;
		if ( $bad_alt === 0 ) {
			$checks[] = $this->check( 'image_seo', 'Image SEO', 'pass', 'All images have alt text.', 0, 2 );
			$score   += 2;
		} elseif ( $bad_alt <= 10 ) {
			$checks[] = $this->check( 'image_seo', 'Image SEO', 'warn', "{$bad_alt} images missing alt text — affects both accessibility and SEO.", 5, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'image_seo', 'Image SEO', 'fail', "{$bad_alt} images missing alt text — significant SEO and accessibility gap.", 7, 0 );
		}

		// URL structure (1 pt) — check if pretty permalinks enabled
		$permalink = $seo['permalink_structure'] ?? '';
		if ( $permalink && $permalink !== 'default' ) {
			$checks[] = $this->check( 'url_structure', 'URL structure', 'pass', "Pretty permalinks active: {$permalink}", 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'url_structure', 'URL structure', 'fail', 'Default (ugly) permalinks are enabled. Set a custom permalink structure in Settings → Permalinks.', 7, 0 );
		}

		// Indexability (2 pts)
		$noindex_count = $seo['noindex_page_count'] ?? 0;
		$discourage    = $seo['search_discouraged'] ?? false;
		if ( $discourage ) {
			$checks[] = $this->check( 'indexability', 'Indexability', 'fail', '"Discourage search engines" is enabled in Settings → Reading. Your entire site is being told not to index.', 10, 0 );
		} elseif ( $noindex_count > round( $total * 0.3 ) ) {
			$checks[] = $this->check( 'indexability', 'Indexability', 'warn', "{$noindex_count} pages are set to noindex. Verify this is intentional.", 5, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'indexability', 'Indexability', 'pass', "Site is indexable. {$noindex_count} pages deliberately noindexed.", 0, 2 );
			$score   += 2;
		}

		return array( 'dimension' => 'seo', 'max' => 15, 'score' => min( $score, 15 ), 'checks' => $checks );
	}

	private function score_ai_visibility( array $ai ): array {
		$checks = array();
		$score  = 0;

		// AI crawler access (4 pts)
		$bots_allowed  = $ai['bots_allowed'] ?? array();
		$bots_blocked  = $ai['bots_blocked'] ?? array();
		$blanket_block = $ai['blanket_block'] ?? false;

		if ( $blanket_block ) {
			$checks[] = $this->check( 'ai_crawlers', 'AI crawler access', 'fail', 'Blanket Disallow detected in robots.txt — ALL bots including AI crawlers are blocked.', 10, 0 );
		} elseif ( in_array( 'GPTBot', $bots_blocked ) || in_array( 'ClaudeBot', $bots_blocked ) ) {
			$list     = implode( ', ', $bots_blocked );
			$checks[] = $this->check( 'ai_crawlers', 'AI crawler access', 'fail', "Major AI crawlers blocked: {$list}. These AI assistants cannot see or cite your site.", 9, 0 );
		} elseif ( empty( $bots_blocked ) ) {
			$checks[] = $this->check( 'ai_crawlers', 'AI crawler access', 'pass', 'All AI crawlers appear to have access.', 0, 4 );
			$score   += 4;
		} else {
			$list     = implode( ', ', $bots_blocked );
			$checks[] = $this->check( 'ai_crawlers', 'AI crawler access', 'warn', "Some AI crawlers blocked: {$list}. Consider allowing these for AI search visibility.", 6, 2 );
			$score   += 2;
		}

		// Schema for AI (2 pts)
		$schema_types = $ai['schema_types'] ?? array();
		$has_org      = in_array( 'Organization', $schema_types ) || in_array( 'LocalBusiness', $schema_types );
		$has_faq      = in_array( 'FAQPage', $schema_types );
		if ( $has_org && $has_faq ) {
			$checks[] = $this->check( 'ai_schema', 'Schema for AI extraction', 'pass', 'Organization/LocalBusiness and FAQPage schema detected — excellent for AI citation.', 0, 2 );
			$score   += 2;
		} elseif ( $has_org || $has_faq ) {
			$checks[] = $this->check( 'ai_schema', 'Schema for AI extraction', 'warn', 'Partial AI schema. ' . ( ! $has_faq ? 'Add FAQPage schema — it\'s the highest-ROI schema for AI citations.' : 'Add Organization schema to establish entity identity.' ), 5, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'ai_schema', 'Schema for AI extraction', 'fail', 'No Organization or FAQPage schema detected. AI platforms cannot identify your entity or cite your Q&A content.', 7, 0 );
		}

		// Content extractability (2 pts)
		$has_faq_content = $ai['has_faq_formatted_content'] ?? false;
		if ( $has_faq_content ) {
			$checks[] = $this->check( 'ai_content', 'Content extractability', 'pass', 'FAQ-formatted content detected — optimised for direct AI quote extraction.', 0, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'ai_content', 'Content extractability', 'warn', 'No FAQ-formatted content detected. Q&A patterns and direct answers make content more quotable by AI.', 5, 1 );
			$score   += 1;
		}

		// Freshness (1 pt)
		$days_since_last = $ai['days_since_last_post'] ?? 999;
		if ( $days_since_last <= 30 ) {
			$checks[] = $this->check( 'ai_freshness', 'Content freshness', 'pass', 'Content published or updated within the last 30 days.', 0, 1 );
			$score   += 1;
		} elseif ( $days_since_last <= 90 ) {
			$checks[] = $this->check( 'ai_freshness', 'Content freshness', 'warn', "Last content update was {$days_since_last} days ago. Freshness signals help with AI recommendation priority.", 4, 0 );
		} else {
			$checks[] = $this->check( 'ai_freshness', 'Content freshness', 'fail', "No content in {$days_since_last} days. Stale sites are deprioritised by AI search engines.", 6, 0 );
		}

		// llms.txt (1 pt bonus)
		if ( ! empty( $ai['has_llms_txt'] ) ) {
			$checks[] = $this->check( 'llms_txt', 'llms.txt', 'pass', 'llms.txt present — you\'re ahead of the curve on this emerging standard.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'llms_txt', 'llms.txt', 'warn', 'No llms.txt file. This emerging standard helps AI assistants understand your site content. Low effort to add.', 2, 0 );
		}

		return array( 'dimension' => 'ai_visibility', 'max' => 10, 'score' => min( $score, 10 ), 'checks' => $checks );
	}

	private function score_content_quality( array $content ): array {
		$checks = array();
		$score  = 0;

		// Freshness (3 pts)
		$stale_pct = $content['pct_older_than_12_months'] ?? 0;
		if ( $stale_pct < 30 ) {
			$checks[] = $this->check( 'freshness', 'Content freshness', 'pass', "Only {$stale_pct}% of content is older than 12 months.", 0, 3 );
			$score   += 3;
		} elseif ( $stale_pct < 60 ) {
			$checks[] = $this->check( 'freshness', 'Content freshness', 'warn', "{$stale_pct}% of content is older than 12 months. Consider refreshing key posts.", 5, 2 );
			$score   += 2;
		} else {
			$days = $content['days_since_last_post'] ?? '?';
			$checks[] = $this->check( 'freshness', 'Content freshness', 'fail', "{$stale_pct}% of content is over 12 months old. Last post: {$days} days ago. A stale site signals to Google and AI that it may no longer be authoritative.", 8, 0 );
		}

		// Content depth (2 pts)
		$thin_count = $content['thin_post_count'] ?? 0;
		$total      = max( 1, $content['total_posts'] ?? 1 );
		if ( $thin_count === 0 ) {
			$checks[] = $this->check( 'content_depth', 'Content depth', 'pass', 'No thin content pages detected.', 0, 2 );
			$score   += 2;
		} elseif ( $thin_count <= round( $total * 0.1 ) ) {
			$checks[] = $this->check( 'content_depth', 'Content depth', 'warn', "{$thin_count} pages with fewer than 300 words. Consider expanding or merging them.", 4, 1 );
			$score   += 1;
		} else {
			$pct      = round( ( $thin_count / $total ) * 100 );
			$checks[] = $this->check( 'content_depth', 'Content depth', 'fail', "{$thin_count} ({$pct}%) pages are thin (<300 words). Thin content is penalised by search engines.", 7, 0 );
		}

		// Coverage (2 pts)
		$empty_cats = $content['empty_categories'] ?? 0;
		if ( $empty_cats === 0 ) {
			$checks[] = $this->check( 'coverage', 'Content coverage', 'pass', 'All categories have content.', 0, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'coverage', 'Content coverage', 'warn', "{$empty_cats} empty categories. Empty categories create dead-end pages — delete or fill them.", 3, 1 );
			$score   += 1;
		}

		// Duplicate content (2 pts)
		$dupes = $content['potential_duplicate_count'] ?? 0;
		if ( $dupes === 0 ) {
			$checks[] = $this->check( 'duplicates', 'Duplicate content', 'pass', 'No obvious duplicate posts detected.', 0, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'duplicates', 'Duplicate content', 'warn', "{$dupes} potentially similar posts detected. Duplicate content dilutes SEO authority.", 5, 1 );
			$score   += 1;
		}

		// Media richness (2 pts)
		$text_only_pct = $content['text_only_post_pct'] ?? 0;
		if ( $text_only_pct < 20 ) {
			$checks[] = $this->check( 'media', 'Media richness', 'pass', "Only {$text_only_pct}% of posts are text-only.", 0, 2 );
			$score   += 2;
		} elseif ( $text_only_pct < 50 ) {
			$checks[] = $this->check( 'media', 'Media richness', 'warn', "{$text_only_pct}% of posts have no images or media. Visual content improves engagement and dwell time.", 3, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'media', 'Media richness', 'fail', "{$text_only_pct}% of posts are text-only. Add images, videos, or charts to key posts.", 5, 0 );
		}

		// Readability (2 pts) — approximate from avg word count
		$avg_words = $content['avg_word_count'] ?? 0;
		if ( $avg_words >= 400 ) {
			$checks[] = $this->check( 'readability', 'Content depth & readability', 'pass', "Average post length: {$avg_words} words — good depth.", 0, 2 );
			$score   += 2;
		} elseif ( $avg_words >= 200 ) {
			$checks[] = $this->check( 'readability', 'Content depth & readability', 'warn', "Average post length: {$avg_words} words. Aim for 600+ words on key posts for better SEO value.", 3, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'readability', 'Content depth & readability', 'fail', "Average post length: {$avg_words} words. Very thin. Most posts need significant expansion.", 6, 0 );
		}

		// Publishing consistency (2 pts)
		$avg_days_between = $content['avg_days_between_posts'] ?? null;
		if ( $avg_days_between === null || $content['total_posts'] < 5 ) {
			$checks[] = $this->check( 'cadence', 'Publishing cadence', 'warn', 'Not enough posts to assess publishing cadence. Aim for regular publishing.', 2, 1 );
			$score   += 1;
		} elseif ( $avg_days_between <= 14 ) {
			$checks[] = $this->check( 'cadence', 'Publishing cadence', 'pass', "Publishing roughly every {$avg_days_between} days — consistent.", 0, 2 );
			$score   += 2;
		} elseif ( $avg_days_between <= 60 ) {
			$checks[] = $this->check( 'cadence', 'Publishing cadence', 'warn', "Average {$avg_days_between} days between posts. Monthly publishing is the minimum for maintaining freshness signals.", 3, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'cadence', 'Publishing cadence', 'fail', "Very irregular publishing: average {$avg_days_between} days between posts. Inconsistent publishing hurts authority and freshness signals.", 6, 0 );
		}

		return array( 'dimension' => 'content_quality', 'max' => 15, 'score' => min( $score, 15 ), 'checks' => $checks );
	}

	private function score_commercial( array $comm, array $seo ): array {
		$checks = array();
		$score  = 0;

		// Conversion points (3 pts)
		$has_contact_form = ! empty( $comm['has_contact_form'] );
		$has_ecomm        = ! empty( $comm['has_ecommerce'] );
		$has_booking      = ! empty( $comm['has_booking_plugin'] );
		$has_any_conv     = $has_contact_form || $has_ecomm || $has_booking;

		if ( $has_ecomm ) {
			$checks[] = $this->check( 'conversion', 'Conversion mechanism', 'pass', 'E-commerce is active — primary conversion mechanism in place.', 0, 3 );
			$score   += 3;
		} elseif ( $has_contact_form ) {
			$checks[] = $this->check( 'conversion', 'Conversion mechanism', 'pass', 'Contact form detected — primary conversion mechanism in place.', 0, 3 );
			$score   += 3;
		} elseif ( ! empty( $comm['phone_number_present'] ) || ! empty( $comm['email_address_present'] ) ) {
			$checks[] = $this->check( 'conversion', 'Conversion mechanism', 'warn', 'Contact info found but no form or checkout detected. A contact form converts 2–3× better than a phone number alone.', 5, 2 );
			$score   += 2;
		} else {
			$checks[] = $this->check( 'conversion', 'Conversion mechanism', 'fail', 'No conversion mechanism detected — no contact form, no checkout, no phone/email visible. Visitors have no way to take action.', 10, 0 );
		}

		// CTA placement (2 pts)
		$pages_no_cta = $comm['pages_without_cta_count'] ?? 0;
		$key_pages    = max( 1, $comm['key_page_count'] ?? 5 );
		$cta_pct      = round( ( 1 - ( $pages_no_cta / $key_pages ) ) * 100 );
		if ( $cta_pct >= 80 ) {
			$checks[] = $this->check( 'cta_placement', 'CTA placement', 'pass', "CTAs present on {$cta_pct}% of key pages.", 0, 2 );
			$score   += 2;
		} elseif ( $cta_pct >= 50 ) {
			$checks[] = $this->check( 'cta_placement', 'CTA placement', 'warn', "Only {$cta_pct}% of key pages have a CTA. Add clear next steps to every key page.", 6, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'cta_placement', 'CTA placement', 'fail', "Only {$cta_pct}% of key pages have a CTA. Most visitors arrive and have no obvious next step.", 8, 0 );
		}

		// Contact information (2 pts)
		$has_contact_page  = ! empty( $comm['has_contact_page'] );
		$has_contact_info  = ! empty( $comm['phone_number_present'] ) || ! empty( $comm['email_address_present'] );
		if ( $has_contact_page && $has_contact_info ) {
			$checks[] = $this->check( 'contact_info', 'Contact information', 'pass', 'Contact page exists and contact info is present.', 0, 2 );
			$score   += 2;
		} elseif ( $has_contact_page || $has_contact_info ) {
			$checks[] = $this->check( 'contact_info', 'Contact information', 'warn', 'Partial contact info. Ensure both a contact page and visible contact details (phone/email) are present.', 4, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'contact_info', 'Contact information', 'fail', 'No contact page or contact info detected. Visitors can\'t reach you.', 8, 0 );
		}

		// Trust signals (2 pts)
		$trust_score = $comm['trust_signal_score'] ?? 0; // 0-3 based on testimonials, reviews, logos
		if ( $trust_score >= 2 ) {
			$checks[] = $this->check( 'trust', 'Trust signals', 'pass', 'Trust signals detected (testimonials, reviews, or certifications).', 0, 2 );
			$score   += 2;
		} elseif ( $trust_score === 1 ) {
			$checks[] = $this->check( 'trust', 'Trust signals', 'warn', 'Limited trust signals. Add testimonials, reviews, or partner logos to reduce purchase anxiety.', 6, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'trust', 'Trust signals', 'fail', 'No trust signals detected. Testimonials, reviews, and certifications measurably improve conversion rates.', 7, 0 );
		}

		// Lead capture (1 pt)
		$has_email_signup = ! empty( $comm['has_email_signup'] );
		if ( $has_email_signup ) {
			$checks[] = $this->check( 'lead_capture', 'Lead capture', 'pass', 'Email signup / newsletter form detected.', 0, 1 );
			$score   += 1;
		} else {
			$checks[] = $this->check( 'lead_capture', 'Lead capture', 'warn', 'No email signup form detected. Even visitors who don\'t convert today can be captured for future marketing.', 4, 0 );
		}

		return array( 'dimension' => 'commercial', 'max' => 10, 'score' => min( $score, 10 ), 'checks' => $checks );
	}

	// =========================================================================
	// Data collection methods
	// =========================================================================

	private function get_site_overview(): array {
		global $wpdb;

		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Detect caching plugins
		$cache_plugins    = array( 'wp-super-cache', 'w3-total-cache', 'litespeed-cache', 'wp-rocket', 'wp-fastest-cache', 'comet-cache' );
		$has_cache_plugin = false;
		foreach ( $active_plugins as $plugin ) {
			foreach ( $cache_plugins as $cp ) {
				if ( str_contains( $plugin, $cp ) ) {
					$has_cache_plugin = true;
					break 2;
				}
			}
		}

		// Check for a custom 404 page
		$has_404 = (bool) get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'meta_key' => '_wp_page_template', 'meta_value' => '404', 'posts_per_page' => 1 ) );
		// Fallback: look for a page titled 404
		if ( ! $has_404 ) {
			$has_404 = (bool) get_page_by_title( '404' );
		}

		// Primary nav menu item count
		$menus      = wp_get_nav_menus();
		$menu_count = null;
		if ( ! empty( $menus ) ) {
			$items      = wp_get_nav_menu_items( $menus[0]->term_id );
			$menu_count = $items ? count( array_filter( $items, fn( $i ) => ! $i->menu_item_parent ) ) : null;
		}

		$permalink_struct = get_option( 'permalink_structure' );
		$permalink_name   = match ( true ) {
			empty( $permalink_struct )               => 'default',
			$permalink_struct === '/%postname%/'     => 'plain',
			str_contains( $permalink_struct, 'date' ) => 'date-based',
			default                                  => $permalink_struct,
		};

		return array(
			'site_url'           => home_url(),
			'site_title'         => get_bloginfo( 'name' ),
			'tagline'            => get_bloginfo( 'description' ),
			'wp_version'         => get_bloginfo( 'version' ),
			'active_theme'       => wp_get_theme()->get( 'Name' ),
			'active_plugin_count'=> count( $active_plugins ),
			'permalink_structure'=> $permalink_name,
			'has_caching_plugin' => $has_cache_plugin,
			'has_404_page'       => $has_404,
			'menu_item_count'    => $menu_count,
			'search_discouraged' => (bool) get_option( 'blog_public' ) === false,
		);
	}

	private function get_content_stats(): array {
		global $wpdb;

		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		$total = count( $posts );
		if ( $total === 0 ) {
			return array( 'total_posts' => 0, 'message' => 'No published posts or pages found.' );
		}

		$word_counts        = array();
		$dates              = array();
		$thin_count         = 0;
		$text_only_count    = 0;
		$now                = time();
		$age_buckets        = array( '0_3' => 0, '3_6' => 0, '6_12' => 0, '12_plus' => 0 );

		foreach ( $posts as $id ) {
			$post      = get_post( $id );
			$content   = wp_strip_all_tags( $post->post_content );
			$words     = str_word_count( $content );
			$word_counts[] = $words;
			if ( $words < 300 ) {
				$thin_count++;
			}
			// Media: look for img tags or attachment links
			if ( ! preg_match( '/<img|<video|\[gallery|\[video/', $post->post_content ) ) {
				$has_featured = has_post_thumbnail( $id );
				if ( ! $has_featured ) {
					$text_only_count++;
				}
			}
			$pub_ts = strtotime( $post->post_date );
			$dates[] = $pub_ts;
			$age_days = ( $now - $pub_ts ) / DAY_IN_SECONDS;
			if ( $age_days < 90 )       $age_buckets['0_3']++;
			elseif ( $age_days < 180 )  $age_buckets['3_6']++;
			elseif ( $age_days < 365 )  $age_buckets['6_12']++;
			else                         $age_buckets['12_plus']++;
		}

		$avg_words           = $total > 0 ? round( array_sum( $word_counts ) / $total ) : 0;
		$pct_older_12        = $total > 0 ? round( ( $age_buckets['12_plus'] / $total ) * 100 ) : 0;
		$days_since_last     = $dates ? round( ( $now - max( $dates ) ) / DAY_IN_SECONDS ) : null;
		$text_only_pct       = $total > 0 ? round( ( $text_only_count / $total ) * 100 ) : 0;

		// Publishing cadence (posts only, last 6 months)
		$recent_posts = array_filter( $dates, fn( $d ) => ( $now - $d ) < ( 180 * DAY_IN_SECONDS ) );
		sort( $recent_posts );
		$avg_gap = null;
		if ( count( $recent_posts ) >= 2 ) {
			$gaps    = array();
			for ( $i = 1; $i < count( $recent_posts ); $i++ ) {
				$gaps[] = ( $recent_posts[ $i ] - $recent_posts[ $i - 1 ] ) / DAY_IN_SECONDS;
			}
			$avg_gap = round( array_sum( $gaps ) / count( $gaps ) );
		}

		// Empty categories
		$empty_cats = 0;
		$cats       = get_categories( array( 'hide_empty' => false ) );
		foreach ( $cats as $cat ) {
			if ( $cat->count === 0 ) {
				$empty_cats++;
			}
		}

		return array(
			'total_posts'              => $total,
			'avg_word_count'           => $avg_words,
			'thin_post_count'          => $thin_count,
			'text_only_post_pct'       => $text_only_pct,
			'age_buckets'              => $age_buckets,
			'pct_older_than_12_months' => $pct_older_12,
			'days_since_last_post'     => $days_since_last,
			'avg_days_between_posts'   => $avg_gap,
			'empty_categories'         => $empty_cats,
			'potential_duplicate_count'=> 0, // basic implementation — full similarity check is resource-intensive
		);
	}

	private function get_seo_stats(): array {
		global $wpdb;

		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );
		$total = count( $posts );

		$missing_titles        = 0;
		$missing_meta          = 0;
		$pages_no_h1           = 0;
		$pages_multiple_h1     = 0;
		$orphaned_count        = 0;
		$images_missing_alt    = 0;
		$noindex_count         = 0;
		$all_titles            = array();
		$all_slugs             = array();

		// Detect SEO plugin
		$active_plugins   = (array) get_option( 'active_plugins', array() );
		$seo_plugin       = 'none';
		$schema_types     = array();
		foreach ( $active_plugins as $p ) {
			if ( str_contains( $p, 'seo-by-rank-math' ) || str_contains( $p, 'rank-math' ) ) {
				$seo_plugin = 'rankmath';
			} elseif ( str_contains( $p, 'wordpress-seo' ) || str_contains( $p, 'wpseo' ) ) {
				$seo_plugin = 'yoast';
			}
		}

		// Check schema from options
		$rank_math_schema = get_option( 'rank_math_schema_Organization' ) ?: get_option( 'rank_math_organization' );
		if ( $rank_math_schema ) {
			$schema_types[] = 'Organization';
		}

		// Build a simple "who links to who" map for orphan detection
		$internal_link_targets = array();
		$post_ids              = wp_list_pluck( $posts, 'ID' );

		foreach ( $posts as $post ) {
			// Title
			switch ( $seo_plugin ) {
				case 'rankmath':
					$title = get_post_meta( $post->ID, 'rank_math_title', true );
					break;
				case 'yoast':
					$title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
					break;
				default:
					$title = $post->post_title;
			}
			if ( empty( $title ) ) {
				$missing_titles++;
			}
			$all_titles[] = $title;

			// Meta description
			switch ( $seo_plugin ) {
				case 'rankmath':
					$meta = get_post_meta( $post->ID, 'rank_math_description', true );
					break;
				case 'yoast':
					$meta = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
					break;
				default:
					$meta = $post->post_excerpt;
			}
			if ( empty( $meta ) ) {
				$missing_meta++;
			}

			// noindex
			switch ( $seo_plugin ) {
				case 'rankmath':
					$robots = get_post_meta( $post->ID, 'rank_math_robots', true );
					if ( $robots && in_array( 'noindex', (array) $robots ) ) {
						$noindex_count++;
					}
					break;
				case 'yoast':
					$noindex = get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true );
					if ( $noindex == '1' ) {
						$noindex_count++;
					}
					break;
			}

			// Heading H1 count
			preg_match_all( '/<h1[^>]*>/i', $post->post_content, $h1_matches );
			$h1_count = count( $h1_matches[0] );
			if ( $h1_count === 0 ) {
				$pages_no_h1++;
			} elseif ( $h1_count > 1 ) {
				$pages_multiple_h1++;
			}

			// Images missing alt
			preg_match_all( '/<img[^>]+>/i', $post->post_content, $img_matches );
			foreach ( $img_matches[0] as $img ) {
				if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img ) ) {
					$images_missing_alt++;
				}
			}

			// Internal links for orphan detection
			preg_match_all( '/href=["\']' . preg_quote( home_url(), '/' ) . '[^"\']*["\']/', $post->post_content, $link_matches );
			foreach ( $link_matches[0] as $lm ) {
				preg_match( '/href=["\']([^"\']+)["\']/', $lm, $href );
				if ( ! empty( $href[1] ) ) {
					$internal_link_targets[] = $href[1];
				}
			}
		}

		// Orphan count: pages with no internal links pointing to them
		foreach ( $posts as $post ) {
			$url         = get_permalink( $post->ID );
			$is_linked   = false;
			foreach ( $internal_link_targets as $target ) {
				if ( str_contains( $target, $post->post_name ) ) {
					$is_linked = true;
					break;
				}
			}
			if ( ! $is_linked && $post->ID !== intval( get_option( 'page_on_front' ) ) ) {
				$orphaned_count++;
			}
		}

		// Schema types from active SEO plugin options
		if ( $seo_plugin === 'rankmath' ) {
			$schema_types[] = 'managed-by-rankmath';
		}

		return array(
			'total_pages'            => $total,
			'missing_titles'         => $missing_titles,
			'missing_meta_descriptions' => $missing_meta,
			'pages_no_h1'            => $pages_no_h1,
			'pages_multiple_h1'      => $pages_multiple_h1,
			'orphaned_pages'         => $orphaned_count,
			'images_missing_alt'     => $images_missing_alt,
			'noindex_page_count'     => $noindex_count,
			'search_discouraged'     => ! (bool) get_option( 'blog_public', 1 ),
			'permalink_structure'    => get_option( 'permalink_structure' ) ?: 'default',
			'seo_plugin'             => $seo_plugin,
			'schema_types_detected'  => $schema_types,
		);
	}

	private function get_accessibility_stats(): array {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );

		$total_images      = 0;
		$missing_alt       = 0;
		$empty_alt         = 0;
		$pages_no_h1       = 0;
		$pages_multiple_h1 = 0;
		$generic_links     = 0;

		$generic_patterns = array( 'click here', 'read more', 'here', 'learn more', 'more info', 'more information' );

		foreach ( $posts as $post ) {
			preg_match_all( '/<img[^>]+>/i', $post->post_content, $img_matches );
			foreach ( $img_matches[0] as $img ) {
				$total_images++;
				if ( preg_match( '/alt=["\']["\']/', $img ) ) {
					$empty_alt++;
				} elseif ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img ) ) {
					$missing_alt++;
				}
			}

			preg_match_all( '/<h1[^>]*>/i', $post->post_content, $h1m );
			$h1c = count( $h1m[0] );
			if ( $h1c === 0 ) $pages_no_h1++;
			if ( $h1c > 1 )   $pages_multiple_h1++;

			foreach ( $generic_patterns as $pattern ) {
				$generic_links += substr_count( strtolower( $post->post_content ), ">$pattern<" );
			}
		}

		$active_plugins    = (array) get_option( 'active_plugins', array() );
		$form_plugins      = array( 'contact-form-7', 'gravityforms', 'ninja-forms', 'wpforms' );
		$has_form_plugin   = false;
		foreach ( $active_plugins as $p ) {
			foreach ( $form_plugins as $fp ) {
				if ( str_contains( $p, $fp ) ) { $has_form_plugin = true; break 2; }
			}
		}

		return array(
			'total_images'      => $total_images,
			'images_missing_alt'=> $missing_alt,
			'images_empty_alt'  => $empty_alt,
			'pages_no_h1'       => $pages_no_h1,
			'pages_multiple_h1' => $pages_multiple_h1,
			'generic_link_count'=> $generic_links,
			'has_form_plugin'   => $has_form_plugin,
		);
	}

	private function get_privacy_compliance_status(): array {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Consent plugins
		$consent_plugins = array(
			'cookie-law-info'           => 'Cookie Law Info',
			'gdpr-cookie-consent'       => 'GDPR Cookie Consent',
			'cookiebot'                 => 'Cookiebot',
			'cookie-notice'             => 'Cookie Notice',
			'complianz'                 => 'Complianz',
			'uk-cookie-consent'         => 'UK Cookie Consent',
			'cookieyyes'                => 'CookieYes',
			'wp-gdpr-compliance'        => 'WP GDPR Compliance',
			'weforms'                   => 'weForms',
		);
		$has_consent_plugin  = false;
		$consent_plugin_name = '';
		foreach ( $active_plugins as $p ) {
			foreach ( $consent_plugins as $slug => $name ) {
				if ( str_contains( $p, $slug ) ) {
					$has_consent_plugin  = true;
					$consent_plugin_name = $name;
					break 2;
				}
			}
		}

		// Privacy policy page
		$privacy_page_id  = (int) get_option( 'wp_page_for_privacy_policy' );
		$has_privacy      = $privacy_page_id && get_post_status( $privacy_page_id ) === 'publish';
		$privacy_url      = $has_privacy ? get_permalink( $privacy_page_id ) : null;

		// Terms page (look by common slugs/titles)
		$terms_page  = get_page_by_path( 'terms' ) ?: get_page_by_path( 'terms-of-service' ) ?: get_page_by_path( 'terms-and-conditions' );
		$has_terms   = $terms_page && $terms_page->post_status === 'publish';

		// Known tracker script domains
		$known_trackers = array(
			'google-analytics.com'  => 'Google Analytics',
			'googletagmanager.com'  => 'Google Tag Manager',
			'connect.facebook.net'  => 'Facebook Pixel',
			'static.hotjar.com'     => 'Hotjar',
			'script.hotjar.com'     => 'Hotjar',
			'cdn.mouseflow.com'     => 'Mouseflow',
			'static.clarity.ms'     => 'Microsoft Clarity',
		);
		$detected_trackers = array();
		// Scan theme header.php for tracker domains
		$header_file = get_template_directory() . '/header.php';
		$header_html = file_exists( $header_file ) ? file_get_contents( $header_file ) : '';
		foreach ( $known_trackers as $domain => $tracker_name ) {
			if ( str_contains( $header_html, $domain ) ) {
				$detected_trackers[] = $tracker_name;
			}
		}

		// Compare to privacy policy content
		$privacy_content   = $has_privacy ? strtolower( get_post_field( 'post_content', $privacy_page_id ) ) : '';
		$undisclosed       = array();
		foreach ( $detected_trackers as $tracker ) {
			if ( ! str_contains( $privacy_content, strtolower( $tracker ) ) && ! str_contains( $privacy_content, strtolower( explode( ' ', $tracker )[0] ) ) ) {
				$undisclosed[] = $tracker;
			}
		}

		return array(
			'has_cookie_consent_plugin' => $has_consent_plugin,
			'consent_plugin_name'       => $consent_plugin_name,
			'has_privacy_policy'        => $has_privacy,
			'privacy_policy_url'        => $privacy_url,
			'has_terms_of_service'      => $has_terms,
			'detected_trackers'         => $detected_trackers,
			'undisclosed_trackers'      => $undisclosed,
		);
	}

	private function get_web_standards_status(): array {
		global $wpdb;

		$home = home_url();

		// robots.txt
		$robots_path  = ABSPATH . 'robots.txt';
		$has_robots   = file_exists( $robots_path );
		$robots_txt   = $has_robots ? file_get_contents( $robots_path ) : '';

		// sitemap
		$sitemap_url     = home_url( '/sitemap.xml' );
		$sitemap_index   = home_url( '/sitemap_index.xml' );
		$has_sitemap     = false;
		$sitemap_pct     = 0;
		$sm_response     = @wp_safe_remote_get( $sitemap_url, array( 'timeout' => 5, 'sslverify' => false ) );
		if ( is_wp_error( $sm_response ) || wp_remote_retrieve_response_code( $sm_response ) !== 200 ) {
			$sm_response = @wp_safe_remote_get( $sitemap_index, array( 'timeout' => 5, 'sslverify' => false ) );
		}
		if ( ! is_wp_error( $sm_response ) && wp_remote_retrieve_response_code( $sm_response ) === 200 ) {
			$has_sitemap = true;
			// Rough coverage: count URLs in sitemap vs published posts
			$sm_body     = wp_remote_retrieve_body( $sm_response );
			$url_count   = substr_count( $sm_body, '<url>' ) + substr_count( $sm_body, '<sitemap>' );
			$post_count  = wp_count_posts( 'post' )->publish + wp_count_posts( 'page' )->publish;
			$sitemap_pct = $post_count > 0 ? min( 100, round( ( $url_count / $post_count ) * 100 ) ) : 100;
		}

		// Favicon
		$favicon  = get_site_icon_url();
		$has_fav  = ! empty( $favicon );

		// viewport meta and OG — approximate via home page content
		$home_response  = @wp_safe_remote_get( $home, array( 'timeout' => 8, 'sslverify' => false ) );
		$home_html      = ! is_wp_error( $home_response ) ? wp_remote_retrieve_body( $home_response ) : '';

		$viewport_meta  = (bool) preg_match( '/<meta[^>]+name=["\']viewport["\'][^>]*>/i', $home_html );
		$has_og_home    = (bool) preg_match( '/<meta[^>]+property=["\']og:/i', $home_html );
		$has_canonical  = (bool) preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]*>/i', $home_html );
		$uses_srcset    = (bool) preg_match( '/srcset=["\'][^"\']+/', $home_html );

		// OG and canonical coverage across all pages (approximation: assume all pages have same setup as home)
		$og_pct        = $has_og_home ? 90 : 0;
		$canonical_pct = $has_canonical ? 90 : 0;

		return array(
			'robots_txt_exists'      => $has_robots,
			'sitemap_exists'         => $has_sitemap,
			'sitemap_coverage_pct'   => $sitemap_pct,
			'favicon_present'        => $has_fav,
			'viewport_meta_present'  => $viewport_meta,
			'og_coverage_pct'        => $og_pct,
			'canonical_coverage_pct' => $canonical_pct,
			'uses_srcset'            => $uses_srcset,
		);
	}

	private function get_ai_visibility_status(): array {
		$robots_path  = ABSPATH . 'robots.txt';
		$robots_txt   = file_exists( $robots_path ) ? file_get_contents( $robots_path ) : '';

		$ai_bots = array( 'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai', 'Google-Extended', 'PerplexityBot' );
		$allowed = array();
		$blocked = array();

		// Check blanket block
		$blanket_block = (bool) preg_match( '/User-agent:\s*\*.*?Disallow:\s*\//si', $robots_txt );

		foreach ( $ai_bots as $bot ) {
			$pattern = '/User-agent:\s*' . preg_quote( $bot, '/' ) . '.*?Disallow:\s*\//si';
			if ( preg_match( $pattern, $robots_txt ) ) {
				$blocked[] = $bot;
			} else {
				$allowed[] = $bot;
			}
		}

		// Schema types from home page
		$home_response = @wp_safe_remote_get( home_url(), array( 'timeout' => 8, 'sslverify' => false ) );
		$home_html     = ! is_wp_error( $home_response ) ? wp_remote_retrieve_body( $home_response ) : '';
		$schema_types  = array();
		if ( preg_match_all( '/"@type"\s*:\s*"([^"]+)"/', $home_html, $sm ) ) {
			$schema_types = array_unique( $sm[1] );
		}

		// FAQ formatted content
		$faq_content = $this->has_faq_content();

		// llms.txt
		$llms_path = ABSPATH . 'llms.txt';
		$has_llms  = file_exists( $llms_path );

		// Days since last post
		$last = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		$days_since = $last ? round( ( time() - strtotime( $last[0]->post_date ) ) / DAY_IN_SECONDS ) : null;

		return array(
			'blanket_block'               => $blanket_block,
			'bots_allowed'                => $allowed,
			'bots_blocked'                => $blocked,
			'schema_types'                => $schema_types,
			'has_faq_formatted_content'   => $faq_content,
			'has_llms_txt'                => $has_llms,
			'days_since_last_post'        => $days_since,
		);
	}

	private function has_faq_content(): bool {
		global $wpdb;
		// Look for FAQ block or common Q&A patterns
		$result = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_status = 'publish'
			 AND post_type IN ('post', 'page')
			 AND (post_content LIKE '%wp:yoast/faq-block%'
			      OR post_content LIKE '%faq%'
			      OR post_content LIKE '%frequently asked%')"
		);
		return $result > 0;
	}

	private function get_commercial_signals(): array {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Contact form plugins
		$form_slugs = array( 'contact-form-7', 'gravityforms', 'ninja-forms', 'wpforms', 'formidable' );
		$has_form   = false;
		foreach ( $active_plugins as $p ) {
			foreach ( $form_slugs as $fs ) {
				if ( str_contains( $p, $fs ) ) { $has_form = true; break 2; }
			}
		}

		// E-commerce
		$has_ecomm = false;
		foreach ( $active_plugins as $p ) {
			if ( str_contains( $p, 'woocommerce' ) || str_contains( $p, 'easy-digital-downloads' ) ) {
				$has_ecomm = true;
				break;
			}
		}

		// Email signup
		$email_slugs = array( 'mailchimp', 'convertkit', 'mailerlite', 'klaviyo', 'fluentcrm', 'newsletter', 'wp-mail-smtp' );
		$has_email   = false;
		foreach ( $active_plugins as $p ) {
			foreach ( $email_slugs as $es ) {
				if ( str_contains( $p, $es ) ) { $has_email = true; break 2; }
			}
		}

		// Booking
		$booking_slugs = array( 'bookly', 'amelia', 'woocommerce-bookings', 'simply-schedule-appointments', 'booking-calendar' );
		$has_booking   = false;
		foreach ( $active_plugins as $p ) {
			foreach ( $booking_slugs as $bs ) {
				if ( str_contains( $p, $bs ) ) { $has_booking = true; break 2; }
			}
		}

		// Contact page
		$contact_page = get_page_by_path( 'contact' ) ?: get_page_by_path( 'contact-us' );
		$has_contact_page = $contact_page && $contact_page->post_status === 'publish';

		// Scan homepage for contact info and trust signals
		$home_response = @wp_safe_remote_get( home_url(), array( 'timeout' => 8, 'sslverify' => false ) );
		$home_text     = ! is_wp_error( $home_response ) ? strtolower( wp_remote_retrieve_body( $home_response ) ) : '';

		$phone_present       = (bool) preg_match( '/\+?[\d\s\-\(\)]{7,15}/', $home_text );
		$email_present       = (bool) preg_match( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/', $home_text );

		// CTA patterns across key pages: homepage, services, about, contact
		$cta_patterns        = array( 'contact us', 'get started', 'buy now', 'book a call', 'sign up', 'get a quote', 'schedule', 'request a demo', 'try for free', 'get in touch', 'start now', 'learn more' );
		$key_pages_slugs     = array( '', 'services', 'about', 'about-us', 'products', 'pricing', 'contact' );
		$pages_without_cta   = 0;
		$key_page_count      = 0;
		foreach ( $key_pages_slugs as $slug ) {
			$url          = home_url( "/$slug" );
			$resp         = @wp_safe_remote_get( $url, array( 'timeout' => 5, 'sslverify' => false ) );
			$code         = is_wp_error( $resp ) ? 0 : wp_remote_retrieve_response_code( $resp );
			if ( $code === 200 ) {
				$key_page_count++;
				$body     = strtolower( wp_remote_retrieve_body( $resp ) );
				$has_cta  = false;
				foreach ( $cta_patterns as $pat ) {
					if ( str_contains( $body, $pat ) ) { $has_cta = true; break; }
				}
				if ( ! $has_cta ) {
					$pages_without_cta++;
				}
			}
		}

		// Trust signals: testimonials, reviews, logos
		$trust_score = 0;
		if ( str_contains( $home_text, 'testimonial' ) || str_contains( $home_text, 'what our clients' ) ) $trust_score++;
		if ( str_contains( $home_text, 'review' ) || str_contains( $home_text, 'stars' ) || str_contains( $home_text, '★' ) || str_contains( $home_text, '⭐' ) ) $trust_score++;
		if ( str_contains( $home_text, 'certified' ) || str_contains( $home_text, 'award' ) || str_contains( $home_text, 'partner' ) ) $trust_score++;

		return array(
			'has_contact_form'      => $has_form,
			'has_ecommerce'         => $has_ecomm,
			'has_email_signup'      => $has_email,
			'has_booking_plugin'    => $has_booking,
			'has_contact_page'      => $has_contact_page,
			'phone_number_present'  => $phone_present,
			'email_address_present' => $email_present,
			'pages_without_cta_count' => $pages_without_cta,
			'key_page_count'        => max( 1, $key_page_count ),
			'trust_signal_score'    => $trust_score,
		);
	}

	// =========================================================================
	// History management
	// =========================================================================

	private function get_audit_history(): array {
		$history = get_option( self::HISTORY_OPTION, array() );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		// Return summary data for trend display
		return array_map( function ( $entry ) {
			return array(
				'scanned_at' => $entry['scanned_at'] ?? '',
				'overall'    => $entry['overall'] ?? 0,
				'grade'      => $entry['grade'] ?? '?',
				'dimensions' => array_map(
					fn( $d ) => array( 'score' => $d['score'], 'max' => $d['max'] ),
					$entry['dimensions'] ?? array()
				),
			);
		}, $history );
	}

	private function save_audit_result( array $result ): array {
		$history   = get_option( self::HISTORY_OPTION, array() );
		$history[] = $result;
		// Keep last 12 scans
		if ( count( $history ) > 12 ) {
			$history = array_slice( $history, -12 );
		}
		update_option( self::HISTORY_OPTION, $history, false );
		return array( 'saved' => true, 'stored_count' => count( $history ) );
	}

	private function post_admin_notice( string $message, string $type ): array {
		update_option( self::NOTICE_OPTION, array(
			'message' => $message,
			'type'    => $type,
			'date'    => gmdate( 'Y-m-d H:i:s' ),
		) );
		return array( 'posted' => true );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Create a standardised check result.
	 *
	 * @param string $id      Machine ID.
	 * @param string $label   Human label.
	 * @param string $status  "pass", "warn", or "fail".
	 * @param string $message Human-readable explanation.
	 * @param int    $impact  0–10: how much fixing this improves commercial performance.
	 * @param int    $score   Points earned for this check.
	 */
	private function check( string $id, string $label, string $status, string $message, int $impact, int $score ): array {
		return compact( 'id', 'label', 'status', 'message', 'impact', 'score' );
	}

	private function score_to_grade( int $score ): string {
		return match ( true ) {
			$score >= 90 => 'A',
			$score >= 80 => 'B',
			$score >= 65 => 'C',
			$score >= 50 => 'D',
			$score >= 35 => 'D-',
			default      => 'F',
		};
	}

	private function grade_label( string $grade ): string {
		return match ( $grade ) {
			'A'  => 'Excellent — well-optimised across all dimensions',
			'B'  => 'Good — solid foundation with minor gaps',
			'C'  => 'Fair — noticeable issues affecting performance',
			'D'  => 'Poor — significant issues limiting performance',
			'D-' => 'Weak — major roadblocks across multiple dimensions',
			'F'  => 'Critical — fundamental problems that must be addressed',
			default => '',
		};
	}
}
