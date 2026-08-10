<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Customers Page Class.
 *
 * @package MHMRentiva\Admin\Customers
 */


// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.





/**
 * Handles the display and processing of the customers management page.
 */
final class CustomersPage {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;


	/**
	 * Safe sanitize text field that handles null values.
	 *
	 * @param mixed $value Input value.
	 * @return string Sanitized string.
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if (null === $value || '' === $value) {
			return '';
		}
		return sanitize_text_field( (string) $value);
	}

	/**
	 * Register actions and hooks.
	 */
	public static function register(): void
	{
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		// REST routes are registered in Plugin.php (context-agnostic path) — not here.
		add_action( 'admin_post_mhmrentiva_export_customers', array( \MHMRentiva\Admin\Customers\Export\CustomerExporter::class, 'handle' ) );
		// "View Bookings" lands on the bookings list filtered by customer; this
		// notice is the way back to the customer profile the visitor came from.
		add_action( 'admin_notices', array( self::class, 'render_bookings_backlink' ) );
		// The four core-table indexes this screen used to rely on
		// (idx_postmeta_customer_email, idx_postmeta_booking_price,
		// idx_usermeta_customer_phone, idx_posts_booking_date) were retired by
		// RetiredIndexes -- see DatabaseMigrator's class docblock. This screen
		// runs unindexed queries against wp_postmeta/wp_usermeta/wp_posts now,
		// the same as before those indexes ever existed.
		AddCustomerPage::register();
	}

