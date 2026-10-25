<?php
/**
 * Results header line: "6 vehicles · 12 Jul – 15 Jul · 3 days".
 *
 * Rendered by the results page and by the AJAX re-render (same markup).
 *
 * @var array{count: string, range: string, days: int} $summary SearchResults::summary().
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_summary = (array) ( $summary ?? array() );
$mhmrentiva_rest    = array();
if ('' !== (string) ( $mhmrentiva_summary['range'] ?? '' )) {
	$mhmrentiva_rest[] = (string) $mhmrentiva_summary['range'];
}
if ( (int) ( $mhmrentiva_summary['days'] ?? 0 ) > 0) {
	/* translators: %s: number of rental days. */
	$mhmrentiva_rest[] = sprintf(_n('%s day', '%s days', (int) $mhmrentiva_summary['days'], 'mhm-rentiva'), number_format_i18n( (int) $mhmrentiva_summary['days']));
}
?>
<?php
echo '<strong>' . esc_html( (string) ( $mhmrentiva_summary['count'] ?? '' )) . '</strong>';
if (array() !== $mhmrentiva_rest) {
	echo ' <span class="rvs-results__summary-rest">· ' . esc_html(implode(' · ', $mhmrentiva_rest)) . '</span>';
}
