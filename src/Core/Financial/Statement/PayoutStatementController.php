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
		add_action('admin_post_mhm_rentiva_view_statement', array( self::class, 'render_view' ));
		add_action('admin_post_nopriv_mhm_rentiva_view_statement', array( self::class, 'render_view' ));
	}

	public static function on_transition(string $new_status, string $old_status, \WP_Post $post): void
	{
		if ($post->post_type !== PostType::POST_TYPE) {
			return;
		}
		if ($new_status !== 'publish' || $old_status === 'publish') {
			return;
		}
		// NOTE: on the live admin approve path this runs synchronously INSIDE
		// AtomicPayoutService::approve()'s DB transaction (it fires from the wp_update_post
		// that publishes the payout). A throw here rolls the whole approval back — fail-closed:
		// no payout without a statement. Keep generate_for_payout() and everything it calls
		// resilient; do not let a non-critical sub-step (e.g. the vendor email) throw.
		PayoutStatementService::generate_for_payout( (int) $post->ID);
	}

	public static function can_view(int $payout_id, int $user_id): bool
	{
		if ($user_id <= 0) {
			return false;
		}
		if (user_can($user_id, 'manage_options')) {
			return true;
		}
		return (int) get_post_field('post_author', $payout_id) === $user_id;
	}

	public static function view_url(int $payout_id): string
	{
		return wp_nonce_url(
			admin_url('admin-post.php?action=mhm_rentiva_view_statement&payout=' . $payout_id),
			'mhm_rentiva_view_statement_' . $payout_id
		);
	}

	public static function render_view(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified on next line.
		$payout_id = isset($_GET['payout']) ? (int) $_GET['payout'] : 0;
		$nonce     = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

		if ($payout_id <= 0 || ! wp_verify_nonce($nonce, 'mhm_rentiva_view_statement_' . $payout_id)) {
			wp_die(esc_html__('Invalid or expired statement link.', 'mhm-rentiva'), '', array( 'response' => 403 ));
		}
		if (! self::can_view($payout_id, get_current_user_id())) {
			wp_die(esc_html__('You are not allowed to view this statement.', 'mhm-rentiva'), '', array( 'response' => 403 ));
		}

		$statement = PayoutStatementRepository::get($payout_id);
		if ($statement === null) {
			wp_die(esc_html__('No statement found for this payout.', 'mhm-rentiva'), '', array( 'response' => 404 ));
		}

		// Standalone printable page.
		header('Content-Type: text/html; charset=utf-8');
		echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( (string) $statement['number']) . '</title>';
		echo '<style>@media print{.mhm-statement__noprint{display:none!important;}}</style></head><body>';
		echo PayoutStatementRenderer::render($statement); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		echo '</body></html>';
		exit;
	}
}
