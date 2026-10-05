<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicle Gallery Meta Box
 *
 * Manages vehicle images with WordPress Media Library integration.
 */
final class VehicleGallery extends AbstractMetaBox {


	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	protected static function get_post_type(): string {
		return 'mhmrentiva_vehicle';
	}

	protected static function get_meta_box_id(): string {
		return 'mhmrentiva_vehicle_gallery';
	}

	protected static function get_title(): string {
		return __( 'Vehicle Gallery', 'mhm-rentiva' );
	}

	protected static function get_fields(): array {
		return array(
			'mhmrentiva_vehicle_gallery' => array(
				'title'    => __( 'Vehicle Gallery', 'mhm-rentiva' ),
				'context'  => 'side',
				'priority' => 'high',
				'template' => 'render_gallery_meta_box',
			),
		);
	}

	public static function register(): void {
		parent::register();

		// register_meta_fields() is NOT hooked here -- see VehicleMeta::register().
		// This method is admin-only, and a meta DECLARATION must not be.
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		add_action( 'save_post_mhmrentiva_vehicle', array( self::class, 'save_gallery_images' ) );

		add_action( 'wp_ajax_mhmrentiva_add_gallery_image', array( self::class, 'ajax_add_gallery_image' ) );
		add_action( 'wp_ajax_mhmrentiva_remove_gallery_image', array( self::class, 'ajax_remove_gallery_image' ) );
		add_action( 'wp_ajax_mhmrentiva_reorder_gallery_images', array( self::class, 'ajax_reorder_gallery_images' ) );
	}

	/**
	 * Register meta fields
	 */
	public static function register_meta_fields(): void {
		register_post_meta(
			'mhmrentiva_vehicle',
			'_mhmrentiva_gallery_images',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_gallery_images' ),
			)
		);
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue_scripts(): void {
		global $post_type;

		if ( $post_type !== 'mhmrentiva_vehicle' ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'mhm-rentiva-vehicle-gallery',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-gallery.js',
			array( 'jquery', 'jquery-ui-sortable', 'media-upload', 'media-views' ),
			MHMRENTIVA_VERSION,
			true
		);

		wp_enqueue_style(
			'mhm-rentiva-vehicle-gallery',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-gallery.css',
			array( 'mhm-rentiva-css-variables' ),
			MHMRENTIVA_VERSION
		);

		// Get max gallery images from settings (default: 50)
		$max_gallery_images = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhmrentiva_vehicle_max_gallery_images',
			50 // Default: 50 images
		);

