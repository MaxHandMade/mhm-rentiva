<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use WP_UnitTestCase;

/**
 * Task A10 removed Pro-feature templates from Lite's ZIP: three orphans (Pro
 * already ships its own copies), three account templates and four vendor/
 * message email templates moved to Pro outright.
 *
 * - templates/shortcodes/transfer-results.php  (orphan -- deleted)
 * - templates/shortcodes/transfer-search.php   (orphan -- deleted)
 * - templates/partials/transfer-card.php       (orphan -- deleted)
 * - templates/account/messages.php             (moved to Pro)
 * - templates/account/vendor-ledger.php        (moved to Pro)
 * - templates/account/vendor-booking-detail.php (moved to Pro)
 * - templates/emails/booking-created-vendor.html.php        (moved to Pro)
 * - templates/emails/booking-status-changed-vendor.html.php (moved to Pro)
 * - templates/emails/message-received-admin.html.php        (moved to Pro)
 * - templates/emails/message-replied-customer.html.php      (moved to Pro)
 *
 * None of the ten may ship in Lite's templates/ directory any more -- each
 * either leaked paid-feature UI/IP into the free ZIP (transfer, vendor
 * ledger, vendor messaging) or rendered an event Lite itself cannot fire
 * (a vendor-marketplace/messaging notification).
 */
final class A10TemplateCarveTest extends WP_UnitTestCase {

	/** @return list<string> Repo-relative paths, relative to the plugin root. */
	private function removed_templates(): array {
		return array(
			'templates/shortcodes/transfer-results.php',
			'templates/shortcodes/transfer-search.php',
			'templates/partials/transfer-card.php',
			'templates/account/messages.php',
			'templates/account/vendor-ledger.php',
			'templates/account/vendor-booking-detail.php',
			'templates/emails/booking-created-vendor.html.php',
			'templates/emails/booking-status-changed-vendor.html.php',
			'templates/emails/message-received-admin.html.php',
			'templates/emails/message-replied-customer.html.php',
		);
	}

	public function test_none_of_the_carved_templates_ship_in_lite(): void {
		foreach ( $this->removed_templates() as $relative ) {
			$this->assertFileDoesNotExist(
				MHM_RENTIVA_PLUGIN_PATH . $relative,
				"Lite must not ship {$relative} any more (Task A10)."
			);
		}
	}

	/**
	 * templates/account/ still exists in Lite -- it keeps user-dashboard.php,
	 * account-details.php, bookings.php, favorites.php, etc. Only the three
	 * Pro-only files above were removed from it.
	 */
	public function test_account_templates_directory_still_exists_with_lite_owned_files(): void {
		$this->assertDirectoryExists( MHM_RENTIVA_PLUGIN_PATH . 'templates/account' );
		$this->assertFileExists( MHM_RENTIVA_PLUGIN_PATH . 'templates/account/user-dashboard.php' );
	}
}
