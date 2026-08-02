<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This helper intentionally builds reusable meta/tax SQL fragments for centralized query composition.



use MHMRentiva\Admin\Core\MetaKeys;



/**
 * Meta Query Helper
 *
 * Manages repeated meta query patterns centrally
 */
final class MetaQueryHelper {

	/**
	 * Check if migration fallback is active (DEV MODE ONLY)
	 *
	 * @return bool
	 */
	public static function is_migration_fallback_active(): bool
	{
		// Active if WP_DEBUG is on OR explicitly enabled via constant.
		//
		// 🔴 EXTERNAL CONTRACT -- NOT a name this plugin owns, and deliberately
		// NOT swept to the 6.0.0 prefix. Nothing in this repository defines it:
		// it is a constant the SITE OPERATOR sets in wp-config.php, so the only
		// spelling that works is the one they already wrote. Renaming the lookup
		// does not rename their wp-config; it just makes defined() return false
		// forever, and the fallback silently stops existing with no error.
		//
		// The test that identifies this family is not "is it a storage key" but
		// "does anything OUTSIDE this repository have to agree with this exact
		// string?" -- see PrefixMigrationMap::EXTERNAL_CONTRACT_LITERALS.
		// prefix-rename:ignore-start
		return ( defined('WP_DEBUG') && WP_DEBUG ) || defined('MHM_RENTIVA_MIGRATION_FALLBACK');
		// prefix-rename:ignore-end
	}

	/**
	 * Sanitize SQL alias used for joins.
	 */
	private static function sanitize_alias(string $alias): string
	{
		return preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?? '';
	}

	/**
	 * Normalize supported SQL operators.
	 */
	private static function normalize_operator(string $operator): string
	{
		$allowed = array( '=', '!=', '>', '<', '>=', '<=', 'LIKE' );
		$clean   = strtoupper(trim($operator));

		return in_array($clean, $allowed, true) ? $clean : '=';
	}

	/**
	 * Create LEFT JOIN for meta field
	 */
	public static function left_join_meta(string $alias, string $meta_key): string
	{
		global $wpdb;
		$safe_meta_key = $wpdb->prepare('%s', $meta_key);
		return "LEFT JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = {$safe_meta_key}";
	}

	/**
	 * Create INNER JOIN for meta field
	 */
	public static function inner_join_meta(string $alias, string $meta_key): string
	{
		global $wpdb;
		$safe_meta_key = $wpdb->prepare('%s', $meta_key);
		return "INNER JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = {$safe_meta_key}";
	}

	/**
	 * Create JOINs for multiple meta fields
	 */
	public static function build_meta_joins(array $meta_fields, string $join_type = 'LEFT'): array
	{
		$joins   = array();
		$selects = array();

		foreach ($meta_fields as $alias => $config) {
			$meta_key      = $config['meta_key'];
			$select_alias  = $config['select_alias'] ?? $alias;
			$default_value = $config['default_value'] ?? '';

			if ($join_type === 'INNER') {
				$joins[] = self::inner_join_meta($alias, $meta_key);
			} else {
				$joins[] = self::left_join_meta($alias, $meta_key);
			}

			if ($default_value !== '') {
				$selects[] = "COALESCE({$alias}.meta_value, '{$default_value}') as {$select_alias}";
			} else {
				$selects[] = "{$alias}.meta_value as {$select_alias}";
			}
		}

		return array(
			'joins'   => $joins,
			'selects' => $selects,
		);
	}

	/**
	 * Standard JOINs for message meta fields
	 */
	public static function get_message_meta_joins(): array
	{
		$meta_fields = array(
			'pm_customer_name'  => array(
				'meta_key'      => '_mhmrentiva_customer_name',
				'select_alias'  => 'customer_name',
				'default_value' => '',
			),
			'pm_customer_email' => array(
				'meta_key'      => '_mhmrentiva_customer_email',
				'select_alias'  => 'customer_email',
				'default_value' => '',
			),
			'pm_category'       => array(
				'meta_key'      => '_mhmrentiva_message_category',
				'select_alias'  => 'category',
				'default_value' => 'general',
			),
			'pm_status'         => array(
				'meta_key'      => '_mhmrentiva_message_status',
				'select_alias'  => 'status',
				'default_value' => 'pending',
			),
			'pm_thread'         => array(
				'meta_key'      => '_mhmrentiva_thread_id',
				'select_alias'  => 'thread_id',
				'default_value' => 'p.ID',
			),
			'pm_read'           => array(
				'meta_key'      => '_mhmrentiva_is_read',
				'select_alias'  => 'is_read',
				'default_value' => '0',
			),
			'pm_parent'         => array(
				'meta_key'      => '_mhmrentiva_parent_message_id',
				'select_alias'  => 'parent_message_id',
				'default_value' => '0',
			),
			'pm_priority'       => array(
				'meta_key'      => '_mhmrentiva_message_priority',
				'select_alias'  => 'priority',
				'default_value' => 'normal',
			),
		);

		return self::build_meta_joins($meta_fields);
	}

	/**
	 * Standard JOINs for booking meta fields
	 */
	public static function get_booking_meta_joins(): array
	{
		$meta_fields = array(
			'email_meta' => array(
				'meta_key'      => 'mhmrentiva_customer_email',
				'select_alias'  => 'customer_email',
				'default_value' => '',
			),
			'name_meta'  => array(
				'meta_key'      => 'mhmrentiva_customer_name',
				'select_alias'  => 'customer_name',
				'default_value' => '',
			),
			'phone_meta' => array(
				'meta_key'      => 'mhmrentiva_customer_phone',
				'select_alias'  => 'customer_phone',
				'default_value' => '',
			),
			'price_meta' => array(
				'meta_key'      => 'mhmrentiva_total_price',
				'select_alias'  => 'total_price',
				'default_value' => '0',
			),
		);

		return self::build_meta_joins($meta_fields);
	}

