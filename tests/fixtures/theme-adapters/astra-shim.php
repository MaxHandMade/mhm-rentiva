<?php
/**
 * Stand-in for the two Astra 4.14.0 entry points ThemeAdapters reads, in the same
 * shape (astra/inc/extras.php:868-873, inc/class-astra-global-palette.php:137,
 * :245-257). The PHPUnit site runs no theme (get_template() === 'default'), so the
 * real functions are not loaded.
 *
 * The real functions only exist when Astra is the active template; the shim
 * answers only then, so other tests in the same process see "no Astra palette"
 * (read_astra() returns null for a non-array).
 *
 * Only loaded by tests/Core/Color/ThemeAdaptersTest.php; tests/ does not ship.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Universal.Files.SeparateFunctionsFromOO, Generic.Files.OneObjectStructurePerFile

if (! class_exists('Astra_Global_Palette')) {
	class Astra_Global_Palette
	{
		public static function get_default_color_palette()
		{
			return array(
				'currentPalette' => 'palette_1',
				'palettes'       => array(
					'palette_1' => array( '#046bd2', '#045cb4', '#1e293b' ),
					'palette_2' => array( '#0067FF', '#005EE9', '#0F172A' ),
					'palette_3' => array( '#0067FF', '#005EE9', '#0F172A' ),
					'palette_4' => array( '#FF7A3D', '#1A1A1A', '#0F172A' ),
				),
			);
		}

		public static function astra_get_active_global_palette()
		{
			$data = astra_get_palette_colors();
			return is_array($data) && isset($data['currentPalette']) ? $data['currentPalette'] : 'palette_1';
		}
	}
}

if (! function_exists('astra_get_palette_colors')) {
	function astra_get_palette_colors()
	{
		if ('astra' !== get_template()) {
			return false;
		}
		$palettes = get_option('astra-color-palettes', false);
		if (! $palettes) {
			$palettes = Astra_Global_Palette::get_default_color_palette();
		}
		return $palettes;
	}
}
