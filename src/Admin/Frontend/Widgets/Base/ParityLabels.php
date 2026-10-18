<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translatable labels for Elementor parity controls ("Additional Settings").
 *
 * Parity controls are generated from block.json attributes, so their ids are
 * canonical snake_case keys and their enum options are raw values. This dictionary
 * turns both into translatable strings. Every entry is a literal __() / _x() call
 * so `wp i18n make-pot` extracts it.
 *
 * msgid rule: when the block inspector (assets/blocks/<slug>/index.js) labels the
 * same attribute, the exact same EN string is used, so the block and the Elementor
 * widget share one catalog entry. Otherwise a new Title Case EN string is used.
 *
 * Keys are canonical keys. A key that needs a different label or option set in one
 * block is written `<block-slug>:<key>`; lookups try the qualified key first.
 *
 * Saved values never change: option KEYS always come from block.json.
 */
final class ParityLabels {

	/**
	 * Unfiltered dictionary, per locale.
	 *
	 * @var array<string,array{labels:array<string,string>,options:array<string,array<array-key,string>>}>
	 */
	private static array $cache = array();

	/**
	 * Filtered dictionary of the last lookup. Single slot, replaced when the key
	 * (locale + hook signature) changes.
	 *
	 * @var array{key:string,dictionary:array{labels:array<string,string>,options:array<string,array<array-key,string>>}}|null
	 */
	private static ?array $filtered = null;

	/**
	 * Label of the parity section in the Elementor panel.
	 */
	public static function section_label(): string {
		return __( 'Additional Settings', 'mhm-rentiva' );
	}

	/**
	 * Control label for a canonical key, or null when the dictionary has none.
	 *
	 * @param string $canonical Canonical attribute key (the control id).
	 * @param string $block     Block slug the control was derived from.
	 */
	public static function label( string $canonical, string $block = '' ): ?string {
		$labels = self::dictionary()['labels'];

		if ( '' !== $block && isset( $labels[ $block . ':' . $canonical ] ) ) {
			return $labels[ $block . ':' . $canonical ];
		}

		return $labels[ $canonical ] ?? null;
	}

	/**
	 * Option label for an enum value of a canonical key, or null when unknown.
	 *
	 * @param string $canonical Canonical attribute key.
	 * @param string $value     Enum value (the saved option key).
	 * @param string $block     Block slug the control was derived from.
	 */
	public static function option( string $canonical, string $value, string $block = '' ): ?string {
		$options = self::dictionary()['options'];

		if ( '' !== $block && isset( $options[ $block . ':' . $canonical ][ $value ] ) ) {
			return $options[ $block . ':' . $canonical ][ $value ];
		}

		return $options[ $canonical ][ $value ] ?? null;
	}

	/**
	 * SELECT options for an enum: keys are the block.json enum values unchanged,
	 * labels come from the dictionary with the raw value as fallback.
	 *
	 * @param string       $canonical Canonical attribute key.
	 * @param array<mixed> $enum      block.json `enum`.
	 * @param string       $block     Block slug.
	 * @return array<array-key,string> Numeric enum values become integer keys, as PHP does for any array.
	 */
	public static function select_options( string $canonical, array $enum, string $block = '' ): array {
		$options = array();
		foreach ( $enum as $enum_value ) {
			if ( ! is_scalar( $enum_value ) ) {
				continue;
			}
			$key             = (string) $enum_value;
			$options[ $key ] = self::option( $canonical, $key, $block ) ?? $key;
		}
		return $options;
	}

	/**
	 * Filtered dictionary, memoised per locale and per hooked callback set.
	 *
	 * @return array{labels:array<string,string>,options:array<string,array<array-key,string>>}
	 */
	private static function dictionary(): array {
		$locale = determine_locale();
		$key    = $locale . '|' . self::hook_signature();

		if ( null !== self::$filtered && self::$filtered['key'] === $key ) {
			return self::$filtered['dictionary'];
		}

		if ( ! isset( self::$cache[ $locale ] ) ) {
			self::$cache[ $locale ] = self::build();
		}
		$base = self::$cache[ $locale ];

		/**
		 * Filters the parity label dictionary (add-ons add their own keys).
		 *
		 * @param array{labels:array<string,string>,options:array<string,array<array-key,string>>} $dictionary
		 */
		$filtered = apply_filters( 'mhmrentiva_parity_labels', $base );

		$dictionary = is_array( $filtered ) ? self::normalise( $filtered ) : $base;

		self::$filtered = array(
			'key'        => $key,
			'dictionary' => $dictionary,
		);

		return $dictionary;
	}

