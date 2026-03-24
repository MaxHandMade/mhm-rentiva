<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\AbstractListTable;
use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;
use MHMRentiva\Admin\Core\Utilities\ErrorHandler;
use MHMRentiva\Admin\Core\Utilities\TypeValidator;
use MHMRentiva\Admin\Messages\Settings\MessagesSettings;
use MHMRentiva\Admin\Messages\Core\MessageQueryHelper;
use MHMRentiva\Admin\Messages\Core\MessageCache;
use MHMRentiva\Admin\Messages\Core\MessageUrlHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress List Table class for messages
 */
final class MessageListTable extends AbstractListTable {


	public static function register(): void {
		// MessageListTable class is only for use, no registration needed
	}

	public function __construct() {
		parent::__construct();
		$this->nonce_action = 'mhm_messages_bulk_action';
		$this->nonce_name   = 'mhm_messages_nonce';
	}

	protected function get_singular_name(): string {
		return 'message';
	}

	protected function get_plural_name(): string {
		return 'messages';
	}

	protected function get_bulk_action_name(): string {
		return 'message_ids';
	}

	protected function get_data_query_args(): array {
		return array(
			'post_type'      => Message::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $this->default_per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
	}

	protected function get_data_from_results( $results ): array {
		$messages = array();
		foreach ( $results as $result ) {
			// Get customer information - check for NULL
			$customer_name  = ! empty( $result->customer_name ) ? $result->customer_name : '';
			$customer_email = ! empty( $result->customer_email ) ? $result->customer_email : '';

			// If customer_name is empty and post_author exists, get from author
			if ( empty( $customer_name ) && ! empty( $result->post_author ) ) {
				$author = get_userdata( (int) $result->post_author );
				if ( $author ) {
					$customer_name  = $author->display_name ?: $author->user_login;
					$customer_email = $author->user_email;
				}
			}

			// If still empty, find user by email
			if ( empty( $customer_name ) && ! empty( $customer_email ) ) {
				$user = get_user_by( 'email', $customer_email );
				if ( $user ) {
					$customer_name = $user->display_name ?: $user->user_login;
				}
			}

			// Booking ID - NULL check
			$booking_id = isset( $result->booking_id ) && $result->booking_id !== null && $result->booking_id !== ''
				? (int) $result->booking_id
				: 0;

			// Thread ID - NULL check
			$thread_id = isset( $result->thread_id ) && $result->thread_id !== null && $result->thread_id !== ''
				? (int) $result->thread_id
				: 0;

			// Get Category, Status and Priority directly from database (independent of cache)
			// This ensures updates are visible immediately
			$category = get_post_meta( (int) $result->ID, '_mhm_message_category', true );
			$status   = get_post_meta( (int) $result->ID, '_mhm_message_status', true );
			$priority = get_post_meta( (int) $result->ID, '_mhm_message_priority', true );

			// Fallback: If meta doesn't exist, use value from query
			if ( empty( $category ) ) {
				$category = ! empty( $result->category ) ? $result->category : 'general';
			}
			if ( empty( $status ) ) {
				$status = ! empty( $result->status ) ? $result->status : 'pending';
			}
			if ( empty( $priority ) ) {
				$priority = ! empty( $result->priority ) ? $result->priority : 'normal';
			}

			// Parent message ID - NULL check
			$parent_message_id = isset( $result->parent_message_id ) && $result->parent_message_id !== null && $result->parent_message_id !== ''
				? (int) $result->parent_message_id
				: 0;

			// Optimized query data already includes meta information
			$messages[] = array(
				'id'                => (int) $result->ID,
				'subject'           => $result->post_title,
				'customer_name'     => $customer_name ?: __( 'Anonymous', 'mhm-rentiva' ),
				'customer_email'    => $customer_email ?: '',
				'category'          => $category,
				'priority'          => $priority,
				'status'            => $status,
				'date'              => $result->post_date,
				'thread_id'         => $thread_id,
				'booking_id'        => $booking_id,
				'is_read'           => ! empty( $result->is_read ) && $result->is_read !== '0',
				'parent_message_id' => $parent_message_id,
			);
		}
		return $messages;
	}

	/**
	 * Override get_data to use MessageQueryHelper instead of WP_Query
	 */
	protected function get_data(): array {
		$args = $this->get_data_query_args();

		// Pagination
		$args['offset']         = ( $this->get_pagenum() - 1 ) * $this->default_per_page;
		$args['posts_per_page'] = $this->default_per_page;

		// Sorting
		$orderby         = sanitize_sql_orderby( self::get_text_param( 'orderby', 'date' ) );
		$order           = self::get_text_param( 'order', 'DESC' );
		$args['orderby'] = $orderby ?: 'date';
		$args['order']   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// Add search
		$search = self::get_text_param( 's' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		// Add custom filters
		$args = $this->apply_custom_filters( $args );

		// Show only main messages (replies will be shown in thread view)
		$args['parent_only'] = true;

		// Use MessageQueryHelper
		$result = MessageQueryHelper::get_messages_query( $args );

		// Set pagination
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $this->default_per_page,
				'total_pages' => ceil( $result['total'] / $this->default_per_page ),
			)
		);

		return $this->get_data_from_results( $result['messages'] );
	}

