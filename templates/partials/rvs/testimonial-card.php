<?php
/**
 * One testimonial: stars, the words, then who said it. The card knows no layout:
 * the grid, the list and the slider place it. With `$template` it is the empty
 * card "Load more" clones, kept hidden and inert in the page: the same classes and the
 * flags of this page, empty [data-rvs-slot] elements, no sample text, no stars (the
 * script draws them). The script fills the slots with text and builds no markup from
 * strings.
 *
 * @var array<string, mixed> $card     ReviewsView::card(); empty for the template.
 * @var array<string, bool>  $show     rating, customer, avatar, vehicle, date, quotes.
 * @var bool                 $template Print the empty card for the script to clone.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_tc      = (array) ( $card ?? array() );
$mhmrentiva_tc_show = (array) ( $show ?? array() );
$mhmrentiva_tc_tpl  = ! empty($template);
if ($mhmrentiva_tc_tpl) {
	$mhmrentiva_tc = array(
		'rating'      => 0,
		'stars_label' => '',
		'text'        => '',
		'initial'     => '',
		'name'        => '',
		'vehicle'     => '',
		'date'        => '',
	);
}
?>
<article class="rvs-tcard" data-rvs-card>
	<?php if (! empty($mhmrentiva_tc_show['quotes'])) : ?>
		<svg class="rvs-tcard__quote" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M10 7H6a2 2 0 0 0-2 2v4h6V7zM20 7h-4a2 2 0 0 0-2 2v4h6V7zM10 13c0 2-1 4-3 4M20 13c0 2-1 4-3 4"/></svg>
	<?php endif; ?>
	<?php if ($mhmrentiva_tc_tpl && ! empty($mhmrentiva_tc_show['rating'])) : ?>
		<span class="rvs-review-stars" role="img" aria-label="" data-rvs-stars></span>
	<?php elseif (! empty($mhmrentiva_tc_show['rating']) && (int) $mhmrentiva_tc['rating'] > 0) : ?>
		<?php
		Templates::render(
			'partials/rvs/review-stars',
			array(
				'rating' => (int) $mhmrentiva_tc['rating'],
				'label'  => (string) $mhmrentiva_tc['stars_label'],
			)
		);
		?>
	<?php endif; ?>
	<p class="rvs-tcard__text" data-rvs-slot="text"><?php echo '' === (string) $mhmrentiva_tc['text'] ? '' : '&ldquo;' . esc_html( (string) $mhmrentiva_tc['text']) . '&rdquo;'; ?></p>
	<?php if (! empty($mhmrentiva_tc_show['customer'])) : ?>
		<div class="rvs-tcard__author">
			<?php if (! empty($mhmrentiva_tc_show['avatar'])) : ?>
				<span class="rvs-tcard__avatar" aria-hidden="true" data-rvs-slot="initial"><?php echo esc_html( (string) $mhmrentiva_tc['initial']); ?></span>
			<?php endif; ?>
			<span class="rvs-tcard__info">
				<strong class="rvs-tcard__name" data-rvs-slot="name"><?php echo esc_html( (string) $mhmrentiva_tc['name']); ?></strong>
				<?php if (! empty($mhmrentiva_tc_show['vehicle']) && ( $mhmrentiva_tc_tpl || '' !== (string) $mhmrentiva_tc['vehicle'] )) : ?>
					<span class="rvs-tcard__vehicle" data-rvs-slot="vehicle"><?php echo esc_html( (string) $mhmrentiva_tc['vehicle']); ?></span>
				<?php endif; ?>
				<?php if (! empty($mhmrentiva_tc_show['date']) && ( $mhmrentiva_tc_tpl || '' !== (string) $mhmrentiva_tc['date'] )) : ?>
					<span class="rvs-tcard__date" data-rvs-slot="date"><?php echo esc_html( (string) $mhmrentiva_tc['date']); ?></span>
				<?php endif; ?>
			</span>
		</div>
	<?php endif; ?>
</article>
