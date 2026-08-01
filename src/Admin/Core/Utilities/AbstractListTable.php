<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure WP_List_Table is loaded in admin context
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Abstract ListTable Base Class
 *
 * Central base class for WordPress ListTable implementations.
 * Eliminates repeated structure and shared logic.
 *
 * @abstract
 */
abstract class AbstractListTable extends \WP_List_Table {


	protected int $default_per_page = 20;
	protected string $nonce_action  = 'mhmrentiva_listtable_bulk_action';
	protected string $nonce_name    = 'mhmrentiva_listtable_nonce';

	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => $this->get_singular_name(),
				'plural'   => $this->get_plural_name(),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Abstract methods - must be implemented by child classes
	 */
	abstract protected function get_singular_name(): string;
	abstract protected function get_plural_name(): string;
	abstract protected function get_data_query_args(): array;
	abstract protected function get_data_from_results( $results ): array;
	abstract protected function get_total_count(): int;

	/**
	 * Prepare column headers and data
	 */
	public function prepare_items(): void {
		// Process bulk actions before loading data
		// NOTE: Redirects terminate this function, so handle redirects early
		if ( '' !== $this->current_action() ) {
			$this->handle_bulk_actions();
		}

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination
		$per_page     = $this->default_per_page;
		$current_page = $this->get_pagenum();
		$total_items  = $this->get_total_count();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		// Retrieve data
		$this->items = $this->get_paginated_data( $per_page, $current_page );
	}

