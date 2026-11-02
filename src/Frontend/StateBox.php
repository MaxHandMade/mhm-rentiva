<?php

declare(strict_types=1);

namespace MHMRentiva\Frontend;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The customer-facing empty and error box (drawing Bos-Hata): a kicker, a
 * headline, one honest line and, when there is somewhere to go, one action.
 *
 * It reuses `rvs-empty` and adds only the left-aligned layout (`rvs-state`);
 * the `alert` tone is the same box in the alert colours. Used by every
 * server-rendered "nothing here" / "could not load" surface so they read the
 * same: the vehicle page with no vehicle, a shortcode that failed, a search
 * that found nothing, the featured strip with nothing to feature, a missing
 * booking. JS-drawn states (the results error) build the same markup by hand.
 *
 * Standalone safe: when no flagged shortcode is rendering (the box is not
 * already inside `.rvs-shell`) it opens its own shell and loads the styles.
 */
final class StateBox {

	/**
	 * @param array{kicker?:string,title:string,text?:string,action_label?:string,action_url?:string,action_style?:string,tone?:string,detail?:string,state?:string} $args
	 *   kicker       Small label above the headline (optional).
	 *   title        Headline.
	 *   text         One honest line under it.
	 *   action_label Button text; with action_url, the one action.
	 *   action_url   Where the action goes.
	 *   action_style 'primary' or 'secondary' (default secondary).
	 *   tone         'empty' (default) or 'alert'.
	 *   detail       Extra line for administrators; the caller gates it.
	 *   state        A slug for `data-rvs-empty`, so a caller can test for the state.
	 */
	public static function html( array $args ): string {
		$tone  = 'alert' === ( $args['tone'] ?? '' ) ? 'alert' : 'empty';
		$state = isset( $args['state'] ) ? sanitize_key( (string) $args['state'] ) : '';

		$classes = 'rvs-empty rvs-state' . ( 'alert' === $tone ? ' rvs-state--alert' : '' );
		$attrs   = ( '' !== $state ? ' data-rvs-empty="' . esc_attr( $state ) . '"' : '' );

		$out = '<div class="' . esc_attr( $classes ) . '"' . $attrs . '>';
		if ( ! empty( $args['kicker'] ) ) {
			$out .= '<span class="rvs-kicker">' . esc_html( (string) $args['kicker'] ) . '</span>';
		}
		$out .= '<h3>' . esc_html( (string) $args['title'] ) . '</h3>';
		if ( ! empty( $args['text'] ) ) {
			$out .= '<p>' . esc_html( (string) $args['text'] ) . '</p>';
		}
		if ( ! empty( $args['detail'] ) ) {
			$out .= '<p class="rvs-state__detail">' . esc_html( (string) $args['detail'] ) . '</p>';
		}
		if ( ! empty( $args['action_label'] ) && ! empty( $args['action_url'] ) ) {
			$style = 'primary' === ( $args['action_style'] ?? '' ) ? 'primary' : 'secondary';
			$out  .= '<a class="rvs-btn rvs-btn--' . $style . '" href="' . esc_url( (string) $args['action_url'] ) . '">'
				. esc_html( (string) $args['action_label'] ) . '</a>';
		}
		$out .= '</div>';

		if ( ! AbstractShortcode::in_rvs_shell() ) {
			AssetManager::enqueue_rvs_card_styles();
			AssetManager::enqueue_rvs_styles();
			$out = '<div class="rvs-shell">' . $out . '</div>';
		}

		return $out;
	}

	/**
	 * Where "Browse cars" goes: the search results page, then the vehicle list,
	 * then the vehicle archive, then the home page. Never empty.
	 */
	public static function browse_url(): string {
		foreach ( array( 'rentiva_search_results', 'rentiva_vehicles_list' ) as $tag ) {
			$url = ShortcodeUrlManager::get_page_url( $tag );
			if ( '' !== $url ) {
				return $url;
			}
		}

		$archive = get_post_type_archive_link( 'mhmrentiva_vehicle' );

		return is_string( $archive ) && '' !== $archive ? $archive : home_url( '/' );
	}
}
