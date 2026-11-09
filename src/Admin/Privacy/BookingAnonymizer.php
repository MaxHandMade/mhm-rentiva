<?php
/**
 * Anonymise one closed booking and its order-line copy.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a booking loses its personal data (5a spec T5; user decision
 * U1: the booking, its dates, vehicle and amounts stay for the accounts).
 *
 * ORDER MATTERS (spec T4). Everything except the match keys is written first
 * and read back; only when all of it held are the match keys (the e-mail keys
 * and the customer user ID) anonymised. A booking that fails half-way therefore
 * still matches its person, so the eraser can mark it and a later run finds it
 * again -- it never drops out of reach while still holding personal data.
 *
 * Idempotent: a second call writes nothing new (the anonymous values are
 * already there, deleted keys stay deleted), so two erasure runs on the same
 * address end in the same state. Post fields are written straight to the
 * table, not through wp_update_post(): the booking's save_post handlers read
 * $_POST and must not run without a form behind them.
 */
final class BookingAnonymizer {

	private const NAME_KEYS = array(
		'_mhmrentiva_customer_name',
		'_mhmrentiva_customer_first_name',
		'_mhmrentiva_customer_last_name',
		'_mhmrentiva_booking_customer_name',
		'_mhmrentiva_booking_customer_first_name',
		'_mhmrentiva_contact_name',
	);

	private const PHONE_KEYS = array(
		'_mhmrentiva_customer_phone',
		'_mhmrentiva_booking_customer_phone',
		'_mhmrentiva_contact_phone',
	);

	private const EMAIL_KEYS = array(
		'_mhmrentiva_customer_email',
		'_mhmrentiva_booking_customer_email',
		'_mhmrentiva_contact_email',
	);

	private const FREE_TEXT_KEYS = array(
		'_mhmrentiva_special_notes',
		'_mhmrentiva_refund_reason',
		'_mhmrentiva_refund_completed_reference',
		'_mhmrentiva_refund_review_dismissed_reason',
	);

	private const RECEIPT_KEYS = array(
		'_mhmrentiva_receipt_attachment_id',
		'_mhmrentiva_receipt_uploaded_by',
		'_mhmrentiva_receipt_uploaded_at',
		'_mhmrentiva_receipt_note',
	);

	private const CANCELLATION_DROP = array( 'cancellation_reason', 'cancelled_by', 'cancelled_by_roles' );

	private const LOG_DATA_KEEP = array( 'order_id', 'refund_id', 'amount', 'refund_amount', 'currency', 'old_status', 'new_status', 'emailed' );

	private const SNAPSHOT_KEY = '_mhmrentiva_booking_data';

