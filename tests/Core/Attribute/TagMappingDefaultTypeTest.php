<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use WP_UnitTestCase;

final class TagMappingDefaultTypeTest extends WP_UnitTestCase
{
	public function test_show_booking_button_defaults_are_string_flags_for_key_tags(): void
	{
		$registry = AllowlistRegistry::get_registry();
		$tags     = array(
			'rentiva_search_results',
			'rentiva_transfer_results',
			'rentiva_availability_calendar',
		);

		foreach ($tags as $tag) {
			$this->assertArrayHasKey($tag, $registry);
			$this->assertArrayHasKey('show_booking_button', $registry[$tag]);

			$default = $registry[$tag]['show_booking_button']['default'] ?? null;
			$this->assertIsString($default, sprintf('%s show_booking_button default must be string', $tag));
			$this->assertContains($default, array('0', '1'), sprintf('%s show_booking_button default must be "0" or "1"', $tag));
		}
	}

	public function test_legacy_btn_keys_are_not_mapped_when_canonical_button_keys_exist(): void
	{
		$registry = AllowlistRegistry::get_registry();

		$tagExpectations = array(
			'rentiva_vehicles_list' => array('show_booking_button', 'show_favorite_button', 'show_compare_button'),
			'rentiva_vehicles_grid' => array('show_booking_button', 'show_favorite_button', 'show_compare_button'),
			'rentiva_my_favorites'  => array('show_booking_button', 'show_favorite_button'),
		);

		foreach ($tagExpectations as $tag => $requiredCanonicals) {
			$this->assertArrayHasKey($tag, $registry);

			foreach ($requiredCanonicals as $canonicalKey) {
				$this->assertArrayHasKey($canonicalKey, $registry[$tag], sprintf('%s must define canonical key %s', $tag, $canonicalKey));
			}

			$this->assertArrayNotHasKey('show_booking_btn', $registry[$tag], sprintf('%s should not expose legacy show_booking_btn key', $tag));
			$this->assertArrayNotHasKey('show_favorite_btn', $registry[$tag], sprintf('%s should not expose legacy show_favorite_btn key', $tag));
			$this->assertArrayNotHasKey('show_compare_btn', $registry[$tag], sprintf('%s should not expose legacy show_compare_btn key', $tag));
		}
	}

	public function test_canonical_favorite_and_compare_keys_include_legacy_aliases(): void
	{
		$schema = AllowlistRegistry::get_schema('rentiva_vehicles_grid');

		$this->assertArrayHasKey('show_favorite_button', $schema);
		$this->assertArrayHasKey('show_compare_button', $schema);

		$favoriteAliases = $schema['show_favorite_button']['aliases'] ?? array();
		$compareAliases  = $schema['show_compare_button']['aliases'] ?? array();

		$this->assertContains('show_favorite_btn', $favoriteAliases);
		$this->assertContains('show_compare_btn', $compareAliases);
	}
}
