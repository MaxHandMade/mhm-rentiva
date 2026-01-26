<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransferAdmin {


	/**
	 * Register hooks
	 */
	public static function register(): void {
		// Form handlers
		add_action( 'admin_post_mhm_save_location', array( self::class, 'handle_save_location' ) );
		add_action( 'admin_post_mhm_delete_location', array( self::class, 'handle_delete_location' ) );
		add_action( 'admin_post_mhm_save_route', array( self::class, 'handle_save_route' ) );
		add_action( 'admin_post_mhm_delete_route', array( self::class, 'handle_delete_route' ) );
		add_action( 'admin_post_mhm_save_transfer_settings', array( self::class, 'handle_save_transfer_settings' ) );

		// Register Meta Box
		VehicleTransferMetaBox::register();

		// Register Integration
		Integration\TransferCartIntegration::register();
		Integration\TransferBookingHandler::register();
	}



	/**
	 * Get available location types
	 *
	 * @return array
	 */
	public static function get_location_types(): array {
		$default_types = array(
			'airport'     => __( 'Airport', 'mhm-rentiva' ),
			'hotel'       => __( 'Hotel', 'mhm-rentiva' ),
			'port'        => __( 'Port / Cruise', 'mhm-rentiva' ),
			'station'     => __( 'Train / Bus Station', 'mhm-rentiva' ),
			'city_center' => __( 'City Center', 'mhm-rentiva' ),
			'hospital'    => __( 'Hospital / Clinic', 'mhm-rentiva' ),
		);

		// Merge with custom types from database
		$custom_types_raw = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_transfer_custom_types', '' );

		// Handle String Input (Lines)
		if ( is_string( $custom_types_raw ) && ! empty( $custom_types_raw ) ) {
			$lines        = explode( "\n", $custom_types_raw );
			$custom_types = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				// Slug: sanitize title, Label: line
				$slug                  = sanitize_title( $line );
				$custom_types[ $slug ] = $line;
			}
			$default_types = array_merge( $default_types, $custom_types );
		}
		// Backward compatibility if it was saved as array mainly
		elseif ( is_array( $custom_types_raw ) && ! empty( $custom_types_raw ) ) {
			$default_types = array_merge( $default_types, $custom_types_raw );
		}

		return apply_filters( 'mhm_rentiva_location_types', $default_types );
	}

	/**
	 * Render Settings Page
	 */
	public static function render_settings_page(): void {
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'mhm-rentiva' ) . '</p></div>';
		}

		$deposit_type = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_transfer_deposit_type', 'full_payment' );
		$deposit_rate = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_transfer_deposit_rate', '20' );
		$custom_types = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_transfer_custom_types', '' );
		// If array (legacy), implode it for display
		if ( is_array( $custom_types ) ) {
			$custom_types = implode( "\n", $custom_types ); // Values only? Or keys? Assuming values for simplicity if array was ['slug'=>'Label']
			// Actually, if it was array, it might be key=>value.
			// For safety, let's just treat as string mostly.
			// If the user saves via this new form, it becomes string.
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Transfer Settings', 'mhm-rentiva' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mhm_save_transfer_settings">
				<?php wp_nonce_field( 'mhm_save_transfer_settings_nonce', 'mhm_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="mhm_transfer_deposit_type"><?php echo esc_html__( 'Payment Type', 'mhm-rentiva' ); ?></label></th>
						<td>
							<select name="mhm_transfer_deposit_type" id="mhm_transfer_deposit_type">
								<option value="full_payment" <?php selected( $deposit_type, 'full_payment' ); ?>><?php echo esc_html__( 'Full Payment Required', 'mhm-rentiva' ); ?></option>
								<option value="percentage" <?php selected( $deposit_type, 'percentage' ); ?>><?php echo esc_html__( 'Deposit (Percentage)', 'mhm-rentiva' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'Select how customers should pay for transfers.', 'mhm-rentiva' ); ?></p>
						</td>
					</tr>
					<tr id="deposit_rate_row" style="<?php echo ( 'percentage' === $deposit_type ) ? '' : 'display:none;'; ?>">
						<th scope="row"><label for="mhm_transfer_deposit_rate"><?php echo esc_html__( 'Deposit Rate (%)', 'mhm-rentiva' ); ?></label></th>
						<td>
							<input name="mhm_transfer_deposit_rate" type="number" id="mhm_transfer_deposit_rate" value="<?php echo esc_attr( $deposit_rate ); ?>" class="regular-text" min="1" max="100" step="1">
							<p class="description"><?php echo esc_html__( 'Percentage of total price to be paid as deposit.', 'mhm-rentiva' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mhm_transfer_custom_types"><?php echo esc_html__( 'Custom Location Types', 'mhm-rentiva' ); ?></label></th>
						<td>
							<textarea name="mhm_transfer_custom_types" id="mhm_transfer_custom_types" rows="5" class="large-text code"><?php echo esc_textarea( $custom_types ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Enter custom location types, one per line. (e.g. Stadium, Exhibition Center)', 'mhm-rentiva' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
			<script>
				jQuery(document).ready(function($) {
					$('#mhm_transfer_deposit_type').on('change', function() {
						if ($(this).val() === 'percentage') {
							$('#deposit_rate_row').show();
						} else {
							$('#deposit_rate_row').hide();
						}
					});
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Handle save transfer settings
	 */
	public static function handle_save_transfer_settings(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mhm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_nonce'] ) ), 'mhm_save_transfer_settings_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'mhm-rentiva' ) );
		}

		$settings                              = (array) get_option( 'mhm_rentiva_settings', array() );
		$settings['mhm_transfer_deposit_type'] = sanitize_text_field( $_POST['mhm_transfer_deposit_type'] );
		$settings['mhm_transfer_deposit_rate'] = intval( $_POST['mhm_transfer_deposit_rate'] );

		// Save Custom Types as Text
		if ( isset( $_POST['mhm_transfer_custom_types'] ) ) {
			$settings['mhm_transfer_custom_types'] = sanitize_textarea_field( wp_unslash( $_POST['mhm_transfer_custom_types'] ) );
		}

		update_option( 'mhm_rentiva_settings', $settings );

		wp_redirect( admin_url( 'admin.php?page=mhm-rentiva-settings&tab=transfer&updated=true' ) );
		exit;
	}

	// ... existing list render methods ...

	/**
	 * Render Locations Page
	 */
	public static function render_locations_page(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (prefix + constant).
		$locations     = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY priority ASC, name ASC" );
		$edit_location = null;

		if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['id'] ) ) {
			$edit_location = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", intval( $_GET['id'] ) ) );
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Transfer Locations', 'mhm-rentiva' ); ?></h1>
			<?php \MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button(); ?>
			<hr class="wp-header-end">

			<div id="col-container" class="wp-clearfix">
				<!-- Add/Edit Form -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php echo $edit_location ? esc_html__( 'Edit Location', 'mhm-rentiva' ) : esc_html__( 'Add New Location', 'mhm-rentiva' ); ?></h2>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="mhm_save_location">
								<?php wp_nonce_field( 'mhm_save_location_nonce', 'mhm_nonce' ); ?>
								<?php if ( $edit_location ) : ?>
									<input type="hidden" name="id" value="<?php echo esc_attr( $edit_location->id ); ?>">
								<?php endif; ?>

								<div class="form-field">
									<label for="name"><?php echo esc_html__( 'Name', 'mhm-rentiva' ); ?></label>
									<input name="name" id="name" type="text" value="<?php echo $edit_location ? esc_attr( $edit_location->name ) : ''; ?>" required>
								</div>

								<div class="form-field">
									<label for="type"><?php echo esc_html__( 'Type', 'mhm-rentiva' ); ?></label>
									<select name="type" id="type">
										<?php
										$types = self::get_location_types();

										foreach ( $types as $key => $label ) {
											echo '<option value="' . esc_attr( $key ) . '" ' . selected( $edit_location ? $edit_location->type : '', $key, false ) . '>' . esc_html( $label ) . '</option>';
										}
										?>
									</select>
								</div>

								<div class="form-field">
									<label for="priority"><?php echo esc_html__( 'Priority', 'mhm-rentiva' ); ?></label>
									<input name="priority" id="priority" type="number" value="<?php echo $edit_location ? esc_attr( $edit_location->priority ) : '0'; ?>">
								</div>

								<?php submit_button( $edit_location ? __( 'Update Location', 'mhm-rentiva' ) : __( 'Add Location', 'mhm-rentiva' ) ); ?>
								<?php if ( $edit_location ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-transfer-locations' ) ); ?>" class="button"><?php echo esc_html__( 'Cancel', 'mhm-rentiva' ); ?></a>
								<?php endif; ?>
							</form>
						</div>
					</div>
				</div>

				<!-- List Table -->
				<div id="col-right">
					<div class="col-wrap">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Name', 'mhm-rentiva' ); ?></th>
									<th><?php echo esc_html__( 'Type', 'mhm-rentiva' ); ?></th>
									<th><?php echo esc_html__( 'Priority', 'mhm-rentiva' ); ?></th>
									<th><?php echo esc_html__( 'Actions', 'mhm-rentiva' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $locations ) ) : ?>
									<?php foreach ( $locations as $location ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $location->name ); ?></strong></td>
											<td><?php echo esc_html( ucfirst( $location->type ) ); ?></td>
											<td><?php echo esc_html( $location->priority ); ?></td>
											<td>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-transfer-locations&action=edit&id=' . $location->id ) ); ?>"><?php echo esc_html__( 'Edit', 'mhm-rentiva' ); ?></a> |
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mhm_delete_location&id=' . $location->id ), 'mhm_delete_location_nonce' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'mhm-rentiva' ) ); ?>');" style="color: #a00;"><?php echo esc_html__( 'Delete', 'mhm-rentiva' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="4"><?php echo esc_html__( 'No locations found.', 'mhm-rentiva' ); ?></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Routes Page
	 */
	public static function render_routes_page(): void {
		global $wpdb;
		$table_routes    = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
		$table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe (prefix + constant).
		$routes = $wpdb->get_results(
			"
            SELECT r.*, 
                   l1.name as origin_name, 
                   l2.name as dest_name 
            FROM {$table_routes} r
            LEFT JOIN {$table_locations} l1 ON r.origin_id = l1.id
            LEFT JOIN {$table_locations} l2 ON r.destination_id = l2.id
            ORDER BY r.id DESC
        "
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safe (prefix + constant).

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (prefix + constant).
		$locations = $wpdb->get_results( "SELECT id, name FROM {$table_locations} ORDER BY name ASC" );

		$edit_route = null;
		if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['id'] ) ) {
			$edit_route = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_routes} WHERE id = %d", intval( $_GET['id'] ) ) );
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Transfer Routes', 'mhm-rentiva' ); ?></h1>
			<?php \MHMRentiva\Admin\Core\Utilities\UXHelper::render_docs_button(); ?>
			<hr class="wp-header-end">

			<div id="col-container" class="wp-clearfix">
				<!-- Add/Edit Form -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php echo $edit_route ? esc_html__( 'Edit Route', 'mhm-rentiva' ) : esc_html__( 'Add New Route', 'mhm-rentiva' ); ?></h2>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="mhm_save_route">
								<?php wp_nonce_field( 'mhm_save_route_nonce', 'mhm_nonce' ); ?>
								<?php if ( $edit_route ) : ?>
									<input type="hidden" name="id" value="<?php echo esc_attr( $edit_route->id ); ?>">
								<?php endif; ?>

								<!-- Origin -->
								<div class="form-field">
									<label for="origin_id"><?php echo esc_html__( 'Origin', 'mhm-rentiva' ); ?></label>
									<select name="origin_id" id="origin_id" required>
										<option value=""><?php echo esc_html__( 'Select Origin', 'mhm-rentiva' ); ?></option>
										<?php foreach ( $locations as $loc ) : ?>
											<option value="<?php echo esc_attr( $loc->id ); ?>" <?php selected( $edit_route ? $edit_route->origin_id : '', $loc->id ); ?>><?php echo esc_html( $loc->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Destination -->
								<div class="form-field">
									<label for="destination_id"><?php echo esc_html__( 'Destination', 'mhm-rentiva' ); ?></label>
									<select name="destination_id" id="destination_id" required>
										<option value=""><?php echo esc_html__( 'Select Destination', 'mhm-rentiva' ); ?></option>
										<?php foreach ( $locations as $loc ) : ?>
											<option value="<?php echo esc_attr( $loc->id ); ?>" <?php selected( $edit_route ? $edit_route->destination_id : '', $loc->id ); ?>><?php echo esc_html( $loc->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Distance & Duration -->
								<div class="form-field">
									<label for="distance_km"><?php echo esc_html__( 'Distance (KM)', 'mhm-rentiva' ); ?></label>
									<input name="distance_km" id="distance_km" type="number" step="0.1" value="<?php echo $edit_route ? esc_attr( $edit_route->distance_km ) : ''; ?>" required>
								</div>
								<div class="form-field">
									<label for="duration_min"><?php echo esc_html__( 'Duration (Minutes)', 'mhm-rentiva' ); ?></label>
									<input name="duration_min" id="duration_min" type="number" value="<?php echo $edit_route ? esc_attr( $edit_route->duration_min ) : ''; ?>" required>
								</div>

								<!-- Pricing Method -->
								<div class="form-field">
									<label for="pricing_method"><?php echo esc_html__( 'Pricing Method', 'mhm-rentiva' ); ?></label>
									<select name="pricing_method" id="pricing_method" onchange="togglePricingFields(this.value)">
										<option value="fixed" <?php selected( $edit_route ? $edit_route->pricing_method : '', 'fixed' ); ?>><?php echo esc_html__( 'Fixed Price', 'mhm-rentiva' ); ?></option>
										<option value="calculated" <?php selected( $edit_route ? $edit_route->pricing_method : '', 'calculated' ); ?>><?php echo esc_html__( 'Distance Based (KM)', 'mhm-rentiva' ); ?></option>
									</select>
								</div>

								<!-- Pricing Fields -->
								<div class="form-field">
									<label for="base_price"><span id="base_price_label"><?php echo esc_html__( 'Price', 'mhm-rentiva' ); ?></span></label>
									<input name="base_price" id="base_price" type="number" step="0.01" value="<?php echo $edit_route ? esc_attr( $edit_route->base_price ) : ''; ?>" required>
								</div>

								<div class="form-field" id="min_price_field" style="<?php echo ( $edit_route && 'calculated' === $edit_route->pricing_method ) ? '' : 'display:none;'; ?>">
									<label for="min_price"><?php echo esc_html__( 'Minimum Price', 'mhm-rentiva' ); ?></label>
									<input name="min_price" id="min_price" type="number" step="0.01" value="<?php echo $edit_route ? esc_attr( $edit_route->min_price ) : ''; ?>">
								</div>

								<?php submit_button( $edit_route ? __( 'Update Route', 'mhm-rentiva' ) : __( 'Add Route', 'mhm-rentiva' ) ); ?>
								<?php if ( $edit_route ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-transfer-routes' ) ); ?>" class="button"><?php echo esc_html__( 'Cancel', 'mhm-rentiva' ); ?></a>
								<?php endif; ?>
							</form>
						</div>
					</div>
				</div>

				<!-- List Table -->
				<div id="col-right">
					<div class="col-wrap">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Route', 'mhm-rentiva' ); ?></th>
									<th><?php echo esc_html__( 'Distance/Time', 'mhm-rentiva' ); ?></th>
									<th><?php echo esc_html__( 'Pricing', 'mhm-rentiva' ); ?></th>
									<th><?php echo esc_html__( 'Actions', 'mhm-rentiva' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $routes ) ) : ?>
									<?php foreach ( $routes as $route ) : ?>
										<tr>
											<td>
												<?php echo esc_html( $route->origin_name ); ?> &rarr; <?php echo esc_html( $route->dest_name ); ?>
											</td>
											<td>
												<?php echo esc_html( $route->distance_km ); ?> km <br>
												<small><?php echo esc_html( $route->duration_min ); ?> min</small>
											</td>
											<td>
												<?php if ( 'fixed' === $route->pricing_method ) : ?>
													<span class="badge badge-primary">Fixed</span>
													<strong><?php echo wp_kses_post( wc_price( $route->base_price ) ); ?></strong>
												<?php else : ?>
													<span class="badge badge-secondary">KM</span>
													<?php echo wp_kses_post( wc_price( $route->base_price ) ); ?> / km <br>
													Min: <?php echo wp_kses_post( wc_price( $route->min_price ) ); ?>
												<?php endif; ?>
											</td>
											<td>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-transfer-routes&action=edit&id=' . $route->id ) ); ?>"><?php echo esc_html__( 'Edit', 'mhm-rentiva' ); ?></a> |
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mhm_delete_route&id=' . $route->id ), 'mhm_delete_route_nonce' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'mhm-rentiva' ) ); ?>');" style="color: #a00;"><?php echo esc_html__( 'Delete', 'mhm-rentiva' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="4"><?php echo esc_html__( 'No routes found.', 'mhm-rentiva' ); ?></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<script>
				function togglePricingFields(method) {
					const label = document.getElementById('base_price_label');
					const minField = document.getElementById('min_price_field');
					if (method === 'calculated') {
						label.textContent = '<?php echo esc_js( __( 'Price per KM', 'mhm-rentiva' ) ); ?>';
						minField.style.display = 'block';
					} else {
						label.textContent = '<?php echo esc_js( __( 'Total Price', 'mhm-rentiva' ) ); ?>';
						minField.style.display = 'none';
					}
				}
			</script>
		</div>
		<?php
	}

	// --- FORM HANDLERS ---

	public static function handle_save_location(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mhm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_nonce'] ) ), 'mhm_save_location_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'mhm-rentiva' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';

		$data = array(
			'name'     => sanitize_text_field( $_POST['name'] ),
			'type'     => sanitize_text_field( $_POST['type'] ),
			'priority' => intval( $_POST['priority'] ),
		);

		if ( ! empty( $_POST['id'] ) ) {
			$wpdb->update( $table_name, $data, array( 'id' => intval( $_POST['id'] ) ) );
		} else {
			$wpdb->insert( $table_name, $data );
		}

		wp_redirect( admin_url( 'admin.php?page=mhm-rentiva-transfer-locations&updated=true' ) );
		exit;
	}

	public static function handle_delete_location(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mhm_delete_location_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'mhm-rentiva' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		$wpdb->delete( $table_name, array( 'id' => intval( $_GET['id'] ) ) );

		wp_redirect( admin_url( 'admin.php?page=mhm-rentiva-transfer-locations&deleted=true' ) );
		exit;
	}

	public static function handle_save_route(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mhm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhm_nonce'] ) ), 'mhm_save_route_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'mhm-rentiva' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_routes';

		$data = array(
			'origin_id'      => intval( $_POST['origin_id'] ),
			'destination_id' => intval( $_POST['destination_id'] ),
			'distance_km'    => floatval( $_POST['distance_km'] ),
			'duration_min'   => intval( $_POST['duration_min'] ),
			'pricing_method' => sanitize_text_field( $_POST['pricing_method'] ),
			'base_price'     => floatval( $_POST['base_price'] ),
			'min_price'      => floatval( $_POST['min_price'] ),
		);

		if ( ! empty( $_POST['id'] ) ) {
			$wpdb->update( $table_name, $data, array( 'id' => intval( $_POST['id'] ) ) );
		} else {
			$wpdb->insert( $table_name, $data );
		}

		wp_redirect( admin_url( 'admin.php?page=mhm-rentiva-transfer-routes&updated=true' ) );
		exit;
	}

	public static function handle_delete_route(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mhm_delete_route_nonce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'mhm-rentiva' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
		$wpdb->delete( $table_name, array( 'id' => intval( $_GET['id'] ) ) );

		wp_redirect( admin_url( 'admin.php?page=mhm-rentiva-transfer-routes&deleted=true' ) );
		exit;
	}
}
