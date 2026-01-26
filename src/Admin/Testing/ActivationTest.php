<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ 4. STAGE - Activation/Deactivation Test Suite
 */
final class ActivationTest {


	/**
	 * Run all activation tests
	 */
	public static function run_all_tests(): array {
		$results = array();

		$results['php_version']           = self::test_php_version();
		$results['plugin_file']           = self::test_plugin_file_exists();
		$results['autoloader']            = self::test_autoloader();
		$results['bootstrap']             = self::test_bootstrap();
		$results['cpt_registration']      = self::test_cpt_registration();
		$results['taxonomy_registration'] = self::test_taxonomy_registration();
		$results['rewrite_rules']         = self::test_rewrite_rules();
		$results['rating_table']          = self::test_rating_table();
		$results['multisite_support']     = self::test_multisite_support();

		return $results;
	}

	/**
	 * Test: PHP Version Check
	 */
	public static function test_php_version(): array {
		$required = '7.4';
		$current  = PHP_VERSION;
		$pass     = version_compare( $current, $required, '>=' );

		if ( $pass ) {
			$message = sprintf(
				/* translators: 1: %1$s; 2: %2$s. */
				esc_html__( '✅ PHP %1$s >= %2$s', 'mhm-rentiva' ),
				esc_html( $current ),
				esc_html( $required )
			);
		} else {
			$message = sprintf(
				/* translators: 1: %1$s; 2: %2$s. */
				esc_html__( '❌ PHP %1$s < %2$s', 'mhm-rentiva' ),
				esc_html( $current ),
				esc_html( $required )
			);
		}

		return array(
			'test'     => __( 'PHP Version Check', 'mhm-rentiva' ),
			'status'   => $pass ? 'pass' : 'fail',
			'message'  => $message,
			'required' => $required,
			'current'  => $current,
		);
	}

	/**
	 * Test: Plugin File Exists
	 */
	public static function test_plugin_file_exists(): array {
		$main_file = MHM_RENTIVA_PLUGIN_FILE;
		$pass      = file_exists( $main_file );

		if ( $pass ) {
			$message = sprintf(
				/* translators: %s placeholder. */
				esc_html__( '✅ Main file exists: %s', 'mhm-rentiva' ),
				esc_html( $main_file )
			);
		} else {
			$message = sprintf(
				/* translators: %s placeholder. */
				esc_html__( '❌ Main file not found: %s', 'mhm-rentiva' ),
				esc_html( $main_file )
			);
		}

		return array(
			'test'    => __( 'Plugin Main File', 'mhm-rentiva' ),
			'status'  => $pass ? 'pass' : 'fail',
			'message' => $message,
			'file'    => $main_file,
		);
	}

	/**
	 * Test: Autoloader
	 */
	public static function test_autoloader(): array {
		$test_classes = array(
			'MHMRentiva\\Plugin',
			'MHMRentiva\\Admin\\Core\\AssetManager',
			'MHMRentiva\\Admin\\Vehicle\\PostType\\Vehicle',
			'MHMRentiva\\Admin\\Booking\\PostType\\Booking',
		);

		$loaded = array();
		$failed = array();

		foreach ( $test_classes as $class ) {
			if ( class_exists( $class ) ) {
				$loaded[] = $class;
			} else {
				$failed[] = $class;
			}
		}

		$pass = empty( $failed );

		if ( $pass ) {
			$message = sprintf(
				/* translators: 1: %1$d; 2: %2$d. */
				esc_html__( '✅ %1$d/%2$d classes loaded', 'mhm-rentiva' ),
				count( $loaded ),
				count( $test_classes )
			);
		} else {
			$message = sprintf(
				/* translators: %d placeholder. */
				esc_html__( '❌ %d classes failed to load', 'mhm-rentiva' ),
				count( $failed )
			);
		}

		return array(
			'test'    => __( 'PSR-4 Autoloader', 'mhm-rentiva' ),
			'status'  => $pass ? 'pass' : 'fail',
			'message' => $message,
			'loaded'  => $loaded,
			'failed'  => $failed,
		);
	}

	/**
	 * Test: Bootstrap Process
	 */
	public static function test_bootstrap(): array {
		$pass = class_exists( 'MHMRentiva\\Plugin' );

		if ( $pass ) {
			// Check if VERSION constant is accessible
			$version_accessible = defined( 'MHM_RENTIVA_VERSION' );
			$pass               = $pass && $version_accessible;
		}

		return array(
			'test'    => __( 'Bootstrap Process', 'mhm-rentiva' ),
			'status'  => $pass ? 'pass' : 'fail',
			'message' => $pass ?
				esc_html__( '✅ Plugin bootstrapped successfully', 'mhm-rentiva' ) :
				esc_html__( '❌ Bootstrap failed', 'mhm-rentiva' ),
			'version' => MHM_RENTIVA_VERSION ?? 'undefined',
		);
	}

