<?php
/**
 * WP.org compliance oracle for the files that actually enter the Lite ZIP.
 *
 * @package MHMRentiva
 */

declare( strict_types=1 );

$base = dirname( __DIR__ );

/**
 * Concrete paid-surface tokens. Deliberately avoid broad domain words such as
 * "transfer", "vendor", "premium", "license", or "messages" by themselves.
 *
 * @var array<string, string>
 */
$patterns = array(
	'edition gating'       => '/\b(?:isPro|is_pro|allowsSeam|pro_seam|pro_feature|pro_widget|LicenseManager|LicenseAdmin|VerifyEndpoint)\b|\bMode::|Licensing\\\\Mode|\bcanUse[A-Z]|MHMRentiva\\\\Pro/',
	'paid shortcode tag'   => '/\b(?:rentiva_transfer_search|rentiva_transfer_results|rentiva_vendor_apply|rentiva_vendor_profile|rentiva_vendor_directory|rentiva_vendor_bookings|rentiva_vendor_ledger|rentiva_messages)\b/',
	'paid CSS/asset token' => '/(?:search-premium|mhm-premium-search|mhm-transfer-|mhm-vendor-|vendor-badge|card-service-badge|--transfer\b)/i',
	'paid Lite class'      => '/\b(?:AddonContextMetaBox|AddonContextMigration|AddonContextTaxonomy|AddonContextValidator|VehicleCommissionRateMetaBox|PenaltyCalculator|ReliabilityScoreCalculator)\b/',
	'paid role'            => '/[\'\"]rentiva_vendor[\'\"]/',
	'paid vehicle meta'    => '/\bvehicle_service_type\b/',
	'paid email type'      => '/\b(?:booking_created_vendor|booking_status_changed_vendor|booking[-_]created[-_]vendor|booking[-_]status[-_]changed[-_]vendor|message_received_admin|message_replied_customer|message_auto_reply|vendor_approved|vendor_rejected|vendor_suspended|vendor_application_received|vendor_application_new_admin|payout_approved|payout_rejected|iban_change_approved|iban_change_rejected|vehicle_expiry_warning|booking_vendor_notifications)\b/',
);

/**
 * Legacy identifiers must remain readable by migration/uninstall cleanup.
 * These files cannot expose a live UI or register a paid feature.
 *
 * @var array<string, true>
 */
$compatibility_files = array_fill_keys(
	array(
		'src/Admin/Core/Utilities/DatabaseCleaner.php',
		'src/Admin/Core/Utilities/DatabaseMigrator.php',
		'src/Admin/Core/Utilities/PrefixMigrationMap.php',
		'src/Admin/Utilities/Uninstall/Uninstaller.php',
		'uninstall.php',
	),
	true
);

/**
 * Return matching rule labels for one line.
 *
 * @param string                $line     Source line.
 * @param array<string, string> $rules    Named regex rules.
 * @return array<int, string>
 */
function mhmrentiva_paid_surface_matches( string $line, array $rules ): array {
	$matches = array();
	foreach ( $rules as $label => $pattern ) {
		if ( 1 === preg_match( $pattern, $line ) ) {
			$matches[] = $label;
		}
	}

	return $matches;
}

// Built-in negative control: the oracle must catch a concrete paid token while
// leaving legitimate rental-domain prose alone.
if (
	array() === mhmrentiva_paid_surface_matches( 'shortcode: rentiva_vendor_apply', $patterns )
	|| array() === mhmrentiva_paid_surface_matches( '.mhm-card-vendor-badge svg {', $patterns )
	|| array() === mhmrentiva_paid_surface_matches( '.mhm-card-service-badge--transfer {', $patterns )
	|| array() === mhmrentiva_paid_surface_matches( "Mailer::sendBookingEmail( 'booking_created_vendor', \$booking_id, 'vendor' );", $patterns )
	|| array() === mhmrentiva_paid_surface_matches( "case 'message_auto_reply':", $patterns )
	|| array() !== mhmrentiva_paid_surface_matches( 'Bank transfer payments are handled by WooCommerce.', $patterns )
	|| array() !== mhmrentiva_paid_surface_matches( '.mhm-booking-list .booking-type.transfer {', $patterns )
	|| array() !== mhmrentiva_paid_surface_matches( "Mailer::sendBookingEmail( 'booking_created_admin', \$booking_id, 'admin' );", $patterns )
) {
	fwrite( STDERR, "check-no-pro-refs FAILED -- oracle negative control is invalid.\n" );
	exit( 2 );
}

$extensions = array_fill_keys( array( 'php', 'js', 'jsx', 'css', 'json', 'txt' ), true );
$output     = array();
$status     = 2;
$interpreters = 'Windows' === PHP_OS_FAMILY
	? array( 'python', 'py -3' )
	: array( 'python3', 'python' );

foreach ( $interpreters as $interpreter ) {
	$output  = array();
	$command = $interpreter . ' ' . escapeshellarg( $base . '/bin/build-release.py' ) . ' --list-shipped';
	exec( $command, $output, $status );
	if ( 0 === $status && array() !== $output ) {
		break;
	}
}

if ( 0 !== $status || array() === $output ) {
	fwrite( STDERR, "check-no-pro-refs FAILED -- could not resolve the release builder's shipped-file list.\n" );
	exit( 2 );
}

$hits = array();
foreach ( $output as $relative_path ) {
	$relative_path = str_replace( '\\', '/', trim( $relative_path ) );
	if ( '' === $relative_path || isset( $compatibility_files[ $relative_path ] ) ) {
		continue;
	}

	$extension = strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) );
	if ( ! isset( $extensions[ $extension ] ) ) {
		continue;
	}

	$path  = $base . '/' . $relative_path;
	$lines = is_file( $path ) ? file( $path ) : false;
	if ( false === $lines ) {
		continue;
	}

	foreach ( $lines as $line_number => $line ) {
		foreach ( mhmrentiva_paid_surface_matches( $line, $patterns ) as $label ) {
			$hits[] = sprintf(
				'%s:%d [%s] %s',
				$relative_path,
				$line_number + 1,
				$label,
				trim( $line )
			);
		}
	}
}

if ( array() !== $hits ) {
	fwrite( STDERR, "check-no-pro-refs FAILED -- shipped Lite surface contains paid-feature tokens:\n" . implode( "\n", $hits ) . "\n" );
	exit( 1 );
}

echo 'check-no-pro-refs: clean (' . count( $output ) . " shipped files scanned)\n";
