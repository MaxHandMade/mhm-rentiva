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
$form_error = '';
$form_success = '';
if (
    isset($_POST['mhm_payout_request_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mhm_payout_request_nonce'])), 'mhm_payout_request_' . $current_user_id)
) {
    $requested_amount = isset($_POST['payout_amount']) ? (float) sanitize_text_field(wp_unslash((string) $_POST['payout_amount'])) : 0.0;
    $result = PayoutService::request_payout($current_user_id, $requested_amount);

    if (is_wp_error($result)) {
        $form_error = $result->get_error_message();
    } else {
        $form_success = __('Your payout request has been submitted. We will process it shortly.', 'mhm-rentiva');
        // Refresh balance and pending state after successful request.
        $available_balance = Ledger::get_balance($current_user_id);
        $has_pending       = true;
    }
}

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

            <!-- Balance summary -->
            <div class="mhm-rentiva-dashboard__payout-balance">
                <div class="mhm-rentiva-dashboard__payout-balance-label"><?php esc_html_e('Available Balance', 'mhm-rentiva'); ?></div>
                <div class="mhm-rentiva-dashboard__payout-balance-value">
                    <?php echo wp_kses_post($format_currency($available_balance)); ?>
                </div>
                <?php if ($min_payout > 0.0) : ?>
                    <div class="mhm-rentiva-dashboard__payout-balance-min">
                        <?php
                        printf(
                            /* translators: %s: minimum payout amount */
                            esc_html__('Minimum payout: %s', 'mhm-rentiva'),
                            wp_kses_post($format_currency($min_payout))
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($form_success !== '') : ?>
                <div class="mhm-rentiva-dashboard__notice is-success">
                    <?php echo esc_html($form_success); ?>
                </div>
            <?php endif; ?>

            <?php if ($form_error !== '') : ?>
                <div class="mhm-rentiva-dashboard__notice is-error">
                    <?php echo esc_html($form_error); ?>
                </div>
            <?php endif; ?>

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
                <form method="post" class="mhm-rentiva-dashboard__payout-form" novalidate>
                    <?php wp_nonce_field('mhm_payout_request_' . $current_user_id, 'mhm_payout_request_nonce'); ?>

                    <div class="mhm-rentiva-dashboard__payout-form-row">
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

                    <button type="submit" class="mhm-rentiva-dashboard__payout-submit">
                        <?php esc_html_e('Request Payout', 'mhm-rentiva'); ?>
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
                                <td><?php echo wp_kses_post($format_currency($payout['amount'])); ?></td>
                                <td>
                                    <span class="mhm-rentiva-dashboard__status <?php echo esc_attr($status_info['class']); ?>">
                                        <?php echo esc_html($status_info['label']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($ext_ref); ?></td>
                                <td><?php echo esc_html($display_date); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No payout history yet.', 'mhm-rentiva'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.section history -->

</div><!-- /.mhm-rentiva-dashboard__payouts -->