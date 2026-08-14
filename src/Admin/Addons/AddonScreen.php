<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The add-ons admin screen.
 *
 * WHY THIS IS NOT THE NATIVE LIST TABLE
 * -------------------------------------
 * Bookings and Vehicles kept WordPress's `edit.php` because those screens grow
 * to hundreds of rows, where core's paging, search and Screen Options earn
 * their keep. Add-ons do not grow: a rental firm defines somewhere between 8
 * and 20 services and then edits them rarely. At that size paging and search
 * return nothing for the space they occupy, while the two things the design
 * asks for -- an inline create form beside the list, and a status toggle on
 * each row -- are exactly what `edit.php` cannot host.
 *
 * So the rule this screen follows, and the one the next screen should be
 * measured against, is about size and not about taste: a list that grows keeps
 * the native table; a list that is small and stable may have its own page.
 *
 * The native screen is NOT removed. `edit.php?post_type=mhmrentiva_addon` stays
 * reachable by URL, and `post-new.php` / `post.php` keep serving the full
 * editor -- this screen links to the latter for the seven fields its own quick
 * form does not write. AddonsScreenMenuTest holds both halves in place.
 */
final class AddonScreen {

	/** Submenu slug. Also the `page` query arg the screen answers on. */
	public const SLUG = 'mhm-rentiva-addons';

	/**
	 * Register the screen's own hooks.
	 *
	 * The menu entry itself lives in Menu::add_menu() with every other one, so
	 * that the admin's shape is readable from a single file.
	 */
	public static function register(): void {
		// Endpoints and assets land here in the tasks that follow.
	}

	/**
	 * Render the screen.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'mhm-rentiva' ) );
		}

		echo '<div class="wrap" id="mhm-addons-root">';
		echo '<h1>' . esc_html__( 'Additional Services', 'mhm-rentiva' ) . '</h1>';
		echo '</div>';
	}
}
