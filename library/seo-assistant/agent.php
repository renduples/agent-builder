<?php
/**
 * Agent Name: SEO Assistant
 * Version: 1.0.0
 * Description: Audits posts and pages for SEO health. Fixes meta titles, descriptions, headings, and keyword usage so your content ranks higher.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: SEO
 * Tags: seo, meta, keywords, rankings, headings, content
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
 * SEO Assistant Agent
 *
 * Audits WordPress posts and pages for on-page SEO issues and applies fixes.
 * Supports Rank Math, Yoast, and plain post excerpts as meta description sources.
 */
class Agentic_SEO_Assistant extends \Agentic\Agent_Base {

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'seo-assistant';
	}

	public function get_name(): string {
		return 'SEO Assistant';
	}

	public function get_description(): string {
		return 'Audits posts and pages for SEO health. Fixes meta titles, descriptions, headings, and keyword usage so your content ranks higher.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '🔍';
	}

	public function get_category(): string {
		return 'SEO';
	}

	public function get_required_capabilities(): array {
		return array( 'edit_posts' );
	}

	public function get_welcome_message(): string {
		return "🔍 **SEO Assistant**\n\n" .
			"I audit your content for SEO opportunities and apply fixes directly.\n\n" .
			"**What I can do:**\n" .
			"- **Site-wide overview** — find posts with missing meta, thin content, no internal links\n" .
			"- **Individual audits** — score a post on title, meta description, headings, keyword density, links, and alt text\n" .
			"- **Apply fixes** — update titles, meta descriptions, slugs, and focus keyword meta\n" .
			"- **Works with** Rank Math, Yoast, or neither\n\n" .
			"Share a post ID or ask for a site-wide audit to get started.";
	}

	public function get_suggested_prompts(): array {
		return array(
			'Run a site-wide SEO overview',
			'Audit post #[id] for SEO',
			'Find posts with missing meta descriptions',
			'Fix the title and meta description for post #[id]',
		);
	}

	// -------------------------------------------------------------------------
	// Tool definitions
	// -------------------------------------------------------------------------

	public function get_tools(): array {
		return array(

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_seo_plugin_info',
					'description' => 'Detect which SEO plugin is active (Rank Math, Yoast, or none) and return the meta field names used for storing meta descriptions and focus keywords. Call this once at the start of each session.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_seo_overview',
					'description' => 'Scan all published posts for common SEO issues: missing meta descriptions, titles that are too short (<30 chars) or too long (>60 chars), thin content (<300 words), no internal links, and no images. Returns counts and examples for each issue type.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array(
								'type'        => 'string',
								'description' => '"post" (default), "page", or "any".',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Max posts to scan (1–200). Defaults to 100.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_posts_needing_seo',
					'description' => 'List posts filtered by a specific SEO issue. Useful for batching fixes.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'issue'     => array(
								'type'        => 'string',
								'description' => '"missing_meta_description", "thin_content", "title_too_long", "title_too_short", "no_internal_links", "no_images", or "no_excerpt".',
							),
							'post_type' => array(
								'type'        => 'string',
								'description' => '"post" (default) or "page".',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Max results (1–50). Defaults to 20.',
							),
						),
						'required'   => array( 'issue' ),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'analyze_post_seo',
					'description' => 'Full SEO audit of a single post. Returns a score (0–100), title length, meta description, heading structure, keyword density, internal/external link counts, image alt text coverage, and a list of specific recommendations. Always analyse before updating.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'       => array(
								'type'        => 'integer',
								'description' => 'The WordPress post ID to audit.',
							),
							'focus_keyword' => array(
								'type'        => 'string',
								'description' => 'Optional focus keyword to check for in title, meta, headings, first paragraph, and body.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'update_post_seo',
					'description' => 'Update SEO fields on a post: title, meta description (stored in excerpt and in Rank Math/Yoast if installed), URL slug, and focus keyword. Only supply the fields you want to change. Always get explicit user approval before calling this.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'          => array(
								'type'        => 'integer',
								'description' => 'The WordPress post ID to update.',
							),
							'title'            => array(
								'type'        => 'string',
								'description' => 'New post title. Recommended: 30–60 characters including focus keyword.',
							),
							'meta_description' => array(
								'type'        => 'string',
								'description' => 'Meta description. Recommended: 120–158 characters. Stored in excerpt and in Rank Math/Yoast meta if installed.',
							),
							'slug'             => array(
								'type'        => 'string',
								'description' => 'URL slug — 2–5 words, hyphen-separated, keyword-rich.',
							),
							'focus_keyword'    => array(
								'type'        => 'string',
								'description' => 'Focus keyword to write to Rank Math (rank_math_focus_keyword) or Yoast (_yoast_wpseo_focuskw) meta.',
							),
						),
						'required'   => array( 'post_id' ),
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
			'get_seo_plugin_info'    => $this->tool_get_seo_plugin_info(),
			'get_seo_overview'       => $this->tool_get_seo_overview( $arguments ),
			'list_posts_needing_seo' => $this->tool_list_posts_needing_seo( $arguments ),
			'analyze_post_seo'       => $this->tool_analyze_post_seo( $arguments ),
			'update_post_seo'        => $this->tool_update_post_seo( $arguments ),
			default                  => null,
		};
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	private function tool_get_seo_plugin_info(): array {
		$plugin = $this->detect_seo_plugin();
		$info   = array( 'detected' => $plugin );

		if ( 'rankmath' === $plugin ) {
			$info['meta_description_key'] = 'rank_math_description';
			$info['focus_keyword_key']    = 'rank_math_focus_keyword';
		} elseif ( 'yoast' === $plugin ) {
			$info['meta_description_key'] = '_yoast_wpseo_metadesc';
			$info['focus_keyword_key']    = '_yoast_wpseo_focuskw';
		} else {
			$info['note'] = 'No SEO plugin detected. Meta descriptions will be read from and written to the post excerpt field.';
		}

		return $info;
	}

	private function tool_get_seo_overview( array $args ): array {
		$post_type = $args['post_type'] ?? 'post';
		$limit     = min( max( (int) ( $args['limit'] ?? 100 ), 1 ), 200 );
		$site_url  = untrailingslashit( get_bloginfo( 'url' ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$issues = array(
			'missing_meta_description' => array(),
			'title_too_short'          => array(),
			'title_too_long'           => array(),
			'thin_content'             => array(),
			'no_internal_links'        => array(),
			'no_images'                => array(),
		);

		foreach ( $posts as $post ) {
			$title      = $post->post_title;
			$title_len  = mb_strlen( $title );
			$content    = $post->post_content;
			$word_count = str_word_count( wp_strip_all_tags( $content ) );
			$meta_desc  = $this->get_meta_description( $post->ID, $post->post_excerpt );
			$stub       = array( 'id' => $post->ID, 'title' => $title );

			if ( empty( $meta_desc ) ) {
				$issues['missing_meta_description'][] = $stub;
			}
			if ( $title_len < 30 ) {
				$issues['title_too_short'][] = $stub;
			}
			if ( $title_len > 60 ) {
				$issues['title_too_long'][] = $stub;
			}
			if ( $word_count < 300 ) {
				$issues['thin_content'][] = $stub;
			}

			preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links );
			$has_internal = false;
			foreach ( $links[1] as $href ) {
				if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
					$has_internal = true;
					break;
				}
			}
			if ( ! $has_internal ) {
				$issues['no_internal_links'][] = $stub;
			}
			if ( ! preg_match( '/<img[^>]+>/i', $content ) ) {
				$issues['no_images'][] = $stub;
			}
		}

		$summary = array();
		foreach ( $issues as $key => $affected ) {
			$summary[ $key ] = array(
				'count'    => count( $affected ),
				'examples' => array_slice( $affected, 0, 5 ),
			);
		}

		return array(
			'total_posts_scanned' => count( $posts ),
			'issues'              => $summary,
		);
	}

	private function tool_list_posts_needing_seo( array $args ): array {
		$issue     = $args['issue'] ?? '';
		$post_type = $args['post_type'] ?? 'post';
		$limit     = min( max( (int) ( $args['limit'] ?? 20 ), 1 ), 50 );
		$site_url  = untrailingslashit( get_bloginfo( 'url' ) );

		$all_posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
			)
		);

		$results = array();

		foreach ( $all_posts as $post ) {
			$content   = $post->post_content;
			$plain     = wp_strip_all_tags( $content );
			$title     = $post->post_title;
			$title_len = mb_strlen( $title );
			$meta_desc = $this->get_meta_description( $post->ID, $post->post_excerpt );
			$match     = false;

			switch ( $issue ) {
				case 'missing_meta_description':
					$match = empty( $meta_desc );
					break;
				case 'thin_content':
					$match = str_word_count( $plain ) < 300;
					break;
				case 'title_too_long':
					$match = $title_len > 60;
					break;
				case 'title_too_short':
					$match = $title_len < 30;
					break;
				case 'no_internal_links':
					preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links );
					$has_internal = false;
					foreach ( $links[1] as $href ) {
						if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
							$has_internal = true;
							break;
						}
					}
					$match = ! $has_internal;
					break;
				case 'no_images':
					$match = ! preg_match( '/<img[^>]+>/i', $content );
					break;
				case 'no_excerpt':
					$match = empty( $post->post_excerpt );
					break;
			}

			if ( $match ) {
				$results[] = array(
					'id'          => $post->ID,
					'title'       => $title,
					'title_chars' => $title_len,
					'word_count'  => str_word_count( $plain ),
					'meta_desc'   => $meta_desc,
					'url'         => get_permalink( $post->ID ),
					'date'        => $post->post_date,
				);
				if ( count( $results ) >= $limit ) {
					break;
				}
			}
		}

		return array(
			'issue' => $issue,
			'count' => count( $results ),
			'posts' => $results,
		);
	}

	private function tool_analyze_post_seo( array $args ): array {
		$post = get_post( (int) ( $args['post_id'] ?? 0 ) );
		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$content    = $post->post_content;
		$plain_text = wp_strip_all_tags( $content );
		$title      = $post->post_title;
		$title_len  = mb_strlen( $title );
		$word_count = str_word_count( $plain_text );
		$meta_desc  = $this->get_meta_description( $post->ID, $post->post_excerpt );
		$slug       = $post->post_name;
		$site_url   = untrailingslashit( get_bloginfo( 'url' ) );

		// Headings.
		preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', $content, $headings );
		$h1_texts = array();
		$h2_texts = array();
		foreach ( $headings[1] as $i => $level ) {
			if ( '1' === $level ) {
				$h1_texts[] = wp_strip_all_tags( $headings[2][ $i ] );
			} elseif ( '2' === $level ) {
				$h2_texts[] = wp_strip_all_tags( $headings[2][ $i ] );
			}
		}

		// Links.
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links );
		$internal_links = 0;
		$external_links = 0;
		foreach ( $links[1] as $href ) {
			if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
				++$internal_links;
			} else {
				++$external_links;
			}
		}

		// Images.
		preg_match_all( '/<img([^>]*)>/i', $content, $img_matches );
		$imgs_without_alt = 0;
		foreach ( $img_matches[1] as $img_attrs ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img_attrs ) ) {
				++$imgs_without_alt;
			}
		}

		// Focus keyword analysis.
		$kw_analysis = null;
		if ( ! empty( $args['focus_keyword'] ) ) {
			$kw      = strtolower( trim( $args['focus_keyword'] ) );
			$lc_text = strtolower( $plain_text );
			$count   = substr_count( $lc_text, $kw );
			$density = $word_count > 0 ? round( ( $count / $word_count ) * 100, 2 ) : 0;

			$kw_analysis = array(
				'keyword'       => $args['focus_keyword'],
				'in_title'      => str_contains( strtolower( $title ), $kw ),
				'in_meta_desc'  => str_contains( strtolower( $meta_desc ), $kw ),
				'in_slug'       => str_contains( strtolower( $slug ), str_replace( ' ', '-', $kw ) ),
				'in_h1'         => (bool) array_filter( $h1_texts, fn( $h ) => str_contains( strtolower( $h ), $kw ) ),
				'in_first_para' => str_contains( strtolower( mb_substr( $plain_text, 0, 200 ) ), $kw ),
				'occurrences'   => $count,
				'density'       => $density,
				'density_note'  => $density < 0.5 ? 'Too low — use the keyword more naturally' : ( $density > 3.0 ? 'Too high — risk of keyword stuffing' : 'Good (0.5–3%)' ),
			);
		}

		// Score.
		$score  = 100;
		$issues = array();
		$pass   = array();

		if ( $title_len < 30 ) {
			$score -= 15;
			$issues[] = "Title too short ({$title_len} chars). Aim for 30–60 characters.";
		} elseif ( $title_len > 60 ) {
			$score -= 10;
			$issues[] = "Title too long ({$title_len} chars). Aim for 30–60 characters.";
		} else {
			$pass[] = "Title length is good ({$title_len} chars).";
		}

		$meta_len = mb_strlen( $meta_desc );
		if ( empty( $meta_desc ) ) {
			$score -= 20;
			$issues[] = 'No meta description. Add one (120–158 chars) to control your search snippet.';
		} elseif ( $meta_len < 120 ) {
			$score -= 10;
			$issues[] = "Meta description is short ({$meta_len} chars). Aim for 120–158 characters.";
		} elseif ( $meta_len > 158 ) {
			$score -= 5;
			$issues[] = "Meta description too long ({$meta_len} chars). Keep under 158 to avoid truncation.";
		} else {
			$pass[] = "Meta description length is good ({$meta_len} chars).";
		}

		if ( $word_count < 300 ) {
			$score -= 15;
			$issues[] = "Thin content ({$word_count} words). Aim for 600+ words for competitive topics.";
		} else {
			$pass[] = "Content length is good ({$word_count} words).";
		}

		if ( count( $headings[0] ) === 0 ) {
			$score -= 10;
			$issues[] = 'No headings found. Add an H1 and at least one H2.';
		} elseif ( count( $h1_texts ) === 0 ) {
			$score -= 5;
			$issues[] = 'No H1 heading found.';
		} else {
			$pass[] = 'Heading structure present.';
		}

		if ( $internal_links === 0 ) {
			$score -= 10;
			$issues[] = 'No internal links. Link to related content to improve crawlability.';
		} else {
			$pass[] = "{$internal_links} internal link(s) found.";
		}

		if ( count( $img_matches[0] ) === 0 ) {
			$score -= 5;
			$issues[] = 'No images found. Images improve engagement and provide alt text opportunities.';
		} elseif ( $imgs_without_alt > 0 ) {
			$score -= 5;
			$issues[] = "{$imgs_without_alt} image(s) missing alt text.";
		} else {
			$pass[] = 'All images have alt text.';
		}

		if ( strlen( $slug ) > 50 ) {
			$score -= 5;
			$issues[] = 'URL slug is long. Keep it concise and keyword-rich (2–5 words).';
		} else {
			$pass[] = 'URL slug is concise.';
		}

		return array(
			'id'                   => $post->ID,
			'title'                => $title,
			'title_chars'          => $title_len,
			'meta_description'     => $meta_desc,
			'meta_desc_chars'      => $meta_len,
			'slug'                 => $slug,
			'word_count'           => $word_count,
			'h1_headings'          => $h1_texts,
			'h2_headings'          => $h2_texts,
			'total_headings'       => count( $headings[0] ),
			'internal_links'       => $internal_links,
			'external_links'       => $external_links,
			'images'               => count( $img_matches[0] ),
			'images_no_alt'        => $imgs_without_alt,
			'seo_score'            => max( 0, $score ),
			'passing'              => $pass,
			'issues'               => $issues,
			'focus_keyword'        => $kw_analysis,
		);
	}

	private function tool_update_post_seo( array $args ): array {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'You do not have permission to edit this post.' );
		}

		$post_data = array( 'ID' => $post_id );
		$updated   = array();

		if ( isset( $args['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $args['title'] );
			$updated[]               = 'title';
		}
		if ( isset( $args['meta_description'] ) ) {
			$clean_meta                = sanitize_textarea_field( $args['meta_description'] );
			$post_data['post_excerpt'] = $clean_meta;
			$updated[]                 = 'excerpt (meta description)';

			$seo = $this->detect_seo_plugin();
			if ( 'rankmath' === $seo ) {
				update_post_meta( $post_id, 'rank_math_description', $clean_meta );
				$updated[] = 'rank_math_description';
			} elseif ( 'yoast' === $seo ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $clean_meta );
				$updated[] = '_yoast_wpseo_metadesc';
			}
		}
		if ( isset( $args['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $args['slug'] );
			$updated[]              = 'slug';
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return array( 'error' => $result->get_error_message() );
			}
		}

		if ( isset( $args['focus_keyword'] ) ) {
			$seo = $this->detect_seo_plugin();
			if ( 'rankmath' === $seo ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $args['focus_keyword'] ) );
				$updated[] = 'rank_math_focus_keyword';
			} elseif ( 'yoast' === $seo ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $args['focus_keyword'] ) );
				$updated[] = '_yoast_wpseo_focuskw';
			} else {
				$updated[] = 'focus_keyword (no SEO plugin active — install Rank Math or Yoast to persist this)';
			}
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'updated' => $updated,
			'url'     => get_permalink( $post_id ),
			'message' => "SEO fields updated for post {$post_id}: " . implode( ', ', $updated ) . '.',
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function detect_seo_plugin(): string {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			return 'rankmath';
		}
		if ( defined( 'WPSEO_VERSION' ) || is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			return 'yoast';
		}
		return 'none';
	}

	private function get_meta_description( int $post_id, string $excerpt ): string {
		$plugin = $this->detect_seo_plugin();
		if ( 'rankmath' === $plugin ) {
			$meta = get_post_meta( $post_id, 'rank_math_description', true );
			if ( $meta ) {
				return $meta;
			}
		} elseif ( 'yoast' === $plugin ) {
			$meta = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			if ( $meta ) {
				return $meta;
			}
		}
		return $excerpt;
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_SEO_Assistant() );
	}
);
