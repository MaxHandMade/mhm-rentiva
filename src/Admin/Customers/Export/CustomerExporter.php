<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers\Export;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Customers\CustomersOptimizer;

final class CustomerExporter {

	public static function handle(): void
	{
		if (! check_admin_referer( 'mhmrentiva_export_customers', 'nonce' )) {
			wp_die( esc_html__( 'Invalid security token.', 'mhm-rentiva' ), 403 );
		}

		// Exports customer PII (name, email, phone, address) to CSV, so it is gated on
		// `edit_users` — the capability matching the data — like the Customers screen
		// and the /customers REST routes.
		if (! current_user_can( 'edit_users' )) {
			wp_die( esc_html__( 'Unauthorized.', 'mhm-rentiva' ), 403 );
		}

		$search  = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$raw_ids = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) );
		$ids     = array_values( array_filter( $raw_ids, fn( $id ) => $id > 0 ) );

		$rows = self::get_csv_rows( $search, $ids );

		$filename = 'customers-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// UTF-8 BOM for Excel compatibility.
		echo "\xEF\xBB\xBF";

		// php://output is the HTTP response body, not a file, so WP_Filesystem
		// does not apply -- WPCS agrees and exempts this exact stream from its
		// file-operation checks (see AlternativeFunctionsSniff's
		// $allowed_local_streams). The handle is deliberately NOT fclose()d: the
		// response body is closed by PHP when the request ends, and calling
		// fclose() on it bought nothing except a WP_Filesystem warning.
		$handle = fopen( 'php://output', 'w' );
		if ( $handle ) {
			foreach ( $rows as $row ) {
				$row = array_map( array( self::class, 'guard_csv_cell' ), $row );
				fputcsv( $handle, $row );
			}
		}

		// exit, not wp_die(): a bare wp_die() runs the default die handler, which
		// sends a 500 status and Content-Type: text/html over the CSV headers set
		// above. Chrome refuses the download outright ("ERR_INVALID_RESPONSE"), so
		// the Export CSV button has never produced a file -- verified in the
		// browser against the pre-fix code. This is the same
		// `header(...) + write to php://output + exit` shape the SQL download
		// endpoints use (DatabaseCleanupPage::send_download_body()).
		exit;
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
	 * Recover a raw, spreadsheet-parseable number from a formatted money string.
	 *
	 * `CustomersOptimizer` hands back `total_spent` through
	 * `CurrencyHelper::format_price()` -- the canonical, ON-SCREEN shape: a
	 * currency symbol, WooCommerce's locale grouping/decimal separators, and (for
	 * the `*_space` positions) a U+00A0 between number and symbol. A spreadsheet's
	 * SUM() can't read that. `CurrencyHelper::to_amount()` is the plugin's
	 * existing "formatted money back to a float" utility -- built for exactly
	 * this class of problem -- so it is reused here instead of re-deriving a
	 * parser. The recovered float is re-emitted with a fixed `.` decimal and no
	 * thousands grouping: the same bare-digits shape the `Bookings` column
	 * already uses, portable across any spreadsheet's CSV import regardless of
	 * the site's or the opening machine's locale.
	 *
	 * @param mixed $formatted Formatted money value (or a raw one; `to_amount()` accepts both).
	 * @return string
	 */
	private static function raw_amount( $formatted ): string
	{
		return number_format( CurrencyHelper::to_amount( $formatted ), 2, '.', '' );
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
		// One site-wide currency for the whole file (WooCommerce, or the plugin
		// fallback) -- a separate column instead of folding it into the header or
		// the number, so `Total Spent` stays a pure, summable value.
		$currency_code = CurrencyHelper::get_currency_code();

		$rows   = array();
		$rows[] = array( 'Name', 'Email', 'Phone', 'Bookings', 'Total Spent', 'Currency', 'Last Booking', 'Registered' );

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
					self::raw_amount( $detail['total_spent'] ?? '0' ),
					$currency_code,
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
					self::raw_amount( $c['total_spent'] ?? '0' ),
					$currency_code,
					$c['last_booking']  ?? '',
					$c['created_date']  ?? '',
				);
			}
			++$page;
		} while ( $page <= ( $result['total_pages'] ?? 1 ) );

		return $rows;
	}
}
