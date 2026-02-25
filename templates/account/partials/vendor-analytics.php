<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

use MHMRentiva\Core\Dashboard\SparklineRenderer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Analytics partial — Revenue tab content.
 *
 * Consumed by user-dashboard.php when $active_tab === 'revenue'.
 * Receives $analytics array from DashboardDataProvider via $dashboard['analytics'].
 *
 * @since 4.21.0
 */

$analytics = is_array($analytics ?? null) ? $analytics : array();

$revenue_30d       = (float) ($analytics['revenue_30d']       ?? 0.0);
$revenue_30d_prev  = (float) ($analytics['revenue_30d_prev']  ?? 0.0);
$growth_7d         = isset($analytics['growth_7d']) && is_numeric($analytics['growth_7d'])
    ? (float) $analytics['growth_7d']
    : null;
$avg_booking       = (float) ($analytics['avg_booking_value'] ?? 0.0);
$sparkline_7d      = is_array($analytics['sparkline_7d']  ?? null) ? $analytics['sparkline_7d']  : array_fill(0, 7, 0.0);
$sparkline_30d     = is_array($analytics['sparkline_30d'] ?? null) ? $analytics['sparkline_30d'] : array_fill(0, 30, 0.0);

// --- Growth badge resolution ---
$growth_class = 'is-neutral';
$growth_label = '—';
if ($growth_7d !== null) {
    if ($growth_7d > 0.0) {
        $growth_class = 'is-up';
        $growth_label = '↑ ' . number_format($growth_7d, 1) . '%';
    } elseif ($growth_7d < 0.0) {
        $growth_class = 'is-down';
        $growth_label = '↓ ' . number_format(abs($growth_7d), 1) . '%';
    } else {
        $growth_class = 'is-neutral';
        $growth_label = '0.0%';
    }
}

// --- Currency formatting ---
$format_currency = static function (float $amount): string {
    if (function_exists('wc_price')) {
        return (string) wc_price($amount);
    }
    $symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '₺';
    return $symbol . number_format($amount, 2, '.', ',');
};

// --- Zero state detection ---
$has_data = $revenue_30d > 0.0 || $avg_booking > 0.0;
?>

