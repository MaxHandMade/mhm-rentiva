<?php
/**
 * One month of the front-end calendar.
 *
 * Prints RvsCalendar::month(). Month navigation belongs to the caller (the
 * vehicle page and the availability calendar each have their own).
 *
 * @var array<string, mixed> $calendar RvsCalendar::month().
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_cal = (array) ( $calendar ?? array() );
if (empty($mhmrentiva_cal['weeks'])) {
	return;
}
$mhmrentiva_cal_interactive = ! empty($mhmrentiva_cal['interactive']);
$mhmrentiva_cal_title       = (string) $mhmrentiva_cal['id'] . '-title';
?>
<div class="rvs-cal<?php echo $mhmrentiva_cal_interactive ? ' is-interactive' : ''; ?>" data-rvs-calendar data-year="<?php echo esc_attr( (string) $mhmrentiva_cal['year']); ?>" data-month="<?php echo esc_attr( (string) $mhmrentiva_cal['month']); ?>">
	<h3 class="rvs-cal__title" id="<?php echo esc_attr($mhmrentiva_cal_title); ?>"><?php echo esc_html( (string) $mhmrentiva_cal['label']); ?></h3>
	<table class="rvs-cal__grid" aria-labelledby="<?php echo esc_attr($mhmrentiva_cal_title); ?>">
		<thead>
			<tr>
				<?php foreach ( (array) $mhmrentiva_cal['weekdays'] as $mhmrentiva_weekday) : ?>
					<th scope="col" abbr="<?php echo esc_attr( (string) $mhmrentiva_weekday['long']); ?>"><?php echo esc_html( (string) $mhmrentiva_weekday['short']); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( (array) $mhmrentiva_cal['weeks'] as $mhmrentiva_week) : ?>
				<tr>
					<?php foreach ( (array) $mhmrentiva_week as $mhmrentiva_cell) : ?>
						<?php if (null === $mhmrentiva_cell) : ?>
							<td class="rvs-cal__day is-empty"></td>
						<?php else : ?>
							<?php
							$mhmrentiva_state   = (string) $mhmrentiva_cell['state'];
							$mhmrentiva_classes = 'rvs-cal__day is-' . $mhmrentiva_state . ( ! empty($mhmrentiva_cell['today']) ? ' is-today' : '' );
							if ($mhmrentiva_cal_interactive) {
								$mhmrentiva_range = (string) ( $mhmrentiva_cell['range'] ?? '' );
								if ('start' === $mhmrentiva_range || 'single' === $mhmrentiva_range) {
									$mhmrentiva_classes .= ' is-range-start';
								}
								if ('end' === $mhmrentiva_range || 'single' === $mhmrentiva_range) {
									$mhmrentiva_classes .= ' is-range-end';
								}
							}
							$mhmrentiva_is_button = $mhmrentiva_cal_interactive && in_array($mhmrentiva_state, array( 'available', 'selected' ), true);
							?>
							<?php if ($mhmrentiva_is_button) : ?>
							<td class="<?php echo esc_attr($mhmrentiva_classes); ?>" data-date="<?php echo esc_attr( (string) $mhmrentiva_cell['date']); ?>"<?php echo ! empty($mhmrentiva_cell['today']) ? ' aria-current="date"' : ''; ?>><button type="button" class="rvs-cal__btn" data-date="<?php echo esc_attr( (string) $mhmrentiva_cell['date']); ?>" aria-label="<?php echo esc_attr( (string) $mhmrentiva_cell['label']); ?>"><span class="rvs-cal__num"><?php echo esc_html( (string) $mhmrentiva_cell['day']); ?></span></button></td>
							<?php else : ?>
							<td class="<?php echo esc_attr($mhmrentiva_classes); ?>" data-date="<?php echo esc_attr( (string) $mhmrentiva_cell['date']); ?>"<?php echo ! empty($mhmrentiva_cell['today']) ? ' aria-current="date"' : ''; ?>><span class="rvs-cal__num"><?php echo esc_html( (string) $mhmrentiva_cell['day']); ?></span><span class="rvs-sr-only">, <?php echo esc_html( (string) $mhmrentiva_cell['status']); ?></span></td>
							<?php endif; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
