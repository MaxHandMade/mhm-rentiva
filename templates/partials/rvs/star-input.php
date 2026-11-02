<?php
/**
 * The star input: five focusable radios under the icons, inside the caller's
 * fieldset. The arrow keys are the browser's. Styles: rvs-forms.css `.rvs-stars`.
 *
 * @var bool $disabled Start disabled (a field its form hides for now).
 * @var int  $checked  The star already chosen, 0 for none.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_si_disabled = ! empty($disabled);
$mhmrentiva_si_checked  = (int) ( $checked ?? 0 );
?>
<div class="rvs-stars">
	<?php for ($mhmrentiva_si_star = 1; $mhmrentiva_si_star <= 5; $mhmrentiva_si_star++) : ?>
		<label>
			<input type="radio" name="rating" value="<?php echo esc_attr( (string) $mhmrentiva_si_star); ?>"<?php checked($mhmrentiva_si_checked, $mhmrentiva_si_star); ?><?php echo $mhmrentiva_si_disabled ? ' disabled' : ''; ?>>
			<span class="rvs-sr-only">
				<?php
				/* translators: %d: number of stars. */
				echo esc_html(sprintf(_n('%d star', '%d stars', $mhmrentiva_si_star, 'mhm-rentiva'), $mhmrentiva_si_star));
				?>
			</span>
			<svg class="rvs-stars__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3l2.8 5.9 6.2.9-4.5 4.4 1.1 6.3L12 17.6l-5.6 2.9 1.1-6.3L3 9.8l6.2-.9z"/></svg>
		</label>
	<?php endfor; ?>
</div>
