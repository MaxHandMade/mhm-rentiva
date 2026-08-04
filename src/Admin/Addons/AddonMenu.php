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
		// The former `admin_notices` callback printed one success notice gated on
		// `?addon_created=1`. Nothing in this plugin has ever redirected with
		// that parameter, so the notice could not fire; callback and read are
		// both gone rather than kept behind a guard.
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
		if ('edit.php' !== $pagenow || 'mhmrentiva_addon' !== $post_type) {
			return;
		}

		$renderer = new class() {
			use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

			public function render()
			{
				// Standardized Header — skip the trailing wp-header-end marker
				// because WordPress core already emits one for the built-in
				// post-type list H1 above us. Two markers make WP's notice
				// relocator clone each admin_notices callback registered on
				// this screen (jQuery `.before()` on multiple targets). See
				// the `$skip_wp_header_end` docblock in AdminHelperTrait.
				$this->render_admin_header(
					esc_html__('Additional Services', 'mhm-rentiva'),
					array(
						array(
							'text'  => esc_html__('Add New', 'mhm-rentiva'),
							'url'   => admin_url('post-new.php?post_type=mhmrentiva_addon'),
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
		echo '</div>';
	}

	/**
	 * Enqueue the CSS that hides the default WP Title & Add New button on
	 * the addon list screen, replaced with the standardized header.
	 */
	public static function enqueue_page_title_style(): void
	{
		global $pagenow, $post_type;

		// Only show on addon list page.
		if ('edit.php' !== $pagenow || 'mhmrentiva_addon' !== $post_type) {
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
