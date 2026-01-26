<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mhm-rentiva-revenue-report">
	<div class="overview-header">
		<h2><?php echo esc_html__( 'Revenue Report', 'mhm-rentiva' ); ?></h2>
		<p class="overview-description">
			<?php
			printf(
				/* translators: 1: %s; 2: %s. */
				esc_html__( 'Revenue analysis and trends for %1$s to %2$s', 'mhm-rentiva' ),
				esc_html( wp_date( 'd.m.Y', strtotime( $start_date ) ) ),
				esc_html( wp_date( 'd.m.Y', strtotime( $end_date ) ) )
			);
			?>
		</p>
	</div>

	<div class="overview-cards-grid">
		<!-- Total Revenue Card -->
		<div class="analytics-card revenue-analytics">
			<div class="card-header">
				<h3><?php echo esc_html__( 'Total Revenue', 'mhm-rentiva' ); ?></h3>
				<span class="card-icon">💰</span>
			</div>
			<div class="card-content">
				<div class="analytics-metrics">
					<div class="metric-row">
						<span class="metric-label">Total Revenue</span>
						<span class="metric-value"><?php echo esc_html( number_format( (float) ( $data['total'] ?? 0 ), 0, ',', '.' ) . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol() ); ?></span>
					</div>
					<div class="metric-row">
						<span class="metric-label">Daily Average</span>
						<span class="metric-value"><?php echo esc_html( number_format( (float) ( ( $data['total'] ?? 0 ) / max( 1, count( $data['daily'] ?? array() ) ) ), 0, ',', '.' ) . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol() ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Revenue Trend Card -->
		<div class="analytics-card bookings-analytics">
			<div class="card-header">
				<h3><?php echo esc_html__( 'Revenue Trend', 'mhm-rentiva' ); ?></h3>
				<span class="card-icon">📈</span>
			</div>
			<div class="card-content">
				<div class="analytics-chart">
					<div class="chart-bars">
						<?php
						$daily_revenue = $data['daily'] ?? array();
						$max_revenue   = 1;

						if ( ! empty( $daily_revenue ) ) {
							$revenues = array();
							foreach ( $daily_revenue as $item ) {
								if ( is_object( $item ) && isset( $item->revenue ) ) {
									$revenues[] = (float) $item->revenue;
								} elseif ( is_array( $item ) && isset( $item['revenue'] ) ) {
									$revenues[] = (float) $item['revenue'];
								}
							}
							if ( ! empty( $revenues ) ) {
								$max_revenue = max( $revenues );
							}
						}

						for ( $i = 0; $i < 7; $i++ ) {
							$day_revenue = 0;

							if ( ! empty( $daily_revenue ) && isset( $daily_revenue[ $i ] ) ) {
								$item = $daily_revenue[ $i ];
								if ( is_object( $item ) && isset( $item->revenue ) ) {
									$day_revenue = (float) $item->revenue;
								} elseif ( is_array( $item ) && isset( $item['revenue'] ) ) {
									$day_revenue = (float) $item['revenue'];
								}
							}

							$bar_height   = $max_revenue > 0 ? ( $day_revenue / $max_revenue ) * 100 : 0;
							$min_height   = $day_revenue > 0 ? 20 : 5;
							$final_height = max( $bar_height, $min_height );
							echo '<div class="chart-bar" style="height: ' . esc_attr( (string) $final_height ) . '%;">';
							echo '<div class="bar-value">' . esc_html( number_format( $day_revenue, 0, ',', '.' ) ) . '</div>';
							echo '</div>';
						}
						?>
					</div>
					<div class="chart-labels">
						<span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Detail table -->
	<div class="data-table-container">
		<h3><?php echo esc_html__( 'Daily Revenue Details', 'mhm-rentiva' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Date', 'mhm-rentiva' ); ?></th>
					<th><?php echo esc_html__( 'Revenue', 'mhm-rentiva' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['daily'] as $day ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $day->date ) ) ); ?></td>
						<td><?php echo esc_html( number_format( (float) $day->revenue, 2, ',', '.' ) . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol() ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>