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
                        <div class="mhm-rentiva-spinner" style="display:none; width:16px; height:16px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:mhm-spin 1s linear infinite;"></div>
                    </button>
                    <style>
                        @keyframes mhm-spin {
                            0% {
                                transform: rotate(0deg);
                            }

                            100% {
                                transform: rotate(360deg);
                            }
                        }
                    </style>
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