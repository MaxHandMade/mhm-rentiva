<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Addon Menu Class.
 *
 * @package MHMRentiva\Admin\Addons
 */






/**
 * Handles admin menu and notices for additional services.
 */
final class AddonMenu {




	/**
	 * Register actions.
	 */
	public static function register(): void
	{
		add_action('admin_notices', array( self::class, 'admin_notices' ));
		add_action('admin_notices', array( self::class, 'add_addon_page_title' ));
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_page_title_style' ));
	}

	/**
	 * Deprecated menu page handler.
	 */
	public static function add_menu_pages(): void
	{
		// WordPress automatically adds post type menus.
	}

	/**
	 * Add custom title to addon page.
	 */
	public static function add_addon_page_title(): void
	{
		global $pagenow, $post_type;

		// Only show on addon list page.
		if ('edit.php' !== $pagenow || 'vehicle_addon' !== $post_type) {
			return;
		}

		$renderer = new class() {
			use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

			public function render()
			{
				// Standardized Header — skip the trailing wp-header-end marker
				// because WordPress core already emits one for the built-in
				// post-type list H1 above us. Two markers make WP's notice
				// relocator clone each admin notice (jQuery `.before()` on
				// multiple targets) and that's what produced the duplicated
				// "Rentiva Lite Limit" banner on this screen. See the
				// `$skip_wp_header_end` docblock in AdminHelperTrait.
				$this->render_admin_header(
					esc_html__('Additional Services', 'mhm-rentiva'),
					array(
						array(
							'text'  => esc_html__('Add New', 'mhm-rentiva'),
							'url'   => admin_url('post-new.php?post_type=vehicle_addon'),
							'class' => 'button button-primary',
							'icon'  => 'dashicons-plus',
						),
						array(
							'type' => 'documentation',
							'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
						),
					),
					true,
					'',
					true
				);

				// Developer Mode Banner
				$this->render_developer_mode_banner();
			}
		};

		echo '<div class="wrap">';
		$renderer->render();

		// NOTE (v4.27.2): the Lite limit notice used to be emitted here, but
		// that caused it to render twice — WordPress's core notice-relocator
		// JS inserts a copy right after the first `wp-header-end` marker while
		// the DOM still contains the original inside this nested `.wrap`.
		// The notice is now emitted by `render_addon_limit_notice()`, hooked
		// to `admin_notices` as its own callback. WordPress places it once and
		// cleans up the duplicate.

		echo '</div>';
	}

	/**
	 * Render admin notices.
	 */
	public static function admin_notices(): void
	{
		$addon_created = isset( $_GET['addon_created'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['addon_created'] ) ) : '';

		// Show success message for addon creation.
		if ( '1' === $addon_created ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>' . esc_html__('Additional service created successfully.', 'mhm-rentiva') . '</p>';
			echo '</div>';
		}
	}


	/**
	 * Enqueue the CSS that hides the default WP Title & Add New button on
	 * the addon list screen, replaced with the standardized header.
	 */
	public static function enqueue_page_title_style(): void
	{
		global $pagenow, $post_type;

		// Only show on addon list page.
		if ('edit.php' !== $pagenow || 'vehicle_addon' !== $post_type) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-hide-wp-chrome',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/hide-wp-chrome.css',
			array(),
			MHMRENTIVA_VERSION
		);
	}
}