	/**
	 * Render the customers page.
	 */
	public function render(): void
	{
		// Matches the submenu registration and the /customers REST routes: this screen
		// renders customer PII and spend history, so it is gated on `edit_users` rather
		// than the generic manage_options.
		if (! current_user_can('edit_users')) {
			return;
		}

		// Check action parameters.
		$action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
		if ('' !== $action) {
			switch ($action) {
				case 'add-customer':
					AddCustomerPage::render();
					return;
				case 'view':
					$this->render_customer_view();
					return;
				case 'edit':
					$this->render_customer_edit();
					return;
			}
		}

		echo '<div class="wrap mhm-rentiva-wrap customers-page">';

		$this->render_admin_header(
			(string) get_admin_page_title(),
			array(
				array(
					'type' => 'documentation',
					'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				),
			)
		);

		echo '<div id="mhm-customers-root"></div>';
		echo '</div>';
	}


	/**
	 * Load CSS and JS files (via hook)
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void
	{
		if ( strpos( $hook, 'mhm-rentiva-customers' ) === false ) {
			return;
		}

		\MHMRentiva\Admin\Core\AssetManager::enqueue_react_page( 'customers' );

		wp_enqueue_style(
			'mhm-rentiva-customers',
			MHMRENTIVA_PLUGIN_URL . 'build/admin/customers.css',
			array(),
			\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'build/admin/customers.css' )
		);

		$stats    = CustomersOptimizer::get_customer_stats_optimized();
		$currency = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();

		wp_localize_script(
			'mhm-rentiva-react-customers',
			'mhmRentivaCustomers',
			array(
				'stats'            => array(
					'total'          => $stats['total']         ?? 0,
					'active'         => $stats['active']        ?? 0,
					'new_this_month' => $stats['new']           ?? 0,
					'monthly_avg'    => $stats['average']       ?? 0,
					'new_trend'      => $stats['average_trend'] ?? '',
					// Redesign KPIs: activity inside 90 days + lifetime spend per customer.
					'active_90d'     => $stats['active_90d']    ?? 0,
					'avg_spend'      => $stats['avg_spend']     ?? 0,
				),
				'currency'         => $currency,
				'admin_url'        => admin_url(),
				'export_nonce'     => wp_create_nonce( 'mhmrentiva_export_customers' ),
				'add_customer_url' => admin_url( 'admin.php?page=mhm-rentiva-customers&action=add-customer' ),
			)
		);
	}



	/**
	 * On the bookings list filtered by a customer ("View Bookings"), show a
	 * link back to the profile the visitor came from.
	 */
	public static function render_bookings_backlink(): void
	{
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-mhmrentiva_booking' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation aid on a capability-gated admin screen.
		$customer_email = sanitize_email( wp_unslash( $_GET['customer_email'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation aid on a capability-gated admin screen.
		$customer_id = absint( wp_unslash( $_GET['customer_id'] ?? 0 ) );
		if ( '' === $customer_email ) {
			return;
		}

		$user = $customer_id > 0 ? get_user_by( 'id', $customer_id ) : get_user_by( 'email', $customer_email );
		if ( ! $user ) {
			return;
		}

		$profile_url = admin_url( 'admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . (int) $user->ID );
		/* translators: 1: customer display name, 2: profile URL */
		$message = __( 'Showing bookings for <strong>%1$s</strong> only. <a href="%2$s">← Back to customer profile</a>', 'mhm-rentiva' );
		echo '<div class="notice notice-info"><p>';
		printf(
			wp_kses(
				$message,
				array(
					'strong' => array(),
					'a'      => array( 'href' => array() ),
				)
			),
			esc_html( $user->display_name ),
			esc_url( $profile_url )
		);
		echo '</p></div>';
	}

	/**
	 * Avatar colour pairs; mirrors AVATAR_PALETTE in
	 * src-react/admin/customers/components/CustomerTable.jsx so the detail
	 * page shows the same avatar the list rendered for this customer.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return array{0: string, 1: string} Background and foreground colours.
	 */
	private static function avatar_colors( int $customer_id ): array
	{
		$palette = array(
			array( '#e5f0fb', '#135e96' ),
			array( '#e4f6e9', '#0a6b1e' ),
			array( '#fdf0e4', '#a15b1e' ),
			array( '#f3e8fb', '#6b2fa0' ),
			array( '#fbe9f1', '#9e2b63' ),
			array( '#e9f6f6', '#0f6b6b' ),
			array( '#eef2f7', '#41505f' ),
			array( '#fcf3d6', '#8a6d1b' ),
		);
		return $palette[ abs( $customer_id ) % count( $palette ) ];
	}

	/**
	 * First letters of up to two words, the same shape the React table shows.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private static function avatar_initials( string $name ): string
	{
		$words    = preg_split( '/\s+/', trim( $name ) ) ?: array();
		$initials = '';
		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$initials .= mb_substr( $word, 0, 1 );
		}
		return mb_strtoupper( $initials );
	}

	/**
	 * Translated label for a derived customer status tag.
	 *
	 * @param string $status vip|new|active|none.
	 * @return string Empty for none.
	 */
	private static function status_label( string $status ): string
	{
		switch ( $status ) {
			case 'vip':
				return __( 'VIP', 'mhm-rentiva' );
			case 'new':
				return __( 'New', 'mhm-rentiva' );
			case 'active':
				return __( 'Active', 'mhm-rentiva' );
		}
		return '';
	}

	/**
	 * Render customer view page
	 *
	 * @return void
	 */
	private function render_customer_view(): void
	{
		if (! isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
			wp_die(esc_html__('Invalid customer ID.', 'mhm-rentiva'));
		}

		$customer_id = intval(wp_unslash($_GET['customer_id'] ?? 0));
		$detail      = CustomersOptimizer::get_customer_details_optimized($customer_id);

		if (! $detail) {
			wp_die(esc_html__('Customer not found.', 'mhm-rentiva'));
		}

		$buttons = array(
			array(
				'text'  => esc_html__('Customers List', 'mhm-rentiva'),
				'url'   => admin_url('admin.php?page=mhm-rentiva-customers'),
				'class' => 'button',
			),
		);

		list( $av_bg, $av_color ) = self::avatar_colors( $customer_id );
		$status_label             = self::status_label( (string) ( $detail['status'] ?? '' ) );

		echo '<div class="wrap mhm-customer-view-wrap">';
		$this->render_admin_header(esc_html__('Customer Details', 'mhm-rentiva'), $buttons);

		echo '<div class="rv-cust-scope"><div class="rv-cust rv-cust-detail-page">';
		echo '<div class="rv-cust-panel__card">';

		// Header: avatar + name + registered/status.
		echo '<div class="rv-cust-panel__head">';
		echo '<span class="rv-cust-avatar is-lg" style="background:' . esc_attr( $av_bg ) . ';color:' . esc_attr( $av_color ) . '">' . esc_html( self::avatar_initials( (string) $detail['name'] ) ) . '</span>';
		echo '<div class="rv-cust-panel__title">';
		echo '<div class="rv-cust-panel__name">' . esc_html( $detail['name'] ) . '</div>';
		echo '<div class="rv-cust-panel__meta">' . esc_html( $detail['registered'] . ( '' !== $status_label ? ' · ' . $status_label : '' ) ) . '</div>';
		echo '</div>';
		if ( '' !== $status_label ) {
			echo '<span class="rv-cust-tag is-' . esc_attr( (string) $detail['status'] ) . '">' . esc_html( $status_label ) . '</span>';
		}
		echo '</div>';

		// Contact lines.
		echo '<div class="rv-cust-panel__contact">';
		echo '<div class="rv-cust-panel__line"><span>' . esc_html__( 'Email', 'mhm-rentiva' ) . '</span><span><a href="mailto:' . esc_attr( $detail['email'] ) . '">' . esc_html( $detail['email'] ) . '</a></span></div>';
		echo '<div class="rv-cust-panel__line"><span>' . esc_html__( 'Phone', 'mhm-rentiva' ) . '</span><span>' . esc_html( $detail['phone'] ) . '</span></div>';
		echo '<div class="rv-cust-panel__line"><span>' . esc_html__( 'Address', 'mhm-rentiva' ) . '</span><span>' . esc_html( $detail['address'] ) . '</span></div>';
		echo '<div class="rv-cust-panel__line"><span>' . esc_html__( 'First Booking', 'mhm-rentiva' ) . '</span><span>' . esc_html( $detail['first_booking'] ) . '</span></div>';
		echo '<div class="rv-cust-panel__line"><span>' . esc_html__( 'Last Booking', 'mhm-rentiva' ) . '</span><span>' . esc_html( $detail['last_booking'] ) . '</span></div>';
		echo '</div>';

		// Stat grid.
		echo '<div class="rv-cust-panel__stats">';
		echo '<div><strong>' . esc_html( (string) $detail['booking_count'] ) . '</strong><span>' . esc_html__( 'bookings', 'mhm-rentiva' ) . '</span></div>';
		echo '<div><strong>' . esc_html( $detail['currency'] . $detail['total_spent'] ) . '</strong><span>' . esc_html__( 'total', 'mhm-rentiva' ) . '</span></div>';
		echo '<div><strong>' . esc_html( (string) ( $detail['favorites_count'] ?? 0 ) ) . '</strong><span>' . esc_html__( 'favorites', 'mhm-rentiva' ) . '</span></div>';
		echo '</div>';

		// Recent bookings, 5 per page. Page 1 comes from the cached detail
		// payload; deeper pages query the same bounded lookup with an offset.
		$per_page      = 5;
		$total_pages   = max( 1, (int) ceil( ( (int) $detail['booking_count'] ) / $per_page ) );
		$bookings_page = min( $total_pages, max( 1, absint( wp_unslash( $_GET['bookings_page'] ?? 1 ) ) ) );
		$recent        = 1 === $bookings_page
			? (array) ( $detail['recent_bookings'] ?? array() )
			: CustomersOptimizer::get_recent_bookings( (string) $detail['email'], $per_page, ( $bookings_page - 1 ) * $per_page );

		echo '<div class="rv-cust-panel__body">';
		echo '<div class="rv-cust-panel__section-title">' . esc_html__( 'Recent bookings', 'mhm-rentiva' ) . '</div>';
		if ( empty( $recent ) ) {
			echo '<p class="rv-cust-panel__none">' . esc_html__( 'No bookings yet.', 'mhm-rentiva' ) . '</p>';
		} else {
			foreach ( $recent as $booking ) {
				$booking_url = admin_url( 'post.php?post=' . (int) $booking['id'] . '&action=edit' );
				$reference   = (string) ( $booking['reference'] ?? ( '#' . $booking['id'] ) );
				echo '<div class="rv-cust-panel__booking">';
				echo '<div><div class="rv-cust-panel__booking-vehicle"><a href="' . esc_url( $booking_url ) . '">' . esc_html( $reference ) . '</a> · ' . esc_html( $booking['vehicle'] ) . '</div>';
				echo '<div class="rv-cust-panel__booking-date">' . esc_html( $booking['date'] ) . '</div></div>';
				echo '<span class="rv-cust-panel__booking-amount">' . esc_html( $detail['currency'] . $booking['amount'] ) . '</span>';
				echo '</div>';
			}
		}
		if ( $total_pages > 1 ) {
			$base_url = admin_url( 'admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id );
			echo '<div class="rv-cust-panel__paging">';
			if ( $bookings_page > 1 ) {
				echo '<a class="rv-cust-btn" href="' . esc_url( $base_url . '&bookings_page=' . ( $bookings_page - 1 ) ) . '">‹ ' . esc_html__( 'Previous', 'mhm-rentiva' ) . '</a>';
			}
			/* translators: 1: current page, 2: total pages */
			echo '<span class="rv-cust-panel__paging-info">' . esc_html( sprintf( __( '%1$d / %2$d', 'mhm-rentiva' ), $bookings_page, $total_pages ) ) . '</span>';
			if ( $bookings_page < $total_pages ) {
				echo '<a class="rv-cust-btn" href="' . esc_url( $base_url . '&bookings_page=' . ( $bookings_page + 1 ) ) . '">' . esc_html__( 'Next', 'mhm-rentiva' ) . ' ›</a>';
			}
			echo '</div>';
		}
		echo '<div class="rv-cust-panel__actions">';
		echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=edit&customer_id=' . $customer_id)) . '" class="rv-cust-btn is-primary">' . esc_html__('Edit', 'mhm-rentiva') . '</a>';
		echo '<a href="' . esc_url(admin_url('edit.php?post_type=mhmrentiva_booking&customer_email=' . $detail['email'] . '&customer_id=' . $customer_id)) . '" class="rv-cust-btn">' . esc_html__('View Bookings', 'mhm-rentiva') . '</a>';
		echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers')) . '" class="rv-cust-btn">' . esc_html__('Go Back', 'mhm-rentiva') . '</a>';
		echo '</div>';
		echo '</div>';

		echo '</div></div></div>';
		echo '</div>';
	}

	/**
	 * Render customer edit page
	 *
	 * @return void
	 */
	private function render_customer_edit(): void
	{
		// Editing a customer updates a real WordPress user account, so this is
		// gated on edit_users, not manage_options.
		if (! current_user_can('edit_users')) {
			wp_die(esc_html__('You do not have permission to edit customers.', 'mhm-rentiva'));
		}

		if (! isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
			wp_die(esc_html__('Invalid customer ID.', 'mhm-rentiva'));
		}

		$customer_id = intval(wp_unslash($_GET['customer_id'] ?? 0));
		$customer    = get_user_by('id', $customer_id);

		if (! $customer) {
			wp_die(esc_html__('Customer not found.', 'mhm-rentiva'));
		}

		// Form processing. The nonce field is the submission signal: only this
		// page's own form carries it, so its validity is the whole test.
		$nonce = sanitize_text_field(wp_unslash($_POST['mhmrentiva_edit_customer_nonce'] ?? ''));
		if (wp_verify_nonce($nonce, 'mhmrentiva_edit_customer')) {
			$customer_name    = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash( (string) $_POST['customer_name'])) : '';
			$customer_email   = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
			$customer_phone   = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash( (string) $_POST['customer_phone'])) : '';
			$customer_address = sanitize_textarea_field(wp_unslash($_POST['customer_address'] ?? ''));

			// Phone is required alongside name and e-mail: bookings need a way to
			// reach the customer, and the HTML `required` attribute alone is not
			// enforcement.
			if (empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
				echo '<div class="notice notice-error"><p>' . esc_html__('Customer name, email and phone fields are required.', 'mhm-rentiva') . '</p></div>';
			} else {
				// Update user information
				wp_update_user(
					array(
						'ID'           => $customer_id,
						'display_name' => $customer_name,
						'user_email'   => $customer_email,
						'first_name'   => $customer_name,
					)
				);

				// Update meta information
				update_user_meta($customer_id, 'mhmrentiva_phone', $customer_phone);
				update_user_meta($customer_id, 'mhmrentiva_address', $customer_address);

				// Clear the WHOLE customers cache, not just this customer's
				// details: the list payload carries phone/name per row, and a
				// per-customer clear left the list serving the old values for
				// the rest of the TTL.
				\MHMRentiva\Admin\Customers\CustomersOptimizer::clear_cache();

				$view_url = admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id);
				echo '<div class="notice notice-success"><p>' . esc_html__('Customer information updated successfully.', 'mhm-rentiva') . ' <a href="' . esc_url($view_url) . '">' . esc_html__('← Back to customer details', 'mhm-rentiva') . '</a></p></div>';

				// Get updated information
				$customer = get_user_by('id', $customer_id);
			}
		}

		$buttons = array(
			array(
				'text'  => esc_html__('View', 'mhm-rentiva'),
				'url'   => admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id),
				'class' => 'button',
			),
		);

		echo '<div class="wrap mhm-customer-edit-wrap">';
		$this->render_admin_header(esc_html__('Edit Customer', 'mhm-rentiva'), $buttons);

		echo '<div class="rv-cust-scope"><div class="rv-cust rv-cust-detail-page">';
		echo '<form method="post" action="" class="rv-cust-panel__card rv-cust-form">';
		wp_nonce_field('mhmrentiva_edit_customer', 'mhmrentiva_edit_customer_nonce');

		echo '<div class="rv-cust-form__field">';
		echo '<label for="customer_name">' . esc_html__('Customer Name', 'mhm-rentiva') . ' <span class="rv-cust-req">' . esc_html__('Required', 'mhm-rentiva') . '</span></label>';
		echo '<input name="customer_name" type="text" id="customer_name" value="' . esc_attr($customer->display_name) . '" required />';
		echo '</div>';

		echo '<div class="rv-cust-form__field">';
		echo '<label for="customer_email">' . esc_html__('Email', 'mhm-rentiva') . ' <span class="rv-cust-req">' . esc_html__('Required', 'mhm-rentiva') . '</span></label>';
		echo '<input name="customer_email" type="email" id="customer_email" value="' . esc_attr($customer->user_email) . '" required />';
		echo '</div>';

		echo '<div class="rv-cust-form__field">';
		echo '<label for="customer_phone">' . esc_html__('Phone', 'mhm-rentiva') . ' <span class="rv-cust-req">' . esc_html__('Required', 'mhm-rentiva') . '</span></label>';
		echo '<input name="customer_phone" type="tel" id="customer_phone" value="' . esc_attr(get_user_meta($customer_id, 'mhmrentiva_phone', true)) . '" required />';
		echo '</div>';

		echo '<div class="rv-cust-form__field">';
		echo '<label for="customer_address">' . esc_html__('Address', 'mhm-rentiva') . '</label>';
		echo '<textarea name="customer_address" id="customer_address" rows="3">' . esc_textarea(get_user_meta($customer_id, 'mhmrentiva_address', true)) . '</textarea>';
		echo '</div>';

		echo '<div class="rv-cust-form__actions">';
		echo '<button type="submit" name="submit" id="submit" class="rv-cust-btn is-primary">' . esc_html__('Update', 'mhm-rentiva') . '</button>';
		echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $customer_id)) . '" class="rv-cust-btn">' . esc_html__('Cancel', 'mhm-rentiva') . '</a>';
		echo '</div>';

		echo '</form>';
		echo '</div></div>';
		echo '</div>';
	}
}
