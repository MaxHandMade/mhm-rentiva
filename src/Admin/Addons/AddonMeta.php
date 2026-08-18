<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Meta Box Class.
 *
 * @package MHMRentiva\Admin\Addons
 */





use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Addons\AddonPricingType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles meta boxes for additional services.
 */
final class AddonMeta extends AbstractMetaBox {



	/**
	 * Get post type.
	 *
	 * @return string Post type.
	 */
	protected static function get_post_type(): string {
		return AddonPostType::POST_TYPE;
	}

	/**
	 * Get meta box ID.
	 *
	 * @return string Meta box ID.
	 */
	protected static function get_meta_box_id(): string {
		return 'addon_details';
	}

	/**
	 * Get meta box title.
	 *
	 * @return string Title.
	 */
	protected static function get_title(): string {
		return __( 'Additional Service Details', 'mhm-rentiva' );
	}

	/**
	 * Get meta fields configuration.
	 *
	 * @return array Meta fields.
	 */
	protected static function get_fields(): array {
		return array(
			'addon_details'  => array(
				'title'    => __( 'Additional Service Details', 'mhm-rentiva' ),
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => array(
					'mhmrentiva_addon_price'         => array(
						'type'              => 'number',
						'label'             => __( 'Price', 'mhm-rentiva' ),
						/* translators: %s placeholder. */
						'description'       => sprintf( __( 'Fixed price for this additional service. Will be added to booking total. (%s)', 'mhm-rentiva' ), \MHMRentiva\Admin\Addons\AddonManager::get_default_currency() ),
						'step'              => '0.01',
						'min'               => '0',
						'required'          => true,
						'class'             => 'regular-text',
						'sanitize_callback' => array( self::class, 'sanitize_price' ),
					),
					'_mhmrentiva_addon_pricing_type' => array(
						'type'              => 'select',
						'label'             => __( 'Pricing Type', 'mhm-rentiva' ),
						'description'       => __( 'Choose how this additional service is priced.', 'mhm-rentiva' ),
						'options'           => array(
							AddonPricingType::PER_BOOKING => AddonPricingType::label( AddonPricingType::PER_BOOKING ),
							AddonPricingType::PER_DAY     => AddonPricingType::label( AddonPricingType::PER_DAY ),
							AddonPricingType::PER_PASSENGER => AddonPricingType::label( AddonPricingType::PER_PASSENGER ),
						),
						'default'           => AddonPricingType::PER_BOOKING,
						'sanitize_callback' => array( AddonPricingType::class, 'sanitize' ),
						'class'             => 'regular-text mhm-addon-pricing-type-select',
					),
				),
			),
			'addon_settings' => array(
				'title'    => __( 'Additional Service Settings', 'mhm-rentiva' ),
				// Main column, not the sidebar. These fields render through the
				// shared form-table template, which splits each row into a label
				// cell and a control cell. In the ~280px sidebar that left both
				// halves around 90px wide, so the checkbox labels and their
				// explanations wrapped to one or two words per line and the
				// setting could only be read as a column of fragments.
				//
				// The sidebar keeps Publish, Add-on Categories, Contexts,
				// Attributes and the featured image, so it does not empty out.
				'context'  => 'normal',
				'priority' => 'default',
				'fields'   => array(
					'mhmrentiva_addon_enabled'  => array(
						'type'         => 'checkbox',
						'label'        => __( 'Active', 'mhm-rentiva' ),
						'label_text'   => __( 'Enable this additional service', 'mhm-rentiva' ),
						'description'  => __( 'Only active additional services are visible in booking form.', 'mhm-rentiva' ),
						// Unticking has to leave a '0' behind, not an empty row.
						// AddonManager::is_sellable() reads a missing flag as ACTIVE
						// so that services predating this field keep selling; without
						// this line, unticking here deleted the row and the service
						// went straight back on sale -- which is what the description
						// directly above promises it will not do.
						'absent_value' => '0',
						// ...and only an explicit '0' means off, which is exactly
						// what AddonManager::is_enabled() answers everywhere else.
						// The editor is the surface that turns a read into a write:
						// render it unticked and the next Update writes '0'.
						'off_value'    => '0',
					),
					'mhmrentiva_addon_required' => array(
						'type'         => 'checkbox',
						'label'        => __( 'Required', 'mhm-rentiva' ),
						'label_text'   => __( 'This additional service is required', 'mhm-rentiva' ),
						'description'  => __( 'Required additional services are automatically selected and cannot be removed.', 'mhm-rentiva' ),
						// Absence already means "not required" everywhere that reads
						// it, so this changes no behaviour. It is here so the two
						// switches in one box are not saved by two different rules,
						// which is the kind of difference nobody notices until it
						// matters.
						'absent_value' => '0',
					),
				),
			),
		);
	}

	/**
	 * Custom render for settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $field_config Field configuration.
	 */
	protected static function render_settings_meta_box( \WP_Post $post, array $field_config ): void {
		// Default render.
		self::render_default_template( $post, $field_config, 'addon_settings' );
	}

	/**
	 * Price sanitization.
	 *
	 * @param mixed $value Price value.
	 * @return float Sanitized price.
	 */
	public static function sanitize_price( $value ): float {
		$price = floatval( $value );
		return max( 0, $price );
	}

	/**
	 * Override save_meta to add cache clearing.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		// Call parent save_meta.
		parent::save_meta( $post_id, $post );

		// Clear cache.
		\MHMRentiva\Admin\Core\Utilities\CacheManager::clear_addon_cache( $post_id );
	}

	/**
	 * Validate post data before save.
	 *
	 * @param array $data Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array Validated data.
	 */
	public static function validate_post_data( array $data, array $postarr ): array {
		if ( AddonPostType::POST_TYPE !== $data['post_type'] ) {
			return $data;
		}

		return $data;
	}


	// update_addon_meta() and delete_addon_meta() used to sit here. Both had no
	// caller in either edition, and the first one did real harm by existing: it
	// looked exactly like the editor's save path, so AddonManager::is_sellable()
	// was written citing its behaviour ("unchecking writes '0'") as the reason
	// absence could be read as active. The editor never called it. It deleted the
	// row instead, and the predicate sold services the operator had switched off.
	//
	// Deleted rather than wired up: the save path they duplicate is
	// AbstractMetaBox::save_field(), which now carries the `absent_value` option
	// these fields declare. Two ways to write the same three meta keys is how the
	// wrong one gets believed.
}
