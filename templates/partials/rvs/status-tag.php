<?php
/**
 * A booking status tag: icon + label (the colour is never the only cue).
 *
 * @var array{modifier: string, label: string} $status RvsStatus::for().
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_tag_modifier = (string) ( $status['modifier'] ?? 'pending' );
$mhmrentiva_tag_label    = (string) ( $status['label'] ?? '' );

// One 24-unit stroke path per colour pair.
$mhmrentiva_tag_paths = array(
	'confirmed' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
	'pending'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	'payment'   => '<rect x="3" y="6" width="18" height="12" rx="1"/><path d="M3 10h18"/>',
	'active'    => '<circle cx="12" cy="12" r="9"/><path d="M10 8l5 4-5 4z"/>',
	'done'      => '<path d="M5 12l5 5 9-10"/>',
	'cancelled' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
);
$mhmrentiva_tag_path  = $mhmrentiva_tag_paths[ $mhmrentiva_tag_modifier ] ?? $mhmrentiva_tag_paths['pending'];

// The icon is filtered as one piece, <svg> included. A shape filtered on its
// own is parsed as HTML, where its self-closing slash means nothing: since
// WordPress 7.2 it comes back unclosed, and the next wp_kses() drops the
// whole <svg> together with what follows it.
$mhmrentiva_tag_icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $mhmrentiva_tag_path . '</svg>';
?>
<span class="rvs-status rvs-status--<?php echo esc_attr($mhmrentiva_tag_modifier); ?>"><?php echo wp_kses($mhmrentiva_tag_icon, \MHMRentiva\Helpers\Icons::allowed_svg()); ?><?php echo esc_html($mhmrentiva_tag_label); ?></span>
