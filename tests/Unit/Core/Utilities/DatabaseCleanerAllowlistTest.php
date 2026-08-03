<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\DatabaseCleaner;
use WP_UnitTestCase;

/**
 * DatabaseCleaner's meta-key list is a PROTECTION list, not a deletion list.
 *
 * find_invalid_meta_keys() runs
 *
 *     WHERE meta_key LIKE '_mhm%' AND meta_key NOT IN (<the list>)
 *
 * over the whole postmeta table -- it is scoped to no post type at all -- and
 * cleanup_invalid_meta_keys(false) then DELETEs every row whose key came back.
 * So a key the plugin family actually uses but that is absent from the list is
 * not a cosmetic gap: an admin who clicks "clean invalid meta" destroys live
 * data. Omission costs data; over-inclusion costs nothing but an unswept row.
 * The list is therefore maintained as a superset of every meta-key literal in
 * either plugin's source, and test_no_meta_key_literal_in_the_source_is_missing
 * re-derives that superset from the source tree on every run so it cannot drift.
 *
 * The Pro add-on is a sibling checkout, not a dependency: CI clones this repo
 * alone, so the scan cannot see Pro there. PRO_ONLY_META_KEYS freezes what Pro
 * contributes so CI still guards it, and test_the_frozen_pro_inventory_matches
 * re-checks that freeze against Pro's real source wherever Pro is present.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::valid_meta_keys
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::find_invalid_meta_keys
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::cleanup_invalid_meta_keys
 */
final class DatabaseCleanerAllowlistTest extends WP_UnitTestCase
{
	/**
	 * String literals that match the scan pattern but are not meta keys.
	 *
	 * Each entry names the plugin it lives in, because staleness can only be
	 * asserted for a plugin that was actually scanned -- Pro is absent in CI.
	 * Every entry is a positive identification, not a "did not fit" bucket:
	 * two are concatenation bases that only ever appear glued to a suffix, five
	 * belong to the 6.0.0 prefix-rename map (target-side names that nothing
	 * writes yet -- see PrefixMigrationMap) and one is a nonce field name.
	 *
	 * @var array<string, array{plugin: string, why: string}>
	 */
	private const NON_META_LITERALS = array(
		'_mhm'                             => array(
			'plugin' => 'lite',
			'why'    => 'LIKE prefix, not a meta key: Locker::withLock()/withBookingLock() lock every row of this plugin in BOTH spellings, because a new-prefix-only pattern locks nothing on an un-migrated site',
		),
		'_mhm_'                            => array(
			'plugin' => 'lite',
			'why'    => 'concatenation base: "_mhm_" . $key (TransferBookingHandler, VehicleMeta grid order)',
		),
		'_mhm_rentiva_'                    => array(
			'plugin' => 'lite',
			'why'    => 'concatenation base: "_mhm_rentiva_" . $key (vehicle custom fields)',
		),
		'_mhmrentiva_'                     => array(
			'plugin' => 'lite',
			'why'    => 'PrefixMigrationMap target prefix, not written by any code yet',
		),
		'_mhmrentiva_booking_'             => array(
			'plugin' => 'lite',
			'why'    => 'PrefixMigrationMap target prefix, not written by any code yet',
		),
		'_mhmrentiva_contact_'             => array(
			'plugin' => 'lite',
			'why'    => 'PrefixMigrationMap target prefix, not written by any code yet',
		),
		'_mhmrentiva_deposit'              => array(
			'plugin' => 'lite',
			'why'    => 'PrefixMigrationMap docblock: collision example for the rename',
		),
		// Deliberately the DOUBLED spelling. PrefixMigrationMap's docblock cites
		// '_mhmrentiva_rentiva_welcome_sent' as what a wrong rule ORDER produces,
		// so that exact string is present in the source and must be excused here.
		// It is a counter-example in prose, not a meta key anything writes.
		'_mhmrentiva_rentiva_welcome_sent' => array(
			'plugin' => 'lite',
			'why'    => 'PrefixMigrationMap docblock: rule-order counter-example',
		),
		// Renamed by the 6.0.0 sweep, NOT dropped. The new spelling still begins
		// with '_mhm', so it is still scanned and would be reported as an
		// unprotected meta key -- deleting the exception would trade a stale entry
		// for a false finding. It remains what it always was: a nonce FIELD name,
		// never a meta key.
		'_mhmrentiva_vr_nonce'             => array(
			'plugin' => 'pro',
			'why'    => 'nonce field name (VendorReportsAdminPage::check_admin_referer)',
		),
	);

