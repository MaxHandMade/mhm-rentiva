<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded vendor-scoped query, result set depends on current user.

/**
 * Vendor Bookings Shortcode
 *
 * Displays all bookings received on the current vendor's vehicles.
 * Requires the user to be logged in with the 'rentiva_vendor' role.
 *
 * @shortcode rentiva_vendor_bookings
 */
final class VendorBookings extends AbstractAccountShortcode
{

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vendor_bookings';
    }

    protected static function get_template_path(): string
    {
        // No external template file — render_template() is overridden below.
        return '';
    }

    protected static function get_default_attributes(): array
    {
        return array(
            'limit' => '10',
        );
    }

    protected static function prepare_template_data(array $atts): array
    {
        $user = wp_get_current_user();

        // Require rentiva_vendor role.
        if (!$user->exists() || !in_array('rentiva_vendor', (array) $user->roles, true)) {
            return array('error' => 'not_vendor');
        }

        // Fetch vehicle IDs owned by this vendor.
        $vehicle_ids = get_posts(array(
            'post_type'      => 'vehicle',
            'author'         => $user->ID,
            'post_status'    => array('publish', 'pending'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ));

        if (empty($vehicle_ids)) {
            return array('error' => 'no_vehicles');
        }

        global $wpdb;

        $per_page     = max(1, (int) ($atts['limit'] ?? 10));
        $placeholders = implode(',', array_fill(0, count($vehicle_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders contains only %d tokens.
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID, p.post_status,
                        vm.meta_value AS vehicle_id,
                        sm.meta_value AS booking_status,
                        dm.meta_value AS date_start,
                        em.meta_value AS date_end,
                        cm.meta_value AS customer_name
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} vm ON vm.post_id = p.ID AND vm.meta_key = '_mhm_vehicle_id'
                 LEFT JOIN  {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = '_mhm_status'
                 LEFT JOIN  {$wpdb->postmeta} dm ON dm.post_id = p.ID AND dm.meta_key = '_mhm_pickup_date'
                 LEFT JOIN  {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhm_dropoff_date'
                 LEFT JOIN  {$wpdb->postmeta} cm ON cm.post_id = p.ID AND cm.meta_key = '_mhm_customer_name'
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status NOT IN ('trash','auto-draft')
                 AND CAST(vm.meta_value AS UNSIGNED) IN ($placeholders)
                 ORDER BY p.ID DESC
                 LIMIT %d",
                ...[...$vehicle_ids, $per_page]
            )
        );

        return array(
            'bookings'    => $bookings ?? array(),
            'vehicle_ids' => $vehicle_ids,
        );
    }

    /**
     * Override render_template() to produce inline HTML — no external template file needed.
     *
     * @param array $template_data Data returned by prepare_template_data().
     * @return string
     */
    protected static function render_template(array $template_data): string
    {
        // Not-a-vendor guard.
        if (isset($template_data['error']) && $template_data['error'] === 'not_vendor') {
            return '';
        }

        // No vehicles yet.
        if (isset($template_data['error']) && $template_data['error'] === 'no_vehicles') {
            return '<div class="mhm-rentiva-account-page"><div class="mhm-account-content"><div class="rv-empty-state">'
                . '<p>' . esc_html__('You have no vehicle listings yet.', 'mhm-rentiva') . '</p>'
                . '</div></div></div>';
        }

        $bookings = $template_data['bookings'] ?? array();

        $status_labels = array(
            'pending'     => esc_html__('Pending', 'mhm-rentiva'),
            'confirmed'   => esc_html__('Confirmed', 'mhm-rentiva'),
            'approved'    => esc_html__('Confirmed', 'mhm-rentiva'),
            'in_progress' => esc_html__('In Progress', 'mhm-rentiva'),
            'completed'   => esc_html__('Completed', 'mhm-rentiva'),
            'cancelled'   => esc_html__('Cancelled', 'mhm-rentiva'),
        );

        $status_colors = array(
            'pending'     => 'background:#ffc107;color:#212529;',
            'confirmed'   => 'background:#28a745;color:#fff;',
            'approved'    => 'background:#28a745;color:#fff;',
            'in_progress' => 'background:#17a2b8;color:#fff;',
            'completed'   => 'background:#6c757d;color:#fff;',
            'cancelled'   => 'background:#dc3545;color:#fff;',
        );

        ob_start();
        ?>
        <div class="mhm-rentiva-account-page">
            <div class="mhm-account-content">
                <div class="rv-vendor-bookings" data-testid="vendor-bookings-page">

                    <div class="section-header">
                        <h2><?php esc_html_e('Booking Requests', 'mhm-rentiva'); ?></h2>
                    </div>

                    <?php if (empty($bookings)) : ?>
                        <div class="rv-empty-state">
                            <p><?php esc_html_e('No bookings have been received yet.', 'mhm-rentiva'); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="rv-table-wrapper">
                            <table class="rv-bookings-table mhm-table" data-testid="vendor-bookings-table">
                                <thead>
                                    <tr>
                                        <th style="width:50px;"><?php esc_html_e('ID', 'mhm-rentiva'); ?></th>
                                        <th><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></th>
                                        <th><?php esc_html_e('Customer', 'mhm-rentiva'); ?></th>
                                        <th style="white-space:nowrap;"><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></th>
                                        <th style="white-space:nowrap;"><?php esc_html_e('Dropoff', 'mhm-rentiva'); ?></th>
                                        <th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking) : ?>
                                        <?php
                                        $booking_status = (string) ($booking->booking_status ?? '');
                                        $label          = $status_labels[ $booking_status ] ?? esc_html(ucfirst($booking_status));
                                        $badge_style    = $status_colors[ $booking_status ] ?? 'background:#6c757d;color:#fff;';
                                        $vehicle_title  = get_the_title((int) $booking->vehicle_id) ?: esc_html__('N/A', 'mhm-rentiva');
                                        $date_format    = get_option('date_format');
                                        $date_start_fmt = $booking->date_start ? date_i18n($date_format, strtotime($booking->date_start)) : '—';
                                        $date_end_fmt   = $booking->date_end   ? date_i18n($date_format, strtotime($booking->date_end))   : '—';
                                        ?>
                                        <tr>
                                            <td class="rv-booking-id">#<?php echo esc_html($booking->ID); ?></td>
                                            <td class="rv-vehicle-name"><?php echo esc_html($vehicle_title); ?></td>
                                            <td class="rv-customer-name"><?php echo esc_html($booking->customer_name ?? '—'); ?></td>
                                            <td class="rv-booking-date"><?php echo esc_html($date_start_fmt); ?></td>
                                            <td class="rv-booking-date"><?php echo esc_html($date_end_fmt); ?></td>
                                            <td class="rv-booking-status">
                                                <span class="status-badge status-<?php echo esc_attr($booking_status); ?>"
                                                      style="<?php echo esc_attr($badge_style); ?> padding:2px 8px; border-radius:4px; font-size:0.85em; white-space:nowrap;">
                                                    <?php echo esc_html($label); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                </div><!-- .rv-vendor-bookings -->
            </div><!-- .mhm-account-content -->
        </div><!-- .mhm-rentiva-account-page -->
        <?php
        return (string) ob_get_clean();
    }
}
