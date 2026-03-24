<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Templates {

	// Find template and include it. If $return=true, output is buffered and returns string.
	public static function render( string $relative, array $vars = array(), bool $return = false ) {
		$file = self::locate( $relative );
		if ( ! $file || ! is_file( $file ) ) {

			// Not found: empty string or warning
			if ( $return ) {
				return '';
			}
			return;
		}

		if ( $return ) {
			ob_start();
			self::include_template_with_vars( $file, $vars );
			$output = ob_get_clean();
			// Remove all whitespace characters including newlines
			$output = preg_replace( '/\s+/', ' ', $output );
			$output = trim( $output );
			return (string) $output;
		}

		self::include_template_with_vars( $file, $vars );
	}

	// Find file in theme > parent theme > plugin order
	public static function locate( string $relative ): ?string {
		$relative   = ltrim( $relative, '/\\' );
		$candidates = array();

		// 1) Child theme
		$child_theme = trailingslashit( get_stylesheet_directory() ) . 'mhm-rentiva/' . $relative;
		if ( ! str_ends_with( $child_theme, '.php' ) ) {
			$child_theme .= '.php';
		}
		$candidates[] = $child_theme;

		// 2) Parent theme
		if ( get_stylesheet_directory() !== get_template_directory() ) {
			$parent_theme = trailingslashit( get_template_directory() ) . 'mhm-rentiva/' . $relative;
			if ( ! str_ends_with( $parent_theme, '.php' ) ) {
				$parent_theme .= '.php';
			}
			$candidates[] = $parent_theme;
		}
		// 3) Plugin templates - correct path
		$plugin_templates = MHM_RENTIVA_PLUGIN_PATH . 'templates/' . $relative;

		// Debug logs disabled (for performance)
		// Add .php extension if not present
		if ( ! str_ends_with( $plugin_templates, '.php' ) ) {
			$plugin_templates .= '.php';
		}
		$candidates[] = $plugin_templates;

		// Debug logs removed

		// Alternative paths can be added via filter
		$candidates = apply_filters( 'mhm_rentiva/template_candidates', $candidates, $relative );

		foreach ( $candidates as $path ) {
			if ( is_file( $path ) ) {
				$located = (string) $path;
				return apply_filters( 'mhm_rentiva/locate_template', $located, $relative );
			}
		}

		return null;
	}

	// Price HTML helper method (usable in templates)
	public static function price_html( int $post_id ): string {
		$meta_key = apply_filters( 'mhm_rentiva/vehicle/price_meta_key', '_mhm_rentiva_price_per_day' );
		$raw      = get_post_meta( $post_id, $meta_key, true );
		if ( $raw === '' || ! is_numeric( $raw ) ) {
			return '';
		}
		$price     = (float) $raw;
		$currency  = apply_filters( 'mhm_rentiva/currency_code', 'TRY' );
		$formatted = apply_filters( 'mhm_rentiva/format_price', number_format_i18n( $price, 0 ) . ' ' . $currency, $price, $currency, $post_id );
		return sprintf(
			'<span class="amount">%s</span> <span class="unit">%s</span>',
			esc_html( (string) $formatted ),
			esc_html__( '/day', 'mhm-rentiva' )
		);
	}

	private static function plugin_file(): string {
		// Use MHM_RENTIVA_PLUGIN_FILE constant (more reliable)
		if ( defined( 'MHM_RENTIVA_PLUGIN_FILE' ) ) {
			return MHM_RENTIVA_PLUGIN_FILE;
		}

		// Fallback: Reach plugin root from this class directory
		// .../src/Admin/Core/Utilities/Templates.php -> plugin root: ../../../../
		$plugin_file = dirname( __DIR__, 4 ) . '/mhm-rentiva.php';

		// Debug logs disabled (for performance)

		return $plugin_file;
	}

	// Keep old methods for backward compatibility
	public static function load( string $template_name, array $args = array(), bool $echo = true ): ?string {
		return self::render( $template_name . '.php', $args, ! $echo );
	}

	public static function template_exists( string $template_name ): bool {
		return self::locate( $template_name . '.php' ) !== null;
	}

	public static function get_template_path( string $template_name ): ?string {
		return self::locate( $template_name . '.php' );
	}

	public static function get_available_templates(): array {
		$templates            = array();
		$plugin_templates_dir = trailingslashit( plugin_dir_path( self::plugin_file() ) ) . 'templates/';

		if ( is_dir( $plugin_templates_dir ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_templates_dir )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$relative_path = str_replace( $plugin_templates_dir, '', $file->getPathname() );
					$template_name = str_replace( '.php', '', $relative_path );
					$templates[]   = $template_name;
				}
			}
		}

		return $templates;
	}

	public static function get_override_paths(): array {
		return array(
			'child_theme'    => trailingslashit( get_stylesheet_directory() ) . 'mhm-rentiva/',
			'parent_theme'   => trailingslashit( get_template_directory() ) . 'mhm-rentiva/',
			'plugin_default' => trailingslashit( plugin_dir_path( self::plugin_file() ) ) . 'templates/',
		);
	}

	/**
	 * Include template while mapping only valid variable names.
	 *
	 * @param string $file Template file path.
	 * @param array  $vars Template vars.
	 */
	private static function include_template_with_vars( string $file, array $vars ): void {
		( static function () use ( $file, $vars ): void {
			foreach ( $vars as $key => $value ) {
				if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
					continue;
				}
				${$key} = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			}
			include $file;
		} )();
	}
}
