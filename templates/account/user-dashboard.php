<?php
declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

use MHMRentiva\Core\Dashboard\DashboardContext;
use MHMRentiva\Core\Dashboard\DashboardConfig;
use MHMRentiva\Core\Dashboard\DashboardNavigation;

if (! defined('ABSPATH')) {
	exit;
}

$dashboard         = is_array($dashboard_data ?? null) ? $dashboard_data : array();
$active_tab        = $dashboard['active_tab'] ?? 'overview';
$dashboard_url     = $dashboard['dashboard_url'] ?? home_url('/panel/');
$recent_bookings   = is_array($dashboard['recent_bookings'] ?? null) ? $dashboard['recent_bookings'] : array();
$user              = $dashboard['user'] ?? wp_get_current_user();
$context           = sanitize_key( (string) ( $dashboard['context'] ?? DashboardContext::resolve() ));
$nav_items         = DashboardNavigation::get_items($context);
$kpi_items         = is_array($dashboard['kpis'] ?? null) ? $dashboard['kpis'] : DashboardConfig::get_kpis($context);
$kpi_data          = is_array($dashboard['kpi_data'] ?? null) ? $dashboard['kpi_data'] : array();
$extension_panels  = is_array($dashboard['panels'] ?? null) ? $dashboard['panels'] : array();
$user_display_name = $user->display_name;
if (! $user_display_name) {
	$user_display_name = $user->user_login;
}
?>

