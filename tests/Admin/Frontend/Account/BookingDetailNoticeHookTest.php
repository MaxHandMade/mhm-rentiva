<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * The booking detail offers a neutral place, just above the vehicle section,
 * where an add-on prints one complete notice block. With nobody listening the
 * page must be exactly what it was before the hook existed.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::render_booking_detail
 */
final class BookingDetailNoticeHookTest extends WP_UnitTestCase
{
	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->customer_id );
	}

	public function tearDown(): void
	{
		remove_all_actions( 'mhmrentiva_booking_detail_notice' );
		parent::tearDown();
	}

	private function create_booking(): int
	{
		$booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );

		update_post_meta( $booking_id, '_mhmrentiva_customer_user_id', $this->customer_id );
		update_post_meta( $booking_id, '_mhmrentiva_status', 'confirmed' );
		update_post_meta( $booking_id, '_mhmrentiva_total_price', '300.00' );

		return $booking_id;
	}

	private function normalise( string $html ): string
	{
		$html = (string) preg_replace( '/rvs-acc-\d+/', 'rvs-acc-N', $html );

		return (string) preg_replace( '/\s+/', ' ', $html );
	}

	public function test_hook_fires_with_booking_id(): void
	{
		$booking_id = $this->create_booking();
		$received   = array();

		add_action(
			'mhmrentiva_booking_detail_notice',
			static function ( $id ) use ( &$received ): void {
				$received[] = $id;
			}
		);

		AccountRenderer::render_booking_detail( $booking_id, true );

		$this->assertSame( array( $booking_id ), $received );
	}

	public function test_markup_unchanged_without_subscribers(): void
	{
		$booking_id = $this->create_booking();

		$plain = $this->normalise( AccountRenderer::render_booking_detail( $booking_id, true ) );

		$this->assertStringNotContainsString( 'rvs-account-detail__notice', $plain );
		$this->assertStringNotContainsString( 'rvs-account-detail--has-notice', $plain );
		$this->assertStringNotContainsString( 'mhmrentiva_booking_detail_notice', $plain );

		// A listener that prints nothing leaves the page exactly as it was.
		add_action( 'mhmrentiva_booking_detail_notice', static function (): void {} );
		add_action(
			'mhmrentiva_booking_detail_notice',
			static function (): void {
				echo "  
 ";
			}
		);
		$silent = $this->normalise( AccountRenderer::render_booking_detail( $booking_id, true ) );

		$this->assertSame( $plain, $silent );
	}

	public function test_the_wrapper_and_the_row_modifier_appear_only_with_output(): void
	{
		$booking_id = $this->create_booking();

		add_action(
			'mhmrentiva_booking_detail_notice',
			static function (): void {
				echo '<!--NOTICE-->';
			}
		);
		$html = AccountRenderer::render_booking_detail( $booking_id, true );

		$this->assertStringContainsString( '<div class="rvs-account-detail__notice"', $html );
		$this->assertStringContainsString( 'rvs-account-detail--has-notice', $html );
		$this->assertMatchesRegularExpression( '#</header>\s*<div class="rvs-account-detail__notice">\s*<!--NOTICE-->\s*</div>\s*<section class="rvs-account-detail__vehicle"#', $html );

		// Without the wrapper and the modifier the rest of the page is the plain page.
		remove_all_actions( 'mhmrentiva_booking_detail_notice' );
		$plain = $this->normalise( AccountRenderer::render_booking_detail( $booking_id, true ) );
		$with  = (string) preg_replace( '#\s*<div class="rvs-account-detail__notice">\s*<!--NOTICE-->\s*</div>#', '', $html );
		$with  = str_replace( ' rvs-account-detail--has-notice', '', $with );
		$this->assertSame( $plain, $this->normalise( $with ) );
	}

	public function test_notice_prints_before_the_vehicle_section(): void
	{
		$booking_id = $this->create_booking();

		add_action(
			'mhmrentiva_booking_detail_notice',
			static function (): void {
				echo '<!--NOTICE-->';
			}
		);
		$html = AccountRenderer::render_booking_detail( $booking_id, true );

		$this->assertMatchesRegularExpression( '#<!--NOTICE-->\s*</div>\s*<section class="rvs-account-detail__vehicle"#', $html );
	}

	public function test_the_answer_block_survives_the_notice_allowlist(): void
	{
		$block = '<section class="mhm-swap mhm-swap--pending" aria-live="polite"><p class="mhm-swap__eyebrow">Booking #7</p>'
			. '<h2 id="t1" class="mhm-swap__title">Title</h2><div class="mhm-swap__cars"><div class="mhm-swap__car"><p class="mhm-swap__name">Car</p></div></div>'
			. '<p class="mhm-swap__notice" role="alert">Oops</p><p>Price<br>Unchanged</p>'
			. '<form method="post" class="mhm-swap__form"><input type="hidden" name="_wpnonce" value="abc123">'
			. '<input type="hidden" name="key" value="k"><button type="submit" name="choice" value="accept" class="mhm-swap__button">I approve</button></form>'
			. '<script>alert(1)</script></section>';

		$out = wp_kses( $block, AccountRenderer::notice_allowed_html() );

		$this->assertStringContainsString( '<section class="mhm-swap mhm-swap--pending" aria-live="polite">', $out );
		$this->assertStringContainsString( '<form method="post" class="mhm-swap__form">', $out );
		$this->assertStringContainsString( '<input type="hidden" name="_wpnonce" value="abc123"', $out );
		$this->assertStringContainsString( '<input type="hidden" name="key" value="k"', $out );
		$this->assertStringContainsString( '<button type="submit" name="choice" value="accept" class="mhm-swap__button">I approve</button>', $out );
		$this->assertStringContainsString( '<h2 id="t1" class="mhm-swap__title">', $out );
		$this->assertStringContainsString( 'role="alert"', $out );
		$this->assertStringNotContainsString( '<script', $out );
	}

	public function test_the_allowlist_can_be_extended_by_an_add_on(): void
	{
		add_filter(
			'mhmrentiva_booking_detail_notice_allowed_html',
			static function ( array $allowed ): array {
				$allowed['select'] = array( 'name' => true );

				return $allowed;
			}
		);

		$this->assertArrayHasKey( 'select', AccountRenderer::notice_allowed_html() );
		remove_all_filters( 'mhmrentiva_booking_detail_notice_allowed_html' );
	}
}
