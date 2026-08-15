<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\Export\CustomerExporter;
use WP_UnitTestCase;

/**
 * WordPress.org T8 #2, export side — the sibling the sweep missed.
 *
 * The T8 round fixed three reads and one delete by asking two questions per
 * target instead of one: may the caller act on THIS id (edit_user/delete_user),
 * and is the target this plugin's to act on at all (CustomerIdentity). The CSV
 * export was not touched, and it is the same shape: check_admin_referer plus a
 * blanket edit_users, then $_POST['ids'] handed straight to
 * get_customer_details_optimized(), which LEFT JOINs bookings and therefore
 * returns a full profile row for accounts that have never booked anything —
 * an editor, a second administrator.
 *
 * WHY THE SWEEP MISSED IT: bin/audit-object-capabilities.php matches a hook
 * registration against handlers in the SAME file. This handler is registered
 * from CustomersPage.php:52 via the CustomerExporter::class constant, which is
 * the only cross-file registration in the tree — so the gate neither flagged it
 * nor listed it for manual review. That blind spot is being fixed separately;
 * this test is the behavioural lock.
 *
 * The 6.0.2 changelog also claims the customer screens "and the endpoints
 * behind them" now check two separate things. Export is an endpoint behind that
 * screen, so until this passes, the changelog is a claim a grep disproves.
 */
final class CustomerExportTargetGuardTest extends WP_UnitTestCase {

	/**
	 * Reset the per-request memo so ids created here are judged fresh.
	 */
	public function set_up(): void {
		parent::set_up();
		CustomerIdentity::flush_memo();
	}

	/**
	 * An account that is not a customer must not appear in the export.
	 *
	 * The caller here is an administrator: they hold edit_users, and under the
	 * pre-fix code that single capability was the whole gate. What must keep
	 * the editor out is the target-side question, not the caller-side one.
	 */
	public function test_export_omits_an_account_that_is_not_a_customer(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$editor = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'editor-not-a-customer@example.test',
			)
		);

		$customer = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'real-customer@example.test',
			)
		);

		CustomerIdentity::flush_memo();

		// Guard the guard: if these two premises stop holding, the assertions
		// below would pass for the wrong reason.
		$this->assertFalse(
			CustomerIdentity::is_customer( $editor ),
			'Premise broken: the editor should not read as a customer.'
		);
		$this->assertTrue(
			CustomerIdentity::is_customer( $customer ),
			'Premise broken: the customer-role account should read as a customer.'
		);

		$rows = CustomerExporter::get_csv_rows( '', array( $editor, $customer ) );

		$emails = array();
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$emails[] = $row[1] ?? '';
		}

		$this->assertContains(
			'real-customer@example.test',
			$emails,
			'The real customer must still be exported.'
		);
		$this->assertNotContains(
			'editor-not-a-customer@example.test',
			$emails,
			'An account this plugin does not own must not be written into the CSV.'
		);
	}

	/**
	 * A selection where nothing survives the checks exports nothing.
	 *
	 * This is the failure mode the guard could have introduced: filtering the
	 * ids down to an empty array and then handing that empty array to a
	 * function whose documented meaning for "empty" is "export everything".
	 * A refused selection must not become a full dump.
	 */
	public function test_a_fully_refused_selection_yields_only_the_header(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$editor = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'only-refused@example.test',
			)
		);

		// Another customer exists on the site; a full dump would pick them up.
		self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'bystander-customer@example.test',
			)
		);

		CustomerIdentity::flush_memo();

		$rows = CustomerExporter::get_csv_rows( '', array( $editor ) );

		$this->assertCount(
			1,
			$rows,
			'Only the header row may survive: no target passed, so nothing may be written.'
		);
	}

	/**
	 * The "export everything" path carries the same PII and the same duty.
	 *
	 * With no ids the exporter walks CustomersOptimizer::get_customers_optimized()
	 * page by page. That query starts FROM wp_users and its only real filter is
	 * `u.ID > 1 AND u.user_login != 'admin'`, so every editor and every second
	 * administrator on the site is in it. The 6.0.2 changelog admits the SCREEN
	 * still lists everyone; it says nothing about the CSV, and a list on screen
	 * and a downloaded file of personal data are not the same disclosure.
	 */
	public function test_export_all_omits_accounts_that_are_not_customers(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$editor = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'bulk-editor@example.test',
			)
		);

		CustomerIdentity::flush_memo();

		$this->assertFalse(
			CustomerIdentity::is_customer( $editor ),
			'Premise broken: the editor should not read as a customer.'
		);

		$rows = CustomerExporter::get_csv_rows( '', array() );

		$emails = array();
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$emails[] = $row[1] ?? '';
		}

		$this->assertNotContains(
			'bulk-editor@example.test',
			$emails,
			'Exporting everything must still export only customers.'
		);
	}

	/**
	 * The caller-side question is asked per target as well.
	 *
	 * edit_users answers "may this caller edit users at all". WordPress models
	 * "may this caller edit THIS user" as the meta cap edit_user( $id ), and on
	 * a single site an administrator cannot edit another administrator's
	 * account unless they are the same person. A caller who fails that check
	 * must not receive the row through an export either.
	 */
	public function test_export_omits_a_customer_the_caller_may_not_edit(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$customer = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'blocked-customer@example.test',
			)
		);

		CustomerIdentity::flush_memo();

		// Deny only the per-target capability, leaving edit_users intact, so the
		// test measures the target-side check rather than the blanket one.
		$deny = static function ( array $caps, string $cap, int $user_id, array $args ) use ( $customer ): array {
			if ( 'edit_user' === $cap && isset( $args[0] ) && (int) $args[0] === $customer ) {
				return array( 'do_not_allow' );
			}

			return $caps;
		};

		add_filter( 'map_meta_cap', $deny, 10, 4 );

		$rows = CustomerExporter::get_csv_rows( '', array( $customer ) );

		remove_filter( 'map_meta_cap', $deny, 10 );

		$emails = array();
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$emails[] = $row[1] ?? '';
		}

		$this->assertNotContains(
			'blocked-customer@example.test',
			$emails,
			'A target the caller may not edit must not be exported.'
		);
	}
}
