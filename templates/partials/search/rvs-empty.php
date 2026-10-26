<?php
/**
 * Results empty states (standard §9), each with its own message and action:
 * not_searched -- no dates yet (a notice above the catalogue),
 * no_results   -- nothing free in the window,
 * filtered_out -- the filters emptied the list,
 * no_vehicles  -- no window and still nothing: the catalogue is empty,
 * none_here    -- nothing free at the picked pickup point (a notice above the
 *                 sections that offer the city's other points and the booked car).
 *
 * @var string $kind       SearchResults::empty_state() value.
 * @var string $change_url Where "Change dates" sends the visitor.
 * @var string $place      none_here: the picked pickup point(s).
 * @var string $range      none_here: the window, "7 Dec – 10 Dec".
 * @var bool   $has_nearby none_here: the city's other points have cars.
 * @var bool   $has_more   none_here: a section follows; without one, "Change dates".
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_kind = (string) ( $kind ?? '' );
$mhmrentiva_url  = (string) ( $change_url ?? '' );

if ('not_searched' === $mhmrentiva_kind) :
	?>
	<div class="rvs-results__notice-box" data-rvs-empty="not_searched">
		<p><?php esc_html_e('Choose your dates to see the total price and only the cars that are free.', 'mhm-rentiva'); ?></p>
		<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url($mhmrentiva_url); ?>" data-rvs-change><?php esc_html_e('Choose dates', 'mhm-rentiva'); ?></a>
	</div>
	<?php
elseif ('filtered_out' === $mhmrentiva_kind) :
	?>
	<div class="rvs-empty" data-rvs-empty="filtered_out">
		<h3><?php esc_html_e('No cars match your filters', 'mhm-rentiva'); ?></h3>
		<p><?php esc_html_e('Remove a filter or clear them all.', 'mhm-rentiva'); ?></p>
		<button type="button" class="rvs-btn rvs-btn--primary" data-rvs-clear><?php esc_html_e('Clear filters', 'mhm-rentiva'); ?></button>
	</div>
	<?php
elseif ('none_here' === $mhmrentiva_kind) :
	?>
	<div class="rvs-results__notice-box rvs-results__notice-box--none-here" data-rvs-empty="none_here">
		<p class="rvs-results__notice-title"><strong>
		<?php
		/* translators: 1: pickup point name, 2: the searched dates, e.g. "7 Dec – 10 Dec". */
		echo esc_html(sprintf(__('No cars are free at the %1$s pickup point for %2$s.', 'mhm-rentiva'), (string) ( $place ?? '' ), (string) ( $range ?? '' )));
		?>
		</strong></p>
		<?php if (! empty($has_nearby)) : ?>
			<p class="rvs-results__notice-sub"><?php esc_html_e('Other pickup points in the same city have cars. The car you looked for is below, with the earliest time it is free.', 'mhm-rentiva'); ?></p>
		<?php endif; ?>
		<?php if (empty($has_more)) : ?>
			<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url($mhmrentiva_url); ?>" data-rvs-change><?php esc_html_e('Change dates', 'mhm-rentiva'); ?></a>
		<?php endif; ?>
	</div>
	<?php
elseif ('no_vehicles' === $mhmrentiva_kind) :
	?>
	<div class="rvs-empty" data-rvs-empty="no_vehicles">
		<h3><?php esc_html_e('No vehicles are listed yet', 'mhm-rentiva'); ?></h3>
		<p><?php esc_html_e('Please check back soon.', 'mhm-rentiva'); ?></p>
	</div>
	<?php
else :
	?>
	<div class="rvs-empty" data-rvs-empty="no_results">
		<h3><?php esc_html_e('No cars are free for these dates', 'mhm-rentiva'); ?></h3>
		<p><?php esc_html_e('Try other dates or another pickup location.', 'mhm-rentiva'); ?></p>
		<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url($mhmrentiva_url); ?>" data-rvs-change><?php esc_html_e('Change dates', 'mhm-rentiva'); ?></a>
	</div>
	<?php
endif;
