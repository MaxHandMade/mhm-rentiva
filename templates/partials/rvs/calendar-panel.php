<?php
/**
 * The availability calendar's selection panel, or its empty state.
 *
 * The panel starts hidden; the script fills it from `mhmrentiva_calendar_quote`.
 * Without pricing no total is drawn; without the booking button the total stays.
 *
 * @var array<string, mixed> $view  CalendarView::build().
 * @var string               $empty Empty-state text; draws that alone.
 * @var string               $class The shortcode's `class`, on the empty state.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

if (isset($empty)) : ?>
	<div class="<?php echo esc_attr(trim('rvs-empty ' . (string) ( $class ?? '' ))); ?>" data-rvs-availability-empty><?php echo esc_html( (string) $empty); ?></div>
	<?php
	return;
endif;

$mhmrentiva_panel = isset($view) && is_array($view) ? $view : array();
?>
<div class="rvs-availability__panel" data-rvs-cal-panel hidden>
	<p class="rvs-availability__range" data-rvs-cal-range></p>
	<p class="rvs-availability__time" data-rvs-cal-time></p>
	<p class="rvs-availability__note" data-rvs-cal-note hidden></p>
	<?php if (! empty($mhmrentiva_panel['show_pricing'])) : ?>
		<p class="rvs-availability__total" data-rvs-cal-total></p>
	<?php endif; ?>
	<p class="rvs-availability__message" data-rvs-cal-message role="status" aria-live="polite"></p>
	<?php if (! empty($mhmrentiva_panel['show_booking'])) : ?>
		<a class="rvs-btn rvs-btn--primary rvs-availability__book" data-rvs-cal-book href="#" hidden><?php esc_html_e('Make reservation', 'mhm-rentiva'); ?></a>
	<?php endif; ?>
</div>