<div class="mhm-rentiva-dashboard mhmui-front mhmui-front-page">
	<aside class="mhm-rentiva-dashboard__sidebar">
		<div class="mhm-rentiva-dashboard__brand">
			<div class="mhm-rentiva-dashboard__brand-logo">R</div>
			<div class="mhm-rentiva-dashboard__brand-title"><?php esc_html_e('Rentiva Panel', 'mhm-rentiva'); ?></div>
		</div>

		<nav class="mhm-rentiva-dashboard__nav" aria-label="<?php esc_attr_e('Dashboard Navigation', 'mhm-rentiva'); ?>">
			<?php foreach ($nav_items as $tab_key => $item) : ?>
				<a
					class="mhm-rentiva-dashboard__nav-item <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>"
					href="<?php echo esc_url(add_query_arg('tab', $tab_key, $dashboard_url)); ?>"
					data-tab="<?php echo esc_attr($tab_key); ?>">
					<?php echo esc_html( (string) ( $item['label'] ?? '' )); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="mhm-rentiva-dashboard__user">
			<div class="mhm-rentiva-dashboard__user-card">
				<div class="mhm-rentiva-dashboard__user-avatar" aria-hidden="true">
					<?php
					printf(
						'<span class="mhm-rentiva-dashboard__user-avatar-initials">%s</span>',
						esc_html(mhmrentiva_initial_avatar_letter( (string) $user_display_name))
					);
					?>
				</div>
				<div class="mhm-rentiva-dashboard__user-info">
					<div class="mhm-rentiva-dashboard__user-name"><?php echo esc_html($user_display_name); ?></div>
					<?php
					// The prefixed key is what SessionManager writes from 5.2.0. The bare
					// key is still read as a fallback: a site upgrading from an earlier
					// version has the value only under the old name until the user logs
					// in again, and showing a frozen date would be worse than showing
					// the real one.
					$last_login_raw = (string) get_user_meta($user->ID, 'mhmrentiva_last_login', true);
					if ($last_login_raw === '') {
						$last_login_raw = (string) get_user_meta($user->ID, 'last_login', true);
					}
					if ($last_login_raw !== '') {
						$last_login_ts = strtotime($last_login_raw);
						if ($last_login_ts) {
							$last_login_display = date_i18n(get_option('date_format') . ' H:i', $last_login_ts);
							?>
							<div class="mhm-rentiva-dashboard__user-meta" title="<?php esc_attr_e('Last successful login', 'mhm-rentiva'); ?>">
								<?php
								/* translators: %s: formatted date/time of last login */
								echo esc_html(sprintf(__('Last login: %s', 'mhm-rentiva'), $last_login_display));
								?>
							</div>
							<?php
						}
					}
					?>
				</div>
			</div>
			<a class="mhm-rentiva-dashboard__logout" href="<?php echo esc_url(wp_logout_url($dashboard_url)); ?>">
				<?php esc_html_e('Sign Out', 'mhm-rentiva'); ?>
			</a>
		</div>
	</aside>

	<main class="mhm-rentiva-dashboard__main">
		<div class="mhm-rentiva-dashboard__content">
			<?php if ($active_tab === 'overview') : ?>
				<div class="mhm-rentiva-dashboard__header">
					<h2><?php esc_html_e('Overview', 'mhm-rentiva'); ?></h2>
				</div>

				<?php
				// KPI strip: ui-core kit cards (KPI kit migration, Task 9). No icons on
				// the front end (K4), no count-up animation; a trend is the kit's
				// delta line, whose direction mark the kit renders itself.
				$dashboard_cards = array();
				foreach ($kpi_items as $kpi_key => $kpi_config) {
					$kpi_item  = is_array($kpi_data[ $kpi_key ] ?? null) ? $kpi_data[ $kpi_key ] : array();
					$kpi_value = (int) ( $kpi_item['total'] ?? 0 );
					$card      = array(
						'label' => (string) ( $kpi_config['label'] ?? '' ),
						'value' => (string) $kpi_value,
						'sub'   => (string) ( $kpi_config['meta'] ?? '' ),
					);

					if (! empty($kpi_config['trend']) && isset($kpi_item['trend'])) {
						$kpi_direction = (string) ( $kpi_item['direction'] ?? 'neutral' );
						$kpi_direction = in_array($kpi_direction, array( 'up', 'down' ), true) ? $kpi_direction : 'flat';
						$card['delta'] = array(
							'direction' => $kpi_direction,
							// Accessible name for the delta line (kit 0.13.0): the arrow
							// mark is aria-hidden, so up and down would otherwise announce
							// identically to a screen reader.
							'label'     => 'up' === $kpi_direction
								? __('increase', 'mhm-rentiva')
								: ( 'down' === $kpi_direction ? __('decrease', 'mhm-rentiva') : __('no change', 'mhm-rentiva') ),
							'text'      => sprintf(
								/* translators: 1: percentage change (magnitude only; the direction is carried by the arrow), 2: the period this compares. */
								__('%1$s%% %2$s', 'mhm-rentiva'),
								abs( (int) $kpi_item['trend']),
								(string) ( $kpi_config['trend_meta'] ?? $kpi_config['meta'] ?? '' )
							),
						);
					}

					$dashboard_cards[] = $card;
				}
				?>
				<div class="mhm-rentiva-dashboard__strip">
					<?php echo wp_kses_post( \MHMRentiva\Admin\Core\AssetManager::stats_grid_html( $dashboard_cards, 3 ) ); ?>
				</div>

				<div class="mhm-rentiva-dashboard__overview-grid">
					<!-- Recent Bookings (left, wider) -->
					<div class="mhm-rentiva-dashboard__overview-bookings mhm-rentiva-dashboard__section">
						<div class="mhm-rentiva-dashboard__section-head">
							<h3><?php esc_html_e('Recent Bookings', 'mhm-rentiva'); ?></h3>
							<a href="<?php echo esc_url(add_query_arg('tab', 'bookings', $dashboard_url)); ?>">
								<?php esc_html_e('View All', 'mhm-rentiva'); ?>
							</a>
						</div>

						<div class="mhm-rentiva-dashboard__table-wrap">
							<table class="mhm-rentiva-dashboard__table">
								<thead>
									<tr>
										<th><?php esc_html_e('Booking', 'mhm-rentiva'); ?></th>
										<th><?php esc_html_e('Service', 'mhm-rentiva'); ?></th>
										<th><?php esc_html_e('Pickup Date', 'mhm-rentiva'); ?></th>
										<th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if (! empty($recent_bookings)) : ?>
										<?php foreach ($recent_bookings as $booking) : ?>
											<?php
											$booking_id     = (int) ( $booking->ID ?? 0 );
											$vehicle_id     = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
											$vehicle_title  = $vehicle_id > 0 ? get_the_title($vehicle_id) : __('N/A', 'mhm-rentiva');
											$pickup_date    = (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true);
											$pickup_time    = (string) get_post_meta($booking_id, '_mhmrentiva_pickup_time', true);
											$pickup_display = $pickup_date !== '' ? date_i18n(get_option('date_format'), strtotime($pickup_date)) : '-';
											if ($pickup_date !== '' && $pickup_time !== '') {
												$pickup_display .= ' · ' . date_i18n(get_option('time_format'), strtotime($pickup_date . ' ' . $pickup_time));
											}
											$booking_status     = (string) get_post_meta($booking_id, '_mhmrentiva_status', true);
											$booking_status_key = sanitize_key($booking_status);
											$status_class       = 'mhm-rentiva-dashboard__status';
											$status_map         = array(
												'completed' => 'is-completed',
												'confirmed' => 'is-confirmed',
												'in_progress' => 'is-progress',
												'pending'  => 'is-pending',
												'cancelled' => 'is-cancelled',
												'refunded' => 'is-refunded',
											);
											$status_label_map   = array(
												'completed' => __('Completed', 'mhm-rentiva'),
												'confirmed' => __('Confirmed', 'mhm-rentiva'),
												'in_progress' => __('In Progress', 'mhm-rentiva'),
												'pending'  => __('Pending', 'mhm-rentiva'),
												'cancelled' => __('Cancelled', 'mhm-rentiva'),
												'refunded' => __('Refunded', 'mhm-rentiva'),
											);
											if (isset($status_map[ $booking_status_key ])) {
												$status_class .= ' ' . $status_map[ $booking_status_key ];
											}
											$status_label = $status_label_map[ $booking_status_key ] ?? ( $booking_status_key !== '' ? ucwords(str_replace('_', ' ', $booking_status_key)) : '-' );
											?>
											<tr>
												<td data-label="<?php esc_attr_e('Booking', 'mhm-rentiva'); ?>">
													<a class="mhm-rentiva-dashboard__booking-cell" href="<?php echo esc_url(\MHMRentiva\Admin\Frontend\Account\AccountController::get_booking_view_url($booking_id)); ?>">
														<span>#<?php echo esc_html( (string) mhmrentiva_get_display_id( (int) $booking_id)); ?></span>
													</a>
												</td>
												<td data-label="<?php esc_attr_e('Service', 'mhm-rentiva'); ?>"><?php echo esc_html( (string) $vehicle_title); ?></td>
												<td data-label="<?php esc_attr_e('Pickup Date', 'mhm-rentiva'); ?>"><?php echo esc_html($pickup_display); ?></td>
												<td data-label="<?php esc_attr_e('Status', 'mhm-rentiva'); ?>">
													<span class="<?php echo esc_attr($status_class); ?>">
														<?php echo esc_html($status_label); ?>
													</span>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php else : ?>
										<tr>
											<td colspan="4"><?php esc_html_e('No bookings found.', 'mhm-rentiva'); ?></td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

				</div>
			<?php elseif ($active_tab === 'bookings') : ?>
				<div class="mhm-rentiva-dashboard__tab-content">
					<?php \MHMRentiva\Admin\Core\Utilities\Templates::output_shortcode( (string) ( $dashboard['bookings_tab_shortcode'] ?? '[rentiva_my_bookings hide_nav="1"]' )); ?>
				</div>
			<?php elseif ($active_tab === 'favorites') : ?>
				<div class="mhm-rentiva-dashboard__tab-content">
					<?php
                    \MHMRentiva\Admin\Core\Utilities\Templates::output_shortcode( (string) ( $dashboard['favorites_tab_shortcode'] ?? '[rentiva_my_favorites]' ) );
					?>
				</div>
			<?php endif; ?>

			<?php if (isset($extension_panels[ $active_tab ]) && is_string($extension_panels[ $active_tab ])) : ?>
				<div class="mhm-rentiva-dashboard__tab-content">
					<?php echo wp_kses_post($extension_panels[ $active_tab ]); ?>
				</div>
			<?php endif; ?>
		</div>
	</main>
</div>
