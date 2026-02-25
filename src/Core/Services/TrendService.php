<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services;

use MHMRentiva\Core\Services\Metrics\MetricCacheManager;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Generic trend engine for dashboard metrics.
 */
final class TrendService
{
	private const BOOKING_POST_TYPE = 'vehicle_booking';
	private const MESSAGE_POST_TYPE = 'mhm_message';

	/**
	 * Generic public API.
	 *
	 * @param string               $metric
	 * @param string               $context
	 * @param array<string, mixed> $args
	 * @return array{total:int,current:int,previous:int,trend:int,direction:string}
	 */
	public static function get_trend(string $metric, string $context = 'customer', array $args = array()): array
	{
		$metric = sanitize_key($metric);
		$context = sanitize_key($context);
		$subject = self::resolve_subject_key($metric, $args);

		$cached = MetricCacheManager::get($context, $metric, $subject);
		if (is_array($cached)) {
			return $cached;
		}

		$data = self::calculate_trend($metric, $context, $args);
		MetricCacheManager::set($context, $metric, $subject, $data);

		return $data;
	}

	/**
	 * Backward-compat wrapper.
	 *
	 * @return array{total:int,current:int,previous:int,trend:int,direction:string}
	 */
	public static function get_total_booking_trend_for_user(int $user_id): array
	{
		return self::get_trend('total_bookings', 'customer', array('user_id' => $user_id));
	}

	/**
	 * Backward-compat wrapper.
	 *
	 * @return array{total:int,current:int,previous:int,trend:int,direction:string}
	 */
	public static function get_upcoming_pickups_trend_for_user(int $user_id): array
	{
		return self::get_trend('upcoming_pickups', 'customer', array('user_id' => $user_id));
	}

	/**
	 * Backward-compat wrapper.
	 *
	 * @return array{total:int,current:int,previous:int,trend:int,direction:string}
	 */
	public static function get_unread_messages_trend_for_user(string $email): array
	{
		return self::get_trend('unread_messages', 'customer', array('email' => $email));
	}



