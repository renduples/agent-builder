<?php
/**
 * Agent Name: Content Assistant
 * Version: 1.1.0
 * Description: Helps draft, edit, and optimize blog posts and pages. Suggests improvements, fixes grammar, and enhances readability.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Content
 * Tags: writing, editing, posts, pages, drafts, grammar, optimization
 * Capabilities: edit_posts
 * Icon: 📝
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Assistant Agent
 *
 * A true AI agent specialized in content creation and optimization.
 */
class Agentic_Content_Assistant extends \Agentic\Agent_Base {

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'content-assistant';
	}

	public function get_name(): string {
		return 'Content Assistant';
	}

	public function get_description(): string {
		return 'Helps draft, edit, and optimize blog posts and pages.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '📝';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_required_capabilities(): array {
		return array( 'edit_posts' );
	}

	public function get_welcome_message(): string {
		return "📝 **Content Assistant**\n\n" .
				"I'm here to help you create and improve your content!\n\n" .
				"- **Analyze posts** for readability and engagement\n" .
				"- **Suggest titles** that grab attention\n" .
				"- **Improve excerpts** for better click-through\n" .
				"- **Edit content** for clarity and flow\n\n" .
				'What would you like help with?';
	}

	public function get_suggested_prompts(): array {
		return array(
			'Analyze my latest post',
			'Help me write a better title',
			'How can I improve this paragraph?',
			'Check my post for readability',
		);
	}

	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'analyze_content',
					'description' => 'Analyze a post for readability and structure (word count, sentence length, headings, images, links).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'The ID of the post to analyze',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_recent_posts',
					'description' => 'Get a list of recent posts to work with.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'status' => array(
								'type'        => 'string',
								'description' => 'Post status: draft, publish, or any',
							),
							'limit'  => array(
								'type'        => 'integer',
								'description' => 'Number of posts to return',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_post_content',
					'description' => 'Get the full content of a specific post for review.',
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
			'analyze_content'  => $this->tool_analyze_content( $arguments ),
			'get_recent_posts' => $this->tool_get_recent_posts( $arguments ),
			'get_post_content' => $this->tool_get_post_content( $arguments ),
			default            => null,
		};
	}

	private function tool_analyze_content( array $args ): array {
		$post = get_post( $args['post_id'] ?? 0 );

		if ( ! $post ) {
			return array( 'error' => 'Post not found' );
		}

		$content             = wp_strip_all_tags( $post->post_content );
		$word_count          = str_word_count( $content );
		$sentences           = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
		$avg_sentence_length = $word_count / max( 1, count( $sentences ) );

		preg_match_all( '/<h[1-6][^>]*>.*?<\/h[1-6]>/i', $post->post_content, $headings );
		preg_match_all( '/<img[^>]+>/i', $post->post_content, $images );
		preg_match_all( '/<a[^>]+href/i', $post->post_content, $links );

		$recommendations = array();
		if ( $word_count < 300 ) {
			$recommendations[] = 'Content is short - consider expanding to 300+ words for better engagement';
		}
		if ( $avg_sentence_length > 20 ) {
			$recommendations[] = 'Sentences are long - try breaking them up for readability';
		}
		if ( count( $headings[0] ) < 2 && $word_count > 500 ) {
			$recommendations[] = 'Add more headings to improve scannability';
		}
		if ( count( $images[0] ) === 0 && $word_count > 200 ) {
			$recommendations[] = 'Consider adding images for visual interest';
		}

		return array(
			'title'               => $post->post_title,
			'word_count'          => $word_count,
			'sentence_count'      => count( $sentences ),
			'avg_sentence_length' => round( $avg_sentence_length, 1 ),
			'heading_count'       => count( $headings[0] ),
			'image_count'         => count( $images[0] ),
			'link_count'          => count( $links[0] ),
			'has_excerpt'         => ! empty( $post->post_excerpt ),
			'status'              => $post->post_status,
			'recommendations'     => $recommendations,
		);
	}

	private function tool_get_recent_posts( array $args ): array {
		$status = $args['status'] ?? 'any';
		$limit  = min( $args['limit'] ?? 10, 20 );

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => $status,
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array(
			'posts' => array_map(
				fn( $p ) => array(
					'id'     => $p->ID,
					'title'  => $p->post_title,
					'status' => $p->post_status,
					'date'   => $p->post_date,
					'words'  => str_word_count( wp_strip_all_tags( $p->post_content ) ),
				),
				$posts
			),
		);
	}

	private function tool_get_post_content( array $args ): array {
		$post = get_post( $args['post_id'] ?? 0 );

		if ( ! $post ) {
			return array( 'error' => 'Post not found' );
		}

		return array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status'  => $post->post_status,
		);
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Content_Assistant() );
	}
);
