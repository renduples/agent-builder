<?php
/**
 * Agent Registry - Manages agent installation, activation, and lifecycle
 *
 * Similar to WordPress plugin management, this class handles:
 * - Discovering installed agents
 * - Activating/deactivating agents
 * - Loading active agents
 * - Managing the agent library
 *
 * @package    Agent_Builder
 * @subpackage Includes
 * @author     Agent Builder Team <support@agentic-plugin.com>
 * @license    GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://agentic-plugin.com
 * @since      0.2.0
 *
 * php version 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Agentic_Agent_Registry
 *
 * Central registry for all agents in the system.
 */
class Agentic_Agent_Registry {

	/**
	 * Singleton instance
	 *
	 * @var Agentic_Agent_Registry|null
	 */
	private static ?Agentic_Agent_Registry $instance = null;

	/**
	 * Directory where agents are installed
	 *
	 * @var string
	 */
	private string $agents_dir;

	/**
	 * Directory for the local agent library (available agents to install)
	 *
	 * @var string
	 */
	private string $library_dir;

	/**
	 * Cache of discovered agents
	 *
	 * @var array
	 */
	private array $agents_cache = array();

	/**
	 * Registered agent instances (Agent_Base objects)
	 *
	 * @var array<string, \Agentic\Agent_Base>
	 */
	private array $agent_instances = array();

	/**
	 * Option name for active agents
	 */
	const ACTIVE_AGENTS_OPTION = 'agentic_active_agents';

	/**
	 * Required agent header fields
	 */
	const REQUIRED_HEADERS = array(
		'Agent Name',
		'Version',
		'Description',
		'Author',
	);

	/**
	 * Get singleton instance
	 *
	 * @return Agentic_Agent_Registry
	 */
	public static function get_instance(): Agentic_Agent_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->agents_dir  = WP_CONTENT_DIR . '/agents';
		$this->library_dir = AGENT_BUILDER_DIR . 'library';