	/**
	 * Retrieve paginated data
	 */
	protected function get_paginated_data( int $per_page, int $current_page ): array {
		$offset = ( $current_page - 1 ) * $per_page;
		$args   = $this->get_data_query_args();

		// Add pagination
		$args['posts_per_page'] = $per_page;
		$args['offset']         = $offset;

		// Add sorting
		$orderby = self::get_key_param( 'orderby', 'date' );
		$order   = self::get_key_param( 'order', 'desc' );
		$args    = $this->apply_sorting( $args, $orderby, $order );

		// Add search
		$search = self::get_text_param( 's' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		// Add custom filters
		$args = $this->apply_custom_filters( $args );

		$query = new \WP_Query( $args );
		return $this->get_data_from_results( $query->posts );
	}

	/**
	 * Apply sorting
	 */
	protected function apply_sorting( array $args, string $orderby, string $order ): array {
		$sortable_columns = $this->get_sortable_columns();

		if ( isset( $sortable_columns[ $orderby ] ) ) {
			$args['orderby'] = $orderby;
			$args['order']   = strtoupper( $order );
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		return $args;
	}

	/**
	 * Apply custom filters (override in subclasses)
	 */
	protected function apply_custom_filters( array $args ): array {
		return $args;
	}

	/**
	 * Handle bulk actions (public so it can be triggered externally)
	 */
	public function handle_bulk_actions(): void {
		$bulk_key = $this->get_bulk_action_name();

		if ( ! isset( $_POST[ $bulk_key ] ) || ! is_array( $_POST[ $bulk_key ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $this->nonce_name ] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			$this->show_error( __( 'Security check failed.', 'mhm-rentiva' ) );
			return;
		}

		// Read the submitted fields here, in the scope that just verified the nonce,
		// so the check and the reads stay verifiable side by side.
		$action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
			? sanitize_key( wp_unslash( (string) $_POST['action'] ) )
			: '';
		if ( '' === $action ) {
			$action = isset( $_POST['action2'] ) && ! is_array( $_POST['action2'] )
				? sanitize_key( wp_unslash( (string) $_POST['action2'] ) )
				: '';
		}
		$item_ids = array_map( 'intval', map_deep( wp_unslash( $_POST[ $bulk_key ] ), 'sanitize_text_field' ) );

		if ( empty( $item_ids ) ) {
			return;
		}

		$success_count = $this->process_bulk_action( $action, $item_ids );

		if ( $success_count > 0 ) {
			// Clear cache if subclass provides a hook
			if ( method_exists( $this, 'clear_cache_after_bulk_action' ) ) {
				$this->clear_cache_after_bulk_action( $action, $item_ids );
			}

			// Redirect with success message (to avoid resubmission)
			// Get base admin URL - use $_SERVER['REQUEST_URI'] to get current page
			$current_page = self::get_text_param( 'page' );

			if ( empty( $current_page ) ) {
				// Fallback: try to get from REQUEST_URI
				$request_uri = isset( $_SERVER['REQUEST_URI'] )
					? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
					: '';
				if ( preg_match( '/[?&]page=([^&]+)/', $request_uri, $matches ) ) {
					$current_page = $matches[1];
				}
			}

			// Build redirect URL - use admin_url with proper page parameter
			if ( empty( $current_page ) ) {
				// If we can't determine the page, redirect to admin
				$redirect_url = admin_url( 'admin.php' );
			} else {
				$redirect_url = admin_url( 'admin.php?page=' . urlencode( $current_page ) );
			}

			// Remove POST parameters and add success parameters
			$redirect_url = remove_query_arg( array( 'bulk_action', 'bulk_count', 'deleted', 'action', 'action2', $this->get_bulk_action_name(), 'paged' ), $redirect_url );
			$redirect_url = add_query_arg(
				array(
					'bulk_action' => $action,
					'bulk_count'  => $success_count,
				),
				$redirect_url
			);

			// Preserve other GET parameters (filters, search, etc.)
			foreach ( $this->get_preserved_filter_params() as $param ) {
				$param_value = self::get_text_param( $param );
				if ( '' !== $param_value ) {
					$redirect_url = add_query_arg( $param, $param_value, $redirect_url );
				}
			}

			// Redirect (avoid redirect loop by checking if we're already on the target page)
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Process a bulk action (override in subclasses)
	 */
	protected function process_bulk_action( string $action, array $item_ids ): int {
		return 0;
	}

	/**
	 * Get bulk action success message (override in subclasses)
	 */
	protected function get_bulk_success_message( string $action, int $count ): string {
		/* translators: %d placeholder. */
		return sprintf( __( '%d items processed.', 'mhm-rentiva' ), $count );
	}

	/**
	 * Get bulk action name (override in subclasses)
	 */
	protected function get_bulk_action_name(): string {
		return 'item';
	}

	/**
	 * Show error admin notice
	 */
	protected function show_error( string $message ): void {
		add_action(
			'admin_notices',
			function () use ( $message ) {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>' . esc_html( $message ) . '</p>';
				echo '</div>';
			}
		);
	}

	/**
	 * Show success admin notice
	 */
	protected function show_success( string $message ): void {
		add_action(
			'admin_notices',
			function () use ( $message ) {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>' . esc_html( $message ) . '</p>';
				echo '</div>';
			}
		);
	}

	/**
	 * Extra table navigation (override in subclasses)
	 */
	public function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		$this->render_custom_filters();
	}

	/**
	 * Render custom filters (override in subclasses)
	 */
	protected function render_custom_filters(): void {
		// Child classes can provide custom filters here
	}

	/**
	 * No items message (override in subclasses)
	 */
	public function no_items(): void {
		printf(
			/* translators: %s placeholder. */
			esc_html__( 'No %s created yet.', 'mhm-rentiva' ),
			esc_html( $this->get_plural_name() )
		);
	}

	/**
	 * Checkbox column (shared)
	 */
	protected function column_cb( $item ): string {
		$id = $this->get_item_id( $item );
		return sprintf(
			'<input type="checkbox" name="%s[]" value="%s" />',
			esc_attr( $this->get_bulk_action_name() ),
			esc_attr( $id )
		);
	}

	/**
	 * Get item ID (override in subclasses)
	 */
	protected function get_item_id( $item ): string {
		if ( is_object( $item ) ) {
			return (string) ( $item->ID ?? '' );
		}
		return (string) ( $item['id'] ?? '' );
	}

	/**
	 * Shared date column formatter
	 */
	protected function format_date( string $date, string $format = 'd.m.Y' ): string {
		if ( empty( $date ) ) {
			return '-';
		}

		$timestamp = strtotime( $date );
		if ( $timestamp === false ) {
			return $date;
		}

		return gmdate( $format, $timestamp );
	}

	/**
	 * Shared price formatter
	 */
	protected function format_price( float $price, string $currency = 'USD' ): string {
		return number_format( $price, 2, ',', '.' ) . ' ' . $currency;
	}

	/**
	 * Render status badge (shared)
	 */
	protected function render_status_badge( string $status, array $status_labels = array() ): string {
		$label = $status_labels[ $status ] ?? ucfirst( $status );
		$class = 'status-' . sanitize_html_class( $status );

		return sprintf(
			'<span class="status-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Render row actions (shared)
	 */
	protected function render_row_actions( array $actions ): string {
		return $this->row_actions( $actions );
	}

	/**
	 * Create shared “view” link
	 */
	protected function create_view_link( string $page, string $item_id, string $text = '' ): string {
		if ( empty( $text ) ) {
			$text = __( 'View', 'mhm-rentiva' );
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( $page . '&id=' . $item_id ) ),
			esc_html( $text )
		);
	}

	/**
	 * Create shared “edit” link
	 */
	protected function create_edit_link( string $page, string $item_id, string $text = '' ): string {
		if ( empty( $text ) ) {
			$text = __( 'Edit', 'mhm-rentiva' );
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( $page . '&id=' . $item_id ) ),
			esc_html( $text )
		);
	}

	/**
	 * Create shared “delete” link
	 */
	protected function create_delete_link( string $item_id, string $text = '', string $confirm_message = '' ): string {
		if ( empty( $text ) ) {
			$text = __( 'Delete', 'mhm-rentiva' );
		}

		if ( empty( $confirm_message ) ) {
			$confirm_message = __( 'Are you sure you want to delete this item?', 'mhm-rentiva' );
		}

		return sprintf(
			'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
			esc_url( get_delete_post_link( $item_id ) ),
			esc_js( $confirm_message ),
			esc_html( $text )
		);
	}

	/**
	 * Render shared nonce field.
	 */
	protected function render_nonce_field(): void {
		wp_nonce_field( $this->nonce_action, $this->nonce_name );
	}

	/**
	 * Render shared search box.
	 */
	protected function render_search_box(): void {
		$search_term = self::get_text_param( 's' );

		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $this->get_search_input_id() ) . '">' . esc_html__( 'Search:', 'mhm-rentiva' ) . '</label>';
		echo '<input type="search" id="' . esc_attr( $this->get_search_input_id() ) . '" name="s" value="' . esc_attr( $search_term ) . '" />';
		submit_button( esc_html__( 'Search', 'mhm-rentiva' ), '', '', false, array( 'id' => 'search-submit' ) );
		echo '</p>';
	}

	/**
	 * Retrieve search input ID (override as needed).
	 */
	protected function get_search_input_id(): string {
		return $this->get_plural_name() . '-search-input';
	}

	/**
	 * Read a display parameter of the current list screen.
	 *
	 * The sort/search/filter params of a bookmarkable admin URL. They change no
	 * state, and nonce-gating them would break shareable sorted/filtered links,
	 * so WPCS reports NonceVerification.Recommended on the reads below. That
	 * finding is inherent to the shape rather than a missing check; it is
	 * reported to WordPress.org as-is (Görev 17 letter), never annotated away.
	 *
	 * `get_query_var()` is NOT an option here, unlike in BookingColumns /
	 * VehicleColumns / AddonListTable's own readers. Those three live on
	 * `edit.php`, where wp_edit_posts_query() calls wp() and therefore
	 * WP::parse_request() populates the query vars. This base class builds its
	 * redirect as `admin.php?page=...` (see handle_bulk_actions()), and
	 * wp-admin/admin.php never calls wp() -- so on the screen type this class
	 * exists for, every query var would read back empty and sorting, search and
	 * the preserved filters would all silently stop working.
	 *
	 * The is_array() guard keeps `?orderby[]=x` from raising a live
	 * "Array to string conversion" warning on the cast.
	 */
	protected static function get_text_param( string $key, string $default = '' ): string {
		if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
			return $default;
		}

		return sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
	}

	/**
	 * Read a slug-shaped display parameter of the current list screen.
	 */
	protected static function get_key_param( string $key, string $default = '' ): string {
		$value = self::get_text_param( $key, $default );
		return '' === $value ? $default : sanitize_key( $value );
	}

	/**
	 * Filter params to carry across the post-bulk-action redirect.
	 *
	 * Only the core sort/search vars are preserved by default; a subclass that
	 * registers its own filter params should list them here so they survive too.
	 *
	 * @return array<int, string>
	 */
	protected function get_preserved_filter_params(): array {
		return array( 's', 'orderby', 'order' );
	}
}