	/**
	 * Meta keys that exist ONLY in the Pro add-on's source.
	 *
	 * CI clones this repository on its own, so the scanner never sees Pro there
	 * and would happily stay green while protecting none of the commission,
	 * payout and statement keys that made this a data-loss defect in the first
	 * place. Freezing them here gives CI something real to check.
	 *
	 * This list cannot rot unnoticed: wherever Pro IS checked out beside Lite,
	 * test_the_frozen_pro_inventory_matches_pros_real_source asserts it equals
	 * the live scan exactly, in both directions.
	 *
	 * @var list<string>
	 */
	private const PRO_ONLY_META_KEYS = array(
		'_mhm_attachments',
		'_mhmrentiva_iban_change_status',
		'_mhmrentiva_pending_iban',
		'_mhmrentiva_user_agent', // moved home: WP.org T8 Görev 10b deleted Lite's only scanned occurrence (Handler.php's create_booking_atomic()); Pro's Message.php:127 still writes it
		'_mhmrentiva_vendor_account_holder',
		'_mhmrentiva_vendor_approved_at',
		'_mhmrentiva_vendor_bio',
		'_mhmrentiva_vendor_iban',
		'_mhmrentiva_vendor_phone',
		'_mhmrentiva_vendor_service_areas',
		'_mhmrentiva_vendor_tax_number',
		'_mhmrentiva_vendor_tax_office',
		'_mhm_bypass_reason',
		'_mhm_cooling_policy_version',
		'_mhm_ip_address',
		'_mhm_listing_action',
		'_mhm_listing_vehicle_id',
		'_mhm_lock_status',
		'_mhm_log_code',
		'_mhm_log_message',
		'_mhm_log_oid',
		'_mhm_message_type',
		'_mhm_payout_amount',
		'_mhm_payout_external_ref',
		'_mhm_payout_maker_id',
		'_mhm_payout_rejection_reason',
		'_mhm_payout_status',
		'_mhm_read_at',
		'_mhm_release_after',
		'_mhm_rentiva_price_per_month',
		'_mhm_rentiva_price_per_week',
		'_mhm_rentiva_transfer_locations',
		'_mhm_rentiva_transfer_route_prices',
		'_mhm_rentiva_transfer_routes',
		'_mhm_rentiva_vehicle_insurance_doc',
		'_mhm_rentiva_vehicle_registration_doc',
		'_mhm_statement_carried_balance',
		'_mhm_statement_commission_total',
		'_mhm_statement_currency',
		'_mhm_statement_emailed_at',
		'_mhm_statement_generated_at',
		'_mhm_statement_gross',
		'_mhm_statement_last_entry_id',
		'_mhm_statement_lines',
		'_mhm_statement_net_activity',
		'_mhm_statement_number',
		'_mhm_statement_paid',
		'_mhm_statement_penalties',
		'_mhm_statement_period_end',
		'_mhm_statement_period_start',
		'_mhm_statement_vendor_snapshot',
		'_mhm_transfer_max_luggage_score',
		'_mhm_transfer_max_pax',
		'_mhm_transfer_price_multiplier',
		'_mhm_vehicle_base_price',
		'_mhm_vehicle_expiry_warning_first_sent',
		'_mhm_vehicle_expiry_warning_second_sent',
		'_mhm_vehicle_max_big_luggage',
		'_mhm_vehicle_max_small_luggage',
		'_mhm_vehicle_penalty_blocked_dates',
		'_mhm_vehicle_price_per_km',
		// '_mhm_vehicle_service_type' was here and is deliberately NOT any more.
		//
		// 🔴 It is the one place the 6.0.0 map MERGES two distinct legacy keys.
		// Pro writes '_mhm_vehicle_service_type' in one place and
		// '_rentiva_vehicle_service_type' in another (VehicleSubmit.php), and
		// Lite reads the second one too. The map's prefix rules send '_mhm_' and
		// '_rentiva_' both to '_mhmrentiva_', so BOTH canonicalise to
		// '_mhmrentiva_vehicle_service_type' -- the key stops being Pro-exclusive,
		// which is why it no longer belongs in this freeze.
		//
		// Görev 13 needs to know: on a post carrying both rows, a prefix-based
		// migration writes one over the other. G-C mode 1 does not catch this --
		// it checks uniqueness within the exact-key families only, and this
		// collision is between two PREFIX rules. Reported, not silently absorbed.
		'_mhm_vehicle_suspended_by_vendor_ban',
		'_mhm_vendor_payout_freeze',
		'_mhm_workflow_state',
	);

