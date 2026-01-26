<?php

/**
 * Message reply form template
 *
 * @var WP_Post $message
 * @var array $meta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


?>
<div class="message-reply-form">
	<a href="<?php echo esc_url( \MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_message_view_url( $message_id ) ); ?>" class="button">
		← <?php esc_html_e( 'Back to Message', 'mhm-rentiva' ); ?>
	</a>

	<h2><?php esc_html_e( 'Send Reply', 'mhm-rentiva' ); ?></h2>

	<div class="reply-info">
		<p><strong><?php esc_html_e( 'Recipient:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $meta['customer_name'] ); ?> (<?php echo esc_html( $meta['customer_email'] ); ?>)</p>
		<p><strong><?php esc_html_e( 'Subject:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $message->post_title ); ?></p>
	</div>

	<form id="message-reply-form" method="post">
		<?php wp_nonce_field( 'mhm_message_reply', 'mhm_message_reply_nonce' ); ?>
		<input type="hidden" name="parent_message_id" value="<?php echo esc_attr( $message_id ); ?>">
		<input type="hidden" name="thread_id" value="<?php echo esc_attr( $meta['thread_id'] ); ?>">
		<input type="hidden" name="customer_email" value="<?php echo esc_attr( $meta['customer_email'] ); ?>">
		<input type="hidden" name="customer_name" value="<?php echo esc_attr( $meta['customer_name'] ); ?>">

		<div class="form-field">
			<label for="reply_subject"><?php esc_html_e( 'Subject:', 'mhm-rentiva' ); ?></label>
			<input type="text" id="reply_subject" name="subject" value="Re: <?php echo esc_attr( $message->post_title ); ?>" class="regular-text" required>
		</div>

		<div class="form-field">
			<label for="reply_message"><?php esc_html_e( 'Your Reply:', 'mhm-rentiva' ); ?></label>
			<?php
			wp_editor(
				'',
				'reply_message',
				array(
					'textarea_name' => 'message',
					'textarea_rows' => 10,
					'media_buttons' => false,
					'teeny'         => true,
				)
			);
			?>
		</div>

		<div class="form-field">
			<label>
				<input type="checkbox" name="close_thread" value="1">
				<?php esc_html_e( 'Close conversation after this reply', 'mhm-rentiva' ); ?>
			</label>
		</div>

		<div class="form-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Reply', 'mhm-rentiva' ); ?></button>
			<a href="<?php echo esc_url( \MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_message_view_url( $message_id ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'mhm-rentiva' ); ?></a>
		</div>
	</form>
</div>
