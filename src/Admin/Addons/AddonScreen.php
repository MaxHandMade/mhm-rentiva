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

	/** Nonce action shared with the existing inline price editor. */
	public const NONCE_ACTION = 'mhmrentiva_addon_list_nonce';

	/**
	 * Register the screen's own hooks.
	 *
	 * The menu entry itself lives in Menu::add_menu() with every other one, so
	 * that the admin's shape is readable from a single file.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_mhmrentiva_addon_toggle_enabled', array( self::class, 'ajax_toggle_enabled' ) );
		add_action( 'wp_ajax_mhmrentiva_addon_quick_create', array( self::class, 'ajax_quick_create' ) );
	}

	/**
	 * AJAX boundary for the quick-create form. See ajax_toggle_enabled() for
	 * why the nonce is checked here as well as inside the pure method.
	 */
	public static function ajax_quick_create(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( self::create_failure( __( 'Security check failed.', 'mhm-rentiva' ) ) );
		}

		$result = self::quick_create( wp_unslash( $_POST ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}

	/**
	 * Create an add-on from the four fields the sidebar form collects.
	 *
	 * WHY `enabled` IS WRITTEN AND NOT LEFT TO DEFAULTS
	 * -------------------------------------------------
	 * Readers resolve the flag with `(bool) get_post_meta(...)`
	 * (AddonManager.php:356, AddonMeta.php:181), so an absent row reads as
	 * false. A service created here would be born switched off, and the screen
	 * would give the operator no hint why — it would simply show a service they
	 * had just created, inactive. The dev database already contains one add-on
	 * in that state, with no enabled meta at all.
	 *
	 * `required` is written for the opposite reason: false is the right default
	 * there, and writing it says so explicitly instead of relying on an absence
	 * that means the same thing today and might not tomorrow.
	 *
	 * The remaining fields — tax rate, tax inclusivity, confirmation
	 * requirement, calendar price display, category, context — stay with the
	 * full editor, which this screen links to per row. Two of those are not
	 * post meta at all but site-wide settings (AddonSettings), and writing them
	 * per post would silently override the operator's global choice.
	 *
	 * @param array<string,mixed> $request Unslashed request data.
	 * @return array{success:bool,addon_id:int,message:string}
	 */
	public static function quick_create( array $request ): array {
		$nonce = isset( $request['nonce'] ) ? sanitize_text_field( (string) $request['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return self::create_failure( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return self::create_failure( __( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$title = isset( $request['title'] ) ? sanitize_text_field( (string) $request['title'] ) : '';
		$title = trim( $title );

		if ( '' === $title ) {
			return self::create_failure( __( 'Please enter a service name.', 'mhm-rentiva' ) );
		}

		$price = isset( $request['price'] ) ? (float) $request['price'] : 0.0;

		if ( $price < 0 ) {
			return self::create_failure( __( 'Price cannot be negative.', 'mhm-rentiva' ) );
		}

		$description = isset( $request['description'] )
			? sanitize_textarea_field( (string) $request['description'] )
			: '';

		$pricing_type = AddonPricingType::sanitize(
			isset( $request['pricing_type'] ) ? (string) $request['pricing_type'] : ''
		);

		$addon_id = wp_insert_post(
			array(
				'post_type'    => AddonPostType::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_excerpt' => $description,
			),
			true
		);

		if ( is_wp_error( $addon_id ) || 0 === $addon_id ) {
			return self::create_failure( __( 'The additional service could not be created.', 'mhm-rentiva' ) );
		}

		$addon_id = (int) $addon_id;

		update_post_meta( $addon_id, 'mhmrentiva_addon_price', (string) $price );
		update_post_meta( $addon_id, '_mhmrentiva_addon_pricing_type', $pricing_type );
		update_post_meta( $addon_id, 'mhmrentiva_addon_enabled', '1' );
		update_post_meta( $addon_id, 'mhmrentiva_addon_required', '0' );

		return array(
			'success'  => true,
			'addon_id' => $addon_id,
			'message'  => __( 'Additional service created.', 'mhm-rentiva' ),
		);
	}

	/**
	 * @return array{success:bool,addon_id:int,message:string}
	 */
	private static function create_failure( string $message ): array {
		return array(
			'success'  => false,
			'addon_id' => 0,
			'message'  => $message,
		);
	}

	/**
	 * AJAX boundary for the row toggle.
	 *
	 * Kept to argument marshalling and the JSON reply. All decisions live in
	 * toggle_enabled(), which returns an array instead of calling
	 * wp_send_json_*: those emit and then `exit`, which PHPUnit cannot catch,
	 * so a handler that decides and replies in one method is only testable
	 * through the HTTP layer.
	 */
	public static function ajax_toggle_enabled(): void {
		// Checked here, at the boundary, before the superglobal is read at all.
		//
		// toggle_enabled() checks the nonce again, and that is deliberate rather
		// than redundant: it is a public method with its own contract, and its
		// tests drive it directly. What this line adds is that the raw $_POST
		// read below happens only after verification -- which is also the shape
		// the repo's shape-zero gate looks for. A `phpcs:ignore` would have
		// silenced the sniff while leaving the unguarded read in the tree; the
		// gate is right to not count suppressions.
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( self::failure( __( 'Security check failed.', 'mhm-rentiva' ) ) );
		}

		$result = self::toggle_enabled( wp_unslash( $_POST ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}

	/**
	 * Flip one add-on's enabled flag.
	 *
	 * THREE GUARDS, AND WHY THE THIRD ONE MATTERS MOST
	 * ------------------------------------------------
	 * Nonce, capability, and then: is this ID actually an add-on? The third is
	 * the one AddonManager::handle_bulk_actions shipped without, which let it
	 * wp_delete_post() any post on the site — pages included — until T8. It is
	 * copied here from handle_update_price(), the endpoint in this feature that
	 * already had all three.
	 *
	 * WHY THIS DOES NOT QUERY THE ADD-ON LIST
	 * ---------------------------------------
	 * It writes one row and reports that row back. It deliberately does not run
	 * WP_Query/get_posts to return a refreshed list: the paid add-on hooks
	 * pre_get_posts (AddonContextCatalog::scope_rental_queries) and its guard is
	 * `is_admin() && ! wp_doing_ajax()`, so on admin-ajax requests the scope DOES
	 * apply and a "Transfer only" service would silently drop out of the reply.
	 * get_post() below is a single-row read and never reaches that action.
	 *
	 * @param array<string,mixed> $request Unslashed request data.
	 * @return array{success:bool,enabled:bool,message:string}
	 */
	public static function toggle_enabled( array $request ): array {
		$nonce = isset( $request['nonce'] ) ? sanitize_text_field( (string) $request['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return self::failure( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return self::failure( __( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$addon_id = isset( $request['addon_id'] ) ? absint( $request['addon_id'] ) : 0;
		$addon    = $addon_id > 0 ? get_post( $addon_id ) : null;

		if ( ! $addon || AddonPostType::POST_TYPE !== $addon->post_type ) {
			return self::failure( __( 'Additional service not found.', 'mhm-rentiva' ) );
		}

		// Only the literal '1' turns a service on. `! empty()` would be looser
		// in a way that matters here: it treats any non-empty string as true,
		// so a stray 'false' or 'off' from a future caller would ACTIVATE the
		// service rather than deactivate it. An allow-list of one has no such
		// edge.
		$enabled = '1' === (string) ( $request['enabled'] ?? '' );

		update_post_meta( $addon_id, 'mhmrentiva_addon_enabled', $enabled ? '1' : '0' );

		return array(
			'success' => true,
			'enabled' => $enabled,
			'message' => $enabled
				? __( 'Service activated.', 'mhm-rentiva' )
				: __( 'Service deactivated.', 'mhm-rentiva' ),
		);
	}

	/**
	 * @return array{success:bool,enabled:bool,message:string}
	 */
	private static function failure( string $message ): array {
		return array(
			'success' => false,
			'enabled' => false,
			'message' => $message,
		);
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