	/** Source subdirectories expected to exist and be scanned, per plugin. */
	private const SCANNED_SUBDIRS = array( 'src', 'templates', 'assets', 'bin' );

	/** @var list<int> */
	private array $seeded_posts = array();

	/** @var list<string> */
	private array $seeded_options = array();

	public function tearDown(): void
	{
		global $wpdb;

		foreach ( $this->seeded_posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->seeded_posts = array();

		// In production cleanup_invalid_meta_keys() issues a real CREATE TABLE,
		// which implicitly commits and would strand everything written before it.
		// Under WP_UnitTestCase that does not happen: the suite filters `query`
		// and rewrites CREATE TABLE into CREATE TEMPORARY TABLE
		// (wordpress-tests-lib/includes/abstract-testcase.php:498), so the
		// isolation transaction survives and the rollback still covers these
		// writes -- measured, not assumed. Both cleanups below are therefore
		// belt-and-braces: cheap, explicit, and the difference between "isolated"
		// and "isolated by an accident of the harness we do not control".
		foreach ( $this->seeded_options as $option_name ) {
			delete_option( $option_name );
		}
		$this->seeded_options = array();

		$leftovers = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $wpdb->prefix . 'mhm_postmeta_backup_invalid_' ) . '%'
			)
		);
		foreach ( $leftovers as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}

		parent::tearDown();
	}

	private function seed_post_with_meta( array $meta ): int
	{
		$post_id              = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->seeded_posts[] = $post_id;

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	private function seed_option( string $option_name, array $value ): void
	{
		$this->seeded_options[] = $option_name;
		update_option( $option_name, $value );
	}

	/**
	 * The keys the Fable audit named: read by the Pro commission bridge and by
	 * the remaining-payment flow, absent from the list, therefore deleted.
	 */
	public function test_every_meta_key_read_by_code_is_in_the_allowlist(): void
	{
		$read_keys = array(
			'_mhm_booking_id',
			'_mhm_is_remaining_payment',
			'_mhm_original_order_id',
			'_mhm_remaining_order_id',
			'_mhm_auto_cancelled',
			'_mhm_auto_cancelled_reason',
		);

		$valid = DatabaseCleaner::valid_meta_keys();

		foreach ( $read_keys as $key ) {
			$this->assertContains(
				$key,
				$valid,
				"$key is read by shipped code but is not in the cleanup protection list -- cleanup deletes it"
			);
		}
	}

	/**
	 * The proof that matters: run the real destructive path against a real
	 * database and show the at-risk rows survive.
	 *
	 * The bogus key is the negative control. Without it this test would pass
	 * just as well against a cleanup that deletes nothing at all.
	 */
	public function test_the_real_cleanup_spares_protected_meta_and_still_removes_unknown_meta(): void
	{
		$post_id = $this->seed_post_with_meta(
			array(
				'_mhm_booking_id'                    => '4242',
				'_mhm_is_remaining_payment'          => 'yes',
				'_mhm_original_order_id'             => '99',
				'_mhm_remaining_order_id'            => '100',
				'_mhm_auto_cancelled'                => '1',
				'_mhm_vendor_commission_rate'        => '15',
				'_mhm_not_a_real_rentiva_key_at_all' => 'garbage',
			)
		);

		$result = DatabaseCleaner::cleanup_invalid_meta_keys( false );

		$this->assertArrayNotHasKey( 'aborted', $result, 'the cleanup should have run, not refused' );

		$this->assertSame( '4242', get_post_meta( $post_id, '_mhm_booking_id', true ) );
		$this->assertSame( 'yes', get_post_meta( $post_id, '_mhm_is_remaining_payment', true ) );
		$this->assertSame( '99', get_post_meta( $post_id, '_mhm_original_order_id', true ) );
		$this->assertSame( '100', get_post_meta( $post_id, '_mhm_remaining_order_id', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, '_mhm_auto_cancelled', true ) );
		$this->assertSame( '15', get_post_meta( $post_id, '_mhm_vendor_commission_rate', true ) );

		$this->assertSame(
			'',
			get_post_meta( $post_id, '_mhm_not_a_real_rentiva_key_at_all', true ),
			'negative control: a key no code writes must still be cleaned, otherwise this test proves nothing'
		);
		$this->assertContains( '_mhm_not_a_real_rentiva_key_at_all', $result['keys_removed'] );
	}

	/**
	 * Drift gate. Re-derives the inventory from the source tree, so a new meta
	 * key added anywhere in either plugin fails here until it is protected.
	 * MetaKeys' constants are literals inside src/, so this subsumes a
	 * MetaKeys-only check.
	 */
	public function test_no_meta_key_literal_in_the_source_is_missing_from_the_allowlist(): void
	{
		$found = $this->scan_source_for_meta_key_literals();

		$valid   = DatabaseCleaner::valid_meta_keys();
		$missing = array();

		foreach ( $found as $literal => $where ) {
			if ( isset( self::NON_META_LITERALS[ $literal ] ) ) {
				continue;
			}
			if ( ! in_array( $literal, $valid, true ) ) {
				$missing[] = "$literal ({$where})";
			}
		}

		$this->assertSame(
			array(),
			$missing,
			"These meta-key literals exist in the source but are not protected from the invalid-meta cleanup:\n"
			. implode( "\n", $missing )
		);
	}

	/**
	 * The drift gate is only worth its green if it really read the source.
	 *
	 * A total-literals threshold cannot show that: DatabaseCleaner.php's own
	 * list would clear any threshold single-handedly, which is exactly why the
	 * scanner skips that file. So assert coverage positively, directory by
	 * directory, and require the ones that must yield literals to have done so.
	 */
	public function test_the_drift_gate_actually_scanned_every_expected_source_root(): void
	{
		$scanned = $this->roots_scanned();

		foreach ( self::SCANNED_SUBDIRS as $subdir ) {
			$this->assertArrayHasKey(
				"lite/$subdir",
				$scanned,
				"the drift gate did not scan this plugin's $subdir/ -- it is not watching what it claims to"
			);
		}

		$this->assertGreaterThan(
			0,
			$scanned['lite/src'],
			'lite/src was scanned but yielded no meta-key literals, which cannot be true'
		);
		$this->assertGreaterThan(
			0,
			$scanned['lite/templates'],
			'lite/templates was scanned but yielded no meta-key literals, which cannot be true'
		);

		if ( ! $this->pro_is_present() ) {
			// Nothing more to assert here: Pro's contribution is covered by the
			// frozen PRO_ONLY_META_KEYS list, which is checked unconditionally.
			return;
		}

		$this->assertArrayHasKey( 'pro/src', $scanned, 'Pro is checked out but its src/ was not scanned' );
		$this->assertGreaterThan( 0, $scanned['pro/src'] );
	}

	/**
	 * Pro's keys must be protected even where Pro cannot be seen -- i.e. in CI.
	 */
	public function test_the_frozen_pro_only_keys_are_protected(): void
	{
		$valid = DatabaseCleaner::valid_meta_keys();

		foreach ( self::PRO_ONLY_META_KEYS as $key ) {
			$this->assertContains(
				$key,
				$valid,
				"$key is written by the Pro add-on but is not protected -- the Lite cleanup deletes Pro's rows"
			);
		}
	}

	/**
	 * ...and the freeze must not drift away from Pro's real source, wherever
	 * Pro can actually be read.
	 */
	public function test_the_frozen_pro_inventory_matches_pros_real_source(): void
	{
		if ( ! $this->pro_is_present() ) {
			$this->markTestSkipped( 'the Pro add-on is not checked out beside this plugin' );
		}

		// Lite and Pro are renamed in separate commits (Görev 12 then Görev 14),
		// so for the whole window between them Lite spells a key
		// '_mhmrentiva_payout_status' while Pro still spells the SAME key
		// '_mhm_payout_status'. Diffing raw literals would then report every one
		// of Pro's keys as Pro-only and this test would measure the spelling gap
		// instead of the inventory. Both sides are canonicalised to the post-rename
		// spelling first, so the comparison is about which KEYS Pro contributes --
		// the question the freeze actually exists to answer -- and it keeps
		// working unchanged once Pro is swept too.
		$lite_literals = array_map( array( $this, 'canonical_meta_key' ), array_keys( $this->scan_roots( $this->roots_for( 'lite' ) ) ) );
		$pro_literals  = array_map( array( $this, 'canonical_meta_key' ), array_keys( $this->scan_roots( $this->roots_for( 'pro' ) ) ) );

		// The exception list is written in the pre-rename spelling too, so it has
		// to be canonicalised on the same terms or it stops matching.
		$exceptions = array_map( array( $this, 'canonical_meta_key' ), array_keys( self::NON_META_LITERALS ) );

		$pro_only = array_values(
			array_unique(
				array_filter(
					array_diff( $pro_literals, $lite_literals ),
					static function ( string $literal ) use ( $exceptions ): bool {
						return ! in_array( $literal, $exceptions, true );
					}
				)
			)
		);

		sort( $pro_only );
		$frozen = array_values( array_unique( array_map( array( $this, 'canonical_meta_key' ), self::PRO_ONLY_META_KEYS ) ) );
		sort( $frozen );

		$this->assertSame(
			$frozen,
			$pro_only,
			"PRO_ONLY_META_KEYS no longer matches Pro's source. Added in Pro: "
			. implode( ', ', array_diff( $pro_only, $frozen ) )
			. ' | gone from Pro: ' . implode( ', ', array_diff( $frozen, $pro_only ) )
		);
	}

	/**
	 * A meta key in its post-6.0.0 spelling, whichever spelling it arrives in.
	 *
	 * Longest matching prefix wins, exactly as PrefixMigrationMap documents and
	 * as DatabaseCleaner::legacy_meta_keys() applies in the opposite direction.
	 * Already-renamed keys are returned unchanged, because no old prefix matches
	 * them -- so this is safe to apply to both sides of a comparison.
	 *
	 * @param string $key Meta key in either spelling.
	 * @return string Canonical (post-rename) spelling.
	 */
	private function canonical_meta_key( string $key ): string
	{
		$rules = array_merge(
			\MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap::POSTMETA_PREFIX_RULES,
			\MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap::USERMETA_PREFIX_RULES
		);
		uksort( $rules, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );

		foreach ( $rules as $old => $new ) {
			if ( str_starts_with( $key, $old ) ) {
				return $new . substr( $key, strlen( $old ) );
			}
		}

		return $key;
	}

	/**
	 * Every declared exception must still be present in the source, otherwise
	 * the exception list itself rots into a place to hide a real key. Only
	 * entries whose plugin was actually scanned can be judged.
	 */
	public function test_the_non_meta_exception_list_has_no_stale_entries(): void
	{
		$found      = $this->scan_source_for_meta_key_literals();
		$pro_absent = ! $this->pro_is_present();
		$checked    = 0;

		foreach ( self::NON_META_LITERALS as $literal => $meta ) {
			if ( 'pro' === $meta['plugin'] && $pro_absent ) {
				continue;
			}

			++$checked;
			$this->assertArrayHasKey(
				$literal,
				$found,
				"$literal is excused as a non-meta literal but no longer appears in the source -- drop the exception"
			);
		}

		$this->assertGreaterThan( 0, $checked, 'no exception entry was checked at all' );
	}

	/**
	 * Vehicle custom fields are admin-defined: their meta keys are built at
	 * runtime as '_mhm_rentiva_' . $field_key and cannot be listed statically.
	 */
	public function test_admin_defined_custom_vehicle_fields_are_protected(): void
	{
		$this->seed_option( 'mhm_custom_details', array( 'engine_torque' => 'Engine torque' ) );

		$this->assertContains( '_mhm_rentiva_engine_torque', DatabaseCleaner::valid_meta_keys() );

		$post_id = $this->seed_post_with_meta( array( '_mhm_rentiva_engine_torque' => '400 Nm' ) );

		DatabaseCleaner::cleanup_invalid_meta_keys( false );

		$this->assertSame( '400 Nm', get_post_meta( $post_id, '_mhm_rentiva_engine_torque', true ) );
	}

	/**
	 * When the field definitions are gone but their rows are not, the cleanup
	 * must refuse to run rather than delete meta it can no longer identify.
	 */
	public function test_the_cleanup_aborts_when_custom_field_definitions_have_vanished(): void
	{
		// No mhm_custom_* / mhm_vehicle_* options are set, so the derivation
		// comes back empty exactly as it would after a reset or failed import.
		$post_id = $this->seed_post_with_meta( array( '_mhm_rentiva_engine_torque' => '400 Nm' ) );

		$result = DatabaseCleaner::cleanup_invalid_meta_keys( false );

		$this->assertTrue( $result['aborted'] ?? false, 'the cleanup must fail closed, not delete' );
		$this->assertSame( 0, $result['deleted'] );
		$this->assertContains( '_mhm_rentiva_engine_torque', $result['at_risk_keys'] );
		$this->assertSame(
			'400 Nm',
			get_post_meta( $post_id, '_mhm_rentiva_engine_torque', true ),
			'the abort must actually spare the row, not merely report it'
		);
	}

	/**
	 * The abort must not become a way to disable the feature: an empty
	 * derivation on a site that simply has no custom-field rows is normal, and
	 * the cleanup has to carry on doing its job there.
	 */
	public function test_an_empty_derivation_alone_does_not_abort_the_cleanup(): void
	{
		$post_id = $this->seed_post_with_meta( array( '_mhm_not_a_real_rentiva_key_at_all' => 'garbage' ) );

		$result = DatabaseCleaner::cleanup_invalid_meta_keys( false );

		$this->assertArrayNotHasKey(
			'aborted',
			$result,
			'no unvouched custom-field rows exist, so there is nothing to fail closed about'
		);
		$this->assertSame( '', get_post_meta( $post_id, '_mhm_not_a_real_rentiva_key_at_all', true ) );
	}

	/**
	 * mhmrentiva_valid_meta_keys is an extension point on a DESTRUCTIVE
	 * operation. A filter that can shrink it is a filter that can make any
	 * third party's bug delete this plugin's data, so the built-in entries are
	 * unioned back in and only additions survive.
	 */
	public function test_the_filter_can_add_keys_but_cannot_shrink_the_protection_list(): void
	{
		$shrink = static function (): array {
			return array( '_mhm_only_this_one' );
		};

		add_filter( 'mhmrentiva_valid_meta_keys', $shrink );
		$valid = DatabaseCleaner::valid_meta_keys();
		remove_filter( 'mhmrentiva_valid_meta_keys', $shrink );

		$this->assertContains( '_mhm_only_this_one', $valid, 'a filter must still be able to add keys' );
		$this->assertContains( '_mhm_booking_id', $valid, 'a filter must not be able to remove built-in protection' );
		$this->assertContains( '_mhm_status', $valid, 'a filter must not be able to remove built-in protection' );
	}

	// ---------------------------------------------------------------- scanning

	private function lite_dir(): string
	{
		return dirname( __DIR__, 4 );
	}

	private function pro_dir(): string
	{
		return dirname( $this->lite_dir() ) . '/mhm-rentiva-pro';
	}

	private function pro_is_present(): bool
	{
		return is_dir( $this->pro_dir() . '/src' );
	}

	/**
	 * @return array<string, string> "lite/src" => absolute path
	 */
	private function roots_for( string $plugin ): array
	{
		$base   = 'lite' === $plugin ? $this->lite_dir() : $this->pro_dir();
		$subs   = self::SCANNED_SUBDIRS;
		$subs[] = 'src-react';

		$roots = array();
		foreach ( $subs as $sub ) {
			if ( is_dir( "$base/$sub" ) ) {
				$roots[ "$plugin/$sub" ] = "$base/$sub";
			}
		}

		return $roots;
	}

	/**
	 * Every '_mhm...' literal in both plugins' shipped source.
	 *
	 * @return array<string, string> literal => "first file it was seen in"
	 */
	private function scan_source_for_meta_key_literals(): array
	{
		$roots = $this->roots_for( 'lite' );

		if ( $this->pro_is_present() ) {
			$roots += $this->roots_for( 'pro' );
		}

		return $this->scan_roots( $roots );
	}

	/**
	 * @return array<string, int> "lite/src" => number of literals it yielded
	 */
	private function roots_scanned(): array
	{
		$roots = $this->roots_for( 'lite' );

		if ( $this->pro_is_present() ) {
			$roots += $this->roots_for( 'pro' );
		}

		$counts = array();
		foreach ( $roots as $label => $path ) {
			$counts[ $label ] = count( $this->scan_roots( array( $label => $path ) ) );
		}

		return $counts;
	}

	/**
	 * @param array<string, string> $roots
	 * @return array<string, string> literal => "first file it was seen in"
	 */
	/**
	 * Blank out `prefix-rename:ignore-start/end` regions before scanning.
	 *
	 * Those regions mark literals present as DATA rather than as usage. Applied
	 * to the migration only -- see the call site for why, and for why it is not
	 * applied to every file that has regions.
	 */
	private static function strip_ignore_regions( string $code ): string
	{
		$lines  = explode( "\n", $code );
		$open   = false;
		$output = array();

		foreach ( $lines as $line ) {
			if ( str_contains( $line, 'prefix-rename:ignore-start' ) ) {
				$open = true;
			}
			$output[] = $open ? '' : $line;
			if ( str_contains( $line, 'prefix-rename:ignore-end' ) ) {
				$open = false;
			}
		}

		return implode( "\n", $output );
	}

	private function scan_roots( array $roots ): array
	{
		$found = array();

		foreach ( $roots as $root ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				$path = str_replace( '\\', '/', $file->getPathname() );

				if ( str_contains( $path, '/vendor/' ) || str_contains( $path, '/node_modules/' ) ) {
					continue;
				}
				if ( ! in_array( $file->getExtension(), array( 'php', 'js', 'jsx' ), true ) ) {
					continue;
				}
				// The protection list itself is not evidence that a key is used.
				// Scanning it would let the list vouch for its own entries, and
				// would clear any "did the scanner find anything?" threshold on
				// its own -- hiding the absence of every other file.
				if ( str_ends_with( $path, '/Admin/Core/Utilities/DatabaseCleaner.php' ) ) {
					continue;
				}
				// Same reasoning, second instance: bin/prefix-rename.php IS the
				// 6.0.0 rename -- it necessarily carries old meta-key literals as
				// data (PrefixRenamer::RESOLVED_META_KEYS), exactly as the
				// protection list above carries them. Scanning it would feed the
				// tool's own inventory back in as "evidence that a key is used"
				// and drift this gate against Pro's frozen list. A file that IS
				// the inventory must not BE the evidence.
				//
				// Deliberately narrow: every OTHER file under bin/ is still
				// scanned, so a stray meta-key literal introduced in a sibling
				// script still turns this gate red (mutation-proven).
				if ( str_ends_with( $path, '/bin/prefix-rename.php' ) ) {
					continue;
				}

				$code = (string) file_get_contents( $path );

				// Third instance of the rule the two exclusions above state: a
				// file that IS the inventory must not BE the evidence. Görev
				// 13's migration has to NAME the add-on's payout-freeze
				// user-meta key in order to carry those rows onto their new
				// name, and that mention is not Lite starting to write a key
				// the add-on owns. Without this, adding the key to the
				// migration's scope list silently dropped it out of Pro's
				// frozen inventory -- the very inventory that stops Lite's
				// cleanup deleting the add-on's rows.
				//
				// Narrower than the two exclusions above on purpose: only the
				// marked spans of this one file go, so every meta key
				// DatabaseMigrator genuinely uses is still evidence, and the
				// marks are themselves capped and registered by
				// PrefixRenameRegionsTest.
				if ( str_ends_with( $path, '/Admin/Core/Utilities/DatabaseMigrator.php' ) ) {
					$code = self::strip_ignore_regions( $code );
				}

				if ( ! preg_match_all( '/[\'"](_mhm[A-Za-z0-9_]*)[\'"]/', $code, $matches ) ) {
					continue;
				}

				foreach ( $matches[1] as $literal ) {
					if ( ! isset( $found[ $literal ] ) ) {
						$found[ $literal ] = basename( $path );
					}
				}
			}
		}

		return $found;
	}
}
