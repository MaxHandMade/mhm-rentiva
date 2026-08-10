<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate admin statistics are read directly and never mutate data.

/**
 * Registers the live enhancements for WordPress's native add-on CPT list.
 */
final class AddonListTable {

	/**
	 * Register list-screen assets and statistics.
	 */
	public static function register(): void {
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ));
		add_action('admin_notices', array( self::class, 'add_addon_stats_cards' ));
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

		wp_enqueue_style(
			'mhm-rentiva-stats-cards',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
			array(),
			MHMRENTIVA_VERSION
		);

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
		?>
		<div class="mhm-stats-grid">
			<div class="mhm-stat-card">
				<span class="dashicons dashicons-plus-alt"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('Total Additional Services', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html( (string) $stats['total_addons'] ); ?></p>
					<p class="mhm-stat-card__sub"><?php esc_html_e('All services', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card">
				<span class="dashicons dashicons-yes-alt"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('Active Services', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html( (string) $stats['active_addons'] ); ?></p>
					<p class="mhm-stat-card__sub"><?php echo esc_html( (string) $stats['active_percentage'] ); ?>% <?php esc_html_e('active', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card">
				<span class="dashicons dashicons-money-alt"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('Average Price', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html($stats['avg_price']); ?></p>
					<p class="mhm-stat-card__sub"><?php esc_html_e('All services', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card">
				<span class="dashicons dashicons-chart-line"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e('Total Value', 'mhm-rentiva'); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html($stats['total_value']); ?></p>
					<p class="mhm-stat-card__sub"><?php esc_html_e('All prices', 'mhm-rentiva'); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get add-on statistics.
	 *
	 * @return array{total_addons:int,active_addons:int,active_percentage:float|int,avg_price:string,total_value:string}
	 */
	private static function get_addon_stats(): array {
		global $wpdb;

		$total_addons = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'mhmrentiva_addon',
				'publish'
			)
		);

		$active_addons = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND pm.meta_key = 'mhmrentiva_addon_enabled' AND pm.meta_value = '1'",
				'mhmrentiva_addon',
				'publish'
			)
		);

		$avg_price = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(pm.meta_value AS DECIMAL(10,2)))
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND pm.meta_key = 'mhmrentiva_addon_price'",
				'mhmrentiva_addon',
				'publish'
			)
		);

		$total_value = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND pm.meta_key = 'mhmrentiva_addon_price'",
				'mhmrentiva_addon',
				'publish'
			)
		);

		$currency_code     = AddonManager::get_default_currency();
		$currency_symbol   = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol($currency_code);
		$active_percentage = $total_addons > 0 ? round(( $active_addons / $total_addons ) * 100) : 0;

		return array(
			'total_addons'      => $total_addons,
			'active_addons'     => $active_addons,
			'active_percentage' => $active_percentage,
			'avg_price'         => number_format($avg_price, 2, ',', '.') . ' ' . $currency_symbol,
			'total_value'       => number_format($total_value, 2, ',', '.') . ' ' . $currency_symbol,
		);
	}
}
