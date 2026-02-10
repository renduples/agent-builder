<?php
/**
 * Agent Name: Social Media Manager
 * Version: 1.0.0
 * Description: Manages social media content for WordPress. Creates, schedules, and organizes posts for multiple platforms including LinkedIn, X/Twitter, Facebook, Medium, Dev.to, and more.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Marketing
 * Tags: social media, marketing, content, scheduling, campaigns, twitter, linkedin, facebook
 * Capabilities: publish_posts, edit_posts
 * Icon: 📱
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Social Media Manager Agent
 *
 * An AI agent specialized in social media content management.
 * Creates, schedules, and organizes content for multiple platforms.
 */
class Agentic_Social_Media_Manager extends \Agentic\Agent_Base {

	/**
	 * Load system prompt from template file
	 */
	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'social-media';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Social Media Manager';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Manages social media campaigns. Creates and organizes content for LinkedIn, X, Facebook, Medium, and more.';
	}

	/**
	 * Get system prompt
	 */
	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	/**
	 * Get agent icon
	 */
	public function get_icon(): string {
		return '📱';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'Marketing';
	}

	/**
	 * Get required capabilities
	 */
	public function get_required_capabilities(): array {
		return array( 'publish_posts' );
	}

	/**
	 * Get welcome message
	 */
	public function get_welcome_message(): string {
		return "📱 **Social Media Manager**\n\n" .
				"I help you create and manage social media campaigns!\n\n" .
				"- **Create content** for any platform\n" .
				"- **Plan campaigns** with content calendars\n" .
				"- **Organize posts** by platform category\n" .
				"- **Optimize** for engagement and reach\n" .
				"- **Track** campaign progress\n\n" .
				'What would you like to create or manage?';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'Show my social media categories',
			'Create a Twitter thread about our launch',
			'Generate LinkedIn posts for this week',
			'Plan a 4-week campaign for our product',
		);
	}

	/**
	 * Get available tools
	 */
	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_platforms',
					'description' => 'List available social media platform categories with post counts',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_campaign_posts',
					'description' => 'Get all posts for a specific platform/category or all campaign posts',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'platform' => array(
								'type'        => 'string',
								'description' => 'Platform category slug (e.g., "linkedin", "x", "medium"). Leave empty for all.',
							),
							'status'   => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish', 'future', 'all' ),
								'description' => 'Post status filter (default: all)',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_social_post',
					'description' => 'Create a new social media post for a specific platform',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'platform' => array(
								'type'        => 'string',
								'description' => 'Platform category slug (linkedin, x, facebook, medium, dev-to, reddit, hacker-news, product-hunt)',
							),
							'title'    => array(
								'type'        => 'string',
								'description' => 'Post title (for internal organization)',
							),
							'content'  => array(
								'type'        => 'string',
								'description' => 'The actual post content optimized for the platform',
							),
							'week'     => array(
								'type'        => 'integer',
								'description' => 'Campaign week number (1-4) for organization',
							),
							'status'   => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish' ),
								'description' => 'Post status (default: draft)',
							),
							'hashtags' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Hashtags to include',
							),
						),
						'required'   => array( 'platform', 'title', 'content' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_twitter_thread',
					'description' => 'Create a Twitter/X thread as multiple connected posts',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'title'  => array(
								'type'        => 'string',
								'description' => 'Thread title (for internal organization)',
							),
							'tweets' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => 'Array of tweet contents (each max 280 chars)',
							),
							'week'   => array(
								'type'        => 'integer',
								'description' => 'Campaign week number',
							),
						),
						'required'   => array( 'title', 'tweets' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_content',
					'description' => 'Generate optimized content for a specific platform from a topic',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'platform'    => array(
								'type'        => 'string',
								'description' => 'Target platform (linkedin, x, facebook, medium, dev-to, reddit, hacker-news, product-hunt)',
							),
							'topic'       => array(
								'type'        => 'string',
								'description' => 'Topic or key message to create content about',
							),
							'tone'        => array(
								'type'        => 'string',
								'enum'        => array( 'professional', 'casual', 'technical', 'enthusiastic', 'educational' ),
								'description' => 'Desired tone of the content',
							),
							'include_cta' => array(
								'type'        => 'boolean',
								'description' => 'Include a call to action (default: true)',
							),
						),
						'required'   => array( 'platform', 'topic' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'update_social_post',
					'description' => 'Update an existing social media post',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'WordPress post ID to update',
							),
							'content' => array(
								'type'        => 'string',
								'description' => 'New content for the post',
							),
							'title'   => array(
								'type'        => 'string',
								'description' => 'New title (optional)',
							),
							'status'  => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish' ),
								'description' => 'Change post status',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'delete_social_post',
					'description' => 'Delete a social media post',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'WordPress post ID to delete',
							),
							'force'   => array(
								'type'        => 'boolean',
								'description' => 'Permanently delete (true) or move to trash (false, default)',
							),
						),
						'required'   => array( 'post_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_campaign_stats',
					'description' => 'Get statistics about the current campaign',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_platform_category',
					'description' => 'Create a new platform category if it does not exist',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array(
								'type'        => 'string',
								'description' => 'Platform name (e.g., "LinkedIn", "X", "TikTok")',
							),
							'slug'        => array(
								'type'        => 'string',
								'description' => 'Category slug (optional, auto-generated from name)',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'Platform description',
							),
						),
						'required'   => array( 'name' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_hashtag_suggestions',
					'description' => 'Get hashtag suggestions for a topic and platform',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'topic'    => array(
								'type'        => 'string',
								'description' => 'Topic to get hashtags for',
							),
							'platform' => array(
								'type'        => 'string',
								'description' => 'Target platform (affects hashtag format)',
							),
						),
						'required'   => array( 'topic' ),
					),
				),
			),
		);
	}

	/**
	 * Execute a tool
	 */
	public function execute_tool( string $tool_name, array $arguments ): ?array {
		return match ( $tool_name ) {
			'list_platforms'          => $this->tool_list_platforms(),
			'get_campaign_posts'      => $this->tool_get_campaign_posts( $arguments ),
			'create_social_post'      => $this->tool_create_social_post( $arguments ),
			'create_twitter_thread'   => $this->tool_create_twitter_thread( $arguments ),
			'generate_content'        => $this->tool_generate_content( $arguments ),
			'update_social_post'      => $this->tool_update_social_post( $arguments ),
			'delete_social_post'      => $this->tool_delete_social_post( $arguments ),
			'get_campaign_stats'      => $this->tool_get_campaign_stats(),
			'create_platform_category'=> $this->tool_create_platform_category( $arguments ),
			'get_hashtag_suggestions' => $this->tool_get_hashtag_suggestions( $arguments ),
			default                   => array( 'error' => 'Unknown tool: ' . $tool_name ),
		};
	}

	/**
	 * Platform category slugs we recognize
	 */
	private function get_platform_slugs(): array {
		return array(
			'linkedin'     => 'LinkedIn',
			'x'            => 'X (Twitter)',
			'twitter'      => 'X (Twitter)',
			'facebook'     => 'Facebook',
			'medium'       => 'Medium',
			'dev-to'       => 'Dev.to',
			'devto'        => 'Dev.to',
			'reddit'       => 'Reddit',
			'hacker-news'  => 'Hacker News',
			'hackernews'   => 'Hacker News',
			'product-hunt' => 'Product Hunt',
			'producthunt'  => 'Product Hunt',
			'threads'      => 'Threads',
			'mastodon'     => 'Mastodon',
			'bluesky'      => 'Bluesky',
		);
	}

	/**
	 * List platform categories
	 */
	private function tool_list_platforms(): array {
		$platform_slugs = array_keys( $this->get_platform_slugs() );
		$categories     = get_categories(
			array(
				'hide_empty' => false,
			)
		);

		$platforms        = array();
		$other_categories = array();

		foreach ( $categories as $cat ) {
			if ( in_array( $cat->slug, $platform_slugs, true ) ) {
				$platforms[] = array(
					'id'          => $cat->term_id,
					'name'        => $cat->name,
					'slug'        => $cat->slug,
					'post_count'  => $cat->count,
					'description' => $cat->description,
				);
			} else {
				$other_categories[] = array(
					'id'         => $cat->term_id,
					'name'       => $cat->name,
					'slug'       => $cat->slug,
					'post_count' => $cat->count,
				);
			}
		}

		return array(
			'platforms'        => $platforms,
			'platform_count'   => count( $platforms ),
			'other_categories' => $other_categories,
			'tip'              => empty( $platforms )
				? 'No platform categories found. Use create_platform_category to add them.'
				: null,
		);
	}

	/**
	 * Get campaign posts
	 */
	private function tool_get_campaign_posts( array $args ): array {
		$platform = $args['platform'] ?? '';
		$status   = $args['status'] ?? 'all';

		$query_args = array(
			'post_type'      => 'post',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Status filter
		if ( $status === 'all' ) {
			$query_args['post_status'] = array( 'publish', 'draft', 'future' );
		} else {
			$query_args['post_status'] = $status;
		}

		// Platform/category filter
		if ( ! empty( $platform ) ) {
			$query_args['category_name'] = $platform;
		} else {
			// Only get posts in our platform categories
			$platform_slugs              = array_keys( $this->get_platform_slugs() );
			$query_args['category_name'] = implode( ',', $platform_slugs );
		}

		$query = new WP_Query( $query_args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );

			$posts[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'content'      => wp_trim_words( $post->post_content, 50 ),
				'full_content' => $post->post_content,
				'status'       => $post->post_status,
				'date'         => $post->post_date,
				'platform'     => $categories[0] ?? 'uncategorized',
				'categories'   => $categories,
				'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return array(
			'posts'    => $posts,
			'count'    => count( $posts ),
			'platform' => $platform ?: 'all platforms',
			'status'   => $status,
		);
	}

	/**
	 * Create a social media post
	 */
	private function tool_create_social_post( array $args ): array {
		$platform = $args['platform'] ?? '';
		$title    = $args['title'] ?? '';
		$content  = $args['content'] ?? '';
		$week     = $args['week'] ?? null;
		$status   = $args['status'] ?? 'draft';
		$hashtags = $args['hashtags'] ?? array();

		if ( empty( $platform ) || empty( $title ) || empty( $content ) ) {
			return array( 'error' => 'Platform, title, and content are required' );
		}

		// Get or create the platform category
		$category = get_category_by_slug( $platform );
		if ( ! $category ) {
			// Try to create it
			$platform_names = $this->get_platform_slugs();
			$name           = $platform_names[ $platform ] ?? ucfirst( $platform );
			$result         = wp_insert_term( $name, 'category', array( 'slug' => $platform ) );

			if ( is_wp_error( $result ) ) {
				return array( 'error' => 'Platform category not found and could not be created: ' . $result->get_error_message() );
			}

			$category_id = $result['term_id'];
		} else {
			$category_id = $category->term_id;
		}

		// Add hashtags to content if provided
		if ( ! empty( $hashtags ) ) {
			$hashtag_string = implode(
				' ',
				array_map(
					function ( $tag ) {
						return strpos( $tag, '#' ) === 0 ? $tag : '#' . $tag;
					},
					$hashtags
				)
			);
			$content       .= "\n\n" . $hashtag_string;
		}

		// Add week tag if provided
		$tags = array();
		if ( $week ) {
			$tags[] = 'week-' . $week;
		}

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => $status,
			'post_type'     => 'post',
			'post_category' => array( $category_id ),
			'tags_input'    => $tags,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => 'Failed to create post: ' . $post_id->get_error_message() );
		}

		// Store metadata
		update_post_meta( $post_id, '_social_platform', $platform );
		if ( $week ) {
			update_post_meta( $post_id, '_campaign_week', $week );
		}
		if ( ! empty( $hashtags ) ) {
			update_post_meta( $post_id, '_social_hashtags', $hashtags );
		}

		return array(
			'success'    => true,
			'post_id'    => $post_id,
			'title'      => $title,
			'platform'   => $platform,
			'status'     => $status,
			'week'       => $week,
			'edit_link'  => get_edit_post_link( $post_id, 'raw' ),
			'char_count' => strlen( $content ),
		);
	}

	/**
	 * Create a Twitter thread
	 */
	private function tool_create_twitter_thread( array $args ): array {
		$title  = $args['title'] ?? '';
		$tweets = $args['tweets'] ?? array();
		$week   = $args['week'] ?? null;

		if ( empty( $title ) || empty( $tweets ) ) {
			return array( 'error' => 'Title and tweets array are required' );
		}

		// Format tweets into thread content
		$content = "🧵 **Thread**\n\n";
		foreach ( $tweets as $i => $tweet ) {
			$num      = $i + 1;
			$content .= "**{$num}.** {$tweet}\n\n";
		}

		// Get X category
		$category = get_category_by_slug( 'x' );
		if ( ! $category ) {
			$result      = wp_insert_term( 'X', 'category', array( 'slug' => 'x' ) );
			$category_id = is_wp_error( $result ) ? 1 : $result['term_id'];
		} else {
			$category_id = $category->term_id;
		}

		$tags = array( 'thread' );
		if ( $week ) {
			$tags[] = 'week-' . $week;
		}

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => 'draft',
			'post_type'     => 'post',
			'post_category' => array( $category_id ),
			'tags_input'    => $tags,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => 'Failed to create thread: ' . $post_id->get_error_message() );
		}

		// Store metadata
		update_post_meta( $post_id, '_social_platform', 'x' );
		update_post_meta( $post_id, '_thread_tweets', $tweets );
		update_post_meta( $post_id, '_tweet_count', count( $tweets ) );
		if ( $week ) {
			update_post_meta( $post_id, '_campaign_week', $week );
		}

		// Validate tweet lengths
		$warnings = array();
		foreach ( $tweets as $i => $tweet ) {
			$len = strlen( $tweet );
			if ( $len > 280 ) {
				$warnings[] = 'Tweet ' . ( $i + 1 ) . " is {$len} chars (max 280)";
			}
		}

		return array(
			'success'     => true,
			'post_id'     => $post_id,
			'title'       => $title,
			'tweet_count' => count( $tweets ),
			'platform'    => 'x',
			'week'        => $week,
			'warnings'    => $warnings ?: null,
			'edit_link'   => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * Generate content for a platform
	 */
	private function tool_generate_content( array $args ): array {
		$platform    = $args['platform'] ?? '';
		$topic       = $args['topic'] ?? '';
		$tone        = $args['tone'] ?? 'professional';
		$include_cta = $args['include_cta'] ?? true;

		if ( empty( $platform ) || empty( $topic ) ) {
			return array( 'error' => 'Platform and topic are required' );
		}

		// Return platform-specific guidance for the LLM to generate content
		$platform_specs = array(
			'linkedin'     => array(
				'max_length' => 3000,
				'format'     => 'Professional storytelling with line breaks for readability',
				'tone'       => 'Professional, thought leadership, authentic',
				'best_times' => 'Tue-Thu 8-10am, 12pm',
				'tips'       => array(
					'Start with a hook (first 2 lines visible before "see more")',
					'Use emojis sparingly for visual breaks',
					'Include a personal angle or story',
					'End with a question or call to action',
					'Use 3-5 relevant hashtags at the end',
				),
			),
			'x'            => array(
				'max_length' => 280,
				'format'     => 'Concise, punchy, conversational',
				'tone'       => 'Casual, witty, engaging',
				'best_times' => 'Mon-Fri 12-3pm, 5pm',
				'tips'       => array(
					'Hook in first few words',
					'Use thread format for longer content',
					'Include 1-3 hashtags max',
					'Tag relevant accounts if appropriate',
					'Consider adding a visual or GIF',
				),
			),
			'medium'       => array(
				'max_length' => 10000,
				'format'     => 'Long-form article with headers, code blocks, images',
				'tone'       => 'Educational, narrative, in-depth',
				'best_times' => 'Weekday mornings',
				'tips'       => array(
					'Compelling title and subtitle',
					'Use headers to break up content',
					'Include code examples with syntax highlighting',
					'Add relevant images',
					'Cross-post from Dev.to if applicable',
				),
			),
			'dev-to'       => array(
				'max_length' => 10000,
				'format'     => 'Technical tutorial/article with Markdown',
				'tone'       => 'Technical, helpful, developer-focused',
				'best_times' => 'Weekday mornings US time',
				'tips'       => array(
					'Use frontmatter for tags and cover image',
					'Include working code examples',
					'Be practical and actionable',
					'Engage with comments',
					'Consider series format for related topics',
				),
			),
			'reddit'       => array(
				'max_length' => 40000,
				'format'     => 'Self post or link with context',
				'tone'       => 'Authentic, not promotional, community-first',
				'best_times' => 'Sun-Thu 6-9am US time',
				'tips'       => array(
					'Read and follow subreddit rules',
					'Provide value, not just self-promotion',
					'Engage genuinely in comments',
					'Consider r/WordPress, r/webdev, r/programming',
					'Be transparent about being the creator',
				),
			),
			'hacker-news'  => array(
				'max_length' => 2000,
				'format'     => 'Show HN post with clear description',
				'tone'       => 'Technical, factual, humble',
				'best_times' => 'Weekday mornings US time',
				'tips'       => array(
					'Use "Show HN:" prefix for project launches',
					'Be concise and technical',
					'Highlight what makes it interesting technically',
					'Be prepared to answer tough questions',
					'Avoid marketing speak',
				),
			),
			'product-hunt' => array(
				'max_length' => 500,
				'format'     => 'Product launch with tagline, description, visuals',
				'tone'       => 'Enthusiastic, benefit-focused, concise',
				'best_times' => 'Tuesday 12:01am PST',
				'tips'       => array(
					'Prepare visuals and GIFs in advance',
					'Write a compelling tagline',
					'Prepare maker comment with backstory',
					'Line up early supporters',
					'Respond to all comments quickly',
				),
			),
			'facebook'     => array(
				'max_length' => 63206,
				'format'     => 'Conversational with visuals',
				'tone'       => 'Friendly, community-focused',
				'best_times' => 'Wed-Fri 1-4pm',
				'tips'       => array(
					'Use eye-catching visuals',
					'Keep text concise for feed visibility',
					'Encourage comments and shares',
					'Post to relevant groups',
					'Use Facebook-native video when possible',
				),
			),
		);

		$specs = $platform_specs[ $platform ] ?? array(
			'max_length' => 1000,
			'format'     => 'Standard social media post',
			'tone'       => $tone,
			'tips'       => array( 'Optimize for the platform audience' ),
		);

		return array(
			'platform'     => $platform,
			'topic'        => $topic,
			'tone'         => $tone,
			'include_cta'  => $include_cta,
			'specs'        => $specs,
			'instructions' => "Generate {$platform} content about: {$topic}. Use a {$tone} tone. " .
							"Max length: {$specs['max_length']} characters. " .
							( $include_cta ? 'Include a call to action.' : 'No explicit CTA needed.' ),
		);
	}

	/**
	 * Update a social post
	 */
	private function tool_update_social_post( array $args ): array {
		$post_id = $args['post_id'] ?? 0;
		$content = $args['content'] ?? null;
		$title   = $args['title'] ?? null;
		$status  = $args['status'] ?? null;

		if ( ! $post_id ) {
			return array( 'error' => 'Post ID is required' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found" );
		}

		$update_data = array( 'ID' => $post_id );

		if ( $content !== null ) {
			$update_data['post_content'] = $content;
		}
		if ( $title !== null ) {
			$update_data['post_title'] = $title;
		}
		if ( $status !== null ) {
			$update_data['post_status'] = $status;
		}

		$result = wp_update_post( $update_data, true );

		if ( is_wp_error( $result ) ) {
			return array( 'error' => 'Failed to update post: ' . $result->get_error_message() );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'updated' => array_keys(
				array_filter(
					array(
						'content' => $content !== null,
						'title'   => $title !== null,
						'status'  => $status !== null,
					)
				)
			),
		);
	}

	/**
	 * Delete a social post
	 */
	private function tool_delete_social_post( array $args ): array {
		$post_id = $args['post_id'] ?? 0;
		$force   = $args['force'] ?? false;

		if ( ! $post_id ) {
			return array( 'error' => 'Post ID is required' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => "Post {$post_id} not found" );
		}

		$title  = $post->post_title;
		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			return array( 'error' => 'Failed to delete post' );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'title'   => $title,
			'action'  => $force ? 'permanently deleted' : 'moved to trash',
		);
	}

	/**
	 * Get campaign statistics
	 */
	private function tool_get_campaign_stats(): array {
		$platform_slugs = array_keys( $this->get_platform_slugs() );

		$stats = array(
			'by_platform' => array(),
			'by_status'   => array(
				'draft'   => 0,
				'publish' => 0,
				'future'  => 0,
			),
			'by_week'     => array(),
			'total'       => 0,
		);

		// Get all platform categories
		foreach ( $platform_slugs as $slug ) {
			$category = get_category_by_slug( $slug );
			if ( $category ) {
				$stats['by_platform'][ $slug ] = $category->count;
				$stats['total']               += $category->count;
			}
		}

		// Get status breakdown
		foreach ( array( 'draft', 'publish', 'future' ) as $status ) {
			$count                         = wp_count_posts( 'post' )->$status ?? 0;
			$stats['by_status'][ $status ] = $count;
		}

		// Get week breakdown from post meta
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query.
		$week_counts = $wpdb->get_results(
			"SELECT meta_value as week, COUNT(*) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_campaign_week' 
             GROUP BY meta_value"
		);

		foreach ( $week_counts as $row ) {
			$stats['by_week'][ 'week_' . $row->week ] = (int) $row->count;
		}

		return $stats;
	}

	/**
	 * Create a platform category
	 */
	private function tool_create_platform_category( array $args ): array {
		$name        = $args['name'] ?? '';
		$slug        = $args['slug'] ?? sanitize_title( $name );
		$description = $args['description'] ?? '';

		if ( empty( $name ) ) {
			return array( 'error' => 'Platform name is required' );
		}

		// Check if exists
		$existing = get_category_by_slug( $slug );
		if ( $existing ) {
			return array(
				'success' => false,
				'message' => "Category '{$name}' already exists",
				'id'      => $existing->term_id,
				'slug'    => $existing->slug,
			);
		}

		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'        => $slug,
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			return array( 'error' => 'Failed to create category: ' . $result->get_error_message() );
		}

		return array(
			'success' => true,
			'id'      => $result['term_id'],
			'name'    => $name,
			'slug'    => $slug,
		);
	}

	/**
	 * Get hashtag suggestions
	 */
	private function tool_get_hashtag_suggestions( array $args ): array {
		$topic    = $args['topic'] ?? '';
		$platform = $args['platform'] ?? 'x';

		if ( empty( $topic ) ) {
			return array( 'error' => 'Topic is required' );
		}

		// Base hashtags for common topics
		$hashtag_library = array(
			'wordpress'  => array(
				'primary'   => array( '#WordPress', '#WP', '#WordPressDev' ),
				'community' => array( '#WPCommunity', '#WordPressCommunity' ),
				'technical' => array( '#PHP', '#WebDev', '#OpenSource' ),
			),
			'ai'         => array(
				'primary'   => array( '#AI', '#ArtificialIntelligence', '#MachineLearning' ),
				'community' => array( '#AITwitter', '#TechTwitter' ),
				'technical' => array( '#LLM', '#GPT', '#Claude', '#GenerativeAI' ),
			),
			'developer'  => array(
				'primary'   => array( '#Developer', '#Dev', '#Coding' ),
				'community' => array( '#DevCommunity', '#100DaysOfCode', '#CodeNewbie' ),
				'technical' => array( '#Programming', '#SoftwareEngineering', '#WebDevelopment' ),
			),
			'opensource' => array(
				'primary'   => array( '#OpenSource', '#OSS', '#FOSS' ),
				'community' => array( '#OpenSourceCommunity', '#GitHub' ),
				'technical' => array( '#GPL', '#MIT', '#Contributing' ),
			),
			'startup'    => array(
				'primary'   => array( '#Startup', '#IndieHacker', '#BuildInPublic' ),
				'community' => array( '#StartupLife', '#Founder', '#Entrepreneur' ),
				'technical' => array( '#SaaS', '#MicroSaaS', '#Bootstrap' ),
			),
		);

		// Match topic keywords
		$suggestions = array();
		$topic_lower = strtolower( $topic );

		foreach ( $hashtag_library as $key => $tags ) {
			if ( strpos( $topic_lower, $key ) !== false ) {
				$suggestions = array_merge( $suggestions, $tags['primary'], $tags['community'] );
			}
		}

		// Default suggestions
		if ( empty( $suggestions ) ) {
			$suggestions = array( '#Tech', '#Innovation', '#Digital' );
		}

		// Platform-specific adjustments
		$max_hashtags = match ( $platform ) {
			'x', 'twitter' => 3,
			'linkedin'     => 5,
			'facebook'     => 3,
			default        => 5,
		};

		$suggestions = array_unique( $suggestions );
		$suggestions = array_slice( $suggestions, 0, $max_hashtags * 2 );

		return array(
			'topic'       => $topic,
			'platform'    => $platform,
			'suggestions' => array_values( $suggestions ),
			'recommended' => array_slice( $suggestions, 0, $max_hashtags ),
			'tip'         => "Use {$max_hashtags} hashtags max for {$platform}",
		);
	}
}

// Register the agent
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Social_Media_Manager() );
	}
);