	protected function get_total_count(): int {
		// Use optimized query
		$query_args                   = $this->get_data_query_args();
		$query_args['posts_per_page'] = -1; // Get all results
		$query_args['offset']         = 0;
		$query_args                   = $this->apply_custom_filters( $query_args );
		$query_args['parent_only']    = true; // Count only main messages

		return MessageQueryHelper::get_messages_query( $query_args )['total'];
	}

	/**
	 * Override get_paginated_data to use MessageQueryHelper instead of WP_Query
	 */
	protected function get_paginated_data( int $per_page, int $current_page ): array {
		$offset = ( $current_page - 1 ) * $per_page;
		$args   = $this->get_data_query_args();

		// Add pagination
		$args['posts_per_page'] = $per_page;
		$args['offset']         = $offset;

		// Sorting
		$orderby         = sanitize_sql_orderby( self::get_text_param( 'orderby', 'date' ) );
		$order           = self::get_text_param( 'order', 'DESC' );
		$args['orderby'] = $orderby ?: 'date';
		$args['order']   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// Add search
		$search = self::get_text_param( 's' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		// Add custom filters
		$args = $this->apply_custom_filters( $args );

		// Show only main messages (replies will be shown in thread view)
		$args['parent_only'] = true;

		// Use MessageQueryHelper
		$result = MessageQueryHelper::get_messages_query( $args );

		return $this->get_data_from_results( $result['messages'] );
	}

	public function get_columns(): array {
		return array(
			'cb'       => '<input type="checkbox" />',
			'id'       => __( 'ID', 'mhm-rentiva' ),
			'subject'  => __( 'Subject', 'mhm-rentiva' ),
			'customer' => __( 'Customer', 'mhm-rentiva' ),
			'category' => __( 'Category', 'mhm-rentiva' ),
			'priority' => __( 'Priority', 'mhm-rentiva' ),
			'status'   => __( 'Status', 'mhm-rentiva' ),
			'date'     => __( 'Date', 'mhm-rentiva' ),
			'thread'   => __( 'Thread', 'mhm-rentiva' ),
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'id'       => array( 'id', true ),
			'subject'  => array( 'subject', false ),
			'customer' => array( 'customer', false ),
			'category' => array( 'category', false ),
			'status'   => array( 'status', false ),
			'date'     => array( 'date', true ),
		);
	}

	/**
	 * Render ID column
	 * Show ID only in main messages (messages without parent message)
	 */
	protected function column_id( $item ): string {
		// Parent message check - check from item first (faster)
		$parent_message_id = isset( $item['parent_message_id'] ) ? (int) $item['parent_message_id'] : 0;

		// If not in item, check from meta
		if ( $parent_message_id === 0 ) {
			$message_meta      = Message::get_message_meta( $item['id'] );
			$parent_message_id = $message_meta['parent_message_id'] ?? 0;
		}

		// If parent message exists (this is a reply), don't show ID
		if ( $parent_message_id > 0 ) {
			return '<span style="color: #999;">—</span>'; // Dash or empty
		}

		// Show ID for main message
		return sprintf(
			'<strong>#%d</strong>',
			(int) $item['id']
		);
	}

	protected function get_bulk_actions(): array {
		return array(
			'mark_read'     => __( 'Mark as Read', 'mhm-rentiva' ),
			'mark_unread'   => __( 'Mark as Unread', 'mhm-rentiva' ),
			'change_status' => __( 'Change Status', 'mhm-rentiva' ),
			'delete'        => __( 'Delete', 'mhm-rentiva' ),
		);
	}

	/**
	 * Extra table navigation - filters and bulk action helpers
	 */
	public function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		// Render custom filters
		$this->render_custom_filters();
	}

	protected function column_cb( $item ): string {
		$id = TypeValidator::validateString( $item['id'] ?? '' );
		return sprintf( '<input type="checkbox" name="message_ids[]" value="%s" />', esc_attr( $id ) );
	}

