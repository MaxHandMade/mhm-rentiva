<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\PayoutHistoryProvider;
use MHMRentiva\Core\Financial\PayoutService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Payouts partial — rendered under the Ledger & Payouts tab.
 *
 * Provides:
 *  - Payout request form (balance-aware, min-threshold guard shown inline)
 *  - Payout history table with display status badges
 *
 * Receives from parent: $dashboard array including $dashboard['user']->ID
 * Handles AJAX-less POST via standard WP_nonce + form submission.
 *
 * @since 4.21.0
 */

$current_user_id    = (int) ($dashboard['user']->ID ?? get_current_user_id());
$available_balance  = Ledger::get_balance($current_user_id);
$min_payout         = PayoutService::get_minimum_payout_amount();
$has_pending        = PayoutService::vendor_has_pending_payout($current_user_id);
$payout_history     = PayoutHistoryProvider::get_for_vendor($current_user_id, 25);

// Handle payout request form submission.
// Payout is now handled by AJAX in PayoutAjaxController.php

// Status display map.
$status_labels = array(
    'pending'   => array('label' => __('Pending',   'mhm-rentiva'), 'class' => 'is-pending'),
    'approved'  => array('label' => __('Approved',  'mhm-rentiva'), 'class' => 'is-confirmed'),
    'confirmed' => array('label' => __('Confirmed', 'mhm-rentiva'), 'class' => 'is-completed'),
    'failed'    => array('label' => __('Failed',    'mhm-rentiva'), 'class' => 'is-cancelled'),
    'rejected'  => array('label' => __('Rejected',  'mhm-rentiva'), 'class' => 'is-refunded'),
);

$format_currency = static function (float $amount): string {
    if (function_exists('wc_price')) {
        return (string) wc_price($amount);
    }
    $symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₺';
    return $symbol . number_format(abs($amount), 2, '.', ',');
};
?>

