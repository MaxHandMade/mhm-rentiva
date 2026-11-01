<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactFormView;
use WP_UnitTestCase;

/**
 * PR F, R1-R3, R5, R6, R18 + r2 M3: what the contact template receives.
 * The view reads nothing itself, so every case is one array in, one array out.
 */
final class ContactFormViewTest extends WP_UnitTestCase
{
	/**
	 * @param array<string,string> $atts    Attribute overrides.
	 * @param array<string,mixed>  $context Context overrides.
	 * @return array<string,mixed>
	 */
	private function view(array $atts = array(), array $context = array()): array
	{
		return ContactFormView::build(
			$atts + array(
				'type'                  => 'general',
				'show_topic_selector'   => '1',
				'show_phone'            => '1',
				'show_company'          => '0',
				'show_vehicle_selector' => '0',
				'show_priority'         => '0',
				'show_attachment'       => '1',
				'show_booking_id_field' => '0',
				'show_company_info'     => '1',
			),
			$context + array(
				'uid'              => 'rvs-contact-7',
				'logged_in'        => false,
				'own_bookings'     => array(),
				'has_vehicles'     => false,
				'required'         => array(
					'general'  => array( 'name', 'email', 'message' ),
					'booking'  => array( 'name', 'email', 'message' ),
					'support'  => array( 'name', 'email', 'priority', 'message' ),
					'feedback' => array( 'name', 'email', 'message' ),
				),
				'address'          => '',
				'phone'            => '',
				'phone_note'       => '',
				'hours'            => '',
				'email'            => 'desk@example.test',
				'response_note'    => '',
				'consent_enabled'  => false,
				'privacy_url'      => '',
				'attachment_types' => array( 'jpg', 'pdf' ),
				'max_bytes'        => 5 * MB_IN_BYTES,
			)
		);
	}

	public function test_selector_offers_the_three_topics_and_support_only_by_attribute(): void
	{
		$view = $this->view();
		$this->assertTrue($view['selector']);
		$this->assertSame(array( 'general', 'booking', 'feedback' ), array_column($view['topics'], 'value'));
		$this->assertSame('general', $view['selected']);

		$support = $this->view(array( 'type' => 'support' ));
		$this->assertSame(array( 'general', 'booking', 'feedback', 'support' ), array_column($support['topics'], 'value'));
		$this->assertSame('support', $support['selected']);
	}

	public function test_selector_off_keeps_one_fixed_type(): void
	{
		$view = $this->view(array( 'type' => 'booking', 'show_topic_selector' => '0' ));
		$this->assertFalse($view['selector']);
		$this->assertSame(array( 'booking' ), array_column($view['topics'], 'value'));
		$this->assertSame(array( 'booking' ), array_keys($view['fields']));
	}

	public function test_topic_types_is_the_token_set(): void
	{
		$this->assertSame(array( 'general', 'booking', 'feedback' ), ContactFormView::topic_types('general', true));
		$this->assertSame(array( 'general', 'booking', 'feedback', 'support' ), ContactFormView::topic_types('support', true));
		$this->assertSame(array( 'support' ), ContactFormView::topic_types('support', false));
	}

	public function test_required_flags_per_topic(): void
	{
		$fields = $this->view()['fields'];
		$this->assertTrue($fields['general']['name']);
		$this->assertTrue($fields['general']['email']);
		$this->assertTrue($fields['general']['message']);
		$this->assertFalse($fields['booking']['phone'], 'R2: a booking question does not require a phone.');
		$this->assertFalse($fields['general']['attachment']);

		$support = $this->view(array( 'type' => 'support' ))['fields']['support'];
		$this->assertTrue($support['priority'], 'R2: support keeps its required priority.');
	}

	public function test_rating_is_drawn_only_on_a_fixed_feedback_form(): void
	{
		$this->assertArrayNotHasKey('rating', $this->view()['fields']['feedback'], 'M3: no rating in the selector form.');

		$fixed = $this->view(array( 'type' => 'feedback', 'show_topic_selector' => '0' ))['fields']['feedback'];
		$this->assertArrayHasKey('rating', $fixed);
		$this->assertFalse($fixed['rating'], 'R2: optional.');
	}