<div class="mhm-rentiva-dashboard__analytics">

    <!-- KPI Row: Revenue / Growth / Avg Booking -->
    <div class="mhm-rentiva-dashboard__analytics-kpis">

        <!-- 30d Revenue -->
        <div class="mhm-rentiva-dashboard__kpi-card is-analytics">
            <div class="mhm-rentiva-dashboard__kpi-header">
                <div class="mhm-rentiva-dashboard__kpi-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
                        <path d="M4 19H20M7 16V10M12 16V5M17 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="mhm-rentiva-dashboard__kpi-label"><?php esc_html_e('Revenue (30d)', 'mhm-rentiva'); ?></div>
            </div>
            <div class="mhm-rentiva-dashboard__kpi-value is-currency">
                <?php echo wp_kses_post($format_currency($revenue_30d)); ?>
            </div>
            <div class="mhm-rentiva-dashboard__kpi-meta"><?php esc_html_e('Cleared net, last 30 days', 'mhm-rentiva'); ?></div>
        </div>

        <!-- 7d Growth -->
        <div class="mhm-rentiva-dashboard__kpi-card is-analytics">
            <div class="mhm-rentiva-dashboard__kpi-header">
                <div class="mhm-rentiva-dashboard__kpi-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
                        <path d="M3 17L9 11L13 15L21 7M21 7H15M21 7V13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="mhm-rentiva-dashboard__kpi-label"><?php esc_html_e('Growth (7d)', 'mhm-rentiva'); ?></div>
            </div>
            <div class="mhm-rentiva-dashboard__kpi-value">
                <span class="mhm-rentiva-dashboard__kpi-trend <?php echo esc_attr($growth_class); ?>">
                    <?php echo esc_html($growth_label); ?>
                </span>
            </div>
            <div class="mhm-rentiva-dashboard__kpi-meta">
                <?php
                if ($growth_7d === null) {
                    esc_html_e('No prior period data', 'mhm-rentiva');
                } else {
                    esc_html_e('vs. prior 7 days', 'mhm-rentiva');
                }
                ?>
            </div>
        </div>

        <!-- Avg Booking Value -->
        <div class="mhm-rentiva-dashboard__kpi-card is-analytics">
            <div class="mhm-rentiva-dashboard__kpi-header">
                <div class="mhm-rentiva-dashboard__kpi-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" role="img" focusable="false">
                        <path d="M19.5 9.5V17.5C19.5 18.6046 18.6046 19.5 17.5 19.5H6.5C5.39543 19.5 4.5 18.6046 4.5 17.5V6.5C4.5 5.39543 5.39543 4.5 6.5 4.5H16.5C17.6046 4.5 18.5 5.39543 18.5 6.5V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M21 9.5V14.5C21 15.0523 20.5523 15.5 20 15.5H18C16.8954 15.5 16 14.6046 16 13.5V10.5C16 9.39543 16.8954 8.5 18 8.5H20C20.5523 8.5 21 8.94772 21 9.5Z" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                </div>
                <div class="mhm-rentiva-dashboard__kpi-label"><?php esc_html_e('Avg Booking Value', 'mhm-rentiva'); ?></div>
            </div>
            <div class="mhm-rentiva-dashboard__kpi-value is-currency">
                <?php echo wp_kses_post($format_currency($avg_booking)); ?>
            </div>
            <div class="mhm-rentiva-dashboard__kpi-meta"><?php esc_html_e('Per booking, last 30 days', 'mhm-rentiva'); ?></div>
        </div>

    </div><!-- /.mhm-rentiva-dashboard__analytics-kpis -->

    <?php if (! $has_data) : ?>
        <!-- Zero state: vendor has no cleared revenue yet -->
        <div class="mhm-rentiva-dashboard__analytics-empty">
            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <circle cx="24" cy="24" r="20" stroke="#e4e8f2" stroke-width="2" />
                <path d="M16 32V24M24 32V16M32 32V28" stroke="#b0bcd4" stroke-width="2.5" stroke-linecap="round" />
            </svg>
            <p><?php esc_html_e('No cleared revenue data yet. Analytics will appear once bookings are settled.', 'mhm-rentiva'); ?></p>
        </div>
    <?php else : ?>

        <!-- 30-Day Trend Sparkline -->
        <div class="mhm-rentiva-dashboard__section">
            <div class="mhm-rentiva-dashboard__section-head">
                <h3><?php esc_html_e('30-Day Revenue Trend', 'mhm-rentiva'); ?></h3>
                <span class="mhm-rentiva-dashboard__sparkline-range">
                    <?php
                    $from_label = date_i18n(get_option('date_format'), strtotime('-30 days'));
                    $to_label   = date_i18n(get_option('date_format'));
                    echo esc_html($from_label . ' – ' . $to_label);
                    ?>
                </span>
            </div>
            <div class="mhm-rentiva-dashboard__sparkline">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SparklineRenderer output is fully escaped internally.
                echo SparklineRenderer::render($sparkline_30d, 720, 80, '#2f54ff');
                ?>
            </div>
        </div>

        <!-- 7-Day Trend Sparkline -->
        <div class="mhm-rentiva-dashboard__section">
            <div class="mhm-rentiva-dashboard__section-head">
                <h3><?php esc_html_e('7-Day Revenue Trend', 'mhm-rentiva'); ?></h3>
                <span class="mhm-rentiva-dashboard__sparkline-range">
                    <?php
                    $from_label = date_i18n(get_option('date_format'), strtotime('-7 days'));
                    echo esc_html($from_label . ' – ' . date_i18n(get_option('date_format')));
                    ?>
                </span>
            </div>
            <div class="mhm-rentiva-dashboard__sparkline">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SparklineRenderer output is fully escaped internally.
                echo SparklineRenderer::render($sparkline_7d, 720, 80, '#8b5cf6');
                ?>
            </div>
        </div>

    <?php endif; ?>

</div><!-- /.mhm-rentiva-dashboard__analytics -->