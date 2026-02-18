<?php
/**
 * Agent Name: Theme Assistant
 * Version: 2.0.0
 * Description: Helps beginners choose and customise WordPress themes using the Site Editor. Detects your active theme, recommends themes, and guides you through visual customisation.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Starter
 * Tags: themes, design, site-editor, beginner, customisation, block-themes
 * Capabilities: read
 * Icon: 🎨
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Assistant Agent
 *
 * A beginner-friendly agent that helps users choose and customise
 * WordPress themes using the Site Editor. Does not install, download,
 * or modify any files — guidance only.
 */
class Agentic_Theme_Assistant extends \Agentic\Agent_Base {

	/**
	 * Curated list of recommended free FSE block themes.
	 *
	 * @var array
	 */
	private const RECOMMENDED_THEMES = array(
		array(
			'name'        => 'Blocksy',
			'slug'        => 'blocksy',
			'description' => 'Fast, flexible, and feature-rich — great for any type of site with tons of customisation options.',
			'best_for'    => 'Performance and flexibility',
		),
		array(
			'name'        => 'Astra',
			'slug'        => 'astra',
			'description' => 'Lightweight and lightning-fast with a minimalist design that works for blogs, portfolios, and business sites.',
			'best_for'    => 'Speed and minimalism',
		),
		array(
			'name'        => 'Kadence',
			'slug'        => 'kadence',
			'description' => 'Modern starter templates and beautiful patterns — perfect for getting a professional look quickly.',
			'best_for'    => 'Modern patterns and starter sites',
		),
		array(
			'name'        => 'Spectra One',
			'slug'        => 'flavor',
			'description' => 'Clean, elegant design with a focus on simplicity — ideal for personal sites and portfolios.',
			'best_for'    => 'Clean, simple design',
		),
		array(
			'name'        => 'Neve FSE',
			'slug'        => 'flavor',
			'description' => 'Multipurpose starter theme with fast load times and easy-to-use block patterns.',
			'best_for'    => 'Multipurpose starter sites',
		),
		array(
			'name'        => 'Flavor',
			'slug'        => 'flavor',
			'description' => 'A beautiful starter block theme with clean typography and thoughtful spacing.',
			'best_for'    => 'Blog and personal sites',
		),
	);

