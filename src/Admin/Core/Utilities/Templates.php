<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Helpers\Html;

if (!defined('ABSPATH')) {
    exit;
}


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Templates {

	/**
	 * Run a shortcode and send its rendered markup to the page.
	 *
	 * This is the plugin's single point where shortcode output reaches the browser, and
	 * the only place where output is echoed without an escaping function around it. That
	 * is deliberate and unavoidable: do_shortcode() returns fully assembled HTML produced
	 * by the shortcode's own render path, where every dynamic value is already escaped at
	 * its own output site (esc_html/esc_attr/esc_url, or wp_kses() with the allowlist in
	 * Icons::allowed_svg() for generated SVG). Escaping the assembled document fragment a
	 * second time here would encode the tags and print raw markup to the visitor instead
	 * of rendering it -- and wp_kses() cannot be used either, because these shortcodes
	 * legitimately emit form controls and inline SVG that a post-level allowlist strips.
	 *
	 * Routing every caller through this one method keeps that trade-off in a single
	 * auditable place instead of scattering it across widget and template files.
	 *
	 * @param string $shortcode Full shortcode string, e.g. '[rentiva_vehicles_list columns="3"]'.
	 */
	public static function output_shortcode( string $shortcode ): void {
		echo do_shortcode( $shortcode );
	}

	/**
	 * Run a registered shortcode with an attribute array and return its markup.
	 *
	 * Elementor widgets used to serialize their settings into a "[tag a="b"]" string
	 * for do_shortcode(). Any value containing "]" or a quote broke out of that string
	 * and leaked raw attribute text onto the page, and esc_attr() encoded "&" twice.
	 * Calling the registered callback with the array directly removes the parse step.
	 *
	 * Deliberate differences from do_shortcode(): an unregistered tag returns '' instead
	 * of the literal shortcode text; the pre_do_shortcode_tag / do_shortcode_tag filters
	 * do not run; the temporary wp_get_attachment_image_context filter is not installed.
	 * The registered Rentiva callback (ShortcodeServiceProvider's wrapper) still applies
	 * its auth gate and wp_kses() allowlist.
	 *
	 * @param string $tag  Shortcode tag.
	 * @param array  $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode_atts( string $tag, array $atts ): string {
		global $shortcode_tags;

		$callback = $shortcode_tags[ $tag ] ?? null;
		if ( ! is_callable( $callback ) ) {
			return '';
		}

		return (string) call_user_func( $callback, $atts, null, $tag );
	}

	/**
	 * Run a registered shortcode with an attribute array and send its markup to the page.
	 *
	 * The sibling of output_shortcode() above, but escaped at the echo rather than left
	 * bare. output_shortcode()'s bare echo is accepted because do_shortcode() is on the
	 * sniffers' auto-escaped list; a direct callback call is not, and gate G-A
	 * (bin/check-shape-zero.php) allows zero EscapeOutput shapes even under
	 * --ignore-annotations, so a phpcs:ignore here would not pass.
	 *
	 * wp_kses() with the full render allowlist does not flatten the markup the way
	 * esc_html() would: it is the same allowlist and the same safe_style_css widening
	 * that ShortcodeServiceProvider::handle_shortcode_execution() already applied to
	 * Rentiva's own callbacks, so for them this second pass changes nothing, and for any
	 * other callback registered under the tag it is the only escaping on this path.
	 *
	 * @param string $tag  Shortcode tag.
	 * @param array  $atts Shortcode attributes.
	 */
	public static function output_shortcode_atts( string $tag, array $atts ): void {
		$html = self::render_shortcode_atts( $tag, $atts );

		add_filter( 'safe_style_css', array( Html::class, 'allow_inline_style_props' ) );
		try {
			echo wp_kses( $html, Html::allowed_markup() );
		} finally {
			remove_filter( 'safe_style_css', array( Html::class, 'allow_inline_style_props' ) );
		}
	}

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
		$plugin_templates = MHMRENTIVA_PLUGIN_PATH . 'templates/' . $relative;

		// Debug logs disabled (for performance)
		// Add .php extension if not present
		if ( ! str_ends_with( $plugin_templates, '.php' ) ) {
			$plugin_templates .= '.php';
		}
		$candidates[] = $plugin_templates;

		// Debug logs removed

		// Alternative paths can be added via filter
		$candidates = apply_filters( 'mhmrentiva_template_candidates', $candidates, $relative );

		foreach ( $candidates as $path ) {
			if ( is_file( $path ) ) {
				$located = (string) $path;
				return apply_filters( 'mhmrentiva_locate_template', $located, $relative );
			}
		}

		return null;
	}

	// price_html() was removed on 2026-08-24. It had zero callers in Lite AND in
	// the paid add-on -- the cross-tree grep the house requires before calling
	// anything dead -- and the three filters it carried
	// (mhmrentiva_vehicle_price_meta_key, mhmrentiva_currency_code,
	// mhmrentiva_format_price) fired nowhere else, so they were extension points
	// no integrator could ever reach: the method that would have run them never
	// ran. Vehicle cards format prices through CurrencyHelper::format_price()
	// and always did. Removing it changes no behaviour; the filters are named in
	// the changelog so anyone who hooked one learns they never fired.

	private static function plugin_file(): string {
		// Use MHMRENTIVA_PLUGIN_FILE constant (more reliable)
		if ( defined( 'MHMRENTIVA_PLUGIN_FILE' ) ) {
			return MHMRENTIVA_PLUGIN_FILE;
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