		wp_localize_script(
			'mhm-rentiva-vehicle-gallery',
			'mhmVehicleGallery',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'mhmrentiva_vehicle_gallery_nonce' ),
				'maxImages' => $max_gallery_images,
				'strings'   => array(
					'selectImages'     => __( 'Select Images', 'mhm-rentiva' ),
					'addImages'        => __( 'Add Image', 'mhm-rentiva' ),
					'removeImage'      => __( 'Remove Image', 'mhm-rentiva' ),
					'setAsFeatured'    => __( 'Set as Featured Image', 'mhm-rentiva' ),
					'noImages'         => __( 'No images added yet', 'mhm-rentiva' ),
					/* translators: %d: maximum number of images */
					'maxImages'        => sprintf( __( 'You can add maximum %d images', 'mhm-rentiva' ), $max_gallery_images ),
					'confirmRemove'    => __( 'Are you sure you want to remove this image?', 'mhm-rentiva' ),
					'uploading'        => __( 'Uploading...', 'mhm-rentiva' ),
					'uploadError'      => __( 'Error occurred while uploading image', 'mhm-rentiva' ),
					'addImageError'    => __( 'Error occurred while adding image', 'mhm-rentiva' ),
					'removeImageError' => __( 'Error occurred while removing image', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * Render gallery meta box
	 *
	 * Every stored entry is listed. An entry the site does not show (see
	 * is_displayable_image()) is marked and listed without its image -- it may be
	 * another user's file -- so the drop on the next save is announced, not silent.
	 */
	public static function render_gallery_meta_box( \WP_Post $post ): void {
		$gallery_images = array();
		foreach ( self::get_gallery_images( $post->ID ) as $entry ) {
			$id = self::entry_id( $entry );
			if ( $id <= 0 ) {
				continue;
			}

			$shown = self::is_displayable_image( $post->ID, $id );

			// The stored url is never trusted: it is rebuilt from the attachment.
			$gallery_images[] = array(
				'id'    => $id,
				'url'   => $shown ? self::rendition_url( $id ) : '',
				'alt'   => $shown ? self::entry_text( $entry, 'alt' ) : '',
				'title' => $shown ? self::entry_text( $entry, 'title' ) : '',
				'shown' => $shown,
			);
		}

		include MHMRENTIVA_PLUGIN_PATH . 'src/Admin/Vehicle/Templates/vehicle-gallery.php';
	}

	/**
	 * Save gallery images
	 */
	public static function save_gallery_images( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['mhmrentiva_gallery_images_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_gallery_images' ) ) {
			return;
		}

		if ( isset( $_POST['mhmrentiva_gallery_images'] ) ) {
			$gallery_images = sanitize_text_field( wp_unslash( (string) $_POST['mhmrentiva_gallery_images'] ) );
			$decoded        = json_decode( $gallery_images, true );
			$entries        = self::filter_allowed_entries( $post_id, is_array( $decoded ) ? $decoded : array() );
			update_post_meta( $post_id, '_mhmrentiva_gallery_images', wp_slash( wp_json_encode( $entries ) ) );
		}
	}

	/**
	 * Whether an attachment may newly enter a vehicle's gallery or become its featured image.
	 *
	 * Passes only for an image attachment the actor may use: actors with
	 * `edit_others_posts` may use any image, everyone else only images attached to
	 * the vehicle or uploaded by themselves. The filter runs last so an add-on can
	 * veto an otherwise acceptable image (e.g. a sensitive document).
	 *
	 * Applies to entries that are new to the vehicle. An entry already stored for
	 * the same vehicle is judged by is_displayable_image() instead, so a vendor's
	 * write does not silently drop an image an administrator uploaded and added.
	 * The two rules differ on purpose: an administrator may add another ordinary
	 * user's upload here, but that entry has no trusted provenance on the
	 * vehicle, so it is then neither displayed nor kept by a later write.
	 *
	 * @param int      $vehicle_id Vehicle the image is destined for.
	 * @param int      $id         Attachment ID.
	 * @param int|null $actor_id   Acting user; defaults to the current user.
	 */
	public static function is_allowed_image( int $vehicle_id, int $id, ?int $actor_id = null ): bool {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return false;
		}

		$actor_id = $actor_id ?? get_current_user_id();

		if ( ! user_can( $actor_id, 'edit_others_posts' ) ) {
			$attachment = get_post( $id );
			if ( ! $attachment || ( (int) $attachment->post_parent !== $vehicle_id && (int) $attachment->post_author !== $actor_id ) ) {
				return false;
			}
		}

		/**
		 * Filters whether an attachment may be used in a vehicle gallery.
		 *
		 * Applied only after the type, image and ownership checks have passed.
		 *
		 * The filter is a content veto (e.g. a sensitive attachment). When an image
		 * is displayed, or an entry already in the gallery is kept on a write, it
		 * runs after the image and provenance checks, with the vehicle's author as
		 * `$actor_id` rather than the signed-in user, so a filter that grants access
		 * based on the actor is not supported there.
		 *
		 * @since 6.1.6
		 *
		 * @param bool $allowed    Whether the attachment is allowed.
		 * @param int  $id         Attachment ID.
		 * @param int  $vehicle_id Vehicle ID.
		 * @param int  $actor_id   Acting user ID.
		 */
		return (bool) apply_filters( 'mhmrentiva_gallery_attachment_allowed', true, $id, $vehicle_id, $actor_id );
	}

	/**
	 * Whether a stored gallery image may be shown, kept on a write, or written without a signed-in user.
	 *
	 * A stored row is not proof that its entries were accepted: a row can be
	 * written straight to the database, by an older version, or by an
	 * integration. So an entry is shown, or kept when already stored for the same
	 * vehicle, only when it is an image attachment with a trusted provenance on
	 * this vehicle (see has_trusted_provenance()) and the
	 * `mhmrentiva_gallery_attachment_allowed` filter does not veto it. The
	 * vehicle's author is passed as the actor: the filter is a content veto, and
	 * a filter that grants access based on the actor is not supported here. A
	 * sensitive document therefore never renders. The no-current-user write
	 * branches of guard_gallery() and guard_thumbnail() (importers, CLI, cron)
	 * use the same rule.
	 *
	 * Known limit: an administrator may add another ordinary user's upload to a
	 * vehicle (is_allowed_image() lets edit_others_posts use any image), but such
	 * an entry has no trusted provenance here, so it is not displayed and a later
	 * write drops it. Attaching the image to the vehicle makes it trusted.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @param int $id         Attachment ID.
	 */
	public static function is_displayable_image( int $vehicle_id, int $id ): bool {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return false;
		}

