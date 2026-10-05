<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\CanonicalAttributeMapper;
use MHMRentiva\Core\Attribute\KeyNormalizer;

/**
 * Pure widget-settings -> canonical-attributes bridge.
 *
 * Deliberately Elementor-free and stateless so it can be unit tested without a
 * widget instance; widgets pass in already-live settings.
 */
final class WidgetAttributeBridge {

	/**
	 * Types for which '' means "no value" (schema/shortcode default applies).
	 * Includes types the schema may not use yet, for future-proofing.
	 * bool and string are absent on purpose: '' is a deliberate value there.
	 */
	private const EMPTY_MEANS_UNSET = array( 'int', 'float', 'idlist', 'enum', 'url' );

	/**
	 * Flatten Elementor URL control arrays to their url string.
	 * Without this Transformers would turn the array into '' and lose the link.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	public static function flatten_controls( array $settings ): array {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) ) {
				$settings[ $key ] = $value['url'];
			}
		}
		return $settings;
	}

	/**
	 * Drop values that mean "not set" so defaults can apply.
	 *
	 * @param string $tag      Shortcode tag.
	 * @param array  $settings Flattened settings.
	 * @return array
	 */
	public static function filter_empty( string $tag, array $settings ): array {
		$schema = AllowlistRegistry::get_schema( $tag );

		foreach ( $settings as $key => $value ) {
			if ( null === $value ) {
				// Elementor hands null for controls hidden by a condition; that is not "off".
				unset( $settings[ $key ] );
				continue;
			}
			if ( '' !== $value ) {
				continue;
			}
			$canonical = KeyNormalizer::normalize( (string) $key, $schema );
			$type      = $schema[ $canonical ]['type'] ?? null;
			if ( null !== $type && in_array( $type, self::EMPTY_MEANS_UNSET, true ) ) {
				unset( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Resolve settings into canonical attributes.
	 *
	 * Precedence per canonical key: $explicit wins over every raw setting; among
	 * raw settings the exact canonical name beats aliases, else first seen.
	 *
	 * @param string $tag      Shortcode tag.
	 * @param array  $settings Already flattened and filtered settings.
	 * @param array  $explicit Widget's explicit mapping (canonical keys).
	 * @return array
	 */
	public static function to_canonical( string $tag, array $settings, array $explicit ): array {
		$schema = AllowlistRegistry::get_schema( $tag );
		$merged = array();
		$exact  = array();

		foreach ( $settings as $raw_key => $value ) {
			$raw_key = (string) $raw_key;
			if ( '' !== $raw_key && '_' === $raw_key[0] ) {
				// Elementor internals (_title, _transform_*) are never shortcode attributes.
				continue;
			}
			$canonical = KeyNormalizer::normalize( $raw_key, $schema );
			if ( $raw_key === $canonical ) {
				$merged[ $canonical ] = $value;
				$exact[ $canonical ]  = true;
			} elseif ( ! isset( $exact[ $canonical ] ) && ! array_key_exists( $canonical, $merged ) ) {
				$merged[ $canonical ] = $value;
			}
		}

		foreach ( $explicit as $key => $value ) {
			$merged[ KeyNormalizer::normalize( (string) $key, $schema ) ] = $value;
		}

		$atts               = CanonicalAttributeMapper::map( $tag, $merged );
		$atts['_canonical'] = true;

		return $atts;
	}
}
