<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="welcome-email-content">
	<div class="content">
		<p>
			<?php
			/* translators: %s: customer name. */
			echo esc_html( sprintf( __( 'Hello %s, thanks for joining us!', 'mhm-rentiva' ), (string) ( $data['customer']['name'] ?? '' ) ) );
			?>
		</p>
		<p><?php esc_html_e( 'You can access your account anytime using the button below:', 'mhm-rentiva' ); ?></p>
		<?php
		// ✅ Use WooCommerce native approach instead of ShortcodeUrlManager
		$account_url = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/my-account/' );
		?>
		<p style="text-align:center">
			<a class="cta-button" href="<?php echo esc_url( $account_url ); ?>" style="display: inline-block; background: #2196F3; color: white; padding: 12px 22px; text-decoration: none; border-radius: 6px; margin: 10px 0; font-weight: 600;">
				<?php esc_html_e( 'My Account', 'mhm-rentiva' ); ?>
			</a>
		</p>
	</div>

	<div class="welcome-message" style="margin-top: 20px; text-align: center; color: #666;">
		<p><?php esc_html_e( 'We are happy to have you with us.', 'mhm-rentiva' ); ?></p>
	</div>
</div>