		// Ensure directories exist.
		$this->ensure_directories();
	}

	/**
	 * Ensure required directories exist
	 */
	private function ensure_directories(): void {
		if ( ! file_exists( $this->agents_dir ) ) {
			wp_mkdir_p( $this->agents_dir );

			// Create index.php for security.
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();

			$wp_filesystem->put_contents(
				$this->agents_dir . '/index.php',
				"<?php\n// Silence is golden.\n",
				FS_CHMOD_FILE
			);
		}

		if ( ! file_exists( $this->library_dir ) ) {
			wp_mkdir_p( $this->library_dir );
		}
	}

	/**
	 * Get all installed agents
	 *
	 * Includes both user-installed agents (wp-content/agents) and
	 * bundled library agents (agent-builder/library).
	 *
	 * @param bool $force_refresh Force refresh the cache.
	 * @return array
	 */
	public function get_installed_agents( bool $force_refresh = false ): array {
		if ( ! empty( $this->agents_cache ) && ! $force_refresh ) {
			return $this->agents_cache;
		}

		$agents = array();

		// First, load agents from wp-content/agents (user-installed).
		if ( is_dir( $this->agents_dir ) ) {
			$agent_folders = scandir( $this->agents_dir );

			foreach ( $agent_folders as $folder ) {
				if ( '.' === $folder || '..' === $folder || 'index.php' === $folder ) {
					continue;
				}

				$agent_path = $this->agents_dir . '/' . $folder;

				if ( ! is_dir( $agent_path ) ) {
					continue;
				}

				$main_file = $this->find_agent_main_file( $agent_path, $folder );

				if ( $main_file ) {
					$agent_data = $this->get_agent_data( $main_file );

					if ( $agent_data ) {
						$agent_data['slug']      = $folder;
						$agent_data['path']      = $main_file;
						$agent_data['directory'] = $agent_path;
						$agent_data['active']    = $this->is_agent_active( $folder );
						$agent_data['bundled']   = false;

						$agents[ $folder ] = $agent_data;
					}
				}
			}
		}

		// Then, load bundled agents from library (skip if already installed).
		if ( is_dir( $this->library_dir ) ) {
			$library_folders = scandir( $this->library_dir );

			foreach ( $library_folders as $folder ) {
				if ( '.' === $folder || '..' === $folder || 'README.md' === $folder ) {
					continue;
				}

				// Skip if already loaded from agents_dir.
				if ( isset( $agents[ $folder ] ) ) {
					continue;
				}

				$agent_path = $this->library_dir . '/' . $folder;

				if ( ! is_dir( $agent_path ) ) {
					continue;
				}

				$main_file = $this->find_agent_main_file( $agent_path, $folder );

				if ( $main_file ) {
					$agent_data = $this->get_agent_data( $main_file );

					if ( $agent_data ) {
						$agent_data['slug']      = $folder;
						$agent_data['path']      = $main_file;
						$agent_data['directory'] = $agent_path;
						$agent_data['active']    = $this->is_agent_active( $folder );
						$agent_data['bundled']   = true;

						$agents[ $folder ] = $agent_data;
					}
				}
			}
		}

		$this->agents_cache = $agents;

		return $agents;
	}

	/**
	 * Find the main agent file in a directory
	 *
	 * @param string $agent_path Path to agent directory.
	 * @param string $folder     Folder name.
	 * @return string|null
	 */
	private function find_agent_main_file( string $agent_path, string $folder ): ?string {
		// First check for agent.php.
		if ( file_exists( $agent_path . '/agent.php' ) ) {
			return $agent_path . '/agent.php';
		}

		// Then check for {folder-name}.php.
		if ( file_exists( $agent_path . '/' . $folder . '.php' ) ) {
			return $agent_path . '/' . $folder . '.php';
		}

		// Look for any PHP file with agent headers.
		$php_files = glob( $agent_path . '/*.php' );

		foreach ( $php_files as $file ) {
			$data = $this->get_agent_data( $file );
			if ( $data && ! empty( $data['name'] ) ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Parse agent file headers (similar to get_plugin_data)
	 *
	 * @param string $file Path to agent file.
	 * @return array|null
	 */
	public function get_agent_data( string $file ): ?array {
		if ( ! file_exists( $file ) ) {
			return null;
		}

		$default_headers = array(
			'name'         => 'Agent Name',
			'version'      => 'Version',
			'description'  => 'Description',
			'author'       => 'Author',
			'author_uri'   => 'Author URI',
			'agent_uri'    => 'Agent URI',
			'license'      => 'License',
			'license_uri'  => 'License URI',
			'text_domain'  => 'Text Domain',
			'requires_wp'  => 'Requires at least',
			'requires_php' => 'Requires PHP',
			'capabilities' => 'Capabilities',
			'category'     => 'Category',
			'tags'         => 'Tags',
			'icon'         => 'Icon',
		);

		$data = get_file_data( $file, $default_headers );

		// Must have at least a name.
		if ( empty( $data['name'] ) ) {
			return null;
		}

		// Parse capabilities as comma-separated list.
		if ( ! empty( $data['capabilities'] ) ) {
			$data['capabilities'] = array_map( 'trim', explode( ',', $data['capabilities'] ) );
		} else {
			$data['capabilities'] = array();
		}

		// Parse tags as comma-separated list.
		if ( ! empty( $data['tags'] ) ) {
			$data['tags'] = array_map( 'trim', explode( ',', $data['tags'] ) );
		} else {
			$data['tags'] = array();
		}

		return $data;
	}

	/**
	 * Check if an agent is active
	 *
	 * @param string $slug Agent slug.
	 * @return bool
	 */
	public function is_agent_active( string $slug ): bool {
		$active_agents = get_option( self::ACTIVE_AGENTS_OPTION, array() );
		return in_array( $slug, $active_agents, true );
	}

	/**
	 * Get all active agents
	 *
	 * @return array
	 */
	public function get_active_agents(): array {
		return get_option( self::ACTIVE_AGENTS_OPTION, array() );
	}

	/**
	 * Activate an agent
	 *
	 * @param string $slug Agent slug.
	 * @return bool|WP_Error
	 */
	public function activate_agent( string $slug ) {
		$agents = $this->get_installed_agents();

		if ( ! isset( $agents[ $slug ] ) ) {
			return new WP_Error( 'agent_not_found', __( 'Agent not found.', 'agentbuilder' ) );
		}

		if ( $this->is_agent_active( $slug ) ) {
			return new WP_Error( 'already_active', __( 'Agent is already active.', 'agentbuilder' ) );
		}

		$agent = $agents[ $slug ];

		// Check PHP version requirement.
		if ( ! empty( $agent['requires_php'] ) && version_compare( PHP_VERSION, $agent['requires_php'], '<' ) ) {
			return new WP_Error(
				'php_version',
				/* translators: %s: minimum required PHP version */
			);
		}

		// Check WordPress version requirement.
		if ( ! empty( $agent['requires_wp'] ) && version_compare( get_bloginfo( 'version' ), $agent['requires_wp'], '<' ) ) {
			return new WP_Error(
				'wp_version',
				/* translators: %s: minimum required WordPress version */
			);
		}

		// Load the agent to check for errors.
		$result = $this->load_agent( $agent );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Call activation hook if exists.
		$activation_hook = 'agentic_agent_' . $slug . '_activate';
		if ( has_action( $activation_hook ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook name by design.
			do_action( $activation_hook );
		}

		$active_agents   = $this->get_active_agents();
		$active_agents[] = $slug;
		update_option( self::ACTIVE_AGENTS_OPTION, array_unique( $active_agents ) );

		// Clear cache.
		$this->agents_cache = array();

		// Log activation.
		if ( class_exists( 'Agentic_Audit_Log' ) ) {
			Agentic_Audit_Log::get_instance()->log(
				'agent_activated',
				sprintf( 'Agent activated: %s', $agent['name'] ),
				array(
					'slug'    => $slug,
					'version' => $agent['version'],
				)
			);
		}

		do_action( 'agentic_agent_activated', $slug, $agent );

		return true;
	}

	/**
	 * Deactivate an agent
	 *
	 * @param string $slug Agent slug.
	 * @return bool|WP_Error
	 */
	public function deactivate_agent( string $slug ) {
		if ( ! $this->is_agent_active( $slug ) ) {
			return new WP_Error( 'not_active', __( 'Agent is not active.', 'agentbuilder' ) );
		}

		$agents = $this->get_installed_agents( true );
		$agent  = $agents[ $slug ] ?? null;

		// Call deactivation hook if exists.
		$deactivation_hook = 'agentic_agent_' . $slug . '_deactivate';
		if ( has_action( $deactivation_hook ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook name by design.
			do_action( $deactivation_hook );
		}

		// Remove from active agents.
		$active_agents = $this->get_active_agents();
		$active_agents = array_diff( $active_agents, array( $slug ) );
		update_option( self::ACTIVE_AGENTS_OPTION, array_values( $active_agents ) );

		// Clear cache.
		$this->agents_cache = array();

		// Log deactivation.
		if ( class_exists( 'Agentic_Audit_Log' ) && $agent ) {
			Agentic_Audit_Log::get_instance()->log(
				'agent_deactivated',
				sprintf( 'Agent deactivated: %s', $agent['name'] ),
				array( 'slug' => $slug )
			);
		}

		do_action( 'agentic_agent_deactivated', $slug, $agent );

		return true;
	}

	/**
	 * Load a single agent
	 *
	 * @param array $agent Agent data.
	 * @return bool|WP_Error
	 */
	public function load_agent( array $agent ) {
		if ( empty( $agent['path'] ) || ! file_exists( $agent['path'] ) ) {
			return new WP_Error( 'file_not_found', __( 'Agent file not found.', 'agentbuilder' ) );
		}

		// Pre-check for class name conflicts to prevent fatal errors.
		$class_name = $this->extract_class_name( $agent['path'] );
		if ( $class_name && class_exists( $class_name, false ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
				error_log(
					sprintf(
						'Agentic: Skipping agent %s — class %s already declared.',
						$agent['slug'] ?? basename( $agent['path'] ),
						$class_name
					)
				);
			}
			return new WP_Error(
				'class_exists',
				sprintf(
					/* translators: %s: Class name */
					__( 'Cannot load agent: class %s is already declared by another agent.', 'agentbuilder' ),
					$class_name
				)
			);
		}

		try {
			include_once $agent['path'];
			return true;
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'load_error', /* translators: %s: Error message */
				sprintf( __( 'Error loading agent: %s', 'agentbuilder' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Extract the main class name from an agent file without loading it
	 *
	 * Scans for "class ClassName extends" pattern in the file.
	 *
	 * @param string $file Path to agent file.
	 * @return string|null Class name or null if not found.
	 */
	private function extract_class_name( string $file ): ?string {
		// Read first 4KB — class declaration is always near the top.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file for class name extraction.
		$contents = file_get_contents( $file, false, null, 0, 4096 );
		if ( ! $contents ) {
			return null;
		}

		if ( preg_match( '/^\s*class\s+(\w+)\s+extends\s/m', $contents, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Load all active agents
	 *
	 * Called during WordPress init to load all activated agents.
	 */
	public function load_active_agents(): void {
		$active_slugs = $this->get_active_agents();

		if ( empty( $active_slugs ) ) {
			// Still init agent instances for base functionality.
			$this->init_agent_instances();
			return;
		}

		// Include base class first.
		require_once AGENT_BUILDER_DIR . 'includes/class-agent-base.php';

		$installed = $this->get_installed_agents();

		foreach ( $active_slugs as $slug ) {
			if ( isset( $installed[ $slug ] ) ) {
				// License check: skip non-bundled premium agents if license invalid.
				if ( empty( $installed[ $slug ]['bundled'] ) && class_exists( '\Agentic\License_Client' ) ) {
					$license_client = \Agentic\License_Client::get_instance();
					if ( ! $license_client->can_agent_run( $slug ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
							error_log( sprintf( 'Agentic: Skipping agent %s — license invalid.', $slug ) );
						}
						continue;
					}
				}

				$result = $this->load_agent( $installed[ $slug ] );

				if ( is_wp_error( $result ) ) {
					// Log error but continue loading other agents.
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
						error_log(
							sprintf(
								'Agentic: Failed to load agent %s: %s',
								$slug,
								$result->get_error_message()
							)
						);
					}
				}
			}
		}

		// Allow agents to register their instances.
		do_action( 'agentic_register_agents', $this );

		do_action( 'agentic_agents_loaded' );
	}

	/**
	 * Get agents from the library (available to install)
	 *
	 * @param array $args Search/filter arguments.
	 * @return array
	 */
	public function get_library_agents( array $args = array() ): array {
		$defaults = array(
			'search'   => '',
			'category' => '',
			'page'     => 1,
			'per_page' => 12,
		);

		$args   = wp_parse_args( $args, $defaults );
		$agents = array();

		if ( ! is_dir( $this->library_dir ) ) {
			return array(
				'agents' => array(),
				'total'  => 0,
			);
		}

		$library_folders = scandir( $this->library_dir );

		foreach ( $library_folders as $folder ) {
			if ( '.' === $folder || '..' === $folder ) {
				continue;
			}

			$agent_path = $this->library_dir . '/' . $folder;

			if ( ! is_dir( $agent_path ) ) {
				continue;
			}

			$main_file = $this->find_agent_main_file( $agent_path, $folder );

			if ( $main_file ) {
				$agent_data = $this->get_agent_data( $main_file );

				if ( $agent_data ) {
					$agent_data['slug']         = $folder;
					$agent_data['library_path'] = $agent_path;
					$agent_data['installed']    = $this->is_agent_installed( $folder ) || $this->is_agent_active( $folder );

					// Apply search filter.
					if ( ! empty( $args['search'] ) ) {
						$search = strtolower( $args['search'] );
						$match  = str_contains( strtolower( $agent_data['name'] ), $search )
									|| str_contains( strtolower( $agent_data['description'] ), $search );

						if ( ! $match ) {
							continue;
						}
					}

					// Apply category filter.
					if ( ! empty( $args['category'] ) && ! empty( $agent_data['category'] ) ) {
						if ( strtolower( $agent_data['category'] ) !== strtolower( $args['category'] ) ) {
							continue;
						}
					}

					$agents[ $folder ] = $agent_data;
				}
			}
		}

		$total = count( $agents );

		// Pagination.
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$agents = array_slice( $agents, $offset, $args['per_page'], true );

		return array(
			'agents' => $agents,
			'total'  => $total,
			'pages'  => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Check if an agent is installed
	 *
	 * @param string $slug Agent slug.
	 * @return bool
	 */
	public function is_agent_installed( string $slug ): bool {
		return is_dir( $this->agents_dir . '/' . $slug ) || is_dir( $this->library_dir . '/' . $slug );
	}

	/**
	 * Install an agent from the library
	 *
	 * @param string $slug Agent slug.
	 * @return bool|WP_Error
	 */
	public function install_agent( string $slug ) {
		$library = $this->get_library_agents();

		$agent = null;
		foreach ( $library['agents'] as $lib_slug => $lib_agent ) {
			if ( $lib_slug === $slug ) {
				$agent = $lib_agent;
				break;
			}
		}

		// Also check full library without pagination.
		if ( ! $agent ) {
			$full_library = $this->get_library_agents( array( 'per_page' => 1000 ) );
			foreach ( $full_library['agents'] as $lib_slug => $lib_agent ) {
				if ( $lib_slug === $slug ) {
					$agent = $lib_agent;
					break;
				}
			}
		}

		if ( ! $agent ) {
			return new WP_Error( 'not_in_library', __( 'Agent not found in library.', 'agentbuilder' ) );
		}

		if ( $this->is_agent_installed( $slug ) ) {
			return new WP_Error( 'already_installed', __( 'Agent is already installed.', 'agentbuilder' ) );
		}

		$source = $agent['library_path'];
		$dest   = $this->agents_dir . '/' . $slug;

		// Copy agent files.
		$result = $this->copy_directory( $source, $dest );

		if ( ! $result ) {
			return new WP_Error( 'copy_failed', __( 'Failed to copy agent files.', 'agentbuilder' ) );
		}

		// Clear cache.
		$this->agents_cache = array();

		// Log installation.
		if ( class_exists( 'Agentic_Audit_Log' ) ) {
			Agentic_Audit_Log::get_instance()->log(
				'agent_installed',
				sprintf( 'Agent installed: %s', $agent['name'] ),
				array(
					'slug'    => $slug,
					'version' => $agent['version'],
				)
			);
		}

		do_action( 'agentic_agent_installed', $slug, $agent );

		return true;
	}

	/**
	 * Delete an installed agent
	 *
	 * @param string $slug Agent slug.
	 * @return bool|WP_Error
	 */
	public function delete_agent( string $slug ) {
		if ( ! $this->is_agent_installed( $slug ) ) {
			return new WP_Error( 'not_installed', __( 'Agent is not installed.', 'agentbuilder' ) );
		}

		// Deactivate first if active.
		if ( $this->is_agent_active( $slug ) ) {
			$this->deactivate_agent( $slug );
		}

		$agents     = $this->get_installed_agents();
		$agent      = $agents[ $slug ] ?? null;

		// Use the actual directory stored on the agent record (covers both agents_dir and library_dir).
		$agent_path = $agent['directory'] ?? ( $this->agents_dir . '/' . $slug );

		// Prevent deleting core bundled library agents (those without a .uploaded or agent-trainer marker).
		// Allow deleting trainer-generated agents that happen to live in library/.
		if ( str_starts_with( $agent_path, $this->library_dir ) ) {
			$is_user_created = file_exists( $agent_path . '/.uploaded' ) ||
			                   ! in_array( $slug, [ 'content-writer', 'seo-assistant', 'security-assistant', 'site-doctor', 'ai-radar', 'assistant-trainer', 'wordpress-assistant' ], true );
			if ( ! $is_user_created ) {
				return new \WP_Error( 'delete_bundled', __( 'Bundled library agents cannot be deleted.', 'agentbuilder' ) );
			}
		}

		// Call uninstall hook if exists.
		$uninstall_hook = 'agentic_agent_' . $slug . '_uninstall';
		if ( has_action( $uninstall_hook ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook name by design.
			do_action( $uninstall_hook );
		}

		$result = $this->delete_directory( $agent_path );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete agent files.', 'agentbuilder' ) );
		}

		// Clear cache.
		$this->agents_cache = array();

		// Log deletion.
		if ( class_exists( 'Agentic_Audit_Log' ) && $agent ) {
			Agentic_Audit_Log::get_instance()->log(
				'agent_deleted',
				sprintf( 'Agent deleted: %s', $agent['name'] ),
				array( 'slug' => $slug )
			);
		}

		do_action( 'agentic_agent_deleted', $slug );

		return true;
	}

	/**
	 * Copy a directory recursively
	 *
	 * @param string $source Source path.
	 * @param string $dest   Destination path.
	 * @return bool
	 */
	private function copy_directory( string $source, string $dest ): bool {
		if ( ! is_dir( $source ) ) {
			return false;
		}

		if ( ! wp_mkdir_p( $dest ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$target = $dest . '/' . $iterator->getSubPathname();

			if ( $item->isDir() ) {
				if ( ! wp_mkdir_p( $target ) ) {
					return false;
				}
			} elseif ( ! copy( $item->getPathname(), $target ) ) {
					return false;
			}
		}

		return true;
	}

	/**
	 * Delete a directory recursively
	 *
	 * @param string $dir Directory path.
	 * @return bool
	 */
	private function delete_directory( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				$wp_filesystem->rmdir( $item->getPathname() );
			} else {
				$wp_filesystem->delete( $item->getPathname() );
			}
		}

		return $wp_filesystem->rmdir( $dir );
	}

	/**
	 * Get agent categories from library
	 *
	 * @return array
	 */
	public function get_agent_categories(): array {
		$library    = $this->get_library_agents( array( 'per_page' => 1000 ) );
		$categories = array();

		foreach ( $library['agents'] as $agent ) {
			if ( ! empty( $agent['category'] ) ) {
				$cat = $agent['category'];
				if ( ! isset( $categories[ $cat ] ) ) {
					$categories[ $cat ] = 0;
				}
				++$categories[ $cat ];
			}
		}

		return $categories;
	}

	/**
	 * Get all agent tags from library
	 *
	 * @return array Associative array of tag => count
	 */
	public function get_agent_tags(): array {
		$library = $this->get_library_agents( array( 'per_page' => 1000 ) );
		$tags    = array();

		foreach ( $library['agents'] as $agent ) {
			if ( ! empty( $agent['tags'] ) && is_array( $agent['tags'] ) ) {
				foreach ( $agent['tags'] as $tag ) {
					$tag = strtolower( trim( $tag ) );
					if ( ! isset( $tags[ $tag ] ) ) {
						$tags[ $tag ] = 0;
					}
					++$tags[ $tag ];
				}
			}
		}

		arsort( $tags );
		return $tags;
	}

	/**
	 * Get agents directory path
	 *
	 * @return string
	 */
	public function get_agents_dir(): string {
		return $this->agents_dir;
	}

	/**
	 * Get library directory path
	 *
	 * @return string
	 */
	public function get_library_dir(): string {
		return $this->library_dir;
	}

	/**
	 * Register an agent instance
	 *
	 * Agents call this via the 'agentic_register_agents' hook.
	 *
	 * @param \Agentic\Agent_Base $agent Agent instance.
	 * @return bool Whether registration succeeded.
	 */
	public function register( \Agentic\Agent_Base $agent ): bool {
		$id = $agent->get_id();

		if ( isset( $this->agent_instances[ $id ] ) ) {
			return false; // Already registered.
		}

		$this->agent_instances[ $id ] = $agent;

		do_action( 'agentic_agent_instance_registered', $id, $agent );

		return true;
	}

	/**
	 * Get a registered agent instance by ID
	 *
	 * @param string $agent_id Agent ID.
	 * @return \Agentic\Agent_Base|null Agent instance or null.
	 */
	public function get_agent_instance( string $agent_id ): ?\Agentic\Agent_Base {
		return $this->agent_instances[ $agent_id ] ?? null;
	}

	/**
	 * Get all registered agent instances
	 *
	 * @return array<string, \Agentic\Agent_Base> All agent instances.
	 */
	public function get_all_instances(): array {
		return $this->agent_instances;
	}

	/**
	 * Get agent instances accessible by current user
	 *
	 * @return array<string, \Agentic\Agent_Base> Accessible agents.
	 */
	public function get_accessible_instances(): array {
		return array_filter(
			$this->agent_instances,
			fn( \Agentic\Agent_Base $agent ) => $agent->current_user_can_access()
		);
	}

	/**
	 * Load Agent_Base class and trigger agent registration
	 *
	 * Called after active agents are loaded.
	 */
	public function init_agent_instances(): void {
		// Include base class.
		require_once AGENT_BUILDER_DIR . 'includes/class-agent-base.php';

		// Allow agents to register themselves.
		do_action( 'agentic_register_agents', $this );
	}
}
