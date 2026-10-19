<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar;
use WP_Ajax_UnitTestCase;

/**
 * The calendar's vehicle-info AJAX sent status_text through esc_html__(), and the script
 * escapes it again (escapeHtml() / .text()) -- a translation with an apostrophe showed as
 * "&#039;" on screen. JSON carries text; the script owns the escaping.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::ajax_get_vehicle_info
 */
final class CalendarStatusTextNotDoubleEscapedTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	public function set_up(): void
	{
		parent::set_up();
		AvailabilityCalendar::register();
		wp_set_current_user( 0 );
		add_filter( 'gettext', array( $this, 'translate' ), 10, 3 );
	}

	public function tear_down(): void
	{
		remove_filter( 'gettext', array( $this, 'translate' ), 10 );
		parent::tear_down();
	}

	/** A translation with an apostrophe, as many Turkish/French strings have. */
	public function translate( string $translation, string $text, string $domain ): string
	{
		if ( 'mhm-rentiva' === $domain && 'Out of Order' === $text ) {
			return "Hors d'usage";
		}
		return $translation;
	}

	public function test_status_text_is_plain_text(): void
	{
		$vehicle = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ) );
		update_post_meta( $vehicle, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS, 'maintenance' );

		$_POST    = array(
			'action'     => 'mhmrentiva_get_vehicle_info',
			'nonce'      => wp_create_nonce( 'mhmrentiva_availability_nonce' ),
			'vehicle_id' => (string) $vehicle,
		);
		$_REQUEST = $_POST;
		$ob_level = ob_get_level();
		try {
			$this->_handleAjax( 'mhmrentiva_get_vehicle_info' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The payload is under test.
			unset( $e );
		} finally {
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
			while ( ob_get_level() < $ob_level ) {
				ob_start();
			}
		}

		// Both priv and nopriv handlers answer; the first JSON object is the response.
		$this->assertSame( 1, preg_match( '/^\{.*?\}(?=\{|$)/s', (string) $this->_last_response, $first ), 'Fixture guard: a JSON response.' );
		$response = json_decode( $first[0], true );
		$this->assertTrue( $response['success'] ?? false, 'Fixture guard: the vehicle is served.' );
		$this->assertSame( "Hors d'usage", $response['data']['status_text'] ?? null );
	}
}
