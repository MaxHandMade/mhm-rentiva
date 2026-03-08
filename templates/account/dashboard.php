<?php

declare(strict_types=1);
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * My Account - Dashboard Template
 *
 * @var WP_User $user
 * @var int $bookings_count
 * @var int $active_bookings_count
 * @var array $recent_bookings
 * @var array $navigation
 */

if (! defined('ABSPATH')) {
	exit;
}



// Get customer experience settings
$navigation    = $data['navigation'] ?? array();
$is_integrated = empty($navigation);
$wrapper_class = 'mhm-rentiva-account-page';
if ($is_integrated) {
	$wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">

	<!-- Account Navigation (only show if not on WooCommerce My Account page) -->
	<?php if (! empty($data['navigation'])) : ?>
		<?php echo wp_kses_post(\MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', array( 'navigation' => $data['navigation'] ), true)); ?>
	<?php endif; ?>

	<!-- Dashboard Content -->
	<div class="mhm-account-content">

		<!-- Welcome Header -->
		<div class="account-header">
			<h1>
				<?php
				/* translators: %s: customer display name. */
				printf(esc_html__('Welcome back, %s!', 'mhm-rentiva'), esc_html($data['user']->display_name));
				?>
			</h1>
			<p class="account-subtitle"><?php echo esc_html($data['welcome_message']); ?></p>
		</div>

		<!-- Statistics Cards -->
		<div class="stats-grid">
			<div class="stat-card stat-card-total-bookings">
				<div class="stat-icon">
					<?php Icons::render('calendar'); ?>
				</div>
				<div class="stat-content">
					<h3 class="stat-number"><?php echo esc_html($data['bookings_count']); ?></h3>
					<p class="stat-label"><?php esc_html_e('Total Bookings', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="stat-card stat-card-active-bookings">
				<div class="stat-icon">
					<?php Icons::render('car'); ?>
				</div>
				<div class="stat-content">
					<h3 class="stat-number"><?php echo esc_html($data['active_bookings_count']); ?></h3>
					<p class="stat-label"><?php esc_html_e('Active Bookings', 'mhm-rentiva'); ?></p>
				</div>
			</div>

			<div class="stat-card stat-card-total-favorites">
				<div class="stat-icon">
					<?php Icons::render('heart'); ?>
				</div>
				<div class="stat-content">
					<?php
					if ($data['favorites'] === '1') {
						$favorites_data  = get_user_meta($data['user']->ID, 'mhm_rentiva_favorites', true);
						$favorites_count = is_array($favorites_data) ? count($favorites_data) : 0;
					} else {
						$favorites_count = 0;
					}
					?>
					<h3 class="stat-number"><?php echo esc_html($favorites_count); ?></h3>
					<p class="stat-label"><?php esc_html_e('Favorite Vehicles', 'mhm-rentiva'); ?></p>
				</div>
			</div>
		</div>

		<!-- Recent Bookings -->
		<?php if ($data['booking_history'] === '1') : ?>
			<div class="account-section">
				<div class="section-header">
					<h2><?php esc_html_e('Recent Bookings', 'mhm-rentiva'); ?></h2>
					<a href="<?php echo esc_url(add_query_arg('endpoint', 'bookings', \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url())); ?>" class="view-all-link">
						<?php esc_html_e('View All', 'mhm-rentiva'); ?> â†’
					</a>
				</div>

				<?php if (! empty($data['recent_bookings'])) : ?>
					<div class="bookings-list">
						<?php
						foreach ($data['recent_bookings'] as $booking) :
							$vehicle_id     = get_post_meta($booking->ID, '_mhm_vehicle_id', true);
							$vehicle        = get_post($vehicle_id);
							$booking_status = get_post_meta($booking->ID, '_mhm_status', true);
							$pickup_date    = get_post_meta($booking->ID, '_mhm_pickup_date', true);
							$dropoff_date   = get_post_meta($booking->ID, '_mhm_dropoff_date', true);
							// Get pickup and dropoff times with fallbacks
							$pickup_time = get_post_meta($booking->ID, '_mhm_start_time', true);
							if (! $pickup_time) {
								$pickup_time = get_post_meta($booking->ID, '_mhm_pickup_time', true);
							}
							if (! $pickup_time) {
								$pickup_time = get_post_meta($booking->ID, '_booking_pickup_time', true);
							}
							$dropoff_time = get_post_meta($booking->ID, '_mhm_end_time', true);
							if (! $dropoff_time) {
								$dropoff_time = get_post_meta($booking->ID, '_mhm_dropoff_time', true);
							}
							if (! $dropoff_time) {
								$dropoff_time = get_post_meta($booking->ID, '_booking_dropoff_time', true);
							}
							$total_price = get_post_meta($booking->ID, '_mhm_total_price', true);

							$status_class = '';
							$status_label = '';
							switch ($booking_status) {
								case 'confirmed':
									$status_class = 'status-confirmed';
									$status_label = esc_html__('Confirmed', 'mhm-rentiva');
									break;
								case 'completed':
									$status_class = 'status-completed';
									$status_label = esc_html__('Completed', 'mhm-rentiva');
									break;
								case 'cancelled':
									$status_class = 'status-cancelled';
									$status_label = esc_html__('Cancelled', 'mhm-rentiva');
									break;
								default:
									$status_class = 'status-pending';
									$status_label = esc_html__('Pending', 'mhm-rentiva');
							}
							?>
							<div class="booking-item">
								<div class="booking-vehicle">
									<?php if ($vehicle && has_post_thumbnail($vehicle_id)) : ?>
										<div class="vehicle-thumbnail">
											<?php echo get_the_post_thumbnail($vehicle_id, 'thumbnail'); ?>
										</div>
									<?php endif; ?>
									<div class="vehicle-info">
										<h4><?php echo $vehicle ? esc_html($vehicle->post_title) : esc_html__('Vehicle Not Found', 'mhm-rentiva'); ?></h4>
										<p class="booking-number">
											<?php
											/* translators: %s: booking post ID. */
											printf(esc_html__('Booking #%s', 'mhm-rentiva'), esc_html($booking->ID));
											?>
										</p>
									</div>
								</div>

								<div class="booking-dates">
									<div class="date-item">
										<span class="date-label"><?php esc_html_e('Pickup:', 'mhm-rentiva'); ?></span>
										<span class="date-value">
											<?php
											$pickup_dt = trim($pickup_date . ' ' . ( $pickup_time ? $pickup_time : '' ));
											$pickup_ts = strtotime($pickup_dt);
											$format    = get_option('date_format') . ' ' . get_option('time_format');
											echo esc_html($pickup_ts ? date_i18n($format, $pickup_ts) : date_i18n(get_option('date_format'), strtotime($pickup_date)));
											?>
										</span>
									</div>
									<div class="date-item">
										<span class="date-label"><?php esc_html_e('Return:', 'mhm-rentiva'); ?></span>
										<span class="date-value">
											<?php
											$dropoff_dt = trim($dropoff_date . ' ' . ( $dropoff_time ? $dropoff_time : '' ));
											$dropoff_ts = strtotime($dropoff_dt);
											$format     = get_option('date_format') . ' ' . get_option('time_format');
											echo esc_html($dropoff_ts ? date_i18n($format, $dropoff_ts) : date_i18n(get_option('date_format'), strtotime($dropoff_date)));
											?>
										</span>
									</div>
								</div>

								<div class="booking-status">
									<span class="status-badge <?php echo esc_attr($status_class); ?>">
										<?php echo esc_html($status_label); ?>
									</span>
								</div>

								<div class="booking-price">
									<span class="price-amount">
										<?php
										if (function_exists('wc_price')) {
											echo wp_kses_post(wc_price($total_price));
										} else {
											$currency_code     = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
											$currency_symbol   = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
											$currency_position = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
											$formatted_amount  = number_format( (float) $total_price, 2, ',', '.');

											switch ($currency_position) {
												case 'left':
													echo esc_html($currency_symbol . $formatted_amount);
													break;
												case 'left_space':
													echo esc_html($currency_symbol . ' ' . $formatted_amount);
													break;
												case 'right':
													echo esc_html($formatted_amount . $currency_symbol);
													break;
												case 'right_space':
												default:
													echo esc_html($formatted_amount . ' ' . $currency_symbol);
													break;
											}
										}
										?>
									</span>
								</div>

								<div class="booking-actions">
									<a href="<?php echo esc_url(\MHMRentiva\Admin\Frontend\Account\AccountController::get_booking_view_url($booking->ID)); ?>" class="btn-view">
										<?php esc_html_e('View', 'mhm-rentiva'); ?>
									</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="no-bookings">
						<div class="empty-state">
							<div class="empty-icon">&#x1F4C5;</div>
							<h3><?php esc_html_e('No Bookings Yet', 'mhm-rentiva'); ?></h3>
							<p><?php esc_html_e('You haven\'t made any vehicle bookings yet. Browse our fleet and make your first booking!', 'mhm-rentiva'); ?></p>
							<a href="
							<?php
							$vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
							if (! $vehicles_url) {
								$vehicles_url = get_post_type_archive_link('vehicle');
								if (! $vehicles_url) {
									$vehicles_url = home_url('/');
								}
							}
							echo esc_url($vehicles_url);
							?>
							" class="btn btn-primary">
								<?php esc_html_e('Browse Vehicles', 'mhm-rentiva'); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Quick Actions -->
		<div class="account-section">
			<div class="section-header">
				<h2><?php esc_html_e('Quick Actions', 'mhm-rentiva'); ?></h2>
			</div>
			<div class="quick-actions">
				<a href="
				<?php
				$vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
				if (! $vehicles_url) {
					$vehicles_url = get_post_type_archive_link('vehicle');
					if (! $vehicles_url) {
						$vehicles_url = home_url('/');
					}
				}
				echo esc_url($vehicles_url);
				?>
				" class="action-card">
					<div class="action-icon">&#x1F697;</div>
					<div class="action-content">
						<h4><?php esc_html_e('Browse Vehicles', 'mhm-rentiva'); ?></h4>
						<p><?php esc_html_e('Explore our fleet', 'mhm-rentiva'); ?></p>
					</div>
				</a>

				<a href="
				<?php
				$booking_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');
				if (! $booking_url) {
					$booking_url = home_url('/');
				}
				echo esc_url($booking_url);
				?>
				" class="action-card">
					<div class="action-icon">&#x1F4C5;</div>
					<div class="action-content">
						<h4><?php esc_html_e('New Booking', 'mhm-rentiva'); ?></h4>
						<p><?php esc_html_e('Make a new reservation', 'mhm-rentiva'); ?></p>
					</div>
				</a>

				<a href="
				<?php
				$contact_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_contact');
				if (! $contact_url) {
					$contact_url = home_url('/');
				}
				echo esc_url($contact_url);
				?>
				" class="action-card">
					<div class="action-icon">&#x1F4AC;</div>
					<div class="action-content">
						<h4><?php esc_html_e('Contact Support', 'mhm-rentiva'); ?></h4>
						<p><?php esc_html_e('We\'re here to help', 'mhm-rentiva'); ?></p>
					</div>
				</a>
			</div>
		</div>

		<!-- Communication Preferences Section -->
		<?php
		$comm_preferences = SettingsCore::get('mhm_rentiva_customer_comm_preferences', '1');
		if ($comm_preferences === '1') :
			?>
			<div class="account-section">
				<div class="section-header">
					<h2><?php esc_html_e('Communication Preferences', 'mhm-rentiva'); ?></h2>
					<p><?php esc_html_e('Manage your email notification preferences', 'mhm-rentiva'); ?></p>
				</div>

				<div class="communication-preferences">
					<form method="post" action="" class="comm-prefs-form">
						<?php wp_nonce_field('update_comm_preferences', 'comm_prefs_nonce'); ?>

						<div class="preference-item">
							<label class="preference-label">
								<input type="checkbox" name="welcome_email" value="1"
									<?php checked(get_user_meta(get_current_user_id(), 'mhm_welcome_email', true), '1'); ?>>
								<span class="preference-text">
									<strong><?php esc_html_e('Welcome Emails', 'mhm-rentiva'); ?></strong>
									<small><?php esc_html_e('Receive welcome emails for new features and updates', 'mhm-rentiva'); ?></small>
								</span>
							</label>
						</div>

						<div class="preference-item">
							<label class="preference-label">
								<input type="checkbox" name="booking_notifications" value="1"
									<?php checked(get_user_meta(get_current_user_id(), 'mhm_booking_notifications', true), '1'); ?>>
								<span class="preference-text">
									<strong><?php esc_html_e('Booking Notifications', 'mhm-rentiva'); ?></strong>
									<small><?php esc_html_e('Receive notifications about your bookings', 'mhm-rentiva'); ?></small>
								</span>
							</label>
						</div>

						<div class="preference-item">
							<label class="preference-label">
								<input type="checkbox" name="marketing_emails" value="1"
									<?php checked(get_user_meta(get_current_user_id(), 'mhm_marketing_emails', true), '1'); ?>>
								<span class="preference-text">
									<strong><?php esc_html_e('Marketing Emails', 'mhm-rentiva'); ?></strong>
									<small><?php esc_html_e('Receive promotional offers and special deals', 'mhm-rentiva'); ?></small>
								</span>
							</label>
						</div>

						<div class="form-actions">
							<button type="submit" name="update_comm_preferences" class="btn btn-primary">
								<?php esc_html_e('Save Preferences', 'mhm-rentiva'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<!-- Privacy Controls Section -->
		<?php
		$gdpr_enabled = SettingsCore::get('mhm_rentiva_customer_gdpr_compliance', '1');
		if ($gdpr_enabled === '1') :
			?>
			<div class="account-section">
				<div class="section-header">
					<h2><?php esc_html_e('Privacy Controls', 'mhm-rentiva'); ?></h2>
					<p><?php esc_html_e('Manage your personal data and privacy settings', 'mhm-rentiva'); ?></p>
				</div>

				<div class="privacy-controls">
					<div class="privacy-action">
						<h4><?php esc_html_e('Data Export', 'mhm-rentiva'); ?></h4>
						<p><?php esc_html_e('Download a copy of all your personal data', 'mhm-rentiva'); ?></p>
						<button type="button" id="export-data" class="btn btn-secondary">
							<?php esc_html_e('Export My Data', 'mhm-rentiva'); ?>
						</button>
					</div>

					<div class="privacy-action">
						<h4><?php esc_html_e('Withdraw Consent', 'mhm-rentiva'); ?></h4>
						<p><?php esc_html_e('Withdraw your consent for data processing', 'mhm-rentiva'); ?></p>
						<button type="button" id="withdraw-consent" class="btn btn-warning">
							<?php esc_html_e('Withdraw Consent', 'mhm-rentiva'); ?>
						</button>
					</div>

					<div class="privacy-action">
						<h4><?php esc_html_e('Delete Account', 'mhm-rentiva'); ?></h4>
						<p><?php esc_html_e('Permanently delete your account and all associated data', 'mhm-rentiva'); ?></p>
						<button type="button" id="delete-account" class="btn btn-danger">
							<?php esc_html_e('Delete Account', 'mhm-rentiva'); ?>
						</button>
					</div>
				</div>
			</div>
		<?php endif; ?>

	</div><!-- .mhm-account-content -->

</div><!-- .mhm-rentiva-account-page -->