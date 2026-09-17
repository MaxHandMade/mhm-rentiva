<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\ListTable\ListScreenLayout;


/**
 * Registers the live enhancements for WordPress's native add-on CPT list.
 */
final class AddonListTable {

	/**
	 * Register list-screen assets and statistics.
	 */
	public static function register(): void {
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));
		// Moved off `admin_notices` (fires above `edit.php`'s `.wrap`, so the
		// KPI band painted at the top of the stream and jQuery dragged it into
		// place at DOMContentLoaded -- measured jump `.mhm-stats-grid` y=166 →
		// y=112 on every load) onto ListScreenLayout's header slot, the same
		// server-side seam the Vehicles and Bookings screens use. Default
		// priority (10) puts it below AddonMenu's page-title block (priority
		// 5), matching the order the two had when both printed from
		// `admin_notices`.
		add_action(ListScreenLayout::HEADER_ACTION, array( self::class, 'add_addon_stats_cards' ));
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_scripts(string $hook): void {
		global $post_type;

		if ('edit.php' !== $hook || 'mhmrentiva_addon' !== $post_type) {
			return;
		}

		// KPI strip markup/styling now comes from the ui-core kit.
		\MHMRentiva\Admin\Core\AssetManager::enqueue_kit( 'admin' );

		wp_enqueue_style(
			'mhm-rentiva-shared-admin',
			MHMRENTIVA_PLUGIN_URL . 'src-react/shared/admin.css',
			array(),
			MHMRENTIVA_VERSION
		);

		wp_enqueue_style(
			'mhm-rentiva-addon-list',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/addon-list.css',
			array(),
			MHMRENTIVA_VERSION
		);

		wp_enqueue_script(
			'mhm-rentiva-addon-list',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/addon-list.js',
			array( 'jquery' ),
			MHMRENTIVA_VERSION,
			true
		);

		wp_localize_script(
			'mhm-rentiva-addon-list',
			'mhmrentiva_addon_list_vars',
			array(
				'ajax_url'          => admin_url('admin-ajax.php'),
				'nonce'             => wp_create_nonce('mhmrentiva_addon_list_nonce'),
				'no_items_selected' => __('No items selected.', 'mhm-rentiva'),
				'items_selected'    => __('items selected', 'mhm-rentiva'),
				'confirm_enable'    => __('Are you sure you want to enable selected additional services?', 'mhm-rentiva'),
				'confirm_disable'   => __('Are you sure you want to disable selected additional services?', 'mhm-rentiva'),
				'confirm_delete'    => __('Are you sure you want to delete selected additional services? This action cannot be undone.', 'mhm-rentiva'),
				'processing'        => __('Processing...', 'mhm-rentiva'),
				'error_occurred'    => __('An error occurred. Please try again.', 'mhm-rentiva'),
				'auto_refresh'      => false,
				'strings'           => array(
					'invalidPrice'     => __('Invalid price value!', 'mhm-rentiva'),
					'priceUpdateError' => __('Error updating price', 'mhm-rentiva'),
					'unknownError'     => __('Unknown error', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Add statistics cards to the add-on list screen.
	 */
	public static function add_addon_stats_cards(): void {
		global $post_type, $pagenow;

		if ('edit.php' !== $pagenow || 'mhmrentiva_addon' !== $post_type) {
			return;
		}

		$stats = self::get_addon_stats();

		$cards = array(
			array(
				'label' => __( 'Total Additional Services', 'mhm-rentiva' ),
				'value' => (string) $stats['total_addons'],
				'icon'  => 'plus-alt',
				'sub'   => __( 'All services', 'mhm-rentiva' ),
			),
			array(
				'label' => __( 'Active Services', 'mhm-rentiva' ),
				'value' => (string) $stats['active_addons'],
				'icon'  => 'yes-alt',
				'sub'   => sprintf(
					/* translators: %s: percentage of add-ons that are active. */
					__( '%s%% active', 'mhm-rentiva' ),
					$stats['active_percentage']
				),
			),
			array(
				'label' => __( 'Average Price', 'mhm-rentiva' ),
				// Already formatted by AddonStats; not re-formatted here.
				'value' => $stats['avg_price'],
				'icon'  => 'money-alt',
				'sub'   => __( 'All services', 'mhm-rentiva' ),
			),
			array(
				'label' => __( 'Total Value', 'mhm-rentiva' ),
				// Already formatted by AddonStats; not re-formatted here.
				'value' => $stats['total_value'],
				'icon'  => 'chart-line',
				'sub'   => __( 'All prices', 'mhm-rentiva' ),
			),
		);

		echo '<div class="mhmui-admin">';
		echo function_exists( 'mhmuicore_stats_grid_html' )
			? mhmuicore_stats_grid_html( $cards, 4 )
			: '';
		echo '</div>';
	}

	/**
	 * Get add-on statistics.
	 *
	 * Delegates to AddonStats, which owns these four figures. They are also
	 * painted by the plugin's own add-ons screen; keeping the arithmetic here as
	 * well would be two definitions of the same numbers, one per screen, free to
	 * drift apart.
	 *
	 * @return array{total_addons:int,active_addons:int,active_percentage:float|int,avg_price:string,total_value:string}
	 */
	private static function get_addon_stats(): array {
		return AddonStats::get();
	}
}
