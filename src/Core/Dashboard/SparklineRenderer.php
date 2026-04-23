<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure server-side SVG sparkline renderer.
 *
 * Produces an inline <svg> polyline from a flat array of float data points.
 * No JavaScript. No chart libraries. Safe for SSR and block-editor contexts.
 *
 * Usage:
 *   echo SparklineRenderer::render($points, 200, 60);
 *
 * Output example:
 *   <svg viewBox="0 0 200 60" xmlns="http://www.w3.org/2000/svg" ...>
 *     <polyline points="0,54 40,20 80,60 ..." />
 *   </svg>
 *
 * Math:
 *   x_i = i * (width / max(count - 1, 1))
 *   y_i = height - ((val - min) / max(range, 0.01)) * (height - padding * 2) + padding
 *   (vertical padding prevents SVG clipping on extremes)
 *
 * Zero-data guard:
 *   If all points === 0.0, renders a flat baseline at y = height - padding.
 *   This is visually informative ("flat / no data") rather than a blank space.
 *
 * @since 4.21.0
 */
final class SparklineRenderer {

    private const DEFAULT_WIDTH  = 200;
    private const DEFAULT_HEIGHT = 60;
    private const VERTICAL_PAD   = 4; // px top/bottom inner padding to prevent clip

    /**
     * Render a sparkline SVG string from an array of float data points.
     *
     * @param float[] $points     Ordered data values (oldest → newest).
     * @param int     $width      SVG canvas width in px.
     * @param int     $height     SVG canvas height in px.
     * @param string  $color      Stroke colour (hex or CSS keyword).
     * @return string             Safe inline SVG string. Escaped for direct echo.
     */
    public static function render(
        array $points,
        int $width = self::DEFAULT_WIDTH,
        int $height = self::DEFAULT_HEIGHT,
        string $color = '#2f54ff'
    ): string {
        if (count($points) === 0) {
            return '';
        }

        // Ensure all values are non-negative floats.
        $points = array_values(array_map('floatval', $points));
        $count  = count($points);

        $min_val = min($points);
        $max_val = max($points);
        $range   = $max_val - $min_val;

        $pad    = self::VERTICAL_PAD;
        $draw_h = $height - ( $pad * 2 );
        $x_step = $count > 1 ? ( $width / ( $count - 1 ) ) : 0.0;

        $svg_points = array();

        foreach ($points as $i => $val) {
            $x = round($i * $x_step, 2);

            // Normalize y: 0 → bottom, max → top, with vertical padding.
            if ($range < 0.01) {
                // All values equal or all zero → flat baseline at bottom.
                $y = $height - $pad;
            } else {
                $y = round($pad + $draw_h - ( ( $val - $min_val ) / $range ) * $draw_h, 2);
            }

            $svg_points[] = "{$x},{$y}";
        }

        $points_attr = esc_attr(implode(' ', $svg_points));
        $color_attr  = esc_attr($color);

        return sprintf(
            '<svg viewBox="0 0 %1$d %2$d" xmlns="http://www.w3.org/2000/svg" '
                . 'aria-hidden="true" focusable="false" '
                . 'class="mhm-rentiva-dashboard__sparkline-svg" '
                . 'preserveAspectRatio="none">'
                . '<polyline points="%3$s" '
                . 'fill="none" stroke="%4$s" stroke-width="1.5" '
                . 'stroke-linecap="round" stroke-linejoin="round" />'
                . '</svg>',
            $width,
            $height,
            $points_attr,
            $color_attr
        );
    }
}
