<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Plan 0.4: the booking's receipt box printed the stored status key
 * (approved / rejected / submitted) as-is, in English on every site language.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::render_receipt_box
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::receipt_status_label
 */
final class ReceiptStatusLabelTest extends WP_UnitTestCase
{
	public function translate( string $translation, string $text, string $domain ): string
	{
		$tr = array(
			'Approved'  => 'Onaylandı',
			'Rejected'  => 'Reddedildi',
			'Submitted' => 'Gönderildi',
		);
		return ( 'mhm-rentiva' === $domain && isset( $tr[ $text ] ) ) ? $tr[ $text ] : $translation;
	}

	/**
	 * @dataProvider statuses
	 */
	public function test_the_receipt_box_shows_the_status_label( string $stored, string $label ): void
	{
		add_filter( 'gettext', array( $this, 'translate' ), 10, 3 );
		$booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking, '_mhmrentiva_receipt_status', $stored );

		ob_start();
		BookingMeta::render_receipt_box( get_post( $booking ) );
		$html = (string) ob_get_clean();
		remove_filter( 'gettext', array( $this, 'translate' ), 10 );

		$this->assertStringContainsString( $label, $html );
		$this->assertDoesNotMatchRegularExpression( '/>\s*' . $stored . '\s*</', $html, 'The raw status key is not shown.' );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function statuses(): array
	{
		return array(
			'approved'  => array( 'approved', 'Onaylandı' ),
			'rejected'  => array( 'rejected', 'Reddedildi' ),
			'submitted' => array( 'submitted', 'Gönderildi' ),
		);
	}

	public function test_no_status_shows_a_dash(): void
	{
		$this->assertSame( '-', BookingMeta::receipt_status_label( '' ) );
	}
}
