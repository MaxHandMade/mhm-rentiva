<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Export;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Licensing\Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExportReports {


	public static function export_data( array $data, string $filename, string $format = 'csv' ): void {
		// Security check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'mhm-rentiva' ) );
		}

		// License check
		if ( ! in_array( $format, array( 'csv', 'json' ) ) && ! Mode::featureEnabled( Mode::FEATURE_EXPORT ) ) {
			wp_die( esc_html__( 'Excel/CSV export is available in Pro version.', 'mhm-rentiva' ) );
		}

		// Invalid data check
		if ( empty( $data ) ) {
			wp_die( esc_html__( 'No data found to export.', 'mhm-rentiva' ) );
		}

		// Sanitize filename
		$filename = sanitize_file_name( $filename );

		switch ( $format ) {
			case 'csv':
				self::export_csv( $data, $filename );
				break;
			case 'json':
				self::export_json( $data, $filename );
				break;
			case 'xml':
				self::export_xml( $data, $filename );
				break;
			case 'xls':
				self::export_excel( $data, $filename );
				break;
			case 'pdf':
				self::export_pdf( $data, $filename );
				break;
			default:
				wp_die( esc_html__( 'Invalid format', 'mhm-rentiva' ) );
		}
	}

	private static function export_csv( array $data, string $filename ): void {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		// Add BOM for Excel compatibility
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fprintf
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Write headers
		if ( ! empty( $data ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv( $output, array_keys( $data[0] ) );
		}

		// Write data
		foreach ( $data as $row ) {
			// Convert data to string and clean
			$clean_row = array_map(
				function ( $value ) {
					if ( is_array( $value ) || is_object( $value ) ) {
						return json_encode( $value );
					}
					return (string) $value;
				},
				$row
			);

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv( $output, $clean_row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	private static function export_excel( array $data, string $filename ): void {
		// Pro feature check
		if ( ! Mode::featureEnabled( Mode::FEATURE_EXPORT ) ) {
			wp_die( esc_html__( 'Excel export is available in Pro version.', 'mhm-rentiva' ) );
		}

		header( 'Content-Type: application/vnd.ms-excel' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo '<html><head><meta charset="UTF-8"></head><body>';
		echo '<table border="1">';

		// Write headers
		if ( ! empty( $data ) ) {
			echo '<tr>';
			foreach ( array_keys( $data[0] ) as $header ) {
				echo '<th>' . esc_html( $header ) . '</th>';
			}
			echo '</tr>';
		}

		// Write data
		foreach ( $data as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				$value = is_array( $cell ) || is_object( $cell ) ? json_encode( $cell ) : $cell;
				echo '<td>' . esc_html( $value ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</table>';
		echo '</body></html>';
		exit;
	}

	private static function export_pdf( array $data, string $filename ): void {
		// Pro feature check
		if ( ! Mode::featureEnabled( Mode::FEATURE_REPORTS_ADV ) ) {
			wp_die( esc_html__( 'PDF export is available in Pro version.', 'mhm-rentiva' ) );
		}

		// Simple HTML table export (real PDF requires library like TCPDF)
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.pdf"' );

		echo '<html><head><title>' . esc_html( $filename ) . '</title></head><body>';
		echo '<h1>' . esc_html( $filename ) . '</h1>';
		echo '<table border="1" style="width: 100%; border-collapse: collapse;">';

		// Write headers
		if ( ! empty( $data ) ) {
			echo '<tr>';
			foreach ( array_keys( $data[0] ) as $header ) {
				echo '<th style="background: #f0f0f0; padding: 8px;">' . esc_html( $header ) . '</th>';
			}
			echo '</tr>';
		}

		// Write data
		foreach ( $data as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				$value = is_array( $cell ) || is_object( $cell ) ? json_encode( $cell ) : $cell;
				echo '<td style="padding: 8px;">' . esc_html( $value ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</table>';
		echo '</body></html>';
		exit;
	}

	/**
	 * JSON export function
	 */
	private static function export_json( array $data, string $filename ): void {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Export metadata
		$export_data = array(
			'export_info' => array(
				'timestamp'      => current_time( 'Y-m-d H:i:s' ),
				'total_records'  => count( $data ),
				'plugin_version' => MHM_RENTIVA_VERSION ?? 'Unknown',
				'exported_by'    => wp_get_current_user()->display_name ?? 'Unknown',
			),
			'data'        => $data,
		);

		echo json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * XML export function
	 */
	private static function export_xml( array $data, string $filename ): void {
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.xml"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<export>' . "\n";
		echo '  <export_info>' . "\n";
		echo '    <timestamp>' . esc_html( current_time( 'Y-m-d H:i:s' ) ) . '</timestamp>' . "\n";
		echo '    <total_records>' . esc_html( count( $data ) ) . '</total_records>' . "\n";
		echo '    <plugin_version>' . esc_html( MHM_RENTIVA_VERSION ?? 'Unknown' ) . '</plugin_version>' . "\n";
		echo '    <exported_by>' . esc_html( wp_get_current_user()->display_name ?? 'Unknown' ) . '</exported_by>' . "\n";
		echo '  </export_info>' . "\n";
		echo '  <data>' . "\n";

		foreach ( $data as $index => $row ) {
			echo '    <record id="' . esc_attr( $index + 1 ) . '">' . "\n";
			foreach ( $row as $key => $value ) {
				$clean_key   = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );
				$clean_value = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : $value;
				echo '      <' . esc_html( $clean_key ) . '><![CDATA[' . esc_html( $clean_value ) . ']]></' . esc_html( $clean_key ) . '>' . "\n";
			}
			echo '    </record>' . "\n";
		}

		echo '  </data>' . "\n";
		echo '</export>' . "\n";
		exit;
	}

	public static function get_supported_formats(): array {
		$formats = array(
			'csv'  => __( 'CSV', 'mhm-rentiva' ),
			'json' => __( 'JSON', 'mhm-rentiva' ),
		);

		if ( Mode::featureEnabled( Mode::FEATURE_EXPORT ) ) {
			$formats['xml'] = __( 'XML', 'mhm-rentiva' );
			$formats['xls'] = __( 'Excel (XLS)', 'mhm-rentiva' );
		}

		if ( Mode::featureEnabled( Mode::FEATURE_REPORTS_ADV ) ) {
			$formats['pdf'] = __( 'PDF', 'mhm-rentiva' );
		}

		return $formats;
	}

	public static function validate_export_request( string $type, string $start_date, string $end_date, string $format ): bool {
		// Date format check
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			return false;
		}

		// Date validity check
		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			return false;
		}

		// Format check
		$supported_formats = self::get_supported_formats();
		if ( ! isset( $supported_formats[ $format ] ) ) {
			return false;
		}

		// License check
		if ( ! Mode::featureEnabled( Mode::FEATURE_REPORTS_ADV ) ) {
			$max_days  = Mode::reportsMaxRangeDays();
			$date_diff = ( strtotime( $end_date ) - strtotime( $start_date ) ) / ( 60 * 60 * 24 );

			if ( $date_diff > $max_days ) {
				return false;
			}
		}

		return true;
	}

	public static function log_export( string $type, string $format, int $record_count ): void {
		// Export logging (can be used in the future)
		$log_data = array(
			'type'         => $type,
			'format'       => $format,
			'record_count' => $record_count,
			'user_id'      => get_current_user_id(),
			'timestamp'    => current_time( 'timestamp' ),
		);

		// Update log file (simple approach)
		$log_file  = wp_upload_dir()['basedir'] . '/mhm-rentiva-exports.log';
		$log_entry = current_time( 'Y-m-d H:i:s' ) . ' - ' . json_encode( $log_data ) . "\n";

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$current_content = $wp_filesystem->exists( $log_file ) ? $wp_filesystem->get_contents( $log_file ) : '';
			$wp_filesystem->put_contents( $log_file, $current_content . $log_entry, FS_CHMOD_FILE );
		}
	}
}
