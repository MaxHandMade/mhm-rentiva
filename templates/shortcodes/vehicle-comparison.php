<?php
/**
 * Vehicle comparison (rentiva_vehicle_comparison), inside `.rvs-shell`.
 *
 * Prints ComparisonView::build(). One table for every width: under 782 px the
 * CSS lays it out as the drawing's sticky vehicle header and labelled rows, so
 * every table element names its role (a changed `display` would drop it).
 * The script (vehicle-comparison.js) works from the `data-rvs-cmp-*` hooks and
 * each comparison's root carries its own state.
 *
 * @var array<string, mixed> $view ComparisonView::build().
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Frontend\RenderContext;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_cmp         = isset($view) && is_array($view) ? $view : array();
$mhmrentiva_cmp_columns = (array) ( $mhmrentiva_cmp['columns'] ?? array() );
$mhmrentiva_cmp_groups  = (array) ( $mhmrentiva_cmp['groups'] ?? array() );
$mhmrentiva_cmp_show    = (array) ( $mhmrentiva_cmp['show'] ?? array() );
$mhmrentiva_cmp_has     = ! empty($mhmrentiva_cmp['has_vehicles']);
$mhmrentiva_cmp_fixed   = ! empty($mhmrentiva_cmp['fixed']);
$mhmrentiva_cmp_layout  = 'cards' === ( $mhmrentiva_cmp['layout'] ?? 'table' ) ? 'cards' : 'table';
$mhmrentiva_cmp_preview = RenderContext::current()->is_preview();
// The add column is always there on the visitor's own list: a removal can make room without a reload.
$mhmrentiva_cmp_addable = ! $mhmrentiva_cmp_fixed && '' !== (string) ( $mhmrentiva_cmp['add_url'] ?? '' );
$mhmrentiva_cmp_room    = ! empty($mhmrentiva_cmp['can_add']);
$mhmrentiva_cmp_span    = 1 + count($mhmrentiva_cmp_columns) + ( $mhmrentiva_cmp_room ? 1 : 0 );
$mhmrentiva_cmp_classes = trim('rvs-compare rvs-compare--' . $mhmrentiva_cmp_layout . ' ' . (string) ( $mhmrentiva_cmp['class'] ?? '' ));

$mhmrentiva_cmp_cell_body = static function (array $cell): void {
	?>
	<span class="rvs-compare__value"><?php echo esc_html( (string) $cell['text']); ?></span>
	<span class="rvs-sr-only" data-rvs-cmp-cue<?php echo empty($cell['different']) ? ' hidden' : ''; ?>><?php esc_html_e('Differs from the others', 'mhm-rentiva'); ?></span>
	<?php
};
?>
<section class="<?php echo esc_attr($mhmrentiva_cmp_classes); ?>"
	data-rvs-comparison
	data-max-vehicles="<?php echo esc_attr( (string) ( $mhmrentiva_cmp['max'] ?? 3 )); ?>"
	data-fixed="<?php echo $mhmrentiva_cmp_fixed ? '1' : '0'; ?>"
	data-headers="<?php echo esc_attr( (string) wp_json_encode($mhmrentiva_cmp['headers'] ?? array())); ?>">

	<div class="rvs-compare__head">
		<div class="rvs-compare__heading">
			<h2 class="rvs-compare__title"><?php echo esc_html( (string) ( $mhmrentiva_cmp['title'] ?? '' )); ?></h2>
			<?php if ($mhmrentiva_cmp_has) : ?>
				<p class="rvs-compare__meta" data-rvs-cmp-meta><?php echo esc_html( (string) $mhmrentiva_cmp['header']); ?></p>
			<?php endif; ?>
		</div>
		<?php if ($mhmrentiva_cmp_has) : ?>
			<label class="rvs-check rvs-compare__only" data-rvs-cmp-only-label>
				<input type="checkbox" data-rvs-cmp-only>
				<span><?php esc_html_e('Show only differences', 'mhm-rentiva'); ?></span>
			</label>
		<?php endif; ?>
	</div>

	<?php if ($mhmrentiva_cmp_has) : ?>
		<div class="rvs-compare__body" data-rvs-cmp-body>
			<?php if ('table' === $mhmrentiva_cmp_layout) : ?>
				<div class="rvs-compare__scroll" role="region" aria-label="<?php esc_attr_e('Comparison table', 'mhm-rentiva'); ?>" tabindex="0">
					<table class="rvs-compare__table" role="table">
						<caption class="rvs-sr-only"><?php esc_html_e('Comparison of the selected vehicles', 'mhm-rentiva'); ?></caption>
						<thead class="rvs-compare__vehicles" role="rowgroup">
							<tr role="row">
								<td class="rvs-compare__corner" role="cell"></td>
								<?php foreach ($mhmrentiva_cmp_columns as $mhmrentiva_cmp_column) : ?>
									<th class="rvs-compare__col" role="columnheader" scope="col" data-rvs-cmp-col data-rvs-cmp-vehicle data-vehicle-id="<?php echo esc_attr( (string) $mhmrentiva_cmp_column['id']); ?>">
										<?php
										Templates::render(
											'partials/rvs/compare-vehicle',
											array(
												'column'  => $mhmrentiva_cmp_column,
												'show'    => $mhmrentiva_cmp_show,
												'preview' => $mhmrentiva_cmp_preview,
											),
											false
										);
										?>
									</th>
								<?php endforeach; ?>
								<?php if ($mhmrentiva_cmp_addable) : ?>
									<td class="rvs-compare__add-cell" role="cell" data-rvs-cmp-add<?php echo $mhmrentiva_cmp_room ? '' : ' hidden'; ?>>
										<a class="rvs-compare__add" href="<?php echo esc_url( (string) $mhmrentiva_cmp['add_url']); ?>"><?php esc_html_e('+ Add vehicle', 'mhm-rentiva'); ?></a>
									</td>
								<?php endif; ?>
							</tr>
						</thead>
						<?php foreach ($mhmrentiva_cmp_groups as $mhmrentiva_cmp_group) : ?>
							<tbody class="rvs-compare__group" role="rowgroup" data-rvs-cmp-group>
								<tr class="rvs-compare__group-row" role="row" data-rvs-cmp-group-head>
									<th class="rvs-compare__group-name" role="columnheader" scope="colgroup" colspan="<?php echo esc_attr( (string) $mhmrentiva_cmp_span); ?>" data-rvs-cmp-span><?php echo esc_html( (string) $mhmrentiva_cmp_group['label']); ?></th>
								</tr>
								<?php foreach ($mhmrentiva_cmp_group['rows'] as $mhmrentiva_cmp_row) : ?>
									<tr class="rvs-compare__row" role="row" data-rvs-cmp-row="<?php echo esc_attr( (string) $mhmrentiva_cmp_row['key']); ?>">
										<th class="rvs-compare__label" role="rowheader" scope="row"><?php echo esc_html( (string) $mhmrentiva_cmp_row['label']); ?></th>
										<?php foreach ($mhmrentiva_cmp_row['cells'] as $mhmrentiva_cmp_cell) : ?>
											<td class="rvs-compare__cell<?php echo ! empty($mhmrentiva_cmp_cell['different']) ? ' is-different' : ''; ?>" role="cell" data-rvs-cmp-col data-vehicle-id="<?php echo esc_attr( (string) $mhmrentiva_cmp_cell['vehicle_id']); ?>" data-row="<?php echo esc_attr( (string) $mhmrentiva_cmp_row['key']); ?>" data-rvs-cmp-cell data-row-value="<?php echo esc_attr( (string) $mhmrentiva_cmp_cell['value']); ?>" data-empty="<?php echo ! empty($mhmrentiva_cmp_cell['empty']) ? '1' : '0'; ?>"><?php $mhmrentiva_cmp_cell_body($mhmrentiva_cmp_cell); ?></td>
										<?php endforeach; ?>
										<?php if ($mhmrentiva_cmp_addable) : ?>
											<td class="rvs-compare__filler" role="cell" data-rvs-cmp-filler<?php echo $mhmrentiva_cmp_room ? '' : ' hidden'; ?>></td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						<?php endforeach; ?>
					</table>
				</div>
			<?php else : ?>
				<div class="rvs-card-grid rvs-compare__cards">
					<?php foreach ($mhmrentiva_cmp_columns as $mhmrentiva_cmp_column) : ?>
						<article class="rvs-card rvs-compare__card" data-rvs-cmp-col data-rvs-cmp-vehicle data-vehicle-id="<?php echo esc_attr( (string) $mhmrentiva_cmp_column['id']); ?>">
							<?php
							Templates::render(
								'partials/rvs/compare-vehicle',
								array(
									'column'  => $mhmrentiva_cmp_column,
									'show'    => $mhmrentiva_cmp_show,
									'preview' => $mhmrentiva_cmp_preview,
								),
								false
							);
							?>
							<?php foreach ($mhmrentiva_cmp_groups as $mhmrentiva_cmp_group) : ?>
								<div class="rvs-compare__facts" data-rvs-cmp-group>
									<h3 class="rvs-kicker rvs-compare__facts-name" data-rvs-cmp-group-head><?php echo esc_html( (string) $mhmrentiva_cmp_group['label']); ?></h3>
									<dl class="rvs-compare__facts-list">
										<?php foreach ($mhmrentiva_cmp_group['rows'] as $mhmrentiva_cmp_row) : ?>
											<?php foreach ($mhmrentiva_cmp_row['cells'] as $mhmrentiva_cmp_cell) : ?>
												<?php
												if ( (int) $mhmrentiva_cmp_cell['vehicle_id'] !== (int) $mhmrentiva_cmp_column['id']) {
													continue;
												}
												?>
												<div class="rvs-compare__fact" data-rvs-cmp-row="<?php echo esc_attr( (string) $mhmrentiva_cmp_row['key']); ?>">
													<dt class="rvs-compare__label"><?php echo esc_html( (string) $mhmrentiva_cmp_row['label']); ?></dt>
													<dd class="rvs-compare__cell<?php echo ! empty($mhmrentiva_cmp_cell['different']) ? ' is-different' : ''; ?>" data-row="<?php echo esc_attr( (string) $mhmrentiva_cmp_row['key']); ?>" data-rvs-cmp-cell data-row-value="<?php echo esc_attr( (string) $mhmrentiva_cmp_cell['value']); ?>" data-empty="<?php echo ! empty($mhmrentiva_cmp_cell['empty']) ? '1' : '0'; ?>"><?php $mhmrentiva_cmp_cell_body($mhmrentiva_cmp_cell); ?></dd>
												</div>
											<?php endforeach; ?>
										<?php endforeach; ?>
									</dl>
								</div>
							<?php endforeach; ?>
						</article>
					<?php endforeach; ?>
					<?php if ($mhmrentiva_cmp_addable) : ?>
						<a class="rvs-compare__add" href="<?php echo esc_url( (string) $mhmrentiva_cmp['add_url']); ?>" data-rvs-cmp-add<?php echo $mhmrentiva_cmp_room ? '' : ' hidden'; ?>><?php esc_html_e('+ Add vehicle', 'mhm-rentiva'); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p class="rvs-compare__none" data-rvs-cmp-none hidden><?php esc_html_e('These vehicles do not differ in the compared details.', 'mhm-rentiva'); ?></p>
			<p class="rvs-compare__note"><?php esc_html_e('Light blue cell: a value different from the others.', 'mhm-rentiva'); ?></p>
		</div>
	<?php endif; ?>

	<?php
	// Printed hidden on a fixed comparison: its removals only leave the screen, so a
	// reload would bring them back.
	if (! $mhmrentiva_cmp_has || $mhmrentiva_cmp_fixed) :
		$mhmrentiva_cmp_one = $mhmrentiva_cmp_has || 1 === (int) ( $mhmrentiva_cmp['count'] ?? 0 );
		?>
		<div class="rvs-compare__empty" data-rvs-cmp-empty<?php echo $mhmrentiva_cmp_has ? ' hidden' : ''; ?>>
			<h3 class="rvs-compare__empty-title"><?php echo esc_html($mhmrentiva_cmp_one ? __('Add one more vehicle to compare', 'mhm-rentiva') : __('No vehicles to compare', 'mhm-rentiva')); ?></h3>
			<p class="rvs-compare__empty-text">
				<?php
				if ($mhmrentiva_cmp_one) {
					esc_html_e('A comparison needs at least 2 vehicles. Tick "Compare" on another vehicle in the list.', 'mhm-rentiva');
				} else {
					/* translators: %d: the most vehicles that can be compared. */
					echo esc_html(sprintf(__('Tick "Compare" in the vehicle list; you will see up to %d vehicles side by side.', 'mhm-rentiva'), (int) ( $mhmrentiva_cmp['max'] ?? 3 )));
				}
				?>
			</p>
			<a class="rvs-btn rvs-btn--primary rvs-compare__browse" href="<?php echo esc_url( (string) ( $mhmrentiva_cmp['browse_url'] ?? '' )); ?>"><?php esc_html_e('Go to vehicles', 'mhm-rentiva'); ?></a>
		</div>
	<?php endif; ?>
</section>
