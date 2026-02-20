<?php
/**
 * Agent Name: Site Doctor
 * Version: 1.0.0
 * Description: Diagnoses your site's health. Finds broken links, database bloat, orphaned content, outdated plugins, and PHP errors.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: Maintenance
 * Tags: health, maintenance, database, broken-links, plugins, errors
 * Capabilities: manage_options
 * Icon: 🩺
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Doctor Agent
 *
 * Diagnostic and maintenance advisory agent. Surfaces technical health issues
 * and recommends fixes. Read-only — never makes changes directly.
 */
class Agentic_Site_Doctor extends \Agentic\Agent_Base {

	private function load_system_prompt(): string {
		$prompt_file = __DIR__ . '/templates/system-prompt.txt';
		return file_exists( $prompt_file ) ? file_get_contents( $prompt_file ) : '';
	}

	public function get_id(): string {
		return 'site-doctor';
	}

	public function get_name(): string {
		return 'Site Doctor';
	}

	public function get_description(): string {
		return 'Diagnoses your site\'s health. Finds broken links, database bloat, orphaned content, outdated plugins, and PHP errors.';
	}

	public function get_system_prompt(): string {
		return $this->load_system_prompt();
	}

	public function get_icon(): string {
		return '🩺';
	}

	public function get_category(): string {
		return 'Maintenance';
	}

	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	public function get_welcome_message(): string {
		return "🩺 **Site Doctor**\n\n" .
			"I diagnose your site's technical health and tell you exactly what to fix.\n\n" .
			"**What I check:**\n" .
			"- **Database** — table sizes, autoload bloat, transient accumulation\n" .
			"- **Plugins** — outdated, inactive, and potentially risky plugins\n" .
			"- **Orphaned content** — old drafts, trash, spam comments, post revisions\n" .
			"- **Broken links** — internal links returning 404 errors\n" .
			"- **PHP errors** — recent fatal errors and warnings from the debug log\n\n" .
			"Run a full health check to get a scored report and priority action list.";
	}

