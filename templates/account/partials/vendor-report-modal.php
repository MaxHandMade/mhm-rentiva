<?php
/**
 * Vendor report modal — shared markup printed once in the account footer.
 *
 * Used by:
 *  - "Sorun Bildir" buttons on vendor bookings, vehicles, and footer
 *  - "İtiraz Et" buttons on penalty history rows and paused/withdrawn vehicles
 *  - The withdrawal reason capture flow (intercepts `mhm-lifecycle-btn` for
 *    withdraw/pause actions before the AJAX submission)
 *
 * The form is context-aware: client JS toggles labels and the AJAX endpoint
 * based on the trigger button's data attributes.
 *
 * @since 4.35.0
 *
 * @var string $vendor_report_nonce  Nonce token for `mhmrentiva_vendor_report`.
 * @var string $lifecycle_nonce      Nonce token for `mhmrentiva_vehicle_lifecycle`.
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="mhm-vendor-report-modal" data-mhm-vendor-report-modal hidden role="dialog" aria-modal="true" aria-labelledby="mhm-vendor-report-modal-title">
    <div class="mhm-vendor-report-modal__backdrop" data-mhm-vrm-close></div>
    <div class="mhm-vendor-report-modal__dialog">
        <button type="button" class="mhm-vendor-report-modal__close" data-mhm-vrm-close aria-label="<?php echo esc_attr__('Close', 'mhm-rentiva'); ?>">&times;</button>

        <header class="mhm-vendor-report-modal__header">
            <h2 id="mhm-vendor-report-modal-title" class="mhm-vendor-report-modal__title">
                <?php echo esc_html__('Report Issue', 'mhm-rentiva'); ?>
            </h2>
            <p class="mhm-vendor-report-modal__subtitle" data-mhm-vrm-subtitle>
                <?php echo esc_html__('Tell the administrator what happened. Your message goes only to the platform team.', 'mhm-rentiva'); ?>
            </p>
        </header>

        <form class="mhm-vendor-report-modal__form" data-mhm-vrm-form novalidate>
            <input type="hidden" name="context_type" value="">
            <input type="hidden" name="context_id" value="">
            <input type="hidden" name="mode" value="report">

            <div class="mhm-vendor-report-modal__field" data-mhm-vrm-title-field>
                <label class="mhm-vendor-report-modal__label" for="mhm-vrm-title">
                    <?php echo esc_html__('Report Title', 'mhm-rentiva'); ?>
                    <span class="mhm-vendor-report-modal__required" aria-hidden="true">*</span>
                </label>
                <input
                    id="mhm-vrm-title"
                    name="title"
                    type="text"
                    maxlength="255"
                    required
                    class="mhm-vendor-report-modal__input"
                >
            </div>

            <div class="mhm-vendor-report-modal__field">
                <label class="mhm-vendor-report-modal__label" for="mhm-vrm-description" data-mhm-vrm-description-label>
                    <?php echo esc_html__('Describe the issue in detail...', 'mhm-rentiva'); ?>
                    <span class="mhm-vendor-report-modal__required" aria-hidden="true">*</span>
                </label>
                <textarea
                    id="mhm-vrm-description"
                    name="description"
                    minlength="20"
                    maxlength="5000"
                    rows="5"
                    required
                    class="mhm-vendor-report-modal__textarea"
                ></textarea>
                <p class="mhm-vendor-report-modal__hint">
                    <?php echo esc_html__('Minimum 20 characters.', 'mhm-rentiva'); ?>
                </p>
            </div>

            <div class="mhm-vendor-report-modal__error" data-mhm-vrm-error hidden role="alert"></div>

            <footer class="mhm-vendor-report-modal__footer">
                <button type="button" class="mhm-vendor-report-modal__btn mhm-vendor-report-modal__btn--cancel" data-mhm-vrm-close>
                    <?php echo esc_html__('Cancel', 'mhm-rentiva'); ?>
                </button>
                <button type="submit" class="mhm-vendor-report-modal__btn mhm-vendor-report-modal__btn--submit" data-mhm-vrm-submit>
                    <?php echo esc_html__('Submit Report', 'mhm-rentiva'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
