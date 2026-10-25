<?php
/**
 * Price breakdown: rows, total (WooCommerce suffix under it), deposit and
 * due at pickup.
 *
 * With `$collapsible` the rows fold into a <details>; the total stays visible.
 *
 * @var array<string, mixed> $breakdown   RvsBreakdown::from_quote().
 * @var bool                 $collapsible Fold the rows (vehicle page sidebar, mobile bar).
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_bd = (array) ( $breakdown ?? array() );
if (empty($mhmrentiva_bd['rows'])) {
	return;
}
$mhmrentiva_bd_fold  = ! empty($collapsible);
$mhmrentiva_bd_html  = PriceDisplay::allowed_html();
$mhmrentiva_bd_total = (array) $mhmrentiva_bd['total'];
?>
<div class="rvs-breakdown" data-rvs-breakdown>
	<?php if ($mhmrentiva_bd_fold) : ?>
		<details class="rvs-breakdown__details">
			<summary class="rvs-breakdown__summary"><?php esc_html_e('Price breakdown', 'mhm-rentiva'); ?></summary>
	<?php endif; ?>
	<dl class="rvs-breakdown__rows">
		<?php foreach ( (array) $mhmrentiva_bd['rows'] as $mhmrentiva_bd_row) : ?>
			<div class="rvs-breakdown__row rvs-breakdown__row--<?php echo esc_attr( (string) $mhmrentiva_bd_row['kind']); ?>">
				<dt><?php echo esc_html( (string) $mhmrentiva_bd_row['label']); ?></dt>
				<dd><?php echo wp_kses( (string) $mhmrentiva_bd_row['html'], $mhmrentiva_bd_html); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
	<?php if ($mhmrentiva_bd_fold) : ?>
		</details>
	<?php endif; ?>
	<div class="rvs-breakdown__total">
		<span class="rvs-breakdown__total-label"><?php esc_html_e('Total', 'mhm-rentiva'); ?></span>
		<span class="rvs-breakdown__total-value">
			<span class="rvs-breakdown__amount"><?php echo wp_kses( (string) $mhmrentiva_bd_total['html'], $mhmrentiva_bd_html); ?></span>
			<?php if ('' !== trim( (string) ( $mhmrentiva_bd_total['suffix'] ?? '' ))) : ?>
				<span class="rvs-breakdown__suffix"><?php echo wp_kses( (string) $mhmrentiva_bd_total['suffix'], $mhmrentiva_bd_html); ?></span>
			<?php endif; ?>
		</span>
	</div>
	<?php if (! empty($mhmrentiva_bd['deposit'])) : ?>
		<div class="rvs-breakdown__deposit">
			<span><?php esc_html_e('Due now (deposit)', 'mhm-rentiva'); ?></span>
			<strong><?php echo wp_kses( (string) $mhmrentiva_bd['deposit']['html'], $mhmrentiva_bd_html); ?></strong>
		</div>
	<?php endif; ?>
	<?php if (! empty($mhmrentiva_bd['on_pickup'])) : ?>
		<div class="rvs-breakdown__pickup">
			<span><?php esc_html_e('Due at pickup', 'mhm-rentiva'); ?></span>
			<span><?php echo wp_kses( (string) $mhmrentiva_bd['on_pickup']['html'], $mhmrentiva_bd_html); ?></span>
		</div>
	<?php endif; ?>
</div>