	/**
	 * Identity of the callbacks hooked to the filter: priority => unique ids, in
	 * execution order. Changes whenever a callback is added, removed, replaced or
	 * reordered, which a plain count would miss.
	 *
	 * Accepted limit: a freed closure's object id can be reused by a new closure at
	 * the same priority, which then yields the same signature (registrations happen
	 * once at boot).
	 */
	private static function hook_signature(): string {
		$hook = $GLOBALS['wp_filter']['mhmrentiva_parity_labels'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return '';
		}

		return (string) wp_json_encode( array_map( 'array_keys', $hook->callbacks ) );
	}

	/**
	 * Keep only string labels / option labels from a filtered dictionary.
	 *
	 * @param array<mixed> $filtered Result of the filter.
	 * @return array{labels:array<string,string>,options:array<string,array<array-key,string>>}
	 */
	private static function normalise( array $filtered ): array {
		$labels = array();
		foreach ( (array) ( $filtered['labels'] ?? array() ) as $key => $label ) {
			if ( is_string( $label ) ) {
				$labels[ (string) $key ] = $label;
			}
		}

		$options = array();
		foreach ( (array) ( $filtered['options'] ?? array() ) as $key => $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $value => $label ) {
				if ( is_string( $label ) ) {
					$options[ (string) $key ][ (string) $value ] = $label;
				}
			}
		}

