<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) { exit; }

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
 * Rule order matters throughout: '_mhm_rentiva_' collapses BEFORE '_mhm_' so
 * the two families cannot collide ('_mhm_rentiva_deposit' -> '_mhmrentiva_deposit',
 * a hypothetical '_mhm_deposit' -> '_mhmrentiva_deposit' would collide; the
 * bijection check in bin/check-prefix-inventory.php fails the build if any
 * two old keys map to one new key within the same exact-key family).
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
        'vehicle'         => 'mhmrentiva_vehicle',     // 18 <= 20
        'vehicle_booking' => 'mhmrentiva_booking',     // 18
        'vehicle_addon'   => 'mhmrentiva_addon',       // 16
        'mhm_app_log'     => 'mhmrentiva_app_log',     // 18
        'mhm_email_log'   => 'mhmrentiva_email_log',   // 20 -- exact limit
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
     * The trailing bare 'mhm_' rule additionally covers bare (no leading
     * underscore) user-meta keys this codebase actually uses, e.g.
     * CompareService::STORAGE_KEY ('mhm_rentiva_compare') and
     * FavoritesService::META_KEY ('mhm_rentiva_favorites') -- both read/
     * written via get_user_meta()/update_user_meta(), neither underscore-
     * prefixed, both correctly collapse via this bare rule since they do
     * not match either underscore-prefixed rule above.
     */
    public const USERMETA_PREFIX_RULES = [
        '_mhm_rentiva_' => '_mhmrentiva_',
        '_mhm_'         => '_mhmrentiva_',
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
     * Görev 13 Adım 3b'nin bootstrap-fallback'i bu iki adı SÜREKLİ literal
     * olarak taşır (eski kurulumu tanımak için) -- G-C mod 4'ün post-sweep
     * "eski ad kalmasın" kontrolü bunları AÇIKÇA muaf tutar, yoksa kapı asla
     * yeşil olamaz. Mod 5 (OPTIONS coverage) de aynı iki adı muaf tutar --
     * bu iki isim tasarım gereği hiçbir zaman OPTIONS'ta bir anahtar OLMAYACAK.
     */
    public const BOOTSTRAP_FALLBACK_ALLOWLIST = [
        'mhm_rentiva_db_version',
        'mhm_rentiva_plugin_version',
    ];
}
