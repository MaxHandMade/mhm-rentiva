<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- template-scope render variables.

$customer = $data['customer'] ?? array();
$booking  = $data['booking']  ?? array();
$vehicle  = $data['vehicle']  ?? array();
$vendor   = $data['vendor']   ?? array();
$panel    = $data['panel']    ?? array();
$change   = $data['status_change'] ?? array();

$panel_url = $panel['url'] ?? home_url( '/panel/' );
?>
<div class="booking-status-vendor-email">
	<div class="intro" style="margin-bottom: 20px;">
		<p style="font-size: 15px; line-height: 1.6; color: #333;">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: vendor name */
					__( 'Hello %s,', 'mhm-rentiva' ),
					$vendor['name'] ?? ''
				)
			);
			?>
		</p>
		<p><?php esc_html_e( 'A reservation on your vehicle has changed status.', 'mhm-rentiva' ); ?></p>
	</div>

	<h2 style="color: #555; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Status Update', 'mhm-rentiva' ); ?></h2>

	<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777; width: 40%;"><strong><?php esc_html_e( 'Booking No:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;">#<?php echo esc_html( (string) ( $booking['id'] ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Vehicle:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $vehicle['title'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Customer:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $customer['name'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Old Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right; color: #6c757d;"><?php echo esc_html( $change['old_status_label'] ?? ( $change['old_status'] ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; color: #777;"><strong><?php esc_html_e( 'New Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html( $change['new_status_label'] ?? ( $change['new_status'] ?? '' ) ); ?></td>
		</tr>
	</table>

	<?php if ( ! empty( $data['cancellation_reason'] ) ) : ?>
	<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #fff3cd; border-radius: 8px;">
		<tr>
			<td style="padding: 12px 15px; border-bottom: 1px solid #ffe69c; color: #856404; width: 40%;"><strong><?php esc_html_e( 'Cancellation Reason:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 15px; border-bottom: 1px solid #ffe69c; color: #856404;"><?php echo esc_html( $data['cancellation_reason'] ); ?></td>
		</tr>
	</table>
	<?php endif; ?>

	<div style="text-align: center; margin-top: 20px;">
		<a href="<?php echo esc_url( $panel_url ); ?>" style="display: inline-block; background: #1e6bf5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600;"><?php esc_html_e( 'Open Vendor Panel', 'mhm-rentiva' ); ?></a>
	</div>
</div>
