<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.





if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Textdomain is loaded automatically by WordPress since 4.6 for plugins on WordPress.org
// For custom plugins, you can still use load_plugin_textdomain in a hook if needed.


/**
 * Database Cleaner
 *
 * Cleans orphaned data, expired transients and unused meta keys
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- This utility performs intentional maintenance/migration operations directly on custom tables and wp_* metadata for cleanup and recovery workflows.
final class DatabaseCleaner {



	/**
	 * Public, read-only view of the invalid-meta cleanup's protection list.
	 *
	 * Exists so the list can be asserted against without reflection; the
	 * private, filtered mechanism below is unchanged.
	 *
	 * @return array<string> Array of protected meta key strings
	 */
	public static function valid_meta_keys(): array {
		return self::get_valid_meta_keys();
	}

	/**
	 * Meta keys the invalid-meta cleanup must NEVER delete.
	 *
	 * This is a PROTECTION list, not a deletion list. find_invalid_meta_keys()
	 * uses it as `meta_key LIKE '_mhm%' AND meta_key NOT IN (<this list>)`
	 * across the entire postmeta table -- it is scoped to no post type -- and
	 * cleanup_invalid_meta_keys( false ) DELETEs every row it returns. A key
	 * that is missing here is therefore destroyed on the next cleanup run.
	 *
	 * Because omission costs live data and over-inclusion costs nothing but an
	 * unswept row, membership is deliberately generous: every meta-key literal
	 * that appears in either this plugin's or the Pro add-on's source is listed,
	 * whether it is written today, only read for backward compatibility, or
	 * lives in a different meta table entirely. The cleanup ships in Lite but
	 * deletes Pro's rows too, so Pro's keys are not optional here.
	 *
	 * DatabaseCleanerAllowlistTest re-derives the same inventory from the source
	 * tree on every run, so a newly introduced key fails the build instead of
	 * silently becoming deletable.
	 *
	 * @return array<string> Array of valid meta key strings
	 */
	private static function get_valid_meta_keys(): array {
		$valid_keys = array_merge(
			self::static_meta_keys(),
			self::legacy_meta_keys(),
			self::runtime_custom_field_meta_keys()
		);

		/**
		 * Filter: Allow addons and third-party plugins to add custom valid meta keys
		 *
		 * This is CRITICAL to prevent DatabaseCleaner from deleting valid meta keys
		 * added by addons or custom implementations.
		 *
		 * @param array<string> $valid_keys Array of valid meta key strings
		 * @return array Modified valid meta keys array
		 *
		 * @example
		 * add_filter('mhmrentiva_valid_meta_keys', function($keys) {
		 *     $keys[] = '_mhmrentiva_custom_addon_meta';
		 *     $keys[] = '_mhmrentiva_payment_custom_field';
		 *     return $keys;
		 * });
		 */
		$filtered = apply_filters( 'mhmrentiva_valid_meta_keys', $valid_keys );

		// The filter may ADD protection, never remove it. On a destructive
		// operation an extension point that can shrink the protection list turns
		// any third party's bug -- a filter that returns the wrong variable, or
		// an empty array on an early return -- into deletion of this plugin's
		// data. Unioning the built-in list back in keeps the extension point
		// useful and makes that failure mode impossible.
		return array_values(
			array_unique(
				array_merge( $valid_keys, array_filter( (array) $filtered, 'is_string' ) )
			)
		);
	}

	/**
	 * The pre-6.0.0 spelling of every key in static_meta_keys().
	 *
	 * 🔴 THE TRANSITION WINDOW. The 6.0.0 rename moves this plugin's meta keys
	 * from the pre-rename prefixes onto the single-token one, but the code and
	 * the DATABASE do not move at the same instant: the code changes when the
	 * plugin file updates, the rows change when Görev 13's migration runs, and
	 * between those two moments a site holds OLD rows while running NEW code.
	 * The list above is the list the cleanup PROTECTS -- a `NOT IN (list)`
	 * against a prefix LIKE, unscoped across the whole postmeta table -- and a
	 * row in EITHER spelling still matches that LIKE. So a protection list
	 * holding only the post-rename spelling makes every un-migrated row
	 * unprotected, and the next cleanup run deletes the site's entire booking,
	 * vehicle and payment meta.
	 *
	 * (This paragraph deliberately names no prefix literally. The sweep that
	 * created this situation also rewrote the first version of it, leaving the
	 * only prose explaining the most dangerous invariant in the file reading
	 * "moves the keys from X to X" -- which is worse than no comment, because it
	 * looks like an explanation.)
	 *
	 * Both spellings therefore have to be live simultaneously, for as long as any
	 * site can still be un-migrated -- which is forever, since nothing forces a
	 * site to run the migration before running the cleanup.
	 *
	 * Derived rather than hand-listed, so it cannot drift from the list above.
	 * The derivation runs the map's prefix rules BACKWARDS and keeps EVERY
	 * pre-image, not the single "most likely" one: '_mhmrentiva_booking_id' could
	 * have been '_mhmrentiva_booking_id', '_mhmrentiva_booking_id', '_rentiva_booking_id'
	 * or '_booking_id', and there is no way to tell which from the new name alone.
	 * Keeping all four is correct here precisely because this list's errors are
	 * asymmetric: an over-inclusion leaves one row unswept, an omission destroys
	 * live data.
	 *
	 * @return array<string> Array of legacy meta key strings.
	 */
	/**
	 * The pre-6.0.0 name of an option, or null if the map does not know it.
	 *
	 * Unlike the meta-key direction above this is unambiguous: OPTIONS is an
	 * exact old => new table whose uniqueness G-C mode 1 enforces, so a reverse
	 * lookup has at most one answer.
	 *
	 * @param string $new_name Current option name.
	 * @return string|null Legacy name, or null.
	 */
	private static function legacy_option_name( string $new_name ): ?string {
		$old = array_search( $new_name, PrefixMigrationMap::OPTIONS, true );

		return false === $old ? null : (string) $old;
	}

	/**
	 * Every OLD prefix that the map rewrites into $new_prefix.
	 *
	 * @param string $new_prefix Post-rename prefix.
	 * @return array<int,string> Legacy prefixes.
	 */
	private static function legacy_prefixes_for( string $new_prefix ): array {
		$rules = array_merge(
			PrefixMigrationMap::POSTMETA_PREFIX_RULES,
			PrefixMigrationMap::USERMETA_PREFIX_RULES
		);

		$legacy = array();
		foreach ( $rules as $old => $new ) {
			if ( $new === $new_prefix && $old !== $new_prefix ) {
				$legacy[] = $old;
			}
		}

		return array_values( array_unique( $legacy ) );
	}

	private static function legacy_meta_keys(): array {
		$rules = array_merge(
			PrefixMigrationMap::POSTMETA_PREFIX_RULES,
			PrefixMigrationMap::USERMETA_PREFIX_RULES
		);

		$legacy = array();
		foreach ( self::static_meta_keys() as $key ) {
			foreach ( $rules as $old_prefix => $new_prefix ) {
				if ( str_starts_with( $key, $new_prefix ) ) {
					$legacy[] = $old_prefix . substr( $key, strlen( $new_prefix ) );
				}
			}
		}

		return array_values( array_unique( $legacy ) );
	}

	/**
	 * The statically known meta keys, derived from a literal scan of both plugins.
	 *
	 * @return array<string> Array of valid meta key strings
	 */
	private static function static_meta_keys(): array {
		return array(
			'_mhmrentiva_additional_services_price',
			'_mhmrentiva_addon_details',
			'_mhmrentiva_addon_pricing_type',
			'_mhmrentiva_addon_total',
			'_mhmrentiva_attachments',
			'_mhmrentiva_auto_cancelled',
			'_mhmrentiva_auto_cancelled_reason',
			'_mhmrentiva_auto_created',
			'_mhmrentiva_blocked_dates',
			'_mhmrentiva_blocked_dates_notes',
			'_mhmrentiva_booking_blocked_dates',
			'_mhmrentiva_booking_created',
			'_mhmrentiva_booking_data',
			'_mhmrentiva_booking_history',
			'_mhmrentiva_booking_id',
			'_mhmrentiva_booking_logs',
			'_mhmrentiva_booking_payment_type',
			'_mhmrentiva_booking_pending',
			'_mhmrentiva_booking_price',
			'_mhmrentiva_booking_type',
			'_mhmrentiva_bypass_reason',
			'_mhmrentiva_cancellation_data',
			'_mhmrentiva_cancellation_deadline',
			'_mhmrentiva_cancellation_policy',
			'_mhmrentiva_client_ip',
			'_mhmrentiva_contact_email',
			'_mhmrentiva_contact_name',
			'_mhmrentiva_contact_phone',
			'_mhmrentiva_cooling_policy_version',
			'_mhmrentiva_created_by',
			'_mhmrentiva_created_via',
			'_mhmrentiva_custom_details',
			'_mhmrentiva_customer_email',
			'_mhmrentiva_customer_first_name',
			'_mhmrentiva_customer_id',
			'_mhmrentiva_customer_last_name',
			'_mhmrentiva_customer_name',
			'_mhmrentiva_customer_phone',
			'_mhmrentiva_customer_user_id',
			'_mhmrentiva_deposit',
			'_mhmrentiva_deposit_amount',
			'_mhmrentiva_deposit_percentage',
			'_mhmrentiva_deposit_type',
			'_mhmrentiva_details_order',
			'_mhmrentiva_dropoff_date',
			'_mhmrentiva_dropoff_time',
			'_mhmrentiva_email_context',
			'_mhmrentiva_email_key',
			'_mhmrentiva_email_status',
			'_mhmrentiva_email_subject',
			'_mhmrentiva_email_to',
			'_mhmrentiva_end_date',
			'_mhmrentiva_end_time',
			'_mhmrentiva_end_ts',
			'_mhmrentiva_equipment_order',
			'_mhmrentiva_features_order',
			'_mhmrentiva_gallery_images',
			'_mhmrentiva_guests',
			'_mhmrentiva_ip_address',
			'_mhmrentiva_is_read',
			'_mhmrentiva_is_remaining_payment',
			'_mhmrentiva_is_transfer',
			'_mhmrentiva_layout_audit_log',
			'_mhmrentiva_layout_hash',
			'_mhmrentiva_layout_hash_previous',
			'_mhmrentiva_layout_manifest',
			'_mhmrentiva_layout_manifest_previous',
			'_mhmrentiva_layout_version_timestamp',
			'_mhmrentiva_layout_version_timestamp_previous',
			'_mhmrentiva_listing_action',
			'_mhmrentiva_listing_vehicle_id',
			'_mhmrentiva_lock_status',
			'_mhmrentiva_log_action',
			'_mhmrentiva_log_amount_kurus',
			'_mhmrentiva_log_booking_id',
			'_mhmrentiva_log_category',
			'_mhmrentiva_log_code',
			'_mhmrentiva_log_context',
			'_mhmrentiva_log_currency',
			'_mhmrentiva_log_customer_id',
			'_mhmrentiva_log_execution_time',
			'_mhmrentiva_log_gateway',
			'_mhmrentiva_log_ip_address',
			'_mhmrentiva_log_level',
			'_mhmrentiva_log_memory_usage',
			'_mhmrentiva_log_message',
			'_mhmrentiva_log_oid',
			'_mhmrentiva_log_status',
			'_mhmrentiva_log_user_agent',
			'_mhmrentiva_log_user_id',
			'_mhmrentiva_log_vehicle_id',
			'_mhmrentiva_message_category',
			'_mhmrentiva_message_priority',
			'_mhmrentiva_message_status',
			'_mhmrentiva_message_type',
			'_mhmrentiva_offline_receipt_id',
			'_mhmrentiva_order_id',
			'_mhmrentiva_original_order_id',
			'_mhmrentiva_parent_message_id',
			'_mhmrentiva_payment_amount',
			'_mhmrentiva_payment_currency',
			'_mhmrentiva_payment_deadline',
			'_mhmrentiva_payment_display',
			'_mhmrentiva_payment_gateway',
			'_mhmrentiva_payment_method',
			'_mhmrentiva_payment_status',
			'_mhmrentiva_payment_type',
			'_mhmrentiva_payout_amount',
			'_mhmrentiva_payout_external_ref',
			'_mhmrentiva_payout_maker_id',
			'_mhmrentiva_payout_rejection_reason',
			'_mhmrentiva_payout_status',
			'_mhmrentiva_pickup_date',
			'_mhmrentiva_pickup_location_id',
			'_mhmrentiva_pickup_time',
			'_mhmrentiva_price_per_day',
			'_mhmrentiva_read_at',
			'_mhmrentiva_receipt_attachment_id',
			'_mhmrentiva_receipt_note',
			'_mhmrentiva_receipt_status',
			'_mhmrentiva_receipt_uploaded_at',
			'_mhmrentiva_receipt_uploaded_by',
			'_mhmrentiva_recipient_id',
			'_mhmrentiva_refund_date',
			'_mhmrentiva_refund_processed_by',
			'_mhmrentiva_refund_reason',
			'_mhmrentiva_refund_requested_at',
			'_mhmrentiva_refund_requested_by',
			'_mhmrentiva_refund_status',
			'_mhmrentiva_refund_txn_id',
			'_mhmrentiva_refunded_amount',
			'_mhmrentiva_release_after',
			'_mhmrentiva_remaining_amount',
			'_mhmrentiva_remaining_order_id',
			'_mhmrentiva_removed_details',
			'_mhmrentiva_rental_days',
			'_mhmrentiva_availability',
			'_mhmrentiva_average_rating',
			'_mhmrentiva_blocked_dates',
			'_mhmrentiva_brand',
			'_mhmrentiva_category',
			'_mhmrentiva_color',
			'_mhmrentiva_confidence_score',
			'_mhmrentiva_customer_email',
			'_mhmrentiva_customer_name',
			'_mhmrentiva_customer_rating',
			'_mhmrentiva_customer_review',
			'_mhmrentiva_daily_price',
			'_mhmrentiva_deposit',
			'_mhmrentiva_doors',
			'_mhmrentiva_engine_power',
			'_mhmrentiva_engine_size',
			'_mhmrentiva_equipment',
			'_mhmrentiva_featured',
			'_mhmrentiva_features',
			'_mhmrentiva_fuel_type',
			'_mhmrentiva_gallery',
			'_mhmrentiva_gallery_images',
			'_mhmrentiva_is_featured',
			'_mhmrentiva_license_plate',
			'_mhmrentiva_location',
			'_mhmrentiva_location_id',
			'_mhmrentiva_mileage',
			'_mhmrentiva_model',
			'_mhmrentiva_model_year',
			'_mhmrentiva_plate',
			'_mhmrentiva_price',
			'_mhmrentiva_price_per_day',
			'_mhmrentiva_price_per_month',
			'_mhmrentiva_price_per_week',
			'_mhmrentiva_rating_average',
			'_mhmrentiva_rating_count',
			'_mhmrentiva_review_approved',
			'_mhmrentiva_seats',
			'_mhmrentiva_transfer_locations',
			'_mhmrentiva_transfer_route_prices',
			'_mhmrentiva_transfer_routes',
			'_mhmrentiva_transmission',
			'_mhmrentiva_vehicle_city',
			'_mhmrentiva_vehicle_id',
			'_mhmrentiva_vehicle_insurance_doc',
			'_mhmrentiva_vehicle_registration_doc',
			'_mhmrentiva_vendor_location_id',
			'_mhmrentiva_welcome_sent',
			'_mhmrentiva_year',
			'_mhmrentiva_return_date',
			'_mhmrentiva_selected_addons',
			'_mhmrentiva_service_type',
			'_mhmrentiva_shortcode',
			'_mhmrentiva_special_notes',
			'_mhmrentiva_start_date',
			'_mhmrentiva_start_time',
			'_mhmrentiva_start_ts',
			'_mhmrentiva_statement_carried_balance',
			'_mhmrentiva_statement_commission_total',
			'_mhmrentiva_statement_currency',
			'_mhmrentiva_statement_emailed_at',
			'_mhmrentiva_statement_generated_at',
			'_mhmrentiva_statement_gross',
			'_mhmrentiva_statement_last_entry_id',
			'_mhmrentiva_statement_lines',
			'_mhmrentiva_statement_net_activity',
			'_mhmrentiva_statement_number',
			'_mhmrentiva_statement_paid',
			'_mhmrentiva_statement_penalties',
			'_mhmrentiva_statement_period_end',
			'_mhmrentiva_statement_period_start',
			'_mhmrentiva_statement_vendor_snapshot',
			'_mhmrentiva_status',
			'_mhmrentiva_thread_id',
			'_mhmrentiva_total_price',
			'_mhmrentiva_transfer_adults',
			'_mhmrentiva_transfer_children',
			'_mhmrentiva_transfer_destination_id',
			'_mhmrentiva_transfer_distance_km',
			'_mhmrentiva_transfer_duration_min',
			'_mhmrentiva_transfer_luggage_big',
			'_mhmrentiva_transfer_luggage_small',
			'_mhmrentiva_transfer_max_luggage_score',
			'_mhmrentiva_transfer_max_pax',
			'_mhmrentiva_transfer_origin_id',
			'_mhmrentiva_transfer_price_multiplier',
			'_mhmrentiva_user_agent',
			'_mhmrentiva_user_id',
			'_mhmrentiva_vehicle_availability',
			'_mhmrentiva_vehicle_base_price',
			'_mhmrentiva_vehicle_blocked_dates',
			'_mhmrentiva_vehicle_cooldown_ends_at',
			'_mhmrentiva_vehicle_deferred_penalty',
			'_mhmrentiva_vehicle_expiry_warning_first_sent',
			'_mhmrentiva_vehicle_expiry_warning_second_sent',
			'_mhmrentiva_vehicle_id',
			'_mhmrentiva_vehicle_lifecycle_status',
			'_mhmrentiva_vehicle_listing_expires_at',
			'_mhmrentiva_vehicle_listing_renewal_count',
			'_mhmrentiva_vehicle_listing_renewed_at',
			'_mhmrentiva_vehicle_listing_started_at',
			'_mhmrentiva_vehicle_max_big_luggage',
			'_mhmrentiva_vehicle_max_small_luggage',
			'_mhmrentiva_vehicle_pause_count_month',
			'_mhmrentiva_vehicle_paused_at',
			'_mhmrentiva_vehicle_penalty_blocked_dates',
			'_mhmrentiva_vehicle_penalty_uuid',
			'_mhmrentiva_vehicle_plate',
			'_mhmrentiva_vehicle_price_per_km',
			'_mhmrentiva_vehicle_service_type',
			'_mhmrentiva_vehicle_status',
			'_mhmrentiva_vehicle_suspended_by_vendor_ban',
			'_mhmrentiva_vehicle_withdrawal_excused',
			'_mhmrentiva_vehicle_withdrawn_at',
			'_mhmrentiva_vehicle_year',
			'_mhmrentiva_vendor_commission_rate',
			'_mhmrentiva_vendor_payout_freeze',
			'_mhmrentiva_wc_order_id',
			'_mhmrentiva_wc_payment_type',
			'_mhmrentiva_woocommerce_order_id',
			'_mhmrentiva_workflow_state',

			// Görev 12 moved four families that the LIKE '_mhm%' pattern could
			// never previously reach -- '_booking_*', '_contact_*', '_rentiva_*'
			// and the visible 'addon_*' keys -- onto the '_mhmrentiva_' prefix.
			// That is exactly the day the entry below anticipated: they are now
			// INSIDE the DELETE's reach for the first time, so every one of them
			// has to be protected explicitly or the next cleanup removes it.
			// Found by the drift gate, not by inspection.
			'_mhmrentiva_booking_customer_email',
			'_mhmrentiva_booking_customer_first_name',
			'_mhmrentiva_booking_customer_name',
			'_mhmrentiva_booking_customer_phone',
			'_mhmrentiva_booking_dropoff_date',
			'_mhmrentiva_booking_dropoff_time',
			'_mhmrentiva_booking_guests',
			'_mhmrentiva_booking_offline_receipt_id',
			'_mhmrentiva_booking_order_id',
			'_mhmrentiva_booking_payment_amount',
			'_mhmrentiva_booking_payment_currency',
			'_mhmrentiva_booking_payment_gateway',
			'_mhmrentiva_booking_payment_status',
			'_mhmrentiva_booking_pickup_date',
			'_mhmrentiva_booking_pickup_time',
			'_mhmrentiva_booking_rental_days',
			'_mhmrentiva_booking_return_date',
			'_mhmrentiva_booking_start_ts',
			'_mhmrentiva_booking_status',
			'_mhmrentiva_booking_total_price',
			'_mhmrentiva_booking_vehicle_id',
			'_mhmrentiva_contact_attachment',
			'_mhmrentiva_contact_company',
			'_mhmrentiva_contact_email',
			'_mhmrentiva_contact_ip_address',
			'_mhmrentiva_contact_name',
			'_mhmrentiva_contact_phone',
			'_mhmrentiva_contact_preferred_date',
			'_mhmrentiva_contact_priority',
			'_mhmrentiva_contact_rating',
			'_mhmrentiva_contact_status',
			'_mhmrentiva_contact_timestamp',
			'_mhmrentiva_contact_type',
			'_mhmrentiva_contact_user_agent',
			'_mhmrentiva_contact_vehicle_id',
			'_mhmrentiva_vehicle_service_type',
			'_mhmrentiva_vendor_avatar_id',
			'_mhmrentiva_vendor_city',
			'_mhmrentiva_vendor_reliability_score',
			'_mhmrentiva_vendor_reliability_updated_at',
			'_mhmrentiva_vendor_score_history',
			'_mhmrentiva_vendor_slug',
			'_mhmrentiva_vendor_slug_history',
			'_mhmrentiva_vendor_status',
			'mhmrentiva_addon_description',
			'mhmrentiva_addon_enabled',
			'mhmrentiva_addon_price',
			'mhmrentiva_addon_required',
			'mhmrentiva_addon_type',
			// Legacy families kept for documentation and for the day the
			// LIKE pattern widens; none of them can match '_mhm%' today.
			'_mhmrentiva_booking_customer_email',
			'_mhmrentiva_booking_customer_name',
			'_mhmrentiva_booking_customer_phone',
			'_mhmrentiva_booking_payment_gateway',
			'_mhmrentiva_booking_payment_status',
			'_mhmrentiva_booking_pickup_date',
			'_mhmrentiva_booking_rental_days',
			'_mhmrentiva_booking_return_date',
			'_mhmrentiva_booking_total_price',
			'_mhmrentiva_booking_vehicle_id',
			'mhmrentiva_addon_description',
			'mhmrentiva_addon_price',
			'mhmrentiva_addon_type',
		);
	}

	/**
	 * Meta keys that only exist at runtime, so no literal scan can find them.
	 *
	 * Vehicle detail/feature/equipment fields are admin-defined: VehicleMeta and
	 * VehicleSubmit store each one as '_mhmrentiva_' . $field_key, where
	 * $field_key comes from these options or from a feature/equipment taxonomy
	 * term. Without this derivation, every custom field an admin has ever added
	 * is "invalid" to the cleanup and gets deleted.
	 *
	 * @return array<string> Array of valid meta key strings
	 */
	private static function runtime_custom_field_meta_keys(): array {
		$field_maps = array();

		// mhmrentiva_vehicle_* hold the standard field definitions (an admin can extend
		// them); mhmrentiva_custom_* hold the fields an admin added outright. Both are
		// read back by VehicleMeta::save_meta() to decide which meta to write.
		foreach ( array(
			'mhmrentiva_vehicle_details',
			'mhmrentiva_vehicle_features',
			'mhmrentiva_vehicle_equipment',
			'mhmrentiva_custom_details',
			'mhmrentiva_custom_features',
			'mhmrentiva_custom_equipment',
		) as $option_name ) {
			$field_maps[] = (array) get_option( $option_name, array() );

			// Transition window: on a site that has not run the 6.0.0 migration
			// yet, the definitions still live under the OLD option name. Reading
			// only the new one yields an empty derivation, and an empty
			// derivation means every admin-defined custom field looks invalid --
			// i.e. deletable. The old name is read too, not instead, because a
			// half-migrated site can legitimately have both.
			$legacy_option = self::legacy_option_name( $option_name );
			if ( null !== $legacy_option ) {
				$field_maps[] = (array) get_option( $legacy_option, array() );
			}
		}

		$field_maps[] = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_taxonomy_features();
		$field_maps[] = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_taxonomy_equipment();

		$keys = array();

		foreach ( $field_maps as $field_map ) {
			foreach ( array_keys( $field_map ) as $field_key ) {
				$field_key = (string) $field_key;
				if ( '' !== $field_key ) {
					$keys[] = '_mhmrentiva_' . $field_key;
					// Same transition window: the ROWS for these fields were
					// written under the old prefix and stay that way until the
					// migration runs, so both spellings must be protected.
					//
					// DERIVED from the map rather than written as literals. The
					// first version of this hardcoded the two pre-rename prefixes
					// -- and the next run of the rename tool swept them straight
					// into duplicates of the line above, silently removing the
					// protection they existed to provide. Anything spelled in a
					// pre-rename prefix is, by construction, something the sweep
					// rewrites; only a derivation survives re-running the tool.
					// (Which is also why this comment names no old prefix.)
					foreach ( self::legacy_prefixes_for( '_mhmrentiva_' ) as $legacy_prefix ) {
						$keys[] = $legacy_prefix . $field_key;
					}
				}
			}
		}

		return $keys;
	}

	/**
	 * Custom-field meta that is live on this site but that nothing can vouch for.
	 *
	 * An empty derivation means two very different things, and
	 * runtime_custom_field_meta_keys() cannot tell them apart: this site has no
	 * custom vehicle fields, or this site's field definitions have been wiped,
	 * reset or re-imported while the '_mhmrentiva_<field>' rows they describe
	 * are still in the database. The
	 * first is ordinary; the second means the derivation has silently stopped
	 * protecting real data, and the caller's next statement is a DELETE.
	 *
	 * Telling them apart is cheap: ask the database whether any
	 * '_mhmrentiva_%' rows exist that the static list does not already cover.
	 * If some do while the derivation is empty, the definitions are gone and the
	 * cleanup must refuse to run rather than delete what it cannot identify.
	 *
	 * @return array<string> Meta keys that would be deleted without cover; empty when it is safe to proceed.
	 */
	private static function unvouched_custom_field_meta_keys(): array {
		global $wpdb;

		if ( array() !== self::runtime_custom_field_meta_keys() ) {
			// The definitions are readable, so the derivation speaks for them.
			return array();
		}

		// Must be judged against the SAME cover the DELETE uses, old spellings
		// included: after the 6.0.0 rename the two families coexist, and a probe
		// that only knows the new one reports every un-migrated row as at risk.
		$static_keys = array_values(
			array_unique( array_merge( self::static_meta_keys(), self::legacy_meta_keys() ) )
		);

		$likes = array();
		foreach ( self::custom_field_prefixes() as $prefix ) {
			$likes[] = $wpdb->esc_like( $prefix ) . '%';
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT DISTINCT meta_key
            FROM {$wpdb->postmeta}
            WHERE ( " . implode( ' OR ', array_fill( 0, count( $likes ), 'meta_key LIKE %s' ) ) . ' )
            AND meta_key NOT IN (' . implode( ',', array_fill( 0, count( $static_keys ), '%s' ) ) . ')
            LIMIT 20
        ',
				array_merge( $likes, $static_keys )
			)
		);
	}

	/**
	 * The prefixes admin-defined vehicle fields are stored under, current first.
	 *
	 * DERIVED, for the same reason legacy_prefixes_for() exists: the previous
	 * version of this probe spelled the pre-rename prefix as a literal, the
	 * rename tool rewrote it into a duplicate of the current one, and the probe
	 * quietly stopped asking about the old family at all -- while the DELETE it
	 * guards still reached those rows. A test caught it; nothing else would have.
	 *
	 * Only the LONGEST legacy prefix is used. Several old prefixes collapse onto
	 * '_mhmrentiva_', but custom fields were only ever written under the longest
	 * of them; including the short ones would make any unrelated meta row look
	 * like an at-risk custom field and abort the cleanup permanently, turning a
	 * safety net into an off switch.
	 *
	 * @return array<int,string>
	 */
	private static function custom_field_prefixes(): array {
		$legacy = self::legacy_prefixes_for( '_mhmrentiva_' );
		usort( $legacy, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );

		$prefixes = array( '_mhmrentiva_' );
		if ( array() !== $legacy ) {
			$prefixes[] = $legacy[0];
		}

		return $prefixes;
	}

	/**
	 * Create cleanup report (pre-backup analysis)
	 */
	public static function analyze_database(): array {
		return array(
			'orphaned_postmeta'  => self::find_orphaned_postmeta(),
			'orphaned_usermeta'  => self::find_orphaned_usermeta(),
			'expired_transients' => self::find_expired_transients(),
			'unused_options'     => self::find_unused_options(),
			'invalid_meta_keys'  => self::find_invalid_meta_keys(),
			'old_logs'           => self::cleanup_old_logs( 30, true ),
			'table_stats'        => self::get_table_stats(),
		);
	}

	/**
	 * Detect orphaned postmeta (post no longer exists but meta does)
	 */
	public static function find_orphaned_postmeta(): array {
		global $wpdb;

		$orphaned = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE %s
            LIMIT 100
        ",
				'_mhm%%'
			),
			ARRAY_A
		);

		return array(
			'count'               => count( $orphaned ),
			'samples'             => array_slice( $orphaned, 0, 10 ),
			'total_size_estimate' => count( $orphaned ) * 200, // bytes estimate
		);
	}

	/**
	 * Detect orphaned usermeta (user no longer exists but meta does)
	 */
	public static function find_orphaned_usermeta(): array {
		global $wpdb;

		$orphaned = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT um.umeta_id, um.user_id, um.meta_key, um.meta_value 
            FROM {$wpdb->usermeta} um
            LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
            WHERE u.ID IS NULL
            AND um.meta_key LIKE %s
            LIMIT 100
        ",
				'mhmrentiva%%'
			),
			ARRAY_A
		);

		return array(
			'count'   => count( $orphaned ),
			'samples' => array_slice( $orphaned, 0, 10 ),
		);
	}

	/**
	 * Detect expired transients
	 */
	public static function find_expired_transients(): array {
		global $wpdb;

		$expired = $wpdb->get_results(
			"
            SELECT o1.option_name, o1.option_value, o2.option_value as timeout
            FROM {$wpdb->options} o1
            INNER JOIN {$wpdb->options} o2 
                ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_mhm%'
            AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
            LIMIT 100
        ",
			ARRAY_A
		);

		return array(
			'count'               => count( $expired ),
			'samples'             => array_slice( $expired, 0, 10 ),
			'total_size_estimate' => $wpdb->get_var(
				"
                SELECT SUM(LENGTH(option_value))
                FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_mhm%'
            "
			),
		);
	}

	/**
	 * Detect unused options
	 */
	public static function find_unused_options(): array {
		global $wpdb;

		// MHM Rentiva options
		// prefix-rename:ignore-start
		$all_options = $wpdb->get_results(
			"
            SELECT option_name, LENGTH(option_value) as size
            FROM {$wpdb->options}
            WHERE ( option_name LIKE 'mhmrentiva%' OR option_name LIKE 'mhm_rentiva%' )
            AND option_name NOT LIKE '_transient%'
        ",
			ARRAY_A
		);
		// prefix-rename:ignore-end

		// Autoload options (unnecessary memory usage)
		// prefix-rename:ignore-start
		$autoload_options = $wpdb->get_results(
			"
            SELECT option_name, LENGTH(option_value) as size
            FROM {$wpdb->options}
            WHERE ( option_name LIKE 'mhmrentiva%' OR option_name LIKE 'mhm_rentiva%' )
            AND autoload = 'yes'
        ",
			ARRAY_A
		);
		// prefix-rename:ignore-end

		return array(
			'total_options'    => count( $all_options ),
			'autoload_options' => count( $autoload_options ),
			'autoload_size'    => array_sum( array_column( $autoload_options, 'size' ) ),
			'samples'          => array_slice( $all_options, 0, 20 ),
		);
	}

	/**
	 * Detect meta keys the protection list does not cover.
	 *
	 * There is no VALID_META_KEYS constant; the list comes from
	 * get_valid_meta_keys() and is used here with NOT IN, so it protects rather
	 * than selects. The scan spans the whole postmeta table and is limited to
	 * neither this plugin's post types nor its own rows.
	 */
	public static function find_invalid_meta_keys(): array {
		global $wpdb;

		$valid_keys = self::get_valid_meta_keys();

		$invalid_keys = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT DISTINCT meta_key, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE %s
            AND meta_key NOT IN (" . implode( ',', array_fill( 0, count( $valid_keys ), '%s' ) ) . ')
            GROUP BY meta_key
            ORDER BY count DESC
            LIMIT 50
        ',
				// esc_like() escapes the leading underscore. Unescaped, '_' is
				// MySQL's single-character wildcard, so '_mhm%' also matched
				// 'Xmhm...' for any X -- rows belonging to nobody in particular,
				// on a statement that DELETEs. Task 11 deferred this here because
				// it is one half of the rename hazard. Escaping only ever NARROWS
				// what the DELETE can reach, which is the safe direction.
				array_merge( array( $wpdb->esc_like( '_mhm' ) . '%' ), $valid_keys )
			),
			ARRAY_A
		);

		return array(
			'count' => count( $invalid_keys ),
			'keys'  => $invalid_keys,
		);
	}

	/**
	 * DELETE every postmeta row whose key the protection list does not cover.
	 *
	 * Destructive with $dry_run = false. Rows are copied to a timestamped backup
	 * table first, but the only thing standing between live data and this DELETE
	 * is get_valid_meta_keys(), so a key missing from that list is data lost.
	 */
	public static function cleanup_invalid_meta_keys( bool $dry_run = true ): array {
		global $wpdb;

		// Fail closed. If the custom-field definitions cannot vouch for meta that
		// is demonstrably live on this site, delete nothing and say so: a wrong
		// answer here is destroyed customer data, and "do nothing" is always the
		// recoverable half of the mistake.
		$unvouched = self::unvouched_custom_field_meta_keys();

		if ( array() !== $unvouched ) {
			return array(
				'dry_run'      => $dry_run,
				'aborted'      => true,
				'deleted'      => 0,
				'keys_removed' => array(),
				'at_risk_keys' => $unvouched,
			);
		}

		// Get invalid meta keys first
		$invalid_data = self::find_invalid_meta_keys();

		if ( $dry_run ) {
			return array(
				'dry_run'      => true,
				'would_delete' => array_sum( array_column( $invalid_data['keys'] ?? array(), 'count' ) ),
				'keys'         => $invalid_data['keys'] ?? array(),
			);
		}

		if ( empty( $invalid_data['keys'] ) ) {
			return array(
				'dry_run'      => false,
				'deleted'      => 0,
				'keys_removed' => array(),
			);
		}

		// Create backup table
		$backup_table = $wpdb->prefix . 'mhmrentiva_postmeta_backup_invalid_' . gmdate( 'Ymd_His' );
		$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $backup_table, $wpdb->postmeta ) );

		// Extract meta keys
		$invalid_keys = array_column( $invalid_data['keys'], 'meta_key' );

		// Backup invalid meta data
		$wpdb->query(
			$wpdb->prepare(
				"
            INSERT INTO %i
            SELECT pm.*
            FROM {$wpdb->postmeta} pm
            WHERE pm.meta_key IN (" . implode( ',', array_fill( 0, count( $invalid_keys ), '%s' ) ) . ')
        ',
				array_merge( array( $backup_table ), $invalid_keys )
			)
		);

		// Delete invalid meta keys
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"
            DELETE pm
            FROM {$wpdb->postmeta} pm
            WHERE pm.meta_key IN (" . implode( ',', array_fill( 0, count( $invalid_keys ), '%s' ) ) . ')
        ',
				$invalid_keys
			)
		);

		return array(
			'dry_run'      => false,
			'deleted'      => (int) $deleted,
			'keys_removed' => $invalid_keys,
			'backup_table' => $backup_table,
		);
	}

	/**
	 * Table statistics
	 */
	public static function get_table_stats(): array {
		global $wpdb;

		$tables = array(
			'payment_log'        => $wpdb->prefix . 'mhmrentiva_payment_log',
			'sessions'           => $wpdb->prefix . 'mhmrentiva_sessions',
			'transfer_routes'    => $wpdb->prefix . 'rentiva_transfer_routes',
			'transfer_locations' => $wpdb->prefix . 'rentiva_transfer_locations',
			'queue'              => $wpdb->prefix . 'mhmrentiva_queue',
			'ratings'            => $wpdb->prefix . 'mhmrentiva_ratings',
			'report_queue'       => $wpdb->prefix . 'mhmrentiva_background_jobs',
			'message_logs'       => $wpdb->prefix . 'mhmrentiva_message_logs',
		);

		$stats = array();

		foreach ( $tables as $key => $table_name ) {
			// Check if table exists
			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'
                SHOW TABLES LIKE %s
            ',
					$table_name
				)
			);

			if ( ! $table_exists ) {
				$stats[ $key ] = array( 'exists' => false );
				continue;
			}

			// Get table information
			$row_count  = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
			$table_size = $wpdb->get_var(
				$wpdb->prepare(
					'
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = %s
            ',
					$table_name
				)
			);

			$stats[ $key ] = array(
				'exists'     => true,
				'rows'       => (int) $row_count,
				'size_mb'    => (float) $table_size,
				'table_name' => $table_name,
			);
		}

		return $stats;
	}

	/**
	 * Clean orphaned postmeta (WITH BACKUP)
	 */
	public static function cleanup_orphaned_postmeta( bool $dry_run = true ): array {
		global $wpdb;

		// Get orphaned meta count first
		$count = $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE '_mhm%'
        "
		);

		if ( ! $dry_run && $count > 0 ) {
			// Create backup table
			$backup_table = $wpdb->prefix . 'mhmrentiva_postmeta_backup_' . gmdate( 'Ymd_His' );
			$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $backup_table, $wpdb->postmeta ) );

			// Backup orphaned data
			$wpdb->query(
				$wpdb->prepare(
					"
                INSERT INTO %i
                SELECT pm.*
                FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.ID IS NULL
                AND pm.meta_key LIKE %s
            ",
					$backup_table,
					'_mhm%'
				)
			);

			// Delete orphaned meta
			$deleted = $wpdb->query(
				"
                DELETE pm
                FROM `{$wpdb->postmeta}` pm
                LEFT JOIN `{$wpdb->posts}` p ON pm.post_id = p.ID
                WHERE p.ID IS NULL
                AND pm.meta_key LIKE '_mhm%'
            "
			);

			return array(
				'dry_run'      => false,
				'deleted'      => $deleted,
				'backup_table' => $backup_table ?? null,
			);
		}

		return array(
			'dry_run'      => true,
			'would_delete' => (int) $count,
			'action'       => 'Set dry_run=false to execute cleanup',
		);
	}

	/**
	 * Clean expired transients
	 */
	public static function cleanup_expired_transients( bool $dry_run = true ): array {
		global $wpdb;

		// Count expired transients
		$count = $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$wpdb->options} o1
            INNER JOIN {$wpdb->options} o2 
                ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_mhm%'
            AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
        "
		);

		if ( $dry_run ) {
			return array(
				'dry_run'      => true,
				'would_delete' => (int) $count,
			);
		}

		// Execute cleanup even if count is 0 (to ensure consistency)
		if ( $count > 0 ) {
			// Delete expired transients
			$deleted = $wpdb->query(
				"
                DELETE o1, o2
                FROM {$wpdb->options} o1
                INNER JOIN {$wpdb->options} o2 
                    ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
                WHERE o1.option_name LIKE '_transient_mhm%'
                AND CAST(o2.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
            "
			);

			// Calculate size freed (approximate)
			$size_freed = $wpdb->get_var(
				"
                SELECT SUM(LENGTH(option_value))
                FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_mhm%'
            "
			);

			return array(
				'dry_run'          => false,
				'deleted'          => (int) $deleted,
				'size_freed_bytes' => (int) $size_freed,
			);
		}

		// No expired transients to clean
		return array(
			'dry_run'          => false,
			'deleted'          => 0,
			'size_freed_bytes' => 0,
		);
	}

	/**
	 * Clean old log records
	 */
	public static function cleanup_old_logs( int $days = 30, bool $dry_run = true ): array {
		global $wpdb;

		$tables = array(
			'queue'        => $wpdb->prefix . 'mhmrentiva_queue',
			'report_queue' => $wpdb->prefix . 'mhmrentiva_background_jobs',
			'message_logs' => $wpdb->prefix . 'mhmrentiva_message_logs',
		);

		$results     = array();
		$cutoff_date = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$days} days" ) );

		foreach ( $tables as $key => $table_name ) {
			// Check if table exists
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

			if ( ! $table_exists ) {
				$results[ $key ] = array( 'exists' => false );
				continue;
			}

			// Count old records
			$date_column = ( $key === 'queue' ) ? 'created_at' : 'created_at';
			// Security: whitelist allow columns for direct SQL injection prevention
			$allowed_columns = array( 'created_at' );
			if ( ! in_array( $date_column, $allowed_columns, true ) ) {
				continue;
			}

			$count = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i < %s', $table_name, $date_column, $cutoff_date )
			);

			if ( ! $dry_run && $count > 0 ) {
				// Create backup
				$backup_table = $table_name . '_backup_' . gmdate( 'Ymd_His' );

				$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $backup_table, $table_name ) );
				$wpdb->query(
					$wpdb->prepare(
						'INSERT INTO %i SELECT * FROM %i WHERE %i < %s',
						$backup_table,
						$table_name,
						$date_column,
						$cutoff_date
					)
				);

				// Delete old records
				$deleted = $wpdb->query(
					$wpdb->prepare( 'DELETE FROM %i WHERE %i < %s', $table_name, $date_column, $cutoff_date )
				);

				$results[ $key ] = array(
					'exists'       => true,
					'deleted'      => $deleted,
					'backup_table' => $backup_table,
				);
			} else {
				$results[ $key ] = array(
					'exists'       => true,
					'would_delete' => (int) $count,
				);
			}
		}

		return $results;
	}

	/**
	 * Autoload options optimization
	 */
	public static function optimize_autoload_options( bool $dry_run = true ): array {
		global $wpdb;

		// Find large autoload options
		// prefix-rename:ignore-start
		$large_autoload = $wpdb->get_results(
			"
            SELECT option_name, LENGTH(option_value) as size
            FROM {$wpdb->options}
            WHERE ( option_name LIKE 'mhmrentiva%' OR option_name LIKE 'mhm_rentiva%' )
            AND autoload = 'yes'
            AND LENGTH(option_value) > 1024
            ORDER BY size DESC
            LIMIT 20
        ",
			ARRAY_A
		);
		// prefix-rename:ignore-end

		if ( $dry_run ) {
			return array(
				'dry_run'      => true,
				'would_update' => count( $large_autoload ),
				'options'      => $large_autoload,
			);
		}

		// Execute optimization
		if ( ! empty( $large_autoload ) ) {
			$updated      = 0;
			$memory_saved = 0;
			foreach ( $large_autoload as $option ) {
				// Set large options to autoload=no
				$update_result = $wpdb->update(
					$wpdb->options,
					array( 'autoload' => 'no' ),
					array( 'option_name' => $option['option_name'] ),
					array( '%s' ),
					array( '%s' )
				);
				if ( $update_result !== false ) {
					++$updated;
					$memory_saved += (int) ( $option['size'] ?? 0 );
				}
			}

			return array(
				'dry_run'            => false,
				'updated'            => $updated,
				'memory_saved_bytes' => $memory_saved,
			);
		}

		// No large autoload options to optimize
		return array(
			'dry_run'            => false,
			'updated'            => 0,
			'memory_saved_bytes' => 0,
		);
	}

	/**
	 * Optimize database tables
	 */
	public static function optimize_tables(): array {
		global $wpdb;

		$tables = array(
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->options,
			$wpdb->prefix . 'mhmrentiva_queue',
			$wpdb->prefix . 'mhmrentiva_ratings',
			$wpdb->prefix . 'mhmrentiva_background_jobs',
			$wpdb->prefix . 'mhmrentiva_message_logs',
		);

		$results = array();

		foreach ( $tables as $table ) {
			// Check if table exists
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( ! $table_exists ) {
				continue;
			}

			$start_time     = microtime( true );
			$result         = $wpdb->query( $wpdb->prepare( 'OPTIMIZE TABLE %i', $table ) );
			$execution_time = microtime( true ) - $start_time;

			$results[ $table ] = array(
				'success'           => $result !== false,
				'execution_time_ms' => round( $execution_time * 1000, 2 ),
			);
		}

		return $results;
	}

	/**
	 * Full cleanup process (ALL CLEANUP OPERATIONS)
	 */
	public static function full_cleanup( bool $dry_run = true, array $options = array() ): array {
		$default_options = array(
			'orphaned_postmeta'  => true,
			'orphaned_usermeta'  => true,
			'expired_transients' => true,
			'old_logs_days'      => 30,
			'optimize_autoload'  => true,
			'optimize_tables'    => false, // Can be slow
		);

		$options = array_merge( $default_options, $options );

		$results = array(
			'dry_run'    => $dry_run,
			'timestamp'  => current_time( 'mysql' ),
			'operations' => array(),
		);

		// Clean orphaned postmeta
		if ( $options['orphaned_postmeta'] ) {
			$results['operations']['orphaned_postmeta'] = self::cleanup_orphaned_postmeta( $dry_run );
		}

		// Clean orphaned usermeta (usually none but let's check)
		if ( $options['orphaned_usermeta'] ) {
			$orphaned_usermeta                          = self::find_orphaned_usermeta();
			$results['operations']['orphaned_usermeta'] = array(
				'checked' => true,
				'found'   => $orphaned_usermeta['count'],
			);
		}

		// Clean expired transients
		if ( $options['expired_transients'] ) {
			$results['operations']['expired_transients'] = self::cleanup_expired_transients( $dry_run );
		}

		// Clean old logs
		if ( $options['old_logs_days'] ) {
			$results['operations']['old_logs'] = self::cleanup_old_logs( $options['old_logs_days'], $dry_run );
		}

		// Optimize autoload
		if ( $options['optimize_autoload'] ) {
			$results['operations']['autoload_optimization'] = self::optimize_autoload_options( $dry_run );
		}

		// Optimize tables
		if ( $options['optimize_tables'] && ! $dry_run ) {
			$results['operations']['table_optimization'] = self::optimize_tables();
		}

		return $results;
	}

	/**
	 * Render cleanup report HTML
	 */
	public static function render_cleanup_report( array $analysis ): string {
		ob_start();
		?>
		<div class="mhm-database-cleanup-report">
			<h3><?php esc_html_e( 'Database Cleanup Report', 'mhm-rentiva' ); ?></h3>

			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Category', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Count', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Size', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Action', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Orphaned Post Meta', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['orphaned_postmeta']['count'] ); ?></td>
						<td><?php echo esc_html( size_format( $analysis['orphaned_postmeta']['total_size_estimate'] ) ); ?></td>
						<td>
							<?php if ( $analysis['orphaned_postmeta']['count'] > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Expired Transients', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['expired_transients']['count'] ); ?></td>
						<td><?php echo esc_html( size_format( $analysis['expired_transients']['total_size_estimate'] ) ); ?></td>
						<td>
							<?php if ( $analysis['expired_transients']['count'] > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Autoload Options', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['unused_options']['autoload_options'] ); ?></td>
						<td><?php echo esc_html( size_format( $analysis['unused_options']['autoload_size'] ) ); ?></td>
						<td>
							<?php if ( $analysis['unused_options']['autoload_size'] > 10240 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Optimization Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Optimized', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Invalid Meta Keys', 'mhm-rentiva' ); ?></td>
						<td><?php echo esc_html( $analysis['invalid_meta_keys']['count'] ); ?></td>
						<td>-</td>
						<td>
							<?php if ( $analysis['invalid_meta_keys']['count'] > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
								<button type="button" class="button button-small" id="mhm-cleanup-invalid-meta-btn" style="margin-left: 10px;">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
								</button>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'All Valid', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Log Records (>30 days)', 'mhm-rentiva' ); ?></td>
						<td>
							<?php
							$log_count = 0;
							foreach ( $analysis['old_logs'] as $table_log ) {
								$log_count += ( $table_log['would_delete'] ?? 0 );
							}
							echo esc_html( (string) $log_count );
							?>
						</td>
						<td>-</td>
						<td>
							<?php if ( $log_count > 0 ) : ?>
								<span class="dashicons dashicons-warning" style="color: orange;"></span>
								<?php esc_html_e( 'Cleanup Recommended', 'mhm-rentiva' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Clean', 'mhm-rentiva' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Custom Tables', 'mhm-rentiva' ); ?></h4>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Table', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Exists', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Rows', 'mhm-rentiva' ); ?></th>
						<th><?php esc_html_e( 'Size (MB)', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $analysis['table_stats'] as $key => $stats ) : ?>
						<tr>
							<td><?php echo esc_html( $key ); ?></td>
							<td>
								<?php if ( $stats['exists'] ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color: red;"></span>
									<button type="button" class="button button-small mhm-repair-table-btn" data-table="<?php echo esc_attr( $key ); ?>" style="margin-left: 5px;">
										<?php esc_html_e( 'Repair/Create', 'mhm-rentiva' ); ?>
									</button>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $stats['rows'] ?? '-' ); ?></td>
							<td><?php echo esc_html( $stats['size_mb'] ?? '-' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			</tbody>
			</table>
		</div>
		<?php
		// Repair-table button behavior is delegated in assets/js/admin/database-cleanup.js.
		return ob_get_clean();
	}

	/**
	 * Render cleanup buttons
	 */
	public static function render_cleanup_buttons(): string {
		ob_start();
		?>
		<div class="mhm-cleanup-actions">
			<button type="button" class="button button-primary" id="mhm-analyze-db-btn">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Analyze Database', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-cleanup-orphaned-btn">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clean Orphaned Meta', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-cleanup-transients-btn">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Clean Expired Transients', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-optimize-autoload-btn">
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'Optimize Autoload', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button" id="mhm-optimize-tables-btn">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'Optimize Tables', 'mhm-rentiva' ); ?>
			</button>

			<button type="button" class="button button-secondary" id="mhm-cleanup-logs-btn">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e( 'Purge Old Logs', 'mhm-rentiva' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * List all backup tables
	 */
	public static function list_backups(): array {
		global $wpdb;

		// Both spellings, and that is not cosmetic.
		//
		// Backup tables carry the PRE-rename prefix in their name, and the
		// physical table keeps that name forever: it is not in
		// PrefixMigrationMap::TABLES, so Görev 13 never renames it either. A
		// new-prefix-only pattern therefore cannot see ANY backup taken before
		// 6.0.0 -- and because is_managed_backup_table() decides membership by
		// enumerating this list, and export_backup_to_sql() gates on that, such a
		// backup becomes unlistable, unexportable and UNRESTORABLE. It is also the
		// only copy of the postmeta the cleanup deleted.
		// prefix-rename:ignore-start
		$backup_tables = array_merge(
			(array) $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}mhmrentiva_%_backup%'" ),
			(array) $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}mhm_%_backup%'" )
		);
		// prefix-rename:ignore-end
		$backup_tables = array_values( array_unique( array_filter( $backup_tables ) ) );

		$backups = array();

		foreach ( $backup_tables as $table_name ) {
			// Get table info
			$row_count  = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
			$table_size = $wpdb->get_var(
				$wpdb->prepare(
					'
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = %s
            ',
					$table_name
				)
			);

			// Parse backup type from table name
			$backup_type = 'unknown';
			if ( strpos( $table_name, 'merge_losers_backup_' ) !== false ) {
				// Written by the 6.0.0 prefix migration before it discards the
				// losing spelling of a merged meta key. Typed explicitly rather
				// than falling into 'custom', because restore_backup() has to be
				// able to recognise and refuse it -- see there.
				$backup_type = 'merge_losers';
			} elseif ( strpos( $table_name, 'postmeta_backup_invalid' ) !== false ) {
				$backup_type = 'invalid_meta';
			} elseif ( strpos( $table_name, 'postmeta_backup_' ) !== false ) {
				$backup_type = 'orphaned_meta';
			} elseif ( strpos( $table_name, '_backup_' ) !== false ) {
				$backup_type = 'custom';
			}

			// Extract date from table name (format: YYYYMMDD_HHMMSS)
			$date_match = array();
			preg_match( '/(\d{8}_\d{6})/', $table_name, $date_match );
			$backup_date = ! empty( $date_match[1] ) ? $date_match[1] : 'unknown';

			$backups[] = array(
				'table_name' => $table_name,
				'type'       => $backup_type,
				'date'       => $backup_date,
				'rows'       => (int) $row_count,
				'size_mb'    => (float) $table_size,
			);
		}

		// Sort by date (newest first)
		usort(
			$backups,
			function ( $a, $b ) {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		return $backups;
	}

	/**
	 * Is this one of the backup tables this plugin created?
	 *
	 * The maintenance endpoints take a table name from the request and
	 * interpolate it into SHOW CREATE TABLE / SELECT / INSERT / DROP. Checking
	 * that the table EXISTS is not a scope check -- every table in the database
	 * exists, so an existence test admitted wp_users as readily as one of our
	 * own backups. Membership is decided against the same enumeration the UI
	 * lists, which is scoped to the plugin's `{prefix}mhmrentiva_%_backup%` naming,
	 * and compared as a whole string so no LIKE wildcard can widen it.
	 */
	public static function is_managed_backup_table( string $table_name ): bool {
		if ( '' === $table_name ) {
			return false;
		}

		foreach ( self::list_backups() as $backup ) {
			if ( (string) ( $backup['table_name'] ?? '' ) === $table_name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate SQL export for a backup table
	 */
	public static function export_backup_to_sql( string $table_name ): string {
		global $wpdb;

		if ( ! self::is_managed_backup_table( $table_name ) ) {
			return '';
		}

		// Get table structure
		$create_table = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table_name ), ARRAY_A );
		$sql          = "-- Backup Export: {$table_name}\n";
		$sql         .= '-- Generated: ' . gmdate( 'Y-m-d H:i:s' ) . "\n\n";
		$sql         .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
		$sql         .= $create_table['Create Table'] . ";\n\n";

		// Get all rows
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table_name ), ARRAY_A );

		if ( ! empty( $rows ) ) {
			$sql   .= "INSERT INTO `{$table_name}` VALUES\n";
			$values = array();
			foreach ( $rows as $row ) {
				$row_values = array();
				foreach ( $row as $value ) {
					if ( $value === null ) {
						$row_values[] = 'NULL';
					} else {
						$row_values[] = "'" . esc_sql( $value ) . "'";
					}
				}
				$values[] = '(' . implode( ',', $row_values ) . ')';
			}
			$sql .= implode( ",\n", $values ) . ";\n";
		}

		return $sql;
	}

	/**
	 * Restore backup to original table
	 */
	public static function restore_backup( string $backup_table ): array {
		global $wpdb;

		if ( ! self::is_managed_backup_table( $backup_table ) ) {
			return array(
				'success' => false,
				'message' => __( 'Backup table not found', 'mhm-rentiva' ),
			);
		}

		// The 6.0.0 merge-loser backup has NO single target table and must never
		// reach the generic branch below.
		//
		// Its rows span wp_postmeta AND wp_usermeta, and only its `family` column
		// says which row belongs where. The fallback below defaults
		// $target_table to wp_postmeta and then runs
		// `INSERT INTO <target> SELECT * FROM <backup>`, so restoring this table
		// blind would write vendor user-meta into wp_postmeta keyed by user id as
		// though it were a post id -- inventing rows on unrelated posts. The
		// export path handles this table fine (it is schema-agnostic); recovery is
		// export, read, and put the values back deliberately.
		if ( strpos( $backup_table, 'merge_losers_backup_' ) !== false ) {
			return array(
				'success' => false,
				'message' => __( 'This backup holds discarded meta from two different tables and cannot be restored in place. Export it and reapply the rows you need.', 'mhm-rentiva' ),
			);
		}

		// Determine target table based on backup type
		$target_table = $wpdb->postmeta; // Default

		if ( strpos( $backup_table, 'postmeta_backup_invalid' ) !== false ) {
			$target_table = $wpdb->postmeta;
		} elseif ( strpos( $backup_table, 'postmeta_backup_' ) !== false ) {
			$target_table = $wpdb->postmeta;
		} else {
			// Try to determine from backup table name
			// Format: prefix_table_backup_YYYYMMDD_HHMMSS
			$parts = explode( '_backup_', $backup_table );
			if ( count( $parts ) === 2 ) {
				$possible_table = $parts[0];
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $possible_table ) ) ) {
					$target_table = $possible_table;
				}
			}
		}

		// Restore data
		$restored = $wpdb->query(
			$wpdb->prepare(
				'
            INSERT INTO %i
            SELECT * FROM %i
            ON DUPLICATE KEY UPDATE
                meta_id = VALUES(meta_id),
                post_id = VALUES(post_id),
                meta_key = VALUES(meta_key),
                meta_value = VALUES(meta_value)
        ',
				$target_table,
				$backup_table
			)
		);

		return array(
			'success'      => $restored !== false,
			'restored'     => (int) $restored,
			'target_table' => $target_table,
			'message'      => sprintf(
				/* translators: 1: %d; 2: %s. */
				__( 'Restored %1$d records to %2$s', 'mhm-rentiva' ),
				(int) $restored,
				$target_table
			),
		);
	}

	/**
	 * Delete backup table
	 */
	public static function delete_backup( string $table_name ): array {
		global $wpdb;

		// "backup" appearing somewhere in the name used to be the only test here,
		// which reached other plugins' tables; ownership is what matters.
		if ( ! self::is_managed_backup_table( $table_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Not a backup table', 'mhm-rentiva' ),
			);
		}

		// Delete table
		$deleted = $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

		return array(
			'success' => $deleted !== false,
			'message' => $deleted ? __( 'Backup deleted successfully', 'mhm-rentiva' ) : __( 'Failed to delete backup', 'mhm-rentiva' ),
		);
	}

	/**
	 * Create full database backup (all plugin-related tables)
	 */
	public static function create_full_backup(): array {
		global $wpdb;

		$backup_name = 'mhmrentiva_full_backup_' . gmdate( 'Ymd_His' );
		$backup_dir  = self::backup_dir();

		// Initialize Filesystem
		if ( ! self::init_filesystem() ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to initialize filesystem.', 'mhm-rentiva' ),
			);
		}

		global $wp_filesystem;

		// Create backup directory if it doesn't exist
		if ( ! $wp_filesystem->exists( $backup_dir ) ) {
			$wp_filesystem->mkdir( $backup_dir );
		}

		// Always ensure backup directory is secure (even if it already existed)
		self::secure_backup_directory( $backup_dir );

		// Define tables to backup
		$tables_to_backup = array(
			// WordPress core tables used by plugin
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->options,

			// Plugin custom tables
			$wpdb->prefix . 'mhmrentiva_queue',
			$wpdb->prefix . 'mhmrentiva_ratings',
			$wpdb->prefix . 'mhmrentiva_report_queue',
			$wpdb->prefix . 'mhmrentiva_message_logs',
			$wpdb->prefix . 'mhmrentiva_background_jobs',
		);

		// Filter existing tables only
		$existing_tables = array();
		foreach ( $tables_to_backup as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists ) {
				$existing_tables[] = $table;
			}
		}

		if ( empty( $existing_tables ) ) {
			return array(
				'success' => false,
				'message' => __( 'No tables found to backup', 'mhm-rentiva' ),
			);
		}

		// Generate SQL file
		$sql_content  = "-- MHM Rentiva Full Database Backup\n";
		$sql_content .= '-- Generated: ' . gmdate( 'Y-m-d H:i:s' ) . "\n";
		$sql_content .= "-- Backup Name: {$backup_name}\n\n";

		$total_rows = 0;

		foreach ( $existing_tables as $table ) {
			// Table structure
			$create_table = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_A );
			if ( ! $create_table ) {
				continue;
			}

			$sql_content .= "\n-- Table: {$table}\n";
			$sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
			$sql_content .= $create_table['Create Table'] . ";\n\n";

			// Table data
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
			if ( ! empty( $rows ) ) {
				$sql_content .= "INSERT INTO `{$table}` VALUES\n";
				$values       = array();
				foreach ( $rows as $row ) {
					$row_values = array();
					foreach ( $row as $value ) {
						if ( $value === null ) {
							$row_values[] = 'NULL';
						} else {
							$row_values[] = "'" . esc_sql( $value ) . "'";
						}
					}
					$values[] = '(' . implode( ',', $row_values ) . ')';
				}
				$sql_content .= implode( ",\n", $values ) . ";\n\n";
				$total_rows  += count( $rows );
			}
		}

		// Save to file
		$file_path    = $backup_dir . '/' . $backup_name . '.sql';
		$file_written = $wp_filesystem->put_contents( $file_path, $sql_content, FS_CHMOD_FILE );

		if ( ! $file_written ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to write backup file', 'mhm-rentiva' ),
			);
		}

		// Also create a record in database for management
		$backup_table = $wpdb->prefix . 'mhmrentiva_backup_records';

		// Create backup records table if it doesn't exist
		$wpdb->query(
			$wpdb->prepare(
				"CREATE TABLE IF NOT EXISTS %i (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `backup_name` varchar(255) NOT NULL,
            `backup_type` varchar(50) NOT NULL DEFAULT 'full',
            `file_path` varchar(500) NOT NULL,
            `file_size` bigint(20) UNSIGNED DEFAULT 0,
            `tables_count` int(11) DEFAULT 0,
            `rows_count` int(11) DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `backup_name` (`backup_name`),
            KEY `backup_type` (`backup_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
				$backup_table
			)
		);

		// Insert record
		$wpdb->insert(
			$backup_table,
			array(
				'backup_name'  => $backup_name,
				'backup_type'  => 'full',
				'file_path'    => $file_path,
				'file_size'    => $wp_filesystem->size( $file_path ),
				'tables_count' => count( $existing_tables ),
				'rows_count'   => $total_rows,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		return array(
			'success'      => true,
			'backup_name'  => $backup_name,
			'file_path'    => $file_path,
			'file_size'    => $wp_filesystem->size( $file_path ),
			'tables_count' => count( $existing_tables ),
			'rows_count'   => $total_rows,
			'message'      => sprintf(
				/* translators: 1: %d; 2: %d. */
				__( 'Full backup created successfully: %1$d tables, %2$d rows', 'mhm-rentiva' ),
				count( $existing_tables ),
				$total_rows
			),
		);
	}

	/**
	 * List all full backups (from files and database records)
	 */
	public static function list_full_backups(): array {
		global $wpdb;

		$backups      = array();
		$backup_table = $wpdb->prefix . 'mhmrentiva_backup_records';

		// Get backups from database records
		$backup_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $backup_table ) );

		if ( $backup_table_exists ) {
			$records = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT * FROM %i
                WHERE backup_type = 'full'
                ORDER BY created_at DESC
            ",
					$backup_table
				),
				ARRAY_A
			);

			foreach ( $records as $record ) {
				$file_exists = false;
				if ( self::init_filesystem() ) {
					global $wp_filesystem;
					$file_exists = $wp_filesystem->exists( $record['file_path'] );
				}

				$backups[] = array(
					'id'           => (int) $record['id'],
					'backup_name'  => $record['backup_name'],
					'type'         => 'full',
					'file_path'    => $record['file_path'],
					'file_exists'  => $file_exists,
					'file_size'    => (int) $record['file_size'],
					'file_size_mb' => round( $record['file_size'] / 1024 / 1024, 2 ),
					'tables_count' => (int) $record['tables_count'],
					'rows_count'   => (int) $record['rows_count'],
					'created_at'   => $record['created_at'],
					'date'         => $record['created_at'],
				);
			}
		}

		// Also check the backup directories for files not in the database. Both are
		// scanned: an install that took a backup before the directory moved out of
		// wp-content still has real files in the old place, and a backup that stops
		// being listed is indistinguishable from a backup that was lost.
		if ( self::init_filesystem() ) {
			global $wp_filesystem;

			foreach ( self::backup_dirs() as $backup_dir ) {
				if ( $wp_filesystem->exists( $backup_dir ) && $wp_filesystem->is_dir( $backup_dir ) ) {
					// WP_Filesystem doesn't have a direct glob() alternative that works consistently across all methods.
					// However, dirlist() works for FTP/Direct etc.
					$file_list = $wp_filesystem->dirlist( $backup_dir );

					if ( is_array( $file_list ) ) {
						foreach ( $file_list as $file_info ) {
							if ( strpos( $file_info['name'], 'mhmrentiva_full_backup_' ) !== 0 || substr( $file_info['name'], -4 ) !== '.sql' ) {
								continue;
							}

							$file_path   = $backup_dir . '/' . $file_info['name'];
							$file_name   = $file_info['name'];
							$backup_name = str_replace( '.sql', '', $file_name );

							// Check if already in database
							$exists_in_db = false;
							foreach ( $backups as $backup ) {
								if ( $backup['backup_name'] === $backup_name ) {
									$exists_in_db = true;
									break;
								}
							}

							if ( ! $exists_in_db ) {
								$backups[] = array(
									'id'           => 0,
									'backup_name'  => $backup_name,
									'type'         => 'full',
									'file_path'    => $file_path,
									'file_exists'  => true,
									'file_size'    => isset( $file_info['size'] ) ? (int) $file_info['size'] : 0,
									'file_size_mb' => isset( $file_info['size'] ) ? round( $file_info['size'] / 1024 / 1024, 2 ) : 0,
									'tables_count' => 0, // Unknown
									'rows_count'   => 0, // Unknown
									// `lastmodunix`, not `lastmod`. dirlist() returns both, and
									// `lastmod` is already formatted for display -- gmdate('M j'),
									// e.g. "Jul 25" -- so feeding it back to gmdate() is a
									// TypeError on PHP 8 and takes the whole screen down.
									'created_at'   => isset( $file_info['lastmodunix'] ) ? gmdate( 'Y-m-d H:i:s', (int) $file_info['lastmodunix'] ) : '',
									'date'         => isset( $file_info['lastmodunix'] ) ? gmdate( 'Y-m-d H:i:s', (int) $file_info['lastmodunix'] ) : '',
								);
							}
						}
					}
				}
			}
		}

		// Sort by date (newest first)
		usort(
			$backups,
			function ( $a, $b ) {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		return $backups;
	}

	/**
	 * Where backups are written.
	 *
	 * Under the uploads directory, not straight into `WP_CONTENT_DIR`. Uploads
	 * is the one location WordPress guarantees is writable and lets the owner
	 * configure (it honours the `UPLOADS` constant and the multisite per-blog
	 * path), and it is where hosts, backup tools and security scanners expect a
	 * plugin's generated files to be. The WordPress.org preflight rule forbids
	 * building the path from `WP_CONTENT_DIR`.
	 */
	public static function backup_dir(): string {
		$uploads = wp_upload_dir();

		return $uploads['basedir'] . '/mhm-rentiva-backups';
	}

	/**
	 * Where backups written by earlier versions still are.
	 *
	 * Kept for reading only. An install that took a backup before the move has
	 * real SQL files in the old place, and they have to stay listable,
	 * restorable and deletable; silently ignoring them would look to the site
	 * owner exactly like losing them.
	 */
	private static function legacy_backup_dir(): string {
		return WP_CONTENT_DIR . '/mhm-rentiva-backups';
	}

	/**
	 * Every directory a backup file may legitimately live in.
	 *
	 * @return list<string>
	 */
	public static function backup_dirs(): array {
		return array( self::backup_dir(), self::legacy_backup_dir() );
	}

	/**
	 * Is this path inside one of the plugin's own backup directories?
	 *
	 * The containment check used to live only in the AJAX callers, re-derived at
	 * each one. That made the invariant a property of the callers rather than of
	 * the class that executes the file, so the next entry point to call
	 * restore_full_backup() without copying the check would get arbitrary SQL
	 * execution from an arbitrary file. It belongs here.
	 *
	 * Both ends are resolved with realpath() before comparison: that is what
	 * collapses a `..` traversal, and realpath() returns false for a path that
	 * does not resolve, which must be rejected rather than compared.
	 */
	public static function is_backup_file( string $file_path ): bool {
		$real_file = realpath( $file_path );

		if ( false === $real_file ) {
			return false;
		}

		foreach ( self::backup_dirs() as $dir ) {
			$real_dir = realpath( $dir );

			if ( false !== $real_dir && strpos( $real_file, $real_dir . DIRECTORY_SEPARATOR ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @deprecated Kept as the private name the class used internally.
	 */
	private static function is_contained_backup_file( string $file_path ): bool {
		return self::is_backup_file( $file_path );
	}

	/**
	 * Restore full backup from SQL file
	 */
	public static function restore_full_backup( string $file_path ): array {
		global $wpdb;

		if ( ! self::is_contained_backup_file( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid backup file path', 'mhm-rentiva' ),
			);
		}

		if ( ! self::init_filesystem() ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to initialize filesystem', 'mhm-rentiva' ),
			);
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Backup file not found', 'mhm-rentiva' ),
			);
		}

		// Read SQL file
		$sql_content = $wp_filesystem->get_contents( $file_path );
		if ( empty( $sql_content ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to read backup file', 'mhm-rentiva' ),
			);
		}

		// Execute SQL (split by semicolon)
		$queries = array_filter(
			array_map( 'trim', explode( ';', $sql_content ) ),
			function ( $query ) {
				return ! empty( $query ) && ! preg_match( '/^--/', $query );
			}
		);

		$executed = 0;
		$errors   = array();

		foreach ( $queries as $query ) {
			if ( empty( trim( $query ) ) ) {
				continue;
			}
			// DELIBERATELY NOT SUPPRESSED (WP.org T7 Task 9.5).
			//
			// $query is one statement split out of a .sql dump this plugin wrote
			// itself, and the file path is contained by is_contained_backup_file()
			// -> is_backup_file(), which resolves both ends with realpath() before
			// comparing. There is no placeholder form for "execute this arbitrary
			// statement", so no amount of prepare() removes the finding -- and a
			// phpcs:ignore here would only hide it from our own gate while a human
			// reviewer still sees it. It stays visible until the owner decides
			// between rewriting the backup format, dropping the feature, or
			// explaining it to WP.org.
			$result = $wpdb->query( $query );
			if ( $result === false ) {
				$errors[] = $wpdb->last_error;
			} else {
				++$executed;
			}
		}

		return array(
			'success'  => empty( $errors ),
			'executed' => $executed,
			'errors'   => $errors,
			'message'  => empty( $errors )
				/* translators: %d placeholder. */
				? sprintf( __( 'Restored %d queries successfully', 'mhm-rentiva' ), $executed )
				/* translators: 1: %d; 2: %d. */
				: sprintf( __( 'Restored %1$d queries, %2$d errors occurred', 'mhm-rentiva' ), $executed, count( $errors ) ),
		);
	}

	/**
	 * Delete full backup
	 */
	public static function delete_full_backup( string $backup_name ): array {
		global $wpdb;

		// Resolve against both directories: a backup taken before the directory
		// moved out of wp-content is still listed, so it must still be deletable.
		// Falls back to the current location when the file is absent from both,
		// because the DB bookkeeping below has to run either way.
		$file_path = self::backup_dir() . '/' . $backup_name . '.sql';
		foreach ( self::backup_dirs() as $dir ) {
			$candidate = $dir . '/' . $backup_name . '.sql';
			if ( file_exists( $candidate ) ) {
				$file_path = $candidate;
				break;
			}
		}

		$backup_table = $wpdb->prefix . 'mhmrentiva_backup_records';

		// Contain the resolved path to the backup directory before deleting. A target
		// that does not resolve is allowed through: there is nothing on disk to
		// traverse to, and the DB bookkeeping below still needs to run.
		if ( file_exists( $file_path ) && ! self::is_contained_backup_file( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid backup file path', 'mhm-rentiva' ),
			);
		}

		// Initialize filesystem
		$file_deleted = false;
		if ( self::init_filesystem() ) {
			global $wp_filesystem;
			if ( $wp_filesystem->exists( $file_path ) ) {
				$file_deleted = $wp_filesystem->delete( $file_path );
			} else {
				// If file doesn't exist, consider it "deleted" from filesystem perspective
				$file_deleted = true;
			}
		}

		// Delete database record
		$record_deleted      = false;
		$backup_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $backup_table ) );
		if ( $backup_table_exists ) {
			$record_deleted = $wpdb->delete(
				$backup_table,
				array( 'backup_name' => $backup_name ),
				array( '%s' )
			);
		}

		return array(
			'success' => $file_deleted || $record_deleted !== false,
			'message' => ( $file_deleted && $record_deleted !== false )
				? __( 'Backup deleted successfully', 'mhm-rentiva' )
				: __( 'Backup deletion completed with some warnings', 'mhm-rentiva' ),
		);
	}

	/**
	 * Secure backup directory from direct web access
	 * Creates .htaccess and index.php files (WordPress standards compliant)
	 */
	/**
	 * Secure backup directory from direct web access
	 * Creates .htaccess and index.php files (WordPress standards compliant)
	 */
	private static function secure_backup_directory( string $directory ): void {
		if ( ! self::init_filesystem() ) {
			return;
		}

		global $wp_filesystem;

		// Create .htaccess file to deny web access (Apache)
		$htaccess_content  = "# MHM Rentiva Backup Directory Protection\n";
		$htaccess_content .= "# This file prevents direct web access to backup files\n";
		$htaccess_content .= "# WordPress Security Standards Compliant\n\n";
		$htaccess_content .= "<IfModule mod_authz_core.c>\n";
		$htaccess_content .= "    Require all denied\n";
		$htaccess_content .= "</IfModule>\n";
		$htaccess_content .= "<IfModule !mod_authz_core.c>\n";
		$htaccess_content .= "    Order deny,allow\n";
		$htaccess_content .= "    Deny from all\n";
		$htaccess_content .= "</IfModule>\n";

		$htaccess_file = $directory . '/.htaccess';
		// Always update .htaccess to ensure it's secure
		$wp_filesystem->put_contents( $htaccess_file, $htaccess_content, FS_CHMOD_FILE );

		// Create index.php file as additional protection (WordPress standard)
		$index_content  = "<?php\n";
		$index_content .= "// Silence is golden.\n";
		$index_content .= "// This file prevents directory listing.\n";

		$index_file = $directory . '/index.php';
		// Always update index.php to ensure it exists
		$wp_filesystem->put_contents( $index_file, $index_content, FS_CHMOD_FILE );
	}

	/**
	 * Verify backup directory security
	 */
	public static function verify_backup_directory_security( string $directory ): array {
		$issues = array();

		if ( ! self::init_filesystem() ) {
			return array(
				'secure' => false,
				'issues' => array( __( 'Filesystem initialization failed', 'mhm-rentiva' ) ),
			);
		}

		global $wp_filesystem;

		// Check if directory exists
		if ( ! $wp_filesystem->exists( $directory ) ) {
			return array(
				'secure' => false,
				'issues' => array( __( 'Backup directory does not exist', 'mhm-rentiva' ) ),
			);
		}

		// Check .htaccess file
		$htaccess_file = $directory . '/.htaccess';
		if ( ! $wp_filesystem->exists( $htaccess_file ) ) {
			$issues[] = __( '.htaccess file missing - directory is not protected from web access', 'mhm-rentiva' );
		} else {
			$htaccess_content = $wp_filesystem->get_contents( $htaccess_file );
			if ( strpos( $htaccess_content, 'Deny from all' ) === false && strpos( $htaccess_content, 'Require all denied' ) === false ) {
				$issues[] = __( '.htaccess file exists but does not deny access properly', 'mhm-rentiva' );
			}
		}

		// Check index.php file
		$index_file = $directory . '/index.php';
		if ( ! $wp_filesystem->exists( $index_file ) ) {
			$issues[] = __( 'index.php file missing - directory listing is possible', 'mhm-rentiva' );
		}

		return array(
			'secure' => empty( $issues ),
			'issues' => $issues,
		);
	}

	/**
	 * Helper: Initialize WP_Filesystem
	 */
	private static function init_filesystem(): bool {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( ! WP_Filesystem() ) {
				return false;
			}
		}

		return ! empty( $wp_filesystem );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:enable
