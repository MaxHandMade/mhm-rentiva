<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ CODE QUALITY IMPROVEMENT - Meta Query Helper
 *
 * Manages repeated meta query patterns centrally
 */
final class MetaQueryHelper {

	/**
	 * Create LEFT JOIN for meta field
	 */
	public static function left_join_meta( string $alias, string $meta_key ): string {
		global $wpdb;
		$safe_meta_key = $wpdb->prepare( '%s', $meta_key );
		return "LEFT JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = {$safe_meta_key}";
	}

	/**
	 * Create INNER JOIN for meta field
	 */
	public static function inner_join_meta( string $alias, string $meta_key ): string {
		global $wpdb;
		$safe_meta_key = $wpdb->prepare( '%s', $meta_key );
		return "INNER JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = {$safe_meta_key}";
	}

	/**
	 * Create JOINs for multiple meta fields
	 */
	public static function build_meta_joins( array $meta_fields, string $join_type = 'LEFT' ): array {
		$joins   = array();
		$selects = array();

		foreach ( $meta_fields as $alias => $config ) {
			$meta_key      = $config['meta_key'];
			$select_alias  = $config['select_alias'] ?? $alias;
			$default_value = $config['default_value'] ?? '';

			if ( $join_type === 'INNER' ) {
				$joins[] = self::inner_join_meta( $alias, $meta_key );
			} else {
				$joins[] = self::left_join_meta( $alias, $meta_key );
			}

			if ( $default_value !== '' ) {
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
	public static function get_message_meta_joins(): array {
		$meta_fields = array(
			'pm_customer_name'  => array(
				'meta_key'      => '_mhm_customer_name',
				'select_alias'  => 'customer_name',
				'default_value' => '',
			),
			'pm_customer_email' => array(
				'meta_key'      => '_mhm_customer_email',
				'select_alias'  => 'customer_email',
				'default_value' => '',
			),
			'pm_category'       => array(
				'meta_key'      => '_mhm_message_category',
				'select_alias'  => 'category',
				'default_value' => 'general',
			),
			'pm_status'         => array(
				'meta_key'      => '_mhm_message_status',
				'select_alias'  => 'status',
				'default_value' => 'pending',
			),
			'pm_thread'         => array(
				'meta_key'      => '_mhm_thread_id',
				'select_alias'  => 'thread_id',
				'default_value' => 'p.ID',
			),
			'pm_read'           => array(
				'meta_key'      => '_mhm_is_read',
				'select_alias'  => 'is_read',
				'default_value' => '0',
			),
			'pm_parent'         => array(
				'meta_key'      => '_mhm_parent_message_id',
				'select_alias'  => 'parent_message_id',
				'default_value' => '0',
			),
			'pm_priority'       => array(
				'meta_key'      => '_mhm_message_priority',
				'select_alias'  => 'priority',
				'default_value' => 'normal',
			),
		);

		return self::build_meta_joins( $meta_fields );
	}

	/**
	 * Standard JOINs for booking meta fields
	 */
	public static function get_booking_meta_joins(): array {
		$meta_fields = array(
			'email_meta' => array(
				'meta_key'      => 'mhm_rentiva_customer_email',
				'select_alias'  => 'customer_email',
				'default_value' => '',
			),
			'name_meta'  => array(
				'meta_key'      => 'mhm_rentiva_customer_name',
				'select_alias'  => 'customer_name',
				'default_value' => '',
			),
			'phone_meta' => array(
				'meta_key'      => 'mhm_rentiva_customer_phone',
				'select_alias'  => 'customer_phone',
				'default_value' => '',
			),
			'price_meta' => array(
				'meta_key'      => 'mhm_rentiva_total_price',
				'select_alias'  => 'total_price',
				'default_value' => '0',
			),
		);

		return self::build_meta_joins( $meta_fields );
	}

	/**
	 * Standard JOINs for vehicle meta fields
	 */
	public static function get_vehicle_meta_joins(): array {
		$meta_fields = array(
			'price_meta'    => array(
				'meta_key'      => '_mhm_rentiva_price_per_day',
				'select_alias'  => 'price_per_day',
				'default_value' => '0',
			),
			'featured_meta' => array(
				'meta_key'      => '_mhm_rentiva_featured',
				'select_alias'  => 'featured',
				'default_value' => '0',
			),
		);

		return self::build_meta_joins( $meta_fields );
	}

	/**
	 * Create WHERE clause for meta query
	 */
	public static function build_meta_where( string $alias, string $value, string $operator = '=' ): string {
		global $wpdb;
		return $wpdb->prepare( "{$alias}.meta_value {$operator} %s", $value );
	}

	/**
	 * Create IN clause for meta query
	 */
	public static function build_meta_in( string $alias, array $values ): string {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
		return $wpdb->prepare( "{$alias}.meta_value IN ({$placeholders})", $values );
	}
}
