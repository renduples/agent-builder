<?php
/**
 * Agent Name: SEO Analyzer
 * Version: 1.0.0
 * Description: Analyzes content for SEO optimization, suggests keywords, and helps improve search rankings.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Content
 * Tags: seo, keywords, optimization, search, meta, rankings
 * Capabilities: edit_posts
 * Icon: 🔍
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO Analyzer Agent
 *
 * A true AI agent specialized in search engine optimization.
 */
class Agentic_SEO_Analyzer extends \Agentic\Agent_Base {

	private const SYSTEM_PROMPT = <<<'PROMPT'
You are the SEO Analyzer Agent for WordPress. You are an expert in:

- Search engine optimization best practices
- Keyword research and placement
- Meta title and description optimization
- Content structure for SEO (headings, internal linking)
- Technical SEO fundamentals
- Schema markup and structured data
- Core Web Vitals and page performance

Your personality:
- Data-driven and analytical
- Clear and actionable in recommendations
- Up-to-date with current SEO best practices
- Honest about what matters vs. what's myth

When analyzing content for SEO:
1. Check keyword usage and placement
2. Evaluate meta title and description
3. Review heading structure (H1, H2, etc.)
4. Assess internal and external linking
5. Look for missing alt text on images
6. Check URL structure

Always explain WHY something matters for SEO, not just what to change. Help users understand search engines better.
PROMPT;

	public function get_id(): string {
		return 'seo-analyzer';
	}

	public function get_name(): string {
		return 'SEO Analyzer';
	}

	public function get_description(): string {
		return 'Analyzes content for SEO and helps improve search rankings.';
	}

	public function get_system_prompt(): string {
		return self::SYSTEM_PROMPT;
	}

