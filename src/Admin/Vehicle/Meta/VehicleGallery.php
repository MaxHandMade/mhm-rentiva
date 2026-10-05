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
	 */
	public static function render_gallery_meta_box( \WP_Post $post ): void {
		$gallery_images = self::get_gallery_images( $post->ID );

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
	 * Whether an attachment may enter a vehicle's gallery or become its featured image.
	 *
	 * Passes only for an image attachment the actor may use: actors with
	 * `edit_others_posts` may use any image, everyone else only images attached to
	 * the vehicle or uploaded by themselves. The filter runs last so an add-on can
	 * veto an otherwise acceptable image (e.g. a sensitive document).
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
	 * Whether a stored gallery image may be shown, or written without a signed-in user.
	 *
	 * The display layer does not re-decide ownership: every writer enforces
	 * is_allowed_image() (ownership plus the filter veto), so what is stored was
	 * accepted for someone entitled to curate this gallery. Display therefore asks
	 * only that the entry is an image attachment and that the
	 * `mhmrentiva_gallery_attachment_allowed` filter does not veto it (the vehicle's
	 * author is passed as the actor), so a sensitive document never renders. The
	 * no-current-user write branch of guard_gallery() (importers, CLI, cron) uses
	 * the same rule, because those code paths are trusted.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @param int $id         Attachment ID.
	 */
	public static function is_displayable_image( int $vehicle_id, int $id ): bool {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return false;
		}

		$owner = (int) get_post_field( 'post_author', $vehicle_id );

		/** This filter is documented in is_allowed_image(). */
		return (bool) apply_filters( 'mhmrentiva_gallery_attachment_allowed', true, $id, $vehicle_id, $owner );
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
	 * storing). User 0 (WP-CLI, cron, importers) is not restricted. Deleting the
	 * meta is never blocked: `delete_post_metadata` is not hooked.
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

		if ( 0 === self::current_actor_id() ) {
			return $check;
		}

		if ( ! is_numeric( $meta_value ) || ! self::is_allowed_image( $object_id, (int) $meta_value ) ) {
			return false;
		}

		return $check;
	}

	/**
	 * Write guard for a vehicle's gallery (`_mhmrentiva_gallery_images`).
	 *
	 * Hooked next to guard_thumbnail() on `update_post_metadata` and
	 * `add_post_metadata`, so a direct update_post_meta()/add_post_meta() from an
	 * integration or importer obeys the same image policy as the gallery writers.
	 * The incoming value is the already-sanitized JSON string (or array); anything
	 * that is not a list is left to the registered sanitize callback. When there
	 * is no current user (WP-CLI, cron, importers) is_displayable_image() judges the
	 * entry, so the filter veto still applies.
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

		foreach ( $entries as $entry ) {
			// An entry that names no attachment is refused, not skipped: a reader
			// would resolve it the same way and could still show something.
			$id = self::entry_id( $entry );
			if ( $id <= 0 ) {
				return false;
			}

			$allowed = 0 === $actor_id
				? self::is_displayable_image( $object_id, $id )
				: self::is_allowed_image( $object_id, $id, $actor_id );
			if ( ! $allowed ) {
				return false;
			}
		}

		return $check;
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
	 * Reduce gallery entries to those that pass is_allowed_image().
	 *
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
		$kept = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['id'] ) || ! is_numeric( $entry['id'] ) ) {
				continue;
			}

			$id = (int) $entry['id'];
			if ( ! self::is_allowed_image( $vehicle_id, $id, $actor_id ) ) {
				continue;
			}

			$kept[] = array(
				'id'    => $id,
				'url'   => self::rendition_url( $id ),
				'alt'   => sanitize_text_field( (string) ( $entry['alt'] ?? '' ) ),
				'title' => sanitize_text_field( (string) ( $entry['title'] ?? '' ) ),
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
			if ( isset( $image['id'] ) && is_numeric( $image['id'] ) ) {
				$sanitized_images[] = array(
					'id'    => intval( $image['id'] ),
					'url'   => esc_url_raw( $image['url'] ?? '' ),
					'alt'   => sanitize_text_field( (string) ( $image['alt'] ?? '' ) ),
					'title' => sanitize_text_field( (string) ( $image['title'] ?? '' ) ),
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

		// Re-validate what is already stored: a gallery may have been poisoned earlier.
		$gallery_images = self::filter_allowed_entries( $post_id, self::get_gallery_images( $post_id ) );

		$existing_ids = array_column( $gallery_images, 'id' );

		// Determine limit
		$limit = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_vehicle_max_gallery_images', 50 );

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
				}
			}
		}

		update_post_meta( $post_id, '_mhmrentiva_gallery_images', wp_slash( wp_json_encode( $gallery_images ) ) );

		wp_send_json_success(
			array(
				'message'        => __( 'Images successfully added', 'mhm-rentiva' ),
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

		// Re-validate what stays: a stored gallery may already be poisoned.
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
	 * Get gallery images
	 */
	public static function get_gallery_images( int $post_id ): array {
		return self::normalize_gallery( get_post_meta( $post_id, '_mhmrentiva_gallery_images', true ) );
	}

	/**
	 * Get gallery images for frontend use
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
					'alt'           => is_array( $image ) ? (string) ( $image['alt'] ?? '' ) : '',
					'title'         => is_array( $image ) ? (string) ( $image['title'] ?? '' ) : '',
					'full_url'      => wp_get_attachment_image_url( $image_id, 'full' ),
					'thumbnail_url' => wp_get_attachment_image_url( $image_id, 'thumbnail' ),
				);
			}
		}

		return $frontend_images;
	}
}