	/**
	 * Get system prompt from template file
	 */
	private function load_system_prompt(): string {
		$template_file = __DIR__ . '/templates/system-prompt.txt';
		if ( file_exists( $template_file ) ) {
			return file_get_contents( $template_file );
		}
		return 'You are the Theme Assistant agent. You help beginners choose and customise WordPress themes.';
	}

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'theme-assistant';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Theme Assistant';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Helps beginners choose and customise WordPress themes using the Site Editor. Detects your active theme, recommends themes, and guides you through visual customisation.';
	}

	/**
	 * Get agent version
	 */
	public function get_version(): string {
		return '2.0.0';
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
		return '🎨';
	}

	/**
	 * Get agent category
	 */
	public function get_category(): string {
		return 'Starter';
	}

	/**
	 * Get required capabilities — read-only, no elevated permissions needed
	 */
	public function get_required_capabilities(): array {
		return array( 'read' );
	}

	/**
	 * Get welcome message
	 */
	public function get_welcome_message(): string {
		return "🎨 **Theme Assistant**\n\n" .
				"I'll help you pick the perfect look for your WordPress site — no coding needed!\n\n" .
				"I can:\n" .
				"- **Check your current theme** and suggest quick improvements\n" .
				"- **Recommend free themes** that are easy to customise\n" .
				"- **Walk you through** the Site Editor step by step\n\n" .
				"Want me to check what theme you're using and give you some tips?";
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'What theme am I using right now?',
			'Recommend a good free theme for my site',
			'How do I change my site colors?',
			'Help me customise my homepage layout',
		);
	}

	/**
	 * Get available tools — read-only, no file writes or installs
	 */
	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_active_theme',
					'description' => 'Get detailed information about the currently active WordPress theme, including whether it supports the Site Editor (block theme).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_recommended_themes',
					'description' => 'Get a curated list of recommended free FSE block themes from WordPress.org with install links and descriptions.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'purpose' => array(
								'type'        => 'string',
								'description' => 'What the user wants their site for (e.g., "blog", "portfolio", "business", "shop"). Used to prioritise recommendations.',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_site_editor_guide',
					'description' => 'Get step-by-step instructions for a specific Site Editor task (changing colors, editing header, customising homepage, etc.).',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'task' => array(
								'type'        => 'string',
								'description' => 'The customisation task the user wants to do (e.g., "change colors", "edit header", "customise homepage", "add navigation menu", "change fonts")',
							),
						),
						'required'   => array( 'task' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'list_installed_themes',
					'description' => 'List all themes currently installed on this WordPress site with their status.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
						'required'   => array(),
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
			'get_active_theme'       => $this->tool_get_active_theme(),
			'get_recommended_themes' => $this->tool_get_recommended_themes( $arguments ),
			'get_site_editor_guide'  => $this->tool_get_site_editor_guide( $arguments ),
			'list_installed_themes'  => $this->tool_list_installed_themes(),
			default                  => array( 'error' => 'Unknown tool: ' . $tool_name ),
		};
	}

	/**
	 * Get active theme details
	 */
	private function tool_get_active_theme(): array {
		$theme        = wp_get_theme();
		$is_block     = method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme();
		$parent       = $theme->parent();
		$slug         = $theme->get_stylesheet();
		$is_tt5       = in_array( $slug, array( 'twentytwentyfive', 'tt5' ), true );
		$is_default   = str_starts_with( $slug, 'twenty' );

		$info = array(
			'name'           => $theme->get( 'Name' ),
			'slug'           => $slug,
			'version'        => $theme->get( 'Version' ),
			'description'    => $theme->get( 'Description' ),
			'author'         => $theme->get( 'Author' ),
			'is_block_theme' => $is_block,
			'is_child_theme' => (bool) $parent,
			'parent_theme'   => $parent ? $parent->get( 'Name' ) : null,
			'theme_uri'      => $theme->get( 'ThemeURI' ),
			'supports_site_editor' => $is_block,
		);

		// Add contextual guidance for the LLM.
		if ( $is_tt5 ) {
			$info['agent_note'] = 'This is Twenty Twenty-Five — the WordPress 2026 default theme. Praise it as an excellent starting point. It has 70+ block patterns, multiple style variations, fluid typography, and strong accessibility. Guide the user to Appearance → Editor to start customising.';
		} elseif ( $is_block ) {
			$info['agent_note'] = 'This is an FSE block theme. The user can customise it in Appearance → Editor. Offer to help with colors, fonts, header, footer, or homepage layout.';
		} elseif ( $is_default ) {
			$info['agent_note'] = 'This is an older default WordPress theme (classic). Suggest upgrading to Twenty Twenty-Five or another modern block theme for the full Site Editor experience.';
		} else {
			$info['agent_note'] = 'This is a classic (non-block) theme. It does not support the full Site Editor. Suggest switching to a block theme for a better visual editing experience. Use get_recommended_themes to show options.';
		}

		return $info;
	}

	/**
	 * Get recommended themes with install links
	 */
	private function tool_get_recommended_themes( array $args ): array {
		$purpose = $args['purpose'] ?? '';

		$themes = array();
		foreach ( self::RECOMMENDED_THEMES as $theme ) {
			$themes[] = array(
				'name'        => $theme['name'],
				'slug'        => $theme['slug'],
				'description' => $theme['description'],
				'best_for'    => $theme['best_for'],
				'install_url' => 'https://wordpress.org/themes/' . $theme['slug'] . '/',
				'type'        => 'Free FSE Block Theme',
			);
		}

		// Always include Twenty Twenty-Five at the top.
		array_unshift(
			$themes,
			array(
				'name'        => 'Twenty Twenty-Five',
				'slug'        => 'twentytwentyfive',
				'description' => 'The official WordPress 2026 default — 70+ patterns, multiple style variations, fluid typography, and excellent accessibility. A great blank canvas.',
				'best_for'    => 'Any type of site (the safe default)',
				'install_url' => 'https://wordpress.org/themes/twentytwentyfive/',
				'type'        => 'Free FSE Block Theme (Default)',
			)
		);

		return array(
			'themes'       => $themes,
			'purpose'      => $purpose ?: 'general',
			'install_note' => 'To install any of these: go to Appearance → Themes → Add New Theme, search for the name, click Install, then Activate.',
			'next_step'    => 'After activating, go straight to Appearance → Editor to start tweaking!',
		);
	}

	/**
	 * Get Site Editor guide for a specific task
	 */
	private function tool_get_site_editor_guide( array $args ): array {
		$task = strtolower( $args['task'] ?? '' );

		if ( empty( $task ) ) {
			return array( 'error' => 'Please specify what you want to customise.' );
		}

		$guides = array(
			'change colors'       => array(
				'title' => 'Change Your Site Colors',
				'steps' => array(
					'Go to **Appearance → Editor** in your WordPress admin.',
					'Click **Styles** (the half-circle icon in the top-right).',
					'Click **Colors** to see your color palette.',
					'Click any color swatch to change it — try the Background, Text, or Accent colors.',
					'Use **Browse styles** to try pre-made color schemes with one click.',
					'Click **Save** when you\'re happy!',
				),
				'tip'   => 'Try the Browse styles button first — it lets you preview complete color schemes before committing.',
			),
			'change fonts'        => array(
				'title' => 'Change Your Site Fonts',
				'steps' => array(
					'Go to **Appearance → Editor**.',
					'Click **Styles** (half-circle icon, top-right).',
					'Click **Typography**.',
					'You\'ll see options for Headings, Body text, Links, etc.',
					'Click any element to change its font family, size, weight, and line height.',
					'Click **Save** when you\'re happy!',
				),
				'tip'   => 'Stick with 1–2 fonts for a clean, professional look. System fonts (like the defaults) load fastest.',
			),
			'edit header'         => array(
				'title' => 'Customise Your Header',
				'steps' => array(
					'Go to **Appearance → Editor**.',
					'Click **Patterns** in the left sidebar.',
					'Click **Template Parts**, then click your **Header**.',
					'You can now edit your header visually — add a site logo, change the navigation, add buttons, etc.',
					'Click any block to modify it, or use the **+** button to add new blocks.',
					'Click **Save** when you\'re happy!',
				),
				'tip'   => 'To add a logo, insert a "Site Logo" block. To change navigation links, click the Navigation block and edit menu items.',
			),
			'edit footer'         => array(
				'title' => 'Customise Your Footer',
				'steps' => array(
					'Go to **Appearance → Editor**.',
					'Click **Patterns** in the left sidebar.',
					'Click **Template Parts**, then click your **Footer**.',
					'Edit the footer visually — add social links, copyright text, extra navigation, etc.',
					'Click **Save** when you\'re happy!',
				),
				'tip'   => 'Add a "Social Icons" block to display links to your social media profiles.',
			),
			'customise homepage'  => array(
				'title' => 'Customise Your Homepage Layout',
				'steps' => array(
					'Go to **Appearance → Editor**.',
					'Click **Pages** in the left sidebar.',
					'Click your **Front Page** (homepage).',
					'Now you can edit the homepage layout — add hero sections, feature grids, testimonials, etc.',
					'Click the **+** button to browse block patterns — these are pre-designed sections you can insert with one click.',
					'Rearrange sections by dragging blocks up or down.',
					'Click **Save** when you\'re happy!',
				),
				'tip'   => 'Search patterns for "hero", "features", or "call to action" to find great homepage sections instantly.',
			),
			'add navigation menu' => array(
				'title' => 'Set Up Your Navigation Menu',
				'steps' => array(
					'Go to **Appearance → Editor**.',
					'Click on the **Navigation** block in your header (or find it in Patterns → Template Parts → Header).',
					'Click the Navigation block to select it.',
					'Use the **+** button inside it to add menu items — Pages, Posts, Custom Links, etc.',
					'Drag items to reorder them.',
					'Click **Save** when you\'re happy!',
				),
				'tip'   => 'You can create dropdown submenus by dragging a menu item slightly to the right to nest it under another.',
			),
			'browse styles'       => array(
				'title' => 'Try Different Style Variations',
				'steps' => array(
					'Go to **Appearance → Editor**.',
					'Click **Styles** (half-circle icon, top-right).',
					'Click **Browse styles** to see all available variations.',
					'Click any variation to preview it — this changes colors, fonts, and spacing all at once.',
					'Pick one you like and click **Save**!',
				),
				'tip'   => 'Style variations are the fastest way to completely change your site\'s look. Twenty Twenty-Five comes with multiple variations built in.',
			),
		);

		// Try to match the task to a guide.
		$matched = null;
		foreach ( $guides as $key => $guide ) {
			if ( str_contains( $task, $key ) || str_contains( $key, $task ) ) {
				$matched = $guide;
				break;
			}
		}

		// Fuzzy match by keywords.
		if ( ! $matched ) {
			$keyword_map = array(
				'color'      => 'change colors',
				'colour'     => 'change colors',
				'font'       => 'change fonts',
				'typography' => 'change fonts',
				'header'     => 'edit header',
				'logo'       => 'edit header',
				'footer'     => 'edit footer',
				'homepage'   => 'customise homepage',
				'home page'  => 'customise homepage',
				'front page' => 'customise homepage',
				'landing'    => 'customise homepage',
				'nav'        => 'add navigation menu',
				'menu'       => 'add navigation menu',
				'style'      => 'browse styles',
				'variation'  => 'browse styles',
				'layout'     => 'customise homepage',
			);

			foreach ( $keyword_map as $keyword => $guide_key ) {
				if ( str_contains( $task, $keyword ) ) {
					$matched = $guides[ $guide_key ];
					break;
				}
			}
		}

		if ( ! $matched ) {
			return array(
				'title'          => 'Site Editor Customisation',
				'general_note'   => "I don't have a specific guide for \"$task\" yet, but almost everything can be done in the Site Editor!",
				'steps'          => array(
					'Go to **Appearance → Editor** in your WordPress admin.',
					'Look through **Styles** (colors, fonts), **Pages**, and **Patterns** (template parts).',
					'Click any element on the page to select and edit it.',
					'Use the **+** button to add new blocks and patterns.',
					'Click **Save** when you\'re happy!',
				),
				'available_guides' => array_keys( $guides ),
			);
		}

		return $matched;
	}

	/**
	 * List all installed themes
	 */
	private function tool_list_installed_themes(): array {
		$themes       = wp_get_themes();
		$active_slug  = get_stylesheet();
		$result       = array();

		foreach ( $themes as $slug => $theme ) {
			$is_block = method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme();

			$result[] = array(
				'name'        => $theme->get( 'Name' ),
				'slug'        => $slug,
				'version'     => $theme->get( 'Version' ),
				'status'      => ( $slug === $active_slug ) ? 'active' : 'inactive',
				'type'        => $is_block ? 'Block (FSE)' : 'Classic',
				'is_child'    => (bool) $theme->parent(),
				'description' => wp_trim_words( $theme->get( 'Description' ), 15 ),
			);
		}

		return array(
			'themes'       => $result,
			'total'        => count( $result ),
			'active_theme' => $active_slug,
			'agent_note'   => 'Present this as a simple list. Highlight the active theme. If there are block themes available that are not active, mention those as options. If only classic themes are installed, suggest getting a modern block theme.',
		);
	}
}

// Register the agent
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Theme_Assistant() );
	}
);
