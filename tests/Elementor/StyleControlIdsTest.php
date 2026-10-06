<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Tests\Support\StyleControlInventory;
use WP_UnitTestCase;

/**
 * Spec §3 T1: a style control's id is what Elementor stores the user's value under,
 * so it must not change with the admin language. Before Slice 2 the helpers derived
 * group names from the translated label (`tipografi_typography`, `golge_shadow`):
 * a value saved in one language produced no CSS in another.
 */
final class StyleControlIdsTest extends WP_UnitTestCase
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	public function tear_down(): void
	{
		// Leave the next test an English stack.
		foreach (array_keys(StyleControlInventory::WIDGETS) as $class) {
			StyleControlInventory::style_controls($class);
		}
		parent::tear_down();
	}

	/**
	 * @return array<string,list<string>>
	 */
	private function id_sets(): array
	{
		$sets = array();
		foreach (array_keys(StyleControlInventory::WIDGETS) as $class) {
			$ids = array_keys(StyleControlInventory::style_controls($class));
			sort($ids);
			$sets[ $class ] = $ids;
		}

		return $sets;
	}

	public function test_style_control_ids_do_not_depend_on_the_admin_language(): void
	{
		$english = $this->id_sets();

		switch_to_locale('tr_TR');
		load_textdomain('mhm-rentiva', dirname(__DIR__, 2) . '/languages/mhm-rentiva-tr_TR.mo', 'tr_TR');
		try {
			// Without the translated label the run would just repeat en_US.
			$this->assertSame('Tipografi', __('Typography', 'mhm-rentiva'), 'tr_TR translation did not load; the locale run would prove nothing.');
			$turkish = $this->id_sets();
		} finally {
			restore_previous_locale();
			unload_textdomain('mhm-rentiva', true);
		}

		$cyrillic_filter = static function ($translation, $text, $domain) {
			return 'mhm-rentiva' === $domain ? 'Типография ' . $text : $translation;
		};
		add_filter('gettext', $cyrillic_filter, 10, 3);
		try {
			$cyrillic = $this->id_sets();
		} finally {
			remove_filter('gettext', $cyrillic_filter, 10);
		}

		foreach ($english as $class => $ids) {
			$this->assertNotEmpty($ids, $class . ' registers no style controls.');
			$this->assertSame($ids, $turkish[ $class ], $class . ': style control ids differ under tr_TR.');
			$this->assertSame($ids, $cyrillic[ $class ], $class . ': style control ids differ under a Cyrillic label.');
		}
	}

	public function test_live_controls_keep_their_english_ids(): void
	{
		$search  = StyleControlInventory::style_controls(self::NS . 'UnifiedSearchWidget');
		$booking = StyleControlInventory::style_controls(self::NS . 'BookingFormWidget');

		$this->assertArrayHasKey('general-style_typography_font_family', $search);
		$this->assertArrayHasKey('main_color', $search);
		$this->assertArrayHasKey('shadow_shadow_box_shadow_type', $booking);
		$this->assertArrayHasKey('form_background', $booking);
		$this->assertArrayHasKey('form_border_radius', $booking);
	}

	public function test_dead_groups_get_new_names(): void
	{
		$card = StyleControlInventory::style_controls(self::NS . 'VehicleCardWidget');
		foreach (array( 'title_typography_font_family', 'price_amount_typography_font_family', 'booking_button_typography_font_family', 'card_border_border', 'card_shadow_box_shadow_type' ) as $id) {
			$this->assertArrayHasKey($id, $card, 'card: ' . $id);
		}

		foreach (array( 'VehiclesListWidget', 'VehiclesGridWidget', 'FeaturedVehiclesWidget' ) as $short) {
			$this->assertArrayHasKey('title_style_typography_font_family', StyleControlInventory::style_controls(self::NS . $short), $short);
		}
		foreach (array( 'VehiclesListWidget', 'VehiclesGridWidget' ) as $short) {
			$this->assertArrayHasKey('price_style_typography_font_family', StyleControlInventory::style_controls(self::NS . $short), $short);
		}

		$booking = StyleControlInventory::style_controls(self::NS . 'BookingFormWidget');
		$this->assertArrayHasKey('submit_typography_font_family', $booking);

		$all = array();
		foreach (array_keys(StyleControlInventory::WIDGETS) as $class) {
			$all += StyleControlInventory::style_controls($class);
		}
		foreach (array( 'price_typography_font_family', 'button_typography_font_family', 'typography_typography_font_family', 'vehicle-title_typography_font_family' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $all, $gone);
		}
	}
}
