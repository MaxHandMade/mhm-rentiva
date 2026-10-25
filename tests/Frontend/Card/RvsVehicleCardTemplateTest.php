<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Card;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 2: the rvs card markup contract (classes the CSS, the JS and the
 * Elementor controls target; accessible names; escaping; toggles).
 *
 * @coversNothing
 */
final class RvsVehicleCardTemplateTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		RequestSimulator::new_request();
		$this->vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_seats', '5');
	}

	/**
	 * @param array<string, mixed> $vehicle
	 * @param array<string, mixed> $atts
	 */
	private function card(array $vehicle = array(), array $atts = array(), string $layout = 'grid'): string
	{
		return Templates::render(
			'partials/rvs-vehicle-card',
			array(
				'vehicle' => array_merge(
					array(
						'id'           => $this->vehicle_id,
						'title'        => 'Hyundai Tucson',
						'permalink'    => 'http://example.org/arac/tucson/',
						'image'        => array( 'id' => 0, 'url' => 'http://example.org/t.jpg', 'alt' => 'Tucson front' ),
						'category'     => array( 'name' => 'SUV' ),
						'rating'       => array( 'average' => 4.8, 'count' => 24 ),
						'availability' => array( 'is_available' => true ),
						'booking_url'  => 'http://example.org/rezervasyon/',
					),
					$vehicle
				),
				'atts'    => $atts,
				'layout'  => $layout,
			),
			true
		);
	}

	public function test_markup_contract(): void
	{
		$html = $this->card();

		foreach (
			array(
				'class="rvs-card rvs-card--grid"',
				'data-rvs-card',
				'data-id="' . $this->vehicle_id . '"',
				'class="rvs-card__media"',
				'rvs-tag rvs-tag--outline rvs-card__tag">SUV<',
				'data-rvs-fav',
				'mhm-vehicle-favorite-btn',
				'class="rvs-card__title"><a href="http://example.org/arac/tucson/">Hyundai Tucson</a>',
				'class="rvs-card__rating"',
				'class="rvs-card__specs">5 People<',
				'class="rvs-card__terms"',
				'class="rvs-card__amount"',
				'rvs-btn rvs-btn--primary rvs-card__select',
				'data-testid="vehicle-book-btn"',
				'data-rvs-compare',
				'data-testid="vehicle-compare-btn"',
			) as $needle
		) {
			$this->assertStringContainsString($needle, $html, $needle);
		}
	}

	public function test_accessible_names(): void
	{
		$html = $this->card();

		$this->assertStringContainsString('aria-label="Add to favorites"', $html);
		$this->assertStringContainsString('aria-pressed="false"', $html);
		$this->assertStringContainsString('alt="Tucson front"', $html);
		$this->assertMatchesRegularExpression('/<label class="rvs-check rvs-card__compare">\s*<input type="checkbox"/', $html);

		$active = $this->card(array( 'is_favorite' => true ));
		$this->assertStringContainsString('aria-label="Remove from favorites"', $active);
		$this->assertStringContainsString('aria-pressed="true"', $active);
	}

	public function test_title_is_escaped(): void
	{
		$html = $this->card(array( 'title' => '<script>alert(1)</script>' ));

		$this->assertStringNotContainsString('<script>alert(1)</script>', $html);
		$this->assertStringContainsString('&lt;script&gt;', $html);
	}

	public function test_toggles_remove_elements(): void
	{
		$html = $this->card(array(), array( 'show_favorite_button' => '0', 'show_compare_button' => '0', 'show_booking_button' => '0', 'show_category' => '0' ));

		$this->assertStringNotContainsString('data-rvs-fav', $html);
		$this->assertStringNotContainsString('data-rvs-compare', $html);
		$this->assertStringNotContainsString('rvs-card__select', $html);
		$this->assertStringNotContainsString('rvs-card__tag', $html);
	}

	public function test_list_layout_and_unavailable(): void
	{
		$html = $this->card(array( 'availability' => array( 'is_available' => false ) ), array(), 'list');

		$this->assertStringContainsString('rvs-card--list is-unavailable', $html);
		$this->assertMatchesRegularExpression('/<button type="button" class="rvs-btn rvs-btn--primary rvs-card__select" data-testid="vehicle-book-btn" disabled>Not available<\/button>/', $html);
	}

	public function test_price_keeps_wc_price_markup(): void
	{
		if (! function_exists('wc_price')) {
			$this->markTestSkipped('WooCommerce absent.');
		}

		$this->assertStringContainsString('<bdi>', $this->card(), 'wc_price() wraps the amount in <bdi>; plain wp_kses_post() would strip it.');
	}
}
