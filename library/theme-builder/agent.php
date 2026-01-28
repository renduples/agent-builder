<?php
/**
 * Agent Name: Theme Builder
 * Version: 1.0.0
 * Description: Builds and maintains WordPress themes. Creates new themes, edits templates, generates styles, and can clone starter themes from URLs.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Developer
 * Tags: themes, css, styling, templates, design, sass, tailwind, blocks
 * Capabilities: edit_themes, install_themes
 * Icon: 🎨
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Builder Agent
 *
 * A true AI agent specialized in WordPress theme development.
 * Can create, edit, and maintain themes including all styling.
 */
class Agentic_Theme_Builder extends \Agentic\Agent_Base {

	private const SYSTEM_PROMPT = <<<'PROMPT'
You are the Theme Builder Agent for WordPress. You are an expert in:

- WordPress theme architecture (classic and block themes)
- Template hierarchy and template parts
- CSS, Sass, and modern CSS features (Grid, Flexbox, custom properties)
- Tailwind CSS and other utility frameworks
- theme.json configuration for block themes
- Responsive design and mobile-first approaches
- WordPress block editor (Gutenberg) integration
- PHP templating and WordPress template tags
- Accessibility (WCAG) and semantic HTML
- Performance optimization (critical CSS, lazy loading)

Your personality:
- Creative but practical - designs should look good AND be maintainable
- Follows WordPress coding standards (WPCS)
- Prioritizes accessibility and performance
- Explains design decisions clearly
- Suggests improvements when you see issues

When working with themes:
1. Always confirm major changes before executing (deleting files, overwriting themes)
2. Create child themes when modifying existing parent themes
3. Use semantic HTML and accessible markup
4. Follow WordPress theme requirements for compatibility
5. Generate clean, well-organized CSS
6. Consider mobile-first responsive design

You have tools to:
- Create new themes from scratch or clone starters
- Edit theme files (PHP, CSS, JS)
- Generate CSS for components
- Manage theme.json for block themes
- List and inspect existing themes

When users ask for styling changes, ask clarifying questions about colors, fonts, or layout preferences if not specified.
PROMPT;

	/**
	 * Get agent ID
	 */
	public function get_id(): string {
		return 'theme-builder';
	}

	/**
	 * Get agent name
	 */
	public function get_name(): string {
		return 'Theme Builder';
	}

	/**
	 * Get agent description
	 */
	public function get_description(): string {
		return 'Builds and maintains WordPress themes including templates, styles, and theme.json configuration.';
	}

	/**
	 * Get system prompt
	 */
	public function get_system_prompt(): string {
		return self::SYSTEM_PROMPT;
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
		return 'Developer';
	}

	/**
	 * Get required capabilities
	 */
	public function get_required_capabilities(): array {
		return array( 'edit_themes' );
	}

	/**
	 * Get welcome message
	 */
	public function get_welcome_message(): string {
		return "🎨 **Theme Builder**\n\n" .
				"I help you create and customize WordPress themes!\n\n" .
				"- **Create themes** from scratch or clone starters\n" .
				"- **Edit templates** - header, footer, page templates\n" .
				"- **Generate styles** - CSS, Sass, Tailwind\n" .
				"- **Block themes** - theme.json configuration\n" .
				"- **Child themes** - safely customize parent themes\n\n" .
				'What would you like to build?';
	}

