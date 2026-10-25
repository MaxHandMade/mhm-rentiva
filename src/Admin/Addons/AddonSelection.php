<?php
/**
 * The add-ons a booking carries, with their quantities.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Core\MetaKeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Selected add-on ids plus id => quantity (Aşama 2 PR G, K12).
 *
 * Two readers only: from_input() (a form, an AJAX call, an admin screen) and
 * for_booking() (a stored booking). The id list keeps the shape every reader
 * has always seen (`_mhmrentiva_selected_addons`, a list -- the add-on reads
 * it); quantities live beside it in `_mhmrentiva_addon_quantities`, holding
 * only those above 1, so a booking without that row is all ones.
 *
 * Invariant: every key of quantities() is in ids().
 */
final class AddonSelection {

	/** The most anyone can take of one add-on. */
	public const MAX = 20;

	/**
	 * @param list<int>       $ids
	 * @param array<int, int> $quantities Above 1 only.
	 */
	private function __construct( private array $ids, private array $quantities ) {
	}

	/**
	 * An add-on's maximum quantity (1 = a plain checkbox), 1..MAX.
	 */
	public static function max_quantity( int $addon_id ): int {
		$max = (int) get_post_meta( $addon_id, MetaKeys::ADDON_MAX_QUANTITY, true );
		return max( 1, min( self::MAX, $max ) );
	}

	/**
	 * Sanitize callback for the add-on screen's "Maximum quantity" field.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_max( $value ): string {
		return (string) max( 1, min( self::MAX, (int) $value ) );
	}

	/**
	 * A visitor's or an admin's choice: only sellable add-ons, each quantity
	 * clamped to 1..its maximum. An optional add-on asked for 0 times is
	 * dropped; a selected required one never goes below 1. A missing or
	 * malformed quantity means 1. A required add-on that was not selected is
	 * not added here -- the booking form refuses that request itself.
	 *
	 * @param array<int|string, mixed> $ids        Candidate add-on ids.
	 * @param array<int|string, mixed> $quantities Raw id => quantity (addon_qty[]).
	 */
	public static function from_input( array $ids, array $quantities ): self {
		$out_ids = array();
		$out_qty = array();
		foreach ( AddonManager::filter_sellable( $ids ) as $addon_id ) {
			$raw = $quantities[ $addon_id ] ?? null;
			$qty = ( is_scalar( $raw ) && is_numeric( $raw ) ) ? (int) $raw : 1;
			if ( $qty < 1 ) {
				if ( '1' !== (string) get_post_meta( $addon_id, 'mhmrentiva_addon_required', true ) ) {
					continue;
				}
				$qty = 1;
			}
			$qty       = min( $qty, self::max_quantity( $addon_id ) );
			$out_ids[] = $addon_id;
			if ( $qty > 1 ) {
				$out_qty[ $addon_id ] = $qty;
			}
		}
		return new self( $out_ids, $out_qty );
	}

	/**
	 * Trusted server-side values (a stored booking, a computed cart line).
	 *
	 * @param array<int|string, mixed> $ids
	 * @param array<int|string, mixed> $quantities
	 */
	public static function of( array $ids, array $quantities = array() ): self {
		$out_ids = array();
		foreach ( $ids as $candidate ) {
			$addon_id = (int) $candidate;
			if ( $addon_id > 0 && ! in_array( $addon_id, $out_ids, true ) ) {
				$out_ids[] = $addon_id;
			}
		}
		$out_qty = array();
		foreach ( $quantities as $addon_id => $qty ) {
			$addon_id = (int) $addon_id;
			$qty      = is_scalar( $qty ) ? min( self::MAX, (int) $qty ) : 1;
			if ( $qty > 1 && in_array( $addon_id, $out_ids, true ) ) {
				$out_qty[ $addon_id ] = $qty;
			}
		}
		return new self( $out_ids, $out_qty );
	}

	/**
	 * What a stored booking carries; no quantities row = all ones.
	 */
	public static function for_booking( int $booking_id ): self {
		$ids        = get_post_meta( $booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, true );
		$quantities = get_post_meta( $booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, true );
		return self::of( is_array( $ids ) ? $ids : array(), is_array( $quantities ) ? $quantities : array() );
	}

	/**
	 * @return list<int>
	 */
	public function ids(): array {
		return $this->ids;
	}

	/**
	 * The quantity of one add-on: 0 when it is not selected.
	 */
	public function quantity( int $addon_id ): int {
		if ( ! in_array( $addon_id, $this->ids, true ) ) {
			return 0;
		}
		return $this->quantities[ $addon_id ] ?? 1;
	}

	/**
	 * @return array<int, int> Quantities above 1, by add-on id.
	 */
	public function quantities(): array {
		return $this->quantities;
	}

	/**
	 * Write the id list and, when any quantity is above 1, the quantities row.
	 */
	public function store( int $booking_id ): void {
		update_post_meta( $booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, $this->ids );
		if ( array() === $this->quantities ) {
			delete_post_meta( $booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES );
		} else {
			update_post_meta( $booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, $this->quantities );
		}
	}
}