	/**
	 * Anonymise the booking. Returns the targets that did NOT end anonymised.
	 *
	 * @param int    $booking_id      Booking ID.
	 * @param int    $subject_user_id Account of the request's address, 0 for none.
	 * @param string $request_email   The request's address; its keyed hash is pinned
	 *                                on the booking while the identity is written.
	 * @return string[] Empty on full success.
	 */
	public static function anonymize( int $booking_id, int $subject_user_id, string $request_email = '' ): array {
		$customer_id = (int) get_post_meta( $booking_id, '_mhmrentiva_customer_user_id', true );
		$text        = (string) wp_privacy_anonymize_data( 'text' );

		foreach ( self::NAME_KEYS as $key ) {
			self::set_every_row( $booking_id, $key, $text );
		}
		foreach ( array_merge( self::PHONE_KEYS, self::FREE_TEXT_KEYS ) as $key ) {
			delete_post_meta( $booking_id, $key );
		}
		self::write_post_fields( $booking_id, $customer_id, $subject_user_id );
		self::write_cancellation( $booking_id );
		self::write_receipt( $booking_id );
		self::write_history( $booking_id );
		self::write_logs( $booking_id );
		$snapshots = self::write_snapshots( $booking_id );

		wp_cache_delete( $booking_id, 'post_meta' );
		clean_post_cache( $booking_id );

		$left = self::verify( $booking_id, $customer_id, $subject_user_id, $snapshots );
		if ( array() !== $left ) {
			return $left;
		}

		// Match keys last: only now may the booking stop matching its person.
		// The keys are several separate writes, so first pin the request's
		// keyed hash on the booking: if a later write fails half-way, the
		// matcher still finds the booking by that hash and a later run
		// finishes the job (Codex r4 B1).
		$subject = EmailMatch::subject_hash( $request_email );
		if ( '' !== $subject ) {
			update_post_meta( $booking_id, BookingMatcher::SUBJECT_KEY, $subject );
			wp_cache_delete( $booking_id, 'post_meta' );
			if ( get_post_meta( $booking_id, BookingMatcher::SUBJECT_KEY, true ) !== $subject ) {
				return array( 'identity' );
			}
		}

		$email = (string) wp_privacy_anonymize_data( 'email' );
		foreach ( self::EMAIL_KEYS as $key ) {
			self::set_every_row( $booking_id, $key, $email );
		}
		self::set_every_row( $booking_id, '_mhmrentiva_customer_user_id', '0' );
		wp_cache_delete( $booking_id, 'post_meta' );

		if ( ! self::identity_is_anonymous( $booking_id ) ) {
			return array( 'identity' );
		}

		// The first erasure's time is the one worth keeping. The mark is what
		// takes the booking out of the queue (core's anonymous address is a
		// fixed one), so it is read back: unwritten, the booking is not done
		// and keeps its subject hash (Codex r5).
		if ( ! metadata_exists( 'post', $booking_id, BookingMatcher::ERASED_KEY ) ) {
			update_post_meta( $booking_id, BookingMatcher::ERASED_KEY, gmdate( 'c' ) );
			if ( ! EmailMatch::meta_exists_now( 'post', $booking_id, BookingMatcher::ERASED_KEY ) ) {
				return array( 'erased_mark' );
			}
		}
		delete_post_meta( $booking_id, BookingMatcher::SUBJECT_KEY );

		return array();
	}

	/**
	 * Give every row of a meta key one value (existing keys only).
	 *
	 * One update_post_meta() call without a previous value updates every row
	 * of the key, so it covers a key stored twice; the check reads all rows.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $key        Meta key.
	 * @param string $value      Value every row should hold.
	 * @return void
	 */
	private static function set_every_row( int $booking_id, string $key, string $value ): void {
		if ( ! self::every_row_is( $booking_id, $key, $value ) ) {
			update_post_meta( $booking_id, $key, $value );
		}
	}

