<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Card;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_Ajax_UnitTestCase;

/**
 * Aşama 2 PR 2: the search results' AJAX re-render (filters, sort, pages)
 * draws the front-end standard card, keeps the search window for the period
 * total and the booking link, and keeps the booking-button toggle.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::ajax_filter_results
 */
final class SearchResultsCardTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		SearchResults::register();
		wp_set_current_user(0);

		$this->vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Zz Search Card',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
	}

	/**
	 * @param array<string, string> $extra
	 * @return array<string, mixed> The response's data.
	 */
	private function filter(array $extra = array()): array
	{
		$_POST = array_merge(
			array(
				'nonce'    => wp_create_nonce('mhmrentiva_search_results'),
				'per_page' => '12',
			),
			$extra
		);

		try {
			$this->_handleAjax('mhmrentiva_filter_results');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}

		$decoded = json_decode((string) $this->_last_response, true);
		$this->assertIsArray($decoded, (string) $this->_last_response);
		$this->assertTrue($decoded['success'] ?? false, (string) $this->_last_response);
		$this->_last_response = '';

		return $decoded['data'];
	}

	public function test_ajax_rerender_uses_rvs_card_with_dates_and_booking_toggle(): void
	{
		$data = $this->filter(array(
			'mhmrentiva_pickup_date' => '2027-07-12',
			'mhmrentiva_return_date' => '2027-07-15',
		));
		$html = (string) $data['html'];

		$this->assertStringContainsString('data-rvs-card', $html);
		$this->assertStringNotContainsString('mhm-vehicle-card', $html);
		$this->assertStringContainsString('3 days total', $html, 'The search window reaches the card: period total.');
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-07-12', $html, 'The booking link carries the window.');
		$this->assertStringNotContainsString('class="rvs-shell"', $html, 'A re-render is inner content; the page already has its shell.');

		$this->assertStringContainsString('data-testid="vehicle-book-btn"', $html);
		$off = $this->filter(array( 'show_booking_button' => '0' ));
		$this->assertStringNotContainsString('data-testid="vehicle-book-btn"', (string) $off['html']);
		$legacy = $this->filter(array( 'show_booking_btn' => '0' ));
		$this->assertStringNotContainsString('data-testid="vehicle-book-btn"', (string) $legacy['html'], 'The old JS key still switches it off.');
	}
}
