<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Customers\Export;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;

final class CustomerExporter {

	/**
	 * The CSV column row. Named so the handler can emit a header-only export
	 * without duplicating the literal that get_csv_rows() writes.
	 *
	 * @var string[]
	 */
	public const CSV_HEADER = array( 'Name', 'Email', 'Phone', 'Bookings', 'Total Spent', 'Last Booking', 'Registered' );

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

		$had_selection = ! empty( $ids );

		// The per-target pair, here at the entry point as well as inside
		// get_csv_rows(). Both are deliberate. get_csv_rows() is public and
		// directly tested, so it defends itself; this copy is what a reviewer
		// reading THIS handler can see. A check that exists only in a helper the
		// handler calls is not line-local evidence -- our own object-capability
		// audit flagged this method while the guard sat one call away, and
		// WordPress.org's scanner reads it the same way.
		$ids = array_values(
			array_filter(
				$ids,
				static fn( int $id ): bool => current_user_can( 'edit_user', $id ) && CustomerIdentity::is_customer( $id )
			)
		);

		// A selection that filtered down to nothing must produce an empty
		// export, NOT fall through to the "no ids means export everything"
		// branch -- that would turn a refused selection into a full dump.
		$rows = ( $had_selection && empty( $ids ) )
			? array( self::CSV_HEADER )
			: self::get_csv_rows( $search, $ids );

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
	 * Build export rows. Public for testability.
	 *
	 * @param string $search  Optional search term.
	 * @param int[]  $ids     Optional specific user IDs; empty = all matching $search.
	 * @return array<int, array<int, string>>
	 */
	public static function get_csv_rows( string $search, array $ids ): array
	{
		$rows   = array();
		$rows[] = self::CSV_HEADER;

		if ( ! empty( $ids ) ) {
			// Fetch individual details for selected IDs.
			foreach ( $ids as $id ) {
				// Two questions per target, the same pair get_detail asks. The
				// handler's edit_users check is about the CALLER and says nothing
				// about which account; get_customer_details_optimized() LEFT JOINs
				// bookings, so it happily returns a full PII row for an editor or a
				// second administrator who has never booked anything. This is the
				// T8 #2 shape, and the export is the sibling that sweep missed.
				if ( ! current_user_can( 'edit_user', $id ) || ! CustomerIdentity::is_customer( $id ) ) {
					continue;
				}

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
				// Same pair as the selected-ids path above. The list query this
				// walks starts FROM wp_users and filters only on `ID > 1 AND
				// user_login != 'admin'`, so it hands back every editor and
				// second administrator on the site. The screen showing them is a
				// known, declared debt; writing them into a downloaded file of
				// personal data is a different disclosure and not covered by it.
				$candidate = (int) ( $c['id'] ?? 0 );
				if ( $candidate <= 0
					|| ! current_user_can( 'edit_user', $candidate )
					|| ! CustomerIdentity::is_customer( $candidate ) ) {
					continue;
				}

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