	/**
	 * Whether every row of a meta key holds the value (true when the key is absent).
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $key        Meta key.
	 * @param string $value      Expected value.
	 * @return bool
	 */
	private static function every_row_is( int $booking_id, string $key, string $value ): bool {
		foreach ( (array) get_post_meta( $booking_id, $key, false ) as $row ) {
			if ( (string) $row !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether any order line or the booking itself points at a WooCommerce order.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public static function has_linked_order( int $booking_id ): bool {
		if ( \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( $booking_id ) > 0 ) {
			return true;
		}

		return array() !== self::linked_item_ids( $booking_id );
	}

	/**
	 * Order item IDs whose `_mhmrentiva_booking_id` is this booking.
	 *
	 * Order items live in the same two tables under HPOS and the legacy post
	 * store, so this lookup does not depend on the order storage.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int[]
	 */
	private static function linked_item_ids( int $booking_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return array();
		}

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT order_item_id FROM {$table} WHERE meta_key = '_mhmrentiva_booking_id' AND meta_value = %s ORDER BY order_item_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefixed core table name.
				(string) $booking_id
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Clear post_content, and post_author when it is the customer.
	 *
	 * @param int $booking_id      Booking ID.
	 * @param int $customer_id     The booking's customer user ID before anonymising.
	 * @param int $subject_user_id Account of the request's address.
	 * @return void
	 */
	private static function write_post_fields( int $booking_id, int $customer_id, int $subject_user_id ): void {
		global $wpdb;

		$post = get_post( $booking_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$fields = array();
		if ( '' !== $post->post_content ) {
			$fields['post_content'] = '';
		}
		if ( self::author_is_the_person( (int) $post->post_author, $customer_id, $subject_user_id ) ) {
			$fields['post_author'] = 0;
		}
		if ( array() !== $fields ) {
			$wpdb->update( $wpdb->posts, $fields, array( 'ID' => $booking_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see the class docblock: no save_post without a form.
		}
	}

	/**
	 * Whether a post author is the person the request is about.
	 *
	 * @param int $author          post_author.
	 * @param int $customer_id     Booking's customer user ID before anonymising.
	 * @param int $subject_user_id Account of the request's address.
	 * @return bool
	 */
	private static function author_is_the_person( int $author, int $customer_id, int $subject_user_id ): bool {
		return $author > 0 && ( $author === $customer_id || $author === $subject_user_id );
	}

	/**
	 * Keep only how the booking was cancelled.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private static function write_cancellation( int $booking_id ): void {
		$data = get_post_meta( $booking_id, '_mhmrentiva_cancellation_data', true );
		if ( ! is_array( $data ) || array() === array_intersect( self::CANCELLATION_DROP, array_keys( $data ) ) ) {
			return;
		}

		update_post_meta( $booking_id, '_mhmrentiva_cancellation_data', array_diff_key( $data, array_flip( self::CANCELLATION_DROP ) ) );
	}

	/**
	 * Delete the uploaded receipt and its four meta keys.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private static function write_receipt( int $booking_id ): void {
		$attachment = (int) get_post_meta( $booking_id, '_mhmrentiva_receipt_attachment_id', true );
		if ( $attachment > 0 && get_post( $attachment ) instanceof \WP_Post ) {
			// The file first, and checked on disk: wp_delete_attachment() drops
			// the post even when the file stays, and then nothing points at the
			// file any more. Until the file is really gone, keep the post and
			// the link so verify() reports the receipt and the booking keeps
			// matching its person (Codex r4 B4).
			foreach ( self::attachment_files( $attachment ) as $file ) {
				if ( file_exists( $file ) ) {
					wp_delete_file( $file );
				}
				if ( file_exists( $file ) ) {
					return;
				}
			}
			wp_delete_attachment( $attachment, true );
		}
		if ( $attachment > 0 && get_post( $attachment ) instanceof \WP_Post ) {
			return; // Keep the link so verify() reports the receipt as left.
		}

		foreach ( self::RECEIPT_KEYS as $key ) {
			delete_post_meta( $booking_id, $key );
		}
	}

	/**
	 * Every file an attachment owns: the attached file, the pre-scaling
	 * original and each generated size, read before anything is deleted.
	 *
	 * @param int $attachment Attachment ID.
	 * @return string[] Absolute paths.
	 */
	private static function attachment_files( int $attachment ): array {
		$main  = (string) get_attached_file( $attachment );
		$files = '' !== $main ? array( $main ) : array();
		$meta  = wp_get_attachment_metadata( $attachment );
		if ( '' !== $main && is_array( $meta ) ) {
			$dir = dirname( $main );
			// Core adds `original_image` when it scaled a large upload; the
			// type stub does not list it.
			$original = array_key_exists( 'original_image', $meta ) ? (string) $meta['original_image'] : '';
			if ( '' !== $original ) {
				$files[] = $dir . '/' . wp_basename( $original );
			}
			foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) ) {
					$files[] = $dir . '/' . wp_basename( (string) $size['file'] );
				}
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * The anonymous form of the History box.
	 *
	 * @param mixed $history Stored value.
	 * @return array<string, mixed>|null Null when there is nothing to store.
	 */
	private static function anonymous_history( $history ): ?array {
		if ( ! is_array( $history ) ) {
			return null;
		}

		$out = array();
		foreach ( $history as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$type = (string) ( $entry['type'] ?? '' );
			if ( 'payment_update' === $type && isset( $entry['payment_from'], $entry['payment_to'] ) ) {
				$entry['note'] = '';
			} elseif ( 'status_change' !== $type ) {
				continue;
			}
			$entry['user_id'] = 0;
			$out[ $key ]      = $entry;
		}

		return $out;
	}

	/**
	 * Keep status changes and structured payment changes, without people.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private static function write_history( int $booking_id ): void {
		$history = get_post_meta( $booking_id, '_mhmrentiva_booking_history', true );
		$clean   = self::anonymous_history( $history );
		if ( null !== $clean && $clean !== $history ) {
			update_post_meta( $booking_id, '_mhmrentiva_booking_history', $clean );
		}
	}

	/**
	 * The anonymous form of the action log.
	 *
	 * @param mixed $logs Stored value.
	 * @return array<int|string, mixed>|null Null when there is nothing to store.
	 */
	private static function anonymous_logs( $logs ): ?array {
		if ( ! is_array( $logs ) ) {
			return null;
		}

		$out = array();
		foreach ( $logs as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$clean = array(
				'action'    => $entry['action'] ?? '',
				'timestamp' => $entry['timestamp'] ?? '',
			);
			if ( isset( $entry['data'] ) && is_array( $entry['data'] ) ) {
				$clean['data'] = array_intersect_key( $entry['data'], array_flip( self::LOG_DATA_KEEP ) );
			}
			$out[ $key ] = $clean;
		}

		return $out;
	}

	/**
	 * Keep what happened and the amounts; drop who and why.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private static function write_logs( int $booking_id ): void {
		$logs  = get_post_meta( $booking_id, '_mhmrentiva_booking_logs', true );
		$clean = self::anonymous_logs( $logs );
		if ( null !== $clean && $clean !== $logs ) {
			update_post_meta( $booking_id, '_mhmrentiva_booking_logs', $clean );
		}
	}

	/**
	 * Decode an order-line snapshot (array, or the older serialised string).
	 *
	 * @param mixed $raw Stored value.
	 * @return array<string, mixed>|null
	 */
	private static function decode_snapshot( $raw ): ?array {
		$value = maybe_unserialize( maybe_unserialize( $raw ) );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Anonymise the customer fields of this booking's order-line snapshots.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int[] The order item IDs that carry a snapshot.
	 */
	private static function write_snapshots( int $booking_id ): array {
		if ( ! function_exists( 'wc_get_order_item_meta' ) ) {
			// WooCommerce is off but its tables may still hold this booking's
			// copy. An API we cannot call is not an absent copy: report every
			// linked line as left, so the identity is not cleared (Codex r4 B5).
			return self::linked_item_ids( $booking_id );
		}

		$with_snapshot = array();
		foreach ( self::linked_item_ids( $booking_id ) as $item_id ) {
			$raw = wc_get_order_item_meta( $item_id, self::SNAPSHOT_KEY, true );
			if ( '' === $raw || null === $raw || false === $raw ) {
				continue;
			}
			$with_snapshot[] = $item_id;
			$snapshot        = self::decode_snapshot( $raw );
			if ( null === $snapshot ) {
				continue; // verify() reports it.
			}
			$clean = self::anonymous_snapshot( $snapshot );
			if ( $clean !== $snapshot || ! is_array( $raw ) ) {
				wc_update_order_item_meta( $item_id, self::SNAPSHOT_KEY, $clean );
			}
		}

		return $with_snapshot;
	}

	/**
	 * The snapshot with its customer fields anonymised.
	 *
	 * @param array<string, mixed> $snapshot Decoded snapshot.
	 * @return array<string, mixed>
	 */
	private static function anonymous_snapshot( array $snapshot ): array {
		$text = (string) wp_privacy_anonymize_data( 'text' );
		foreach ( array( 'customer_name', 'customer_first_name', 'customer_last_name' ) as $key ) {
			if ( array_key_exists( $key, $snapshot ) ) {
				$snapshot[ $key ] = $text;
			}
		}
		if ( array_key_exists( 'customer_email', $snapshot ) ) {
			$snapshot['customer_email'] = (string) wp_privacy_anonymize_data( 'email' );
		}
		if ( array_key_exists( 'customer_phone', $snapshot ) ) {
			$snapshot['customer_phone'] = '';
		}
		if ( array_key_exists( 'customer_user_id', $snapshot ) ) {
			$snapshot['customer_user_id'] = 0;
		}

		return $snapshot;
	}

	/**
	 * Re-read every non-identity target; list the ones still holding data.
	 *
	 * @param int   $booking_id      Booking ID.
	 * @param int   $customer_id     Customer user ID before anonymising.
	 * @param int   $subject_user_id Account of the request's address.
	 * @param int[] $snapshots       Order items carrying a snapshot.
	 * @return string[]
	 */
	private static function verify( int $booking_id, int $customer_id, int $subject_user_id, array $snapshots ): array {
		$left = array();
		$text = (string) wp_privacy_anonymize_data( 'text' );

		foreach ( self::NAME_KEYS as $key ) {
			if ( ! self::every_row_is( $booking_id, $key, $text ) ) {
				$left[] = 'customer_name';
				break;
			}
		}
		foreach ( self::PHONE_KEYS as $key ) {
			if ( metadata_exists( 'post', $booking_id, $key ) ) {
				$left[] = 'customer_phone';
				break;
			}
		}

		$post = get_post( $booking_id );
		foreach ( self::FREE_TEXT_KEYS as $key ) {
			if ( metadata_exists( 'post', $booking_id, $key ) ) {
				$left[] = 'free_text';
				break;
			}
		}
		if ( $post instanceof \WP_Post && '' !== $post->post_content && ! in_array( 'free_text', $left, true ) ) {
			$left[] = 'free_text';
		}
		if ( $post instanceof \WP_Post && self::author_is_the_person( (int) $post->post_author, $customer_id, $subject_user_id ) ) {
			$left[] = 'post_author';
		}

		$cancellation = get_post_meta( $booking_id, '_mhmrentiva_cancellation_data', true );
		if ( is_array( $cancellation ) && array() !== array_intersect( self::CANCELLATION_DROP, array_keys( $cancellation ) ) ) {
			$left[] = 'cancellation';
		}

		foreach ( self::RECEIPT_KEYS as $key ) {
			if ( metadata_exists( 'post', $booking_id, $key ) ) {
				$left[] = 'receipt';
				break;
			}
		}

		$history = get_post_meta( $booking_id, '_mhmrentiva_booking_history', true );
		if ( is_array( $history ) && self::anonymous_history( $history ) !== $history ) {
			$left[] = 'history';
		}
		$logs = get_post_meta( $booking_id, '_mhmrentiva_booking_logs', true );
		if ( is_array( $logs ) && self::anonymous_logs( $logs ) !== $logs ) {
			$left[] = 'logs';
		}

		foreach ( $snapshots as $item_id ) {
			if ( ! function_exists( 'wc_get_order_item_meta' ) ) {
				$left[] = 'order_snapshot:' . $item_id . ' (WooCommerce inactive)';
				continue;
			}
			$snapshot = self::decode_snapshot( wc_get_order_item_meta( $item_id, self::SNAPSHOT_KEY, true ) );
			if ( null === $snapshot || self::anonymous_snapshot( $snapshot ) !== $snapshot ) {
				$left[] = 'order_snapshot:' . $item_id;
			}
		}

		return $left;
	}

	/**
	 * Whether the match keys hold no trace of the person.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	private static function identity_is_anonymous( int $booking_id ): bool {
		$email = (string) wp_privacy_anonymize_data( 'email' );
		foreach ( self::EMAIL_KEYS as $key ) {
			foreach ( (array) get_post_meta( $booking_id, $key, false ) as $value ) {
				if ( $value !== $email ) {
					return false;
				}
			}
		}
		foreach ( (array) get_post_meta( $booking_id, '_mhmrentiva_customer_user_id', false ) as $value ) {
			if ( '0' !== (string) $value ) {
				return false;
			}
		}

		return true;
	}
}