	/**
	 * Test: CPT Registration
	 */
	public static function test_cpt_registration(): array {
		// Real post type names
		$cpts       = array(
			'vehicle',           // Vehicle
			'vehicle_booking',   // Booking
			'vehicle_addon',     // Addon
			'mhm_message',      // Message (POST_TYPE constant: mhm_message)
			'mhm_app_log',       // Log (TYPE constant: mhm_app_log)
		);
		$registered = array();
		$missing    = array();

		foreach ( $cpts as $cpt ) {
			if ( post_type_exists( $cpt ) ) {
				$registered[] = $cpt;
			} else {
				$missing[] = $cpt;
			}
		}

		// Vehicle and booking are mandatory
		$critical_cpts       = array( 'vehicle', 'vehicle_booking' );
		$critical_registered = array_intersect( $critical_cpts, $registered );
		$pass                = count( $critical_registered ) === count( $critical_cpts );

		if ( $pass ) {
			$message = sprintf(
				/* translators: 1: %1$d; 2: %2$d. */
				esc_html__( '✅ %1$d/%2$d CPTs registered', 'mhm-rentiva' ),
				count( $registered ),
				count( $cpts )
			);
		} else {
			$message = sprintf(
				/* translators: %d placeholder. */
				esc_html__( '⚠️ %d critical CPTs missing', 'mhm-rentiva' ),
				count( $critical_cpts ) - count( $critical_registered )
			);
		}

		return array(
			'test'       => __( 'Custom Post Types', 'mhm-rentiva' ),
			'status'     => $pass ? 'pass' : 'warning',
			'message'    => $message,
			'registered' => $registered,
			'missing'    => $missing,
		);
	}

	/**
	 * Test: Taxonomy Registration
	 */
	public static function test_taxonomy_registration(): array {
		$taxonomy = 'vehicle_category';
		$pass     = taxonomy_exists( $taxonomy );

		return array(
			'test'     => __( 'Taxonomies', 'mhm-rentiva' ),
			'status'   => $pass ? 'pass' : 'fail',
			'message'  => $pass ?
				esc_html__( '✅ Vehicle category taxonomy registered', 'mhm-rentiva' ) :
				esc_html__( '❌ Vehicle category taxonomy not registered', 'mhm-rentiva' ),
			'taxonomy' => $taxonomy,
		);
	}

	/**
	 * Test: Rewrite Rules
	 */
	public static function test_rewrite_rules(): array {
		global $wp_rewrite;

		// Check Vehicle post type rewrite setting
		$vehicle_post_type        = get_post_type_object( 'vehicle' );
		$vehicle_rewrite_disabled = $vehicle_post_type && isset( $vehicle_post_type->rewrite ) && $vehicle_post_type->rewrite === false;

		// Get rewrite rules (flush if not exists)
		$rules = $wp_rewrite->wp_rewrite_rules();

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			// Rewrite rules not created, let's flush
			flush_rewrite_rules();
			$rules = $wp_rewrite->wp_rewrite_rules();
		}

		// Check for vehicle-related rewrite rules (taxonomy, etc.)
		$vehicle_rules = array();
		if ( is_array( $rules ) ) {
			$vehicle_rules = array_filter(
				$rules,
				function ( $rule, $pattern ) {
					return strpos( $pattern, 'vehicle' ) !== false || strpos( $pattern, 'vehicle-category' ) !== false;
				},
				ARRAY_FILTER_USE_BOTH
			);
		}

		// If Vehicle post type rewrite is disabled (by design), check taxonomy rewrite instead
		if ( $vehicle_rewrite_disabled ) {
			// Vehicle post type uses query string (?vehicle=slug) - this is intentional
			// Check taxonomy rewrite rules instead
			$taxonomy_rewrite_found = ! empty( $vehicle_rules );

			if ( $taxonomy_rewrite_found ) {
				$message = sprintf(
					/* translators: %d placeholder. */
					esc_html__( '✅ Vehicle taxonomy rewrite rules found (%d rules). Vehicle post type uses query string (by design)', 'mhm-rentiva' ),
					count( $vehicle_rules )
				);
				$pass = true;
			} else {
				// Check if taxonomy exists and has rewrite enabled
				$taxonomy             = get_taxonomy( 'vehicle_category' );
				$taxonomy_has_rewrite = $taxonomy && ! empty( $taxonomy->rewrite );

				if ( $taxonomy_has_rewrite ) {
					$message = esc_html__( '⚠️ Vehicle taxonomy rewrite rules not found (flush rewrite rules in Settings → Permalinks)', 'mhm-rentiva' );
					$pass    = false;
				} else {
					$message = esc_html__( '✅ Vehicle post type uses query string (by design). No rewrite rules needed', 'mhm-rentiva' );
					$pass    = true;
				}
			}
		} else {
			// Vehicle post type should have rewrite rules
			$pass = ! empty( $vehicle_rules );

			if ( $pass ) {
				$message = sprintf(
					/* translators: %d placeholder. */
					esc_html__( '✅ %d vehicle rewrite rules found', 'mhm-rentiva' ),
					count( $vehicle_rules )
				);
			} else {
				$message = esc_html__( '⚠️ No vehicle rewrite rules found (flush rewrite rules in Settings → Permalinks)', 'mhm-rentiva' );
			}
		}

