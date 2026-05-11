<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Payouts;

if (! defined('ABSPATH')) {
	exit;
}



/**
 * Admin list page for mhm_payout CPT using PayoutListTable.
 *
 * Handles bulk approve processing, flash notices, and CSV export link.
 *
 * @since 4.41.0
 */
final class PayoutAdminPage {

	/**
	 * Renders the full admin page: bulk processing → notices → list table → CSV button.
	 */
	public static function render(): void
	{
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom governance capability registered via DatabaseMigrator::register_governance_capabilities().
		if (! current_user_can('mhm_rentiva_approve_payout')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'mhm-rentiva'));
		}

		// Detect bulk action from either dropdown (top or bottom).
		// Nonce + capability are validated inside process_bulk_approve().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';
		if ( '-1' === $action ) {
			$action = isset($_POST['action2']) ? sanitize_key($_POST['action2']) : '';
		}
		// phpcs:enable

		$result = null;
		if ( PayoutListTable::BULK_ACTION_APPROVE === $action ) {
			$result = PayoutListTable::process_bulk_approve();
		}

		$table = new PayoutListTable();
		$table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__('Payout Requests', 'mhm-rentiva') . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( PayoutCsvExporter::get_export_url() ) . '">'
			. esc_html__('Export CSV', 'mhm-rentiva') . '</a>';
		echo '<hr class="wp-header-end">';

		if ( $result !== null ) {
			if ( ! empty($result['errors']) ) {
				foreach ( $result['errors'] as $err ) {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html($err)
					);
				}
			}

			if ( $result['approved'] > 0 ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: number of approved payout requests */
							_n('%d payout approved.', '%d payouts approved.', $result['approved'], 'mhm-rentiva'),
							$result['approved']
						)
					)
				);
			}

			if ( 0 === $result['approved'] && empty($result['errors']) && $result['skipped'] > 0 ) {
				echo '<div class="notice notice-warning"><p>'
					. esc_html__('No pending payouts were selected for approval.', 'mhm-rentiva')
					. '</p></div>';
			}
		}

		echo '<form id="mhm-payouts-filter" method="post">';
		$table->display();
		echo '</form>';
		echo '</div>';
	}
}
