<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Core\AssetManager;
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
		add_action( 'wp_ajax_mhmrentiva_addon_delete', array( self::class, 'ajax_delete_addon' ) );

		// The endpoints above flush directly. These cover every other way the
		// figures can change: the full editor, the native screen's bulk actions
		// and inline price editor, deletion, and anything else that saves an
		// add-on. Meta written straight through update_post_meta() does not
		// fire save_post, which is why the endpoints do not rely on this.
		add_action( 'save_post_' . AddonPostType::POST_TYPE, array( AddonStats::class, 'flush' ) );
		add_action( 'deleted_post', array( AddonStats::class, 'flush' ) );
		add_action( 'updated_post_meta', array( self::class, 'flush_stats_on_addon_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( self::class, 'flush_stats_on_addon_meta' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Load this screen's script, and only on this screen.
	 *
	 * Versioned with get_file_version() (filemtime) rather than the plugin
	 * version: during development the plugin version does not move between
	 * edits, so a browser holds the previous file and the change appears not to
	 * have happened.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'mhm-rentiva-addons-screen',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/addons-screen.css',
			array(),
			AssetManager::get_file_version( 'assets/css/admin/addons-screen.css' )
		);

		wp_enqueue_script(
			'mhm-rentiva-addons-screen',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/addons-screen.js',
			array(),
			AssetManager::get_file_version( 'assets/js/admin/addons-screen.js' ),
			true
		);

		wp_localize_script(
			'mhm-rentiva-addons-screen',
			'mhmRentivaAddonsScreen',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => array(
					'active'            => __( 'Active', 'mhm-rentiva' ),
					'inactive'          => __( 'Inactive', 'mhm-rentiva' ),
					'saving'            => __( 'Saving…', 'mhm-rentiva' ),
					'nameRequired'      => __( 'Please enter a service name.', 'mhm-rentiva' ),
					'genericError'      => __( 'An error occurred. Please try again.', 'mhm-rentiva' ),
					// The two counter formats the script rewrites after a
					// toggle. Same strings the server renders -- both come from
					// count_label()/active_share_label(), so there is one
					// translation and not two that can drift apart.
					/* translators: 1: number of active services, 2: total number of services. */
					'countLabel'        => __( '%1$d active · %2$d total', 'mhm-rentiva' ),
					/* translators: %s: share of services that are active, as a percentage. */
					'activeShare'       => __( '%s%% active', 'mhm-rentiva' ),
					/* translators: %s: name of the additional service. */
					'confirmDelete'     => __( 'Move “%s” to the trash?', 'mhm-rentiva' ),
					/* translators: %d: number of selected services. */
					'selectedCount'     => __( '%d selected', 'mhm-rentiva' ),
					/* translators: %d: number of selected services. */
					'confirmBulkDelete' => __( 'Move %d selected services to the trash?', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * Flush the KPI cache when an add-on's price or enabled flag is written by
	 * any path, including the native screen's inline price editor and its bulk
	 * enable/disable actions.
	 *
	 * @param int    $meta_id  Unused.
	 * @param int    $post_id  Post the meta belongs to.
	 * @param string $meta_key Meta key written.
	 */
	public static function flush_stats_on_addon_meta( $meta_id, $post_id, $meta_key ): void {
		if ( 'mhmrentiva_addon_price' !== $meta_key && 'mhmrentiva_addon_enabled' !== $meta_key ) {
			return;
		}

		if ( AddonPostType::POST_TYPE !== get_post_type( (int) $post_id ) ) {
			return;
		}

		AddonStats::flush();
	}

	/**
	 * AJAX boundary for deletion. See ajax_toggle_enabled() for why the nonce is
	 * checked here as well as inside the pure method.
	 */
	public static function ajax_delete_addon(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( self::delete_failure( __( 'Security check failed.', 'mhm-rentiva' ) ) );
		}

		$result = self::delete_addon( wp_unslash( $_POST ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}

		wp_send_json_error( $result );
	}

	/**
	 * Move one add-on to the trash.
	 *
	 * TRASH, NOT PERMANENT DELETE
	 * ---------------------------
	 * The native screen this replaced deletes through WordPress's row action,
	 * which trashes: the row can be restored, and bookings that reference the
	 * service keep a resolvable post ID. Forcing a permanent delete for the same
	 * click would make the new screen more destructive than the old one, which
	 * is a regression wearing a feature's clothes.
	 *
	 * The guards are the ones AddonManager::handle_bulk_actions was missing when
	 * it called wp_delete_post( $id, true ) on any post id it was handed.
	 *
	 * @param array<string,mixed> $request Unslashed request data.
	 * @return array{success:bool,addon_id:int,message:string}
	 */
	public static function delete_addon( array $request ): array {
		$nonce = isset( $request['nonce'] ) ? sanitize_text_field( (string) $request['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return self::delete_failure( __( 'Security check failed.', 'mhm-rentiva' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return self::delete_failure( __( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$addon_id = isset( $request['addon_id'] ) ? absint( $request['addon_id'] ) : 0;
		$addon    = $addon_id > 0 ? get_post( $addon_id ) : null;

		if ( ! $addon || AddonPostType::POST_TYPE !== $addon->post_type ) {
			return self::delete_failure( __( 'Additional service not found.', 'mhm-rentiva' ) );
		}

		if ( ! wp_trash_post( $addon_id ) ) {
			return self::delete_failure( __( 'The additional service could not be deleted.', 'mhm-rentiva' ) );
		}

		AddonStats::flush();

		return array(
			'success'  => true,
			'addon_id' => $addon_id,
			'message'  => __( 'Additional service moved to the trash.', 'mhm-rentiva' ),
		);
	}

	/**
	 * @return array{success:bool,addon_id:int,message:string}
	 */
	private static function delete_failure( string $message ): array {
		return array(
			'success'  => false,
			'addon_id' => 0,
			'message'  => $message,
		);
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

		// Absent means active. The form sends the switch explicitly, but a
		// caller that omits it must still get a usable service: an absent flag
		// reads as false everywhere else, and a service born switched off with
		// no explanation is the silent defect this endpoint exists to avoid.
		$enabled  = ! isset( $request['enabled'] ) || '1' === (string) $request['enabled'];
		$required = isset( $request['required'] ) && '1' === (string) $request['required'];

		update_post_meta( $addon_id, 'mhmrentiva_addon_price', (string) $price );
		update_post_meta( $addon_id, '_mhmrentiva_addon_pricing_type', $pricing_type );
		update_post_meta( $addon_id, 'mhmrentiva_addon_enabled', $enabled ? '1' : '0' );
		update_post_meta( $addon_id, 'mhmrentiva_addon_required', $required ? '1' : '0' );
		AddonStats::flush();

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
		AddonStats::flush();

		return array(
			'success' => true,
			'enabled' => $enabled,
			'message' => $enabled
				? __( 'Service activated.', 'mhm-rentiva' )
				: __( 'Service deactivated.', 'mhm-rentiva' ),
			// Read AFTER the flush, so these are the figures the toggle just
			// produced rather than the ones it invalidated. The screen used to
			// keep whatever it was rendered with, so switching a service on
			// left three rows reading Aktif above a header still reading
			// "2 aktif · 3 toplam" -- the counter being the one thing an
			// operator would trust over counting the rows.
			'stats'   => AddonStats::get(),
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

		self::render_stats_band();

		echo '<div class="rv-addon-layout">';

		self::render_create_form();

		echo '<div class="rv-addon-list-card">';

		echo '<div class="rv-addon-list-head">';
		echo '<span class="rv-addon-list-title">' . esc_html__( 'Defined services', 'mhm-rentiva' ) . '</span>';
		printf(
			'<span class="rv-addon-count">%s</span>',
			esc_html( self::count_label( $active, count( $addons ) ) )
		);
		echo '</div>';

		if ( array() === $addons ) {
			echo '<p class="rv-addon-empty">' . esc_html__( 'No additional services yet.', 'mhm-rentiva' ) . '</p>';
		} else {
			self::render_bulk_bar();
		}

		$usage = self::get_usage_counts();

		foreach ( $addons as $index => $addon ) {
			self::render_row( $addon, $index, (int) ( $usage[ $addon->ID ] ?? 0 ) );
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * The create form beside the list.
	 *
	 * Four fields, matching the design. The other seven an add-on carries stay
	 * with the full editor each row links to — this is a quick-create, not a
	 * second editor, and duplicating the editor here would give the same field
	 * two homes free to disagree.
	 *
	 * The pricing select offers all three types the plugin actually supports.
	 * The design draws two ("Günlük" and "Tek seferlik"); per-passenger exists
	 * in AddonPricingType and is used by transfers, and leaving it out of the
	 * form would make it uncreatable from the screen that replaced the list.
	 *
	 * Not a `<form>`: the submit is an ajax call, and a real form here would sit
	 * inside no action and offer the browser a default submit that reloads the
	 * page with nothing saved.
	 */
	private static function render_create_form(): void {
		echo '<div class="rv-addon-create-card">';
		echo '<h2 class="rv-addon-create-title">' . esc_html__( 'New Additional Service', 'mhm-rentiva' ) . '</h2>';
		echo '<p class="rv-addon-create-intro">' . esc_html__( 'Add a service the customer can choose during booking.', 'mhm-rentiva' ) . '</p>';

		printf(
			'<p class="rv-addon-field"><label for="rv-addon-name">%1$s</label>' .
			'<input type="text" id="rv-addon-name" class="rv-addon-input" placeholder="%2$s"></p>',
			esc_html__( 'Service name', 'mhm-rentiva' ),
			esc_attr__( 'e.g. Roadside Assistance', 'mhm-rentiva' )
		);

		printf(
			'<p class="rv-addon-field"><label for="rv-addon-desc">%1$s</label>' .
			'<textarea id="rv-addon-desc" class="rv-addon-input" rows="3" placeholder="%2$s"></textarea></p>',
			esc_html__( 'Description', 'mhm-rentiva' ),
			esc_attr__( 'Short description', 'mhm-rentiva' )
		);

		echo '<div class="rv-addon-field-row">';

		printf(
			'<p class="rv-addon-field"><label for="rv-addon-price">%1$s</label>' .
			'<input type="number" id="rv-addon-price" class="rv-addon-input" min="0" step="0.01" inputmode="decimal"></p>',
			esc_html__( 'Price', 'mhm-rentiva' )
		);

		echo '<p class="rv-addon-field"><label for="rv-addon-type">' . esc_html__( 'Type', 'mhm-rentiva' ) . '</label>';
		echo '<select id="rv-addon-type" class="rv-addon-input">';
		foreach ( AddonPricingType::all() as $type ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $type ),
				selected( $type, AddonPricingType::PER_DAY, false ),
				esc_html( AddonPricingType::label( $type ) )
			);
		}
		echo '</select></p>';

		echo '</div>';

		// Both switches live here rather than only in the full editor. They are
		// single checkboxes, and leaving them out meant a service created from
		// this form had to be opened in the editor to be switched off or made
		// mandatory -- the exact trip the form exists to save. The complicated
		// fields (tax, category, context) still belong to the editor.
		printf(
			'<p class="rv-addon-switch"><label><input type="checkbox" id="rv-addon-enabled" checked> %s</label></p>',
			esc_html__( 'Enable this additional service', 'mhm-rentiva' )
		);

		printf(
			'<p class="rv-addon-switch"><label><input type="checkbox" id="rv-addon-required"> %s</label></p>',
			esc_html__( 'This additional service is required', 'mhm-rentiva' )
		);

		printf(
			'<button type="button" class="button button-primary rv-addon-create">%s</button>',
			esc_html__( 'Add Service', 'mhm-rentiva' )
		);

		echo '<p class="rv-addon-create-feedback" role="status" aria-live="polite"></p>';
		echo '</div>';
	}

	/**
	 * Bulk actions.
	 *
	 * The native screen offered enable, disable and delete across a selection,
	 * and a replacement without them takes that away for anyone maintaining a
	 * longer list -- switching a season's worth of services off one row at a
	 * time is the case this exists for.
	 *
	 * The bar is inert until something is selected, so it never invites a click
	 * that would do nothing.
	 */
	private static function render_bulk_bar(): void {
		echo '<div class="rv-addon-bulk">';

		printf(
			'<label class="rv-addon-bulk-all"><input type="checkbox" class="rv-addon-select-all"> %s</label>',
			esc_html__( 'Select all', 'mhm-rentiva' )
		);

		echo '<span class="rv-addon-bulk-actions">';

		printf(
			'<button type="button" class="button rv-addon-bulk-action" data-bulk="enable" disabled>%s</button>',
			esc_html__( 'Activate', 'mhm-rentiva' )
		);

		printf(
			'<button type="button" class="button rv-addon-bulk-action" data-bulk="disable" disabled>%s</button>',
			esc_html__( 'Deactivate', 'mhm-rentiva' )
		);

		printf(
			'<button type="button" class="button rv-addon-bulk-action rv-addon-bulk-delete" data-bulk="delete" disabled>%s</button>',
			esc_html__( 'Delete', 'mhm-rentiva' )
		);

		echo '</span>';
		echo '<span class="rv-addon-bulk-count" role="status" aria-live="polite"></span>';
		echo '</div>';
	}

	/**
	 * The KPI band.
	 *
	 * The design does not draw one — it opens straight into the two columns —
	 * but the band stays, by decision: it is the only place four of these
	 * numbers are shown at all, and average price and total value appear on no
	 * other screen. Dropping them to match a mockup would be removing working
	 * information to gain a resemblance.
	 *
	 * It reuses the shared `mhm-stat-card` markup rather than inventing a second
	 * card system for this screen; Task 10 restyles it inside
	 * `#mhm-addons-root`, which is what keeps the change local.
	 */
	/**
	 * "N active · M total", the list header's counter.
	 *
	 * One place, because two now need it: this screen renders it, and the
	 * script rewrites it after a toggle. A second copy of the format string
	 * would be a second thing to translate and a second thing to forget.
	 *
	 * @param int $active Active services.
	 * @param int $total  All services.
	 * @return string
	 */
	private static function count_label( int $active, int $total ): string {
		return sprintf(
			/* translators: 1: number of active services, 2: total number of services. */
			__( '%1$d active · %2$d total', 'mhm-rentiva' ),
			$active,
			$total
		);
	}

	/**
	 * "N% active", the sub-line under the Active Services figure.
	 *
	 * Same reason as count_label(): rendered here, rewritten by the script.
	 *
	 * @param string $percentage Share of services that are active.
	 * @return string
	 */
	private static function active_share_label( string $percentage ): string {
		return sprintf(
			/* translators: %s: share of services that are active, as a percentage. */
			__( '%s%% active', 'mhm-rentiva' ),
			$percentage
		);
	}

	private static function render_stats_band(): void {
		$stats = AddonStats::get();

		// Each card carries the AddonStats key it displays. Without it every card
		// is an identical .mhm-stat-card and the script has no way to say "the
		// active-services one" except by counting position, which the next
		// person to reorder them would break silently.
		$cards = array(
			array(
				'key'   => 'total_addons',
				'icon'  => 'dashicons-plus-alt',
				'label' => __( 'Total Additional Services', 'mhm-rentiva' ),
				'value' => (string) $stats['total_addons'],
				'sub'   => __( 'All services', 'mhm-rentiva' ),
			),
			array(
				'key'   => 'active_addons',
				'icon'  => 'dashicons-yes-alt',
				'label' => __( 'Active Services', 'mhm-rentiva' ),
				'value' => (string) $stats['active_addons'],
				'sub'   => self::active_share_label( (string) $stats['active_percentage'] ),
			),
			array(
				'key'   => 'avg_price',
				'icon'  => 'dashicons-money-alt',
				'label' => __( 'Average Price', 'mhm-rentiva' ),
				'value' => $stats['avg_price'],
				'sub'   => __( 'All services', 'mhm-rentiva' ),
			),
			array(
				'key'   => 'total_value',
				'icon'  => 'dashicons-chart-line',
				'label' => __( 'Total Value', 'mhm-rentiva' ),
				'value' => $stats['total_value'],
				'sub'   => __( 'All prices', 'mhm-rentiva' ),
			),
		);

		echo '<div class="mhm-stats-grid">';
		foreach ( $cards as $card ) {
			printf(
				'<div class="mhm-stat-card" data-stat="%1$s"><span class="dashicons %2$s"></span><div class="mhm-stat-card__body">' .
				'<p class="mhm-stat-card__label">%3$s</p><p class="mhm-stat-card__value">%4$s</p>' .
				'<p class="mhm-stat-card__sub">%5$s</p></div></div>',
				esc_attr( $card['key'] ),
				esc_attr( $card['icon'] ),
				esc_html( $card['label'] ),
				esc_html( $card['value'] ),
				esc_html( $card['sub'] )
			);
		}
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
	/**
	 * How many countable bookings each add-on appears on.
	 *
	 * One query for the whole screen, not one per row -- the report returns the
	 * full breakdown keyed by add-on id, so a per-row call would be the same
	 * work repeated once per service.
	 *
	 * Cached alongside the KPI figures and invalidated with them. The window is
	 * deliberately wide rather than "this month": the number answers "is this
	 * service worth keeping", which is a question about its whole life.
	 *
	 * @return array<int,int> Addon ID => booking count.
	 */
	private static function get_usage_counts(): array {
		$cached = wp_cache_get( 'mhmrentiva_addon_usage', 'mhmrentiva' );
		if ( is_array( $cached ) ) {
			/** @var array<int,int> $cached */
			return $cached;
		}

		$report = \MHMRentiva\Admin\Booking\Addons\AddonBooking::get_addon_revenue_report(
			new \DateTime( '@0' ),
			new \DateTime( '+1 day' )
		);

		$counts = array();
		foreach ( (array) ( $report['addon_stats'] ?? array() ) as $addon_id => $stats ) {
			$counts[ (int) $addon_id ] = (int) ( $stats['count'] ?? 0 );
		}

		wp_cache_set( 'mhmrentiva_addon_usage', $counts, 'mhmrentiva' );

		return $counts;
	}

	private static function render_row( \WP_Post $addon, int $index, int $usage = 0 ): void {
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
			'<input type="checkbox" class="rv-addon-select" aria-label="%s">',
			/* translators: %s: name of the additional service. */
			esc_attr( sprintf( __( 'Select %s', 'mhm-rentiva' ), $addon->post_title ) )
		);

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
		// Editable in place, the way the native screen's price column was. The
		// raw value rides in a data attribute so the editor reads the number
		// rather than scraping the formatted string, which carries a currency
		// symbol and locale separators.
		printf(
			'<button type="button" class="rv-addon-amount rv-addon-price-value" data-price="%1$s" title="%2$s">%3$s</button>',
			esc_attr( (string) $price ),
			esc_attr__( 'Click to edit the price', 'mhm-rentiva' ),
			esc_html( CurrencyHelper::format_price( $price, 2 ) )
		);
		echo '<span class="rv-addon-type">' . esc_html( AddonPricingType::label( $type ) ) . '</span>';
		echo '</span>';

		echo '<span class="rv-addon-usage">';
		echo '<span class="rv-addon-usage-count">' . esc_html( number_format_i18n( $usage ) ) . '</span>';
		echo '<span class="rv-addon-usage-label">' . esc_html(
			/* translators: label under the number of bookings an add-on appears on. */
			_n( 'booking', 'bookings', $usage, 'mhm-rentiva' )
		) . '</span>';
		echo '</span>';

		// aria-pressed is what makes this a toggle button rather than a button
		// that happens to change its own label. Without it, assistive
		// technology announces an activation and no state; with it, the state
		// is part of the control and the change is announced when it happens.
		// The script keeps it in step with the label, revert included.
		printf(
			'<button type="button" class="rv-addon-status%1$s" data-enabled="%2$s" aria-pressed="%3$s">%4$s</button>',
			$enabled ? ' is-on' : '',
			$enabled ? '1' : '0',
			$enabled ? 'true' : 'false',
			esc_html( $enabled ? __( 'Active', 'mhm-rentiva' ) : __( 'Inactive', 'mhm-rentiva' ) )
		);

		echo '<span class="rv-addon-actions">';

		if ( is_string( $edit_url ) && '' !== $edit_url ) {
			printf(
				'<a class="rv-addon-edit" href="%1$s">%2$s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'mhm-rentiva' )
			);
		}

		// Deleting was only possible on the native screen until now, so a list
		// that replaced it without this was quietly taking a capability away.
		printf(
			'<button type="button" class="rv-addon-delete" aria-label="%1$s">%2$s</button>',
			/* translators: %s: name of the additional service. */
			esc_attr( sprintf( __( 'Delete %s', 'mhm-rentiva' ), $addon->post_title ) ),
			esc_html__( 'Delete', 'mhm-rentiva' )
		);

		echo '</span>';

		echo '</div>';
	}
}