	protected function column_subject( $item ): string {
		$actions = array(
			'view'  => sprintf(
				'<a href="%s">%s</a>',
				MessageUrlHelper::get_message_view_url( (int) $item['id'] ),
				__( 'View', 'mhm-rentiva' )
			),
			'edit'  => sprintf(
				'<a href="%s">%s</a>',
				MessageUrlHelper::get_message_edit_url( (int) $item['id'] ),
				__( 'Edit', 'mhm-rentiva' )
			),
			'reply' => sprintf(
				'<a href="%s">%s</a>',
				MessageUrlHelper::get_message_reply_url( (int) $item['id'] ),
				__( 'Reply', 'mhm-rentiva' )
			),
		);

		$unread_indicator = ! $item['is_read'] ? '<span class="unread-indicator">●</span> ' : '';

		// Status badge - "New" badge for pending messages
		$status_badge = '';
		if ( $item['status'] === 'pending' ) {
			$status_badge = '<span class="status-badge-new" style="background: #ff9800; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 8px; text-transform: uppercase;">' . __( 'New', 'mhm-rentiva' ) . '</span>';
		}

		// Parent message check - if parent exists, add indent
		$message_meta      = Message::get_message_meta( $item['id'] );
		$parent_message_id = $message_meta['parent_message_id'] ?? 0;
		$indent            = '';

		if ( $parent_message_id > 0 ) {
			// Calculate parent message depth (maximum 3 levels)
			$depth   = $this->get_message_depth( $item['id'] );
			$indent  = str_repeat( '&nbsp;&nbsp;&nbsp;&nbsp;', min( $depth, 3 ) );
			$indent .= '<span class="reply-indicator" style="color: #2271b1;">↳</span> ';
		}

		return sprintf(
			'<strong>%s%s<a href="%s">%s</a>%s</strong>%s',
			$unread_indicator,
			$indent,
			MessageUrlHelper::get_message_view_url( (int) $item['id'] ),
			esc_html( $item['subject'] ),
			$status_badge,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Calculate message depth within thread
	 */
	private function get_message_depth( int $message_id, int $current_depth = 0 ): int {
		if ( $current_depth >= 5 ) { // Maximum 5 levels
			return $current_depth;
		}

		$meta      = Message::get_message_meta( $message_id );
		$parent_id = $meta['parent_message_id'] ?? 0;

		if ( $parent_id > 0 ) {
			return $this->get_message_depth( $parent_id, $current_depth + 1 );
		}

		return $current_depth;
	}

	protected function column_customer( $item ): string {
		// Get customer information - first from item, then from meta
		$customer_name  = isset( $item['customer_name'] ) ? $item['customer_name'] : '';
		$customer_email = isset( $item['customer_email'] ) ? $item['customer_email'] : '';

		// If empty, get directly from message meta
		if ( empty( $customer_name ) || $customer_name === __( 'Anonymous', 'mhm-rentiva' ) ) {
			$message_meta   = Message::get_message_meta( $item['id'] );
			$customer_name  = isset( $message_meta['customer_name'] ) ? $message_meta['customer_name'] : '';
			$customer_email = isset( $message_meta['customer_email'] ) ? $message_meta['customer_email'] : '';
		}

		// If customer_name is empty or Anonymous and email exists, get from user
		if ( ( empty( $customer_name ) || $customer_name === __( 'Anonymous', 'mhm-rentiva' ) ) && ! empty( $customer_email ) ) {
			$user = get_user_by( 'email', $customer_email );
			if ( $user ) {
				$customer_name = $user->display_name ?: $user->user_login;
			}
		}

		// If post_author exists and still empty, get from author
		if ( empty( $customer_name ) || $customer_name === __( 'Anonymous', 'mhm-rentiva' ) ) {
			$message = get_post( $item['id'] );
			if ( $message && ! empty( $message->post_author ) ) {
				$author = get_userdata( (int) $message->post_author );
				if ( $author ) {
					$customer_name  = $author->display_name ?: $author->user_login;
					$customer_email = $author->user_email;
				}
			}
		}

		// If still empty, show "Anonymous"
		if ( empty( $customer_name ) ) {
			$customer_name = __( 'Anonymous', 'mhm-rentiva' );
		}

		$customer_info = sprintf(
			'<strong>%s</strong><br><small>%s</small>',
			esc_html( $customer_name ),
			esc_html( $customer_email ?: __( 'No email', 'mhm-rentiva' ) )
		);

		// Check Booking ID - first from item, then from meta
		$booking_id = isset( $item['booking_id'] ) ? (int) $item['booking_id'] : 0;
		if ( $booking_id === 0 ) {
			$message_meta = Message::get_message_meta( $item['id'] );
			$booking_id   = isset( $message_meta['booking_id'] ) ? (int) $message_meta['booking_id'] : 0;
		}

		if ( $booking_id > 0 ) {
			$booking_title = get_the_title( $booking_id );
			if ( $booking_title && $booking_title !== __( 'Auto Draft', 'mhm-rentiva' ) && $booking_title !== 'Auto Draft' ) {
				$customer_info .= sprintf(
					'<br><small><a href="%s">%s</a></small>',
					MessageUrlHelper::get_booking_edit_url( $booking_id ),
					__( 'Booking: ', 'mhm-rentiva' ) . esc_html( $booking_title )
				);
			}
		}

		return $customer_info;
	}

	protected function column_category( $item ): string {
		$categories = MessagesSettings::get_categories();
		return esc_html( $categories[ $item['category'] ] ?? $item['category'] );
	}

	protected function column_priority( $item ): string {
		$priorities     = MessagesSettings::get_priorities();
		$priority       = $item['priority'] ?? 'normal';
		$priority_label = $priorities[ $priority ] ?? __( 'Normal', 'mhm-rentiva' );

		$priority_classes = array(
			'normal' => 'priority-normal',
			'high'   => 'priority-high',
			'urgent' => 'priority-urgent',
		);

		$class = $priority_classes[ $priority ] ?? 'priority-normal';

		return sprintf(
			'<span class="priority-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $priority_label )
		);
	}

	protected function column_status( $item ): string {
		$statuses     = MessagesSettings::get_statuses();
		$status_label = $statuses[ $item['status'] ] ?? $item['status'];

		$status_class = 'status-' . sanitize_html_class( $item['status'] );

		return sprintf(
			'<span class="message-status %s">%s</span>',
			$status_class,
			esc_html( $status_label )
		);
	}

	protected function column_date( $item ): string {
		$date       = strtotime( $item['date'] );
		$human_date = human_time_diff( $date, current_time( 'timestamp' ) );

		return sprintf(
			'<time datetime="%s" title="%s">%s ' . esc_html__( 'ago', 'mhm-rentiva' ) . '</time>',
			gmdate( 'c', $date ),
			gmdate( 'd.m.Y H:i', $date ),
			$human_date
		);
	}

	protected function column_thread( $item ): string {
		// Check booking information first - get booking_id directly from message meta
		$booking_id = isset( $item['booking_id'] ) ? (int) $item['booking_id'] : 0;

		// If booking_id is not in item, get directly from message meta
		if ( $booking_id === 0 ) {
			$message_meta = Message::get_message_meta( $item['id'] );
			$booking_id   = isset( $message_meta['booking_id'] ) ? (int) $message_meta['booking_id'] : 0;
		}

		if ( $booking_id > 0 ) {
			$booking_title = get_the_title( $booking_id );

			// If booking title is valid, show it
			if ( $booking_title && $booking_title !== __( 'Auto Draft', 'mhm-rentiva' ) && $booking_title !== 'Auto Draft' ) {
				$booking_link = sprintf(
					'<a href="%s" title="%s">%s #%d</a>',
					MessageUrlHelper::get_booking_edit_url( $booking_id ),
					__( 'View Booking', 'mhm-rentiva' ),
					__( 'Booking', 'mhm-rentiva' ),
					$booking_id
				);

				// Also add thread information (if exists)
				$thread_id = isset( $item['thread_id'] ) ? (int) $item['thread_id'] : 0;
				if ( $thread_id > 0 ) {
					$thread_messages = MessageQueryHelper::get_thread_messages( $thread_id );
					$thread_count    = count( $thread_messages );

					if ( $thread_count > 1 ) {
						return sprintf(
							'%s<br><small><a href="%s">%d %s</a></small>',
							$booking_link,
							MessageUrlHelper::get_messages_list_url_with_thread( $thread_id ),
							$thread_count,
							__( 'messages', 'mhm-rentiva' )
						);
					}
				}

				return $booking_link;
			}
		}

		// If no booking, show thread information
		$thread_id = isset( $item['thread_id'] ) ? (int) $item['thread_id'] : 0;
		if ( $thread_id === 0 ) {
			$message_meta = Message::get_message_meta( $item['id'] );
			$thread_id    = isset( $message_meta['thread_id'] ) ? (int) $message_meta['thread_id'] : 0;
		}

		if ( $thread_id > 0 ) {
			$thread_messages = MessageQueryHelper::get_thread_messages( $thread_id );
			$thread_count    = count( $thread_messages );

			if ( $thread_count > 1 ) {
				return sprintf(
					'<a href="%s" title="%s">%d %s</a>',
					MessageUrlHelper::get_messages_list_url_with_thread( $thread_id ),
					__( 'View Thread', 'mhm-rentiva' ),
					$thread_count,
					__( 'messages', 'mhm-rentiva' )
				);
			}
		}

		return __( 'Single message', 'mhm-rentiva' );
	}

	protected function apply_custom_filters( array $args ): array {
		// Status filter - use status_filter key for MessageQueryHelper
		$status_filter = self::get_key_param( 'status_filter' );
		if ( $status_filter && in_array( $status_filter, array_keys( MessagesSettings::get_statuses() ), true ) ) {
			$args['status_filter'] = $status_filter;
		}

		// Category filter - use category_filter key for MessageQueryHelper
		$category_filter = self::get_key_param( 'category_filter' );
		if ( $category_filter && in_array( $category_filter, array_keys( MessagesSettings::get_categories() ), true ) ) {
			$args['category_filter'] = $category_filter;
		}

		// Thread ID filter (if exists)
		$thread_id = absint( self::get_text_param( 'thread_id', '0' ) );
		if ( $thread_id > 0 ) {
			$args['thread_id'] = $thread_id;
		}

		// Unread only filter (if exists)
		$unread_only = self::get_text_param( 'unread_only' ) === '1';
		if ( $unread_only ) {
			$args['unread_only'] = true;
		}

		return $args;
	}


	protected function render_custom_filters(): void {
		$status_filter   = self::get_key_param( 'status_filter' );
		$category_filter = self::get_key_param( 'category_filter' );

		?>
		<div class="alignleft actions">
			<select name="status_filter" id="status_filter">
				<option value=""><?php esc_html_e( 'All Statuses', 'mhm-rentiva' ); ?></option>
				<?php foreach ( MessagesSettings::get_statuses() as $status_key => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status_filter, $status_key ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="category_filter" id="category_filter">
				<option value=""><?php esc_html_e( 'All Categories', 'mhm-rentiva' ); ?></option>
				<?php foreach ( MessagesSettings::get_categories() as $category_key => $category_label ) : ?>
					<option value="<?php echo esc_attr( $category_key ); ?>" <?php selected( $category_filter, $category_key ); ?>>
						<?php echo esc_html( $category_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mhm-rentiva' ); ?></button>
		</div>
		<?php
	}

	protected function process_bulk_action( string $action, array $item_ids ): int {
		$success_count = 0;

		switch ( $action ) {
			case 'mark_read':
				foreach ( $item_ids as $message_id ) {
					if ( Message::mark_as_read( $message_id ) ) {
						++$success_count;
					}
				}
				break;

			case 'mark_unread':
				foreach ( $item_ids as $message_id ) {
					delete_post_meta( $message_id, '_mhm_is_read' );
					delete_post_meta( $message_id, '_mhm_read_at' );
					++$success_count;
				}
				break;

			case 'change_status':
				$new_status = self::post_key_param( 'bulk_status' );
				if ( in_array( $new_status, array_keys( MessagesSettings::get_statuses() ), true ) ) {
					foreach ( $item_ids as $message_id ) {
						if ( Message::update_message_status( $message_id, $new_status ) ) {
							++$success_count;
						}
					}
				}
				break;

			case 'delete':
				foreach ( $item_ids as $message_id ) {
					if ( wp_delete_post( $message_id, true ) ) {
						++$success_count;
					}
				}
				break;
		}

		return $success_count;
	}

	protected function get_bulk_success_message( string $action, int $count ): string {
		switch ( $action ) {
			case 'mark_read':
				/* translators: %d placeholder. */
				return sprintf( __( '%d messages marked as read.', 'mhm-rentiva' ), $count );
			case 'mark_unread':
				/* translators: %d placeholder. */
				return sprintf( __( '%d messages marked as unread.', 'mhm-rentiva' ), $count );
			case 'change_status':
				/* translators: %d placeholder. */
				return sprintf( __( '%d messages status updated.', 'mhm-rentiva' ), $count );
			case 'delete':
				/* translators: %d placeholder. */
				return sprintf( __( '%d messages deleted.', 'mhm-rentiva' ), $count );
			default:
				return parent::get_bulk_success_message( $action, $count );
		}
	}

	/**
	 * Clear cache after bulk action
	 */
	protected function clear_cache_after_bulk_action( string $action, array $item_ids ): void {
		// Clear cache
		MessageCache::flush();

		// Clear cache for each message
		foreach ( $item_ids as $message_id ) {
			$email = get_post_meta( $message_id, '_mhm_customer_email', true );
			if ( $email ) {
				MessageCache::clear_message_cache( $message_id, $email );
			}
		}
	}
}