	public function test_show_toggles_reach_the_fields(): void
	{
		$fields = $this->view(array( 'show_phone' => '0', 'show_attachment' => '0', 'show_company' => '1', 'show_priority' => '1' ))['fields']['general'];
		$this->assertArrayNotHasKey('phone', $fields);
		$this->assertArrayNotHasKey('attachment', $fields);
		$this->assertArrayHasKey('company', $fields);
		$this->assertFalse($fields['priority']);

		$this->assertArrayNotHasKey('vehicle_id', $this->view(array( 'show_vehicle_selector' => '1' ))['fields']['general'], 'No vehicles, no selector.');
		$this->assertArrayHasKey('vehicle_id', $this->view(array( 'show_vehicle_selector' => '1' ), array( 'has_vehicles' => true ))['fields']['general']);
	}

	/**
	 * R3 + M3: the booking field per visitor and attribute.
	 */
	public function test_booking_field_modes(): void
	{
		$rows = array(
			array(
				'id'      => 10,
				'number'  => '10',
				'vehicle' => 'Fiat Egea',
				'dates'   => '21/10/2026 – 24/10/2026',
			),
		);

		// Signed in, with bookings, selector on: the list, on the booking topic only.
		$view = $this->view(array(), array( 'logged_in' => true, 'own_bookings' => $rows ));
		$this->assertSame('list', $view['booking_field']['mode']);
		$this->assertSame(array( 'booking' ), $view['booking_field']['topics']);
		$this->assertArrayHasKey('booking_id', $view['fields']['booking']);
		$this->assertArrayNotHasKey('booking_id', $view['fields']['general']);
		$this->assertSame(10, $view['bookings'][0]['id']);
		$this->assertSame('#10 · Fiat Egea · 21/10/2026 – 24/10/2026', $view['bookings'][0]['label']);

		// A guest: nothing unless the attribute asks; then the number box.
		$this->assertSame('', $this->view()['booking_field']['mode']);
		$this->assertArrayNotHasKey('booking_id', $this->view()['fields']['booking']);
		$this->assertSame('number', $this->view(array( 'show_booking_id_field' => '1' ))['booking_field']['mode']);

		// A member without listed bookings keeps the number box too (M3; e-mail owned bookings, m5).
		$this->assertSame('number', $this->view(array( 'show_booking_id_field' => '1' ), array( 'logged_in' => true ))['booking_field']['mode']);
		$this->assertSame('', $this->view(array(), array( 'logged_in' => true ))['booking_field']['mode']);

		// Selector off: the attribute alone decides, on the one type.
		$off = array( 'type' => 'general', 'show_topic_selector' => '0' );
		$this->assertSame('', $this->view($off, array( 'logged_in' => true, 'own_bookings' => $rows ))['booking_field']['mode']);
		$on = $this->view($off + array( 'show_booking_id_field' => '1' ), array( 'logged_in' => true, 'own_bookings' => $rows ));
		$this->assertSame('list', $on['booking_field']['mode']);
		$this->assertSame(array( 'general' ), $on['booking_field']['topics']);
	}

	public function test_guest_gets_no_booking_rows_even_if_handed_some(): void
	{
		$view = $this->view(
			array(),
			array(
				'own_bookings' => array(
					array(
						'id'      => 10,
						'number'  => '10',
						'vehicle' => 'Fiat',
						'dates'   => '',
					),
				),
			)
		);
		$this->assertSame(array(), $view['bookings']);
	}

	public function test_booking_label_without_dates_has_no_dangling_separator(): void
	{
		$view = $this->view(
			array(),
			array(
				'logged_in'    => true,
				'own_bookings' => array(
					array(
						'id'      => 10,
						'number'  => '10',
						'vehicle' => 'Fiat',
						'dates'   => '',
					),
				),
			)
		);
		$this->assertSame('#10 · Fiat', $view['bookings'][0]['label']);
	}

