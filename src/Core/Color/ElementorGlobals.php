<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Resolves a stored Elementor global colour reference (`globals/colors?id=xxx`,
 * the raw `__globals__` value) to a concrete '#rrggbb'.
 *
 * Two paths, same data (spec §6, plan R1/R2, Fable branch audit I1):
 * - Elementor's own `data_manager_v2->run( $stored )`, the stored string as is,
 *   only while Elementor is already running its internal REST server (its own
 *   CSS generation). Then `is_internal()` is true and Elementor's server is set,
 *   so run_server() is a no-op: no hook removal, no flag change, no server bind.
 *   In any other state run() would remove every rest_api_init callback when its
 *   own server is unset (even with the core server up: a server rebuilt later
 *   carried Elementor's routes only) and would leave `is_internal` true for the
 *   rest of the request, which opens READABLE globals/* to every visitor.
 * - Otherwise the kit array, mirroring the endpoint (colors.php:15-41): the
 *   active kit for the front end, system + custom colours via
 *   get_settings_for_display(), then the `elementor/globals/colors/items` filter.
 *   `globals/colors` has no Before/After processors, so both paths agree.
 */
final class ElementorGlobals {

	private const PREFIX = 'globals/colors?';

	/**
	 * @return array{id: string, title: string, hex: string}|null
	 */
	public static function resolve(string $stored): ?array
	{
		return self::resolve_all(array( $stored ))[ $stored ] ?? null;
	}

	/**
	 * Several stored references at once; the kit's item list is built at most
	 * once for the whole call (Fable M3).
	 *
	 * @param list<string> $stored_list
	 * @return array<string, array{id: string, title: string, hex: string}|null> keyed by the stored string
	 */
	public static function resolve_all(array $stored_list): array
	{
		$out   = array();
		$items = null;
		foreach ($stored_list as $stored) {
			$out[ $stored ] = null;
			if (! str_starts_with($stored, self::PREFIX) || ! self::elementor_ready()) {
				continue;
			}

			if (self::server_is_up()) {
				$data_manager = self::plugin()->data_manager_v2 ?? null;
				if (is_object($data_manager) && method_exists($data_manager, 'run')) {
					$item = self::to_result($data_manager->run($stored));
					if (null !== $item) {
						$out[ $stored ] = $item;
						continue;
					}
				}
			}

			$query = array();
			parse_str( (string) wp_parse_url($stored, PHP_URL_QUERY), $query);
			$id = isset($query['id']) && is_string($query['id']) ? $query['id'] : '';
			if ('' === $id) {
				continue;
			}

			$items          = $items ?? self::kit_items();
			$out[ $stored ] = isset($items[ $id ]) ? self::to_result($items[ $id ]) : null;
		}
		return $out;
	}

	/**
	 * Elementor is already running its internal REST server (Fable I1). A core
	 * REST server alone is not enough: run() would flip `is_internal` for the
	 * rest of the request.
	 */
	public static function server_is_up(): bool
	{
		if (! self::elementor_ready()) {
			return false;
		}
		$data_manager = self::plugin()->data_manager_v2 ?? null;
		return is_object($data_manager) && method_exists($data_manager, 'is_internal') && true === $data_manager->is_internal();
	}

	private static function elementor_ready(): bool
	{
		return is_object(self::plugin()->kits_manager ?? null);
	}

	/**
	 * Elementor's plugin instance once it is loaded (it fills its managers on
	 * init), else a blank object so property reads stay null.
	 */
	private static function plugin(): object
	{
		$plugin = class_exists('\Elementor\Plugin') ? \Elementor\Plugin::$instance : null;
		return is_object($plugin) ? $plugin : new \stdClass();
	}

	/**
	 * The endpoint's item list, built from the kit array (plan R2).
	 *
	 * @return array<string, mixed>
	 */
	private static function kit_items(): array
	{
		$kits = self::plugin()->kits_manager ?? null;
		if (! is_object($kits) || ! method_exists($kits, 'get_active_kit_for_frontend')) {
			return array();
		}
		$kit = $kits->get_active_kit_for_frontend();
		if (! is_object($kit) || ! method_exists($kit, 'get_main_id') || 0 === (int) $kit->get_main_id()) {
			return array();
		}

		$system = $kit->get_settings_for_display('system_colors');
		$custom = $kit->get_settings_for_display('custom_colors');
		$rows   = array_merge(is_array($system) ? $system : array(), is_array($custom) ? $custom : array());

		$items = array();
		foreach ($rows as $row) {
			if (! is_array($row) || ! isset($row['_id']) || ! is_string($row['_id'])) {
				continue;
			}
			$items[ $row['_id'] ] = array(
				'id'    => $row['_id'],
				'title' => $row['title'] ?? '',
				'value' => $row['color'] ?? '',
			);
		}

		// Same filter the endpoint applies (design-system-sync adds v4 variables here).
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Elementor's own filter, applied exactly as the globals/colors endpoint (colors.php:41) applies it so both paths agree. Not a hook this plugin defines.
		$items = apply_filters('elementor/globals/colors/items', $items);
		return is_array($items) ? $items : array();
	}

	/**
	 * @param mixed $item Endpoint item {id, title, value}.
	 * @return array{id: string, title: string, hex: string}|null
	 */
	private static function to_result(mixed $item): ?array
	{
		if (! is_array($item) || ! isset($item['value'])) {
			return null;
		}
		$hex = ColorMath::normalize($item['value']);
		if (null === $hex) {
			return null;
		}
		return array(
			'id'    => isset($item['id']) && is_scalar($item['id']) ? (string) $item['id'] : '',
			'title' => isset($item['title']) && is_scalar($item['title']) ? (string) $item['title'] : '',
			'hex'   => $hex,
		);
	}
}
