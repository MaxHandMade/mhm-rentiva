<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\PostTypes\Payouts\PostType;

/**
 * Wires payout-statement generation to the payout-approval transition.
 * Both approve paths (AtomicPayoutService::approve and PayoutService::approve_payout)
 * call wp_update_post(post_status => 'publish'), so transition_post_status catches both.
 */
final class PayoutStatementController {

	public static function register(): void
	{
		add_action('transition_post_status', array( self::class, 'on_transition' ), 20, 3);
	}

	public static function on_transition(string $new_status, string $old_status, \WP_Post $post): void
	{
		if ($post->post_type !== PostType::POST_TYPE) {
			return;
		}
		if ($new_status !== 'publish' || $old_status === 'publish') {
			return;
		}
		PayoutStatementService::generate_for_payout( (int) $post->ID);
	}
}
