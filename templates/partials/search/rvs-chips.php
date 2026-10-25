<?php
/**
 * Active filter chips: one button per filter value; a click clears that field.
 *
 * Rendered by the results page and by the AJAX re-render (same markup).
 *
 * @var list<array{name: string, value: string, label: string}> $chips SearchResults::active_chips().
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

foreach ( (array) ( $chips ?? array() ) as $mhmrentiva_chip) :
	?>
	<button type="button" class="rvs-chip" data-rvs-chip data-name="<?php echo esc_attr( (string) $mhmrentiva_chip['name']); ?>" data-value="<?php echo esc_attr( (string) $mhmrentiva_chip['value']); ?>">
		<?php echo esc_html( (string) $mhmrentiva_chip['label']); ?>
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
		<span class="rvs-sr-only"><?php esc_html_e('Remove filter', 'mhm-rentiva'); ?></span>
	</button>
	<?php
endforeach;
