<?php
/**
 * A rating shown as five stars. Filled, not only recoloured: the score is not told
 * by colour alone, and role="img" reads it out as one sentence.
 *
 * @var int    $rating 0 to 5.
 * @var string $label  ReviewsView::stars_label() or the summary's stars_label.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_rs_rating = (int) ( $rating ?? 0 );
?>
<span class="rvs-review-stars" role="img" aria-label="<?php echo esc_attr( (string) ( $label ?? '' )); ?>">
	<?php for ($mhmrentiva_rs_i = 1; $mhmrentiva_rs_i <= 5; $mhmrentiva_rs_i++) : ?>
		<svg class="rvs-review-stars__star<?php echo $mhmrentiva_rs_i <= $mhmrentiva_rs_rating ? ' is-filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M12 3l2.8 5.9 6.2.9-4.5 4.4 1.1 6.3L12 17.6l-5.6 2.9 1.1-6.3L3 9.8l6.2-.9z"/></svg>
	<?php endfor; ?>
</span>
