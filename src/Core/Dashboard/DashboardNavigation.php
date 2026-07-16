<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Provides dashboard navigation items by dashboard context.
 */
final class DashboardNavigation {

	/**
	 * Nav items backed by a feature this build may not ship, keyed by item slug.
	 *
	 * Value is the FQN of the class that renders the item's panel. Messaging is a
	 * Pro surface: its panel is produced by `[rentiva_messages]`, whose class
	 * AccountMessages is carved out of Lite. The shortcode registry drops the tag
	 * to match, but an unregistered shortcode degrades to its own literal source
	 * text -- so an unfiltered Messages tab showed every Lite customer the raw
	 * string `[rentiva_messages hide_nav="1"]`.
	 *
	 * Declared as a string, not ::class: the class genuinely does not exist in
	 * Lite, matching how ShortcodeServiceProvider declares the same seam.
	 */
	private const FEATURE_BACKED_ITEMS = array(
		'messages' => 'MHMRentiva\Admin\Frontend\Shortcodes\Account\AccountMessages',
	);

	/**
	 * Get navigation items by context.
	 *
	 * Items whose backing feature this build does not ship are dropped rather than
	 * disabled or labelled: per the Lite carve decision a missing feature simply
	 * does not render. Filtering here covers both consumers at once -- the template
	 * that draws the tabs and UserDashboard::resolve_tab(), which validates
	 * `?tab=` against these same keys, so a hand-typed `?tab=messages` now falls
	 * back to Overview instead of rendering an empty panel.
	 *
	 * @param string $context customer|vendor
	 * @return array<string, array<string, string>>
	 */
	public static function get_items(string $context): array
	{
		$map = array(
			'customer' => array(
				'overview'  => array(
					'label' => __('Overview', 'mhm-rentiva'),
					'icon'  => 'dashboard',
				),
				'bookings'  => array(
					'label' => __('My Bookings', 'mhm-rentiva'),
					'icon'  => 'calendar',
				),
				'favorites' => array(
					'label' => __('Favorites', 'mhm-rentiva'),
					'icon'  => 'heart',
				),
				'messages'  => array(
					'label' => __('Messages', 'mhm-rentiva'),
					'icon'  => 'mail',
				),
			),
			'vendor'   => array(
				'overview'    => array(
					'label' => __('Overview', 'mhm-rentiva'),
					'icon'  => 'dashboard',
				),
				'listings'    => array(
					'label' => __('Listings', 'mhm-rentiva'),
					'icon'  => 'car',
				),
				'bookings'    => array(
					'label' => __('Booking Requests', 'mhm-rentiva'),
					'icon'  => 'calendar',
				),
				'ledger'      => array(
					'label' => __('Ledger & Payouts', 'mhm-rentiva'),
					'icon'  => 'chart',
				),
				'settings'    => array(
					'label' => __('Payment Settings', 'mhm-rentiva'),
					'icon'  => 'settings',
				),
				'messages'    => array(
					'label' => __('Messages', 'mhm-rentiva'),
					'icon'  => 'mail',
				),
				'reliability' => array(
					'label' => __('Reliability & Penalties', 'mhm-rentiva'),
					'icon'  => 'shield',
				),
				'profil'      => array(
					'label' => __('Profile', 'mhm-rentiva'),
					'icon'  => 'user',
				),
			),
		);

		return self::drop_unavailable_items($map[ $context ] ?? array());
	}

	/**
	 * Drop nav items whose backing feature this build does not ship.
	 *
	 * @param array<string, array<string, string>> $items Items for one context.
	 * @return array<string, array<string, string>> Items minus absent features.
	 */
	private static function drop_unavailable_items(array $items): array
	{
		foreach (self::FEATURE_BACKED_ITEMS as $slug => $backing_class) {
			if (isset($items[ $slug ]) && ! class_exists($backing_class)) {
				unset($items[ $slug ]);
			}
		}

		return $items;
	}
}
