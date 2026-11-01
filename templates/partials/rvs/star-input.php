<?php
/**
 * The star input: five focusable radios under the icons, inside the caller's
 * fieldset. The arrow keys are the browser's. Styles: rvs-forms.css `.rvs-stars`.
 *
 * @var bool $disabled Start disabled (a field its form hides for now).
 * @var bool $required The group is required.
 */

declare(strict_types=1);

use MHMRentiva\Helpers\Icons;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_si_disabled = ! empty($disabled);
?>
<div class="rvs-stars">
	<?php for ($mhmrentiva_si_star = 1; $mhmrentiva_si_star <= 5; $mhmrentiva_si_star++) : ?>
		<label>
			<input type="radio" name="rating" value="<?php echo esc_attr( (string) $mhmrentiva_si_star); ?>"<?php echo $mhmrentiva_si_disabled ? ' disabled' : ''; ?>>
			<span class="rvs-sr-only">
				<?php
				/* translators: %d: number of stars. */
				echo esc_html(sprintf(_n('%d star', '%d stars', $mhmrentiva_si_star, 'mhm-rentiva'), $mhmrentiva_si_star));
				?>
			</span>
			<?php Icons::render('star'); ?>
		</label>
	<?php endfor; ?>
</div>
