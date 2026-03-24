<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Deposit Statistics Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$deposit_stats   = $args['deposit_stats'] ?? array();
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();

function format_dashboard_price( float $price, string $symbol ): string {
	return number_format( $price, 2, '.', ',' ) . ' ' . $symbol;
}
?>

<div class="mhm-dashboard-widget">
	<h3><?php echo esc_html__( 'Deposit Statistics', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<div class="stats-grid">
			<!-- Deposit Bookings -->
			<div class="stat-card stat-card-deposit-bookings">
				<div class="stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="stat-content">
					<div class="stat-number"><?php echo esc_html( $deposit_stats['deposit_bookings'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Deposit Bookings', 'mhm-rentiva' ); ?></div>
					<div class="stat-trend">
						<span class="trend-text <?php echo ( $deposit_stats['deposit_trend'] ?? 0 ) >= 0 ? 'positive' : 'negative'; ?>">
							<?php echo ( $deposit_stats['deposit_trend'] ?? 0 ) >= 0 ? '+' : ''; ?><?php echo esc_html( $deposit_stats['deposit_trend'] ?? 0 ); ?>% <?php echo esc_html__( 'this month', 'mhm-rentiva' ); ?>
						</span>
					</div>
				</div>
			</div>

			<!-- Pending Deposits -->
			<div class="stat-card stat-card-pending-deposits">
				<div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>
				<div class="stat-content">
					<div class="stat-number"><?php echo esc_html( $deposit_stats['pending_deposits'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Pending Deposits', 'mhm-rentiva' ); ?></div>
					<div class="stat-trend">
						<span class="trend-text"><?php echo esc_html( format_dashboard_price( (float) ( $deposit_stats['pending_deposit_amount'] ?? 0 ), $currency_symbol ) ); ?> <?php echo esc_html__( 'total', 'mhm-rentiva' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Completed Deposits -->
			<div class="stat-card stat-card-completed-deposits">
				<div class="stat-icon"><span class="dashicons dashicons-yes"></span></div>
				<div class="stat-content">
					<div class="stat-number"><?php echo esc_html( $deposit_stats['completed_deposits'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Completed Deposits', 'mhm-rentiva' ); ?></div>
					<div class="stat-trend">
						<span class="trend-text"><?php echo esc_html( format_dashboard_price( (float) ( $deposit_stats['completed_deposit_amount'] ?? 0 ), $currency_symbol ) ); ?> <?php echo esc_html__( 'total', 'mhm-rentiva' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle_booking&mhm_payment_type=deposit' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'Deposit Bookings', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>