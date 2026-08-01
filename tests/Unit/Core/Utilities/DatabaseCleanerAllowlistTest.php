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
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::valid_meta_keys
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::find_invalid_meta_keys
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::cleanup_invalid_meta_keys
 */
final class DatabaseCleanerAllowlistTest extends WP_UnitTestCase
{
	/**
	 * String literals that match the scan pattern but are not meta keys.
	 *
	 * Every entry is a positive identification, not a "did not fit" bucket:
	 * three are concatenation bases that only ever appear glued to a suffix,
	 * five belong to the 6.0.0 prefix-rename map (they are target-side names
	 * that nothing writes yet -- see PrefixMigrationMap), one is a nonce field
	 * name and two are illustrations inside DatabaseCleaner's own filter
	 * docblock.
	 *
	 * @var array<string, string>
	 */
	private const NON_META_LITERALS = array(
		'_mhm_'                            => 'concatenation base: "_mhm_" . $key (TransferBookingHandler, VehicleMeta grid order)',
		'_mhm_rentiva_'                    => 'concatenation base: "_mhm_rentiva_" . $key (vehicle custom fields)',
		'_mhmrentiva_'                     => 'PrefixMigrationMap target prefix, not written by any code yet',
		'_mhmrentiva_booking_'             => 'PrefixMigrationMap target prefix, not written by any code yet',
		'_mhmrentiva_contact_'             => 'PrefixMigrationMap target prefix, not written by any code yet',
		'_mhmrentiva_deposit'              => 'PrefixMigrationMap docblock: collision example for the rename',
		'_mhmrentiva_rentiva_welcome_sent' => 'PrefixMigrationMap docblock: rule-order counter-example',
		'_mhm_vr_nonce'                    => 'nonce field name (VendorReportsAdminPage::check_admin_referer)',
		'_mhm_custom_addon_meta'           => 'DatabaseCleaner filter docblock @example',
		'_mhm_payment_custom_field'        => 'DatabaseCleaner filter docblock @example',
	);

	/** @var list<int> */
	private array $seeded_posts = array();

	public function tearDown(): void
	{
		global $wpdb;

		foreach ( $this->seeded_posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->seeded_posts = array();

		// cleanup_invalid_meta_keys() issues a CREATE TABLE, which commits the
		// transaction WP_UnitTestCase relies on for isolation. Drop whatever it
		// left behind by hand so the next test starts from a clean schema.
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
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->seeded_posts[] = $post_id;

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
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
				'_mhm_booking_id'           => '4242',
				'_mhm_is_remaining_payment' => 'yes',
				'_mhm_original_order_id'    => '99',
				'_mhm_remaining_order_id'   => '100',
				'_mhm_auto_cancelled'       => '1',
				'_mhm_vendor_commission_rate' => '15',
				'_mhm_not_a_real_rentiva_key_at_all' => 'garbage',
			)
		);

		$result = DatabaseCleaner::cleanup_invalid_meta_keys( false );

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

		$this->assertGreaterThan(
			200,
			count( $found ),
			'the scanner found almost nothing -- it is pointed at the wrong tree and would pass vacuously'
		);

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
	 * Every declared exception must still be present in the source, otherwise
	 * the exception list itself rots into a place to hide a real key.
	 */
	public function test_the_non_meta_exception_list_has_no_stale_entries(): void
	{
		$found = $this->scan_source_for_meta_key_literals();

		foreach ( array_keys( self::NON_META_LITERALS ) as $literal ) {
			$this->assertArrayHasKey(
				$literal,
				$found,
				"$literal is excused as a non-meta literal but no longer appears in the source -- drop the exception"
			);
		}
	}

	/**
	 * Vehicle custom fields are admin-defined: their meta keys are built at
	 * runtime as '_mhm_rentiva_' . $field_key and cannot be listed statically.
	 */
	public function test_admin_defined_custom_vehicle_fields_are_protected(): void
	{
		update_option( 'mhm_custom_details', array( 'engine_torque' => 'Engine torque' ) );

		$this->assertContains( '_mhm_rentiva_engine_torque', DatabaseCleaner::valid_meta_keys() );

		$post_id = $this->seed_post_with_meta( array( '_mhm_rentiva_engine_torque' => '400 Nm' ) );

		DatabaseCleaner::cleanup_invalid_meta_keys( false );

		$this->assertSame( '400 Nm', get_post_meta( $post_id, '_mhm_rentiva_engine_torque', true ) );
	}

	/**
	 * mhm_rentiva_valid_meta_keys is an extension point on a DESTRUCTIVE
	 * operation. A filter that can shrink it is a filter that can make any
	 * third party's bug delete this plugin's data, so the built-in entries are
	 * unioned back in and only additions survive.
	 */
	public function test_the_filter_can_add_keys_but_cannot_shrink_the_protection_list(): void
	{
		$shrink = static function (): array {
			return array( '_mhm_only_this_one' );
		};

		add_filter( 'mhm_rentiva_valid_meta_keys', $shrink );
		$valid = DatabaseCleaner::valid_meta_keys();
		remove_filter( 'mhm_rentiva_valid_meta_keys', $shrink );

		$this->assertContains( '_mhm_only_this_one', $valid, 'a filter must still be able to add keys' );
		$this->assertContains( '_mhm_booking_id', $valid, 'a filter must not be able to remove built-in protection' );
		$this->assertContains( '_mhm_status', $valid, 'a filter must not be able to remove built-in protection' );
	}

	/**
	 * Collect every '_mhm...' string literal in both plugins' shipped source.
	 *
	 * @return array<string, string> literal => "first file it was seen in"
	 */
	private function scan_source_for_meta_key_literals(): array
	{
		$lite = dirname( __DIR__, 4 );
		$pro  = dirname( $lite ) . '/mhm-rentiva-pro';

		$roots = array();
		foreach ( array( $lite, $pro ) as $plugin ) {
			foreach ( array( 'src', 'templates', 'assets', 'src-react', 'bin' ) as $sub ) {
				if ( is_dir( "$plugin/$sub" ) ) {
					$roots[] = "$plugin/$sub";
				}
			}
		}

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

				$code = (string) file_get_contents( $path );

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
