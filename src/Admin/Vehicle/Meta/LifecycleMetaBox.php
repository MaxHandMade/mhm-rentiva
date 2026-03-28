<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;

/**
 * Read-only meta box displaying vehicle lifecycle status and timeline on the edit screen.
 *
 * @since 4.24.0
 */
final class LifecycleMetaBox
{
	/**
	 * Register the meta box.
	 */
	public static function register(): void
	{
		add_action('add_meta_boxes_vehicle', array(self::class, 'add_meta_box'));
	}

	/**
	 * Add the meta box.
	 */
	public static function add_meta_box(): void
	{
		add_meta_box(
			'mhm_vehicle_lifecycle',
			__('Lifecycle Status', 'mhm-rentiva'),
			array(self::class, 'render'),
			'vehicle',
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render(\WP_Post $post): void
	{
		$vehicle_id = $post->ID;
		$status     = VehicleLifecycleStatus::get($vehicle_id);
		$label      = VehicleLifecycleStatus::get_label($status);
		$color      = VehicleLifecycleStatus::get_color($status);

		echo '<div style="margin:-6px -12px -12px;padding:12px;">';

		// Status badge.
		echo '<p style="margin-bottom:8px;">';
		echo '<strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong> ';
		echo '<span style="display:inline-block;padding:3px 10px;border-radius:4px;background:' . esc_attr($color) . '20;color:' . esc_attr($color) . ';font-weight:700;">';
		echo esc_html($label);
		echo '</span>';
		echo '</p>';

		// Listing timeline.
		$started = get_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, true);
		$expires = get_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);

		if ($started) {
			echo '<p style="margin:4px 0;font-size:12px;color:#666;">';
			echo '<strong>' . esc_html__('Started:', 'mhm-rentiva') . '</strong> ';
			echo esc_html(wp_date(get_option('date_format'), strtotime($started)));
			echo '</p>';
		}

		if ($expires) {
			$days_left = max(0, (int) ceil((strtotime($expires) - time()) / DAY_IN_SECONDS));
			echo '<p style="margin:4px 0;font-size:12px;color:#666;">';
			echo '<strong>' . esc_html__('Expires:', 'mhm-rentiva') . '</strong> ';
			echo esc_html(wp_date(get_option('date_format'), strtotime($expires)));

			if ($status === VehicleLifecycleStatus::ACTIVE) {
				echo ' <span style="color:' . esc_attr($days_left <= 10 ? '#dc3545' : '#666') . ';">';
				/* translators: %d: days remaining */
				echo esc_html(sprintf(__('(%d days)', 'mhm-rentiva'), $days_left));
				echo '</span>';
			}
			echo '</p>';
		}

		// Paused at.
		$paused_at = get_post_meta($vehicle_id, MetaKeys::VEHICLE_PAUSED_AT, true);
		if ($paused_at && $status === VehicleLifecycleStatus::PAUSED) {
			echo '<p style="margin:4px 0;font-size:12px;color:#666;">';
			echo '<strong>' . esc_html__('Paused:', 'mhm-rentiva') . '</strong> ';
			echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($paused_at)));
			echo '</p>';
		}

		// Cooldown (withdrawn).
		$cooldown = get_post_meta($vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, true);
		if ($cooldown && $status === VehicleLifecycleStatus::WITHDRAWN) {
			echo '<p style="margin:4px 0;font-size:12px;color:#666;">';
			echo '<strong>' . esc_html__('Cooldown ends:', 'mhm-rentiva') . '</strong> ';
			echo esc_html(wp_date(get_option('date_format'), strtotime($cooldown)));
			echo '</p>';
		}

		// Renewal count.
		$renewals = (int) get_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_RENEWAL_CNT, true);
		if ($renewals > 0) {
			echo '<p style="margin:4px 0;font-size:12px;color:#666;">';
			echo '<strong>' . esc_html__('Renewals:', 'mhm-rentiva') . '</strong> ';
			echo esc_html((string) $renewals);
			echo '</p>';
		}

		// Vendor reliability score.
		$vendor_id = (int) $post->post_author;
		if ($vendor_id > 0) {
			$score = ReliabilityScoreCalculator::get($vendor_id);
			$score_label = ReliabilityScoreCalculator::get_label($score);
			$score_color = ReliabilityScoreCalculator::get_color($score);

			echo '<hr style="margin:8px 0;border:0;border-top:1px solid #ddd;">';
			echo '<p style="margin:4px 0;font-size:12px;">';
			echo '<strong>' . esc_html__('Vendor Score:', 'mhm-rentiva') . '</strong> ';
			echo '<span style="color:' . esc_attr($score_color) . ';font-weight:700;">';
			echo esc_html($score . '/100 — ' . $score_label);
			echo '</span>';
			echo '</p>';
		}

		echo '</div>';
	}
}
