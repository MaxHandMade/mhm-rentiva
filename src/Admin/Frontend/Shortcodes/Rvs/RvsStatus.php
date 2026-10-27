<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Rvs;

use MHMRentiva\Admin\Booking\Core\Status;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Booking status -> status tag (`.rvs-status--<modifier>`) with its label.
 *
 * The label text always comes from Status::get_label(); the modifier only
 * picks the colour pair. Unknown statuses fall back to `pending` with the raw
 * value as the label.
 */
final class RvsStatus {

	private const MODIFIERS = array(
		Status::DRAFT           => 'pending',
		Status::PENDING         => 'pending',
		Status::PENDING_PAYMENT => 'payment',
		Status::CONFIRMED       => 'confirmed',
		Status::IN_PROGRESS     => 'active',
		Status::COMPLETED       => 'done',
		Status::CANCELLED       => 'cancelled',
		Status::REFUNDED        => 'cancelled',
		Status::NO_SHOW         => 'cancelled',
	);

	/**
	 * @return array{modifier: string, label: string}
	 */
	public static function for(string $status): array
	{
		return array(
			'modifier' => self::MODIFIERS[ $status ] ?? 'pending',
			'label'    => Status::get_label($status),
		);
	}
}
