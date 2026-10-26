<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Rvs;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The booking journey's steps, as plain data.
 *
 * Three steps: dates and extras (the booking form), details and payment
 * (WooCommerce checkout), confirmation (the order-received page). The driver
 * and payment sections of the canvas stay in WooCommerce. The booking form,
 * the checkout and the thank-you page all print templates/partials/rvs/steps.php
 * with these items, so a step reads the same on every page.
 */
final class RvsSteps {

	public const TOTAL = 3;

	/**
	 * @param int $current The step the customer is on, 1..3; out-of-range values are clamped.
	 * @return list<array{num: int, label: string, state: 'done'|'current'|'todo'}>
	 */
	public static function items(int $current): array
	{
		$current = max(1, min(self::TOTAL, $current));
		$labels  = array(
			1 => __('Dates and extras', 'mhm-rentiva'),
			2 => __('Details and payment', 'mhm-rentiva'),
			3 => __('Confirmation', 'mhm-rentiva'),
		);

		$items = array();
		foreach ($labels as $num => $label) {
			if ($num < $current) {
				$state = 'done';
			} elseif ($num === $current) {
				$state = 'current';
			} else {
				$state = 'todo';
			}
			$items[] = array(
				'num'   => $num,
				'label' => $label,
				'state' => $state,
			);
		}
		return $items;
	}
}
