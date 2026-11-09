<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The calendar's daily price reads "amount · per day · suffix" (canvas Takvim,
 * Ç5 approved 2026-11-09). It read "$1.750,00 KDV günlük": WooCommerce's price
 * suffix sat between the amount and "per day". Used for the vehicle card and
 * every option of the vehicle picker.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\CalendarView
 */
final class CalendarPriceSuffixOrderTest extends WP_UnitTestCase
{
	/** @var callable */
	private $suffix;

	public function setUp(): void
	{
		parent::setUp();
		if (! function_exists('wc_get_product')) {
			$this->markTestSkipped('WooCommerce is not loaded.');
		}
		$this->suffix = static function () {
			return ' <small class="woocommerce-price-suffix">KDV</small>';
		};
		add_filter('woocommerce_get_price_suffix', $this->suffix);
	}

	public function tearDown(): void
	{
		remove_filter('woocommerce_get_price_suffix', $this->suffix);
		parent::tearDown();
	}

	public function test_the_suffix_follows_per_day(): void
	{
		$vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', '1750');

		$method = new ReflectionMethod(CalendarView::class, 'price_html');
		$method->setAccessible(true);
		$html = (string) $method->invoke(null, $vehicle);

		$per_day = strpos($html, 'per day');
		$suffix  = strpos($html, 'woocommerce-price-suffix');
		$this->assertNotFalse($per_day, $html);
		$this->assertNotFalse($suffix, 'the store suffix is still shown');
		$this->assertLessThan($suffix, $per_day, 'amount, then "per day", then the suffix: ' . $html);
	}
}
