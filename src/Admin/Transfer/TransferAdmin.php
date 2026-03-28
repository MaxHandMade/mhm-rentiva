<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.






use MHMRentiva\Admin\Transfer\VehicleTransferMetaBox;
use MHMRentiva\Admin\Transfer\Integration\TransferCartIntegration;
use MHMRentiva\Admin\Transfer\Integration\TransferBookingHandler;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Core\Utilities\CityHelper;
use MHMRentiva\Admin\Core\Utilities\UXHelper;



/**
 * Transfer Admin
 *
 * @method void render_admin_header(string $title, array $buttons = array(), bool $echo = true, string $subtitle = '')
 * @method void render_developer_mode_banner(array $features = array())
 * @method void show_admin_notice(string $message, string $type = 'info', bool $dismissible = true)
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Transfer admin analytics/search operations require controlled aggregate SQL and meta/tax filters.
final class TransferAdmin
{

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

	/**
	 * Sanitize table identifier for safe use with %i placeholders.
	 */
	private static function sanitize_table_identifier(string $table_name): string
	{
		return preg_replace('/[^A-Za-z0-9_]/', '', $table_name) ?? '';
	}

	/**
	 * Resolve current table name with fallback to legacy prefix.
	 */
	private static function resolve_table_name(string $new_suffix, string $legacy_suffix): string
	{
		global $wpdb;

		$new_table = self::sanitize_table_identifier($wpdb->prefix . $new_suffix);
		if ($new_table !== '') {
			$new_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));
			if ($new_exists === $new_table) {
				return $new_table;
			}
		}

		return self::sanitize_table_identifier($wpdb->prefix . $legacy_suffix);
	}


	/**
	 * Register hooks
	 */
	public static function register(): void
	{

		// Limit notice rendered inline in render_routes_page() — after KPI cards

		// Assets
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_assets'));

		// Form handlers
		add_action('admin_post_mhm_save_location', array(self::class, 'handle_save_location'));
		add_action('admin_post_mhm_delete_location', array(self::class, 'handle_delete_location'));
		add_action('admin_post_mhm_save_route', array(self::class, 'handle_save_route'));
		add_action('admin_post_mhm_delete_route', array(self::class, 'handle_delete_route'));
		add_action('admin_post_mhm_save_transfer_settings', array(self::class, 'handle_save_transfer_settings'));

		// Register Meta Box
		VehicleTransferMetaBox::register();

		// Register Integration
		Integration\TransferCartIntegration::register();
		Integration\TransferBookingHandler::register();
	}

	/**
	 * Show transfer route limit notice for Lite users
	 */
	public static function routeLimitNotice(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page query check for displaying admin notice.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( $page !== 'mhm-rentiva-transfer-routes' ) {
			return;
		}

		\MHMRentiva\Admin\Core\ProFeatureNotice::displayLimitNotice( 'routes' );
	}

	/**
	 * Enqueue assets
	 */
	public static function enqueue_assets(string $hook): void
	{
		if (strpos($hook, 'mhm-rentiva-transfer') === false) {
			return;
		}

		wp_enqueue_style('mhm-css-variables', MHM_RENTIVA_PLUGIN_URL . 'assets/css/core/css-variables.css', array(), MHM_RENTIVA_VERSION);
		wp_enqueue_style('mhm-stats-cards', MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css', array('mhm-css-variables'), MHM_RENTIVA_VERSION);
		wp_enqueue_script('wc-enhanced-select'); // WC Select2 for admin
	}

	/**
	 * Render Transfer Stats
	 */
	protected function render_transfer_stats(): void
	{
		global $wpdb;

		$table_locations = self::resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');
		$table_routes    = self::resolve_table_name('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');

		// 1. Total Locations
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_table_name.
		$total_locations = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_locations}");

		// 2. Active Routes
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_table_name.
		$total_routes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_routes}");

		// 3. Latest Operation (Last booking of type transfer)
		$latest_transfer_date = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT p.post_date 
				FROM {$wpdb->posts} p 
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
				WHERE p.post_type = %s 
				  AND pm.meta_key = %s 
				  AND pm.meta_value = %s 
				ORDER BY p.post_date DESC LIMIT 1
				",
				'vehicle_booking',
				'mhm_booking_type',
				'transfer'
			)
		);
		$latest_text          = $latest_transfer_date ? date_i18n(get_option('date_format'), strtotime($latest_transfer_date)) : __('No transfers yet', 'mhm-rentiva');

