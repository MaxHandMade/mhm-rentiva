<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;

/**
 * The static halves of the settings gates (spec 2026-10-10-elementor-kopru-dilim-3a §3),
 * shared by the Lite and the Pro suites:
 *   - G3 enum_mismatches(): editor options the schema would replace with its default;
 *   - G4 layers(): where a (tag, key) still exists, on each of the five layers a
 *     removal has to clear.
 */
final class SettingGates
{
	/**
	 * Live option translations applied before the schema sees the value (spec G3,
	 * Fable I2): my-bookings `filterStatus` 'all' => '' (assets/blocks/my-bookings/index.js:40).
	 */
	public const ALIASES = array( 'rentiva_my_bookings' => array( 'status' => array( 'all' => '' ) ) );

	public const LAYERS = array( 'block.json', 'editor control', 'elementor control', 'schema', 'alias' );

	/**
	 * Every editor option the schema's `values` would reject, by
	 * "surface|tag|key|option" => where it was read. '' is "not set" and always allowed.
	 *
	 * @param array<string,array{tag:string,base_dir?:string}>|null $blocks  See SettingProbe::block_surfaces().
	 * @param array<string,string>|null                             $widgets See SettingProbe::elementor_surfaces().
	 * @return array<string,string>
	 */
	public static function enum_mismatches(?array $blocks = null, ?array $widgets = null): array
	{
		$out = array();

		$slugs = array();
		foreach (SettingProbe::block_surfaces($blocks) as $surface) {
			$slugs[ $surface['block'] ] = $surface['tag'];
		}
		foreach ($slugs as $slug => $tag) {
			$schema = AllowlistRegistry::get_schema($tag);
			foreach (SettingProbe::editor_options($slug) as $attr => $options) {
				$key = KeyNormalizer::normalize((string) $attr, $schema);
				foreach ($options as $option) {
					if (self::rejected($tag, $key, (string) $option, $schema)) {
						$out[ "block|$tag|$key|$option" ] = "assets/blocks/$slug/index.js $attr";
					}
				}
			}
		}

		foreach (SettingProbe::elementor_surfaces($widgets) as $surface) {
			$control = SettingProbe::data_controls($surface['widget'])[ $surface['control'] ] ?? array();
			if (! in_array($control['type'] ?? '', array( 'select', 'choose', 'select2' ), true) || 'vehicle_id' === $surface['control']) {
				continue;
			}
			$schema = AllowlistRegistry::get_schema($surface['tag']);
			$key    = SettingProbe::key($surface);
			foreach (array_keys((array) ( $control['options'] ?? array() )) as $option) {
				if (self::rejected($surface['tag'], $key, (string) $option, $schema)) {
					$out[ "elementor|{$surface['tag']}|$key|$option" ] = "{$surface['widget']}::{$surface['control']}";
				}
			}
		}

		ksort($out);
		return $out;
	}

	/**
	 * Where (tag, key) still exists, layer => places (spec §3 G4, Fable I3):
	 *   block.json        -- an attribute of the tag's block that normalizes to key;
	 *   editor control    -- an attribute the block's editor script writes that normalizes to key
	 *                        (BlockEditorWriteScanner);
	 *   elementor control -- an own-stack control of the tag's widgets that normalizes to key;
	 *   schema            -- key in AllowlistRegistry's list (and so defaults) for the tag;
	 *   alias             -- another schema entry of the tag carrying key, or key's camelCase, as an
	 *                        alias (a leftover alias would route the old saved value into a live key).
	 *
	 * @param array<string,array{tag:string,base_dir?:string}>|null $blocks  See SettingProbe::block_surfaces().
	 * @param array<string,string>|null                             $widgets See SettingProbe::elementor_surfaces().
	 * @return array<string,list<string>>
	 */
	public static function layers(string $tag, string $key, ?array $blocks = null, ?array $widgets = null): array
	{
		$schema = AllowlistRegistry::get_schema($tag);
		$found  = array_fill_keys(self::LAYERS, array());

		$slugs       = array();
		$block_attrs = array();
		foreach (SettingProbe::block_surfaces($blocks) as $surface) {
			if ($surface['tag'] !== $tag) {
				continue;
			}
			$slugs[ $surface['block'] ]      = true;
			$block_attrs[ $surface['attr'] ] = true;
			if (KeyNormalizer::normalize($surface['attr'], $schema) === $key) {
				$found['block.json'][] = $surface['block'] . '/' . $surface['attr'];
			}
		}
		foreach (array_keys($slugs) as $slug) {
			$config = SettingProbe::block_json_dir($slug);
			$path   = $config . '/index.js';
			if (! is_readable($path)) {
				continue;
			}
			$scan = BlockEditorWriteScanner::scan((string) file_get_contents($path), $slug); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
			foreach (array_unique(array_merge($scan['literal_keys'], $scan['helper_keys'])) as $attr) {
				if (KeyNormalizer::normalize((string) $attr, $schema) === $key) {
					$found['editor control'][] = $slug . '/index.js ' . $attr;
				}
			}
		}

		foreach (SettingProbe::elementor_surfaces($widgets) as $surface) {
			if ($surface['tag'] === $tag && SettingProbe::key($surface) === $key) {
				$found['elementor control'][] = $surface['widget'] . '::' . $surface['control'];
			}
		}

		if (isset($schema[ $key ])) {
			$found['schema'][] = 'AllowlistRegistry ' . $tag;
		}

		$camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
		foreach ($schema as $canonical => $config) {
			if ($canonical === $key) {
				continue;
			}
			foreach ((array) ( $config['aliases'] ?? array() ) as $alias) {
				// A camelCase alias that is a live block attribute of this tag belongs to
				// the canonical it resolves to (the rule-2 rebind of a twin: featured
				// showBookButton -> show_booking_button after show_book_button was
				// removed); the block.json layer above already proved it is not $key's.
				if ($alias === $camel && $alias !== $key && isset($block_attrs[ $alias ])) {
					continue;
				}
				if ($alias === $key || $alias === $camel) {
					$found['alias'][] = $canonical . ' <- ' . $alias;
				}
			}
		}

		return $found;
	}

	/**
	 * @param array<string,array<string,mixed>> $schema Tag schema.
	 */
	private static function rejected(string $tag, string $key, string $option, array $schema): bool
	{
		$values = $schema[ $key ]['values'] ?? null;
		if (! is_array($values) || '' === $option) {
			return false;
		}
		if (isset(self::ALIASES[ $tag ][ $key ][ $option ])) {
			$option = self::ALIASES[ $tag ][ $key ][ $option ];
			if ('' === $option) {
				return false;
			}
		}
		return ! in_array($option, array_map('strval', $values), true);
	}
}
