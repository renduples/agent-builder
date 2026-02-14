<?php
/**
 * Agent Name: Comment Moderator
 * Version: 1.1.0
 * Description: Automatically moderates comments, detects spam, and helps maintain healthy discussions on your site.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Admin
 * Tags: comments, moderation, spam, discussions, community
 * Capabilities: moderate_comments
 * Icon: 💬
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comment Moderator Agent
 *
 * A true AI agent specialized in comment moderation and community management.
 */
class Agentic_Comment_Moderator extends \Agentic\Agent_Base {

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'comment-moderator';
	}

	public function get_name(): string {
		return 'Comment Moderator';
	}

	public function get_description(): string {
		return 'Moderates comments and maintains healthy discussions.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '💬';
	}

	public function get_category(): string {
		return 'admin';
	}

	public function get_required_capabilities(): array {
		return array( 'moderate_comments' );
	}

	public function get_welcome_message(): string {
		return "💬 **Comment Moderator**\n\n" .
				"I help keep your discussions healthy and spam-free!\n\n" .
				"- **Review pending** comments for approval\n" .
				"- **Analyze comments** for spam or issues\n" .
				"- **Get statistics** on your moderation queue\n" .
				"- **Set guidelines** for automatic moderation\n\n" .
				'How can I help with moderation?';
	}

	public function get_suggested_prompts(): array {
		return array(
			'Show me pending comments',
			'Analyze recent comments for spam',
			'How many comments are awaiting moderation?',
			'Review comments from today',
		);
	}

	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_pending_comments',
					'description' => 'Get comments awaiting moderation.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Number of comments to return',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'analyze_comment',
					'description' => 'Analyze a specific comment for spam indicators.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'comment_id' => array(
								'type'        => 'integer',
								'description' => 'The ID of the comment to analyze',
							),
						),
						'required'   => array( 'comment_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_moderation_stats',
					'description' => 'Get comment moderation statistics.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
		);
	}

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'get_pending_comments' => $this->tool_get_pending( $arguments ),
			'analyze_comment'      => $this->tool_analyze_comment( $arguments ),
			'get_moderation_stats' => $this->tool_get_stats(),
			default                => null,
		};
	}

	private function tool_get_pending( array $args ): array {
		$limit = min( $args['limit'] ?? 10, 50 );

		$comments = get_comments(
			array(
				'status'  => 'hold',
				'number'  => $limit,
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			)
		);

		return array(
			'pending_count' => count( $comments ),
			'comments'      => array_map(
				fn( $c ) => array(
					'id'      => $c->comment_ID,
					'author'  => $c->comment_author,
					'email'   => $c->comment_author_email,
					'content' => wp_trim_words( $c->comment_content, 30 ),
					'post_id' => $c->comment_post_ID,
					'date'    => $c->comment_date,
				),
				$comments
			),
		);
	}

	private function tool_analyze_comment( array $args ): array {
		$comment = get_comment( $args['comment_id'] ?? 0 );

		if ( ! $comment ) {
			return array( 'error' => 'Comment not found' );
		}

		$content    = $comment->comment_content;
		$indicators = array();
		$spam_score = 0;

		// Check for URLs
		$url_count = preg_match_all( '/https?:\/\//i', $content );
		if ( $url_count > 2 ) {
			$indicators[] = 'Multiple URLs detected';
			$spam_score  += 30;
		}

		// Check for typical spam patterns
		$spam_patterns = array( 'buy now', 'click here', 'free money', 'casino', 'viagra' );
		foreach ( $spam_patterns as $pattern ) {
			if ( stripos( $content, $pattern ) !== false ) {
				$indicators[] = "Spam keyword: '{$pattern}'";
				$spam_score  += 40;
			}
		}

		// Check comment length
		if ( strlen( $content ) < 10 ) {
			$indicators[] = 'Very short comment';
			$spam_score  += 10;
		}

		// Check for ALL CAPS
		if ( strtoupper( $content ) === $content && strlen( $content ) > 20 ) {
			$indicators[] = 'All caps detected';
			$spam_score  += 15;
		}

		// Check author email
		if ( ! is_email( $comment->comment_author_email ) ) {
			$indicators[] = 'Invalid email format';
			$spam_score  += 20;
		}

		$recommendation = 'approve';
		if ( $spam_score >= 50 ) {
			$recommendation = 'spam';
		} elseif ( $spam_score >= 25 ) {
			$recommendation = 'review';
		}

		return array(
			'comment_id'     => $comment->comment_ID,
			'author'         => $comment->comment_author,
			'content'        => $content,
			'spam_score'     => min( 100, $spam_score ),
			'indicators'     => $indicators,
			'recommendation' => $recommendation,
			'status'         => $comment->comment_approved,
		);
	}

	private function tool_get_stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query.
		$stats = $wpdb->get_results(
			"
            SELECT comment_approved, COUNT(*) as count 
            FROM {$wpdb->comments} 
            GROUP BY comment_approved
        ",
			ARRAY_A
		);

		$counts = array(
			'approved' => 0,
			'pending'  => 0,
			'spam'     => 0,
			'trash'    => 0,
		);

		foreach ( $stats as $row ) {
			$status = $row['comment_approved'];
			$count  = (int) $row['count'];

			if ( $status === '1' ) {
				$counts['approved'] = $count;
			} elseif ( $status === '0' ) {
				$counts['pending'] = $count;
			} elseif ( $status === 'spam' ) {
				$counts['spam'] = $count;
			} elseif ( $status === 'trash' ) {
				$counts['trash'] = $count;
			}
		}

		return array(
			'total'    => array_sum( $counts ),
			'approved' => $counts['approved'],
			'pending'  => $counts['pending'],
			'spam'     => $counts['spam'],
			'trash'    => $counts['trash'],
		);
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Comment_Moderator() );
	}
);