?>
		<div class="mhm-stats-cards" style="margin-bottom: 20px;">
			<div class="stats-grid">
				<!-- Total Locations -->
				<div class="stat-card stat-card-total-bookings">
					<div class="stat-icon">
						<span class="dashicons dashicons-location-alt"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html($total_locations); ?></div>
						<div class="stat-label"><?php esc_html_e('Total Locations', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php esc_html_e('Active pickup/dropoff points', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Active Routes -->
				<div class="stat-card stat-card-total-vehicles">
					<div class="stat-icon">
						<span class="dashicons dashicons-networking"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html($total_routes); ?></div>
						<div class="stat-label"><?php esc_html_e('Total Routes', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php esc_html_e('Defined transfer paths', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>

				<!-- Latest Operation -->
				<div class="stat-card stat-card-total-customers">
					<div class="stat-icon">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="stat-content">
						<div class="stat-number" style="font-size: 1.2em;"><?php echo esc_html($latest_text); ?></div>
						<div class="stat-label"><?php esc_html_e('Latest Operation', 'mhm-rentiva'); ?></div>
						<div class="stat-trend">
							<span class="trend-text"><?php esc_html_e('Last transfer booking', 'mhm-rentiva'); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php
	}



	/**
	 * Get available location types
	 *
	 * @return array
	 */
	public static function get_location_types(): array
	{
		$default_types = array(
			'airport'     => __('Airport', 'mhm-rentiva'),
			'hotel'       => __('Hotel', 'mhm-rentiva'),
			'port'        => __('Port / Cruise', 'mhm-rentiva'),
			'station'     => __('Train / Bus Station', 'mhm-rentiva'),
			'city_center' => __('City Center', 'mhm-rentiva'),
			'hospital'    => __('Hospital / Clinic', 'mhm-rentiva'),
		);

		// Merge with custom types from database
		$custom_types_raw = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_custom_types', '');
		// Fallback
		if (empty($custom_types_raw)) {
			$custom_types_raw = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_transfer_custom_types', '');
		}

		// Handle String Input (Lines)
		if (is_string($custom_types_raw) && ! empty($custom_types_raw)) {
			$lines        = explode("\n", $custom_types_raw);
			$custom_types = array();
			foreach ($lines as $line) {
				$line = trim($line);
				if (empty($line)) {
					continue;
				}
				// Slug: sanitize title, Label: line
				$slug                  = sanitize_title($line);
				$custom_types[$slug] = $line;
			}
			$default_types = array_merge($default_types, $custom_types);
		}
		// Backward compatibility if it was saved as array mainly
		elseif (is_array($custom_types_raw) && ! empty($custom_types_raw)) {
			$default_types = array_merge($default_types, $custom_types_raw);
		}

		return apply_filters('mhm_rentiva_location_types', $default_types);
	}

	/**
	 * Render Settings Page
	 */
	public function render_settings_page(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI notice flag.
		if (isset($_GET['updated'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'mhm-rentiva') . '</p></div>';
		}

		$deposit_type = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_deposit_type', 'full_payment');
		// Check fallback if default
		if ($deposit_type === 'full_payment' && ! \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_deposit_type')) {
			$old = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_transfer_deposit_type');
			if ($old) {
				$deposit_type = $old;
			}
		}

		$deposit_rate = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_deposit_rate', '20');
		// Check fallback
		if ($deposit_rate === '20' && ! \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_deposit_rate')) {
			$old = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_transfer_deposit_rate');
			if ($old) {
				$deposit_rate = $old;
			}
		}

		$custom_types = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_custom_types', '');
		if ($custom_types === '' && ! \MHMRentiva\Admin\Settings\Core\SettingsCore::get('rentiva_transfer_custom_types')) {
			$old = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_transfer_custom_types');
			if ($old) {
				$custom_types = $old;
			}
		}

		// If array (legacy), implode it for display
		if (is_array($custom_types)) {
			$custom_types = implode("\n", $custom_types); // Values only? Or keys? Assuming values for simplicity if array was ['slug'=>'Label']
		}
	?>
		<?php
		$buttons = array(
			array(
				'type' => 'documentation',
				'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
			),
		);

		echo '<div class="wrap mhm-transfer-settings-wrap">';

		$this->render_admin_header(__('Transfer Settings', 'mhm-rentiva'), $buttons);

		$this->render_developer_mode_banner();
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="mhm_save_transfer_settings">
			<?php wp_nonce_field('mhm_save_transfer_settings_nonce', 'mhm_nonce'); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="rentiva_transfer_deposit_type"><?php echo esc_html__('Payment Type', 'mhm-rentiva'); ?></label></th>
					<td>
						<select name="rentiva_transfer_deposit_type" id="rentiva_transfer_deposit_type">
							<option value="full_payment" <?php selected($deposit_type, 'full_payment'); ?>><?php echo esc_html__('Full Payment Required', 'mhm-rentiva'); ?></option>
							<option value="percentage" <?php selected($deposit_type, 'percentage'); ?>><?php echo esc_html__('Deposit (Percentage)', 'mhm-rentiva'); ?></option>
						</select>
						<p class="description"><?php echo esc_html__('Select how customers should pay for transfers.', 'mhm-rentiva'); ?></p>
					</td>
				</tr>
				<tr id="deposit_rate_row" style="<?php echo ('percentage' === $deposit_type) ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="rentiva_transfer_deposit_rate"><?php echo esc_html__('Deposit Rate (%)', 'mhm-rentiva'); ?></label></th>
					<td>
						<input name="rentiva_transfer_deposit_rate" type="number" id="rentiva_transfer_deposit_rate" value="<?php echo esc_attr($deposit_rate); ?>" class="regular-text" min="1" max="100" step="1">
						<p class="description"><?php echo esc_html__('Percentage of total price to be paid as deposit.', 'mhm-rentiva'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rentiva_transfer_custom_types"><?php echo esc_html__('Custom Location Types', 'mhm-rentiva'); ?></label></th>
					<td>
						<textarea name="rentiva_transfer_custom_types" id="rentiva_transfer_custom_types" rows="5" class="large-text code"><?php echo esc_textarea($custom_types); ?></textarea>
						<p class="description"><?php echo esc_html__('Enter custom location types, one per line. (e.g. Stadium, Exhibition Center)', 'mhm-rentiva'); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<script>
			jQuery(document).ready(function($) {
				$('#rentiva_transfer_deposit_type').on('change', function() {
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
	public static function handle_save_transfer_settings(): void
	{
		if (! current_user_can('manage_options') || ! isset($_POST['mhm_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_nonce'])), 'mhm_save_transfer_settings_nonce')) {
			wp_die(esc_html__('Unauthorized', 'mhm-rentiva'));
		}

		$settings = (array) get_option('mhm_rentiva_settings', array());

		// Save to new keys
		$settings['rentiva_transfer_deposit_type'] = isset($_POST['rentiva_transfer_deposit_type']) ? sanitize_text_field(wp_unslash($_POST['rentiva_transfer_deposit_type'])) : '';
		$settings['rentiva_transfer_deposit_rate'] = isset($_POST['rentiva_transfer_deposit_rate']) ? intval(wp_unslash($_POST['rentiva_transfer_deposit_rate'])) : 0;

		// Save Custom Types as Text
		if (isset($_POST['rentiva_transfer_custom_types'])) {
			$settings['rentiva_transfer_custom_types'] = sanitize_textarea_field(wp_unslash($_POST['rentiva_transfer_custom_types']));
		}

		update_option('mhm_rentiva_settings', $settings);

		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-settings&tab=transfer&updated=true'));
		exit;
	}

	// ... existing list render methods ...

	public function render_locations_page(): void
	{
		global $wpdb;

		$table_name = self::resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_table_name.
		$locations     = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY priority ASC, name ASC");
		$edit_location = null;

		$action  = sanitize_key((string) filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
		$edit_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, array('options' => array('default' => 0)));
		if ('edit' === $action && $edit_id > 0) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_table_name.
			$edit_location = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $edit_id)
			);
		}

	?>
		<div class="wrap">
			<?php
			$this->render_admin_header(
				(string) get_admin_page_title(),
				array(
					array(
						'text'   => esc_html__('Documentation', 'mhm-rentiva'),
						'url'    => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
						'class'  => 'button button-secondary',
						'icon'   => 'dashicons-book-alt',
						'target' => '_blank',
					),
				)
			);

			$this->render_developer_mode_banner();

			$this->render_transfer_stats();
			?>

			<div id="col-container" class="wp-clearfix">
				<!-- Add/Edit Form -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php echo $edit_location ? esc_html__('Edit Location', 'mhm-rentiva') : esc_html__('Add New Location', 'mhm-rentiva'); ?></h2>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<input type="hidden" name="action" value="mhm_save_location">
								<?php wp_nonce_field('mhm_save_location_nonce', 'mhm_nonce'); ?>
								<?php if ($edit_location) : ?>
									<input type="hidden" name="id" value="<?php echo esc_attr($edit_location->id); ?>">
								<?php endif; ?>

								<div class="form-field">
									<label for="name"><?php echo esc_html__('Name', 'mhm-rentiva'); ?></label>
									<input name="name" id="name" type="text" value="<?php echo $edit_location ? esc_attr($edit_location->name) : ''; ?>" required>
								</div>

								<div class="form-field">
									<label for="city"><?php echo esc_html__('City', 'mhm-rentiva'); ?></label>
									<?php echo CityHelper::render_select('city', 'city', $edit_location ? $edit_location->city : ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<p class="description"><?php echo esc_html__('City this location belongs to. Used to filter locations for vendors.', 'mhm-rentiva'); ?></p>
								</div>

								<div class="form-field">
									<label for="type"><?php echo esc_html__('Type', 'mhm-rentiva'); ?></label>
									<select name="type" id="type">
										<?php
										$types = self::get_location_types();

										foreach ($types as $key => $label) {
											echo '<option value="' . esc_attr($key) . '" ' . selected($edit_location ? $edit_location->type : '', $key, false) . '>' . esc_html($label) . '</option>';
										}
										?>
									</select>
								</div>

								<div class="form-field">
									<label for="priority"><?php echo esc_html__('Priority', 'mhm-rentiva'); ?></label>
									<input name="priority" id="priority" type="number" value="<?php echo $edit_location ? esc_attr($edit_location->priority) : '0'; ?>">
								</div>

								<div class="form-field" style="margin: 10px 0;">
									<label>
										<input name="allow_rental" type="checkbox" value="1" <?php checked($edit_location ? (int) $edit_location->allow_rental : 1); ?>>
										<?php echo esc_html__('Allow Rental', 'mhm-rentiva'); ?>
									</label>
									<p class="description"><?php echo esc_html__('Enable this to show the location in car rental searches.', 'mhm-rentiva'); ?></p>
								</div>

								<div class="form-field" style="margin: 10px 0;">
									<label>
										<input name="allow_transfer" type="checkbox" value="1" <?php checked($edit_location ? (int) $edit_location->allow_transfer : 1); ?>>
										<?php echo esc_html__('Allow Transfer', 'mhm-rentiva'); ?>
									</label>
									<p class="description"><?php echo esc_html__('Enable this to show the location in transfer searches.', 'mhm-rentiva'); ?></p>
								</div>

								<?php submit_button($edit_location ? __('Update Location', 'mhm-rentiva') : __('Add Location', 'mhm-rentiva')); ?>
								<?php if ($edit_location) : ?>
									<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-locations')); ?>" class="button"><?php echo esc_html__('Cancel', 'mhm-rentiva'); ?></a>
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
									<th><?php echo esc_html__('Name', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('City', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Type', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Priority', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Services', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Actions', 'mhm-rentiva'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if (! empty($locations)) : ?>
									<?php foreach ($locations as $location) : ?>
										<tr>
											<td><strong><?php echo esc_html($location->name); ?></strong></td>
											<td><?php echo esc_html($location->city); ?></td>
											<td><?php echo esc_html(ucfirst($location->type)); ?></td>
											<td><?php echo esc_html($location->priority); ?></td>
											<td>
												<?php if ($location->allow_rental) : ?>
													<span class="dashicons dashicons-car" title="<?php esc_attr_e('Rental Available', 'mhm-rentiva'); ?>"></span>
												<?php endif; ?>
												<?php if ($location->allow_transfer) : ?>
													<span class="dashicons dashicons-id" title="<?php esc_attr_e('Transfer Available', 'mhm-rentiva'); ?>"></span>
												<?php endif; ?>
												<?php if (! $location->allow_rental && ! $location->allow_transfer) : ?>
													<span class="dashicons dashicons-no-alt" style="color:red;" title="<?php esc_attr_e('No Services', 'mhm-rentiva'); ?>"></span>
												<?php endif; ?>
											</td>
											<td>
												<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-locations&action=edit&id=' . $location->id)); ?>"><?php echo esc_html__('Edit', 'mhm-rentiva'); ?></a> |
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mhm_delete_location&id=' . $location->id), 'mhm_delete_location_nonce')); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure?', 'mhm-rentiva')); ?>');" style="color: #a00;"><?php echo esc_html__('Delete', 'mhm-rentiva'); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="6"><?php echo esc_html__('No locations found.', 'mhm-rentiva'); ?></td>
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

	public function render_routes_page(): void
	{
		global $wpdb;

		$table_routes    = self::resolve_table_name('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');
		$table_locations = self::resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are sanitized via resolve_table_name.
		$routes = $wpdb->get_results(
			"SELECT r.*,
                   l1.name as origin_name, l1.allow_transfer as origin_eligible, l1.city as city,
                   l2.name as dest_name, l2.allow_transfer as dest_eligible
            FROM {$table_routes} r
            LEFT JOIN {$table_locations} l1 ON r.origin_id = l1.id
            LEFT JOIN {$table_locations} l2 ON r.destination_id = l2.id
            ORDER BY l1.city ASC, r.id DESC"
		);

		// SSOT: Only eligible locations for selection
		$locations = \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_locations('transfer');

		$edit_route = null;
		$action     = sanitize_key((string) filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
		$edit_id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, array('options' => array('default' => 0)));
		if ('edit' === $action && $edit_id > 0) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_table_name.
			$edit_route = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$table_routes} WHERE id = %d", $edit_id)
			);
		}
	?>
		<div class="wrap">
			<?php
			$this->render_admin_header(
				(string) get_admin_page_title(),
				array(
					array(
						'text'   => esc_html__('Documentation', 'mhm-rentiva'),
						'url'    => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
						'class'  => 'button button-secondary',
						'icon'   => 'dashicons-book-alt',
						'target' => '_blank',
					),
				)
			);

			$this->render_developer_mode_banner();

			$this->render_transfer_stats();

			\MHMRentiva\Admin\Core\ProFeatureNotice::displayLimitNotice( 'routes' );
			?>

			<div id="col-container" class="wp-clearfix">
				<!-- Add/Edit Form -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h2><?php echo $edit_route ? esc_html__('Edit Route', 'mhm-rentiva') : esc_html__('Add New Route', 'mhm-rentiva'); ?></h2>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<input type="hidden" name="action" value="mhm_save_route">
								<?php wp_nonce_field('mhm_save_route_nonce', 'mhm_nonce'); ?>
								<?php if ($edit_route) : ?>
									<input type="hidden" name="id" value="<?php echo esc_attr($edit_route->id); ?>">
								<?php endif; ?>

								<!-- Origin -->
								<div class="form-field">
									<label for="origin_id"><?php echo esc_html__('Origin', 'mhm-rentiva'); ?></label>
									<select name="origin_id" id="origin_id" required>
										<option value=""><?php echo esc_html__('Select Origin', 'mhm-rentiva'); ?></option>
										<?php foreach ($locations as $loc) : ?>
											<option value="<?php echo esc_attr($loc->id); ?>" <?php selected($edit_route ? (int) $edit_route->origin_id : 0, (int) $loc->id); ?>><?php echo esc_html($loc->name); ?></option>
										<?php endforeach; ?>
										<?php
										// Soft handling: If currently selected origin is not in the eligible list
										if ($edit_route && $edit_route->origin_id) {
											$origin_id = (int) $edit_route->origin_id;
											$found = false;
											foreach ($locations as $loc) {
												if ((int) $loc->id === $origin_id) {
													$found = true;
													break;
												}
											}
											if (!$found) {
												// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_table_name.
												$legacy_origin = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM {$table_locations} WHERE id = %d", $origin_id));
												if ($legacy_origin) {
													echo '<option value="' . esc_attr($legacy_origin->id) . '" selected disabled>' . esc_html($legacy_origin->name) . ' (' . esc_html__('Not eligible for transfer', 'mhm-rentiva') . ')</option>';
												}
											}
										}
										?>
									</select>
								</div>

								<!-- Destination -->
								<div class="form-field">
									<label for="destination_id"><?php echo esc_html__('Destination', 'mhm-rentiva'); ?></label>
									<select name="destination_id" id="destination_id" required>
										<option value=""><?php echo esc_html__('Select Destination', 'mhm-rentiva'); ?></option>
										<?php foreach ($locations as $loc) : ?>
											<option value="<?php echo esc_attr($loc->id); ?>" <?php selected($edit_route ? (int) $edit_route->destination_id : 0, (int) $loc->id); ?>><?php echo esc_html($loc->name); ?></option>
										<?php endforeach; ?>
										<?php
										// Soft handling: If currently selected destination is not in the eligible list
										if ($edit_route && $edit_route->destination_id) {
											$dest_id = (int) $edit_route->destination_id;
											$found = false;
											foreach ($locations as $loc) {
												if ((int) $loc->id === $dest_id) {
													$found = true;
													break;
												}
											}
											if (!$found) {
												// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via resolve_table_name.
												$legacy_dest = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM {$table_locations} WHERE id = %d", $dest_id));
												if ($legacy_dest) {
													echo '<option value="' . esc_attr($legacy_dest->id) . '" selected disabled>' . esc_html($legacy_dest->name) . ' (' . esc_html__('Not eligible for transfer', 'mhm-rentiva') . ')</option>';
												}
											}
										}
										?>
									</select>
								</div>

								<!-- Distance & Duration -->
								<div class="form-field">
									<label for="distance_km"><?php echo esc_html__('Distance (KM)', 'mhm-rentiva'); ?></label>
									<input name="distance_km" id="distance_km" type="number" step="0.1" value="<?php echo $edit_route ? esc_attr($edit_route->distance_km) : ''; ?>" required>
								</div>
								<div class="form-field">
									<label for="duration_min"><?php echo esc_html__('Duration (Minutes)', 'mhm-rentiva'); ?></label>
									<input name="duration_min" id="duration_min" type="number" value="<?php echo $edit_route ? esc_attr($edit_route->duration_min) : ''; ?>" required>
								</div>

								<!-- Pricing Method -->
								<div class="form-field">
									<label for="pricing_method"><?php echo esc_html__('Pricing Method', 'mhm-rentiva'); ?></label>
									<select name="pricing_method" id="pricing_method" onchange="togglePricingFields(this.value)">
										<option value="fixed" <?php selected($edit_route ? $edit_route->pricing_method : '', 'fixed'); ?>><?php echo esc_html__('Fixed Price', 'mhm-rentiva'); ?></option>
										<option value="calculated" <?php selected($edit_route ? $edit_route->pricing_method : '', 'calculated'); ?>><?php echo esc_html__('Distance Based (KM)', 'mhm-rentiva'); ?></option>
									</select>
								</div>

								<!-- Pricing Fields -->
								<div class="form-field">
									<label for="base_price"><span id="base_price_label"><?php echo esc_html__('Price', 'mhm-rentiva'); ?></span></label>
									<input name="base_price" id="base_price" type="number" step="0.01" value="<?php echo $edit_route ? esc_attr($edit_route->base_price) : ''; ?>" required>
								</div>

								<div class="form-field" id="min_price_field" style="<?php echo ($edit_route && 'calculated' === $edit_route->pricing_method) ? '' : 'display:none;'; ?>">
									<label for="min_price"><?php echo esc_html__('Minimum Price', 'mhm-rentiva'); ?></label>
									<input name="min_price" id="min_price" type="number" step="0.01" value="<?php echo $edit_route ? esc_attr($edit_route->min_price) : ''; ?>">
								</div>

								<div class="form-field" id="max_price_field">
									<label for="max_price"><?php echo esc_html__('Maximum Price (Vendor Ceiling)', 'mhm-rentiva'); ?></label>
									<input name="max_price" id="max_price" type="number" step="0.01" min="0" value="<?php echo $edit_route ? esc_attr($edit_route->max_price) : ''; ?>">
									<p class="description"><?php echo esc_html__('Maximum price vendors can charge for this route. Leave 0 for no ceiling.', 'mhm-rentiva'); ?></p>
								</div>

								<?php submit_button($edit_route ? __('Update Route', 'mhm-rentiva') : __('Add Route', 'mhm-rentiva')); ?>
								<?php if ($edit_route) : ?>
									<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-routes')); ?>" class="button"><?php echo esc_html__('Cancel', 'mhm-rentiva'); ?></a>
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
									<th><?php echo esc_html__('City', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Route', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Distance/Time', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Pricing', 'mhm-rentiva'); ?></th>
									<th><?php echo esc_html__('Actions', 'mhm-rentiva'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if (! empty($routes)) : ?>
									<?php foreach ($routes as $route) : ?>
										<tr>
											<td><?php echo esc_html($route->city ?? ''); ?></td>
											<td>
												<strong><?php echo esc_html($route->origin_name); ?></strong> &rarr; <strong><?php echo esc_html($route->dest_name); ?></strong>
												<?php if (!$route->origin_eligible || !$route->dest_eligible) : ?>
													<br><span class="badge badge-warning" style="background: #fff8e5; color: #856404; padding: 2px 6px; border-radius: 4px; font-size: 11px; border: 1px solid #ffeeba; margin-top: 5px; display: inline-block;">
														<span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; margin-top: -2px;"></span>
														<?php echo esc_html__('Not eligible for transfer', 'mhm-rentiva'); ?>
													</span>
												<?php endif; ?>
											</td>
											<td>
												<?php echo esc_html($route->distance_km); ?> km <br>
												<small><?php echo esc_html($route->duration_min); ?> min</small>
											</td>
											<td>
												<?php if ('fixed' === $route->pricing_method) : ?>
													<span class="badge badge-primary">Fixed</span>
													<strong><?php echo wp_kses_post(call_user_func('wc_price', $route->base_price)); ?></strong>
													<?php if ((float) $route->min_price > 0 || (float) $route->max_price > 0) : ?>
														<br><small>
														<?php if ((float) $route->min_price > 0) : ?>
															Min: <?php echo wp_kses_post(call_user_func('wc_price', $route->min_price)); ?>
														<?php endif; ?>
														<?php if ((float) $route->max_price > 0) : ?>
															Max: <?php echo wp_kses_post(call_user_func('wc_price', $route->max_price)); ?>
														<?php endif; ?>
														</small>
													<?php endif; ?>
												<?php else : ?>
													<span class="badge badge-secondary">KM</span>
													<?php echo wp_kses_post(call_user_func('wc_price', $route->base_price)); ?> / km <br>
													Min: <?php echo wp_kses_post(call_user_func('wc_price', $route->min_price)); ?>
													<?php if ((float) $route->max_price > 0) : ?>
														Max: <?php echo wp_kses_post(call_user_func('wc_price', $route->max_price)); ?>
													<?php endif; ?>
												<?php endif; ?>
											</td>
											<td>
												<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-routes&action=edit&id=' . $route->id)); ?>"><?php echo esc_html__('Edit', 'mhm-rentiva'); ?></a> |
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mhm_delete_route&id=' . $route->id), 'mhm_delete_route_nonce')); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure?', 'mhm-rentiva')); ?>');" style="color: #a00;"><?php echo esc_html__('Delete', 'mhm-rentiva'); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="5"><?php echo esc_html__('No routes found.', 'mhm-rentiva'); ?></td>
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
						label.textContent = '<?php echo esc_js(__('Price per KM', 'mhm-rentiva')); ?>';
						minField.style.display = 'block';
					} else {
						label.textContent = '<?php echo esc_js(__('Total Price', 'mhm-rentiva')); ?>';
						minField.style.display = 'none';
					}
				}
			</script>
		</div>
<?php
	}

	// --- FORM HANDLERS ---

	public static function handle_save_location(): void
	{
		if (! current_user_can('manage_options') || ! isset($_POST['mhm_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_nonce'])), 'mhm_save_location_nonce')) {
			wp_die(esc_html__('Unauthorized', 'mhm-rentiva'));
		}

		global $wpdb;
		$table_name = self::resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');

		$data = array(
			'name'           => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
			'city'           => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
			'type'           => isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '',
			'priority'       => isset($_POST['priority']) ? intval(wp_unslash($_POST['priority'])) : 0,
			'allow_rental'   => isset($_POST['allow_rental']) ? 1 : 0,
			'allow_transfer' => isset($_POST['allow_transfer']) ? 1 : 0,
		);

		if (! empty($_POST['id'])) {
			$wpdb->update($table_name, $data, array('id' => intval(wp_unslash($_POST['id']))));
		} else {
			$wpdb->insert($table_name, $data);
		}

		\MHMRentiva\Admin\Transfer\Engine\LocationProvider::clear_cache();

		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-locations&updated=true'));
		exit;
	}

	public static function handle_delete_location(): void
	{
		if (! current_user_can('manage_options') || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mhm_delete_location_nonce')) {
			wp_die(esc_html__('Unauthorized', 'mhm-rentiva'));
		}

		global $wpdb;
		$table_name = self::resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');

		$wpdb->delete($table_name, array('id' => isset($_GET['id']) ? intval(wp_unslash($_GET['id'])) : 0));

		\MHMRentiva\Admin\Transfer\Engine\LocationProvider::clear_cache();

		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-locations&deleted=true'));
		exit;
	}

	public static function handle_save_route(): void
	{
		if (! current_user_can('manage_options') || ! isset($_POST['mhm_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_nonce'])), 'mhm_save_route_nonce')) {
			wp_die(esc_html__('Unauthorized', 'mhm-rentiva'));
		}

		global $wpdb;
		$table_name = self::resolve_table_name('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');

		$data = array(
			'origin_id'      => isset($_POST['origin_id']) ? intval(wp_unslash($_POST['origin_id'])) : 0,
			'destination_id' => isset($_POST['destination_id']) ? intval(wp_unslash($_POST['destination_id'])) : 0,
			'distance_km'    => isset($_POST['distance_km']) ? floatval(wp_unslash($_POST['distance_km'])) : 0.0,
			'duration_min'   => isset($_POST['duration_min']) ? intval(wp_unslash($_POST['duration_min'])) : 0,
			'pricing_method' => isset($_POST['pricing_method']) ? sanitize_text_field(wp_unslash($_POST['pricing_method'])) : '',
			'base_price'     => isset($_POST['base_price']) ? floatval(wp_unslash($_POST['base_price'])) : 0.0,
			'min_price'      => isset($_POST['min_price']) ? floatval(wp_unslash($_POST['min_price'])) : 0.0,
			'max_price'      => isset($_POST['max_price']) ? floatval(wp_unslash($_POST['max_price'])) : 0.0,
		);

		// Server-side validation: Check eligibility
		$table_locations = self::resolve_table_name('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via resolve_table_name.
		$eligibility = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, allow_transfer FROM {$table_locations} WHERE id IN (%d, %d)",
				$data['origin_id'],
				$data['destination_id']
			),
			OBJECT_K
		);

		$origin_ok = isset($eligibility[$data['origin_id']]) && (int) $eligibility[$data['origin_id']]->allow_transfer === 1;
		$dest_ok   = isset($eligibility[$data['destination_id']]) && (int) $eligibility[$data['destination_id']]->allow_transfer === 1;

		if (!$origin_ok || !$dest_ok) {
			wp_die(esc_html__('Error: One or more selected locations are not eligible for transfer services.', 'mhm-rentiva'));
		}

		if (! empty($_POST['id'])) {
			$wpdb->update($table_name, $data, array('id' => intval(wp_unslash($_POST['id']))));
		} else {
			$wpdb->insert($table_name, $data);
		}

		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-routes&updated=true'));
		exit;
	}

	public static function handle_delete_route(): void
	{
		if (! current_user_can('manage_options') || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mhm_delete_route_nonce')) {
			wp_die(esc_html__('Unauthorized', 'mhm-rentiva'));
		}

		global $wpdb;
		$table_name = self::resolve_table_name('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');

		$wpdb->delete($table_name, array('id' => isset($_GET['id']) ? intval(wp_unslash($_GET['id'])) : 0));

		wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-routes&deleted=true'));
		exit;
	}
}
