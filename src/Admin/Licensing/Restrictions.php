<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

final class Restrictions
{
    /**
     * Register restriction hooks
     */
    public static function register(): void
    {
        // Vehicles limit
        add_action('admin_menu', [self::class, 'maybeHideAddNewVehicle']);
        add_action('load-post-new.php', [self::class, 'maybeBlockVehicleCreation']);
        add_action('admin_notices', [self::class, 'vehicleLimitNotice']);

        // Bookings limit
        add_action('load-post-new.php', [self::class, 'maybeBlockBookingCreation']);
        add_action('admin_notices', [self::class, 'bookingLimitNotice']);

        // Customers limit
        add_action('admin_menu', [self::class, 'maybeHideAddNewCustomer']);
        add_action('admin_init', [self::class, 'maybeBlockCustomerCreation']);
        add_action('admin_notices', [self::class, 'customerLimitNotice']);

        // Export gate
        add_action('admin_init', [self::class, 'disableExportIfLite']);

        // Clamp export/report args
        add_filter('mhm_rentiva_export_args', [self::class, 'clampExportArgs']);

        // Settings info
        add_action('admin_notices', [self::class, 'settingsInfoNotice']);

        // Minimal admin CSS (overlay for Pro-locked groups)
        add_action('admin_head', [self::class, 'printAdminCss']);
    }