		return array(
			'labels'  => $labels,
			'options' => $options,
		);
	}

	/**
	 * The Lite dictionary.
	 *
	 * @return array{labels:array<string,string>,options:array<string,array<array-key,string>>}
	 */
	private static function build(): array {
		$labels = array(
			// availability-calendar.
			'show_vehicle_selector'         => __( 'Show Vehicle Selector', 'mhm-rentiva' ),
			'show_legend'                   => __( 'Show Legend', 'mhm-rentiva' ),
			'show_pricing'                  => __( 'Show Pricing', 'mhm-rentiva' ),
			'show_month_nav'                => __( 'Show Month Navigation', 'mhm-rentiva' ),
			'months_to_show'                => __( 'Months to Show', 'mhm-rentiva' ),
			'start_week_on'                 => __( 'Start Week On', 'mhm-rentiva' ),
			'theme'                         => __( 'Theme', 'mhm-rentiva' ),
			'start_month'                   => __( 'Start Month', 'mhm-rentiva' ),
			'show_booking_button'           => __( 'Show Booking Button', 'mhm-rentiva' ),
			// booking-form.
			'booking-form:show_time_select' => __( 'Show Time Select', 'mhm-rentiva' ),
			'start_date'                    => __( 'Start Date', 'mhm-rentiva' ),
			'end_date'                      => __( 'End Date', 'mhm-rentiva' ),
			// contact.
			'show_booking_id_field'         => __( 'Show Booking ID Field', 'mhm-rentiva' ),
			'show_company_info'             => __( 'Show Company Info', 'mhm-rentiva' ),
			'success_message'               => __( 'Success Message', 'mhm-rentiva' ),
			'type'                          => __( 'Form Type', 'mhm-rentiva' ),
			'title'                         => __( 'Title', 'mhm-rentiva' ),
			'description'                   => __( 'Description', 'mhm-rentiva' ),
			'show_phone'                    => __( 'Show Phone Field', 'mhm-rentiva' ),
			'show_company'                  => __( 'Show Company Field', 'mhm-rentiva' ),
			'contact:show_vehicle_selector' => __( 'Show Vehicle Select', 'mhm-rentiva' ),
			'show_priority'                 => __( 'Show Priority Field', 'mhm-rentiva' ),
			'show_attachment'               => __( 'Show Attachment Field', 'mhm-rentiva' ),
			'redirect_url'                  => __( 'Redirect URL', 'mhm-rentiva' ),
			'email_to'                      => __( 'Recipient Email', 'mhm-rentiva' ),
			'auto_reply'                    => __( 'Auto Reply', 'mhm-rentiva' ),
			// featured-vehicles.
			'show_brand'                    => __( 'Show Brand', 'mhm-rentiva' ),
			'category'                      => __( 'Filter Categories (IDs)', 'mhm-rentiva' ),
			'ids'                           => __( 'Vehicle IDs', 'mhm-rentiva' ),
			'interval'                      => __( 'Autoplay Interval (ms)', 'mhm-rentiva' ),
			'max_features'                  => __( 'Max Features', 'mhm-rentiva' ),
			'image_size'                    => __( 'Image Size', 'mhm-rentiva' ),
			// my-bookings.
			'show_image'                    => __( 'Show Image', 'mhm-rentiva' ),
			'my-bookings:show_image'        => __( 'Show Vehicle Image', 'mhm-rentiva' ),
			'show_booking_dates'            => __( 'Show Booking Dates', 'mhm-rentiva' ),
			'show_price'                    => __( 'Show Price', 'mhm-rentiva' ),
			'show_status_toggle'            => __( 'Show Status', 'mhm-rentiva' ),
			'show_pagination'               => __( 'Show Pagination', 'mhm-rentiva' ),
			'hide_nav'                      => __( 'Hide Navigation', 'mhm-rentiva' ),
			// my-favorites.
			'layout'                        => __( 'Layout', 'mhm-rentiva' ),
			'show_availability_status'      => __( 'Show Availability Status', 'mhm-rentiva' ),
			'show_category'                 => __( 'Show Category', 'mhm-rentiva' ),
			'show_title'                    => __( 'Show Title', 'mhm-rentiva' ),
			'no_results_text'               => __( 'No Results Text', 'mhm-rentiva' ),
			// payment-history.
			'show_payment_method'           => __( 'Show Payment Method', 'mhm-rentiva' ),
			'show_date'                     => __( 'Show Date', 'mhm-rentiva' ),
			'show_booking_link'             => __( 'Show Booking Link', 'mhm-rentiva' ),
			'status'                        => __( 'Filter Status', 'mhm-rentiva' ),
			'orderby'                       => __( 'Sort By', 'mhm-rentiva' ),
			'order'                         => _x( 'Order', 'sort direction', 'mhm-rentiva' ),
			'payment-history:order'         => __( 'Sort Order', 'mhm-rentiva' ),
			'limit'                         => __( 'Limit', 'mhm-rentiva' ),
			'payment-history:limit'         => __( 'Limit Results', 'mhm-rentiva' ),
			// search-results.
			'show_rating'                   => __( 'Show Rating', 'mhm-rentiva' ),
			'show_features'                 => __( 'Show Features', 'mhm-rentiva' ),
			'show_badges'                   => __( 'Show Badges', 'mhm-rentiva' ),
			'show_view_toggle'              => __( 'Show View Toggle', 'mhm-rentiva' ),
			// testimonials.
			'show_author_avatar'            => __( 'Show Author Avatar', 'mhm-rentiva' ),
			'show_quotes'                   => __( 'Show Quotes', 'mhm-rentiva' ),
			'vehicle_id'                    => __( 'Vehicle ID (Optional)', 'mhm-rentiva' ),
			// unified-search.
			'show_time_select'              => __( 'Time Select', 'mhm-rentiva' ),
			'show_dropoff_location'         => __( 'Dropoff Location', 'mhm-rentiva' ),
			'location_required'             => __( 'Require Location', 'mhm-rentiva' ),
			'fields_required'               => __( 'Require Form Fields', 'mhm-rentiva' ),
			'redirect_page'                 => __( 'Redirect Page ID', 'mhm-rentiva' ),
			// vehicles-list / vehicle card.
			'show_compare_button'           => __( 'Show Compare Button', 'mhm-rentiva' ),
			'min_rating'                    => __( 'Min Rating (1-5)', 'mhm-rentiva' ),
			'min_reviews'                   => __( 'Min Reviews', 'mhm-rentiva' ),
			'featured'                      => __( 'Featured Only', 'mhm-rentiva' ),
			'show_description'              => __( 'Show Description', 'mhm-rentiva' ),
			'show_favorite_button'          => __( 'Show Favorite Button', 'mhm-rentiva' ),
			'custom_css_class'              => __( 'Custom CSS Class', 'mhm-rentiva' ),
			// vehicle-details.
			'show_reviews'                  => __( 'Show Reviews', 'mhm-rentiva' ),
			'show_similar_vehicles'         => __( 'Show Similar Vehicles', 'mhm-rentiva' ),
			'similar_vehicles_limit'        => __( 'Similar Vehicles Limit', 'mhm-rentiva' ),
			// vehicle-rating-form.
			'show_rating_display'           => __( 'Show Rating Summary', 'mhm-rentiva' ),
			'show_form'                     => __( 'Show Rating Form', 'mhm-rentiva' ),
			'show_ratings_list'             => __( 'Show Ratings List', 'mhm-rentiva' ),
			// vehicles-grid.
			'columns_tablet'                => __( 'Columns (Tablet)', 'mhm-rentiva' ),
			'columns_mobile'                => __( 'Columns (Mobile)', 'mhm-rentiva' ),
		);

		$default_on_off = array(
			'default' => __( 'Global Default', 'mhm-rentiva' ),
			'true'    => __( 'Enabled', 'mhm-rentiva' ),
			'false'   => __( 'Disabled', 'mhm-rentiva' ),
		);

		$options = array(
			// vehicles-list (title..confidence) and payment-history (date, amount) share no value.
			'orderby'               => array(
				'title'        => __( 'Title', 'mhm-rentiva' ),
				'price'        => __( 'Price', 'mhm-rentiva' ),
				'popularity'   => __( 'Popularity', 'mhm-rentiva' ),
				'newest'       => __( 'Newest', 'mhm-rentiva' ),
				'rating'       => __( 'Rating', 'mhm-rentiva' ),
				'rating_count' => __( 'Most Reviewed', 'mhm-rentiva' ),
				'confidence'   => __( 'Confidence Score', 'mhm-rentiva' ),
				'date'         => __( 'Date', 'mhm-rentiva' ),
				'amount'       => __( 'Amount', 'mhm-rentiva' ),
			),
			'order'                 => array(
				'asc'  => __( 'Ascending', 'mhm-rentiva' ),
				'desc' => __( 'Descending', 'mhm-rentiva' ),
			),
			// vehicles-grid (grid, masonry) and my-favorites (grid, list).
			'layout'                => array(
				'grid'    => __( 'Grid', 'mhm-rentiva' ),
				'masonry' => __( 'Masonry', 'mhm-rentiva' ),
				'list'    => __( 'List', 'mhm-rentiva' ),
			),
			'start_week_on'         => array(
				'0' => __( 'Sunday', 'mhm-rentiva' ),
				'1' => __( 'Monday', 'mhm-rentiva' ),
			),
			'status'                => array(
				'all'       => __( 'All', 'mhm-rentiva' ),
				'pending'   => __( 'Pending', 'mhm-rentiva' ),
				'completed' => __( 'Completed', 'mhm-rentiva' ),
				'cancelled' => __( 'Cancelled', 'mhm-rentiva' ),
			),
			'type'                  => array(
				'general'  => __( 'General', 'mhm-rentiva' ),
				'booking'  => __( 'Booking', 'mhm-rentiva' ),
				'support'  => __( 'Support', 'mhm-rentiva' ),
				'feedback' => __( 'Feedback', 'mhm-rentiva' ),
			),
			'show_time_select'      => $default_on_off,
			'show_dropoff_location' => $default_on_off,
			'location_required'     => array(
				'default' => __( 'Global Default', 'mhm-rentiva' ),
				'true'    => __( 'Required', 'mhm-rentiva' ),
				'false'   => __( 'Optional', 'mhm-rentiva' ),
			),
			'fields_required'       => array(
				'default' => __( 'Global Default', 'mhm-rentiva' ),
				'true'    => __( 'Required', 'mhm-rentiva' ),
				'false'   => __( 'Optional (Browse All)', 'mhm-rentiva' ),
			),
		);

		return array(
			'labels'  => $labels,
			'options' => $options,
		);
	}
}
