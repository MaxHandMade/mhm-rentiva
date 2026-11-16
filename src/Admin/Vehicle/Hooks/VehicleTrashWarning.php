<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Hooks;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Tells the manager when a vehicle that still has upcoming bookings goes to
 * the Trash, and lets it come back as it was.
 *
 * Trashing is not refused: it is reversible and retiring a vehicle is a real
 * case. But the bookings keep pointing at it while its page stops answering
 * customers, so the manager hears about it -- on every path that trashes (row
 * action, bulk action, edit screen), since `trashed_post` fires for all of
 * them. The warning is held for the acting user until the next list screen.
 */
final class VehicleTrashWarning {

	private const POST_TYPE = 'mhmrentiva_vehicle';

	/** How many booking links the notice lists before summarising the rest. */
	private const LINKS_SHOWN = 5;

	public static function register(): void
	{
		add_action('trashed_post', array( self::class, 'on_trashed' ));
		add_action('admin_notices', array( self::class, 'render_notice' ));
		add_filter('wp_untrash_post_status', array( self::class, 'untrash_status' ), 10, 3);
	}

	public static function transient_key(int $user_id): string
	{
		return 'mhmrentiva_vehicle_trash_warning_' . $user_id;
	}

	/**
	 * @param int $post_id The trashed post.
	 */
	public static function on_trashed($post_id): void
	{
		$post_id = (int) $post_id;
		$user_id = get_current_user_id();
		if ($user_id <= 0 || self::POST_TYPE !== get_post_type($post_id)) {
			return;
		}

		$bookings = BookingConflicts::upcoming_booking_ids($post_id);
		if (array() === $bookings) {
			return;
		}

		$pending             = self::pending($user_id);
		$pending[ $post_id ] = $bookings;
		set_transient(self::transient_key($user_id), $pending, 10 * MINUTE_IN_SECONDS);
	}

	public static function render_notice(): void
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || 'edit-' . self::POST_TYPE !== $screen->id) {
			return;
		}

		$user_id = get_current_user_id();
		$pending = self::pending($user_id);
		if (array() === $pending) {
			return;
		}
		delete_transient(self::transient_key($user_id));

		foreach ($pending as $vehicle_id => $bookings) {
			$vehicle_id = (int) $vehicle_id;
			// Restored (WP's own Undo) or deleted since: nothing to warn about.
			if ('trash' !== get_post_status($vehicle_id)) {
				continue;
			}
			self::print_notice($vehicle_id, array_map('intval', (array) $bookings));
		}
	}

	/**
	 * Since WordPress 5.6 a post restored from the Trash comes back as a draft.
	 * A vehicle comes back as it was, so restoring it actually undoes the trash.
	 *
	 * @param string $new_status      Status core is about to assign.
	 * @param int    $post_id         The restored post.
	 * @param string $previous_status Status the post had when it was trashed.
	 */
	public static function untrash_status($new_status, $post_id, $previous_status): string
	{
		if (self::POST_TYPE !== get_post_type( (int) $post_id)) {
			return (string) $new_status;
		}

		return wp_untrash_post_set_previous_status( (string) $new_status, (int) $post_id, (string) $previous_status);
	}

	/**
	 * @return array<int, list<int>>
	 */
	private static function pending(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$stored = get_transient(self::transient_key($user_id));

		return is_array($stored) ? $stored : array();
	}

	/**
	 * @param list<int> $bookings
	 */
	private static function print_notice(int $vehicle_id, array $bookings): void
	{
		$count  = count($bookings);
		$linked = array_slice($bookings, 0, self::LINKS_SHOWN);
		$more   = $count - count($linked);

		$restore = wp_nonce_url(
			admin_url('post.php?post=' . $vehicle_id . '&action=untrash'),
			'untrash-post_' . $vehicle_id
		);

		$message = sprintf(
			/* translators: 1: vehicle title, 2: number of upcoming bookings. */
			_n(
				'“%1$s” was moved to the Trash but still has %2$d upcoming booking. The booking keeps this vehicle, but customers can no longer open the vehicle’s page.',
				'“%1$s” was moved to the Trash but still has %2$d upcoming bookings. The bookings keep this vehicle, but customers can no longer open the vehicle’s page.',
				$count,
				'mhm-rentiva'
			),
			get_the_title($vehicle_id),
			$count
		);

		echo '<div class="notice notice-warning is-dismissible mhm-vehicle-trash-warning"><p>';
		echo esc_html($message);
		echo '</p><p>';
		echo esc_html__('Bookings:', 'mhm-rentiva');
		foreach ($linked as $i => $booking_id) {
			echo ( 0 === $i ? ' ' : ', ' ) . '<a href="' . esc_url(admin_url('post.php?post=' . $booking_id . '&action=edit')) . '">#' . esc_html( (string) $booking_id) . '</a>';
		}
		if ($more > 0) {
			/* translators: %d: number of further bookings not linked in the list. */
			echo ', ' . esc_html(sprintf(_n('and %d more', 'and %d more', $more, 'mhm-rentiva'), $more));
		}
		echo '</p><p><a class="button" href="' . esc_url($restore) . '">' . esc_html__('Restore vehicle', 'mhm-rentiva') . '</a></p></div>';
	}
}
