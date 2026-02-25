<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

use MHMRentiva\Core\Dashboard\DashboardContext;
use MHMRentiva\Core\Dashboard\DashboardConfig;
use MHMRentiva\Core\Dashboard\DashboardNavigation;

if (! defined('ABSPATH')) {
	exit;
}

$dashboard = is_array($dashboard_data ?? null) ? $dashboard_data : (is_array($data ?? null) ? $data : array());
$active_tab = $dashboard['active_tab'] ?? 'overview';
$dashboard_url = $dashboard['dashboard_url'] ?? home_url('/panel/');
$recent_bookings = is_array($dashboard['recent_bookings'] ?? null) ? $dashboard['recent_bookings'] : array();
$user = $dashboard['user'] ?? wp_get_current_user();
$context = sanitize_key((string) ($dashboard['context'] ?? DashboardContext::resolve()));
if (! in_array($context, array('customer', 'vendor'), true)) {
	$context = DashboardContext::resolve();
}
$nav_items = DashboardNavigation::get_items($context);
$kpi_items = is_array($dashboard['kpis'] ?? null) ? $dashboard['kpis'] : DashboardConfig::get_kpis($context);
$kpi_data = is_array($dashboard['kpi_data'] ?? null) ? $dashboard['kpi_data'] : array();
$analytics = is_array($dashboard['analytics'] ?? null) ? $dashboard['analytics'] : array();
?>

