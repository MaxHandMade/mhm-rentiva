<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Single source of truth for the 6.0.0 prefix migration (T7 mandate).
 *
 * Every family below was produced by reading the actual code (see
 * bin/prefix-inventory-baseline.txt and the Task 3 report), not by copying
 * the illustrative stub this file started from. In particular:
 *
 * - OPTIONS lists all 135 distinct option-shaped names this plugin's own
 *   code actually passes to update_option()/get_option()/add_option()/
 *   delete_option()/register_setting() -- including 73+ names only reached
 *   through a variable (Templates.php's getSubjectOverride()/getBodyOverride()
 *   $opt switch, EmailTemplates.php's save_email_fields() $fields arrays,
 *   and a handful of class constants such as
 *   AddonContextMigration::FLAG_OPTION) that a literal-string-only grep
 *   would miss. The two brief-cited counts (plan's "10 bare-mhm", compliance
 *   audit's "30 mhm_rentiva_*") both undercounted; see the Task 3 report for
 *   the reconciliation.
 * - TABLES lists only the tables THIS plugin's own dbDelta()/CREATE TABLE
 *   code creates unconditionally. Two tables named in the original brief
 *   stub (mhm_rentiva_transfer_locations, mhm_rentiva_transfer_routes) were
 *   REMOVED after verification: DatabaseMigrator::create_transfer_tables()
 *   delegates entirely to \MHMRentiva\Core\Database\Migrations\TransferMigration,
 *   a Pro-only class that does not exist in this repository (confirmed via
 *   class_exists() gate and a repo-wide search) -- Lite never runs that
 *   CREATE TABLE. Five real tables were ADDED that the stub omitted:
 *   mhm_rentiva_payout_audit, mhm_payment_log, mhm_sessions,
 *   mhm_message_logs, mhm_rentiva_key_registry.
 * - CRON_HOOKS has seven entries, not the four in the un-corrected stub or
 *   the six after the architecture audit's fixes. A seventh --
 *   mhm_rentiva_process_queue (QueueManager.php, wp_schedule_single_event())
 *   -- was found independently during this task's own verification pass and
 *   was not named by either prior audit.
 *
 * Rule order matters throughout: '_mhm_rentiva_' is matched BEFORE '_mhm_', so
 * the cut point is always taken from the longest matching prefix. That is a rule
 * about ORDER, not about collisions.
 *
 * 🔴 CORRECTION (Görev 12 review, 2026-08-02). This paragraph used to claim the
 * two families "cannot collide" and cited '_mhm_deposit' as a HYPOTHETICAL.
 * '_mhm_deposit' is real and sits about a hundred lines below, in OPTIONS'
 * neighbouring meta inventory. The claim was simply wrong, and a wrong invariant
 * in prose is how the next implementer re-derives the wrong conclusion.
 *
 * WHAT IS ACTUALLY TRUE: '_mhm_' and '_mhm_rentiva_' both target '_mhmrentiva_',
 * so a bare key whose suffix matches a rentiva-qualified one lands on
 * the same new name. SEVEN pairs in this codebase do exactly that:
 *
 *     blocked_dates   gallery_images   vehicle_id
 *     customer_email  customer_name    price_per_day   deposit
 *
 * The bijection check in bin/check-prefix-inventory.php does NOT catch these --
 * it verifies uniqueness within each EXACT-KEY family, and this collision is
 * between two PREFIX rules, which it never compares.
 *
 * All seven were resolved by the owner on 2026-08-02. Six are genuinely two
 * historical spellings of ONE value and are deliberately ALLOWED to merge, so
 * they get no override entry. The seventh holds two different values and does:
 * see POSTMETA_EXACT_OVERRIDES below.
 *
 * 🔴 CONSEQUENCE FOR GÖREV 13: wp_postmeta has NO unique index on
 * (post_id, meta_key). A merge therefore does not overwrite; it leaves TWO ROWS
 * with the same key, and get_post_meta($id, $key, true) returns whichever the
 * storage engine happens to order first -- a silent, nondeterministic wrong
 * value. The migration needs a pre-flight collision query, and on a post that
 * carries both it must keep the WINNER and discard the other.
 *
 * The winner is NOT something Görev 13 derives. It is specified, per pair, in
 * POSTMETA_MERGE_WINNERS below.
 */
final class PrefixMigrationMap {

    /**
     * Custom post types this plugin registers via register_post_type().
     * New name length verified <= 20 (WP's hard post_type column limit).
     *
     * 'mhm_message' is deliberately NOT here: it is a CPT this Lite plugin
     * never registers (register_post_type() for it lives in the Pro add-on
     * only -- confirmed no register_post_type('mhm_message', ...) call
     * exists anywhere in this repo). Lite's own code (TrendService,
     * Mailer, DashboardPage, DashboardService, SystemInfo) only ever reads
     * or compares against the literal for cross-plugin compatibility. Its
     * string occurrences are still covered by RUNTIME_STRING_RULES's bare
     * 'mhm_' catch-all so Lite's code stays byte-consistent with a
     * Pro-side rename (Görev 14, Pro lockstep); no POST_TYPES/migration
     * entry is owed here since Lite creates no rows of that type.
     */
    public const POST_TYPES = [
        'vehicle'             => 'mhmrentiva_vehicle',   // 18 <= 20
        'vehicle_booking'     => 'mhmrentiva_booking',   // 18
        'vehicle_addon'       => 'mhmrentiva_addon',     // 16
        'mhm_app_log'         => 'mhmrentiva_app_log',   // 18
        'mhm_email_log'       => 'mhmrentiva_email_log', // 20 -- exact limit
        // 🔴 ADDED 2026-08-02, and the reason it was missing is the point.
        //
        // Nobody calls register_post_type() for this one -- it is an
        // unregistered storage bucket that ContactForm::save_contact_message()
        // writes straight into wp_posts. Because it was not a POST_TYPES entry
        // it never met the "<= 20" check above; the bare 'mhm_' catch-all in
        // RUNTIME_STRING_RULES rewrote the literal instead, and THAT rule has no
        // length rule to break. The result was
        // 'mhmrentiva_contact_message' -- 26 characters into a varchar(20) --
        // so every contact submission would truncate on a non-strict server and
        // ERROR on a strict one, which is the WP 6.x default on many hosts.
        //
        // Being unregistered is exactly why it needs an entry: it is still a
        // post_type VALUE, so it needs the length check AND a migration. Without
        // one its rows belong to no family and are stranded -- owned_post_types()
        // named it only to scope META, never to rewrite the type itself.
        //
        // 'mhmrentiva_contact' is 18. 'mhmrentiva_contact_msg' (22) does not fit.
        'mhm_contact_message' => 'mhmrentiva_contact',   // 18 -- see note above
    ];

    /**
     * Taxonomies this plugin registers via register_taxonomy(). New name
     * length verified <= 32 (WP's hard taxonomy column limit).
     *
     * VehicleSettings::get_taxonomy_features()/get_taxonomy_equipment()
     * additionally probe 'mhm_rentiva_feature'/'mhm_rentiva_equipment' via
     * taxonomy_exists() as defensive cross-plugin compatibility checks --
     * neither is ever registered by this plugin (no register_taxonomy()
     * call for either exists in this repo), so neither gets a TAXONOMIES
     * entry; their string literals are covered by RUNTIME_STRING_RULES'
     * generic 'mhm_rentiva_' rule already.
     */
    public const TAXONOMIES = [
        'vehicle_category' => 'mhmrentiva_vehicle_category', // 27 <= 32
        'addon_context'    => 'mhmrentiva_addon_context',    // 24
        'addon_category'   => 'mhmrentiva_addon_category',   // 25
    ];

    /**
     * Every option name this plugin's code actually passes to
     * update_option()/get_option()/add_option()/delete_option()/
     * register_setting() -- traced through direct string literals, both
     * register_setting() arguments, `$opt = '...'`-style variable
     * assignments (Templates.php's per-template-key switch statements),
     * and the ('name' => 'checkbox'|'text'|'email'|'html') field-definition
     * arrays EmailTemplates::save_email_fields() consumes. 135 entries.
     * Five of them -- the WooCommerce-endpoint version/hash flags
     * (WooCommerceIntegration::maybe_flush_rewrite_rules()) and the
     * v4271/v4272/v4641 one-time migration-marker flags -- were found only
     * by running bin/check-prefix-inventory.php's own mode 5 against this
     * file, not by the initial manual enumeration. A further TEN (fix round
     * 1, 2026-08-01, from an independent reviewer's re-trace) were reached
     * only through a 5th shape mode 5 did not originally detect: option
     * names living as keys/values INSIDE an `array(...)` literal, consumed
     * via `foreach` -- DatabaseMigrator::migrate_standalone_settings()'s
     * `$standalone_mapping` (3 bare mhm_transfer_* keys, read via
     * `foreach ($standalone_mapping as $old_key => $new_key) { get_option($old_key, ...); }`)
     * and SettingsService::reset_to_defaults_by_tab()'s `$legacy_keys` (7
     * mhm_rentiva_* entries, read via `foreach ($legacy_keys as $key) {
     * delete_option($key); }`). See mode 5's
     * detectForeachArrayLiteralOptionCandidates() in
     * bin/check-prefix-inventory.php for the added detection.
     *
     * Deliberately INCLUDES the many vendor/message/payout/vehicle-lifecycle
     * notification subject+body overrides Lite's own Templates.php reads
     * (getSubjectOverride()/getBodyOverride()) even though Lite's UI never
     * writes most of them: those options live in the SAME wp_options table
     * on a Lite+Pro site, and the 6.0.0 migration Görev 13 wires from this
     * map runs from Lite's own activation hook. Omitting a name Lite only
     * reads (never writes) would still orphan a Pro-populated value the
     * instant Lite's own code starts looking for it under the new key.
     *
     * Excludes only the two BOOTSTRAP_FALLBACK_ALLOWLIST names below.
     */
    public const OPTIONS = [
        'mhm_custom_details'                                => 'mhmrentiva_custom_details',
        'mhm_custom_equipment'                              => 'mhmrentiva_custom_equipment',
        'mhm_custom_features'                               => 'mhmrentiva_custom_features',
        'mhm_custom_field_meta'                             => 'mhmrentiva_custom_field_meta',
        'mhm_rentiva_addon_context_migrated_4_36_0'         => 'mhmrentiva_addon_context_migrated_4_36_0',
        'mhm_rentiva_addon_settings'                        => 'mhmrentiva_addon_settings',
        'mhm_rentiva_api_keys'                              => 'mhmrentiva_api_keys',
        'mhm_rentiva_auto_cancel_email_content'             => 'mhmrentiva_auto_cancel_email_content',
        'mhm_rentiva_auto_cancel_email_subject'             => 'mhmrentiva_auto_cancel_email_subject',
        // Fix round 1 (2026-08-01): these 7 were found by an independent
        // reviewer's re-trace, not by the original enumeration -- they are
        // read/written via SettingsService::reset_to_defaults_by_tab()'s
        // `$legacy_keys = array(...); foreach ($legacy_keys as $key) {
        // delete_option($key); }` shape (the "foreach over an array literal"
        // shape mode 5 did not detect until this fix round; see the
        // detectForeachArrayLiteralOptionCandidates() addition in
        // bin/check-prefix-inventory.php).
        'mhm_rentiva_base_color'                            => 'mhmrentiva_base_color',
        'mhm_rentiva_booking_admin_body'                    => 'mhmrentiva_booking_admin_body',
        'mhm_rentiva_booking_admin_enabled'                 => 'mhmrentiva_booking_admin_enabled',
        'mhm_rentiva_booking_admin_subject'                 => 'mhmrentiva_booking_admin_subject',
        'mhm_rentiva_booking_admin_to'                      => 'mhmrentiva_booking_admin_to',
        'mhm_rentiva_booking_cancelled_body'                => 'mhmrentiva_booking_cancelled_body',
        'mhm_rentiva_booking_cancelled_enabled'             => 'mhmrentiva_booking_cancelled_enabled',
        'mhm_rentiva_booking_created_body'                  => 'mhmrentiva_booking_created_body',
        'mhm_rentiva_booking_created_enabled'               => 'mhmrentiva_booking_created_enabled',
        'mhm_rentiva_booking_created_subject'               => 'mhmrentiva_booking_created_subject',
        'mhm_rentiva_booking_created_vendor_body'           => 'mhmrentiva_booking_created_vendor_body',
        'mhm_rentiva_booking_created_vendor_subject'        => 'mhmrentiva_booking_created_vendor_subject',
        'mhm_rentiva_booking_email'                         => 'mhmrentiva_booking_email',
        'mhm_rentiva_booking_reminder_body'                 => 'mhmrentiva_booking_reminder_body',
        'mhm_rentiva_booking_reminder_enabled'              => 'mhmrentiva_booking_reminder_enabled',
        'mhm_rentiva_booking_reminder_subject'              => 'mhmrentiva_booking_reminder_subject',
        'mhm_rentiva_booking_status_admin_body'             => 'mhmrentiva_booking_status_admin_body',
        'mhm_rentiva_booking_status_admin_subject'          => 'mhmrentiva_booking_status_admin_subject',
        'mhm_rentiva_booking_status_body'                   => 'mhmrentiva_booking_status_body',
        'mhm_rentiva_booking_status_enabled'                => 'mhmrentiva_booking_status_enabled',
        'mhm_rentiva_booking_status_subject'                => 'mhmrentiva_booking_status_subject',
        'mhm_rentiva_booking_status_vendor_body'            => 'mhmrentiva_booking_status_vendor_body',
        'mhm_rentiva_booking_status_vendor_subject'         => 'mhmrentiva_booking_status_vendor_subject',
        'mhm_rentiva_comments_settings'                     => 'mhmrentiva_comments_settings',
        'mhm_rentiva_country_restriction_enabled'           => 'mhmrentiva_country_restriction_enabled',
        'mhm_rentiva_currency'                              => 'mhmrentiva_currency',
        'mhm_rentiva_currency_position'                     => 'mhmrentiva_currency_position',
        'mhm_rentiva_customers_indexes_created'             => 'mhmrentiva_customers_indexes_created',
        'mhm_rentiva_dark_mode'                             => 'mhmrentiva_dark_mode',
        'mhm_rentiva_default_payment'                       => 'mhmrentiva_default_payment',
        'mhm_rentiva_enable_deposit'                        => 'mhmrentiva_enable_deposit',
        'mhm_rentiva_feedback_email'                        => 'mhmrentiva_feedback_email',
        'mhm_rentiva_footer_text'                           => 'mhmrentiva_footer_text',       // fix round 1
        'mhm_rentiva_header_image'                          => 'mhmrentiva_header_image',      // fix round 1
        'mhm_rentiva_iban_change_approved_body'             => 'mhmrentiva_iban_change_approved_body',
        'mhm_rentiva_iban_change_approved_subject'          => 'mhmrentiva_iban_change_approved_subject',
        'mhm_rentiva_iban_change_rejected_body'             => 'mhmrentiva_iban_change_rejected_body',
        'mhm_rentiva_iban_change_rejected_subject'          => 'mhmrentiva_iban_change_rejected_subject',
        'mhm_rentiva_install_date'                          => 'mhmrentiva_install_date',
        'mhm_rentiva_last_migration'                        => 'mhmrentiva_last_migration',
        'mhm_rentiva_last_update'                           => 'mhmrentiva_last_update',
        'mhm_rentiva_lifecycle_migration_done'              => 'mhmrentiva_lifecycle_migration_done',
        'mhm_rentiva_message_auto_reply_body'               => 'mhmrentiva_message_auto_reply_body',
        'mhm_rentiva_message_auto_reply_subject'            => 'mhmrentiva_message_auto_reply_subject',
        'mhm_rentiva_message_received_admin_body'           => 'mhmrentiva_message_received_admin_body',
        'mhm_rentiva_message_received_admin_subject'        => 'mhmrentiva_message_received_admin_subject',
        'mhm_rentiva_message_replied_customer_body'         => 'mhmrentiva_message_replied_customer_body',
        'mhm_rentiva_message_replied_customer_subject'      => 'mhmrentiva_message_replied_customer_subject',
        'mhm_rentiva_payout_approved_body'                  => 'mhmrentiva_payout_approved_body',
        'mhm_rentiva_payout_approved_subject'               => 'mhmrentiva_payout_approved_subject',
        'mhm_rentiva_payout_rejected_body'                  => 'mhmrentiva_payout_rejected_body',
        'mhm_rentiva_payout_rejected_subject'               => 'mhmrentiva_payout_rejected_subject',
        'mhm_rentiva_primary_color'                         => 'mhmrentiva_primary_color',
        'mhm_rentiva_refund_admin_body'                     => 'mhmrentiva_refund_admin_body',
        'mhm_rentiva_refund_admin_enabled'                  => 'mhmrentiva_refund_admin_enabled',
        'mhm_rentiva_refund_admin_subject'                  => 'mhmrentiva_refund_admin_subject',
        'mhm_rentiva_refund_admin_to'                       => 'mhmrentiva_refund_admin_to',
        'mhm_rentiva_refund_customer_body'                  => 'mhmrentiva_refund_customer_body',
        'mhm_rentiva_refund_customer_enabled'               => 'mhmrentiva_refund_customer_enabled',
        'mhm_rentiva_refund_customer_subject'               => 'mhmrentiva_refund_customer_subject',
        'mhm_rentiva_rest_settings'                         => 'mhmrentiva_rest_settings',
        'mhm_rentiva_secondary_color'                       => 'mhmrentiva_secondary_color',
        'mhm_rentiva_sender_email'                          => 'mhmrentiva_sender_email',       // fix round 1
        'mhm_rentiva_sender_name'                           => 'mhmrentiva_sender_name',         // fix round 1
        'mhm_rentiva_settings'                              => 'mhmrentiva_settings',
        'mhm_rentiva_setup_completed'                       => 'mhmrentiva_setup_completed',
        'mhm_rentiva_setup_redirect'                        => 'mhmrentiva_setup_redirect',
        'mhm_rentiva_support_email'                         => 'mhmrentiva_support_email',
        'mhm_rentiva_taxonomy_migrated'                     => 'mhmrentiva_taxonomy_migrated',
        'mhm_rentiva_test_email_address'                    => 'mhmrentiva_test_email_address', // fix round 1
        'mhm_rentiva_test_mode'                             => 'mhmrentiva_test_mode',           // fix round 1
        'mhm_rentiva_vehicle_activated_body'                => 'mhmrentiva_vehicle_activated_body',
        'mhm_rentiva_vehicle_activated_subject'             => 'mhmrentiva_vehicle_activated_subject',
        'mhm_rentiva_vehicle_approved_body'                 => 'mhmrentiva_vehicle_approved_body',
        'mhm_rentiva_vehicle_approved_subject'              => 'mhmrentiva_vehicle_approved_subject',
        'mhm_rentiva_vehicle_expired_body'                  => 'mhmrentiva_vehicle_expired_body',
        'mhm_rentiva_vehicle_expired_subject'               => 'mhmrentiva_vehicle_expired_subject',
        'mhm_rentiva_vehicle_expiry_warning_first_body'     => 'mhmrentiva_vehicle_expiry_warning_first_body',
        'mhm_rentiva_vehicle_expiry_warning_first_subject'  => 'mhmrentiva_vehicle_expiry_warning_first_subject',
        'mhm_rentiva_vehicle_expiry_warning_second_body'    => 'mhmrentiva_vehicle_expiry_warning_second_body',
        'mhm_rentiva_vehicle_expiry_warning_second_subject' => 'mhmrentiva_vehicle_expiry_warning_second_subject',
        'mhm_rentiva_vehicle_paused_body'                   => 'mhmrentiva_vehicle_paused_body',
        'mhm_rentiva_vehicle_paused_subject'                => 'mhmrentiva_vehicle_paused_subject',
        'mhm_rentiva_vehicle_pricing_settings'              => 'mhmrentiva_vehicle_pricing_settings',
        'mhm_rentiva_vehicle_rejected_body'                 => 'mhmrentiva_vehicle_rejected_body',
        'mhm_rentiva_vehicle_rejected_subject'              => 'mhmrentiva_vehicle_rejected_subject',
        'mhm_rentiva_vehicle_relisted_body'                 => 'mhmrentiva_vehicle_relisted_body',
        'mhm_rentiva_vehicle_relisted_subject'              => 'mhmrentiva_vehicle_relisted_subject',
        'mhm_rentiva_vehicle_renewed_body'                  => 'mhmrentiva_vehicle_renewed_body',
        'mhm_rentiva_vehicle_renewed_subject'               => 'mhmrentiva_vehicle_renewed_subject',
        'mhm_rentiva_vehicle_rereview_admin_body'           => 'mhmrentiva_vehicle_rereview_admin_body',
        'mhm_rentiva_vehicle_rereview_admin_subject'        => 'mhmrentiva_vehicle_rereview_admin_subject',
        'mhm_rentiva_vehicle_resumed_body'                  => 'mhmrentiva_vehicle_resumed_body',
        'mhm_rentiva_vehicle_resumed_subject'               => 'mhmrentiva_vehicle_resumed_subject',
        'mhm_rentiva_vehicle_submitted_admin_body'          => 'mhmrentiva_vehicle_submitted_admin_body',
        'mhm_rentiva_vehicle_submitted_admin_subject'       => 'mhmrentiva_vehicle_submitted_admin_subject',
        'mhm_rentiva_vehicle_withdrawn_body'                => 'mhmrentiva_vehicle_withdrawn_body',
        'mhm_rentiva_vehicle_withdrawn_subject'             => 'mhmrentiva_vehicle_withdrawn_subject',
        'mhm_rentiva_vendor_application_new_admin_body'     => 'mhmrentiva_vendor_application_new_admin_body',
        'mhm_rentiva_vendor_application_new_admin_subject'  => 'mhmrentiva_vendor_application_new_admin_subject',
        'mhm_rentiva_vendor_application_received_body'      => 'mhmrentiva_vendor_application_received_body',
        'mhm_rentiva_vendor_application_received_subject'   => 'mhmrentiva_vendor_application_received_subject',
        'mhm_rentiva_vendor_approved_body'                  => 'mhmrentiva_vendor_approved_body',
        'mhm_rentiva_vendor_approved_subject'               => 'mhmrentiva_vendor_approved_subject',
        'mhm_rentiva_vendor_rejected_body'                  => 'mhmrentiva_vendor_rejected_body',
        'mhm_rentiva_vendor_rejected_subject'               => 'mhmrentiva_vendor_rejected_subject',
        'mhm_rentiva_vendor_suspended_body'                 => 'mhmrentiva_vendor_suspended_body',
        'mhm_rentiva_vendor_suspended_subject'              => 'mhmrentiva_vendor_suspended_subject',
        'mhm_rentiva_welcome_email_body'                    => 'mhmrentiva_welcome_email_body',
        'mhm_rentiva_welcome_email_subject'                 => 'mhmrentiva_welcome_email_subject',
        'mhm_rentiva_woocommerce_endpoints_flushed'         => 'mhmrentiva_woocommerce_endpoints_flushed',
        'mhm_rentiva_woocommerce_endpoints_version'         => 'mhmrentiva_woocommerce_endpoints_version',
        'mhm_rentiva_woocommerce_endpoints_hash'             => 'mhmrentiva_woocommerce_endpoints_hash',
        // One-time migration marker flags (get_option($flag)/update_option($flag, ...)),
        // found via mode 5's own detection during this task, not by either
        // prior audit or the brief's stub:
        'mhm_rentiva_v4272_test_pollution_cleaned'          => 'mhmrentiva_v4272_test_pollution_cleaned',
        'mhm_rentiva_v4641_test_pollution_recleaned'        => 'mhmrentiva_v4641_test_pollution_recleaned',
        'mhm_rentiva_v4271_labels_migrated'                 => 'mhmrentiva_v4271_labels_migrated',
        'mhm_selected_details'                              => 'mhmrentiva_selected_details',
        'mhm_selected_equipment'                            => 'mhmrentiva_selected_equipment',
        'mhm_selected_features'                             => 'mhmrentiva_selected_features',
        // Fix round 1 (2026-08-01): DatabaseMigrator::migrate_standalone_settings()
        // (runs UNCONDITIONALLY from run_migrations() on every version bump)
        // reads these 3 bare mhm_ options via `$standalone_mapping = array('mhm_transfer_deposit_type'
        // => 'rentiva_transfer_deposit_type', ...); foreach ($standalone_mapping as $old_key => $new_key)
        // { get_option($old_key, null); ... }` -- the foreach-over-array-literal
        // shape this fix round added mode 5 detection for. Only the LEFT-hand
        // (old, bare-mhm_) keys are migration targets here; the right-hand
        // 'rentiva_transfer_*' values are the destination keys that same
        // method writes them INTO inside 'mhm_rentiva_settings' -- a
        // different, already-renamed family, out of scope for this map.
        'mhm_transfer_custom_types'                         => 'mhmrentiva_transfer_custom_types',
        'mhm_transfer_deposit_rate'                         => 'mhmrentiva_transfer_deposit_rate',
        'mhm_transfer_deposit_type'                         => 'mhmrentiva_transfer_deposit_type',
        'mhm_vehicle_details'                               => 'mhmrentiva_vehicle_details',
        'mhm_vehicle_equipment'                              => 'mhmrentiva_vehicle_equipment',
        'mhm_vehicle_features'                              => 'mhmrentiva_vehicle_features',
        'mhm_vehicle_settings'                              => 'mhmrentiva_vehicle_settings',
    ];

    /**
     * Post-meta prefix rules (longest prefix first, collision-checked).
     * '_booking_'/'_contact_' cover ContactForm/BookingMeta's own families;
     * '_rentiva_' covers the vendor_slug family (VehicleSettings et al.);
     * 'addon_' (bare, no leading underscore) covers AddonMeta's visible keys.
     */
    /**
     * Post-meta keys whose destination is decided EXACTLY, overriding the prefix
     * rules below. Görev 13 must apply these FIRST and exclude them from the
     * prefix pass.
     *
     * Only one pair needs it, and it is the one pair of the seven whose two keys
     * do not hold the same value:
     *
     *   '_mhm_blocked_dates'          BlockedDatesMetaBox -- admin-entered JSON,
     *                                 dates PLUS per-date notes
     *   '_mhm_rentiva_blocked_dates'  CancellationHandler -- a flat array of date
     *                                 strings, written when a booking is cancelled
     *
     * Different writers, different value SHAPES, same object. Letting the prefix
     * rules merge them means each silently overwrites the other on the same
     * vehicle, which is what happened between Görev 12's sweep and its review;
     * both are currently held at their pre-rename spellings in the code so the
     * corruption stops while the migration is written.
     *
     * Owner decision, 2026-08-02: keep them distinct.
     *
     * @var array<string,string>
     */
    /**
     * For each MERGED pair, which of the two old keys wins on a post that has
     * both. Owner decision, 2026-08-02.
     *
     * Görev 13 must not re-derive this. wp_postmeta has no unique index on
     * (post_id, meta_key), so a merge leaves two rows and the winner IS the
     * surviving value -- picking wrong is a silent data change, not a naming
     * preference.
     *
     * 🔴 THE RULE IS "THE SPELLING THE WRITERS USE WINS". Not the more qualified
     * spelling, and not the one that happens to have more rows in one database.
     * Applied honestly it gives a DIFFERENT surface answer per pair, which is
     * exactly why it must not be replaced by a heuristic about how the names look.
     *
     * READ THE vehicle_id ENTRY BEFORE APPLYING ANY HEURISTIC. The obvious rule
     * -- "prefer the rentiva-qualified spelling, it is more specific" -- gives
     * the WRONG answer there, and it is the pair where being wrong is worst.
     *
     *     '_mhm_vehicle_id'          <- every writer (Handler, Hooks,
     *                                   CancellationHandler); 25 rows in live
     *                                   wp_postmeta on the pre-rename dev database
     *     '_mhm_rentiva_vehicle_id'  <- what Testimonials filters on; NO writer
     *
     * Testimonials therefore resolved 0 of 29 bookings on the measured database;
     * every one of the 25 that had a vehicle silently returned nothing. The
     * merge is fixing that live bug, and it only fixes it if the writers' key
     * wins -- after which the count is 25 of 29.
     *
     * 🔴 PROVENANCE CORRECTION (Görev 13, 2026-08-02). This paragraph used to
     * cite "'_mhm_rentiva_vehicle_id'   3 rows" as a live measurement. It is not:
     * live wp_postmeta holds ZERO rows under that key. The three rows sit in
     * wp_mhm_postmeta_backup_invalid_20260320_092228, a DatabaseCleaner BACKUP
     * table. The number is left out above rather than restated, so it cannot be
     * re-cited as live. The decision is unchanged -- it is strengthened, since the
     * rentiva-qualified spelling turns out to have no live rows at all -- but a
     * count sourced from a backup table is exactly the kind of provenance error
     * this round has already been bitten by.
     *
     * Format: new key => the OLD key whose value survives.
     *
     * @var array<string,string>
     */
    public const POSTMETA_MERGE_WINNERS = [
        // Sole writer is VehicleGallery, on the rentiva-qualified key.
        '_mhmrentiva_gallery_images'             => '_mhm_rentiva_gallery_images',
        // See the note above -- the bare key is the one every writer uses.
        '_mhmrentiva_vehicle_id'                 => '_mhm_vehicle_id',
        // BookingMeta/BookingColumns write the bare keys; Testimonials only reads.
        '_mhmrentiva_customer_email'             => '_mhm_customer_email',
        '_mhmrentiva_customer_name'              => '_mhm_customer_name',
        // Live keys are the rentiva-qualified ones (MetaKeys, Util, BookingMeta);
        // the bare spellings survive only in the cleanup's protection list.
        '_mhmrentiva_price_per_day'              => '_mhm_rentiva_price_per_day',
        '_mhmrentiva_deposit'                    => '_mhm_rentiva_deposit',
        // EIGHTH pair, owner decision 2026-08-02 (Görev 13). This one collides
        // between the '_mhm_' and '_rentiva_' rules rather than between '_mhm_'
        // and '_mhm_rentiva_', which is why neither the bijection check nor the
        // original seven-pair analysis saw it; Görev 12 named it in
        // DatabaseCleanerAllowlistTest's docblock and deliberately left it for
        // this map rather than absorbing it silently.
        //
        // Winner decided on WRITERS, then confirmed against the database -- both
        // pieces of evidence, because one database is a sample:
        //   '_rentiva_vehicle_service_type'  2 writers  (Pro VehicleSubmit.php:838
        //                                     update_post_meta, and
        //                                     VehicleTransferMetaBox.php:198), plus
        //                                     every reader in Lite's FeaturedVehicles/
        //                                     SearchResults meta_query and both
        //                                     vehicle templates.  6 live rows.
        //   '_mhm_vehicle_service_type'      0 writers  -- it appears only as a
        //                                     DatabaseCleaner protection entry and as
        //                                     TWO legacy read-fallbacks in Pro
        //                                     (TransferSearchEngine.php:100,
        //                                     VehicleTransferMetaBox.php:63), each
        //                                     sitting beside the '_rentiva_' read it
        //                                     falls back FROM.  0 live rows.
        // Note this is the mirror image of vehicle_id: there the BARE spelling was
        // the writer, here the qualified one is. Same rule, opposite-looking answer.
        '_mhmrentiva_vehicle_service_type'       => '_rentiva_vehicle_service_type',
        // SEVEN more, owner decision 2026-08-02, from the add-on's transfer and
        // pricing families. Derived twice independently -- by the add-on agent
        // from its pre-sweep source, and by this map's own structural scan --
        // and the two agreed.
        //
        // They look counterintuitive in the SAME direction as
        // vehicle_service_type: the LESS qualified-looking spelling wins. The
        // evidence, measured rather than reasoned:
        //
        //   '_mhm_*'      NO reader and NO writer anywhere in either tree. Every
        //                 occurrence is a single entry in DatabaseCleaner's
        //                 protection allowlist -- it is not read, not written,
        //                 and holds 0 rows.
        //   '_rentiva_*'  the real key. VehicleTransferMetaBox reads it (now as
        //                 the transition fallback behind the new name), and five
        //                 of the seven hold 6 rows apiece across 6 vehicles on
        //                 the pre-rename database.
        //
        // price_per_km and base_price hold 0 rows under BOTH spellings on that
        // database -- a genuinely empty family, not a failed match. They are
        // declared anyway because a customer site may have what a dev site does
        // not, and an undeclared pair is the one case merge_loser_keys() refuses
        // to resolve.
        '_mhmrentiva_transfer_max_pax'           => '_rentiva_transfer_max_pax',
        '_mhmrentiva_transfer_max_luggage_score' => '_rentiva_transfer_max_luggage_score',
        '_mhmrentiva_transfer_price_multiplier'  => '_rentiva_transfer_price_multiplier',
        '_mhmrentiva_vehicle_max_big_luggage'    => '_rentiva_vehicle_max_big_luggage',
        '_mhmrentiva_vehicle_max_small_luggage'  => '_rentiva_vehicle_max_small_luggage',
        '_mhmrentiva_vehicle_price_per_km'       => '_rentiva_vehicle_price_per_km',
        '_mhmrentiva_vehicle_base_price'         => '_rentiva_vehicle_base_price',
    ];

    /**
     * The usermeta twin of POSTMETA_MERGE_WINNERS. Owner decision, 2026-08-02.
     *
     * The wp_usermeta table has no unique index on (user_id, meta_key) either,
     * so the same hazard applies: a merge leaves TWO rows and
     * get_user_meta($id, $key, true) returns whichever the storage engine
     * happens to order first.
     *
     * ONE pair exists, and the surface was established rather than assumed: the
     * 40 usermeta-shaped keys reachable from *_user_meta() call sites, from class
     * constants resolved to their values, and from the live dev database were all
     * projected through USERMETA_PREFIX_RULES and grouped by destination. Exactly
     * one destination had more than one source. The class is closed, not just
     * this instance.
     *
     *   '_rentiva_vendor_city'       WINNER -- MetaKeys::VENDOR_CITY; written by
     *                                VendorProfileSettingsSave.php:76 and read by
     *                                six call sites including the directory SQL
     *                                and two REST controllers. 5 rows.
     *   '_mhm_rentiva_vendor_city'   ZERO writers, ZERO readers. Its only
     *                                appearance in either codebase is a Pro
     *                                comment (VehicleSubmit.php:55) describing a
     *                                historical bug that read this orphan key --
     *                                a bug that was then fixed. 4 rows.
     *
     * The values are NOT duplicates: three of the four affected vendors hold a
     * different city on the orphan row (Istanbul/Kocaeli, Antalya/Ankara,
     * İzmir Çeşme/Ankara). Letting the orphan win puts a wrong city in the public
     * vendor directory for three vendors.
     *
     * Format: new key => the OLD key whose value survives.
     *
     * @var array<string,string>
     */
    public const USERMETA_MERGE_WINNERS = [
        '_mhmrentiva_vendor_city' => '_rentiva_vendor_city',
    ];

    public const POSTMETA_EXACT_OVERRIDES = [
        '_mhm_blocked_dates'          => '_mhmrentiva_blocked_dates',
        '_mhm_blocked_dates_notes'    => '_mhmrentiva_blocked_dates_notes',
        '_mhm_rentiva_blocked_dates'  => '_mhmrentiva_booking_blocked_dates',
    ];

    public const POSTMETA_PREFIX_RULES = [
        '_mhm_rentiva_' => '_mhmrentiva_',
        '_mhm_'         => '_mhmrentiva_',
        '_booking_'     => '_mhmrentiva_booking_',
        '_contact_'     => '_mhmrentiva_contact_',
        '_rentiva_'     => '_mhmrentiva_',           // vendor_slug family
        'addon_'        => 'mhmrentiva_addon_',      // visible meta stays visible
    ];

    /**
     * Tables this plugin's own dbDelta()/CREATE TABLE code creates
     * unconditionally. Verified by reading every dbDelta() call site in
     * DatabaseMigrator.php and QueueManager.php.
     *
     * DELIBERATELY EXCLUDES mhm_rentiva_transfer_locations/routes: the
     * original brief's stub listed these, but
     * DatabaseMigrator::create_transfer_tables() (line ~619) only ever
     * delegates to \MHMRentiva\Core\Database\Migrations\TransferMigration
     * behind a class_exists() gate, and that class does not exist anywhere
     * in this repository -- confirmed by a repo-wide search. Lite ships no
     * Transfer module (owner decision 2026-07-16, per that method's own
     * docblock) and creates neither table. They are Pro's tables to map,
     * not Lite's.
     *
     * ALSO EXCLUDES mhm_rentiva_background_jobs: DatabaseMigrator::
     * create_background_jobs_table() likewise only delegates to
     * \MHMRentiva\Admin\Reports\BackgroundProcessor behind a class_exists()
     * gate, and that class does not exist in this repository either
     * (src/Admin/Reports/ contains only Repository/). Same reasoning.
     *
     * UNRESOLVED PRODUCT DECISION (fix round 1, 2026-08-01, flagged by an
     * independent reviewer -- deliberately left open here, not decided by
     * this map): even though Lite never CREATES
     * `mhm_rentiva_transfer_locations`, three of Lite's OWN read paths still
     * PROBE for it by bare literal string, as a legacy fallback when the
     * current-name table `rentiva_transfer_locations` is absent:
     *   - QueryHelper.php:116 -- `foreach (['rentiva_transfer_locations',
     *     'mhm_rentiva_transfer_locations'] as $candidate) { ... SHOW TABLES
     *     LIKE ... }` (that file's own docblock: "the legacy name -- which
     *     was previously assumed rather than probed -- must be probed too")
     *   - ReportRepository.php:401-402 -- `$old_loc_table = $wpdb->prefix .
     *     'mhm_rentiva_transfer_locations';` used when the new-name table
     *     misses.
     *   - DashboardService.php:481-482 -- identical fallback pattern.
     * All three literals match RUNTIME_STRING_RULES' generic 'mhm_rentiva_'
     * rule, so Görev 12's code sweep WILL rename them to
     * 'mhmrentiva_transfer_locations' by default -- silently breaking this
     * fallback for any site that genuinely still has the legacy physical
     * table from history (not a data-loss bug -- the table itself isn't
     * touched -- but a real regression: the probe would stop finding a
     * table that is actually still there). This map does NOT decide which
     * way to resolve it. Whoever executes Görev 12 must choose explicitly:
     * (a) let the generic rule rename all three literals (accepting that the
     * legacy-table fallback stops working for old installs -- arguably fine
     * if Transfer's Pro migration already renamed the physical table
     * everywhere it matters), or (b) carve these three specific literal
     * occurrences out into a BARE_TOKEN_EXCEPTIONS-style exemption (same
     * pattern already used above for the bare 'mhm' token) so the legacy
     * probe keeps working. Do not let the generic rule resolve this
     * silently either way.
     */
    public const TABLES = [
        'mhm_rentiva_queue'        => 'mhmrentiva_queue',        // QueueManager::create_table()
        'mhm_rentiva_ratings'      => 'mhmrentiva_ratings',      // DatabaseMigrator::create_rating_table()
        'mhm_rentiva_payout_audit' => 'mhmrentiva_payout_audit', // DatabaseMigrator::create_payout_audit_table()
        'mhm_payment_log'          => 'mhmrentiva_payment_log',  // DatabaseMigrator::create_payment_log_table()
        'mhm_sessions'             => 'mhmrentiva_sessions',     // DatabaseMigrator::create_sessions_table()
        'mhm_message_logs'         => 'mhmrentiva_message_logs', // DatabaseMigrator::create_message_logs_table()
        'mhm_rentiva_key_registry' => 'mhmrentiva_key_registry', // DatabaseMigrator::create_key_registry_table()
    ];

    /**
     * Cron hook names actually driven by wp_schedule_event()/
     * wp_schedule_single_event() (or, for AutoCancel/AutoComplete, direct
     * `cron` option array manipulation that bypasses those functions'
     * validation -- see each entry's note). Seven entries.
     */
    public const CRON_HOOKS = [
        'mhm_rentiva_auto_cancel_event'     => 'mhmrentiva_auto_cancel_event',
        'mhm_rentiva_email_log_purge_event' => 'mhmrentiva_email_log_purge_event',
        'mhm_rentiva_log_purge_event'       => 'mhmrentiva_log_purge_event',
        'mhm_rentiva_daily_log_cleanup'     => 'mhmrentiva_daily_log_cleanup',
        // Found in the architecture audit (2026-07-31) -- both were missing:
        'mhm_rentiva_send_booking_reminder' => 'mhmrentiva_send_booking_reminder', // per-booking single event (ReminderScheduler.php); NO self-heal -- rows already scheduled under the old name before upgrade silently never fire unless migrated/cleared
        'mhm_rentiva_auto_complete_event'   => 'mhmrentiva_auto_complete_event',   // recurring; AutoComplete::maybe_schedule() re-schedules itself on init (self-heal exists, low priority -- only the old row lingers, harmless)
        // Found independently during this task's own Adim 1 verification
        // (2026-08-01) -- named by neither prior audit nor the brief:
        'mhm_rentiva_process_queue'         => 'mhmrentiva_process_queue',         // ad-hoc single event (QueueManager::maybe_start_processing(), wp_schedule_single_event()); self-checks wp_next_scheduled() before re-scheduling, so low risk, but it IS a real scheduled hook name and belongs in this map like any other
    ];

    /**
     * '_mhm_rentiva_' KURALI '_mhm_' kuralindan ONCE ve ayri satirda -- ayni
     * POSTMETA_PREFIX_RULES'daki çakışma-önleme mantığı burada da geçerli.
     * Fable mimari denetimi bu ikinci satırı eksik buldu: gerçek kullanıcı
     * meta'sı '_mhm_rentiva_welcome_sent' (BookingNotifications.php:100,104),
     * yalnız '_mhm_' kuralıyla '_mhmrentiva_rentiva_welcome_sent' diye
     * BOZULURDU (SUBSTRING kesme noktası yanlış konumdan başlar).
     *
     * 🔴 CORRECTION (Görev 13, 2026-08-02, owner-approved). This docblock used to
     * claim that the bare 'mhm_' rule "additionally covers" the underscore-less
     * user-meta keys -- CompareService::STORAGE_KEY ('mhm_rentiva_compare'),
     * FavoritesService::META_KEY ('mhm_rentiva_favorites') -- and that they
     * "both correctly collapse via this bare rule". They do not. The bare rule
     * cuts after 'mhm_', so 'mhm_rentiva_favorites' becomes
     * 'mhmrentiva_rentiva_favorites' while the swept code reads
     * 'mhmrentiva_favorites': the row is orphaned, not migrated. That is the same
     * class of false invariant this file had to correct at lines 41-49, and it is
     * how the next implementer re-derives the wrong conclusion.
     *
     * WHICH RULE COVERS WHICH FAMILY, plainly:
     *
     *   '_mhm_rentiva_'  the underscore-prefixed rentiva-qualified family --
     *                    '_mhm_rentiva_welcome_sent' (BookingNotifications.php:100,
     *                    104), '_mhm_rentiva_vendor_city'. Must precede '_mhm_' or
     *                    SUBSTRING cuts at the wrong offset and the key becomes
     *                    '_mhmrentiva_rentiva_welcome_sent'.
     *
     *   '_rentiva_'      ADDED 2026-08-02. The VENDOR PROFILE family, which had no
     *                    rule at all: '_rentiva_vendor_status', '_vendor_slug',
     *                    '_vendor_iban', '_vendor_bio', '_vendor_city',
     *                    '_vendor_phone', '_vendor_tax_*', '_vendor_approved_at',
     *                    '_vendor_reliability_*', '_vendor_score_history',
     *                    '_pending_iban', '_iban_change_status' -- 18 keys, 65 rows
     *                    on the dev database. Görev 12 renamed these literals in
     *                    the code (DashboardContext.php:29 now reads
     *                    '_mhmrentiva_vendor_status'), so without this rule every
     *                    vendor loses their active status, IBAN, slug and profile
     *                    and cannot enter the panel.
     *
     *   '_mhm_'         the underscore-prefixed bare-vendor family. NOT
     *                    self-scoping: sibling MHM products write user meta too,
     *                    so Görev 13 migrates this family by an explicit key
     *                    allowlist read out of the pre-rename tree, never by a
     *                    prefix LIKE. See DatabaseMigrator::owned_user_meta_keys().
     *
     *   'mhm_rentiva_'  ADDED 2026-08-02, and it MUST precede the bare rule below.
     *                    The underscore-less rentiva-qualified family the old
     *                    docblock wrongly assigned to that bare rule:
     *                    'mhm_rentiva_compare', '_favorites', '_last_login',
     *                    '_last_activity', '_address', '_phone', '_customer'.
     *                    'mhm_rentiva_customer' is load-bearing --
     *                    AccountController.php:425 gates customer account access
     *                    on it, so under the bare rule alone customers cannot
     *                    reach their own account.
     *
     *   'mhm_'          the underscore-less bare-vendor family
     *                    ('mhm_gdpr_consent_*', 'mhm_dashboard_widget_order',
     *                    'mhm_marketing_emails', ...). Same non-exclusivity as
     *                    '_mhm_' above, same explicit-allowlist treatment.
     *
     * Order is longest-first throughout, and the two 'rentiva'-bearing rules carry
     * the product token, so they are the only ones a prefix LIKE may safely drive.
     */
    public const USERMETA_PREFIX_RULES = [
        '_mhm_rentiva_' => '_mhmrentiva_',
        '_rentiva_'     => '_mhmrentiva_',
        '_mhm_'         => '_mhmrentiva_',
        'mhm_rentiva_'  => 'mhmrentiva_',
        'mhm_'          => 'mhmrentiva_',
    ];

    /**
     * Yorum meta ailesi -- araç değerlendirmeleri WP comment olarak saklanıyor
     * (VehicleRatingForm.php:247,266), meta anahtarı bare 'mhm_rating'. Fable
     * mimari denetimi bu aileyi haritada TAMAMEN eksik buldu; RUNTIME_STRING_RULES
     * içindeki hiçbir kural 'mhm_rating' literal'ini yakalamadığı için hem kod
     * sweep'i hem migration bu alanı atlıyordu.
     */
    public const COMMENTMETA = [
        'mhm_rating' => 'mhmrentiva_rating',
        // Added by owner decision, 2026-08-02 (Görev 12 review). The Görev 12
        // sweep renamed this literal in VerifiedReviewHelper while COMMENTMETA
        // held only the key above -- so nothing would have migrated the rows, and
        // every review an admin had manually flagged as verified would silently
        // revert to unverified. That is data loss, not cosmetics. The code is
        // currently held at the old spelling until Görev 13 can carry the rows.
        'mhm_verified_review' => 'mhmrentiva_verified_review',
    ];

    /**
     * Kodda dönüşen ama DB'ye yazılmayan aileler (sweep girdisi, migration
     * DEĞİL): hook/action/nonce/transient/cache-group/cron-schedule-name
     * prefixes, CPT/taxonomy string literals this plugin only reads (see
     * POST_TYPES/TAXONOMIES docblocks above), and everything else
     * bin/prefix-inventory-baseline.txt turned up that isn't claimed by a
     * more specific family. Order matters -- longest/most-specific first,
     * bare 'mhm_' last as the catch-all so it never fires before a more
     * specific rule already consumed the match.
     */
    public const RUNTIME_STRING_RULES = [
        'mhm_rentiva/'        => 'mhmrentiva/',   // slash-stili filter'lar
        'mhm_rentiva_'        => 'mhmrentiva_',   // hook/action/nonce/settings-group
        'MHM_RENTIVA_'        => 'MHMRENTIVA_',   // sabitler
        'mhm_dark_mode_nonce' => 'mhmrentiva_dark_mode_nonce', // tekil bare-mhm nonce
        'mhm_rating'          => 'mhmrentiva_rating', // COMMENTMETA anahtarının literal'i
        // Catch-all: every remaining bare 'mhm_'-prefixed literal this
        // codebase has that isn't a DB-persisted option/meta/table/cron key
        // above -- ajax action suffixes (wp_ajax_mhm_*), other nonce action
        // strings, $_POST/$_GET field names, transient-key prefixes
        // (PerformanceHelper::CACHE_PREFIX etc.), object-cache group names,
        // custom cron-schedule interval names (mhm_rentiva_5min/15min),
        // settings-section IDs, query-var keys (mhm_search_context), and
        // the 'mhm_message' CPT-comparison literal (see POST_TYPES docblock).
        // Ordered last so it never pre-empts a more specific rule above.
        'mhm_'                => 'mhmrentiva_',
    ];

    /**
     * NOT a substitution rule -- deliberately excluded from
     * RUNTIME_STRING_RULES. A blanket 'mhm' => 'mhmrentiva' substring rule
     * would be unsafe at ANY position in that list: 'mhmrentiva' itself
     * starts with the substring 'mhm', so a sequential str_replace() sweep
     * applying such a rule would re-match its own prior output (and every
     * other rule's output too, since every RUNTIME_STRING_RULES new value
     * starts with 'mhm'), corrupting 'mhmrentiva_x' into
     * 'mhmrentivarentiva_x'. The two known bare-'mhm' literals this codebase
     * has -- ElementorWidgetBase::$widget_keywords = ['mhm', 'rentiva'] (an
     * Elementor widget-picker search-keyword hint, not a registered global)
     * and AssetManager's str_contains($screen->id, 'mhm') admin-screen
     * probe -- are exact 3-character tokens with no distinguishing suffix,
     * so there is nothing to "prefix"; they are not CPT/taxonomy/option/hook
     * identifiers T7's mandate governs. Görev 12's sweep should update these
     * two call sites by hand (mhm -> mhmrentiva, verbatim) rather than via a
     * generic rule, and bin/check-prefix-inventory.php's mode 3 explicitly
     * excludes the bare 'mhm' token from its scope for this reason (see
     * isInScopeForMode3()'s BARE_TOKEN_EXCEPTIONS in that file).
     */
    public const BARE_TOKEN_EXCEPTIONS = [
        'mhm',
    ];

    /**
     * Literals somebody OUTSIDE this repository has to agree with, byte for byte.
     *
     * 🔴 The third rename-hazard class this round produced, and like the second
     * it was found by a defect rather than anticipated here. The test that finds
     * this family is NOT "is this a storage key" -- it is:
     *
     *     does anything outside this repository have to agree with this exact
     *     string?
     *
     * A storage name we can migrate. An external contract we cannot, because the
     * other party never runs our migration. Renaming one does not fail loudly;
     * it returns empty and the feature silently stops existing.
     *
     * The instance: MHM_RENTIVA_MIGRATION_FALLBACK, read by
     * MetaQueryHelper::is_migration_fallback_active() via defined() and never
     * defined anywhere in this tree -- a constant the SITE OPERATOR sets in
     * wp-config.php. The sweep renamed the lookup; the operator's wp-config was
     * not renamed with it, so defined() would have returned false forever.
     * Reverted to the operator-facing spelling and carved out at the call site.
     *
     * The same sweep in the add-on found EIGHT: a libsodium KDF context string
     * (changing it derived a different secretbox key, making every sealed audit
     * private key unrecoverable) plus seven operator-defined secrets.
     *
     * 🔴 THIS LIST HAS ONE ENTRY. THE FAMILY DOES NOT.
     *
     * The biggest member of this class is NOT carved out and cannot be: the
     * plugin's own HOOK NAMES. 102 distinct prefixed do_action/apply_filters
     * names across 55 files in src/. A customer's functions.php or a
     * third-party plugin has to agree with each of those byte for byte, and a
     * renamed hook fails in exactly the manner described above -- the callback
     * stops firing, with no error anywhere.
     *
     * That is a KNOWINGLY ACCEPTED major-version break, not an oversight.
     * Prefixing them is the WordPress.org rejection's mandate; carving them out
     * would be refusing the thing this round exists to do. What it must not be
     * is silent: it belongs in the 6.0.0 changelog and the upgrade notice,
     * because it is the one change in this round that breaks working
     * third-party code without an error message.
     *
     * Lite was swept for the whole class, eight queries:
     *   1. defined()/constant() lookups our tree never define -> this one, only.
     *   2. literals reaching a crypto or hash primitive (sodium, hash_hmac,
     *      hash, md5, openssl) -> six hits, all cache/transient KEY
     *      CONSTRUCTION (ObjectCache, Mailer stats, shortcode caches). A hashed
     *      lookup key is not domain separation and no external party matches
     *      it; a changed key simply misses.
     *   3. nonce actions -> 126, all created and verified inside this plugin in
     *      the same release. Internal, not a contract.
     *   4. literals crossing a network boundary (wp_remote_*, HTTP headers) ->
     *      none carrying the prefix.
     *   5. the REST namespace -> 'mhm-rentiva/v1', a HYPHENATED slug the
     *      underscore rules never match. Verified byte-identical pre and post
     *      sweep. In the class, unaffected by construction.
     *   6. HOOK NAMES -> 102 distinct, 123 occurrences, 55 files. In the class,
     *      ACCEPTED AND DOCUMENTED rather than carved out. See above.
     *   7. cookies and query vars, which survive in a browser and in bookmarked
     *      or shared URLs where no migration of ours can reach them -> no
     *      prefixed setcookie/$_COOKIE, and none through
     *      get_query_var/add_query_arg.
     *   8. register_meta() keys with show_in_rest, which REST consumers address
     *      by name -> the two calls this once named ('category_color',
     *      'category_icon') were DELETED on 2026-08-02: unprefixed, registered
     *      against 'term' with no taxonomy subtype, and dead since the initial
     *      release with 0 rows. No register_meta() call outside register_post_meta
     *      remains. The shape is still real and a future prefixed registration
     *      would join this family.
     *
     * "No further instances" is a claim about those eight queries as much as
     * about the tree; a ninth shape would need a ninth query.
     *
     * NOT a substitution table -- these names never change. Listed so the family
     * has a name and the next sweep has somewhere to look first.
     *
     * @var array<int,string>
     */
    public const EXTERNAL_CONTRACT_LITERALS = [
        // No ignore region here on purpose: this whole file is in
        // PrefixRenamer::NEVER_SWEEP, so a region would protect nothing and be a
        // pure blind spot. The carve-out that does the work is at the call site
        // in MetaQueryHelper.
        'MHM_RENTIVA_MIGRATION_FALLBACK',
    ];

    /**
     * Görev 13 Adım 3b'nin bootstrap-fallback'i bu iki adı SÜREKLİ literal
     * olarak taşır (eski kurulumu tanımak için) -- G-C mod 4'ün post-sweep
     * "eski ad kalmasın" kontrolü bunları AÇIKÇA muaf tutar, yoksa kapı asla
     * yeşil olamaz. Mod 5 (OPTIONS coverage) de aynı iki adı muaf tutar --
     * bu iki isim tasarım gereği hiçbir zaman OPTIONS'ta bir anahtar OLMAYACAK.
     *
     * @var array<int,string>
     */
    public const BOOTSTRAP_FALLBACK_ALLOWLIST = [
        'mhm_rentiva_db_version',
        'mhm_rentiva_plugin_version',
    ];
}
