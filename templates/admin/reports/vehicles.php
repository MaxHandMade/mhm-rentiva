<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mhm-rentiva-vehicle-report">
	<div class="overview-header">
		<h2><?php echo esc_html__( 'Vehicle Report', 'mhm-rentiva' ); ?></h2>
		<p class="overview-description">
			<?php
			printf(
				/* translators: 1: %s; 2: %s. */
				esc_html__( 'Vehicle performance and rental analysis for %1$s to %2$s', 'mhm-rentiva' ),
				esc_html( wp_date( 'd.m.Y', strtotime( $start_date ) ) ),
				esc_html( wp_date( 'd.m.Y', strtotime( $end_date ) ) )
			);
			?>
		</p>
	</div>

	<div class="overview-cards-grid">
		<!-- Vehicle Performance Card -->
		<div class="analytics-card vehicles-analytics">
			<div class="card-header">
				<h3><?php echo esc_html__( 'Vehicle Performance', 'mhm-rentiva' ); ?></h3>
				<span class="card-icon">ğŸš—</span>
			</div>
			<div class="card-content">
				<div class="analytics-chart">
					<div class="chart-bars">
						<?php
						$vehicle_categories = array(
							'hatchback' => 0,
							'sedan'     => 0,
							'suv'       => 0,
							'coupe'     => 0,
						);

						if ( ! empty( $vehicle_categories_data ) ) {
							foreach ( $vehicle_categories_data as $category ) {
								$category_name = strtolower( $category->category_name );
								$booking_count = (int) $category->booking_count;

								switch ( $category_name ) {
									case 'hatchback':
										$vehicle_categories['hatchback'] = $booking_count;
										break;
									case 'sedan':
										$vehicle_categories['sedan'] = $booking_count;
										break;
									case 'suv':
										$vehicle_categories['suv'] = $booking_count;
										break;
									case 'coupe':
										$vehicle_categories['coupe'] = $booking_count;
										break;
								}
							}
						}

						$max_vehicles = max( $vehicle_categories ) ?: 1;

						foreach ( $vehicle_categories as $category => $count ) {
							$bar_height   = $max_vehicles > 0 ? ( $count / $max_vehicles ) * 100 : 0;
							$min_height   = $count > 0 ? 20 : 5;
							$final_height = max( $bar_height, $min_height );
							echo '<div class="chart-bar ' . esc_attr( $category ) . '" style="height: ' . esc_attr( (string) $final_height ) . '%;">';
							echo '<div class="bar-value">' . esc_html( (string) $count ) . '</div>';
							echo '</div>';
						}
						?>
					</div>
					<div class="chart-labels">
						<span>Hatchback</span><span>Sedan</span><span>SUV</span><span>Coupe</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Top Vehicles Card -->
		<div class="analytics-card customers-analytics">
			<div class="card-header">
				<h3><?php echo esc_html__( 'Top Vehicles', 'mhm-rentiva' ); ?></h3>
				<span class="card-icon">ğŸ†</span>
			</div>
			<div class="card-content">
				<div class="analytics-metrics">
					<?php
					$top_vehicles  = $data['top_vehicles'] ?? array();
					$display_count = 0;

					foreach ( $top_vehicles as $vehicle ) {
						if ( $display_count >= 3 ) {
							break;
						}
						?>
						<div class="metric-row">
							<span class="metric-label"><?php echo esc_html( $vehicle->vehicle_title ?? 'Unknown' ); ?></span>
							<span class="metric-value"><?php echo number_format( (int) ( $vehicle->booking_count ?? 0 ) ); ?> rentals</span>
						</div>
						<?php
						++$display_count;
					}
					?>
				</div>
			</div>
		</div>
	</div>

	<!-- Detail table -->
	<div class="data-table-container">
		<h3><?php echo esc_html__( 'Most Rented Vehicles', 'mhm-rentiva' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Vehicle', 'mhm-rentiva' ); ?></th>
					<th><?php echo esc_html__( 'Rental Count', 'mhm-rentiva' ); ?></th>
					<th><?php echo esc_html__( 'Total Revenue', 'mhm-rentiva' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['top_vehicles'] as $vehicle ) : ?>
					<tr>
						<td><?php echo esc_html( $vehicle->vehicle_title ); ?></td>
						<td><?php echo number_format( (int) $vehicle->booking_count ); ?></td>
						<td><?php echo esc_html( number_format( (float) $vehicle->total_revenue, 0, ',', '.' ) . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol() ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>