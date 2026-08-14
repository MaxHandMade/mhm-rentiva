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
						'type'        => 'checkbox',
						'label'       => __( 'Active', 'mhm-rentiva' ),
						'label_text'  => __( 'Enable this additional service', 'mhm-rentiva' ),
						'description' => __( 'Only active additional services are visible in booking form.', 'mhm-rentiva' ),
					),
					'mhmrentiva_addon_required' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Required', 'mhm-rentiva' ),
						'label_text'  => __( 'This additional service is required', 'mhm-rentiva' ),
						'description' => __( 'Required additional services are automatically selected and cannot be removed.', 'mhm-rentiva' ),
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

	/**
	 * Get addon meta data.
	 *
	 * @param int $addon_id Addon ID.
	 * @return array Meta data.
	 */
	public static function get_addon_meta( int $addon_id ): array {
		return array(
			'price'    => (float) get_post_meta( $addon_id, 'mhmrentiva_addon_price', true ),
			'enabled'  => (bool) get_post_meta( $addon_id, 'mhmrentiva_addon_enabled', true ),
			'required' => (bool) get_post_meta( $addon_id, 'mhmrentiva_addon_required', true ),
		);
	}

	/**
	 * Update addon meta data.
	 *
	 * @param int   $addon_id Addon ID.
	 * @param array $meta Meta data to update.
	 * @return bool True on success.
	 */
	public static function update_addon_meta( int $addon_id, array $meta ): bool {
		$updated = true;

		if ( isset( $meta['price'] ) ) {
			$updated &= update_post_meta( $addon_id, 'mhmrentiva_addon_price', floatval( $meta['price'] ) ) !== false;
		}

		if ( isset( $meta['enabled'] ) ) {
			$enabled  = $meta['enabled'] ? '1' : '0';
			$updated &= update_post_meta( $addon_id, 'mhmrentiva_addon_enabled', $enabled ) !== false;
		}

		if ( isset( $meta['required'] ) ) {
			$required = $meta['required'] ? '1' : '0';
			$updated &= update_post_meta( $addon_id, 'mhmrentiva_addon_required', $required ) !== false;
		}

		return (bool) $updated;
	}

	/**
	 * Delete addon meta data.
	 *
	 * @param int $addon_id Addon ID.
	 */
	public static function delete_addon_meta( int $addon_id ): void {
		delete_post_meta( $addon_id, 'mhmrentiva_addon_price' );
		delete_post_meta( $addon_id, 'mhmrentiva_addon_enabled' );
		delete_post_meta( $addon_id, 'mhmrentiva_addon_required' );
	}
}