	/**
	 * @param string               $metric
	 * @param string               $context
	 * @param array<string, mixed> $args
	 * @return array{total:int,current:int,previous:int,trend:int,direction:string}
	 */
	private static function calculate_trend(string $metric, string $context, array $args): array
	{
		$now = (int) current_time('timestamp');
		$current_period_start = (int) strtotime('-7 days', $now);
		$previous_period_start = (int) strtotime('-14 days', $now);

		$resolved = self::resolve_metric_totals($metric, $context, $args, $current_period_start, $now, $previous_period_start);
		if (! is_array($resolved)) {
			return self::empty_trend();
		}

		$total = (int) ($resolved['total'] ?? 0);
		$current = (int) ($resolved['current'] ?? 0);
		$previous = (int) ($resolved['previous'] ?? 0);

		$math_result = TrendMath::calculate_trend_from_totals($current, $previous);

		return array(
			'total'     => $total,
			'current'   => $math_result['current'],
			'previous'  => $math_result['previous'],
			'trend'     => $math_result['trend'],
			'direction' => $math_result['direction'],
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, int>|null
	 */
	private static function resolve_metric_totals(string $metric, string $context, array $args, int $current_start, int $now, int $previous_start): ?array
	{
		$handler = MetricRegistry::get($metric);
		if (! $handler) {
			return array('total' => 0, 'current' => 0, 'previous' => 0);
		}

		return $handler->resolve($context, $args, $current_start, $now, $previous_start);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function resolve_customer_user_id_from_args(array $args): int
	{
		if (isset($args['user_id'])) {
			return (int) $args['user_id'];
		}

		if (is_user_logged_in()) {
			return (int) get_current_user_id();
		}

		return 0;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function resolve_customer_email_from_args(array $args): string
	{
		if (isset($args['email'])) {
			return sanitize_email((string) $args['email']);
		}

		$user = wp_get_current_user();
		if ($user instanceof \WP_User) {
			return sanitize_email((string) $user->user_email);
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function resolve_subject_key(string $metric, array $args): string
	{
		$handler = MetricRegistry::get($metric);
		if (! $handler) {
			return 'global';
		}

		return $handler->subjectKey($args);
	}

	/**
	 * @return array{total:int,current:int,previous:int,trend:int,direction:string}
	 */
	private static function empty_trend(): array
	{
		return array(
			'total'     => 0,
			'current'   => 0,
			'previous'  => 0,
			'trend'     => 0,
			'direction' => 'neutral',
		);
	}

	/**
	 * Count bookings for user in a period.
	 */
	public static function count_user_bookings_between(int $user_id, int $start, int $end): int
	{
		$query = new \WP_Query(
			array(
				'post_type'      => self::BOOKING_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_mhm_customer_user_id',
						'value' => $user_id,
					),
				),
				'date_query'     => array(
					array(
						'after'     => gmdate('Y-m-d H:i:s', $start),
						'before'    => gmdate('Y-m-d H:i:s', $end),
						'inclusive' => true,
					),
				),
			)
		);

		return (int) $query->post_count;
	}

	/**
	 * Count total bookings for user.
	 */
	public static function count_user_bookings_total(int $user_id): int
	{
		$query = new \WP_Query(
			array(
				'post_type'      => self::BOOKING_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_mhm_customer_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		return (int) $query->post_count;
	}

	/**
	 * Count upcoming pickup bookings for user in DATE range.
	 */
	public static function count_user_pickups_between(int $user_id, string $start_date, string $end_date): int
	{
		$excluded_statuses = array('cancelled', 'completed', 'refunded');
		$query = new \WP_Query(
			array(
				'post_type'      => self::BOOKING_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_mhm_customer_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		$count = 0;
		$booking_ids = is_array($query->posts) ? $query->posts : array();

		foreach ($booking_ids as $booking_id) {
			$booking_id = (int) $booking_id;
			if ($booking_id <= 0) {
				continue;
			}

			$status = sanitize_key((string) get_post_meta($booking_id, '_mhm_status', true));
			if (in_array($status, $excluded_statuses, true)) {
				continue;
			}

			$pickup_date = (string) get_post_meta($booking_id, '_mhm_pickup_date', true);
			if ($pickup_date === '') {
				$pickup_date = (string) get_post_meta($booking_id, '_booking_pickup_date', true);
			}
			if ($pickup_date === '') {
				$pickup_date = (string) get_post_meta($booking_id, '_mhm_start_date', true);
			}

			$pickup_time = (string) get_post_meta($booking_id, '_mhm_start_time', true);
			if ($pickup_time === '') {
				$pickup_time = (string) get_post_meta($booking_id, '_mhm_pickup_time', true);
			}
			if ($pickup_time === '') {
				$pickup_time = (string) get_post_meta($booking_id, '_booking_pickup_time', true);
			}

			if ($pickup_date === '') {
				continue;
			}

			$pickup_timestamp = strtotime(trim($pickup_date . ' ' . $pickup_time));
			if (! is_int($pickup_timestamp) || $pickup_timestamp <= 0) {
				continue;
			}

			$pickup_day = wp_date('Y-m-d', $pickup_timestamp);
			if ($pickup_day >= $start_date && $pickup_day <= $end_date) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Count unread messages in period.
	 */
	public static function count_unread_messages_between(string $email, int $start, int $end): int
	{
		$query = new \WP_Query(
			array(
				'post_type'      => self::MESSAGE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_mhm_customer_email',
						'value' => $email,
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_mhm_is_read',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_mhm_is_read',
							'value'   => '1',
							'compare' => '!=',
						),
					),
				),
				'date_query'     => array(
					array(
						'after'     => gmdate('Y-m-d H:i:s', $start),
						'before'    => gmdate('Y-m-d H:i:s', $end),
						'inclusive' => true,
					),
				),
			)
		);

		return (int) $query->post_count;
	}

	/**
	 * Count total unread messages.
	 */
	public static function count_unread_messages_total(string $email): int
	{
		$query = new \WP_Query(
			array(
				'post_type'      => self::MESSAGE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_mhm_customer_email',
						'value' => $email,
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => '_mhm_is_read',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_mhm_is_read',
							'value'   => '1',
							'compare' => '!=',
						),
					),
				),
			)
		);

		return (int) $query->post_count;
	}
}
