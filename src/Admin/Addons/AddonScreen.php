<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Settings\Groups\AddonSettings;

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
		add_action( 'wp_ajax_mhmrentiva_addon_reorder', array( self::class, 'ajax_reorder' ) );
	}

	/**
	 * AJAX boundary for drag-to-reorder. See ajax_toggle_enabled() for why the
	 * nonce is checked here as well as inside the pure method.
	 */
	public static function ajax_reorder(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( self::reorder_failure( __( 'Security check failed.', 'mhm-rentiva' ) ) );
		}

		$result = self::reorder( wp_unslash( $_POST ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}

	/**
	 * Persist a new display order.
	 *
	 * ALL OR NOTHING, AND WHY
	 * -----------------------
	 * Every ID is validated before anything is written. One that is missing or
	 * is not an add-on refuses the whole batch rather than being skipped.
	 *
	 * Skipping would leave the order half-applied against a list the operator
	 * is looking at, so the screen and the database would disagree and the only
	 * repair would be to drag everything again. It is also the shape that hides
	 * a foreign write: one stray ID inside an otherwise valid batch would be
	 * written to while the response still read as success. Refusing is loud and
	 * leaves the previous order intact.
	 *
	 * Position is WordPress's own `menu_order` — supported by this post type
	 * through `page-attributes`. It is NOT `mhmrentiva_addon_display_order`,
	 * which despite the name is a site-wide setting naming the sort criterion.
	 *
	 * @param array<string,mixed> $request Unslashed request data.
	 * @return array{success:bool,ordered:int,message:string}
	 */
	public static function reorder( array $request ): array {
		$nonce = isset( $request['nonce'] ) ? sanitize_text_field( (string) $request['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return self::reorder_failure( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return self::reorder_failure( __( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$raw_order = isset( $request['order'] ) && is_array( $request['order'] ) ? $request['order'] : array();

		if ( array() === $raw_order ) {
			return self::reorder_failure( __( 'No order was supplied.', 'mhm-rentiva' ) );
		}

		// Validate the whole list first. Nothing is written until every ID has
		// been proven to be an add-on that exists.
		$ids = array();
		foreach ( $raw_order as $raw_id ) {
			$addon_id = absint( $raw_id );
			$addon    = $addon_id > 0 ? get_post( $addon_id ) : null;

			if ( ! $addon || AddonPostType::POST_TYPE !== $addon->post_type ) {
				return self::reorder_failure( __( 'The order contains an item that is not an additional service.', 'mhm-rentiva' ) );
			}

			$ids[] = $addon_id;
		}

		foreach ( $ids as $position => $addon_id ) {
			wp_update_post(
				array(
					'ID'         => $addon_id,
					'menu_order' => $position,
				)
			);
		}

		return array(
			'success' => true,
			'ordered' => count( $ids ),
			'message' => __( 'Order saved.', 'mhm-rentiva' ),
		);
	}

	/**
	 * @return array{success:bool,ordered:int,message:string}
	 */
	private static function reorder_failure( string $message ): array {
		return array(
			'success' => false,
			'ordered' => 0,
			'message' => $message,
		);
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
	 * The eight-colour row palette from the design. Indexed by position so a
	 * given row keeps its colour across renders.
	 *
	 * @var array<int, array{0:string,1:string}>
	 */
	private const AVATAR_PALETTE = array(
		array( '#e5f0fb', '#135e96' ),
		array( '#e4f6e9', '#0a6b1e' ),
		array( '#fdf0e4', '#a15b1e' ),
		array( '#f0e9fb', '#5b3a9e' ),
		array( '#fbe9f1', '#9e2b63' ),
		array( '#e9f6f6', '#0f6b6b' ),
		array( '#eef2f7', '#41505f' ),
		array( '#fcf3d6', '#8a6d1b' ),
	);

	/**
	 * Render the screen.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'mhm-rentiva' ) );
		}

		$addons = self::get_addons();
		$active = 0;
		foreach ( $addons as $addon ) {
			if ( '1' === (string) get_post_meta( $addon->ID, 'mhmrentiva_addon_enabled', true ) ) {
				++$active;
			}
		}

		echo '<div class="wrap" id="mhm-addons-root">';
		echo '<h1 class="rv-addon-title">' . esc_html__( 'Additional Services', 'mhm-rentiva' ) . '</h1>';

		echo '<div class="rv-addon-layout">';
		echo '<div class="rv-addon-list-card">';

		echo '<div class="rv-addon-list-head">';
		echo '<span class="rv-addon-list-title">' . esc_html__( 'Defined services', 'mhm-rentiva' ) . '</span>';
		printf(
			'<span class="rv-addon-count">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: number of active services, 2: total number of services. */
					__( '%1$d active · %2$d total', 'mhm-rentiva' ),
					$active,
					count( $addons )
				)
			)
		);
		echo '</div>';

		if ( array() === $addons ) {
			echo '<p class="rv-addon-empty">' . esc_html__( 'No additional services yet.', 'mhm-rentiva' ) . '</p>';
		}

		foreach ( $addons as $index => $addon ) {
			self::render_row( $addon, $index );
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * The add-ons, in the order the site is configured to show them.
	 *
	 * Safe to use WP_Query here, unlike in the ajax endpoints: the paid add-on
	 * scopes add-on queries from `pre_get_posts` but returns early when
	 * `is_admin() && ! wp_doing_ajax()`, which is exactly this request.
	 *
	 * @return \WP_Post[]
	 */
	private static function get_addons(): array {
		$args = array(
			'post_type'      => AddonPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		switch ( AddonSettings::get_display_order() ) {
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'price_asc':
			case 'price_desc':
				$args['meta_key'] = 'mhmrentiva_addon_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering by the price the operator chose to sort on.
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'price_asc' === AddonSettings::get_display_order() ? 'ASC' : 'DESC';
				break;
			case 'date_created':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			default:
				$args['orderby'] = 'menu_order';
				$args['order']   = 'ASC';
				break;
		}

		return get_posts( $args );
	}

	/**
	 * Is the list currently sorted by the position the drag handle writes?
	 *
	 * When it is not, the handle is not rendered. Offering it anyway would let
	 * the operator drag a row, have menu_order written exactly as asked, and
	 * watch the list come back in title order — work accepted and discarded,
	 * with nothing reporting an error.
	 */
	private static function is_manually_sorted(): bool {
		return 'menu_order' === AddonSettings::get_display_order();
	}

	/**
	 * One row.
	 */
	private static function render_row( \WP_Post $addon, int $index ): void {
		$enabled  = '1' === (string) get_post_meta( $addon->ID, 'mhmrentiva_addon_enabled', true );
		$price    = (float) get_post_meta( $addon->ID, 'mhmrentiva_addon_price', true );
		$type     = AddonPricingType::sanitize( get_post_meta( $addon->ID, '_mhmrentiva_addon_pricing_type', true ) );
		$palette  = self::AVATAR_PALETTE[ $index % count( self::AVATAR_PALETTE ) ];
		$initial  = mb_strtoupper( mb_substr( $addon->post_title, 0, 1 ) );
		$excerpt  = $addon->post_excerpt;
		$edit_url = get_edit_post_link( $addon->ID, 'url' );

		printf(
			'<div class="rv-addon-row%1$s" data-addon-id="%2$d">',
			$enabled ? '' : ' rv-addon-row--off',
			(int) $addon->ID
		);

		if ( self::is_manually_sorted() ) {
			printf(
				'<span class="rv-addon-drag" aria-label="%s">&#8942;&#8942;</span>',
				esc_attr__( 'Drag to reorder', 'mhm-rentiva' )
			);
		}

		printf(
			'<span class="rv-addon-avatar" style="background:%1$s;color:%2$s" aria-hidden="true">%3$s</span>',
			esc_attr( $palette[0] ),
			esc_attr( $palette[1] ),
			esc_html( $initial )
		);

		echo '<span class="rv-addon-identity">';
		echo '<span class="rv-addon-name">' . esc_html( $addon->post_title ) . '</span>';
		if ( '' !== $excerpt ) {
			echo '<span class="rv-addon-desc">' . esc_html( $excerpt ) . '</span>';
		}
		echo '</span>';

		echo '<span class="rv-addon-price">';
		echo '<span class="rv-addon-amount">' . esc_html( CurrencyHelper::format_price( $price, 2 ) ) . '</span>';
		echo '<span class="rv-addon-type">' . esc_html( AddonPricingType::label( $type ) ) . '</span>';
		echo '</span>';

		printf(
			'<button type="button" class="rv-addon-status%1$s" data-enabled="%2$s">%3$s</button>',
			$enabled ? ' is-on' : '',
			$enabled ? '1' : '0',
			esc_html( $enabled ? __( 'Active', 'mhm-rentiva' ) : __( 'Inactive', 'mhm-rentiva' ) )
		);

		if ( is_string( $edit_url ) && '' !== $edit_url ) {
			printf(
				'<a class="rv-addon-edit" href="%1$s">%2$s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'mhm-rentiva' )
			);
		}

		echo '</div>';
	}
}
