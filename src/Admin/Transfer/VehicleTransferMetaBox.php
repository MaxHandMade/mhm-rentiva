<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer;

if (! defined('ABSPATH')) {
	exit;
}

final class VehicleTransferMetaBox
{

	/**
	 * Register meta box
	 */
	public static function register(): void
	{
		add_action('add_meta_boxes', array(self::class, 'add_meta_box'));
		add_action('save_post', array(self::class, 'save_meta_box'));
	}

	/**
	 * Add meta box to Vehicle post type
	 */
	public static function add_meta_box(): void
	{
		add_meta_box(
			'mhm_rentiva_vehicle_transfer_settings',
			__('Transfer Settings (VIP Module)', 'mhm-rentiva'),
			array(self::class, 'render_meta_box'),
			'vehicle',
			'side',
			'default'
		);
	}

	/**
	 * Render Meta Box content
	 */
	public static function render_meta_box(\WP_Post $post): void
	{
		// Add nonce for security
		wp_nonce_field('rentiva_vehicle_transfer_settings_nonce', 'rentiva_vehicle_transfer_settings_nonce');

		// Retrieve existing values (with backward compatibility)
		$service_type  = get_post_meta($post->ID, '_rentiva_vehicle_service_type', true);
		if (! $service_type) {
			$service_type = get_post_meta($post->ID, '_mhm_vehicle_service_type', true) ?: 'rental';
		}

		$max_pax = get_post_meta($post->ID, '_rentiva_transfer_max_pax', true);
		if ($max_pax === '') {
			$max_pax = get_post_meta($post->ID, '_mhm_transfer_max_pax', true) ?: '';
		}

		$luggage_score = get_post_meta($post->ID, '_rentiva_transfer_max_luggage_score', true);
		if ($luggage_score === '') {
			$luggage_score = get_post_meta($post->ID, '_mhm_transfer_max_luggage_score', true) ?: '';
		}

		$max_big = get_post_meta($post->ID, '_rentiva_vehicle_max_big_luggage', true);
		if ($max_big === '') {
			$max_big = get_post_meta($post->ID, '_mhm_vehicle_max_big_luggage', true);
		}

		$max_small = get_post_meta($post->ID, '_rentiva_vehicle_max_small_luggage', true);
		if ($max_small === '') {
			$max_small = get_post_meta($post->ID, '_mhm_vehicle_max_small_luggage', true);
		}

		$multiplier = get_post_meta($post->ID, '_rentiva_transfer_price_multiplier', true);
		if ($multiplier === '') {
			$multiplier = get_post_meta($post->ID, '_mhm_transfer_price_multiplier', true) ?: '1.0';
		}

?>
		<div class="mhm-meta-box-content">
			<!-- Service Type -->
			<p>
				<label for="rentiva_vehicle_service_type"><strong><?php echo esc_html__('Service Type', 'mhm-rentiva'); ?></strong></label><br>
				<select name="rentiva_vehicle_service_type" id="rentiva_vehicle_service_type" style="width:100%;">
					<option value="rental" <?php selected($service_type, 'rental'); ?>><?php echo esc_html__('Rental Only', 'mhm-rentiva'); ?></option>
					<option value="transfer" <?php selected($service_type, 'transfer'); ?>><?php echo esc_html__('Transfer Only', 'mhm-rentiva'); ?></option>
					<option value="both" <?php selected($service_type, 'both'); ?>><?php echo esc_html__('Both (Rental & Transfer)', 'mhm-rentiva'); ?></option>
				</select>
			</p>

			<hr>

			<!-- Transfer Capacity -->
			<div id="mhm-transfer-fields" style="<?php echo ($service_type === 'rental') ? 'display:none;' : ''; ?>">
				<p>
					<label for="rentiva_transfer_max_pax"><strong><?php echo esc_html__('Max Passengers', 'mhm-rentiva'); ?></strong></label><br>
					<input type="number" name="rentiva_transfer_max_pax" id="rentiva_transfer_max_pax" value="<?php echo esc_attr($max_pax); ?>" style="width:100%;" min="1">
					<span class="description"><?php echo esc_html__('Net capacity excluding driver.', 'mhm-rentiva'); ?></span>
				</p>

				<p>
					<label for="rentiva_transfer_max_luggage_score"><strong><?php echo esc_html__('Luggage Score Capacity', 'mhm-rentiva'); ?></strong></label><br>
					<input type="number" name="rentiva_transfer_max_luggage_score" id="rentiva_transfer_max_luggage_score" value="<?php echo esc_attr($luggage_score); ?>" style="width:100%;" step="0.5" min="0">
					<br>
					<span class="description" style="color:#666; font-size:12px;">
						<?php echo esc_html__('Validations: Small Bag = 1, Big Bag = 2.5 points.', 'mhm-rentiva'); ?>
					</span>
				</p>

				<!-- Detailed Luggage Limits (New) -->
				<div style="background: #f9f9f9; padding: 10px; margin-top: 10px; border: 1px solid #ddd;">
					<p style="margin-top:0;"><strong><?php echo esc_html__('Detailed Luggage Limits', 'mhm-rentiva'); ?></strong></p>

					<p>
						<label for="rentiva_vehicle_max_big_luggage"><?php echo esc_html__('Max Big Luggage', 'mhm-rentiva'); ?></label><br>
						<input type="number" name="rentiva_vehicle_max_big_luggage" id="rentiva_vehicle_max_big_luggage"
							value="<?php echo esc_attr($max_big); ?>"
							style="width:100%;" min="0">
					</p>

					<p style="margin-bottom:0;">
						<label for="rentiva_vehicle_max_small_luggage"><?php echo esc_html__('Max Small Luggage', 'mhm-rentiva'); ?></label><br>
						<input type="number" name="rentiva_vehicle_max_small_luggage" id="rentiva_vehicle_max_small_luggage"
							value="<?php echo esc_attr($max_small); ?>"
							style="width:100%;" min="0">
					</p>
				</div>
				<!-- End Detailed Luggage -->

				<!-- Price Multiplier (New) -->
				<p>
					<label for="rentiva_transfer_price_multiplier"><strong><?php echo esc_html__('Transfer Price Multiplier', 'mhm-rentiva'); ?></strong></label><br>
					<input type="number" name="rentiva_transfer_price_multiplier" id="rentiva_transfer_price_multiplier"
						value="<?php echo esc_attr($multiplier); ?>"
						style="width:100%;" step="0.1" min="0">
					<span class="description" style="color:#666; font-size:12px;">
						<?php echo esc_html__('Base price multiplier. E.g., 1.5 for Luxury, 1.0 for Standard.', 'mhm-rentiva'); ?>
					</span>
				</p>

			</div>

			<script>
				jQuery(document).ready(function($) {
					$('#rentiva_vehicle_service_type').on('change', function() {
						if ($(this).val() === 'rental') {
							$('#mhm-transfer-fields').slideUp();
						} else {
							$('#mhm-transfer-fields').slideDown();
						}
					});
				});
			</script>
		</div>
<?php
	}

