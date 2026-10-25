<?php
/**
 * Results empty states (standard §9), each with its own message and action:
 * not_searched -- no dates yet (a notice above the catalogue),
 * no_results   -- nothing free in the window,
 * filtered_out -- the filters emptied the list.
 *
 * @var string $kind       SearchResults::empty_state() value.
 * @var string $change_url Where "Change dates" sends the visitor.
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
else :
	?>
	<div class="rvs-empty" data-rvs-empty="no_results">
		<h3><?php esc_html_e('No cars are free for these dates', 'mhm-rentiva'); ?></h3>
		<p><?php esc_html_e('Try other dates or another pickup location.', 'mhm-rentiva'); ?></p>
		<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url($mhmrentiva_url); ?>" data-rvs-change><?php esc_html_e('Change dates', 'mhm-rentiva'); ?></a>
	</div>
	<?php
endif;
