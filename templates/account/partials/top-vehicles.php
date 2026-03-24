<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.NonceVerification.Recommended

// templates/account/partials/top-vehicles.php

declare(strict_types=1);

use MHMRentiva\Core\Dashboard\SparklineRenderer;

if (! defined('ABSPATH')) {
    exit;
}

$top_vehicles = is_array($top_vehicles ?? null) ? $top_vehicles : array();

if (empty($top_vehicles)) {
    return;
}
?>
<div class="mhm-rentiva-dashboard__section">
    <div class="mhm-rentiva-dashboard__section-head">
        <h3><?php esc_html_e('Top Performing Vehicles (30d)', 'mhm-rentiva'); ?></h3>
    </div>
    <div class="mhm-rentiva-dashboard__table-wrap">
        <table class="mhm-rentiva-dashboard__table mhm-rentiva-dashboard__top-vehicles-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Revenue', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Occupancy', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Rev. Cancellations', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Trend (7d)', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_vehicles as $veh) :
                    $sparkline = is_array($veh['sparkline_7d'] ?? null) ? $veh['sparkline_7d'] : array_fill(0, 7, 0.0);
                    $revenue_formatted = function_exists('wc_price') ? call_user_func('wc_price', (float)$veh['revenue']) : sprintf('₺%.2f', $veh['revenue']);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($veh['title'] ?? ''); ?></strong></td>
                        <td class="is-value"><?php echo wp_kses_post((string) $revenue_formatted); ?></td>
                        <td><?php echo esc_html(number_format((float)($veh['occupancy_rate'] ?? 0), 1)) . '%'; ?></td>
                        <td><?php echo esc_html((string)($veh['cancellation_count'] ?? 0)); ?></td>
                        <td class="mini-sparkline" style="min-width: 100px;">
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo SparklineRenderer::render($sparkline, 100, 30, '#4ade80'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>