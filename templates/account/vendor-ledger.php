<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Local scope inside render context.

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Display grid matching ledger execution blocks exposing paginations seamlessly extracting securely formatted presentation elements implicitly.
 *
 * Incoming Context:
 * @var array<int, \stdClass> $ledger_entries Array containing stdClass transaction matching rows correctly retrieved from DB.
 * @var array<string, string> $ledger_filters Extracted contextual bounds applying safe query parameters securely.
 * @var int                   $ledger_paged   Resolved sequential index correctly.
 * @var int                   $ledger_limit   Global pagination bounds applied automatically.
 */
?>

<div class="mhm-rentiva-ledger">
    <div class="mhm-rentiva-ledger__header">
        <h2><?php esc_html_e('Financial Ledger', 'mhm-rentiva'); ?></h2>
    </div>

    <!-- Basic Filter Form Handling Pagination Context implicitly without AJAX dependency targeting stable GET protocols. -->
    <form method="GET" class="mhm-rentiva-ledger__filters" action="">
        <!-- Retain existing URL parameters -->
        <?php if (! empty($ledger_tab)) : ?>
            <input type="hidden" name="tab" value="<?php echo esc_attr($ledger_tab); ?>">
        <?php endif; ?>

        <div class="mhm-rentiva-ledger__filter-group">
            <label for="mhm_ledger_status"><?php esc_html_e('Status', 'mhm-rentiva'); ?></label>
            <select name="filter_status" id="mhm_ledger_status">
                <option value=""><?php esc_html_e('All Statuses', 'mhm-rentiva'); ?></option>
                <option value="cleared" <?php selected(($ledger_filters['status'] ?? ''), 'cleared'); ?>><?php esc_html_e('Cleared', 'mhm-rentiva'); ?></option>
                <option value="pending" <?php selected(($ledger_filters['status'] ?? ''), 'pending'); ?>><?php esc_html_e('Pending', 'mhm-rentiva'); ?></option>
            </select>
        </div>

        <div class="mhm-rentiva-ledger__filter-group">
            <label for="mhm_ledger_type"><?php esc_html_e('Type', 'mhm-rentiva'); ?></label>
            <select name="filter_type" id="mhm_ledger_type">
                <option value=""><?php esc_html_e('All Types', 'mhm-rentiva'); ?></option>
                <option value="commission_credit" <?php selected(($ledger_filters['type'] ?? ''), 'commission_credit'); ?>><?php esc_html_e('Earnings', 'mhm-rentiva'); ?></option>
                <option value="payout_debit" <?php selected(($ledger_filters['type'] ?? ''), 'payout_debit'); ?>><?php esc_html_e('Payouts', 'mhm-rentiva'); ?></option>
                <option value="commission_refund" <?php selected(($ledger_filters['type'] ?? ''), 'commission_refund'); ?>><?php esc_html_e('Refunds', 'mhm-rentiva'); ?></option>
            </select>
        </div>

        <div class="mhm-rentiva-ledger__filter-actions">
            <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'mhm-rentiva'); ?></button>
            <a href="<?php echo esc_url($ledger_reset_url); ?>" class="button"><?php esc_html_e('Reset', 'mhm-rentiva'); ?></a>
        </div>
    </form>

    <div class="mhm-rentiva-ledger__table-wrap mhm-rentiva-dashboard__table-wrap">
        <table class="mhm-rentiva-dashboard__table mhm-rentiva-ledger__table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Type', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Context', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Amount', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (! empty($ledger_entries)) : ?>
                    <?php foreach ($ledger_entries as $entry) : ?>
                        <?php
                        // Type Map
                        $type_label = $entry->type;
                        if ($entry->type === 'commission_credit') {
                            $type_label = __('Booking Earning', 'mhm-rentiva');
                        } elseif ($entry->type === 'payout_debit') {
                            $type_label = __('Payout Withdrawal', 'mhm-rentiva');
                        } elseif ($entry->type === 'commission_refund') {
                            $type_label = __('Booking Refund', 'mhm-rentiva');
                        }

                        // Context ID mapper cleanly identifying targets.
                        $context_obj = '-';
                        if ($entry->order_id) {
                            $context_obj = sprintf(
                                /* translators: %d: order ID */
                                __('Order #%d', 'mhm-rentiva'),
                                (int) $entry->order_id
                            );
                        } elseif ($entry->context === 'payout') {
                            $context_obj = __('System Payout', 'mhm-rentiva');
                        }

                        // Status Visual Map matching UI elements
                        $status_class = 'mhm-rentiva-dashboard__status';
                        if ($entry->status === 'cleared') {
                            $status_class .= ' is-completed';
                        } elseif ($entry->status === 'pending') {
                            $status_class .= ' is-pending';
                        }

                        // Value Map
                        $amount = (float) $entry->amount;
                        $amount_class = $amount > 0 ? 'is-positive' : 'is-negative';
                        $amount_display = function_exists('wc_price') ? wc_price($amount, array('currency' => $entry->currency)) : number_format($amount, 2) . ' ' . $entry->currency;
                        ?>
                        <tr>
                            <td>
                                <span class="mhm-rentiva-ledger__date">
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_at))); ?>
                                </span>
                                <small class="mhm-rentiva-ledger__uuid" style="display:block; opacity:0.5; font-size:10px;">
                                    <?php echo esc_html(substr($entry->transaction_uuid, 0, 8) . '...'); ?>
                                </small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($type_label); ?></strong>
                            </td>
                            <td><?php echo esc_html($context_obj); ?></td>
                            <td class="<?php echo esc_attr($amount_class); ?>">
                                <strong><?php echo wp_kses_post($amount_display); ?></strong>
                            </td>
                            <td>
                                <span class="<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($entry->status)); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 2rem;">
                            <?php esc_html_e('No ledger transactions matching this criteria.', 'mhm-rentiva'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    // Minimalist fallback pagination logic (Standard WP pagination bounds generally require precise row limits, handled dynamically here over simple offsets)
    if (count($ledger_entries) === $ledger_limit || $ledger_paged > 1) :
    ?>
        <div class="mhm-rentiva-ledger__pagination" style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
            <?php if ($ledger_paged > 1) : ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $ledger_paged - 1)); ?>" class="button">&laquo; <?php esc_html_e('Previous', 'mhm-rentiva'); ?></a>
            <?php endif; ?>

            <?php if (count($ledger_entries) === $ledger_limit) : ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $ledger_paged + 1)); ?>" class="button"><?php esc_html_e('Next', 'mhm-rentiva'); ?> &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