		if ( ! self::has_trusted_provenance( $vehicle_id, $id ) ) {
			return false;
		}

		$owner = (int) get_post_field( 'post_author', $vehicle_id );

		/** This filter is documented in is_allowed_image(). */
		return (bool) apply_filters( 'mhmrentiva_gallery_attachment_allowed', true, $id, $vehicle_id, $owner );
	}

	/**
	 * Whether an attachment comes from a source trusted for this vehicle.
	 *
	 * Trusted: the attachment is attached to the vehicle, or it was uploaded by
	 * the vehicle's author, by no user (0: WP-CLI, importers), or by a user with
	 * `edit_others_posts` (administrators, editors). Another ordinary user's
	 * upload is not trusted.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @param int $id         Attachment ID.
	 */
	private static function has_trusted_provenance( int $vehicle_id, int $id ): bool {
		$attachment = get_post( $id );
		if ( ! $attachment ) {
			return false;
		}

		if ( (int) $attachment->post_parent === $vehicle_id ) {
			return true;
		}

		$uploader = (int) $attachment->post_author;

		return 0 === $uploader
			|| (int) get_post_field( 'post_author', $vehicle_id ) === $uploader
			|| user_can( $uploader, 'edit_others_posts' );
	}

	/**
	 * Attachment ids already in this vehicle's stored gallery.
	 *
	 * Read from the stored value before a write (the meta write filters run
	 * before the row changes), so the set never holds what the write is adding,
	 * and never another vehicle's entries.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @return array<int, true> Ids as keys.
	 */
	private static function stored_gallery_ids( int $vehicle_id ): array {
		$ids = array();
		foreach ( self::get_gallery_images( $vehicle_id ) as $entry ) {
			$id = self::entry_id( $entry );
			if ( $id > 0 ) {
				$ids[ $id ] = true;
			}
		}
		return $ids;
	}

	/**
	 * Whether a gallery entry may be written: an entry already stored for this
	 * vehicle needs is_displayable_image() (image, trusted provenance, no veto),
	 * a new one needs is_allowed_image(). Being stored alone never keeps an entry.
	 *
	 * @param int              $vehicle_id Vehicle ID.
	 * @param int              $id         Attachment ID.
	 * @param array<int, true> $stored     Ids already stored for this vehicle.
	 * @param int|null         $actor_id   Acting user; defaults to the current user.
	 */
	private static function may_write_entry( int $vehicle_id, int $id, array $stored, ?int $actor_id ): bool {
		return isset( $stored[ $id ] )
			? self::is_displayable_image( $vehicle_id, $id )
			: self::is_allowed_image( $vehicle_id, $id, $actor_id );
	}

	/**
	 * Current user ID, or 0 when user functions are not loaded yet (guards run from bootstrap).
	 */
	private static function current_actor_id(): int {
		return function_exists( 'wp_get_current_user' ) ? get_current_user_id() : 0;
	}

	/**
	 * The single choke for a vehicle's featured image (`_thumbnail_id`).
	 *
	 * Hooked on `update_post_metadata` and `add_post_metadata` on every request,
	 * so admin, REST, front-end and add-on writers all pass through it. Returning
	 * `false` rejects the write; returning the incoming `$check` leaves WordPress
	 * to carry on (a non-null `$check` would short-circuit the write without
	 * storing). Clearing values (0, '', -1) always pass, and deleting the meta is
	 * never blocked: `delete_post_metadata` is not hooked.
	 *
	 * Re-writing the id that is already the featured image needs
	 * is_displayable_image(); any other id needs is_allowed_image(). A write
	 * without a current user (WP-CLI, cron, importers) needs is_displayable_image():
	 * an image with a trusted provenance on the vehicle that the filter does not
	 * veto.
	 *
	 * @param mixed  $check      Short-circuit value from earlier filters.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Value about to be stored.
	 * @return mixed `$check` unchanged to allow, `false` to reject.
	 */
	public static function guard_thumbnail( $check, int $object_id, string $meta_key, $meta_value ) {
		if ( '_thumbnail_id' !== $meta_key || 'mhmrentiva_vehicle' !== get_post_type( $object_id ) ) {
			return $check;
		}

		// Clearing the featured image is never blocked.
		if ( '' === $meta_value || ( is_numeric( $meta_value ) && in_array( (int) $meta_value, array( 0, -1 ), true ) ) ) {
			return $check;
		}

		if ( ! is_numeric( $meta_value ) ) {
			return false;
		}

		$id       = (int) $meta_value;
		$actor_id = self::current_actor_id();
		$current  = (int) get_post_meta( $object_id, '_thumbnail_id', true );

		$allowed = ( 0 === $actor_id || ( $current > 0 && $current === $id ) )
			? self::is_displayable_image( $object_id, $id )
			: self::is_allowed_image( $object_id, $id, $actor_id );

		return $allowed ? $check : false;
	}

	/**
	 * Write guard for a vehicle's gallery (`_mhmrentiva_gallery_images`).
	 *
	 * Hooked next to guard_thumbnail() on `update_post_metadata` and
	 * `add_post_metadata`, so a direct update_post_meta()/add_post_meta() from an
	 * integration or importer obeys the same image policy as the gallery writers.
	 * The incoming value is the already-sanitized JSON string (or array); anything
	 * that is not a list is left to the registered sanitize callback. An id that
	 * is already in this vehicle's stored gallery needs is_displayable_image()
	 * (image, trusted provenance, no veto); a new id needs is_allowed_image().
	 * When there is no current user (WP-CLI, cron, importers)
	 * is_displayable_image() judges every entry, so the provenance rule and the
	 * filter veto still apply.
	 *
	 * @param mixed  $check      Short-circuit value from earlier filters.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Value about to be stored.
	 * @return mixed `$check` unchanged to allow, `false` to reject.
	 */
	public static function guard_gallery( $check, int $object_id, string $meta_key, $meta_value ) {
		if ( '_mhmrentiva_gallery_images' !== $meta_key || 'mhmrentiva_vehicle' !== get_post_type( $object_id ) ) {
			return $check;
		}

		$entries = self::normalize_gallery( $meta_value );

		$actor_id = self::current_actor_id();
		$stored   = 0 === $actor_id || array() === $entries ? array() : self::stored_gallery_ids( $object_id );

		foreach ( $entries as $entry ) {
			// An entry that names no attachment is refused, not skipped: a reader
			// would resolve it the same way and could still show something.
			$id = self::entry_id( $entry );
			if ( $id <= 0 ) {
				return false;
			}

			$allowed = 0 === $actor_id
				? self::is_displayable_image( $object_id, $id )
				: self::may_write_entry( $object_id, $id, $stored, $actor_id );
			if ( ! $allowed ) {
				return false;
			}
		}

		return $check;
	}

	/**
	 * Read choke for a vehicle's featured image (`post_thumbnail_id`).
	 *
	 * Every core reader -- get_post_thumbnail_id(), has_post_thumbnail(),
	 * get_the_post_thumbnail(_url)() -- passes through this filter, so the
	 * plugin's grids, lists and account templates, WooCommerce and the theme all
	 * see a vehicle's cover only when it is_displayable_image(), the same rule as
	 * the gallery readers. The stored `_thumbnail_id` is left as it is: the admin
	 * featured-image box and the write guards read the meta directly.
	 *
	 * @param int|false        $thumbnail_id Stored thumbnail ID, or false when the post does not exist.
	 * @param int|\WP_Post|null $post        Post the thumbnail belongs to.
	 * @return int|false
	 */
	public static function filter_thumbnail_id( $thumbnail_id, $post ) {
		if ( ! $thumbnail_id ) {
			return $thumbnail_id;
		}

		$post = get_post( $post );
		if ( ! $post || 'mhmrentiva_vehicle' !== $post->post_type ) {
			return $thumbnail_id;
		}

		if ( false === wp_cache_get( (int) $thumbnail_id, 'posts' ) ) {
			self::prime_main_query_covers();
		}

		return self::is_displayable_image( (int) $post->ID, (int) $thumbnail_id ) ? $thumbnail_id : 0;
	}

	/**
	 * Core's featured-image box for a vehicle whose stored cover the site does not show.
	 *
	 * The box reads `_thumbnail_id` raw, so it would render another user's file
	 * (possibly a sensitive document) to the editor. Instead it shows the same
	 * mark as the gallery box, followed by core's empty box, whose field holds -1:
	 * saving the vehicle clears the cover, as the mark announces.
	 *
	 * @param string   $content      Box HTML.
	 * @param int      $post_id      Post ID.
	 * @param int|null $thumbnail_id Stored thumbnail ID.
	 */
	public static function filter_admin_thumbnail_html( $content, $post_id, $thumbnail_id ): string {
		$thumbnail_id = (int) $thumbnail_id;
		if ( $thumbnail_id <= 0 || 'mhmrentiva_vehicle' !== get_post_type( (int) $post_id ) || self::is_displayable_image( (int) $post_id, $thumbnail_id ) ) {
			return (string) $content;
		}

		$mark = sprintf(
			'<p class="mhm-thumbnail-hidden"><span class="dashicons dashicons-hidden" aria-hidden="true"></span> <strong>%1$s</strong><br />%2$s</p>',
			esc_html__( 'Not shown on the site', 'mhm-rentiva' ),
			esc_html__( 'This featured image is not shown on the site and will be removed when the vehicle is saved.', 'mhm-rentiva' )
		);

		return $mark . _wp_post_thumbnail_html( null, (int) $post_id );
	}

	/**
	 * Loads the covers of the main query's vehicles, and their uploaders, in one go.
	 *
	 * Core's update_post_thumbnail_cache() asks for every post's thumbnail id before
	 * it primes the attachments, so judging each cover there would load them one
	 * by one. On the first cache miss the stored covers of the main query's
	 * vehicles (their meta is already primed by the query) are loaded together.
	 * Runs once per main query; covers outside it are judged as they come.
	 */
	private static function prime_main_query_covers(): void {
		static $primed = array();

		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query || empty( $wp_query->posts ) ) {
			return;
		}

		$key = spl_object_id( $wp_query ) . ':' . count( $wp_query->posts );
		if ( isset( $primed[ $key ] ) ) {
			return;
		}
		$primed[ $key ] = true;

		self::prime_covers( array_map( static fn( $item ): int => $item instanceof \WP_Post ? (int) $item->ID : (int) $item, $wp_query->posts ) );
	}

	/**
	 * Loads the stored covers of the given vehicles, and their uploaders, in one go,
	 * so filter_thumbnail_id() judges them from the cache.
	 *
	 * Call it with the result of a vehicle query before reading the covers in a
	 * loop. The vehicles' own meta should be primed already (get_posts() does).
	 * Ids that are not vehicles are ignored.
	 *
	 * @param array<int, int> $vehicle_ids Vehicle IDs.
	 */
	public static function prime_covers( array $vehicle_ids ): void {
		$covers = array();
		foreach ( $vehicle_ids as $vehicle_id ) {
			if ( 'mhmrentiva_vehicle' !== get_post_type( (int) $vehicle_id ) ) {
				continue;
			}
			$cover = (int) get_post_meta( (int) $vehicle_id, '_thumbnail_id', true );
			if ( $cover > 0 ) {
				$covers[] = $cover;
			}
		}
		if ( array() === $covers ) {
			return;
		}

		_prime_post_caches( $covers, false, true );

		$uploaders = array();
		foreach ( $covers as $cover ) {
			$uploader = (int) get_post_field( 'post_author', $cover );
			if ( $uploader > 0 ) {
				$uploaders[] = $uploader;
			}
		}
		if ( array() !== $uploaders ) {
			cache_users( array_unique( $uploaders ) );
		}
	}

	/**
	 * Whether the current user may move an existing attachment onto a vehicle.
	 *
	 * Being attached to a vehicle makes an attachment a trusted source for that
	 * vehicle's gallery and cover (has_trusted_provenance()). WordPress checks the
	 * parent when an upload is created, but not when an existing attachment is
	 * re-parented, so a vendor could move their own upload onto another vendor's
	 * vehicle. Moving onto a vehicle therefore needs edit_post on it. A parent
	 * that does not change, detaching, a non-vehicle parent and a write without a
	 * current user (WP-CLI, cron, importers) are not restricted.
	 *
	 * @param int $attachment_id Existing attachment ID.
	 * @param int $parent        Requested parent ID.
	 */
	private static function may_attach_to( int $attachment_id, int $parent ): bool {
		if ( $parent <= 0 || 'mhmrentiva_vehicle' !== get_post_type( $parent ) ) {
			return true;
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || (int) $attachment->post_parent === $parent ) {
			return true;
		}

		return 0 === self::current_actor_id() || current_user_can( 'edit_post', $parent );
	}

	/**
	 * Keeps an attachment's old parent when an update would move it onto a vehicle
	 * the current user may not edit (see may_attach_to()).
	 *
	 * Hooked on `wp_insert_attachment_data`, so every path that ends in
	 * wp_insert_post() is covered: REST, the media modal's save-attachment, the
	 * attachment edit screen and add-ons. New uploads are left to the checks of
	 * the code that creates them. The REST route also gets an explicit error from
	 * guard_rest_attachment_parent(), so its caller is not told the move succeeded.
	 *
	 * @param array<string, mixed> $data                Slashed, sanitized attachment data.
	 * @param array<string, mixed> $postarr             Sanitized post data.
	 * @param array<string, mixed> $unsanitized_postarr Unsanitized post data.
	 * @param bool                 $update              Whether an existing attachment is updated.
	 * @return array<string, mixed>
	 */
	public static function guard_attachment_parent( $data, $postarr, $unsanitized_postarr, $update ) {
		unset( $unsanitized_postarr );

		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $update || $id <= 0 || ! isset( $data['post_parent'] ) ) {
			return $data;
		}

		if ( ! self::may_attach_to( $id, (int) $data['post_parent'] ) ) {
			$data['post_parent'] = (int) get_post_field( 'post_parent', $id );
		}

		return $data;
	}

	/**
	 * REST face of guard_attachment_parent(): refuses `POST /wp/v2/media/{id}` with
	 * a `post` the current user may not attach to, instead of answering 200 with
	 * the parent silently unchanged.
	 *
	 * The requested parent is read from the request: the attachments controller
	 * copies `post` into the prepared object only after this filter has run.
	 *
	 * @param \stdClass|\WP_Error $prepared_post Attachment prepared for the database.
	 * @param \WP_REST_Request    $request       Request.
	 * @return \stdClass|\WP_Error
	 */
	public static function guard_rest_attachment_parent( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) || empty( $prepared_post->ID ) || ! isset( $request['post'] ) ) {
			return $prepared_post;
		}

		if ( self::may_attach_to( (int) $prepared_post->ID, (int) $request['post'] ) ) {
			return $prepared_post;
		}

		return new \WP_Error(
			'mhmrentiva_cannot_attach_to_vehicle',
			__( 'You are not allowed to attach media to this vehicle.', 'mhm-rentiva' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Server-built URL stored with a gallery entry: the medium rendition, so the
	 * edit screen does not download originals; the original when no rendition exists.
	 */
	private static function rendition_url( int $id ): string {
		$url = wp_get_attachment_image_url( $id, 'medium' );
		if ( ! $url ) {
			$url = wp_get_attachment_url( $id );
		}
		return (string) $url;
	}

	/**
	 * Reduce gallery entries to those that may be written.
	 *
	 * An id already in this vehicle's stored gallery needs is_displayable_image()
	 * (image, trusted provenance, no veto), so an image an administrator uploaded
	 * and added survives a vendor's save while a stored upload of another
	 * ordinary user does not; a new id needs is_allowed_image() (ownership plus
	 * veto).
	 * Ids are resolved with (int) because stored galleries may hold them as
	 * strings. The client-supplied url is never trusted: it is rebuilt from the
	 * attachment. Rejected entries are dropped silently.
	 *
	 * @param int               $vehicle_id Vehicle ID.
	 * @param array<int, mixed> $entries    Gallery entries (id/url/alt/title).
	 * @param int|null          $actor_id   Acting user; defaults to the current user.
	 * @return array<int, array{id:int,url:string,alt:string,title:string}>
	 */
	public static function filter_allowed_entries( int $vehicle_id, array $entries, ?int $actor_id = null ): array {
		$kept   = array();
		$stored = self::stored_gallery_ids( $vehicle_id );

		foreach ( $entries as $entry ) {
			$id = self::entry_id( $entry );
			if ( $id <= 0 || ! self::may_write_entry( $vehicle_id, $id, $stored, $actor_id ) ) {
				continue;
			}

			$kept[] = array(
				'id'    => $id,
				'url'   => self::rendition_url( $id ),
				'alt'   => sanitize_text_field( self::entry_text( $entry, 'alt' ) ),
				'title' => sanitize_text_field( self::entry_text( $entry, 'title' ) ),
			);
		}

		return $kept;
	}

	/**
	 * Sanitize gallery images
	 */
	public static function sanitize_gallery_images( $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$images = is_string( $value ) ? json_decode( $value, true ) : $value;
		if ( ! is_array( $images ) ) {
			return '';
		}

		$sanitized_images = array();
		foreach ( $images as $image ) {
			$id = self::entry_id( $image );
			if ( $id > 0 ) {
				$sanitized_images[] = array(
					'id'    => $id,
					'url'   => esc_url_raw( self::entry_text( $image, 'url' ) ),
					'alt'   => sanitize_text_field( self::entry_text( $image, 'alt' ) ),
					'title' => sanitize_text_field( self::entry_text( $image, 'title' ) ),
				);
			}
		}

		return wp_json_encode( $sanitized_images );
	}

	/**
	 * AJAX: Add gallery image
	 */
	public static function ajax_add_gallery_image(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_vehicle_gallery_nonce' ) ) {
			wp_send_json_error( __( 'Security error', 'mhm-rentiva' ) );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );

		// edit_post on the vehicle the request names, not the blanket edit_posts:
		// this writes gallery meta on whichever post_id arrives, and the vehicle CPT
		// uses the default 'post' capability_type, so edit_posts alone let any
		// Author who owns one listing rewrite another vendor's gallery.
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission error', 'mhm-rentiva' ) );
		}

		// edit_post answers "may this user edit that post", never "is that post one of
		// ours". map_meta_cap grants it for any post the caller owns, so without a type
		// check this handler writes gallery meta onto whatever id arrives.
		if ( 'mhmrentiva_vehicle' !== get_post_type( $post_id ) ) {
			wp_send_json_error( __( 'Vehicle not found.', 'mhm-rentiva' ) );
		}

		$image_ids = array_map( 'intval', $_POST['image_ids'] ?? array() );

		if ( empty( $image_ids ) ) {
			wp_send_json_error( __( 'Invalid data', 'mhm-rentiva' ) );
		}

		// Re-validate what is already stored: entries without a trusted provenance,
		// non-images and vetoed entries are dropped.
		$gallery_images = self::filter_allowed_entries( $post_id, self::get_gallery_images( $post_id ) );

		$existing_ids = array_column( $gallery_images, 'id' );

		// Determine limit
		$limit = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_vehicle_max_gallery_images', 50 );

		// Picked images the rule refuses are counted, so the response can say so.
		$rejected = 0;

		foreach ( $image_ids as $image_id ) {
			if ( ! in_array( $image_id, $existing_ids, true ) && count( $gallery_images ) < $limit ) {
				$new_entry = self::filter_allowed_entries(
					$post_id,
					array(
						array(
							'id'    => $image_id,
							'alt'   => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
							'title' => get_the_title( $image_id ),
						),
					)
				);
				if ( $new_entry ) {
					$gallery_images[] = $new_entry[0];
					$existing_ids[]   = $image_id;
				} else {
					++$rejected;
				}
			}
		}

		update_post_meta( $post_id, '_mhmrentiva_gallery_images', wp_slash( wp_json_encode( $gallery_images ) ) );

		wp_send_json_success(
			array(
				'message'        => $rejected > 0
					? __( 'Some images could not be added: only images you uploaded or images attached to this vehicle can be used.', 'mhm-rentiva' )
					: __( 'Images successfully added', 'mhm-rentiva' ),
				'rejected'       => $rejected,
				'gallery_images' => $gallery_images,
			)
		);
	}

	/**
	 * AJAX: Remove gallery image
	 */
	public static function ajax_remove_gallery_image(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_vehicle_gallery_nonce' ) ) {
			wp_send_json_error( __( 'Security error', 'mhm-rentiva' ) );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );

		// See the note on ajax_add_gallery_image(): the capability has to be about
		// the vehicle being modified.
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission error', 'mhm-rentiva' ) );
		}

		// edit_post answers "may this user edit that post", never "is that post one of
		// ours". map_meta_cap grants it for any post the caller owns, so without a type
		// check this handler writes gallery meta onto whatever id arrives.
		if ( 'mhmrentiva_vehicle' !== get_post_type( $post_id ) ) {
			wp_send_json_error( __( 'Vehicle not found.', 'mhm-rentiva' ) );
		}

		$image_id = intval( $_POST['image_id'] ?? 0 );

		if ( ! $image_id ) {
			wp_send_json_error( __( 'Invalid data', 'mhm-rentiva' ) );
		}

		// Re-validate what stays: entries without a trusted provenance, non-images
		// and vetoed entries are dropped.
		$gallery_images = self::filter_allowed_entries( $post_id, self::get_gallery_images( $post_id ) );

		$gallery_images = array_filter(
			$gallery_images,
			function ( $image ) use ( $image_id ) {
				return $image['id'] !== $image_id;
			}
		);

		update_post_meta( $post_id, '_mhmrentiva_gallery_images', wp_slash( wp_json_encode( array_values( $gallery_images ) ) ) );

		wp_send_json_success(
			array(
				'message'        => __( 'Image successfully removed', 'mhm-rentiva' ),
				'gallery_images' => array_values( $gallery_images ),
			)
		);
	}

	/**
	 * AJAX: Reorder gallery images
	 */
	public static function ajax_reorder_gallery_images(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_vehicle_gallery_nonce' ) ) {
			wp_send_json_error( __( 'Security error', 'mhm-rentiva' ) );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );

		// See the note on ajax_add_gallery_image(): the capability has to be about
		// the vehicle being modified.
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission error', 'mhm-rentiva' ) );
		}

		// edit_post answers "may this user edit that post", never "is that post one of
		// ours". map_meta_cap grants it for any post the caller owns, so without a type
		// check this handler writes gallery meta onto whatever id arrives.
		if ( 'mhmrentiva_vehicle' !== get_post_type( $post_id ) ) {
			wp_send_json_error( __( 'Vehicle not found.', 'mhm-rentiva' ) );
		}

		$image_order = array_map( 'intval', $_POST['image_order'] ?? array() );

		if ( empty( $image_order ) ) {
			wp_send_json_error( __( 'Invalid data', 'mhm-rentiva' ) );
		}

		// Only entries that pass the rule can be reordered into the result.
		$gallery_images = self::filter_allowed_entries( $post_id, self::get_gallery_images( $post_id ) );

		$reordered_images = array();
		foreach ( $image_order as $image_id ) {
			foreach ( $gallery_images as $image ) {
				if ( $image['id'] === $image_id ) {
					$reordered_images[] = $image;
					break;
				}
			}
		}

		update_post_meta( $post_id, '_mhmrentiva_gallery_images', wp_slash( wp_json_encode( $reordered_images ) ) );

		wp_send_json_success(
			array(
				'message'        => __( 'Images successfully reordered', 'mhm-rentiva' ),
				'gallery_images' => $reordered_images,
			)
		);
	}

	/**
	 * Reduce a stored or incoming gallery value to a list of entries.
	 *
	 * The value is a JSON string normally, but a write made before the meta field
	 * was registered can leave a PHP array, and a damaged row can hold anything.
	 * Arrays are used as they are, strings are decoded, everything else (and any
	 * decode failure or non-list) yields an empty list.
	 *
	 * @param mixed $raw Stored or incoming value.
	 * @return array<int, mixed>
	 */
	public static function normalize_gallery( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		if ( ! is_array( $raw ) || array() === $raw || array_keys( $raw ) !== range( 0, count( $raw ) - 1 ) ) {
			return array();
		}

		return $raw;
	}

	/**
	 * Attachment ID a stored gallery entry names, or 0 when it names none.
	 *
	 * One resolution shared by the write guard and the readers so they cannot
	 * disagree: an array with a numeric `id`, or a numeric scalar (the bare-id form).
	 *
	 * @param mixed $entry Gallery entry.
	 */
	public static function entry_id( $entry ): int {
		if ( is_array( $entry ) ) {
			$entry = $entry['id'] ?? null;
		}

		return is_numeric( $entry ) ? (int) $entry : 0;
	}

	/**
	 * A text field of a stored gallery entry as a string; '' for scalar entries,
	 * missing keys and non-scalar values.
	 *
	 * @param mixed  $entry Gallery entry.
	 * @param string $key   Field name.
	 */
	private static function entry_text( $entry, string $key ): string {
		$value = is_array( $entry ) ? ( $entry[ $key ] ?? '' ) : '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Get gallery images
	 */
	public static function get_gallery_images( int $post_id ): array {
		return self::normalize_gallery( get_post_meta( $post_id, '_mhmrentiva_gallery_images', true ) );
	}

	/**
	 * Get gallery images for frontend use
	 *
	 * `alt` and `title` come back unescaped; the consumer must escape them for its context.
	 */
	public static function get_gallery_for_frontend( int $post_id, string $size = 'medium' ): array {
		$gallery_images  = self::get_gallery_images( $post_id );
		$frontend_images = array();

		foreach ( $gallery_images as $image ) {
			$image_id = self::entry_id( $image );
			if ( $image_id <= 0 || ! self::is_displayable_image( $post_id, $image_id ) ) {
				continue;
			}

			$image_url = wp_get_attachment_image_url( $image_id, $size );
			if ( $image_url ) {
				$frontend_images[] = array(
					'id'            => $image_id,
					'url'           => $image_url,
					'alt'           => self::entry_text( $image, 'alt' ),
					'title'         => self::entry_text( $image, 'title' ),
					'full_url'      => wp_get_attachment_image_url( $image_id, 'full' ),
					'thumbnail_url' => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
				);
			}
		}

		return $frontend_images;
	}
}
