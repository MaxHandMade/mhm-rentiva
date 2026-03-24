<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="booking-created-email">
	<div class="intro">
		<p>
			<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
			/* translators: %s: customer name. */
			printf( esc_html__( 'Dear %s, your booking has been successfully created.', 'mhm-rentiva' ), esc_html( $data['customer']['name'] ?? '' ) );
			?>
		</p>
	</div>

	<div class="content">
		<h2><?php esc_html_e( 'Reservation Details', 'mhm-rentiva' ); ?></h2>

		<div class="booking-details" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Reservation No:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;">#<?php echo esc_html( $data['booking']['id'] ?? '' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Vehicle:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;"><?php echo esc_html( $data['vehicle']['title'] ?? '' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Pickup Date:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;"><?php echo esc_html( $data['booking']['pickup_date'] ?? '' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Return Date:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;"><?php echo esc_html( $data['booking']['return_date'] ?? '' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Rental Period:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;"><?php echo esc_html( $data['booking']['rental_days'] ?? '' ); ?> <?php esc_html_e( 'days', 'mhm-rentiva' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Total Amount:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;"><?php echo esc_html( apply_filters( 'mhm_rentiva/currency_symbol', '' ) ); ?><?php echo esc_html( number_format( $data['booking']['total_price'] ?? 0, 2 ) ); ?></span>
			</div>

			<?php
			// Payment information
			$payment_type     = $data['booking']['payment_type'] ?? '';
			$deposit_amount   = $data['booking']['deposit_amount'] ?? 0;
			$remaining_amount = $data['booking']['remaining_amount'] ?? 0;
			$payment_method   = $data['booking']['payment_method'] ?? '';
			$payment_status   = $data['booking']['payment_status'] ?? '';

			if ( $payment_type === 'deposit' && $deposit_amount > 0 ) :
				?>
				<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
					<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Deposit Amount:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value" style="color: #333;"><?php echo esc_html( apply_filters( 'mhm_rentiva/currency_symbol', '' ) ); ?><?php echo esc_html( number_format( $deposit_amount, 2 ) ); ?></span>
				</div>
				<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
					<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Remaining Amount:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value" style="color: #333;"><?php echo esc_html( apply_filters( 'mhm_rentiva/currency_symbol', '' ) ); ?><?php echo esc_html( number_format( $remaining_amount, 2 ) ); ?></span>
				</div>
			<?php endif; ?>

			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Payment Method:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;">
					<?php
					$payment_methods = array(
						'credit_card'   => esc_html__( 'Credit Card', 'mhm-rentiva' ),
						'bank_transfer' => esc_html__( 'Bank Transfer', 'mhm-rentiva' ),
						'cash'          => esc_html__( 'Cash', 'mhm-rentiva' ),
						'offline'       => esc_html__( 'Offline Payment', 'mhm-rentiva' ),
					);
					echo esc_html( $payment_methods[ $payment_method ] ?? ucfirst( $payment_method ) );
					?>
				</span>
			</div>

			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Payment Status:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value" style="color: #333;">
					<?php
					$status_colors = array(
						'completed' => '#28a745',
						'pending'   => '#ffc107',
						'failed'    => '#dc3545',
					);
					$status_texts  = array(
						'completed' => esc_html__( 'Completed', 'mhm-rentiva' ),
						'pending'   => esc_html__( 'Pending', 'mhm-rentiva' ),
						'failed'    => esc_html__( 'Failed', 'mhm-rentiva' ),
					);
					$color         = $status_colors[ $payment_status ] ?? '#6c757d';
					$text          = $status_texts[ $payment_status ] ?? ucfirst( $payment_status );
					?>
					<span style="color: <?php echo esc_attr( $color ); ?>; font-weight: bold;"><?php echo esc_html( $text ); ?></span>
				</span>
			</div>
		</div>

		<div class="booking-confirmation-message" style="background: #e8f5e9; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #28a745;">
			<p style="margin: 0; color: #155724;">
				<?php esc_html_e( 'Thank you for your booking. We have received your request and will process it shortly.', 'mhm-rentiva' ); ?>
			</p>
		</div>

		<?php
		// User account check
		$customer_email = $data['customer']['email'] ?? '';
		$user           = get_user_by( 'email', $customer_email );
		$is_new_user    = false;

		// Use WooCommerce native approach instead of ShortcodeUrlManager.
		$account_url = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/my-account/' );

		$reset_url = '';

		if ( ! $user && ! empty( $customer_email ) ) {
			// New user created
			$is_new_user = true;
			$user        = get_user_by( 'email', $customer_email );

			if ( $user ) {
				$reset_key = get_password_reset_key( $user );
				if ( ! is_wp_error( $reset_key ) ) {
					$reset_url = network_site_url(
						"wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode( $customer_email ),
						'login'
					);
				}
			}
		}
		?>

		<!-- Account Access Section -->
		<div class="account-section" style="background: #e3f2fd; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #2196F3;">
			<h3 style="margin: 0 0 15px 0; color: #1976d2;">
				<?php
				echo $is_new_user ?
						esc_html__( 'Your Account Has Been Created!', 'mhm-rentiva' ) :
						esc_html__( 'Access Your Account', 'mhm-rentiva' );
				?>
			</h3>

			<?php if ( $is_new_user && ! empty( $reset_url ) ) : ?>
				<p><?php esc_html_e( 'We have automatically created an account for you to manage your bookings and view your rental history.', 'mhm-rentiva' ); ?></p>

				<div class="new-user-box" style="background: #fff; padding: 15px; border-radius: 6px; margin: 15px 0; border: 2px dashed #2196F3;">
					<p style="margin: 0 0 10px 0;">
						<strong><?php esc_html_e( 'Your Username:', 'mhm-rentiva' ); ?></strong><br>
						<?php echo esc_html( $customer_email ); ?>
					</p>
					<p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">
						<?php esc_html_e( 'Please set your password using the button below:', 'mhm-rentiva' ); ?>
					</p>
				</div>

				<div style="text-align: center; margin: 20px 0;">
					<a href="<?php echo esc_url( $reset_url ); ?>" class="cta-button" style="display: inline-block; background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; font-size: 16px;">
						<?php esc_html_e( 'Set Your Password', 'mhm-rentiva' ); ?>
					</a>
				</div>

				<p style="font-size: 12px; color: #999; text-align: center; margin: 10px 0 0 0;">
					<?php esc_html_e( 'This link expires in 24 hours.', 'mhm-rentiva' ); ?>
				</p>

			<?php else : ?>
				<p><?php esc_html_e( 'You can view and manage your booking from your account dashboard.', 'mhm-rentiva' ); ?></p>

				<div style="text-align: center; margin: 20px 0;">
					<a href="<?php echo esc_url( $account_url ); ?>" class="cta-button" style="display: inline-block; background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; font-size: 16px;">
						<?php esc_html_e( 'My Account', 'mhm-rentiva' ); ?>
					</a>
				</div>

				<p style="font-size: 13px; color: #666; text-align: center; margin: 10px 0 0 0;">
					<?php esc_html_e( 'Login with your registered email and password', 'mhm-rentiva' ); ?>
				</p>
			<?php endif; ?>

			<div class="tip-box" style="background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #ffc107;">
				<p style="margin: 0; font-size: 13px; color: #856404;">
					<strong><?php esc_html_e( 'Tip:', 'mhm-rentiva' ); ?></strong>
					<?php esc_html_e( 'Save your login credentials for faster bookings in the future!', 'mhm-rentiva' ); ?>
				</p>
			</div>
		</div>

	</div>
</div>