		return array(
			'test'                     => __( 'Rewrite Rules', 'mhm-rentiva' ),
			'status'                   => $pass ? 'pass' : 'warning',
			'message'                  => $message,
			'rules_count'              => count( $vehicle_rules ),
			'vehicle_rewrite_disabled' => $vehicle_rewrite_disabled,
		);
	}

	/**
	 * Test: Rating Table Creation
	 */
	public static function test_rating_table(): array {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'mhm_rentiva_ratings';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		$pass         = ( $table_exists === $table_name );

		return array(
			'test'    => __( 'Rating Table', 'mhm-rentiva' ),
			'status'  => $pass ? 'pass' : 'warning',
			'message' => $pass ?
				esc_html__( '✅ Rating table exists', 'mhm-rentiva' ) :
				esc_html__( '⚠️ Rating table not created (will be created on first use)', 'mhm-rentiva' ),
			'table'   => $table_name,
		);
	}

	/**
	 * Test: Multisite Support
	 */
	public static function test_multisite_support(): array {
		$is_multisite = is_multisite();

		if ( ! $is_multisite ) {
			return array(
				'test'         => __( 'Multisite Support', 'mhm-rentiva' ),
				'status'       => 'skip',
				'message'      => esc_html__( '⏭️ Not a multisite installation', 'mhm-rentiva' ),
				'is_multisite' => false,
			);
		}

		// Check multisite functions
		$functions_exist = function_exists( 'switch_to_blog' ) &&
			function_exists( 'restore_current_blog' ) &&
			function_exists( 'get_current_blog_id' );

		return array(
			'test'            => __( 'Multisite Support', 'mhm-rentiva' ),
			'status'          => $functions_exist ? 'pass' : 'fail',
			'message'         => $functions_exist ?
				esc_html__( '✅ Multisite functions available', 'mhm-rentiva' ) :
				esc_html__( '❌ Multisite functions missing', 'mhm-rentiva' ),
			'is_multisite'    => true,
			'current_blog_id' => get_current_blog_id(),
		);
	}

	/**
	 * Render test results as HTML
	 */
	public static function render_results( array $results ): string {
		ob_start();
		?>
		<div class="mhm-test-results">
			<h2>🧪 Activation Test Results</h2>
			<table class="widefat">
				<thead>
					<tr>
						<th>Test</th>
						<th>Status</th>
						<th>Message</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $results as $result ) : ?>
						<tr class="test-<?php echo esc_attr( $result['status'] ); ?>">
							<td><strong><?php echo esc_html( $result['test'] ); ?></strong></td>
							<td>
								<?php
								$badge_class = match ( $result['status'] ) {
									'pass' => 'success',
									'fail' => 'error',
									'warning' => 'warning',
									'skip' => 'info',
									default => 'default'
								};
	?>
								<span class="badge badge-<?php echo esc_attr( $badge_class ); ?>">
									<?php echo esc_html( strtoupper( $result['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $result['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<style>
				.mhm-test-results {
					margin: 20px 0;
					padding: 20px;
					background: #fff;
					border: 1px solid #ccd0d4;
				}

				.mhm-test-results h2 {
					margin-top: 0;
				}

				.test-pass {
					background-color: #f0f8f0;
				}

				.test-fail {
					background-color: #fff0f0;
				}

				.test-warning {
					background-color: #fffbf0;
				}

				.test-skip {
					background-color: #f5f5f5;
				}

				.badge {
					padding: 3px 8px;
					border-radius: 3px;
					font-size: 11px;
					font-weight: bold;
				}

				.badge-success {
					background: #46b450;
					color: #fff;
				}

				.badge-error {
					background: #dc3232;
					color: #fff;
				}

				.badge-warning {
					background: #ffb900;
					color: #000;
				}

				.badge-info {
					background: #00a0d2;
					color: #fff;
				}
			</style>
		</div>
		<?php
		return ob_get_clean();
	}
}
