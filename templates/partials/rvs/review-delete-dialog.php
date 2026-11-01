<?php
/**
 * "Delete your review?" on the shared rvs dialog (rvs-overlay.css + rvs-dialog.js),
 * opened by vehicle-rating-form.js with focus on "Keep".
 *
 * @var string $dialog_id Unique id of this dialog.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_rd_id = (string) ( $dialog_id ?? '' );
?>
<div class="rvs-dialog rvs-reviews__dialog" id="<?php echo esc_attr($mhmrentiva_rd_id); ?>" data-rvs-review-dialog hidden>
	<div class="rvs-dialog__panel rvs-reviews__dialog-panel">
		<div class="rvs-dialog__body rvs-reviews__dialog-body">
			<h2 class="rvs-dialog__title" id="<?php echo esc_attr($mhmrentiva_rd_id . '-title'); ?>"><?php esc_html_e('Delete your review?', 'mhm-rentiva'); ?></h2>
			<p><?php esc_html_e('Your rating and review are removed. This cannot be undone.', 'mhm-rentiva'); ?></p>
			<p class="rvs-error" data-rvs-review-dialog-message role="alert" hidden></p>
			<div class="rvs-reviews__dialog-actions">
				<button type="button" class="rvs-btn rvs-btn--secondary" data-rvs-review-dialog-keep><?php esc_html_e('Keep', 'mhm-rentiva'); ?></button>
				<button type="button" class="rvs-btn rvs-reviews__confirm" data-rvs-review-dialog-confirm><?php esc_html_e('Delete', 'mhm-rentiva'); ?></button>
			</div>
		</div>
	</div>
</div>
