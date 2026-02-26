<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Payouts;

use MHMRentiva\Core\Financial\AtomicPayoutService;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\PayoutService;

if (! defined('ABSPATH')) {
    exit;
}

// WP_List_Table is not auto-loaded.
if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin WP_List_Table for mhm_payout CPT with bulk approve and CSV export.
 *
 * @since 4.21.0
 */
final class PayoutListTable extends \WP_List_Table
{
    /**
     * Action handle for bulk approve.
     */
    public const BULK_ACTION_APPROVE = 'mhm_payout_bulk_approve';

    /**
     * Action handle for CSV export.
     */
    public const ACTION_CSV_EXPORT = 'mhm_export_payouts';

    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'payout',
            'plural'   => 'payouts',
            'ajax'     => false,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        return array(
            'cb'         => '<input type="checkbox">',
            'payout_id'  => __('ID', 'mhm-rentiva'),
            'vendor'     => __('Vendor', 'mhm-rentiva'),
            'amount'     => __('Amount', 'mhm-rentiva'),
            'balance'    => __('Available Balance', 'mhm-rentiva'),
            'status'     => __('Status', 'mhm-rentiva'),
            'requested'  => __('Requested', 'mhm-rentiva'),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function get_bulk_actions(): array
    {
        return array(
            self::BULK_ACTION_APPROVE => __('Approve Selected', 'mhm-rentiva'),
        );
    }

    /**
     * Prepare table items. Queries all pending mhm_payout posts.
     */
    public function prepare_items(): void
    {
        $this->_column_headers = array($this->get_columns(), array(), array());

        $posts = get_posts(array(
            'post_type'      => PostType::POST_TYPE,
            'post_status'    => array('pending', 'publish', 'trash'),
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $this->items = is_array($posts) ? $posts : array();
    }

    /**
     * Checkbox column for bulk selection.
     *
     * @param \WP_Post $item
     */
    protected function column_cb($item): string
    {
        // Only allow selecting pending payouts for approval.
        if ($item->post_status !== 'pending') {
            return '';
        }

        return sprintf(
            '<input type="checkbox" name="payout_ids[]" value="%d">',
            (int) $item->ID
        );
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_payout_id(\WP_Post $item): string
    {
        return '#' . esc_html((string) $item->ID);
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_vendor(\WP_Post $item): string
    {
        $vendor_id = (int) $item->post_author;
        $user      = get_userdata($vendor_id);
        if (! $user instanceof \WP_User) {
            return esc_html__('Unknown', 'mhm-rentiva');
        }

        return esc_html($user->display_name ?: $user->user_login) . ' <small>#' . esc_html((string) $vendor_id) . '</small>';
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_amount(\WP_Post $item): string
    {
        $amount = (float) get_post_meta($item->ID, '_mhm_payout_amount', true);
        if (function_exists('wc_price')) {
            return wp_kses_post(wc_price($amount));
        }

        return esc_html(number_format($amount, 2));
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_balance(\WP_Post $item): string
    {
        $vendor_id = (int) $item->post_author;
        $balance   = Ledger::get_balance($vendor_id);
        if (function_exists('wc_price')) {
            return wp_kses_post(wc_price($balance));
        }

        return esc_html(number_format($balance, 2));
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_status(\WP_Post $item): string
    {
        $status_map = array(
            'pending' => array('label' => __('Pending', 'mhm-rentiva'),  'color' => '#ca8a04'),
            'publish' => array('label' => __('Approved', 'mhm-rentiva'), 'color' => '#2f54ff'),
            'trash'   => array('label' => __('Rejected', 'mhm-rentiva'), 'color' => '#ef4444'),
        );

        $info  = $status_map[$item->post_status] ?? array('label' => esc_html($item->post_status), 'color' => '#64748b');
        $label = esc_html($info['label']);
        $color = esc_attr($info['color']);

        // Check processor sub-status.
        $processor = (string) get_post_meta($item->ID, '_mhm_payout_status', true);
        if ($processor === 'confirmed') {
            $label = esc_html__('Confirmed', 'mhm-rentiva');
            $color = '#10b981';
        } elseif ($processor === 'failed') {
            $label = esc_html__('Failed', 'mhm-rentiva');
            $color = '#ef4444';
        }

        return "<span style=\"color:{$color};font-weight:600\">{$label}</span>";
    }

    /**
     * @param \WP_Post $item
     */
    protected function column_requested(\WP_Post $item): string
    {
        return esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->post_date_gmt)));
    }

    /**
     * @param \WP_Post $item
     * @param string   $column_name
     */
    protected function column_default($item, $column_name): string
    {
        return '';
    }

    /**
     * Process bulk actions. Called by the admin page controller.
     *
     * Idempotency guard: only posts with status='pending' are approved.
     * Any ID not in pending status is silently skipped â€” no double-debit.
     *
     * @return array{approved: int, skipped: int, errors: string[]}
     */
    public static function process_bulk_approve(): array
    {
        if (! current_user_can('mhm_rentiva_approve_payout')) {
            return array('approved' => 0, 'skipped' => 0, 'errors' => array(__('Permission denied by Governance Layer.', 'mhm-rentiva')));
        }

        $raw_ids = isset($_POST['payout_ids']) && is_array($_POST['payout_ids'])
            ? array_map('absint', $_POST['payout_ids'])
            : array();

        if (empty($raw_ids)) {
            return array('approved' => 0, 'skipped' => 0, 'errors' => array());
        }

        $approved = 0;
        $skipped  = 0;
        $errors   = array();

        foreach ($raw_ids as $payout_id) {
            $post = get_post($payout_id);

            // Idempotency: skip anything that isn't truly pending.
            if (! $post instanceof \WP_Post || $post->post_status !== 'pending') {
                $skipped++;
                continue;
            }

            $result = \MHMRentiva\Core\Financial\GovernanceService::process_approval($payout_id);

            if (is_wp_error($result)) {
                $errors[] = sprintf('#%d: %s', $payout_id, $result->get_error_message());
            } else {
                $approved++;
            }
        }

        return compact('approved', 'skipped', 'errors');
    }
}
