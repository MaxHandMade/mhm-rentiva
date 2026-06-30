<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing\LiteOverflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the set of catalog item IDs hidden from the public frontend because
 * the site is in Lite and over the per-type limit. Single option, reversible
 * by clearing — no postmeta, no migration.
 */
final class OverflowRegistry {

	public const OPTION = 'mhm_rentiva_lite_overflow_hidden';

	/** @var string[] */
	public const TYPES = array( 'vehicle', 'vehicle_addon', 'route' );

	/** @return int[] */
	public static function get( string $type ): array {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return array();
		}
		$all = self::all();
		return $all[ $type ] ?? array();
	}

	/** @param array<int|string> $ids */
	public static function set( string $type, array $ids ): void {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return;
		}
		$all          = self::all();
		$all[ $type ] = array_values( array_unique( array_map( 'intval', $ids ) ) );
		update_option( self::OPTION, $all, false );
	}

	public static function isHidden( string $type, int $id ): bool {
		return in_array( $id, self::get( $type ), true );
	}

	public static function clearAll(): void {
		$empty = array();
		foreach ( self::TYPES as $type ) {
			$empty[ $type ] = array();
		}
		update_option( self::OPTION, $empty, false );
	}

	/** @return array<string,int[]> */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$out    = array();
		foreach ( self::TYPES as $type ) {
			$ids          = ( is_array( $stored ) && isset( $stored[ $type ] ) && is_array( $stored[ $type ] ) )
				? array_map( 'intval', $stored[ $type ] )
				: array();
			$out[ $type ] = $ids;
		}
		return $out;
	}
}
