<?php
/**
 * The booking journey's step bar.
 *
 * One list for both layouts: wide, every step shows its number ("Step 1 · Done"
 * for a finished one) and label; compact (`$compact`, and every bar on a narrow
 * screen) shows only the current step as "1 / 3 · label" with "Next: …" and the
 * progress bar under it. rvs-forms.css switches between them.
 *
 * @var list<array{num: int, label: string, state: string}> $items   RvsSteps::items().
 * @var bool                                                $compact The compact layout at every width (checkout, thank-you page).
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_steps = array_values( (array) ( $items ?? array() ));
if (array() === $mhmrentiva_steps) {
	return;
}
$mhmrentiva_steps_total   = count($mhmrentiva_steps);
$mhmrentiva_steps_current = 1;
$mhmrentiva_steps_short   = '';
$mhmrentiva_steps_next    = '';
foreach ($mhmrentiva_steps as $mhmrentiva_steps_i => $mhmrentiva_steps_item) {
	if ('current' !== ( $mhmrentiva_steps_item['state'] ?? '' )) {
		continue;
	}
	$mhmrentiva_steps_current = (int) $mhmrentiva_steps_item['num'];
	$mhmrentiva_steps_short   = sprintf(
		/* translators: 1: the current step's number, 2: the number of steps, 3: the current step's name. */
		__('%1$d / %2$d · %3$s', 'mhm-rentiva'),
		$mhmrentiva_steps_current,
		$mhmrentiva_steps_total,
		(string) $mhmrentiva_steps_item['label']
	);
	if (isset($mhmrentiva_steps[ $mhmrentiva_steps_i + 1 ])) {
		$mhmrentiva_steps_next = sprintf(
			/* translators: %s: the next step's name. */
			__('Next: %s', 'mhm-rentiva'),
			(string) $mhmrentiva_steps[ $mhmrentiva_steps_i + 1 ]['label']
		);
	}
}
?>
<ol class="rvs-steps<?php echo empty($compact) ? '' : ' rvs-steps--compact'; ?>" aria-label="<?php esc_attr_e('Booking steps', 'mhm-rentiva'); ?>">
	<?php foreach ($mhmrentiva_steps as $mhmrentiva_steps_item) : ?>
		<?php $mhmrentiva_steps_state = (string) ( $mhmrentiva_steps_item['state'] ?? 'todo' ); ?>
		<li class="rvs-steps__item is-<?php echo esc_attr($mhmrentiva_steps_state); ?>"<?php echo 'current' === $mhmrentiva_steps_state ? ' aria-current="step"' : ''; ?>>
			<span class="rvs-steps__num">
				<?php
				if ('done' === $mhmrentiva_steps_state) {
					/* translators: %d: the step's number. */
					echo esc_html(sprintf(__('Step %d · Done', 'mhm-rentiva'), (int) $mhmrentiva_steps_item['num']));
				} else {
					/* translators: %d: the step's number. */
					echo esc_html(sprintf(__('Step %d', 'mhm-rentiva'), (int) $mhmrentiva_steps_item['num']));
				}
				?>
			</span>
			<strong class="rvs-steps__label"><?php echo esc_html( (string) $mhmrentiva_steps_item['label']); ?></strong>
			<?php if ('current' === $mhmrentiva_steps_state) : ?>
				<strong class="rvs-steps__short"><?php echo esc_html($mhmrentiva_steps_short); ?></strong>
				<?php if ('' !== $mhmrentiva_steps_next) : ?>
					<span class="rvs-steps__next"><?php echo esc_html($mhmrentiva_steps_next); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ol>
<div class="rvs-steps__bar" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr( (string) $mhmrentiva_steps_total); ?>" aria-valuenow="<?php echo esc_attr( (string) $mhmrentiva_steps_current); ?>" aria-valuetext="<?php echo esc_attr($mhmrentiva_steps_short); ?>" aria-label="<?php esc_attr_e('Booking progress', 'mhm-rentiva'); ?>">
	<span class="rvs-steps__fill"></span>
</div>