    /**
     * Print admin CSS for Pro-locked elements
     */
    public static function printAdminCss(): void
    {
        echo '<style>
            .mhm-pro-locked{position:relative;opacity:.6;pointer-events:none}
            .mhm-pro-locked:after{content:"Pro";position:absolute;top:-8px;right:-8px;background:#2271b1;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px}
            .mhm-pro-note{margin-top:6px;color:#555}
        </style>';
    }

    /**
     * Get current vehicle count
     * 
     * @return int Vehicle count
     */
    public static function vehicleCount(): int
    {
        $q = new \WP_Query([
            'post_type'      => 'vehicle',
            'post_status'    => ['publish', 'pending', 'private'], // EXCLUDING draft/trash
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);
        return (int) ($q->found_posts ?? 0);
    }

    /**
     * Hide add new vehicle menu if limit reached
     */
    public static function maybeHideAddNewVehicle(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        if (self::vehicleCount() >= Mode::maxVehicles()) {
            remove_submenu_page('edit.php?post_type=vehicle', 'post-new.php?post_type=vehicle');
        }
    }

    /**
     * Block vehicle creation if limit reached
     */
    public static function maybeBlockVehicleCreation(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        $pt = $_GET['post_type'] ?? '';
        if ($pt === 'vehicle' && self::vehicleCount() >= Mode::maxVehicles()) {
            wp_die(
                esc_html__('Rentiva Lite allows adding maximum 3 vehicles. Activate your license to add more vehicles.', 'mhm-rentiva'),
                esc_html__('Vehicle Limit Exceeded', 'mhm-rentiva'),
                ['response' => 403]
            );
        }
    }

    /**
     * Show vehicle limit notice
     */
    public static function vehicleLimitNotice(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'edit-vehicle') {
            $count = self::vehicleCount();
            $max   = Mode::maxVehicles();
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                esc_html(sprintf(__('Rentiva Lite: %1$d/%2$d vehicles used. Enter your license for unlimited vehicles and online payments.', 'mhm-rentiva'), $count, $max))
            );
        }
    }

    /**
     * Get current booking count
     * 
     * @return int Booking count
     */
    public static function bookingCount(): int
    {
        $q = new \WP_Query([
            'post_type'      => 'vehicle_booking',
            'post_status'    => ['publish', 'pending', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);
        return (int) ($q->found_posts ?? 0);
    }

    /**
     * Block booking creation if limit reached
     */
    public static function maybeBlockBookingCreation(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        $pt = $_GET['post_type'] ?? '';
        if ($pt === 'vehicle_booking' && self::bookingCount() >= Mode::maxBookings()) {
            wp_die(
                esc_html__('Rentiva Lite has reached booking limit. Activate your license to add more bookings.', 'mhm-rentiva'),
                esc_html__('Booking Limit Exceeded', 'mhm-rentiva'),
                ['response' => 403]
            );
        }
    }

    /**
     * Show booking limit notice
     */
    public static function bookingLimitNotice(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'edit-vehicle_booking') {
            $cnt = self::bookingCount();
            $max = Mode::maxBookings();
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html(sprintf(__('Rentiva Lite: %1$d/%2$d bookings used. Activate your license to remove this limit.', 'mhm-rentiva'), $cnt, $max))
            );
        }
    }

    /**
     * Disable export if Lite version
     */
    public static function disableExportIfLite(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        if (isset($_GET['action']) && strpos((string) $_GET['action'], 'mhm_rentiva_export') !== false) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('Export is available in Pro version. Enter your license key to enable.', 'mhm-rentiva') . '</p></div>';
            });
        }
    }

    /**
     * Clamp export arguments for Lite version
     * 
     * @param array $args Export arguments
     * @return array Clamped arguments
     */
    public static function clampExportArgs(array $args): array
    {
        if (Mode::isPro()) {
            return $args;
        }

        $maxDays = Mode::reportsMaxRangeDays();
        if (!empty($args['date_from']) || !empty($args['date_to'])) {
            $to   = !empty($args['date_to']) ? strtotime((string) $args['date_to']) : time();
            $from = !empty($args['date_from']) ? strtotime((string) $args['date_from']) : ($to - ($maxDays * DAY_IN_SECONDS));
            if (($to - $from) > ($maxDays * DAY_IN_SECONDS)) {
                $from = $to - ($maxDays * DAY_IN_SECONDS);
            }
            $args['date_from'] = gmdate('Y-m-d', $from);
            $args['date_to']   = gmdate('Y-m-d', $to);
        }
        $args['limit'] = min((int) ($args['limit'] ?? 1000), Mode::reportsMaxRows());
        return $args;
    }

    /**
     * Show settings info notice for Lite version
     */
    public static function settingsInfoNotice(): void
    {
        if (!Mode::isLite()) {
            return;
        }
        if (!isset($_GET['page']) || $_GET['page'] !== 'mhm-rentiva-settings') {
            return;
        }
        echo '<div class="notice notice-info"><p>' .
            esc_html__('You are using Rentiva Lite. Online payments (Stripe, PayTR), Export, advanced reports and higher booking limits are available in Pro version.', 'mhm-rentiva') .
            '</p></div>';
    }

    /**
     * Begin Pro-locked section
     */
    public static function beginProLocked(): void
    {
        if (Mode::isLite()) {
            echo '<div class="mhm-pro-locked">';
        }
    }

    /**
     * End Pro-locked section
     * 
     * @param string $note Optional note
     */
    public static function endProLocked(string $note = ''): void
    {
        if (Mode::isLite()) {
            if ($note === '') {
                $note = __('This setting is available in Pro version.', 'mhm-rentiva');
            }
            echo '<p class="description mhm-pro-note">' . esc_html($note) . '</p></div>';
        }
    }

    /**
     * Payment gateway restriction
     * 
     * @param array $gateways Available gateways
     * @return array Allowed gateways
     */
    public static function restrict_payment_gateways(array $gateways): array
    {
        $allowed = Mode::allowedGateways();
        return array_intersect($gateways, $allowed);
    }

    /**
     * Check limit status
     * 
     * @return array Limit status
     */
    public static function check_limits(): array
    {
        return [
            'vehicles' => [
                'current' => self::vehicleCount(),
                'max' => Mode::maxVehicles(),
                'exceeded' => self::vehicleCount() >= Mode::maxVehicles(),
            ],
            'bookings' => [
                'current' => self::bookingCount(),
                'max' => Mode::maxBookings(),
                'exceeded' => self::bookingCount() >= Mode::maxBookings(),
            ],
            'is_pro' => Mode::isPro(),
        ];
    }

    /**
     * Pro feature warning
     * 
     * @param string $feature_name Feature name
     */
    public static function proFeatureNotice(string $feature_name = ''): void
    {
        if (Mode::isPro()) {
            return;
        }

        $message = $feature_name
            ? sprintf(__('%s is available in Pro version. Enter your license key to enable.', 'mhm-rentiva'), $feature_name)
            : __('This feature is available in Pro version. Enter your license key to enable.', 'mhm-rentiva');

        echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Pro feature gate
     * 
     * @param string $feature_name Feature name
     */
    public static function gateProFeature(string $feature_name = ''): void
    {
        if (Mode::isPro()) {
            return;
        }

        wp_die(
            $feature_name
                ? sprintf(__('%s is available in Pro version. Enter your license key to enable.', 'mhm-rentiva'), $feature_name)
                : __('This feature is available in Pro version. Enter your license key to enable.', 'mhm-rentiva'),
            __('Pro Feature', 'mhm-rentiva'),
            ['response' => 403]
        );
    }

    /**
     * Get current customer count
     * 
     * @return int Customer count
     */
    public static function customerCount(): int
    {
        global $wpdb;

        // Total customer count (WordPress users)
        $query = "
            SELECT COUNT(DISTINCT u.ID) as total
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                AND email_meta.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
            WHERE u.ID > 1
        ";

        return (int) $wpdb->get_var($query);
    }

    /**
     * Hide add new customer menu if limit reached
     */
    public static function maybeHideAddNewCustomer(): void
    {
        if (Mode::isPro()) {
            return;
        }

        $current = self::customerCount();
        $max = Mode::maxCustomers();

        if ($current >= $max) {
            remove_submenu_page('mhm-rentiva-customers', 'mhm-rentiva-add-customer');
        }
    }

    /**
     * Block customer creation if limit reached
     */
    public static function maybeBlockCustomerCreation(): void
    {
        if (Mode::isPro()) {
            return;
        }

        // AJAX customer addition control
        if (isset($_POST['action']) && $_POST['action'] === 'mhm_rentiva_add_customer') {
            $current = self::customerCount();
            $max = Mode::maxCustomers();

            if ($current >= $max) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('You can add up to %d customers in Lite version. Enter your license key to upgrade to Pro.', 'mhm-rentiva'),
                        $max
                    )
                ]);
            }
        }
    }

    /**
     * Show customer limit notice
     */
    public static function customerLimitNotice(): void
    {
        if (Mode::isPro()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['mhm-rentiva-customers', 'mhm-rentiva-add-customer'], true)) {
            return;
        }

        $current = self::customerCount();
        $max = Mode::maxCustomers();

        if ($current >= $max) {
            $message = sprintf(
                __('You can add up to %d customers in Lite version. Enter your license key to upgrade to Pro.', 'mhm-rentiva'),
                $max
            );
            echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