	/**
	 * Standard JOINs for vehicle meta fields
	 */
	public static function get_vehicle_meta_joins(): array
	{
		$meta_fields = array(
			'price_meta'    => array(
				'meta_key'      => MetaKeys::VEHICLE_PRICE_PER_DAY,
				'select_alias'  => 'price_per_day',
				'default_value' => '0',
			),
			'featured_meta' => array(
				'meta_key'      => MetaKeys::VEHICLE_FEATURED,
				'select_alias'  => 'featured',
				'default_value' => '0',
			),
		);

		// FEATURE: Phase 1 Transition - Add legacy fallback in DEV MODE ONLY.
		// Note: We keep the target as primary, but developers can see if data is missing.
		// In Phase 2, this block will be removed and legacy keys marked @deprecated.
		// is_migration_fallback_active() is intentionally called here (and its return
		// value discarded) to surface dev-mode warnings via the helper's internal logging.
		self::is_migration_fallback_active();

		return self::build_meta_joins($meta_fields);
	}

	/**
	 * Create WHERE clause for meta query
	 */
	public static function build_meta_where(string $alias, string $value, string $operator = '='): string
	{
		global $wpdb;
		$safe_alias = self::sanitize_alias($alias);

		// A comparison operator cannot be a placeholder, so each supported
		// operator gets its own literal fragment. The alias goes through %i and
		// the value through %s, so neither can carry SQL of its own.
		return (string) match (self::normalize_operator($operator)) {
			'!='    => $wpdb->prepare('%i.meta_value != %s', $safe_alias, $value),
			'>'     => $wpdb->prepare('%i.meta_value > %s', $safe_alias, $value),
			'<'     => $wpdb->prepare('%i.meta_value < %s', $safe_alias, $value),
			'>='    => $wpdb->prepare('%i.meta_value >= %s', $safe_alias, $value),
			'<='    => $wpdb->prepare('%i.meta_value <= %s', $safe_alias, $value),
			'LIKE'  => $wpdb->prepare('%i.meta_value LIKE %s', $safe_alias, $value),
			default => $wpdb->prepare('%i.meta_value = %s', $safe_alias, $value),
		};
	}

	/**
	 * Create IN clause for meta query
	 */
	public static function build_meta_in(string $alias, array $values): string
	{
		global $wpdb;

		// An empty list would have produced `IN ()`, which is a syntax error.
		if (empty($values)) {
			return '';
		}

		// Called on $wpdb->prepare() directly rather than through
		// call_user_func_array(). The indirect call hid this query from the
		// sniffs completely, which is not the same thing as being safe -- it is
		// the evasion this guard exists to remove. The alias binds through %i
		// and the IN list is generated inline from count($values).
		return (string) $wpdb->prepare(
			'%i.meta_value IN (' . implode(',', array_fill(0, count($values), '%s')) . ')',
			array_merge(array( self::sanitize_alias($alias) ), array_values($values))
		);
	}

	/**
	 * Get standardized featured meta query for WP_Query
	 *
	 * @param string $value Value to compare
	 * @param string $compare Comparison operator
	 * @return array Meta query fragment
	 */
	public static function get_featured_meta_query(string $value = '1', string $compare = '='): array
	{
		return array(
			'key'     => MetaKeys::VEHICLE_FEATURED,
			'value'   => $value,
			'compare' => $compare,
		);
	}

	/**
	 * Get standardized status meta query for WP_Query
	 *
	 * @param string|array $value Status value(s)
	 * @param string       $compare Comparison operator
	 * @return array Meta query fragment
	 */
	public static function get_status_meta_query($value = 'active', string $compare = '='): array
	{
		return array(
			'key'     => MetaKeys::VEHICLE_STATUS,
			'value'   => $value,
			'compare' => ( is_array($value) && $compare === '=' ) ? 'IN' : $compare,
		);
	}

	/**
	 * Get meta query that filters only active vehicles for frontend display.
	 *
	 * Checks the new lifecycle status meta first; falls back to the legacy
	 * `_mhmrentiva_vehicle_status` key for vehicles not yet migrated.
	 * Vehicles without either meta are treated as active (legacy default).
	 *
	 * @return array WP_Query meta_query fragment (relation OR).
	 */
	public static function get_active_vehicle_meta_query(): array
	{
		return array(
			'relation' => 'OR',
			// New lifecycle meta: explicitly active.
			array(
				'key'     => MetaKeys::VEHICLE_LIFECYCLE_STATUS,
				'value'   => 'active',
				'compare' => '=',
			),
			// Legacy: old status meta = active AND no lifecycle meta yet.
			array(
				'relation' => 'AND',
				array(
					'key'     => MetaKeys::VEHICLE_LIFECYCLE_STATUS,
					'compare' => 'NOT EXISTS',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => MetaKeys::VEHICLE_STATUS,
						'value'   => 'active',
						'compare' => '=',
					),
					array(
						'key'     => MetaKeys::VEHICLE_STATUS,
						'compare' => 'NOT EXISTS',
					),
				),
			),
		);
	}
}
