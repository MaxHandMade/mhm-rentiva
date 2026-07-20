<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers\Export;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Customers\CustomersOptimizer;

final class CustomerExporter {

	public static function handle(): void
	{
		if (! check_admin_referer( 'mhm_rentiva_export_customers', 'nonce' )) {
			wp_die( esc_html__( 'Invalid security token.', 'mhm-rentiva' ), 403 );
		}

		if (! current_user_can( 'manage_options' )) {
			wp_die( esc_html__( 'Unauthorized.', 'mhm-rentiva' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above via check_admin_referer.
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above via check_admin_referer.
		$raw_ids = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) );
		$ids     = array_values( array_filter( $raw_ids, fn( $id ) => $id > 0 ) );

		$rows = self::get_csv_rows( $search, $ids );

		$filename = 'customers-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// UTF-8 BOM for Excel compatibility.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV binary output.
		echo "\xEF\xBB\xBF";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- WP_Filesystem does not support php://output stream; raw fopen required for CSV streaming.
		$handle = fopen( 'php://output', 'w' );
		if ( $handle ) {
			foreach ( $rows as $row ) {
				$row = array_map( array( self::class, 'guard_csv_cell' ), $row );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fputcsv escapes properly for CSV format.
				fputcsv( $handle, $row );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- matches fopen above; WP_Filesystem has no equivalent for php://output.
			fclose( $handle );
		}

		wp_die();
	}

	/**
	 * Neutralize CSV formula injection. A cell whose first character is one a
	 * spreadsheet treats as a formula/command trigger (=, +, -, @, or a leading
	 * tab/carriage return) is prefixed with a single quote, so Excel/Sheets
	 * renders it as literal text instead of evaluating it. The quote itself is
	 * not displayed by the spreadsheet.
	 *
	 * @param mixed $value Cell value.
	 * @return string Guarded cell value.
	 */
	private static function guard_csv_cell( $value ): string
	{
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Build export rows. Public for testability.
	 *
	 * @param string $search  Optional search term.
	 * @param int[]  $ids     Optional specific user IDs; empty = all matching $search.
	 * @return array<int, array<int, string>>
	 */
	public static function get_csv_rows( string $search, array $ids ): array
	{
		$rows   = array();
		$rows[] = array( 'Name', 'Email', 'Phone', 'Bookings', 'Total Spent', 'Last Booking', 'Registered' );

		if ( ! empty( $ids ) ) {
			// Fetch individual details for selected IDs.
			foreach ( $ids as $id ) {
				$detail = CustomersOptimizer::get_customer_details_optimized( $id );
				if ( null === $detail ) {
					continue;
				}
				$rows[] = array(
					$detail['name']          ?? '',
					$detail['email']         ?? '',
					$detail['phone']         ?? '',
					(string) ( $detail['booking_count'] ?? 0 ),
					$detail['total_spent']   ?? '0',
					$detail['last_booking']  ?? '',
					$detail['registered']    ?? '',
				);
			}
			return $rows;
		}

		// Export all customers matching search, page by page.
		$page     = 1;
		$per_page = 100;
		do {
			$result    = CustomersOptimizer::get_customers_optimized( $page, $per_page, $search );
			$customers = $result['customers'] ?? array();
			foreach ( $customers as $c ) {
				$rows[] = array(
					$c['name']          ?? '',
					$c['email']         ?? '',
					$c['phone']         ?? '',
					(string) ( $c['booking_count'] ?? 0 ),
					$c['total_spent']   ?? '0',
					$c['last_booking']  ?? '',
					$c['created_date']  ?? '',
				);
			}
			++$page;
		} while ( $page <= ( $result['total_pages'] ?? 1 ) );

		return $rows;
	}
}
