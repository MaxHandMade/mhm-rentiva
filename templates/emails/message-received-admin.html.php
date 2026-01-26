<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="message-received-admin-email">
	<div class="content">
		<p><strong><?php esc_html_e( 'From:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $data['message']['from_name'] ?? '' ); ?> &lt;<?php echo esc_html( $data['message']['from_email'] ?? '' ); ?>&gt;</p>
		<p><strong><?php esc_html_e( 'Subject:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $data['message']['subject'] ?? '' ); ?></p>
		<div class="pre" style="white-space: pre-wrap; background:#f8f9fa; border-radius:6px; padding:12px;"><?php echo esc_html( $data['message']['body'] ?? '' ); ?></div>
	</div>
</div>