	/**
	 * Save Meta Box data
	 */
	public static function save_meta_box(int $post_id): void
	{
		// Check if our nonce is set.
		if (! isset($_POST['rentiva_vehicle_transfer_settings_nonce'])) {
			return;
		}

		// Verify that the nonce is valid.
		$nonce = sanitize_text_field(wp_unslash($_POST['rentiva_vehicle_transfer_settings_nonce']));
		if (! wp_verify_nonce($nonce, 'rentiva_vehicle_transfer_settings_nonce')) {
			return;
		}

		// If this is an autosave.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Check the user's permissions.
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		// Save Service Type
		if (isset($_POST['rentiva_vehicle_service_type'])) {
			update_post_meta($post_id, '_rentiva_vehicle_service_type', sanitize_text_field(wp_unslash($_POST['rentiva_vehicle_service_type'])));
		}

		// Save Max Pax
		if (isset($_POST['rentiva_transfer_max_pax'])) {
			update_post_meta($post_id, '_rentiva_transfer_max_pax', intval($_POST['rentiva_transfer_max_pax']));
		}

		// Save Luggage Score
		if (isset($_POST['rentiva_transfer_max_luggage_score'])) {
			update_post_meta($post_id, '_rentiva_transfer_max_luggage_score', floatval($_POST['rentiva_transfer_max_luggage_score']));
		}

		// Save Max Big Luggage
		if (isset($_POST['rentiva_vehicle_max_big_luggage'])) {
			$val = $_POST['rentiva_vehicle_max_big_luggage'] === '' ? '' : intval($_POST['rentiva_vehicle_max_big_luggage']);
			update_post_meta($post_id, '_rentiva_vehicle_max_big_luggage', $val);
		}

		// Save Max Small Luggage
		if (isset($_POST['rentiva_vehicle_max_small_luggage'])) {
			$val = $_POST['rentiva_vehicle_max_small_luggage'] === '' ? '' : intval($_POST['rentiva_vehicle_max_small_luggage']);
			update_post_meta($post_id, '_rentiva_vehicle_max_small_luggage', $val);
		}

		// Save Price Multiplier
		if (isset($_POST['rentiva_transfer_price_multiplier'])) {
			$val = sanitize_text_field(wp_unslash($_POST['rentiva_transfer_price_multiplier']));
			if ($val !== '') {
				update_post_meta($post_id, '_rentiva_transfer_price_multiplier', floatval($val));
			} else {
				delete_post_meta($post_id, '_rentiva_transfer_price_multiplier');
			}
		}
	}
}
