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
$mhmrentiva_cal_title = (string) $mhmrentiva_cal['id'] . '-title';
?>
<div class="rvs-cal" data-rvs-calendar data-year="<?php echo esc_attr( (string) $mhmrentiva_cal['year']); ?>" data-month="<?php echo esc_attr( (string) $mhmrentiva_cal['month']); ?>">
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
							$mhmrentiva_off     = in_array($mhmrentiva_state, array( 'booked', 'past' ), true);
							?>
							<td class="<?php echo esc_attr($mhmrentiva_classes); ?>" data-date="<?php echo esc_attr( (string) $mhmrentiva_cell['date']); ?>" aria-label="<?php echo esc_attr( (string) $mhmrentiva_cell['label']); ?>"<?php echo $mhmrentiva_off ? ' aria-disabled="true"' : ''; ?><?php echo ! empty($mhmrentiva_cell['today']) ? ' aria-current="date"' : ''; ?>><span class="rvs-cal__num" aria-hidden="true"><?php echo esc_html( (string) $mhmrentiva_cell['day']); ?></span></td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
