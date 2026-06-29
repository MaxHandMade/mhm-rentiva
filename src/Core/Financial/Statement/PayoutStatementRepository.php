<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\PostTypes\Payouts\PostType;

/**
 * Frozen snapshot storage for payout statements, kept as meta on the mhm_payout post.
 */
final class PayoutStatementRepository {

	public static function exists(int $payout_id): bool
	{
		return get_post_meta($payout_id, '_mhm_statement_number', true) !== '';
	}

	public static function save(int $payout_id, array $statement): void
	{
		if (self::exists($payout_id)) {
			return; // Immutable: never overwrite an existing statement.
		}

		update_post_meta($payout_id, '_mhm_statement_number', (string) $statement['number']);
		update_post_meta($payout_id, '_mhm_statement_generated_at', (string) $statement['generated_at']);
		update_post_meta($payout_id, '_mhm_statement_period_start', (string) $statement['period_start']);
		update_post_meta($payout_id, '_mhm_statement_period_end', (string) $statement['period_end']);
		update_post_meta($payout_id, '_mhm_statement_last_entry_id', (int) $statement['last_entry_id']);
		update_post_meta($payout_id, '_mhm_statement_gross', (float) $statement['gross']);
		update_post_meta($payout_id, '_mhm_statement_penalties', (float) $statement['penalties']);
		update_post_meta($payout_id, '_mhm_statement_commission_total', (float) ( $statement['commission_total'] ?? 0 ));
		update_post_meta($payout_id, '_mhm_statement_net_activity', (float) $statement['net_activity']);
		update_post_meta($payout_id, '_mhm_statement_paid', (float) $statement['paid']);
		update_post_meta($payout_id, '_mhm_statement_carried_balance', (float) $statement['carried_balance']);
		update_post_meta($payout_id, '_mhm_statement_currency', (string) $statement['currency']);
		update_post_meta($payout_id, '_mhm_statement_lines', wp_json_encode($statement['lines'], JSON_UNESCAPED_UNICODE));
		update_post_meta($payout_id, '_mhm_statement_vendor_snapshot', wp_json_encode($statement['vendor_snapshot'], JSON_UNESCAPED_UNICODE));
		if (get_post_meta($payout_id, '_mhm_statement_emailed_at', true) === '') {
			update_post_meta($payout_id, '_mhm_statement_emailed_at', '');
		}
	}

	public static function get(int $payout_id): ?array
	{
		if (! self::exists($payout_id)) {
			return null;
		}
		$lines = json_decode( (string) get_post_meta($payout_id, '_mhm_statement_lines', true), true);
		$snap  = json_decode( (string) get_post_meta($payout_id, '_mhm_statement_vendor_snapshot', true), true);

		return array(
			'payout_id'        => $payout_id,
			'vendor_id'        => (int) get_post_field('post_author', $payout_id),
			'number'           => (string) get_post_meta($payout_id, '_mhm_statement_number', true),
			'generated_at'     => (string) get_post_meta($payout_id, '_mhm_statement_generated_at', true),
			'currency'         => (string) get_post_meta($payout_id, '_mhm_statement_currency', true),
			'period_start'     => (string) get_post_meta($payout_id, '_mhm_statement_period_start', true),
			'period_end'       => (string) get_post_meta($payout_id, '_mhm_statement_period_end', true),
			'last_entry_id'    => (int) get_post_meta($payout_id, '_mhm_statement_last_entry_id', true),
			'lines'            => is_array($lines) ? $lines : array(),
			'gross'            => (float) get_post_meta($payout_id, '_mhm_statement_gross', true),
			'penalties'        => (float) get_post_meta($payout_id, '_mhm_statement_penalties', true),
			'commission_total' => (float) get_post_meta($payout_id, '_mhm_statement_commission_total', true),
			'net_activity'     => (float) get_post_meta($payout_id, '_mhm_statement_net_activity', true),
			'paid'             => (float) get_post_meta($payout_id, '_mhm_statement_paid', true),
			'carried_balance'  => (float) get_post_meta($payout_id, '_mhm_statement_carried_balance', true),
			'vendor_snapshot'  => is_array($snap) ? $snap : array(),
			'emailed_at'       => (string) get_post_meta($payout_id, '_mhm_statement_emailed_at', true),
		);
	}

	public static function previous_last_entry_id(int $vendor_id, int $exclude_payout_id): int
	{
		$ids = get_posts(array(
			'post_type'      => PostType::POST_TYPE,
			'author'         => $vendor_id,
			'post_status'    => array( 'publish', 'pending', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'exclude'        => array( $exclude_payout_id ),
			'meta_key'       => '_mhm_statement_last_entry_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		));

		$max = 0;
		foreach ($ids as $id) {
			$max = max($max, (int) get_post_meta( (int) $id, '_mhm_statement_last_entry_id', true));
		}
		return $max;
	}

	public static function mark_emailed(int $payout_id): void
	{
		update_post_meta($payout_id, '_mhm_statement_emailed_at', gmdate('Y-m-d H:i:s'));
	}
}
