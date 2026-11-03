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
 * Two paths, same data (spec §6, plan R1/R2):
 * - Elementor's own `data_manager_v2->run( $stored )`, the stored string as is,
 *   only when a REST server is already up (or Elementor is already running its
 *   internal server), with the rest_api_init callbacks restored afterwards
 *   (run_keeping_rest_hooks()). On a plain page request run() would call
 *   remove_all_actions( 'rest_api_init' ) and build the global REST server with
 *   Elementor routes only, so a later rest_preload_api_request() in the same
 *   request (WooCommerce block checkout's Store API preload) would 404 and the
 *   client would fall back to a live fetch.
 * - Otherwise the kit array, mirroring the endpoint (colors.php:15-41): the
 *   active kit for the front end, system + custom colours via
 *   get_settings_for_display(), then the `elementor/globals/colors/items` filter.
 */
final class ElementorGlobals {

	private const PREFIX = 'globals/colors?';

	/**
	 * @return array{id: string, title: string, hex: string}|null
	 */
	public static function resolve(string $stored): ?array
	{
		if (! str_starts_with($stored, self::PREFIX) || ! self::elementor_ready()) {
			return null;
		}

		if (self::server_is_up()) {
			$data_manager = self::plugin()->data_manager_v2 ?? null;
			if (is_object($data_manager) && method_exists($data_manager, 'run')) {
				$item = self::to_result(self::run_keeping_rest_hooks($data_manager, $stored));
				if (null !== $item) {
					return $item;
				}
			}
		}

		$query = array();
		parse_str( (string) wp_parse_url($stored, PHP_URL_QUERY), $query);
		$id = isset($query['id']) && is_string($query['id']) ? $query['id'] : '';
		if ('' === $id) {
			return null;
		}

		$items = self::kit_items();
		return isset($items[ $id ]) ? self::to_result($items[ $id ]) : null;
	}

	/**
	 * A real REST server is already built for this request, or Elementor is
	 * already running its internal one (plan R1, M1: after Elementor's
	 * kill_server() the action count stays but the global is false).
	 */
	public static function server_is_up(): bool
	{
		if (did_action('rest_api_init') > 0 && ( $GLOBALS['wp_rest_server'] ?? null ) instanceof \WP_REST_Server) {
			return true;
		}
		if (! self::elementor_ready()) {
			return false;
		}
		$data_manager = self::plugin()->data_manager_v2 ?? null;
		return is_object($data_manager) && method_exists($data_manager, 'is_internal') && true === $data_manager->is_internal();
	}

	/**
	 * Elementor's run_server() calls remove_all_actions( 'rest_api_init' ) the
	 * first time it runs, even when the REST server is already up. Measured: a
	 * server rebuilt later in the same request then carried Elementor's routes
	 * only. The callbacks are put back as they were.
	 *
	 * @param object $data_manager Elementor data_manager_v2.
	 * @return mixed run() result.
	 */
	private static function run_keeping_rest_hooks(object $data_manager, string $stored): mixed
	{
		global $wp_filter;
		$saved = isset($wp_filter['rest_api_init']) ? clone $wp_filter['rest_api_init'] : null;
		try {
			return $data_manager->run($stored);
		} finally {
			if (null !== $saved) {
				$wp_filter['rest_api_init'] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restores the exact hook list Elementor's run_server() emptied.
			}
		}
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
