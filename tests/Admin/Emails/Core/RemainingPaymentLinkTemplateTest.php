<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Core;

use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

final class RemainingPaymentLinkTemplateTest extends WP_UnitTestCase {

	public function test_registry_has_remaining_payment_link_customer_key(): void {
		$registry = Templates::registry();

		$this->assertArrayHasKey( 'remaining_payment_link_customer', $registry );
		$this->assertSame( 'remaining-payment-link-customer', $registry['remaining_payment_link_customer']['file'] );
	}

	public function test_render_body_includes_payment_url_and_amount(): void {
		$context = array(
			'site'     => array( 'name' => 'Test Site' ),
			'customer' => array( 'name' => 'John Doe', 'email' => 'john@example.com' ),
			'vehicle'  => array( 'title' => 'Fiat Egea' ),
			'booking'  => array( 'id' => 123, 'order_id' => 123, 'remaining_amount' => 765.0 ),
			'payment'  => array( 'url' => 'https://example.com/pay/abc123' ),
		);

		$html = Templates::render_body( 'remaining_payment_link_customer', $context );

		$this->assertStringContainsString( 'https://example.com/pay/abc123', $html );
		$this->assertStringContainsString( '765', $html );
		$this->assertStringContainsString( 'John Doe', $html );
	}

	public function test_compile_subject_is_not_empty(): void {
		$context = array( 'site' => array( 'name' => 'Test Site' ) );

		$subject = Templates::compile_subject( 'remaining_payment_link_customer', $context );

		$this->assertNotEmpty( $subject );
		$this->assertStringContainsString( 'Test Site', $subject );
	}
}