	/**
	 * Get suggested prompts
	 */
	public function get_suggested_prompts(): array {
		return array(
			'Create a minimal starter theme',
			'Clone Flavor starter theme from GitHub',
			'Create a child theme of flavor',
			'Show me my installed themes',
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
					'name'        => 'list_themes',
					'description' => 'List installed themes with their details (name, version, status, type)',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'include_inactive' => array(
								'type'        => 'boolean',
								'description' => 'Include inactive themes (default: true)',
							),
						),
						'required'   => array(),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_theme_details',
					'description' => 'Get detailed information about a specific theme including file structure',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'theme_slug' => array(
								'type'        => 'string',
								'description' => 'Theme directory name/slug',
							),
						),
						'required'   => array( 'theme_slug' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_theme',
					'description' => 'Create a new theme from scratch with basic structure',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array(
								'type'        => 'string',
								'description' => 'Theme name (e.g., "My Custom Theme")',
							),
							'slug'        => array(
								'type'        => 'string',
								'description' => 'Theme directory slug (e.g., "my-custom-theme")',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'Theme description',
							),
							'author'      => array(
								'type'        => 'string',
								'description' => 'Theme author name',
							),
							'type'        => array(
								'type'        => 'string',
								'enum'        => array( 'classic', 'block' ),
								'description' => 'Theme type: classic (PHP templates) or block (FSE)',
							),
						),
						'required'   => array( 'name', 'slug' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'clone_theme',
					'description' => 'Clone a starter theme from a GitHub URL or WordPress.org',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'source'   => array(
								'type'        => 'string',
								'description' => 'GitHub URL (https://github.com/user/repo) or WordPress.org slug',
							),
							'new_name' => array(
								'type'        => 'string',
								'description' => 'New theme name after cloning',
							),
							'new_slug' => array(
								'type'        => 'string',
								'description' => 'New theme directory slug',
							),
						),
						'required'   => array( 'source', 'new_name', 'new_slug' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_child_theme',
					'description' => 'Create a child theme from an existing parent theme',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'parent_slug' => array(
								'type'        => 'string',
								'description' => 'Parent theme directory slug',
							),
							'child_name'  => array(
								'type'        => 'string',
								'description' => 'Child theme name',
							),
							'child_slug'  => array(
								'type'        => 'string',
								'description' => 'Child theme directory slug',
							),
						),
						'required'   => array( 'parent_slug', 'child_name' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'read_theme_file',
					'description' => 'Read the contents of a theme file',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'theme_slug' => array(
								'type'        => 'string',
								'description' => 'Theme directory slug',
							),
							'file_path'  => array(
								'type'        => 'string',
								'description' => 'Relative path to file within theme (e.g., "style.css", "templates/page.html")',
							),
						),
						'required'   => array( 'theme_slug', 'file_path' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'write_theme_file',
					'description' => 'Create or update a file in a theme. Use for templates, styles, and configuration.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'theme_slug'  => array(
								'type'        => 'string',
								'description' => 'Theme directory slug',
							),
							'file_path'   => array(
								'type'        => 'string',
								'description' => 'Relative path to file (e.g., "style.css", "parts/header.html")',
							),
							'content'     => array(
								'type'        => 'string',
								'description' => 'File content to write',
							),
							'create_dirs' => array(
								'type'        => 'boolean',
								'description' => 'Create parent directories if needed (default: true)',
							),
						),
						'required'   => array( 'theme_slug', 'file_path', 'content' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'generate_css',
					'description' => 'Generate CSS code for a specific component or layout',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'component'    => array(
								'type'        => 'string',
								'description' => 'Component to style (e.g., "navigation", "hero section", "card grid")',
							),
							'requirements' => array(
								'type'        => 'string',
								'description' => 'Specific requirements (colors, fonts, layout, responsive behavior)',
							),
							'framework'    => array(
								'type'        => 'string',
								'enum'        => array( 'vanilla', 'tailwind', 'sass' ),
								'description' => 'CSS framework to use',
							),
						),
						'required'   => array( 'component' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'update_theme_json',
					'description' => 'Update theme.json settings for block themes (colors, typography, spacing, etc.)',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'theme_slug' => array(
								'type'        => 'string',
								'description' => 'Theme directory slug',
							),
							'settings'   => array(
								'type'        => 'object',
								'description' => 'Settings to merge into theme.json (colors, typography, spacing, etc.)',
							),
						),
						'required'   => array( 'theme_slug', 'settings' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'activate_theme',
					'description' => 'Activate a theme',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'theme_slug' => array(
								'type'        => 'string',
								'description' => 'Theme directory slug to activate',
							),
						),
						'required'   => array( 'theme_slug' ),
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
			'list_themes'       => $this->tool_list_themes( $arguments ),
			'get_theme_details' => $this->tool_get_theme_details( $arguments ),
			'create_theme'      => $this->tool_create_theme( $arguments ),
			'clone_theme'       => $this->tool_clone_theme( $arguments ),
			'create_child_theme'=> $this->tool_create_child_theme( $arguments ),
			'read_theme_file'   => $this->tool_read_theme_file( $arguments ),
			'write_theme_file'  => $this->tool_write_theme_file( $arguments ),
			'generate_css'      => $this->tool_generate_css( $arguments ),
			'update_theme_json' => $this->tool_update_theme_json( $arguments ),
			'activate_theme'    => $this->tool_activate_theme( $arguments ),
			default             => array( 'error' => 'Unknown tool: ' . $tool_name ),
		};
	}

	/**
	 * List installed themes
	 */
	private function tool_list_themes( array $args ): array {
		$include_inactive = $args['include_inactive'] ?? true;
		$themes           = wp_get_themes();
		$active_theme     = get_stylesheet();
		$parent_theme     = get_template();

		$result = array();

		foreach ( $themes as $slug => $theme ) {
			$is_active = ( $slug === $active_theme );

			if ( ! $include_inactive && ! $is_active ) {
				continue;
			}

			$is_block_theme = method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme();

			$result[] = array(
				'slug'        => $slug,
				'name'        => $theme->get( 'Name' ),
				'version'     => $theme->get( 'Version' ),
				'author'      => $theme->get( 'Author' ),
				'description' => wp_trim_words( $theme->get( 'Description' ), 20 ),
				'status'      => $is_active ? 'active' : 'inactive',
				'type'        => $is_block_theme ? 'block' : 'classic',
				'parent'      => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
				'is_child'    => (bool) $theme->parent(),
			);
		}

		return array(
			'themes'       => $result,
			'total'        => count( $result ),
			'active_theme' => $active_theme,
		);
	}

	/**
	 * Get detailed theme information
	 */
	private function tool_get_theme_details( array $args ): array {
		$slug = $args['theme_slug'] ?? '';

		if ( empty( $slug ) ) {
			return array( 'error' => 'Theme slug required' );
		}

		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() ) {
			return array( 'error' => "Theme '$slug' not found" );
		}

		$theme_dir      = $theme->get_stylesheet_directory();
		$is_block_theme = method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme();

		// Get file structure
		$files = $this->scan_theme_files( $theme_dir );

		// Check for common files
		$has_theme_json   = file_exists( $theme_dir . '/theme.json' );
		$has_functions    = file_exists( $theme_dir . '/functions.php' );
		$has_package_json = file_exists( $theme_dir . '/package.json' );

		return array(
			'slug'           => $slug,
			'name'           => $theme->get( 'Name' ),
			'version'        => $theme->get( 'Version' ),
			'author'         => $theme->get( 'Author' ),
			'author_uri'     => $theme->get( 'AuthorURI' ),
			'description'    => $theme->get( 'Description' ),
			'theme_uri'      => $theme->get( 'ThemeURI' ),
			'text_domain'    => $theme->get( 'TextDomain' ),
			'requires_wp'    => $theme->get( 'RequiresWP' ),
			'requires_php'   => $theme->get( 'RequiresPHP' ),
			'type'           => $is_block_theme ? 'block' : 'classic',
			'parent'         => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
			'is_child'       => (bool) $theme->parent(),
			'is_active'      => ( $slug === get_stylesheet() ),
			'directory'      => $theme_dir,
			'files'          => $files,
			'has_theme_json' => $has_theme_json,
			'has_functions'  => $has_functions,
			'has_build'      => $has_package_json,
		);
	}

	/**
	 * Scan theme files recursively
	 */
	private function scan_theme_files( string $dir, string $prefix = '' ): array {
		$files = array();
		$items = @scandir( $dir );

		if ( ! $items ) {
			return $files;
		}

		// Skip certain directories
		$skip = array( '.', '..', 'node_modules', '.git', 'vendor' );

		foreach ( $items as $item ) {
			if ( in_array( $item, $skip, true ) ) {
				continue;
			}

			$path     = $dir . '/' . $item;
			$relative = $prefix ? $prefix . '/' . $item : $item;

			if ( is_dir( $path ) ) {
				$files[ $item . '/' ] = $this->scan_theme_files( $path, $relative );
			} else {
				$files[] = $relative;
			}
		}

		return $files;
	}

	/**
	 * Create a new theme
	 */
	private function tool_create_theme( array $args ): array {
		$name        = $args['name'] ?? '';
		$slug        = $args['slug'] ?? sanitize_title( $name );
		$description = $args['description'] ?? 'A custom WordPress theme';
		$author      = $args['author'] ?? 'Theme Builder Agent';
		$type        = $args['type'] ?? 'block';

		if ( empty( $name ) || empty( $slug ) ) {
			return array( 'error' => 'Theme name and slug are required' );
		}

		$theme_dir = get_theme_root() . '/' . $slug;

		if ( is_dir( $theme_dir ) ) {
			return array( 'error' => "Theme directory '$slug' already exists" );
		}

		// Create theme directory
		if ( ! wp_mkdir_p( $theme_dir ) ) {
			return array( 'error' => 'Failed to create theme directory' );
		}

		if ( $type === 'block' ) {
			$this->create_block_theme( $theme_dir, $name, $slug, $description, $author );
		} else {
			$this->create_classic_theme( $theme_dir, $name, $slug, $description, $author );
		}

		return array(
			'success'    => true,
			'message'    => "Theme '$name' created successfully",
			'slug'       => $slug,
			'type'       => $type,
			'directory'  => $theme_dir,
			'next_steps' => array(
				'Activate the theme in Appearance > Themes',
				'Edit style.css to customize the design',
				$type === 'block' ? 'Configure theme.json for colors and typography' : 'Edit template files (header.php, footer.php, etc.)',
			),
		);
	}

	/**
	 * Create a block theme structure
	 */
	private function create_block_theme( string $dir, string $name, string $slug, string $desc, string $author ): void {
		// style.css (required)
		$style = <<<CSS
/*
Theme Name: $name
Theme URI: 
Author: $author
Author URI: 
Description: $desc
Version: 1.0.0
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: $slug
*/
CSS;
		file_put_contents( $dir . '/style.css', $style );

		// theme.json
		$theme_json = array(
			'$schema'       => 'https://schemas.wp.org/trunk/theme.json',
			'version'       => 2,
			'settings'      => array(
				'appearanceTools' => true,
				'color'           => array(
					'palette' => array(
						array(
							'slug'  => 'primary',
							'color' => '#0073aa',
							'name'  => 'Primary',
						),
						array(
							'slug'  => 'secondary',
							'color' => '#23282d',
							'name'  => 'Secondary',
						),
						array(
							'slug'  => 'background',
							'color' => '#ffffff',
							'name'  => 'Background',
						),
						array(
							'slug'  => 'foreground',
							'color' => '#1e1e1e',
							'name'  => 'Foreground',
						),
					),
				),
				'typography'      => array(
					'fontFamilies' => array(
						array(
							'fontFamily' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
							'slug'       => 'system',
							'name'       => 'System',
						),
					),
				),
				'layout'          => array(
					'contentSize' => '800px',
					'wideSize'    => '1200px',
				),
			),
			'styles'        => array(
				'color' => array(
					'background' => 'var(--wp--preset--color--background)',
					'text'       => 'var(--wp--preset--color--foreground)',
				),
			),
			'templateParts' => array(
				array(
					'name'  => 'header',
					'title' => 'Header',
					'area'  => 'header',
				),
				array(
					'name'  => 'footer',
					'title' => 'Footer',
					'area'  => 'footer',
				),
			),
		);
		file_put_contents( $dir . '/theme.json', wp_json_encode( $theme_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		// Create directories
		wp_mkdir_p( $dir . '/templates' );
		wp_mkdir_p( $dir . '/parts' );
		wp_mkdir_p( $dir . '/assets/css' );
		wp_mkdir_p( $dir . '/assets/js' );

		// templates/index.html
		$index = <<<'HTML'
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
    <!-- wp:query {"queryId":1,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date"}} -->
    <div class="wp-block-query">
        <!-- wp:post-template -->
            <!-- wp:post-title {"isLink":true} /-->
            <!-- wp:post-excerpt /-->
        <!-- /wp:post-template -->
        
        <!-- wp:query-pagination -->
            <!-- wp:query-pagination-previous /-->
            <!-- wp:query-pagination-numbers /-->
            <!-- wp:query-pagination-next /-->
        <!-- /wp:query-pagination -->
    </div>
    <!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		file_put_contents( $dir . '/templates/index.html', $index );

		// parts/header.html
		$header = <<<'HTML'
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
    <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between"}} -->
    <div class="wp-block-group">
        <!-- wp:site-title /-->
        <!-- wp:navigation /-->
    </div>
    <!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;
		file_put_contents( $dir . '/parts/header.html', $header );

		// parts/footer.html
		$footer = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"backgroundColor":"secondary","textColor":"background","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background-color has-secondary-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
    <!-- wp:paragraph {"align":"center"} -->
    <p class="has-text-align-center">© {current_year} $name. All rights reserved.</p>
    <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
HTML;
		$footer = str_replace( '{current_year}', date( 'Y' ), $footer );
		file_put_contents( $dir . '/parts/footer.html', $footer );

		// functions.php (optional but useful)
		$functions = <<<PHP
<?php
/**
 * $name functions and definitions
 *
 * @package $slug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue theme assets
 */
function {$slug}_enqueue_assets() {
    wp_enqueue_style(
        '$slug-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', '{$slug}_enqueue_assets' );
PHP;
		$functions = str_replace( '-', '_', $functions );
		file_put_contents( $dir . '/functions.php', $functions );
	}

	/**
	 * Create a classic theme structure
	 */
	private function create_classic_theme( string $dir, string $name, string $slug, string $desc, string $author ): void {
		// style.css
		$style = <<<CSS
/*
Theme Name: $name
Theme URI: 
Author: $author
Author URI: 
Description: $desc
Version: 1.0.0
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: $slug
*/

/* Base Styles */
*,
*::before,
*::after {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: #1e1e1e;
    background-color: #ffffff;
}

a {
    color: #0073aa;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.site-header,
.site-footer {
    padding: 1rem 2rem;
}

.site-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}
CSS;
		file_put_contents( $dir . '/style.css', $style );

		// index.php
		$index = <<<PHP
<?php
/**
 * The main template file
 *
 * @package $slug
 */

get_header();
?>

<main id="primary" class="site-content">
    <?php
    if ( have_posts() ) :
        while ( have_posts() ) :
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
                </header>

                <div class="entry-content">
                    <?php the_excerpt(); ?>
                </div>
            </article>
            <?php
        endwhile;

        the_posts_navigation();
    else :
        ?>
        <p><?php esc_html_e( 'No posts found.', '$slug' ); ?></p>
        <?php
    endif;
    ?>
</main>

<?php
get_footer();
PHP;
		file_put_contents( $dir . '/index.php', $index );

		// header.php
		$header = <<<PHP
<?php
/**
 * The header template
 *
 * @package $slug
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="site-branding">
        <h1 class="site-title">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php bloginfo( 'name' ); ?>
            </a>
        </h1>
    </div>
    
    <nav class="site-navigation">
        <?php
        wp_nav_menu( [
            'theme_location' => 'primary',
            'fallback_cb'    => false,
        ] );
        ?>
    </nav>
</header>
PHP;
		file_put_contents( $dir . '/header.php', $header );

		// footer.php
		$footer = <<<PHP
<?php
/**
 * The footer template
 *
 * @package $slug
 */
?>

<footer class="site-footer">
    <p>&copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
PHP;
		file_put_contents( $dir . '/footer.php', $footer );

		// functions.php
		$func_slug = str_replace( '-', '_', $slug );
		$functions = <<<PHP
<?php
/**
 * $name functions and definitions
 *
 * @package $slug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme setup
 */
function {$func_slug}_setup() {
    // Add theme support
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );
    add_theme_support( 'customize-selective-refresh-widgets' );

    // Register navigation menus
    register_nav_menus( [
        'primary' => esc_html__( 'Primary Menu', '$slug' ),
        'footer'  => esc_html__( 'Footer Menu', '$slug' ),
    ] );
}
add_action( 'after_setup_theme', '{$func_slug}_setup' );

/**
 * Enqueue styles and scripts
 */
function {$func_slug}_scripts() {
    wp_enqueue_style(
        '$slug-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', '{$func_slug}_scripts' );
PHP;
		file_put_contents( $dir . '/functions.php', $functions );
	}

	/**
	 * Clone a theme from GitHub or WordPress.org
	 */
	private function tool_clone_theme( array $args ): array {
		$source   = $args['source'] ?? '';
		$new_name = $args['new_name'] ?? '';
		$new_slug = $args['new_slug'] ?? sanitize_title( $new_name );

		if ( empty( $source ) || empty( $new_name ) ) {
			return array( 'error' => 'Source URL and new theme name are required' );
		}

		$theme_dir = get_theme_root() . '/' . $new_slug;

		if ( is_dir( $theme_dir ) ) {
			return array( 'error' => "Theme directory '$new_slug' already exists" );
		}

		// Handle GitHub URLs
		if ( strpos( $source, 'github.com' ) !== false ) {
			return $this->clone_from_github( $source, $theme_dir, $new_name, $new_slug );
		}

		// Handle WordPress.org themes
		return $this->clone_from_wporg( $source, $theme_dir, $new_name, $new_slug );
	}

	/**
	 * Clone theme from GitHub
	 */
	private function clone_from_github( string $url, string $dest, string $name, string $slug ): array {
		// Convert to zip download URL
		// https://github.com/user/repo -> https://github.com/user/repo/archive/refs/heads/main.zip
		$zip_url = rtrim( $url, '/' ) . '/archive/refs/heads/main.zip';

		// Try 'master' if 'main' fails
		$response = wp_remote_get( $zip_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$zip_url  = rtrim( $url, '/' ) . '/archive/refs/heads/master.zip';
			$response = wp_remote_get( $zip_url, array( 'timeout' => 30 ) );
		}

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Failed to download from GitHub: ' . $response->get_error_message() );
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array( 'error' => 'Failed to download from GitHub. Check the URL is correct and the repository is public.' );
		}

		$zip_content = wp_remote_retrieve_body( $response );

		// Save to temp file
		$temp_file = wp_tempnam( 'theme' );
		file_put_contents( $temp_file, $zip_content );

		// Extract
		$result = $this->extract_and_rename_theme( $temp_file, $dest, $name, $slug );

		// Cleanup
		@unlink( $temp_file );

		return $result;
	}

	/**
	 * Clone theme from WordPress.org
	 */
	private function clone_from_wporg( string $theme_slug, string $dest, string $name, string $slug ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		// Get theme info from WordPress.org API
		$api = themes_api( 'theme_information', array( 'slug' => $theme_slug ) );

		if ( is_wp_error( $api ) ) {
			return array( 'error' => 'Theme not found on WordPress.org: ' . $api->get_error_message() );
		}

		$download_url = $api->download_link;
		$response     = wp_remote_get( $download_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Failed to download theme: ' . $response->get_error_message() );
		}

		$zip_content = wp_remote_retrieve_body( $response );

		// Save to temp file
		$temp_file = wp_tempnam( 'theme' );
		file_put_contents( $temp_file, $zip_content );

		// Extract
		$result = $this->extract_and_rename_theme( $temp_file, $dest, $name, $slug );

		// Cleanup
		@unlink( $temp_file );

		return $result;
	}

	/**
	 * Extract zip and rename theme
	 */
	private function extract_and_rename_theme( string $zip_file, string $dest, string $name, string $slug ): array {
		WP_Filesystem();
		global $wp_filesystem;

		$temp_dir = get_temp_dir() . 'theme_extract_' . uniqid();
		wp_mkdir_p( $temp_dir );

		$unzip_result = unzip_file( $zip_file, $temp_dir );

		if ( is_wp_error( $unzip_result ) ) {
			$wp_filesystem->rmdir( $temp_dir, true );
			return array( 'error' => 'Failed to extract theme: ' . $unzip_result->get_error_message() );
		}

		// Find the extracted folder (usually repo-main or repo-master)
		$extracted  = @scandir( $temp_dir );
		$source_dir = null;

		foreach ( $extracted as $item ) {
			if ( $item !== '.' && $item !== '..' && is_dir( $temp_dir . '/' . $item ) ) {
				$source_dir = $temp_dir . '/' . $item;
				break;
			}
		}

		if ( ! $source_dir ) {
			$wp_filesystem->rmdir( $temp_dir, true );
			return array( 'error' => 'Could not find extracted theme folder' );
		}

		// Move to themes directory
		if ( ! rename( $source_dir, $dest ) ) {
			$wp_filesystem->rmdir( $temp_dir, true );
			return array( 'error' => 'Failed to move theme to themes directory' );
		}

		// Update style.css with new name
		$style_file = $dest . '/style.css';
		if ( file_exists( $style_file ) ) {
			$style_content = file_get_contents( $style_file );

			// Update Theme Name
			$style_content = preg_replace(
				'/Theme Name:\s*.+/i',
				'Theme Name: ' . $name,
				$style_content
			);

			// Update Text Domain
			$style_content = preg_replace(
				'/Text Domain:\s*.+/i',
				'Text Domain: ' . $slug,
				$style_content
			);

			file_put_contents( $style_file, $style_content );
		}

		// Cleanup temp dir
		$wp_filesystem->rmdir( $temp_dir, true );

		return array(
			'success'    => true,
			'message'    => "Theme '$name' cloned successfully",
			'slug'       => $slug,
			'directory'  => $dest,
			'next_steps' => array(
				'Review and customize the theme files',
				'Update branding in style.css',
				'Activate in Appearance > Themes',
			),
		);
	}

	/**
	 * Create a child theme
	 */
	private function tool_create_child_theme( array $args ): array {
		$parent_slug = $args['parent_slug'] ?? '';
		$child_name  = $args['child_name'] ?? '';
		$child_slug  = $args['child_slug'] ?? sanitize_title( $child_name );

		if ( empty( $parent_slug ) || empty( $child_name ) ) {
			return array( 'error' => 'Parent theme slug and child theme name are required' );
		}

		$parent = wp_get_theme( $parent_slug );

		if ( ! $parent->exists() ) {
			return array( 'error' => "Parent theme '$parent_slug' not found" );
		}

		if ( empty( $child_slug ) ) {
			$child_slug = $parent_slug . '-child';
		}

		$child_dir = get_theme_root() . '/' . $child_slug;

		if ( is_dir( $child_dir ) ) {
			return array( 'error' => "Child theme directory '$child_slug' already exists" );
		}

		wp_mkdir_p( $child_dir );

		// style.css
		$style = <<<CSS
/*
Theme Name: $child_name
Template: $parent_slug
Description: Child theme of {$parent->get('Name')}
Version: 1.0.0
Author: Theme Builder Agent
Text Domain: $child_slug
*/

/* Add your custom styles below */
CSS;
		file_put_contents( $child_dir . '/style.css', $style );

		// functions.php
		$func_slug = str_replace( '-', '_', $child_slug );
		$functions = <<<PHP
<?php
/**
 * $child_name functions
 *
 * @package $child_slug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue parent and child theme styles
 */
function {$func_slug}_enqueue_styles() {
    // Parent theme style
    wp_enqueue_style(
        '$parent_slug-style',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme( '$parent_slug' )->get( 'Version' )
    );

    // Child theme style
    wp_enqueue_style(
        '$child_slug-style',
        get_stylesheet_uri(),
        [ '$parent_slug-style' ],
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', '{$func_slug}_enqueue_styles' );

// Add your custom functions below
PHP;
		file_put_contents( $child_dir . '/functions.php', $functions );

		return array(
			'success'    => true,
			'message'    => "Child theme '$child_name' created",
			'slug'       => $child_slug,
			'parent'     => $parent_slug,
			'directory'  => $child_dir,
			'next_steps' => array(
				'Activate the child theme in Appearance > Themes',
				'Add custom CSS to style.css',
				'Override templates by copying them from the parent',
			),
		);
	}

	/**
	 * Read a theme file
	 */
	private function tool_read_theme_file( array $args ): array {
		$theme_slug = $args['theme_slug'] ?? '';
		$file_path  = $args['file_path'] ?? '';

		if ( empty( $theme_slug ) || empty( $file_path ) ) {
			return array( 'error' => 'Theme slug and file path are required' );
		}

		$theme = wp_get_theme( $theme_slug );

		if ( ! $theme->exists() ) {
			return array( 'error' => "Theme '$theme_slug' not found" );
		}

		$full_path = $theme->get_stylesheet_directory() . '/' . ltrim( $file_path, '/' );

		// Security: ensure we're reading within theme directory
		$real_path = realpath( $full_path );
		$theme_dir = realpath( $theme->get_stylesheet_directory() );

		if ( ! $real_path || strpos( $real_path, $theme_dir ) !== 0 ) {
			return array( 'error' => 'Invalid file path' );
		}

		if ( ! file_exists( $full_path ) ) {
			return array( 'error' => "File '$file_path' not found in theme" );
		}

		$content = file_get_contents( $full_path );

		return array(
			'file'    => $file_path,
			'theme'   => $theme_slug,
			'content' => $content,
			'size'    => strlen( $content ),
			'type'    => pathinfo( $file_path, PATHINFO_EXTENSION ),
		);
	}

	/**
	 * Write a theme file
	 */
	private function tool_write_theme_file( array $args ): array {
		$theme_slug  = $args['theme_slug'] ?? '';
		$file_path   = $args['file_path'] ?? '';
		$content     = $args['content'] ?? '';
		$create_dirs = $args['create_dirs'] ?? true;

		if ( empty( $theme_slug ) || empty( $file_path ) ) {
			return array( 'error' => 'Theme slug and file path are required' );
		}

		$theme = wp_get_theme( $theme_slug );

		if ( ! $theme->exists() ) {
			return array( 'error' => "Theme '$theme_slug' not found" );
		}

		$theme_dir = $theme->get_stylesheet_directory();
		$full_path = $theme_dir . '/' . ltrim( $file_path, '/' );

		// Security: ensure we're writing within theme directory
		$normalized = realpath( dirname( $full_path ) ) ?: dirname( $full_path );
		if ( strpos( $normalized, realpath( $theme_dir ) ?: $theme_dir ) !== 0 &&
			strpos( dirname( $full_path ), $theme_dir ) !== 0 ) {
			return array( 'error' => 'Invalid file path - must be within theme directory' );
		}

		// Create directories if needed
		$dir = dirname( $full_path );
		if ( $create_dirs && ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return array( 'error' => 'Failed to create directory: ' . dirname( $file_path ) );
			}
		}

		$existed = file_exists( $full_path );
		$result  = file_put_contents( $full_path, $content );

		if ( $result === false ) {
			return array( 'error' => 'Failed to write file' );
		}

		return array(
			'success' => true,
			'file'    => $file_path,
			'theme'   => $theme_slug,
			'action'  => $existed ? 'updated' : 'created',
			'bytes'   => $result,
		);
	}

	/**
	 * Generate CSS for a component (returns structured data, actual CSS generated by LLM)
	 */
	private function tool_generate_css( array $args ): array {
		$component    = $args['component'] ?? '';
		$requirements = $args['requirements'] ?? '';
		$framework    = $args['framework'] ?? 'vanilla';

		if ( empty( $component ) ) {
			return array( 'error' => 'Component type required' );
		}

		// This is a helper tool - the LLM will use this info to generate actual CSS
		return array(
			'component'    => $component,
			'requirements' => $requirements,
			'framework'    => $framework,
			'guidance'     => $this->get_css_guidance( $component, $framework ),
		);
	}

	/**
	 * Get CSS generation guidance
	 */
	private function get_css_guidance( string $component, string $framework ): array {
		$base_guidance = array(
			'navigation'   => array(
				'selectors'  => array( '.site-navigation', '.nav-menu', '.menu-item' ),
				'properties' => array( 'display: flex', 'gap', 'list-style: none' ),
				'responsive' => 'Consider mobile hamburger menu',
			),
			'hero section' => array(
				'selectors'  => array( '.hero', '.hero-content', '.hero-title' ),
				'properties' => array( 'min-height', 'background', 'text-align: center' ),
				'responsive' => 'Stack content on mobile',
			),
			'card grid'    => array(
				'selectors'  => array( '.card-grid', '.card', '.card-image', '.card-content' ),
				'properties' => array( 'display: grid', 'grid-template-columns', 'gap' ),
				'responsive' => 'Single column on mobile, 2-3 on larger screens',
			),
			'footer'       => array(
				'selectors'  => array( '.site-footer', '.footer-widgets', '.footer-copyright' ),
				'properties' => array( 'background', 'padding', 'color' ),
				'responsive' => 'Stack columns on mobile',
			),
		);

		$guidance = $base_guidance[ strtolower( $component ) ] ?? array(
			'selectors'  => array( '.' . sanitize_title( $component ) ),
			'properties' => array(),
			'responsive' => 'Consider mobile-first approach',
		);

		if ( $framework === 'tailwind' ) {
			$guidance['note'] = 'Use Tailwind utility classes instead of custom CSS';
		} elseif ( $framework === 'sass' ) {
			$guidance['note'] = 'Use Sass features like variables, nesting, and mixins';
		}

		return $guidance;
	}

	/**
	 * Update theme.json
	 */
	private function tool_update_theme_json( array $args ): array {
		$theme_slug = $args['theme_slug'] ?? '';
		$settings   = $args['settings'] ?? array();

		if ( empty( $theme_slug ) ) {
			return array( 'error' => 'Theme slug required' );
		}

		$theme = wp_get_theme( $theme_slug );

		if ( ! $theme->exists() ) {
			return array( 'error' => "Theme '$theme_slug' not found" );
		}

		$json_path = $theme->get_stylesheet_directory() . '/theme.json';

		// Load existing or create new
		if ( file_exists( $json_path ) ) {
			$existing = json_decode( file_get_contents( $json_path ), true );
			if ( ! $existing ) {
				return array( 'error' => 'Failed to parse existing theme.json' );
			}
		} else {
			$existing = array(
				'$schema' => 'https://schemas.wp.org/trunk/theme.json',
				'version' => 2,
			);
		}

		// Deep merge settings
		$merged = $this->array_merge_deep( $existing, $settings );

		// Write back
		$result = file_put_contents(
			$json_path,
			wp_json_encode( $merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		if ( $result === false ) {
			return array( 'error' => 'Failed to write theme.json' );
		}

		return array(
			'success' => true,
			'message' => 'theme.json updated',
			'theme'   => $theme_slug,
			'changes' => array_keys( $settings ),
		);
	}

	/**
	 * Deep merge arrays
	 */
	private function array_merge_deep( array $array1, array $array2 ): array {
		foreach ( $array2 as $key => $value ) {
			if ( is_array( $value ) && isset( $array1[ $key ] ) && is_array( $array1[ $key ] ) ) {
				$array1[ $key ] = $this->array_merge_deep( $array1[ $key ], $value );
			} else {
				$array1[ $key ] = $value;
			}
		}
		return $array1;
	}

	/**
	 * Activate a theme
	 */
	private function tool_activate_theme( array $args ): array {
		$theme_slug = $args['theme_slug'] ?? '';

		if ( empty( $theme_slug ) ) {
			return array( 'error' => 'Theme slug required' );
		}

		$theme = wp_get_theme( $theme_slug );

		if ( ! $theme->exists() ) {
			return array( 'error' => "Theme '$theme_slug' not found" );
		}

		if ( ! current_user_can( 'switch_themes' ) ) {
			return array( 'error' => 'You do not have permission to switch themes' );
		}

		switch_theme( $theme_slug );

		return array(
			'success' => true,
			'message' => "Theme '{$theme->get('Name')}' activated",
			'slug'    => $theme_slug,
		);
	}
}

// Register the agent
add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Theme_Builder() );
	}
);