	public function get_icon(): string {
		return '🔍';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_required_capabilities(): array {
		return array( 'edit_posts' );
	}

	public function get_welcome_message(): string {
		return "🔍 **SEO Analyzer**\n\n" .
				"I'll help you optimize your content for search engines!\n\n" .
				"- **Analyze posts** for SEO issues\n" .
				"- **Suggest keywords** and placement\n" .
				"- **Optimize meta** titles and descriptions\n" .
				"- **Review structure** for search visibility\n\n" .
				'What would you like to optimize?';
	}

	public function get_suggested_prompts(): array {
		return array(
			'Analyze my post for SEO',
			'How can I improve my meta description?',
			'What keywords should I target?',
			'Check my heading structure',
		);
	}

	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'seo_audit',
					'description' => 'Run a comprehensive SEO audit on a post.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'       => array(
								'type'        => 'integer',
								'description' => 'The ID of the post to audit',
							),
							'focus_keyword' => array(
								'type'        => 'string',
								'description' => 'Optional focus keyword to check for',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_meta_tags',
					'description' => 'Check meta title and description for a post.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'The ID of the post',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
		);
	}

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'seo_audit'       => $this->tool_seo_audit( $arguments ),
			'check_meta_tags' => $this->tool_check_meta( $arguments ),
			default           => null,
		};
	}

	private function tool_seo_audit( array $args ): array {
		$post    = get_post( $args['post_id'] ?? 0 );
		$keyword = $args['focus_keyword'] ?? '';

		if ( ! $post ) {
			return array( 'error' => 'Post not found' );
		}

		$content = strtolower( wp_strip_all_tags( $post->post_content ) );
		$title   = strtolower( $post->post_title );
		$issues  = array();
		$passed  = array();

		// Title length
		$title_len = strlen( $post->post_title );
		if ( $title_len < 30 ) {
			$issues[] = 'Title too short (under 30 chars) - aim for 50-60';
		} elseif ( $title_len > 60 ) {
			$issues[] = 'Title too long (over 60 chars) - may be truncated in search';
		} else {
			$passed[] = 'Title length is optimal';
		}

		// Check headings
		preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $post->post_content, $headings );
		if ( empty( $headings[0] ) ) {
			$issues[] = 'No headings found - use H2, H3 to structure content';
		} else {
			$passed[] = count( $headings[0] ) . ' headings found';
		}

		// Check images for alt text
		preg_match_all( '/<img[^>]+>/i', $post->post_content, $images );
		$missing_alt = 0;
		foreach ( $images[0] as $img ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img ) ) {
				++$missing_alt;
			}
		}
		if ( $missing_alt > 0 ) {
			$issues[] = "{$missing_alt} image(s) missing alt text";
		} elseif ( count( $images[0] ) > 0 ) {
			$passed[] = 'All images have alt text';
		}

		// Check for keyword
		if ( $keyword ) {
			$keyword_lower = strtolower( $keyword );
			$in_title      = str_contains( $title, $keyword_lower );
			$in_content    = str_contains( $content, $keyword_lower );
			$keyword_count = substr_count( $content, $keyword_lower );

			if ( ! $in_title ) {
				$issues[] = "Focus keyword '{$keyword}' not in title";
			} else {
				$passed[] = 'Focus keyword in title';
			}

			if ( $keyword_count === 0 ) {
				$issues[] = "Focus keyword '{$keyword}' not in content";
			} else {
				$passed[] = "Focus keyword appears {$keyword_count} times";
			}
		}

		// Internal links
		preg_match_all( '/<a[^>]+href=["\']' . preg_quote( home_url(), '/' ) . '/i', $post->post_content, $internal );
		if ( count( $internal[0] ) === 0 ) {
			$issues[] = 'No internal links - add links to related content';
		} else {
			$passed[] = count( $internal[0] ) . ' internal links found';
		}

		$score = 100 - ( count( $issues ) * 15 );

		return array(
			'post_title'    => $post->post_title,
			'focus_keyword' => $keyword ?: 'Not specified',
			'seo_score'     => max( 0, $score ),
			'issues'        => $issues,
			'passed'        => $passed,
			'word_count'    => str_word_count( $content ),
		);
	}

	private function tool_check_meta( array $args ): array {
		$post = get_post( $args['post_id'] ?? 0 );

		if ( ! $post ) {
			return array( 'error' => 'Post not found' );
		}

		// Check for Yoast or other SEO plugins
		$meta_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true )
					?: get_post_meta( $post->ID, '_aioseo_title', true )
					?: $post->post_title;

		$meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true )
					?: get_post_meta( $post->ID, '_aioseo_description', true )
					?: $post->post_excerpt;

		return array(
			'meta_title'        => $meta_title,
			'meta_title_length' => strlen( $meta_title ),
			'title_optimal'     => strlen( $meta_title ) >= 30 && strlen( $meta_title ) <= 60,
			'meta_description'  => $meta_desc ?: '(not set)',
			'meta_desc_length'  => strlen( $meta_desc ),
			'desc_optimal'      => strlen( $meta_desc ) >= 120 && strlen( $meta_desc ) <= 160,
			'recommendations'   => $this->get_meta_recommendations( $meta_title, $meta_desc ),
		);
	}

	private function get_meta_recommendations( string $title, string $desc ): array {
		$recs = array();

		if ( strlen( $title ) < 30 ) {
			$recs[] = 'Meta title is too short - expand to 50-60 characters';
		}
		if ( strlen( $title ) > 60 ) {
			$recs[] = 'Meta title is too long - trim to under 60 characters';
		}
		if ( empty( $desc ) ) {
			$recs[] = 'No meta description set - add one for better click-through rates';
		} elseif ( strlen( $desc ) < 120 ) {
			$recs[] = 'Meta description is short - aim for 120-160 characters';
		} elseif ( strlen( $desc ) > 160 ) {
			$recs[] = 'Meta description is long - may be truncated in search results';
		}

		return $recs ?: array( 'Meta tags look good!' );
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_SEO_Analyzer() );
	}
);