<div class="mhm-rentiva-dashboard">
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
					<?php echo esc_html((string) ($item['label'] ?? '')); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="mhm-rentiva-dashboard__user">
			<div class="mhm-rentiva-dashboard__user-name"><?php echo esc_html($user->display_name ?: $user->user_login); ?></div>
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

				<div class="mhm-rentiva-dashboard__kpis">
					<?php foreach ($kpi_items as $kpi_key => $kpi_config) : ?>
						<?php
						// Skip purely financial metrics in the main KPI loop
						if (in_array($kpi_key, array('available_balance', 'pending_balance', 'total_paid_out'), true)) {
							continue;
						}

						$kpi_label = (string) ($kpi_config['label'] ?? '');
						$kpi_meta = (string) ($kpi_config['meta'] ?? '');
						$kpi_icon = sanitize_key((string) ($kpi_config['icon'] ?? 'chart'));
						$kpi_item = is_array($kpi_data[$kpi_key] ?? null) ? $kpi_data[$kpi_key] : array();
						$kpi_value = (int) ($kpi_item['total'] ?? 0);
						$kpi_trend_direction = 'neutral';
						$kpi_trend_value = null;
						$with_trend = ! empty($kpi_config['trend']);

						if ($with_trend && isset($kpi_item['trend'])) {
							$kpi_trend_direction = sanitize_key((string) ($kpi_item['direction'] ?? 'neutral'));
							$kpi_trend_direction = in_array($kpi_trend_direction, array('up', 'down', 'neutral'), true) ? $kpi_trend_direction : 'neutral';
							$kpi_trend_value = abs((int) $kpi_item['trend']);
						}
						?>
						<div class="mhm-rentiva-dashboard__kpi-card">
							<div class="mhm-rentiva-dashboard__kpi-header">
								<div class="mhm-rentiva-dashboard__kpi-icon" aria-hidden="true">
									<?php if ($kpi_icon === 'calendar') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M7 3.75V6.25M17 3.75V6.25M3.75 9H20.25M6.5 12.25H9.5M12 12.25H15M6.5 16H9.5M3.75 7.75C3.75 6.92157 4.42157 6.25 5.25 6.25H18.75C19.5784 6.25 20.25 6.92157 20.25 7.75V18.75C20.25 19.5784 19.5784 20.25 18.75 20.25H5.25C4.42157 20.25 3.75 19.5784 3.75 18.75V7.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php elseif ($kpi_icon === 'briefcase') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M5.25 9.5H18.75C19.9926 9.5 21 10.5074 21 11.75V16C21 17.2426 19.9926 18.25 18.75 18.25H5.25C4.00736 18.25 3 17.2426 3 16V11.75C3 10.5074 4.00736 9.5 5.25 9.5Z" stroke="currentColor" stroke-width="1.5" />
											<path d="M7 9.5V7.75C7 6.64543 7.89543 5.75 9 5.75H15C16.1046 5.75 17 6.64543 17 7.75V9.5M7.5 13H7.51M16.5 13H16.51" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
										</svg>
									<?php elseif ($kpi_icon === 'mail') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M4.5 6.75H19.5C20.3284 6.75 21 7.42157 21 8.25V15.75C21 16.5784 20.3284 17.25 19.5 17.25H4.5C3.67157 17.25 3 16.5784 3 15.75V8.25C3 7.42157 3.67157 6.75 4.5 6.75Z" stroke="currentColor" stroke-width="1.5" />
											<path d="M4 8L10.9393 12.6262C11.5704 13.0469 12.4296 13.0469 13.0607 12.6262L20 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php elseif ($kpi_icon === 'heart') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M12 20.25C12 20.25 4.5 15.75 4.5 10.5C4.5 8.42893 6.17893 6.75 8.25 6.75C9.52065 6.75 10.6437 7.38082 11.3228 8.34727C11.6222 8.77356 12.3778 8.77356 12.6772 8.34727C13.3563 7.38082 14.4794 6.75 15.75 6.75C17.8211 6.75 19.5 8.42893 19.5 10.5C19.5 15.75 12 20.25 12 20.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php elseif ($kpi_icon === 'car') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M4 14.25L5.8 9.75C6.09 9.02 6.79 8.55 7.58 8.55H16.42C17.21 8.55 17.91 9.02 18.2 9.75L20 14.25M5.25 14.25H18.75C19.44 14.25 20 14.81 20 15.5V17.25C20 17.66 19.66 18 19.25 18H18.5M5.5 18H4.75C4.34 18 4 17.66 4 17.25V15.5C4 14.81 4.56 14.25 5.25 14.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
											<circle cx="7.5" cy="16.5" r="1" fill="currentColor" />
											<circle cx="16.5" cy="16.5" r="1" fill="currentColor" />
										</svg>
									<?php elseif ($kpi_icon === 'chart') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M4 19H20M7 16V10M12 16V5M17 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php elseif ($kpi_icon === 'wallet') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M19.5 9.5V17.5C19.5 18.6046 18.6046 19.5 17.5 19.5H6.5C5.39543 19.5 4.5 18.6046 4.5 17.5V6.5C4.5 5.39543 5.39543 4.5 6.5 4.5H16.5C17.6046 4.5 18.5 5.39543 18.5 6.5V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
											<path d="M21 9.5V14.5C21 15.0523 20.5523 15.5 20 15.5H18C16.8954 15.5 16 14.6046 16 13.5V10.5C16 9.39543 16.8954 8.5 18 8.5H20C20.5523 8.5 21 8.94772 21 9.5Z" stroke="currentColor" stroke-width="1.5" />
											<circle cx="18.5" cy="12" r="0.5" fill="currentColor" />
										</svg>
									<?php elseif ($kpi_icon === 'clock') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
											<path d="M12 7V12L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php elseif ($kpi_icon === 'check-circle') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="currentColor" stroke-width="1.5" />
											<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php else : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M4 19H20M7 16V10M12 16V5M17 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php endif; ?>
								</div>
								<div class="mhm-rentiva-dashboard__kpi-label"><?php echo esc_html($kpi_label); ?></div>
							</div>
							<div class="mhm-rentiva-dashboard__kpi-value" data-count="<?php echo esc_attr((string) $kpi_value); ?>">
								<?php echo esc_html((string) $kpi_value); ?>
							</div>
							<?php if ($with_trend && null !== $kpi_trend_value) : ?>
								<div class="mhm-rentiva-dashboard__kpi-context">
									<span class="mhm-rentiva-dashboard__kpi-meta"><?php echo esc_html((string) ($kpi_config['trend_meta'] ?? $kpi_meta)); ?></span>
									<span class="mhm-rentiva-dashboard__kpi-trend is-<?php echo esc_attr($kpi_trend_direction); ?>">
										<?php echo esc_html((string) $kpi_trend_value . '%'); ?>
									</span>
								</div>
							<?php else : ?>
								<div class="mhm-rentiva-dashboard__kpi-meta"><?php echo esc_html($kpi_meta); ?></div>
							<?php endif; ?>
						</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php
				$financial_metrics = array('available_balance', 'pending_balance', 'total_paid_out');
				$has_financials = false;
				foreach ($financial_metrics as $fm) {
					if (isset($kpi_items[$fm])) {
						$has_financials = true;
						break;
					}
				}
		?>

		<?php if ($has_financials && $context === 'vendor') : ?>
			<div class="mhm-rentiva-dashboard__section">
				<div class="mhm-rentiva-dashboard__section-head">
					<h3><?php esc_html_e('Financial Summary', 'mhm-rentiva'); ?></h3>
				</div>
				<div class="mhm-rentiva-dashboard__kpis mhm-financial-cards">
					<?php foreach ($financial_metrics as $fm_key) : ?>
						<?php if (! isset($kpi_items[$fm_key])) {
							continue;
						} ?>
						<?php
						$fkpi = $kpi_items[$fm_key];
						$fkpi_label = (string) ($fkpi['label'] ?? '');
						$fkpi_meta = (string) ($fkpi['meta'] ?? '');
						$fkpi_icon = sanitize_key((string) ($fkpi['icon'] ?? 'wallet'));
						$fkpi_item = is_array($kpi_data[$fm_key] ?? null) ? $kpi_data[$fm_key] : array();
						// Format Currency Value Native
						$fkpi_value = isset($fkpi_item['total']) ? round((float) $fkpi_item['total'], 2) : 0.00;
						$fkpi_display = function_exists('wc_price') ? wc_price($fkpi_value) : number_format($fkpi_value, 2) . ' ' . get_woocommerce_currency();
						?>
						<div class="mhm-rentiva-dashboard__kpi-card is-financial">
							<div class="mhm-rentiva-dashboard__kpi-header">
								<div class="mhm-rentiva-dashboard__kpi-icon" aria-hidden="true">
									<?php if ($fkpi_icon === 'wallet') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M19.5 9.5V17.5C19.5 18.6046 18.6046 19.5 17.5 19.5H6.5C5.39543 19.5 4.5 18.6046 4.5 17.5V6.5C4.5 5.39543 5.39543 4.5 6.5 4.5H16.5C17.6046 4.5 18.5 5.39543 18.5 6.5V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
											<path d="M21 9.5V14.5C21 15.0523 20.5523 15.5 20 15.5H18C16.8954 15.5 16 14.6046 16 13.5V10.5C16 9.39543 16.8954 8.5 18 8.5H20C20.5523 8.5 21 8.94772 21 9.5Z" stroke="currentColor" stroke-width="1.5" />
											<circle cx="18.5" cy="12" r="0.5" fill="currentColor" />
										</svg>
									<?php elseif ($fkpi_icon === 'clock') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
											<path d="M12 7V12L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php elseif ($fkpi_icon === 'check-circle') : ?>
										<svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
											<path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="currentColor" stroke-width="1.5" />
											<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										</svg>
									<?php endif; ?>
								</div>
								<div class="mhm-rentiva-dashboard__kpi-label"><?php echo esc_html($fkpi_label); ?></div>
							</div>
							<div class="mhm-rentiva-dashboard__kpi-value is-currency" data-raw="<?php echo esc_attr((string) $fkpi_value); ?>">
								<?php echo wp_kses_post($fkpi_display); ?>
							</div>
							<div class="mhm-rentiva-dashboard__kpi-meta"><?php echo esc_html($fkpi_meta); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="mhm-rentiva-dashboard__section">
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
								$booking_id = (int) ($booking->ID ?? 0);
								$vehicle_id = (int) get_post_meta($booking_id, '_mhm_vehicle_id', true);
								$vehicle_title = $vehicle_id > 0 ? get_the_title($vehicle_id) : __('N/A', 'mhm-rentiva');
								$pickup_date = (string) get_post_meta($booking_id, '_mhm_pickup_date', true);
								$booking_status = (string) get_post_meta($booking_id, '_mhm_status', true);
								$status = sanitize_key($booking_status);
								$status_class = 'mhm-rentiva-dashboard__status';
								$status_map = array(
									'completed'   => 'is-completed',
									'confirmed'   => 'is-confirmed',
									'in_progress' => 'is-progress',
									'pending'     => 'is-pending',
									'cancelled'   => 'is-cancelled',
									'refunded'    => 'is-refunded',
								);
								if (isset($status_map[$status])) {
									$status_class .= ' ' . $status_map[$status];
								}
								$status_label = $status !== '' ? ucwords(str_replace('_', ' ', $status)) : '-';
								?>
								<tr>
									<td>#<?php echo esc_html((string) $booking_id); ?></td>
									<td><?php echo esc_html((string) $vehicle_title); ?></td>
									<td><?php echo esc_html($pickup_date !== '' ? date_i18n(get_option('date_format'), strtotime($pickup_date)) : '-'); ?></td>
									<td>
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
	<?php elseif ($active_tab === 'bookings') : ?>
		<div class="mhm-rentiva-dashboard__tab-content">
			<?php echo do_shortcode((string) ($dashboard['bookings_tab_shortcode'] ?? '[rentiva_my_bookings hide_nav="1"]')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	<?php elseif ($active_tab === 'favorites') : ?>
		<div class="mhm-rentiva-dashboard__tab-content">
			<?php echo do_shortcode((string) ($dashboard['favorites_tab_shortcode'] ?? '[rentiva_my_favorites]')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	<?php elseif ($active_tab === 'messages') : ?>
		<div class="mhm-rentiva-dashboard__tab-content">
			<?php echo do_shortcode((string) ($dashboard['messages_tab_shortcode'] ?? '[rentiva_messages hide_nav="1"]')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	<?php elseif ($active_tab === 'listings' || $active_tab === 'revenue') : ?>
		<div class="mhm-rentiva-dashboard__tab-content">
			<?php if ($active_tab === 'revenue' && $context === 'vendor') : ?>
				<?php include MHM_RENTIVA_PLUGIN_PATH . 'templates/account/partials/vendor-analytics.php'; ?>
			<?php else : ?>
				<div class="mhm-rentiva-dashboard__section">
					<div class="mhm-rentiva-dashboard__section-head">
						<h3><?php echo esc_html((string) ($nav_items[$active_tab]['label'] ?? __('Overview', 'mhm-rentiva'))); ?></h3>
					</div>
					<p><?php esc_html_e('This section will be available in the next dashboard phase.', 'mhm-rentiva'); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
</main>
</div>