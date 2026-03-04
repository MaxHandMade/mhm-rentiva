<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Growth;

if (! defined('ABSPATH')) {
	exit;
}

final class FunnelDashboard
{
	public static function register(): void
	{
		add_action('admin_menu', array(self::class, 'add_submenu_page'), 30);
	}

	public static function add_submenu_page(): void
	{
		add_submenu_page(
			'mhm-rentiva',
			esc_html__('Upgrade Funnel', 'mhm-rentiva'),
			esc_html__('Growth', 'mhm-rentiva'),
			'manage_options',
			'mhm-rentiva-growth-funnel',
			array(self::class, 'render')
		);
	}

	public static function render(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'mhm-rentiva'));
		}

		$service = new FunnelAnalyticsService();
		$totals = $service->get_totals();
		$rows = $service->get_last_30_days();
		$variant_stats = $service->get_variant_breakdown();

		$views = (int) ($totals['views'] ?? 0);
		$clicks = (int) ($totals['clicks'] ?? 0);
		$conversion_rate = (float) ($totals['conversion_rate'] ?? 0.0);

		echo '<div class="wrap mhm-rentiva-wrap">';
		echo '<h1>' . esc_html__('Upgrade Funnel', 'mhm-rentiva') . '</h1>';
		echo '<p class="description">' . esc_html__('Read-only analytics for Lite to Pro conversion signals over the last 30 days.', 'mhm-rentiva') . '</p>';

		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">';
		echo '<div class="postbox" style="min-width:220px;padding:12px;">';
		echo '<h2 style="margin:0 0 8px 0;">' . esc_html__('License Page Views', 'mhm-rentiva') . '</h2>';
		echo '<p style="font-size:20px;font-weight:600;margin:0;">' . esc_html((string) $views) . '</p>';
		echo '</div>';
		echo '<div class="postbox" style="min-width:220px;padding:12px;">';
		echo '<h2 style="margin:0 0 8px 0;">' . esc_html__('Upgrade CTA Clicks', 'mhm-rentiva') . '</h2>';
		echo '<p style="font-size:20px;font-weight:600;margin:0;">' . esc_html((string) $clicks) . '</p>';
		echo '</div>';
		echo '<div class="postbox" style="min-width:220px;padding:12px;">';
		echo '<h2 style="margin:0 0 8px 0;">' . esc_html__('Conversion Rate', 'mhm-rentiva') . '</h2>';
		echo '<p style="font-size:20px;font-weight:600;margin:0;">' . esc_html(number_format_i18n($conversion_rate * 100, 2) . '%') . '</p>';
		echo '</div>';
		echo '</div>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Date', 'mhm-rentiva') . '</th>';
		echo '<th>' . esc_html__('Views', 'mhm-rentiva') . '</th>';
		echo '<th>' . esc_html__('Clicks', 'mhm-rentiva') . '</th>';
		echo '<th>' . esc_html__('Conversion', 'mhm-rentiva') . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if (array() === $rows) {
			echo '<tr><td colspan="4">' . esc_html__('No funnel data found for the last 30 days.', 'mhm-rentiva') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				$date = (string) ($row['date'] ?? '');
				$row_views = (int) ($row['views'] ?? 0);
				$row_clicks = (int) ($row['clicks'] ?? 0);
				$row_conversion = (float) ($row['conversion'] ?? 0.0);

				echo '<tr>';
				echo '<td>' . esc_html($date) . '</td>';
				echo '<td>' . esc_html((string) $row_views) . '</td>';
				echo '<td>' . esc_html((string) $row_clicks) . '</td>';
				echo '<td>' . esc_html(number_format_i18n($row_conversion * 100, 2) . '%') . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody>';
		echo '</table>';

		echo '<h2 style="margin-top:20px;">' . esc_html__('Variant Performance', 'mhm-rentiva') . '</h2>';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Variant', 'mhm-rentiva') . '</th>';
		echo '<th>' . esc_html__('Views', 'mhm-rentiva') . '</th>';
		echo '<th>' . esc_html__('Clicks', 'mhm-rentiva') . '</th>';
		echo '<th>' . esc_html__('Conversion', 'mhm-rentiva') . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ($variant_stats as $variant => $data) {
			$variant_views = (int) ($data['views'] ?? 0);
			$variant_clicks = (int) ($data['clicks'] ?? 0);
			$variant_conversion = (float) ($data['conversion'] ?? 0.0);

			echo '<tr>';
			echo '<td>' . esc_html((string) $variant) . '</td>';
			echo '<td>' . esc_html((string) $variant_views) . '</td>';
			echo '<td>' . esc_html((string) $variant_clicks) . '</td>';
			echo '<td>' . esc_html(number_format_i18n($variant_conversion * 100, 2) . '%') . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}
}