	public function test_sidebar_has_no_empty_rows(): void
	{
		$view = $this->view();
		$this->assertSame(array( 'email' ), array_column($view['sidebar'], 'key'), 'R5: empty settings draw no row.');
		$this->assertSame('mailto:desk@example.test', $view['sidebar'][0]['href']);

		$full = $this->view(
			array(),
			array(
				'address'    => 'Airport, gate 8',
				'phone'      => '+90 (212) 000 00 00',
				'phone_note' => 'Roadside help',
				'hours'      => '09:00 - 18:00',
			)
		);
		$this->assertSame(array( 'address', 'phone', 'email', 'hours' ), array_column($full['sidebar'], 'key'));
		$this->assertSame('tel:+902120000000', $full['sidebar'][1]['href']);
		$this->assertSame('Roadside help', $full['sidebar'][1]['note']);

		$note_only = $this->view(array(), array( 'phone_note' => 'Roadside help' ));
		$this->assertNotContains('phone', array_column($note_only['sidebar'], 'key'), 'A note without a number is no row.');

		$this->assertSame(array(), $this->view(array( 'show_company_info' => '0' ))['sidebar']);
	}

	public function test_consent_state_and_text(): void
	{
		$off = $this->view();
		$this->assertFalse($off['consent']['enabled']);
		$this->assertArrayNotHasKey('consent', $off['fields']['general']);

		$plain = $this->view(array(), array( 'consent_enabled' => true ));
		$this->assertTrue($plain['consent']['enabled']);
		$this->assertTrue($plain['fields']['general']['consent']);
		$this->assertTrue($plain['fields']['booking']['consent']);
		$this->assertSame('', $plain['consent']['url']);
		$this->assertStringNotContainsString('<a', $plain['consent']['html'], 'No privacy page: plain text.');
		$this->assertSame($plain['consent']['text'], $plain['consent']['html']);

		$linked = $this->view(array(), array( 'consent_enabled' => true, 'privacy_url' => 'https://example.test/privacy/' ));
		$this->assertStringContainsString('href="https://example.test/privacy/"', $linked['consent']['html']);
	}

	public function test_response_note_and_attachment_limits(): void
	{
		$this->assertSame('', $this->view()['response_note']);
		$this->assertSame('Within two hours', $this->view(array(), array( 'response_note' => 'Within two hours' ))['response_note']);

		$attachment = $this->view()['attachment'];
		$this->assertSame('.jpg,.pdf', $attachment['accept']);
		$this->assertSame(5 * MB_IN_BYTES, $attachment['max_bytes']);
		$this->assertStringContainsString('JPG, PDF', $attachment['help'], 'R18: the real list, not the drawing\'s.');
		$this->assertStringContainsString($attachment['max_label'], $attachment['help']);
	}

	public function test_ids_derive_from_the_instance_uid(): void
	{
		$one = $this->view();
		$two = $this->view(array(), array( 'uid' => 'rvs-contact-8' ));

		$this->assertSame('rvs-contact-7', $one['uid']);
		foreach ($one['ids'] as $key => $id) {
			$this->assertStringStartsWith('rvs-contact-7', $id);
			$this->assertNotSame($id, $two['ids'][ $key ]);
		}
		foreach (array( 'form', 'name', 'email', 'phone', 'company', 'vehicle', 'booking', 'priority', 'message', 'attachment', 'consent', 'honeypot', 'status' ) as $key) {
			$this->assertArrayHasKey($key, $one['ids']);
		}
		$this->assertCount(count($one['ids']), array_unique($one['ids']));
		$this->assertSame(ContactFormView::HONEYPOT_FIELD, $one['honeypot']);
	}

	public function test_honeypot_name_holds_no_word_browsers_autofill(): void
	{
		$this->assertDoesNotMatchRegularExpression(
			'/web|site|url|name|mail|phone|tel|company|org|address|city|zip|postal|country|fax|contact/i',
			ContactFormView::HONEYPOT_FIELD
		);
	}
}
