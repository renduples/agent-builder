<?php
/**
 * Agent Name: Content Writer
 * Version: 2.0.0
 * Description: Creates, edits, and publishes posts and pages. Drafts from a description, rewrites for clarity, and optimises titles, excerpts, and structure.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Content
 * Tags: writing, editing, posts, pages, drafts, publishing, seo, readability
 * Capabilities: edit_posts
 * Icon: ✍️
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Writer Agent
 *
 * A full-featured writing agent that creates, edits, analyses, and publishes
 * WordPress posts and pages. Supports full CRUD operations, readability scoring,
 * SEO keyword analysis, category/tag management, and author selection.
 */
class Agentic_Content_Writer extends \Agentic\Agent_Base {

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'content-writer';
	}

	public function get_name(): string {
		return 'Content Writer';
	}

	public function get_description(): string {
		return 'Creates, edits, and publishes posts and pages. Drafts from a description, rewrites for clarity, and optimises titles, excerpts, and structure.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '✍️';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_required_capabilities(): array {
		return array( 'edit_posts' );
	}

	public function get_welcome_message(): string {
		return "✍️ **Content Writer**\n\n" .
			"I can create, edit, and publish your posts and pages — from a quick description or a full brief.\n\n" .
			"**What I can do:**\n" .
			"- **Draft** new posts and pages from a description or outline\n" .
			"- **Edit** existing content for clarity, tone, and structure\n" .
			"- **Analyse** readability, word count, and heading structure\n" .
			"- **Optimise** titles, excerpts, and keyword focus\n" .
			"- **Publish or save** as draft — your choice\n\n" .
			"How can I help you today?";
	}

	public function get_suggested_prompts(): array {
		return array(
			'Write a post about [topic]',
			'Analyse my latest post',
			'Improve the readability of post #[id]',
			'List my recent drafts',
		);
	}

	// -------------------------------------------------------------------------
	// Tool definitions
	// -------------------------------------------------------------------------

	public function get_tools(): array {
		return array(

			// -- Read --------------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_posts',
					'description' => 'List posts or pages, filtered by status, type, or search term. Use this to find content to work with before fetching or editing it.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array(
								'type'        => 'string',
								'description' => 'Post type: "post" (default) or "page".',
							),
							'status'    => array(
								'type'        => 'string',
								'description' => 'Post status: "publish", "draft", "pending", "private", or "any". Defaults to "any".',
							),
							'search'    => array(
								'type'        => 'string',
								'description' => 'Optional keyword to search titles and content.',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Maximum number of results to return (1–50). Defaults to 10.',
							),
							'offset'    => array(
								'type'        => 'integer',
								'description' => 'Number of results to skip for pagination. Defaults to 0.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_post',
					'description' => 'Retrieve the full content, metadata, categories, and tags of a single post or page by ID. Always call this before editing.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'The WordPress post ID.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_site_context',
					'description' => 'Get site name, description, active categories, common tags, and author list. Call this once at the start of a session to understand the site before creating content.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_author_list',
					'description' => 'Get the list of WordPress users who can author posts (editors, authors, administrators).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(),
					),
				),
			),

			// -- Analyse -----------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'analyze_post',
					'description' => 'Analyse a post for readability (Flesch-Kincaid score), word count, sentence length, heading structure, image presence, internal/external links, and SEO keyword density. Returns actionable recommendations.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'       => array(
								'type'        => 'integer',
								'description' => 'The WordPress post ID.',
							),
							'focus_keyword' => array(
								'type'        => 'string',
								'description' => 'Optional focus keyword to measure density and placement.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),

			// -- Write -------------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_post',
					'description' => 'Create a new WordPress post or page. Saves as draft by default. Always confirm with the user before publishing (setting status to "publish").',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'title'       => array(
								'type'        => 'string',
								'description' => 'The post title.',
							),
							'content'     => array(
								'type'        => 'string',
								'description' => 'The post body in HTML or plain text.',
							),
							'excerpt'     => array(
								'type'        => 'string',
								'description' => 'A short summary shown in archive pages and feeds (150–160 characters recommended).',
							),
							'status'      => array(
								'type'        => 'string',
								'description' => '"draft" (default) or "publish". Only use "publish" when the user explicitly asks to publish.',
							),
							'post_type'   => array(
								'type'        => 'string',
								'description' => '"post" (default) or "page".',
							),
							'author_id'   => array(
								'type'        => 'integer',
								'description' => 'WordPress user ID to set as author. Omit to use current user.',
							),
							'categories'  => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'integer' ),
								'description' => 'Array of category IDs to assign.',
							),
							'tags'        => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Array of tag names to assign (created if they do not exist).',
							),
							'slug'        => array(
								'type'        => 'string',
								'description' => 'URL slug for the post. Generated from the title if omitted.',
							),
						),
						'required'   => array( 'title', 'content' ),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'update_post',
					'description' => 'Update an existing post or page. Only the fields you supply are changed; omitted fields are left as-is. Always call get_post first to review the current content.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'    => array(
								'type'        => 'integer',
								'description' => 'The WordPress post ID to update.',
							),
							'title'      => array(
								'type'        => 'string',
								'description' => 'New post title.',
							),
							'content'    => array(
								'type'        => 'string',
								'description' => 'New post body.',
							),
							'excerpt'    => array(
								'type'        => 'string',
								'description' => 'New excerpt.',
							),
							'status'     => array(
								'type'        => 'string',
								'description' => '"draft", "publish", "pending", or "private". Only publish with explicit user approval.',
							),
							'categories' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'integer' ),
								'description' => 'Replace all category assignments with these IDs.',
							),
							'tags'       => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Replace all tag assignments with these names.',
							),
							'slug'       => array(
								'type'        => 'string',
								'description' => 'New URL slug.',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),

			// -- Taxonomy ----------------------------------------------------

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'manage_categories',
					'description' => 'List existing categories, create a new category, or get posts in a specific category.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'action'      => array(
								'type'        => 'string',
								'description' => '"list" (default), "create", or "get_posts".',
							),
							'name'        => array(
								'type'        => 'string',
								'description' => 'Category name. Required for "create".',
							),
							'parent_id'   => array(
								'type'        => 'integer',
								'description' => 'Parent category ID for nested categories. Optional for "create".',
							),
							'category_id' => array(
								'type'        => 'integer',
								'description' => 'Category ID. Required for "get_posts".',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'manage_tags',
					'description' => 'List the most-used tags, create a new tag, or get all tags on a specific post.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'action'  => array(
								'type'        => 'string',
								'description' => '"list" (default), "create", or "get_post_tags".',
							),
							'name'    => array(
								'type'        => 'string',
								'description' => 'Tag name. Required for "create".',
							),
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'Post ID. Required for "get_post_tags".',
							),
						),
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
			'list_posts'        => $this->tool_list_posts( $arguments ),
			'get_post'          => $this->tool_get_post( $arguments ),
			'get_site_context'  => $this->tool_get_site_context(),
			'get_author_list'   => $this->tool_get_author_list(),
			'analyze_post'      => $this->tool_analyze_post( $arguments ),
			'create_post'       => $this->tool_create_post( $arguments ),
			'update_post'       => $this->tool_update_post( $arguments ),
			'manage_categories' => $this->tool_manage_categories( $arguments ),
			'manage_tags'       => $this->tool_manage_tags( $arguments ),
			default             => null,
		};
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	private function tool_list_posts( array $args ): array {
		$post_type = in_array( $args['post_type'] ?? 'post', array( 'post', 'page' ), true ) ? $args['post_type'] : 'post';
		$status    = $args['status'] ?? 'any';
		$limit     = min( max( (int) ( $args['limit'] ?? 10 ), 1 ), 50 );
		$offset    = max( (int) ( $args['offset'] ?? 0 ), 0 );
		$search    = sanitize_text_field( $args['search'] ?? '' );

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $search ) {
			$query_args['s'] = $search;
		}

		$posts = get_posts( $query_args );

		return array(
			'total_returned' => count( $posts ),
			'offset'         => $offset,
			'posts'          => array_map(
				fn( $p ) => array(
					'id'         => $p->ID,
					'title'      => $p->post_title,
					'status'     => $p->post_status,
					'date'       => $p->post_date,
					'modified'   => $p->post_modified,
					'word_count' => str_word_count( wp_strip_all_tags( $p->post_content ) ),
					'url'        => get_permalink( $p->ID ),
				),
				$posts
			),
		);
	}

	private function tool_get_post( array $args ): array {
		$post = get_post( (int) ( $args['post_id'] ?? 0 ) );

		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$categories = get_the_category( $post->ID );
		$tags       = get_the_tags( $post->ID );

		return array(
			'id'         => $post->ID,
			'post_type'  => $post->post_type,
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
			'status'     => $post->post_status,
			'slug'       => $post->post_name,
			'author_id'  => (int) $post->post_author,
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
			'url'        => get_permalink( $post->ID ),
			'categories' => array_map( fn( $c ) => array( 'id' => $c->term_id, 'name' => $c->name ), $categories ),
			'tags'       => $tags ? array_map( fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name ), $tags ) : array(),
		);
	}

	private function tool_get_site_context(): array {
		$categories = get_categories( array( 'hide_empty' => false, 'number' => 30 ) );
		$tags       = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 20 ) );

		return array(
			'site_name'        => get_bloginfo( 'name' ),
			'site_description' => get_bloginfo( 'description' ),
			'site_url'         => get_bloginfo( 'url' ),
			'categories'       => array_map(
				fn( $c ) => array( 'id' => $c->term_id, 'name' => $c->name, 'count' => $c->count ),
				$categories
			),
			'popular_tags'     => array_map(
				fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'count' => $t->count ),
				$tags
			),
			'total_posts'      => wp_count_posts()->publish,
			'total_pages'      => wp_count_posts( 'page' )->publish,
		);
	}

	private function tool_get_author_list(): array {
		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'editor', 'author' ),
				'fields'   => array( 'ID', 'display_name', 'user_email', 'user_login' ),
				'orderby'  => 'display_name',
			)
		);

		return array(
			'authors' => array_map(
				fn( $u ) => array(
					'id'           => (int) $u->ID,
					'display_name' => $u->display_name,
					'user_login'   => $u->user_login,
				),
				$users
			),
		);
	}

	private function tool_analyze_post( array $args ): array {
		$post = get_post( (int) ( $args['post_id'] ?? 0 ) );

		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}

		$raw_content = $post->post_content;
		$plain_text  = wp_strip_all_tags( $raw_content );

		// Word and sentence counts.
		$word_count = str_word_count( $plain_text );
		$sentences  = preg_split( '/[.!?]+(?:\s|$)/', $plain_text, -1, PREG_SPLIT_NO_EMPTY );
		$sent_count = max( 1, count( $sentences ) );

		// Syllable count + Flesch-Kincaid Reading Ease.
		$syllable_count = $this->count_syllables( $plain_text );
		$fk_score       = 0;
		if ( $word_count > 0 ) {
			$fk_score = 206.835
				- ( 1.015 * ( $word_count / $sent_count ) )
				- ( 84.6 * ( $syllable_count / $word_count ) );
			$fk_score = round( $fk_score, 1 );
		}

		$fk_label = match ( true ) {
			$fk_score >= 70  => 'Easy (suitable for most readers)',
			$fk_score >= 50  => 'Fairly difficult (college level)',
			$fk_score >= 30  => 'Difficult (college graduate level)',
			default          => 'Very difficult (academic / specialist)',
		};

		// Structural analysis.
		preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', $raw_content, $headings );
		preg_match_all( '/<img[^>]+>/i', $raw_content, $images );
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $raw_content, $links );

		$internal_links = 0;
		$external_links = 0;
		$site_url       = untrailingslashit( get_bloginfo( 'url' ) );
		foreach ( $links[1] as $href ) {
			if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
				++$internal_links;
			} else {
				++$external_links;
			}
		}

		// SEO keyword density.
		$keyword_data = array();
		if ( ! empty( $args['focus_keyword'] ) ) {
			$kw         = strtolower( trim( $args['focus_keyword'] ) );
			$lc_content = strtolower( $plain_text );
			$kw_count   = substr_count( $lc_content, $kw );
			$density    = $word_count > 0 ? round( ( $kw_count / $word_count ) * 100, 2 ) : 0;

			$in_title   = str_contains( strtolower( $post->post_title ), $kw );
			$in_excerpt = str_contains( strtolower( $post->post_excerpt ), $kw );
			$in_h1      = false;
			foreach ( $headings[2] as $i => $heading_text ) {
				if ( '1' === $headings[1][ $i ] && str_contains( strtolower( wp_strip_all_tags( $heading_text ) ), $kw ) ) {
					$in_h1 = true;
				}
			}

			$keyword_data = array(
				'keyword'         => $args['focus_keyword'],
				'occurrences'     => $kw_count,
				'density_percent' => $density,
				'in_title'        => $in_title,
				'in_excerpt'      => $in_excerpt,
				'in_h1'           => $in_h1,
				'density_note'    => $density < 0.5 ? 'Low — try to use the keyword more naturally' :
					( $density > 3.0 ? 'High — may read as keyword stuffing' : 'Good range (0.5–3%)' ),
			);
		}

		// Recommendations.
		$recs = array();
		if ( $word_count < 300 ) {
			$recs[] = 'Content is short. Aim for at least 600–800 words for search visibility.';
		}
		if ( ( $word_count / $sent_count ) > 25 ) {
			$recs[] = 'Average sentence length is long. Break sentences up to improve readability.';
		}
		if ( count( $headings[0] ) < 2 && $word_count > 400 ) {
			$recs[] = 'Add subheadings (H2/H3) to break up long content.';
		}
		if ( count( $images[0] ) === 0 ) {
			$recs[] = 'No images found. Adding at least one image improves engagement.';
		}
		if ( empty( $post->post_excerpt ) ) {
			$recs[] = 'No excerpt set. Write a compelling 1–2 sentence excerpt.';
		}
		if ( $fk_score < 50 && $word_count > 100 ) {
			$recs[] = 'Readability score is low. Simplify vocabulary and shorten sentences.';
		}

		return array(
			'id'                      => $post->ID,
			'title'                   => $post->post_title,
			'status'                  => $post->post_status,
			'word_count'              => $word_count,
			'sentence_count'          => $sent_count,
			'avg_words_per_sentence'  => round( $word_count / $sent_count, 1 ),
			'flesch_kincaid_score'    => $fk_score,
			'readability_level'       => $fk_label,
			'heading_count'           => count( $headings[0] ),
			'image_count'             => count( $images[0] ),
			'internal_links'          => $internal_links,
			'external_links'          => $external_links,
			'has_excerpt'             => ! empty( $post->post_excerpt ),
			'focus_keyword_analysis'  => $keyword_data ?: null,
			'recommendations'         => $recs,
		);
	}

	private function tool_create_post( array $args ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return array( 'error' => 'You do not have permission to create posts.' );
		}

		$post_type = in_array( $args['post_type'] ?? 'post', array( 'post', 'page' ), true ) ? ( $args['post_type'] ?? 'post' ) : 'post';
		$status    = in_array( $args['status'] ?? 'draft', array( 'draft', 'publish', 'pending', 'private' ), true ) ? ( $args['status'] ?? 'draft' ) : 'draft';

		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return array( 'error' => 'You do not have permission to publish posts.' );
		}

		$post_data = array(
			'post_title'   => sanitize_text_field( $args['title'] ),
			'post_content' => wp_kses_post( $args['content'] ),
			'post_status'  => $status,
			'post_type'    => $post_type,
		);

		if ( ! empty( $args['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
		}
		if ( ! empty( $args['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $args['slug'] );
		}
		if ( ! empty( $args['author_id'] ) ) {
			$post_data['post_author'] = (int) $args['author_id'];
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => $post_id->get_error_message() );
		}

		if ( ! empty( $args['categories'] ) && is_array( $args['categories'] ) ) {
			wp_set_post_categories( $post_id, array_map( 'intval', $args['categories'] ) );
		}
		if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $args['tags'] ) );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'status'  => $status,
			'url'     => get_permalink( $post_id ),
			'message' => 'publish' === $status ? "Post published at " . get_permalink( $post_id ) : "Draft saved (ID: {$post_id}).",
		);
	}

	private function tool_update_post( array $args ): array {
		$post_id = (int) ( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => 'Post not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'error' => 'You do not have permission to edit this post.' );
		}

		$post_data = array( 'ID' => $post_id );

		if ( isset( $args['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
		}
		if ( isset( $args['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $args['slug'] );
		}

		if ( isset( $args['status'] ) ) {
			$allowed_statuses = array( 'draft', 'publish', 'pending', 'private' );
			$new_status       = in_array( $args['status'], $allowed_statuses, true ) ? $args['status'] : null;
			if ( $new_status ) {
				if ( 'publish' === $new_status && ! current_user_can( 'publish_posts' ) ) {
					return array( 'error' => 'You do not have permission to publish posts.' );
				}
				$post_data['post_status'] = $new_status;
			}
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		if ( isset( $args['categories'] ) && is_array( $args['categories'] ) ) {
			wp_set_post_categories( $post_id, array_map( 'intval', $args['categories'] ) );
		}
		if ( isset( $args['tags'] ) && is_array( $args['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $args['tags'] ) );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'status'  => get_post_status( $post_id ),
			'url'     => get_permalink( $post_id ),
			'message' => "Post ID {$post_id} updated successfully.",
		);
	}

	private function tool_manage_categories( array $args ): array {
		$action = $args['action'] ?? 'list';

		if ( 'create' === $action ) {
			if ( ! current_user_can( 'manage_categories' ) ) {
				return array( 'error' => 'You do not have permission to manage categories.' );
			}
			if ( empty( $args['name'] ) ) {
				return array( 'error' => 'A category name is required.' );
			}
			$term = wp_insert_term(
				sanitize_text_field( $args['name'] ),
				'category',
				array( 'parent' => (int) ( $args['parent_id'] ?? 0 ) )
			);
			if ( is_wp_error( $term ) ) {
				return array( 'error' => $term->get_error_message() );
			}
			return array( 'success' => true, 'category_id' => $term['term_id'], 'name' => $args['name'] );
		}

		if ( 'get_posts' === $action ) {
			if ( empty( $args['category_id'] ) ) {
				return array( 'error' => 'A category_id is required.' );
			}
			$posts = get_posts(
				array(
					'category'       => (int) $args['category_id'],
					'posts_per_page' => 20,
					'post_status'    => 'any',
				)
			);
			return array(
				'posts' => array_map(
					fn( $p ) => array( 'id' => $p->ID, 'title' => $p->post_title, 'status' => $p->post_status ),
					$posts
				),
			);
		}

		// Default: list.
		$categories = get_categories( array( 'hide_empty' => false, 'number' => 50 ) );
		return array(
			'categories' => array_map(
				fn( $c ) => array( 'id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'parent' => $c->parent, 'count' => $c->count ),
				$categories
			),
		);
	}

	private function tool_manage_tags( array $args ): array {
		$action = $args['action'] ?? 'list';

		if ( 'create' === $action ) {
			if ( ! current_user_can( 'manage_categories' ) ) {
				return array( 'error' => 'You do not have permission to manage tags.' );
			}
			if ( empty( $args['name'] ) ) {
				return array( 'error' => 'A tag name is required.' );
			}
			$term = wp_insert_term( sanitize_text_field( $args['name'] ), 'post_tag' );
			if ( is_wp_error( $term ) ) {
				return array( 'error' => $term->get_error_message() );
			}
			return array( 'success' => true, 'tag_id' => $term['term_id'], 'name' => $args['name'] );
		}

		if ( 'get_post_tags' === $action ) {
			if ( empty( $args['post_id'] ) ) {
				return array( 'error' => 'A post_id is required.' );
			}
			$tags = get_the_tags( (int) $args['post_id'] );
			return array(
				'tags' => $tags ? array_map( fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ), $tags ) : array(),
			);
		}

		// Default: list popular.
		$tags = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 50 ) );
		return array(
			'tags' => array_map(
				fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count ),
				$tags
			),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Estimate syllable count using a vowel-group heuristic.
	 * Good enough for Flesch-Kincaid scoring — not a dictionary lookup.
	 */
	private function count_syllables( string $text ): int {
		$words = preg_split( '/\s+/', mb_strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$total = 0;

		foreach ( $words as $word ) {
			$word = preg_replace( '/[^a-z]/', '', $word );
			if ( ! $word ) {
				continue;
			}
			// Count vowel groups as syllables.
			$count = preg_match_all( '/[aeiouy]+/', $word );
			// Subtract silent trailing 'e'.
			if ( strlen( $word ) > 2 && str_ends_with( $word, 'e' ) ) {
				--$count;
			}
			$total += max( 1, $count );
		}

		return $total;
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Content_Writer() );
	}
);