	public function get_suggested_prompts(): array {
		return array(
			'Run a full site health check',
			'How is my database doing?',
			'Find broken internal links',
			'Show me recent PHP errors',
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
					'name'        => 'run_health_check',
					'description' => 'Run a comprehensive site health check. Returns a health score (0–100), a letter grade, a prioritised action list, and summary stats for database size, autoload bloat, transients, orphaned content, and plugins.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_database_health',
					'description' => 'Analyse the WordPress database: total size, largest tables, autoload option bloat (large autoloaded options slow every page load), transient count, and optionally the top 20 autoloaded options by size.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'include_options' => array(
								'type'        => 'boolean',
								'description' => 'Include the top 20 autoloaded options by size. Defaults to true.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_orphaned_content',
					'description' => 'Count orphaned content: draft posts, pending posts, auto-draft posts, trashed items, post revisions, spam comments, and unapproved comments. Optionally include a sample list of each type.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'include_list' => array(
								'type'        => 'boolean',
								'description' => 'Include sample lists of drafts and trashed items (up to 10 each). Defaults to false.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_plugin_status',
					'description' => 'List all installed plugins with their active/inactive status and available update information. Use this to identify plugin bloat and update risks.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'filter' => array(
								'type'        => 'string',
								'description' => '"all" (default), "active", "inactive", or "outdated".',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'check_broken_internal_links',
					'description' => 'Scan published posts for internal links that return HTTP 404. Tests each unique URL with a HEAD request (5 second timeout). Returns the broken URLs, which posts contain them, and a total count.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'post_type'  => array(
								'type'        => 'string',
								'description' => '"post" (default), "page", or "any".',
							),
							'post_limit' => array(
								'type'        => 'integer',
								'description' => 'Max posts to scan (1–50). Defaults to 30.',
							),
						),
					),
				),
			),

			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_php_errors',
					'description' => 'Read the WordPress debug log (wp-content/debug.log) and return recent PHP errors, warnings, and notices. Returns instructions for enabling the log if it does not exist.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Max log lines to return (1–100). Defaults to 50.',
							),
							'level' => array(
								'type'        => 'string',
								'description' => '"all" (default), "fatal" (fatal errors only), or "errors_warnings" (excludes notices).',
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
			'run_health_check'            => $this->tool_run_health_check(),
			'get_database_health'         => $this->tool_get_database_health( $arguments ),
			'get_orphaned_content'        => $this->tool_get_orphaned_content( $arguments ),
			'check_plugin_status'         => $this->tool_check_plugin_status( $arguments ),
			'check_broken_internal_links' => $this->tool_check_broken_internal_links( $arguments ),
			'get_php_errors'              => $this->tool_get_php_errors( $arguments ),
			default                       => null,
		};
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	private function tool_run_health_check(): array {
		$db       = $this->tool_get_database_health( array( 'include_options' => false ) );
		$orphaned = $this->tool_get_orphaned_content( array( 'include_list' => false ) );
		$plugins  = $this->tool_check_plugin_status( array( 'filter' => 'all' ) );

		$score   = 100;
		$actions = array();

		// Database autoload bloat.
		$autoload_kb = $db['autoload_size_kb'] ?? 0;
		if ( $autoload_kb > 1000 ) {
			$score -= 20;
			$actions[] = "Critical: Autoload data is {$autoload_kb} KB. Review top autoloaded options and clear expired transients.";
		} elseif ( $autoload_kb > 500 ) {
			$score -= 10;
			$actions[] = "Autoload data is {$autoload_kb} KB. Review autoloaded options to reduce page load overhead.";
		}

		// Transients.
		$transient_count = $db['transient_count'] ?? 0;
		if ( $transient_count > 500 ) {
			$score -= 10;
			$actions[] = "{$transient_count} transients in the database. A plugin may not be cleaning up. Clear expired transients.";
		} elseif ( $transient_count > 200 ) {
			$score -= 5;
			$actions[] = "{$transient_count} transients found. Consider clearing expired transients.";
		}

		// Revisions.
		$revisions = $orphaned['totals']['revisions'] ?? 0;
		if ( $revisions > 1000 ) {
			$score -= 10;
			$actions[] = "{$revisions} post revisions are consuming database space. Delete old revisions and add a revision limit.";
		} elseif ( $revisions > 500 ) {
			$score -= 5;
			$actions[] = "{$revisions} post revisions. Add `define('WP_POST_REVISIONS', 5);` to wp-config.php to cap future revisions.";
		}

		// Trash and spam.
		$trashed = $orphaned['totals']['trashed'] ?? 0;
		$spam    = $orphaned['totals']['spam_comments'] ?? 0;
		if ( $trashed > 50 ) {
			$score -= 5;
			$actions[] = "{$trashed} items in trash. Empty the trash to recover database space.";
		}
		if ( $spam > 100 ) {
			$score -= 5;
			$actions[] = "{$spam} spam comments. Empty the spam queue.";
		}

		// Outdated plugins.
		$outdated_count = $plugins['outdated_count'] ?? 0;
		if ( $outdated_count > 0 ) {
			$score -= min( 20, $outdated_count * 5 );
			$actions[] = "{$outdated_count} plugin(s) have available updates. Update to patch security vulnerabilities.";
		}

		// Inactive plugins.
		$inactive_count = $plugins['inactive_count'] ?? 0;
		if ( $inactive_count > 5 ) {
			$score -= 5;
			$actions[] = "{$inactive_count} inactive plugins installed. Remove unused plugins to reduce the attack surface.";
		}

		return array(
			'health_score'     => max( 0, $score ),
			'grade'            => $this->score_to_grade( $score ),
			'priority_actions' => $actions,
			'database'         => array(
				'total_size_mb'  => $db['total_size_mb'] ?? 0,
				'autoload_kb'    => $autoload_kb,
				'transients'     => $transient_count,
			),
			'orphaned_content' => $orphaned['totals'] ?? array(),
			'plugins'          => array(
				'active'         => $plugins['active_count'] ?? 0,
				'inactive'       => $plugins['inactive_count'] ?? 0,
				'outdated'       => $outdated_count,
			),
		);
	}

	private function tool_get_database_health( array $args ): array {
		global $wpdb;

		$include_options = (bool) ( $args['include_options'] ?? true );

		// Table sizes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tables = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS `table`,
				ROUND( ( data_length + index_length ) / 1024, 1 ) AS size_kb
				FROM information_schema.TABLES
				WHERE table_schema = %s
				ORDER BY ( data_length + index_length ) DESC',
				DB_NAME
			),
			ARRAY_A
		);

		$total_kb = array_sum( array_column( $tables ?? array(), 'size_kb' ) );

		// Autoload options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$autoload_rows = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS bytes
			FROM {$wpdb->options}
			WHERE autoload = 'yes'
			ORDER BY bytes DESC",
			ARRAY_A
		);

		$autoload_bytes = array_sum( array_column( $autoload_rows ?? array(), 'bytes' ) );
		$autoload_kb    = round( $autoload_bytes / 1024, 1 );

		// Transient count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$transient_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"
		);

		$result = array(
			'total_size_mb'    => round( $total_kb / 1024, 2 ),
			'autoload_size_kb' => $autoload_kb,
			'autoload_options' => count( $autoload_rows ?? array() ),
			'transient_count'  => $transient_count,
			'table_count'      => count( $tables ?? array() ),
			'largest_tables'   => array_slice( $tables ?? array(), 0, 10 ),
		);

		if ( $include_options ) {
			$result['top_autoloaded_options'] = array_map(
				fn( $r ) => array( 'name' => $r['option_name'], 'bytes' => (int) $r['bytes'] ),
				array_slice( $autoload_rows ?? array(), 0, 20 )
			);
		}

		return $result;
	}

	private function tool_get_orphaned_content( array $args ): array {
		global $wpdb;

		$include_list = (bool) ( $args['include_list'] ?? false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$counts = $wpdb->get_results(
			"SELECT post_status, post_type, COUNT(*) AS cnt
			FROM {$wpdb->posts}
			WHERE post_status IN ('draft','pending','auto-draft','trash')
			   OR post_type = 'revision'
			GROUP BY post_status, post_type",
			ARRAY_A
		);

		$totals = array(
			'drafts'    => 0,
			'pending'   => 0,
			'auto_draft'=> 0,
			'trashed'   => 0,
			'revisions' => 0,
		);

		foreach ( $counts ?? array() as $row ) {
			if ( 'revision' === $row['post_type'] ) {
				$totals['revisions'] += (int) $row['cnt'];
			} elseif ( 'draft' === $row['post_status'] ) {
				$totals['drafts'] += (int) $row['cnt'];
			} elseif ( 'pending' === $row['post_status'] ) {
				$totals['pending'] += (int) $row['cnt'];
			} elseif ( 'auto-draft' === $row['post_status'] ) {
				$totals['auto_draft'] += (int) $row['cnt'];
			} elseif ( 'trash' === $row['post_status'] ) {
				$totals['trashed'] += (int) $row['cnt'];
			}
		}

		// Comment counts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$comment_counts = $wpdb->get_results(
			"SELECT comment_approved, COUNT(*) AS cnt FROM {$wpdb->comments} GROUP BY comment_approved",
			ARRAY_A
		);

		foreach ( $comment_counts ?? array() as $row ) {
			if ( 'spam' === $row['comment_approved'] ) {
				$totals['spam_comments'] = (int) $row['cnt'];
			} elseif ( '0' === (string) $row['comment_approved'] ) {
				$totals['unapproved_comments'] = (int) $row['cnt'];
			}
		}

		$result = array( 'totals' => $totals );

		if ( $include_list ) {
			$result['samples'] = array(
				'drafts'  => array_map(
					fn( $p ) => array( 'id' => $p->ID, 'title' => $p->post_title, 'modified' => $p->post_modified ),
					get_posts( array( 'post_status' => 'draft', 'posts_per_page' => 10 ) )
				),
				'trashed' => array_map(
					fn( $p ) => array( 'id' => $p->ID, 'title' => $p->post_title, 'modified' => $p->post_modified ),
					get_posts( array( 'post_status' => 'trash', 'posts_per_page' => 10 ) )
				),
			);
		}

		return $result;
	}

	private function tool_check_plugin_status( array $args ): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$filter         = $args['filter'] ?? 'all';
		$update_plugins = get_site_transient( 'update_plugins' );
		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$active_count   = 0;
		$inactive_count = 0;
		$outdated_count = 0;
		$results        = array();

		foreach ( $all_plugins as $plugin_file => $data ) {
			$is_active   = in_array( $plugin_file, $active_plugins, true );
			$has_update  = isset( $update_plugins->response[ $plugin_file ] );
			$new_version = $has_update ? ( $update_plugins->response[ $plugin_file ]->new_version ?? null ) : null;

			if ( $is_active ) {
				++$active_count;
			} else {
				++$inactive_count;
			}
			if ( $has_update ) {
				++$outdated_count;
			}

			$include = match ( $filter ) {
				'active'   => $is_active,
				'inactive' => ! $is_active,
				'outdated' => $has_update,
				default    => true,
			};

			if ( $include ) {
				$results[] = array(
					'file'        => $plugin_file,
					'name'        => $data['Name'],
					'version'     => $data['Version'],
					'is_active'   => $is_active,
					'has_update'  => $has_update,
					'new_version' => $new_version,
				);
			}
		}

		return array(
			'total'          => count( $all_plugins ),
			'active_count'   => $active_count,
			'inactive_count' => $inactive_count,
			'outdated_count' => $outdated_count,
			'plugins'        => $results,
		);
	}

	private function tool_check_broken_internal_links( array $args ): array {
		$post_type  = $args['post_type'] ?? 'post';
		$post_limit = min( max( (int) ( $args['post_limit'] ?? 30 ), 1 ), 50 );
		$site_url   = untrailingslashit( get_bloginfo( 'url' ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $post_limit,
			)
		);

		$broken       = array();
		$checked_urls = array();

		foreach ( $posts as $post ) {
			preg_match_all( '/<a[^>]+href=["\']([^"\'#?]+)["\'][^>]*>/i', $post->post_content, $link_matches );

			foreach ( $link_matches[1] as $href ) {
				if ( ! str_starts_with( $href, $site_url ) && ! str_starts_with( $href, '/' ) ) {
					continue;
				}

				$url = str_starts_with( $href, '/' ) ? $site_url . $href : $href;

				if ( isset( $checked_urls[ $url ] ) ) {
					if ( 404 === $checked_urls[ $url ] ) {
						$broken[] = array(
							'post_id'   => $post->ID,
							'post_title'=> $post->post_title,
							'broken_url'=> $url,
							'status'    => 404,
						);
					}
					continue;
				}

				$response             = wp_remote_head( $url, array( 'timeout' => 5, 'sslverify' => false ) );
				$status               = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
				$checked_urls[ $url ] = $status;

				if ( 404 === $status ) {
					$broken[] = array(
						'post_id'   => $post->ID,
						'post_title'=> $post->post_title,
						'broken_url'=> $url,
						'status'    => $status,
					);
				}
			}
		}

		return array(
			'posts_scanned' => count( $posts ),
			'links_checked' => count( $checked_urls ),
			'broken_count'  => count( $broken ),
			'broken_links'  => $broken,
			'summary'       => count( $broken ) === 0
				? 'No broken internal links found.'
				: 'Fix broken links by updating or removing the broken URLs in each post.',
		);
	}

	private function tool_get_php_errors( array $args ): array {
		$limit    = min( max( (int) ( $args['limit'] ?? 50 ), 1 ), 100 );
		$level    = $args['level'] ?? 'all';
		$log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			return array(
				'error' => 'debug.log not found. PHP errors are not being logged.',
				'how_to_enable' => "Add to wp-config.php:\ndefine('WP_DEBUG', true);\ndefine('WP_DEBUG_LOG', true);\ndefine('WP_DEBUG_DISPLAY', false);",
			);
		}

		$handle = fopen( $log_file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array( 'error' => 'Could not open debug.log.' );
		}

		$all_lines = array();
		while ( ! feof( $handle ) && count( $all_lines ) < 5000 ) {
			$line = fgets( $handle );
			if ( false !== $line && trim( $line ) ) {
				$all_lines[] = trim( $line );
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$all_lines = array_slice( $all_lines, -( $limit * 3 ) );

		$filtered = array();
		foreach ( $all_lines as $line ) {
			$include = match ( $level ) {
				'fatal'           => str_contains( $line, 'Fatal error' ) || str_contains( $line, 'PHP Fatal' ),
				'errors_warnings' => str_contains( $line, 'error' ) || str_contains( $line, 'Error' ) || str_contains( $line, 'Warning' ),
				default           => true,
			};
			if ( $include ) {
				$filtered[] = $line;
			}
		}

		$filtered = array_slice( $filtered, -$limit );

		return array(
			'log_file' => 'wp-content/debug.log',
			'log_size' => round( filesize( $log_file ) / 1024, 1 ) . ' KB',
			'level'    => $level,
			'returned' => count( $filtered ),
			'entries'  => $filtered,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function score_to_grade( int $score ): string {
		return match ( true ) {
			$score >= 90 => 'A — Excellent',
			$score >= 75 => 'B — Good',
			$score >= 60 => 'C — Needs attention',
			$score >= 45 => 'D — Poor',
			default      => 'F — Critical issues found',
		};
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Site_Doctor() );
	}
);
