<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Core;

use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Task A8b seam inversion: the email-settings screen's tab list, render
 * dispatch and save dispatch no longer name MessageEmails/VendorEmails
 * directly -- Lite exposes three neutral seams instead:
 *   - `mhm_rentiva_email_types` (filter) for the tab list
 *   - `mhm_rentiva_render_email_type` (action) for rendering an unowned tab
 *   - `mhm_rentiva_save_email_type` (action) for saving an unowned tab
 *
 * @covers \MHMRentiva\Admin\Emails\Core\EmailTemplates
 */
final class EmailTemplatesSeamTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( 'mhm_rentiva_email_types' );
		remove_all_actions( 'mhm_rentiva_render_email_type' );
		remove_all_actions( 'mhm_rentiva_save_email_type' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'mhm_rentiva_email_types' );
		remove_all_actions( 'mhm_rentiva_render_email_type' );
		remove_all_actions( 'mhm_rentiva_save_email_type' );
		parent::tearDown();
	}

	/**
	 * @return array<string, string>
	 */
	private function get_email_type_tabs(): array {
		$method = new ReflectionMethod( EmailTemplates::class, 'get_email_type_tabs' );
		$method->setAccessible( true );

		return (array) $method->invoke( null );
	}

	/**
	 * Baseline: with no subscriber, Lite offers only its own three tabs -- no
	 * message_emails/vendor_emails key, by default.
	 */
	public function test_no_subscriber_yields_only_lites_own_tabs(): void {
		$tabs = $this->get_email_type_tabs();

		$this->assertSame(
			array( 'booking_notifications', 'refund_emails', 'preview' ),
			array_keys( $tabs )
		);
		$this->assertArrayNotHasKey( 'message_emails', $tabs );
		$this->assertArrayNotHasKey( 'vendor_emails', $tabs );
	}

	/**
	 * A subscriber (e.g. Pro's EmailExtensions) can add its own type, and it
	 * must land before the Preview tab -- matching the pre-inversion tab
	 * order (booking, refund, message, vendor, preview).
	 */
	public function test_subscriber_can_add_an_email_type_before_preview(): void {
		add_filter(
			'mhm_rentiva_email_types',
			static function ( array $types ): array {
				$types['message_emails'] = 'Message Notifications';
				return $types;
			}
		);

		$tabs = $this->get_email_type_tabs();

		$this->assertSame(
			array( 'booking_notifications', 'refund_emails', 'message_emails', 'preview' ),
			array_keys( $tabs )
		);
		$this->assertSame( 'Message Notifications', $tabs['message_emails'] );
	}

	/**
	 * The render dispatch fires `mhm_rentiva_render_email_type` for any tab
	 * Lite does not own, passing the active type through -- this is the seam
	 * Pro's EmailExtensions::render_email_type() hangs off.
	 */
	public function test_render_content_only_fires_render_action_for_unowned_tab(): void {
		add_filter(
			'mhm_rentiva_email_types',
			static function ( array $types ): array {
				$types['message_emails'] = 'Message Notifications';
				return $types;
			}
		);

		$fired_with = null;
		add_action(
			'mhm_rentiva_render_email_type',
			static function ( string $type ) use ( &$fired_with ): void {
				$fired_with = $type;
			}
		);

		$_GET['type'] = 'message_emails';

		ob_start();
		EmailTemplates::render_content_only();
		ob_end_clean();

		unset( $_GET['type'] );

		$this->assertSame( 'message_emails', $fired_with );
	}

	/**
	 * Lite's own tabs must NOT fall into the generic action -- only tabs Lite
	 * does not recognise do.
	 */
	public function test_render_content_only_does_not_fire_render_action_for_lites_own_tab(): void {
		$fired = false;
		add_action(
			'mhm_rentiva_render_email_type',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$_GET['type'] = 'booking_notifications';

		ob_start();
		EmailTemplates::render_content_only();
		ob_end_clean();

		unset( $_GET['type'] );

		$this->assertFalse( $fired );
	}

	/**
	 * The save dispatch fires `mhm_rentiva_save_email_type` for any tab Lite
	 * does not own -- this is the seam Pro's EmailExtensions::save_email_type()
	 * hangs off. Uses handle_save_templates() end-to-end, the real POST path.
	 */
	public function test_handle_save_templates_fires_save_action_for_unowned_tab(): void {
		add_filter(
			'mhm_rentiva_email_types',
			static function ( array $types ): array {
				$types['message_emails'] = 'Message Notifications';
				return $types;
			}
		);

		$fired_with = null;
		add_action(
			'mhm_rentiva_save_email_type',
			static function ( string $tab ) use ( &$fired_with ): void {
				$fired_with = $tab;
			}
		);

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['mhm_rentiva_email_templates_nonce'] = wp_create_nonce( 'mhm_rentiva_save_email_templates' );
		$_POST['current_tab']                       = 'message_emails';
		$_POST['email_templates_action']            = 'save';

		EmailTemplates::handle_save_templates();

		unset( $_POST['mhm_rentiva_email_templates_nonce'], $_POST['current_tab'], $_POST['email_templates_action'] );
		wp_set_current_user( 0 );

		$this->assertSame( 'message_emails', $fired_with );
	}
}
