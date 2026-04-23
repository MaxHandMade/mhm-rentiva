<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Adds a "Reliability" column to the WordPress Users list table
 * showing vendor reliability scores.
 *
 * @since 4.24.0
 */
final class VendorReliabilityColumn {

	/**
	 * Register the column hooks.
	 */
	public static function register(): void
	{
		add_filter('manage_users_columns', array( self::class, 'add_column' ));
		add_filter('manage_users_custom_column', array( self::class, 'render_column' ), 10, 3);
		add_filter('manage_users_sortable_columns', array( self::class, 'sortable_column' ));
		add_action('pre_get_users', array( self::class, 'sort_by_score' ));
	}

	/**
	 * Add the column header.
	 */
	public static function add_column(array $columns): array
	{
		$columns['mhm_reliability'] = __('Reliability', 'mhm-rentiva');
		return $columns;
	}

	/**
	 * Render the column value.
	 *
	 * @param string $output      Custom column output.
	 * @param string $column_name Column identifier.
	 * @param int    $user_id     User ID.
	 * @return string Column HTML.
	 */
	public static function render_column(string $output, string $column_name, int $user_id): string
	{
		if ($column_name !== 'mhm_reliability') {
			return $output;
		}

		$user = get_userdata($user_id);
		if (! $user || ! in_array('rentiva_vendor', (array) $user->roles, true)) {
			return '—';
		}

		$score = ReliabilityScoreCalculator::get($user_id);
		$label = ReliabilityScoreCalculator::get_label($score);
		$color = ReliabilityScoreCalculator::get_color($score);

		return '<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:' . esc_attr($color) . '20;color:' . esc_attr($color) . ';font-weight:600;font-size:12px;">'
			. esc_html($score . ' — ' . $label)
			. '</span>';
	}

	/**
	 * Make the column sortable.
	 */
	public static function sortable_column(array $columns): array
	{
		$columns['mhm_reliability'] = 'mhm_reliability';
		return $columns;
	}

	/**
	 * Handle sorting by reliability score.
	 */
	public static function sort_by_score(\WP_User_Query $query): void
	{
		if (! is_admin()) {
			return;
		}

		$orderby = $query->get('orderby');
		if ($orderby === 'mhm_reliability') {
			$query->set('meta_key', \MHMRentiva\Admin\Core\MetaKeys::VENDOR_RELIABILITY_SCORE);
			$query->set('orderby', 'meta_value_num');
		}
	}
}