<div class="mhm-rentiva-dashboard__payouts">

    <!-- ========= FINANCIAL SUMMARY KPIs ========= -->
    <?php
    $financial_metrics = array('available_balance', 'pending_balance', 'total_paid_out');
    $kpi_items_all = is_array($dashboard['kpis'] ?? null)
        ? $dashboard['kpis']
        : \MHMRentiva\Core\Dashboard\DashboardConfig::get_kpis('vendor');
    $kpi_data_all = is_array($dashboard['kpi_data'] ?? null) ? $dashboard['kpi_data'] : array();

    $has_financials = false;
    foreach ($financial_metrics as $fm) {
        if (isset($kpi_items_all[$fm])) {
            $has_financials = true;
            break;
        }
    }

    if ($has_financials) : ?>
        <div class="mhm-rentiva-dashboard__section" style="margin-bottom: 24px;">
            <div class="mhm-rentiva-dashboard__section-head">
                <h3><?php esc_html_e('Financial Summary', 'mhm-rentiva'); ?></h3>
            </div>
            <div class="mhm-rentiva-dashboard__kpis mhm-financial-cards">
                <?php foreach ($financial_metrics as $fm_key) : ?>
                    <?php if (! isset($kpi_items_all[$fm_key])) { continue; } ?>
                    <?php
                    $fkpi = $kpi_items_all[$fm_key];
                    $fkpi_label = (string) ($fkpi['label'] ?? '');
                    $fkpi_meta = (string) ($fkpi['meta'] ?? '');
                    $fkpi_icon = sanitize_key((string) ($fkpi['icon'] ?? 'wallet'));
                    $fkpi_item = is_array($kpi_data_all[$fm_key] ?? null) ? $kpi_data_all[$fm_key] : array();
                    $fkpi_value = isset($fkpi_item['total']) ? round((float) $fkpi_item['total'], 2) : 0.00;
                    $fkpi_display = function_exists('wc_price') ? wc_price($fkpi_value) : number_format($fkpi_value, 2) . ' ' . get_woocommerce_currency();
                    ?>
                    <div class="mhm-rentiva-dashboard__kpi-card is-financial">
                        <div class="mhm-rentiva-dashboard__kpi-header">
                            <div class="mhm-rentiva-dashboard__kpi-icon" aria-hidden="true">
                                <?php if ($fkpi_icon === 'wallet') : ?>
                                    <svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
                                        <path d="M19.5 9.5V17.5C19.5 18.6046 18.6046 19.5 17.5 19.5H6.5C5.39543 19.5 4.5 18.6046 4.5 17.5V6.5C4.5 5.39543 5.39543 4.5 6.5 4.5H16.5C17.6046 4.5 18.5 5.39543 18.5 6.5V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M21 9.5V14.5C21 15.0523 20.5523 15.5 20 15.5H18C16.8954 15.5 16 14.6046 16 13.5V10.5C16 9.39543 16.8954 8.5 18 8.5H20C20.5523 8.5 21 8.94772 21 9.5Z" stroke="currentColor" stroke-width="1.5" />
                                        <circle cx="18.5" cy="12" r="0.5" fill="currentColor" />
                                    </svg>
                                <?php elseif ($fkpi_icon === 'clock') : ?>
                                    <svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M12 7V12L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                <?php elseif ($fkpi_icon === 'check-circle') : ?>
                                    <svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
                                        <path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="mhm-rentiva-dashboard__kpi-label"><?php echo esc_html($fkpi_label); ?></div>
                        </div>
                        <div class="mhm-rentiva-dashboard__kpi-value is-currency" data-raw="<?php echo esc_attr((string) $fkpi_value); ?>">
                            <?php echo wp_kses_post($fkpi_display); ?>
                        </div>
                        <div class="mhm-rentiva-dashboard__kpi-meta"><?php echo esc_html($fkpi_meta); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========= REQUEST PAYOUT FORM ========= -->
    <div class="mhm-rentiva-dashboard__section mhm-rentiva-dashboard__payout-request">
        <div class="mhm-rentiva-dashboard__section-head">
            <h3><?php esc_html_e('Request a Payout', 'mhm-rentiva'); ?></h3>
        </div>

        <div class="mhm-rentiva-dashboard__payout-request-body">

            <!-- Balance summary cards -->
            <div class="mhm-rentiva-dashboard__payout-balance-card">
                <div class="mhm-rentiva-dashboard__payout-stat mhm-rentiva-dashboard__payout-stat--available">
                    <div class="mhm-rentiva-dashboard__payout-stat-label"><?php esc_html_e('Available Balance', 'mhm-rentiva'); ?></div>
                    <div class="mhm-rentiva-dashboard__payout-stat-value"><?php echo wp_kses_post($format_currency($available_balance)); ?></div>
                </div>
                <div class="mhm-rentiva-dashboard__payout-stat mhm-rentiva-dashboard__payout-stat--min">
                    <div class="mhm-rentiva-dashboard__payout-stat-label"><?php esc_html_e('Minimum Payout', 'mhm-rentiva'); ?></div>
                    <div class="mhm-rentiva-dashboard__payout-stat-value"><?php echo wp_kses_post($format_currency($min_payout)); ?></div>
                </div>
                <div class="mhm-rentiva-dashboard__payout-stat mhm-rentiva-dashboard__payout-stat--pending">
                    <div class="mhm-rentiva-dashboard__payout-stat-label"><?php esc_html_e('Request Status', 'mhm-rentiva'); ?></div>
                    <div class="mhm-rentiva-dashboard__payout-stat-value"><?php echo $has_pending ? esc_html__('Pending', 'mhm-rentiva') : esc_html__('—', 'mhm-rentiva'); ?></div>
                </div>
            </div>

            <div id="mhm-rentiva-payout-notices"></div>

            <?php if ($has_pending) : ?>
                <div class="mhm-rentiva-dashboard__notice is-warning">
                    <?php esc_html_e('You have a pending payout request. You cannot submit another until it is processed.', 'mhm-rentiva'); ?>
                </div>
            <?php elseif ($available_balance < $min_payout) : ?>
                <div class="mhm-rentiva-dashboard__notice is-warning">
                    <?php
                    printf(
                        /* translators: %s: minimum payout amount */
                        esc_html__('Your available balance is below the minimum payout threshold of %s.', 'mhm-rentiva'),
                        wp_kses_post($format_currency($min_payout))
                    );
                    ?>
                </div>
            <?php else : ?>
                <form id="mhm-rentiva-ajax-payout-form" class="mhm-rentiva-dashboard__payout-form" novalidate>
                    <?php wp_nonce_field('mhm_payout_request_' . $current_user_id, 'mhm_payout_request_nonce'); ?>
                    <div class="mhm-rentiva-dashboard__payout-form-group">
                        <label for="payout_amount" class="mhm-rentiva-dashboard__payout-form-label">
                            <?php esc_html_e('Payout Amount', 'mhm-rentiva'); ?>
                        </label>
                        <input
                            type="number"
                            id="payout_amount"
                            name="payout_amount"
                            class="mhm-rentiva-dashboard__payout-form-input"
                            value="<?php echo esc_attr((string) round($available_balance, 2)); ?>"
                            min="<?php echo esc_attr((string) $min_payout); ?>"
                            max="<?php echo esc_attr((string) round($available_balance, 2)); ?>"
                            step="0.01"
                            required>
                    </div>
                    <button type="submit" class="mhm-rentiva-dashboard__payout-submit" id="mhm-rentiva-payout-btn">
                        <span class="mhm-rentiva-btn-text"><?php esc_html_e('Request Payout', 'mhm-rentiva'); ?></span>
                        <div class="mhm-rentiva-spinner" style="display:none;"></div>
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div><!-- /.payout-request -->

    <!-- ========= PAYOUT HISTORY TABLE ========= -->
    <div class="mhm-rentiva-dashboard__section">
        <div class="mhm-rentiva-dashboard__section-head">
            <h3><?php esc_html_e('Payout History', 'mhm-rentiva'); ?></h3>
        </div>

        <div class="mhm-rentiva-dashboard__table-wrap">
            <table class="mhm-rentiva-dashboard__table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'mhm-rentiva'); ?></th>
                        <th><?php esc_html_e('Amount', 'mhm-rentiva'); ?></th>
                        <th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
                        <th><?php esc_html_e('Reference', 'mhm-rentiva'); ?></th>
                        <th><?php esc_html_e('Date', 'mhm-rentiva'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! empty($payout_history)) : ?>
                        <?php foreach ($payout_history as $payout) : ?>
                            <?php
                            $status_key   = $payout['status'];
                            $status_info  = $status_labels[$status_key] ?? array('label' => esc_html($status_key), 'class' => '');
                            $display_date = $payout['requested_at'] !== ''
                                ? date_i18n(get_option('date_format'), strtotime($payout['requested_at']))
                                : '—';
                            $ext_ref      = $payout['external_reference'] !== '' ? $payout['external_reference'] : '—';
                            ?>
                            <tr>
                                <td>#<?php echo esc_html((string) $payout['id']); ?></td>
                                <td class="mhm-rentiva-dashboard__payout-amount"><?php echo wp_kses_post($format_currency($payout['amount'])); ?></td>
                                <td>
                                    <span class="mhm-rentiva-dashboard__status <?php echo esc_attr($status_info['class']); ?>">
                                        <?php echo esc_html($status_info['label']); ?>
                                    </span>
                                </td>
                                <td style="font-family:monospace;font-size:0.8125rem;color:#6b7280"><?php echo esc_html($ext_ref); ?></td>
                                <td><?php echo esc_html($display_date); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr class="mhm-rentiva-dashboard__empty-row">
                            <td colspan="5"><?php esc_html_e('No payout history yet.', 'mhm-rentiva'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.section history -->

</div><!-- /.mhm-rentiva-dashboard__payouts -->