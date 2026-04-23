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





use MHMRentiva\Admin\Addons\AddonManager;



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
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_admin_scripts' ));
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

		// Hide default WP Title & Add New button to replace with standardized header
		echo '<style>.wp-heading-inline, .page-title-action, .wp-header-end { display: none !important; }</style>';

		$renderer = new class() {
			use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

			public function render()
			{
				// Standardized Header
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
					)
				);

				// Developer Mode Banner
				$this->render_developer_mode_banner();
			}
		};

		echo '<div class="wrap">';
		$renderer->render();

		// Add-on limit notice for Lite users
		\MHMRentiva\Admin\Core\ProFeatureNotice::displayLimitNotice( 'addons' );

		echo '</div>';
	}

	/**
	 * Render admin notices.
	 */
	public static function admin_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect query params.
		$addon_limit_reached = isset( $_GET['addon_limit_reached'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['addon_limit_reached'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect query params.
		$addon_created = isset( $_GET['addon_created'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['addon_created'] ) ) : '';

		// Show license limit notice.
		if ( '1' === $addon_limit_reached ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p>' . esc_html(AddonManager::get_addon_limit_message()) . '</p>';
			echo '</div>';
		}

		// Show success message for addon creation.
		if ( '1' === $addon_created ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>' . esc_html__('Additional service created successfully.', 'mhm-rentiva') . '</p>';
			echo '</div>';
		}
	}


	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_admin_scripts(string $hook): void
	{
		// Only load on addon pages.
		if (false === strpos($hook, 'vehicle_addon')) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-addon-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/addon-admin.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-addon-admin',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-admin.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-addon-admin',
			'mhmAddonAdmin',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('mhm_addon_admin'),
				'strings'  => array(
					'confirm_delete'       => __('Are you sure you want to delete this additional service?', 'mhm-rentiva'),
					'confirm_bulk_enable'  => __('Are you sure you want to enable selected additional services?', 'mhm-rentiva'),
					'confirm_bulk_disable' => __('Are you sure you want to disable selected additional services?', 'mhm-rentiva'),
				),
			)
		);
	}
}
