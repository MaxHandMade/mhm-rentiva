<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="refund-customer-email">
	<div class="intro" style="margin-bottom: 20px;">
		<p>
		<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
			/* translators: %s: booking ID */
			printf( esc_html__( 'Your refund for booking #%s has been processed.', 'mhm-rentiva' ), esc_html( $data['booking']['id'] ?? '' ) );
		?>
		</p>
	</div>

	<h2 style="color: #555; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Refund Details', 'mhm-rentiva' ); ?></h2>

	<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777; width: 40%;"><strong><?php esc_html_e( 'Booking No:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;">#<?php echo esc_html( (string) ( $data['booking']['id'] ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Refund Amount:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html( $data['amount'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;">
			<?php
																							$status        = $data['status'] ?? 'pending';
																							$status_labels = array(
																								'pending' => esc_html__( 'Pending', 'mhm-rentiva' ),
																								'completed' => esc_html__( 'Completed', 'mhm-rentiva' ),
																								'processing' => esc_html__( 'Processing', 'mhm-rentiva' ),
																							);
																							echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) );
																							?>
																							</td>
		</tr>
		<?php if ( ! empty( $data['reason'] ) ) : ?>
			<tr>
				<td style="padding: 12px 0; color: #777;"><strong><?php esc_html_e( 'Reason:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 0; text-align: right;"><?php echo esc_html( (string) $data['reason'] ); ?></td>
			</tr>
		<?php endif; ?>
	</table>

	<?php
	// This is the exact claim Task 8 removed from the cancellation e-mail
	// because it is false for a manual refund (wc-refunds.md, fact 2). This
	// file's rendering path never runs replace_placeholders() -- it is
	// included with `$data = $ctx` and reads the context directly, the way
	// every other value on this page does ($data['booking']['id'],
	// $data['amount'], $data['status'], $data['reason']) -- so the fix
	// follows that same pattern rather than the {mode_text} token the DB/
	// EmailSettings body override understands. RefundNotifications::notify()
	// always sets 'mode_text' before rendering any 'refund_customer' template,
	// so no fallback default is needed for the one caller this file has.
	?>
	<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 6px; margin: 20px 0;">
		<p style="margin: 0;"><?php echo esc_html( $data['mode_text'] ?? '' ); ?></p>
	</div>

	<p style="color: #666; font-size: 14px;"><?php esc_html_e( 'If you have any questions about this refund, please contact us.', 'mhm-rentiva' ); ?></p>
</div>