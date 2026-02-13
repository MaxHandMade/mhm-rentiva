<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * MHM Rentiva - Demo Data Seeder (v15 - Notification Suite)
 * ----------------------------------------------------------------
 * 1. Expanded Fleet: Includes Economy, Mid-range, Luxury, and VIP vehicles.
 * 2. Key Standardization: Optimized metadata keys for core business logic.
 * 3. Surgical Cleanup: Safe deletion mechanism for demo data only.
 * 4. Fixed Deposit: Implements a universal 10% deposit rate policy.
 * 5. Notification Suite: Triggers internal hooks for email testing.
 */
final class DemoSeeder
{

    public const PT_VEHICLE = 'vehicle';
    public const PT_BOOKING = 'vehicle_booking';
    public const PT_SERVICE = 'vehicle_addon';
    public const PT_MESSAGE = 'mhm_message';
    public const TAX_CAT    = 'vehicle_category';

    /**
     * Metadata keys mapping
     */
    private array $keys = array(
        'price'         => '_mhm_rentiva_price_per_day',
        'deposit'       => '_mhm_rentiva_deposit',
        'deposit_rate'  => '_mhm_rentiva_deposit_rate',
        'brand'         => '_mhm_rentiva_brand',
        'model'         => '_mhm_rentiva_model',
        'year'          => '_mhm_rentiva_year',
        'color'         => '_mhm_rentiva_color',
        'engine'        => '_mhm_rentiva_engine_power',
        'plate'         => '_mhm_rentiva_license_plate',
        'km'            => '_mhm_rentiva_mileage',
        'seats'         => '_mhm_rentiva_seats',
        'doors'         => '_mhm_rentiva_doors',
        'trans_pax'     => '_mhm_transfer_max_pax',
        'big_luggage'   => '_mhm_vehicle_max_big_luggage',
        'service_type'  => '_mhm_vehicle_service_type',
        'transmission'  => '_mhm_rentiva_transmission',
        'fuel'          => '_mhm_rentiva_fuel_type',
        'features'      => '_mhm_rentiva_features',
        'status_veh'    => '_mhm_vehicle_status',
        'status'        => '_mhm_status',
        'vehicle_id'    => '_mhm_vehicle_id',
        'customer_id'   => '_mhm_customer_user_id',
        'pickup_date'   => '_mhm_start_date',
        'pickup_time'   => '_mhm_start_time',
        'dropoff_date'  => '_mhm_end_date',
        'dropoff_time'  => '_mhm_end_time',
        'start_date'    => '_mhm_start_date',
        'end_date'      => '_mhm_end_date',
        'start_time'    => '_mhm_start_time',
        'end_time'      => '_mhm_end_time',
        'start_ts'      => '_mhm_start_ts',
        'end_ts'        => '_mhm_end_ts',
        'total_price'   => '_mhm_total_price',
        'rental_days'   => '_mhm_rental_days',
        'pay_type'      => '_mhm_payment_type',
        'paid_amt'      => '_mhm_paid_amount',
        'remain_amt'    => '_mhm_remaining_amount',
        'pay_status'    => '_mhm_payment_status',
        'wc_order_id'   => '_mhm_wc_order_id',
        'cust_name'     => '_mhm_customer_name',
        'cust_email'    => '_mhm_customer_email',
        'cust_phone'    => '_mhm_customer_phone',
        'op_type'       => '_mhm_operation_type',
    );

    private array $categories = array('Economy', 'Mid-Range', 'Luxury', 'SUV', 'Minivan (VIP)');

    private array $names = array(
        'John Smith',
        'Jane Doe',
        'Michael Brown',
        'Emily Davis',
        'David Wilson',
        'Sarah Taylor',
        'James Miller',
        'Linda White'
    );

    /**
     * Run the seeder logic
     */
    public function run(bool $do_cleanup = false): string
    {
        $this->enable_email_simulation();
        $msg = $do_cleanup ? $this->cleanup() . "\n" : '';

        // 1. Core Data Seed
        $this->seed_categories();
        $loc_ids = $this->seed_transfers_sql();
        $this->seed_routes_sql($loc_ids);

        // 2. Users & Services Seed
        $user_ids = $this->seed_users(8);
        $this->seed_addons_verified();
        $this->seed_messages_expanded($user_ids);

        // 3. Inventory & Transactions Seed
        $img_id      = $this->get_dummy_image_id();
        $vehicle_ids = $this->seed_vehicles($img_id);
        $this->seed_bookings($vehicle_ids, $user_ids);

        // 4. Test Email Deliverability
        $this->test_email_delivery();

        $this->disable_email_simulation();

        return $msg . __('✅ Demo v15 (Notification Suite) Completed! Check CLI logs for email triggers.', 'mhm-rentiva');
    }

    /**
     * Simulation mode: catches wp_mail and logs to CLI instead of sending
     */
    private function enable_email_simulation(): void
    {
        add_filter('pre_wp_mail', array($this, 'simulate_mail'), 10, 2);
        add_action('mhm_rentiva_email_sent', array($this, 'log_email_trigger'), 10, 5);
    }

    private function disable_email_simulation(): void
    {
        remove_filter('pre_wp_mail', array($this, 'simulate_mail'), 10);
        remove_action('mhm_rentiva_email_sent', array($this, 'log_email_trigger'), 10);
    }

    public function simulate_mail($return, $args)
    {
        if (class_exists('\WP_CLI')) {
            \call_user_func(array('\WP_CLI', 'line'), sprintf(
                "   [MAIL MOCK] To: %s | Subject: %s",
                $args['to'],
                $args['subject']
            ));
        }
        return true; // Stop actual sending
    }

    public function log_email_trigger($key, $to, $ok, $subject, $context)
    {
        $booking_id = $context['booking']['id'] ?? 'N/A';
        $log_msg = sprintf("Email Template [%s] triggered for Booking #%s to [%s]", $key, $booking_id, $to);

        \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info('Seeder Log', array('message' => $log_msg));

        if (class_exists('\WP_CLI')) {
            \call_user_func(array('\WP_CLI', 'success'), $log_msg);
        }
    }

    /**
     * Manually trigger one of each email type for verification
     */
    public function test_email_delivery(): void
    {
        // Get the last created booking
        $last_booking = \get_posts(array(
            'post_type'      => self::PT_BOOKING,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => '_mhm_is_demo',
            'meta_value'     => '1',
            'fields'         => 'ids'
        ));

        if (empty($last_booking)) {
            return;
        }

        $bid = (int) $last_booking[0];

        if (class_exists('\WP_CLI')) {
            \call_user_func(array('\WP_CLI', 'line'), "--- Running Email Delivery Test Suite ---");
        }

        // 1. Trigger Customer Notification
        if (class_exists('\MHMRentiva\Admin\Emails\Core\Mailer')) {
            \MHMRentiva\Admin\Emails\Core\Mailer::sendBookingEmail('booking_created_customer', $bid, 'customer');
        }

        // 2. Trigger Admin Notification
        if (class_exists('\MHMRentiva\Admin\Emails\Core\Mailer')) {
            \MHMRentiva\Admin\Emails\Core\Mailer::sendBookingEmail('booking_created_admin', $bid, 'admin');
        }

        // 3. Trigger Partner Notification (Mock Trigger)
        // Since there is no explicit Partner Recipient in Mailer yet, we trigger a dedicated hook
        // that developers can listen to for Partner portal integrations.
        do_action('mhm_rentiva_booking_partner_notification', $bid);
        if (class_exists('\WP_CLI')) {
            \call_user_func(array('\WP_CLI', 'success'), "Partner hook [mhm_rentiva_booking_partner_notification] triggered for Booking #$bid");
        }
    }

    /**
     * Surgical Cleanup of Demo Data
     */
    public function cleanup(): string
    {
        global $wpdb;

        // Security check: Only administrators can run cleanup
        if (! current_user_can('manage_options')) {
            return __('❌ Error: You do not have permission to perform this action.', 'mhm-rentiva');
        }

        $count = 0;
        $protected_count = 0;
        $post_types = array(self::PT_VEHICLE, self::PT_BOOKING, self::PT_SERVICE, self::PT_MESSAGE, 'shop_order', 'shop_order_placehold');

        $loc_table    = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $route_table  = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        $notif_table  = $wpdb->prefix . 'mhm_notification_queue';
        $queue_table  = $wpdb->prefix . 'mhm_rentiva_queue';
        $payment_log  = $wpdb->prefix . 'mhm_payment_log';
        $msg_logs     = $wpdb->prefix . 'mhm_message_logs';

        // 1. Surgical Post Deletion (Vehicles, Bookings, Messages, etc. - Non-Order posts)
        $standard_post_types = array(self::PT_VEHICLE, self::PT_BOOKING, self::PT_SERVICE, self::PT_MESSAGE);
        foreach ($standard_post_types as $pt) {
            $posts = \get_posts(array(
                'post_type'      => $pt,
                'numberposts'    => -1,
                'post_status'    => 'any',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array(
                        'key'     => '_mhm_is_demo',
                        'value'   => '1',
                        'compare' => '='
                    ),
                    array(
                        'key'     => '_mhm_is_demo_user',
                        'value'   => '1',
                        'compare' => '='
                    )
                ),
                'fields'         => 'ids'
            ));

            foreach ($posts as $pid) {
                if (\wp_delete_post($pid, true)) {
                    $count++;
                }
            }

            // 1a. Safety cleanup for untagged leftovers (e.g. "Manual Vehicle")
            if ($pt === self::PT_VEHICLE) {
                $untagged = \get_posts(array(
                    'post_type'   => self::PT_VEHICLE,
                    'title'       => 'Manual Vehicle',
                    'post_status' => 'any',
                    'numberposts' => -1,
                    'fields'      => 'ids'
                ));
                foreach ($untagged as $pid) {
                    if (\wp_delete_post($pid, true)) {
                        $count++;
                    }
                }
            }
        }

        // 2. Surgical WooCommerce Order Deletion (HPOS Compatible)
        if (\function_exists('\wc_get_orders')) {
            $orders = \call_user_func('\wc_get_orders', array(
                'limit'     => -1,
                'return'    => 'ids',
                'meta_key'  => '_mhm_is_demo',
                'meta_value' => '1',
            ));

            if (is_array($orders)) {
                foreach ($orders as $order_id) {
                    $order = \call_user_func('\wc_get_order', $order_id);
                    if ($order && \method_exists($order, 'delete')) {
                        if (\call_user_func(array($order, 'delete'), true)) {
                            $count++;
                        }
                    }
                }
            }
        }

        // 3. Surgical User Deletion with Safety Locks
        $current_user_id = \get_current_user_id();
        $users = \get_users(array(
            'meta_key'   => '_mhm_is_demo_user',
            'meta_value' => '1',
            'fields'     => 'ID'
        ));

        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($users as $uid) {
            $uid = (int) $uid;
            // Safety Lock: Protect current user and any administrator
            if ($uid === $current_user_id || \user_can($uid, 'manage_options')) {
                $protected_count++;
                continue;
            }
            if (\wp_delete_user($uid)) {
                $count++;
            }
        }

        // 4. Surgical Category Deletion
        $demo_terms = \get_terms(array(
            'taxonomy'   => self::TAX_CAT,
            'hide_empty' => false,
            'meta_key'   => '_mhm_is_demo',
            'meta_value' => '1'
        ));

        if (! \is_wp_error($demo_terms)) {
            foreach ($demo_terms as $term) {
                \wp_delete_term($term->term_id, self::TAX_CAT);
                $count++;
            }
        }

        // Safety fallback for categories without meta
        foreach ($this->categories as $cat_name) {
            $term = \get_term_by('name', $cat_name, self::TAX_CAT);
            if ($term) {
                \wp_delete_term((int) $term->term_id, self::TAX_CAT);
                $count++;
            }
        }

        // 5. Selective Custom Table Cleanup

        // Notification Queue: Cleanup entries for demo users
        $wpdb->query($wpdb->prepare("
            DELETE n FROM {$notif_table} n
            INNER JOIN {$wpdb->usermeta} um ON n.user_id = um.user_id
            WHERE um.meta_key = %s AND um.meta_value = %s
        ", '_mhm_is_demo_user', '1'));

        // Payment Log: Cleanup entries for demo bookings
        $wpdb->query($wpdb->prepare("
            DELETE l FROM {$payment_log} l
            INNER JOIN {$wpdb->postmeta} pm ON l.booking_id = pm.post_id
            WHERE pm.meta_key = %s AND pm.meta_value = %s
        ", '_mhm_is_demo', '1'));

        // Activity Logs & Queues: Filter by demo metadata
        $tables_by_user = array($msg_logs, $queue_table);
        foreach ($tables_by_user as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                $wpdb->query($wpdb->prepare("
                    DELETE t FROM {$table} t
                    INNER JOIN {$wpdb->usermeta} um ON t.user_id = um.user_id
                    WHERE um.meta_key = %s AND um.meta_value = %s
                ", '_mhm_is_demo_user', '1'));
            }
        }

        // Transfer Locations & Routes: Targeted cleanup based on seeded demo names
        $wpdb->query("DELETE FROM {$loc_table} WHERE name LIKE '%(IST)%' OR name LIKE '%(SAW)%' OR name = 'Taksim Square' OR name = 'Kadikoy Port' OR name = 'Taksim Meydanı' OR name = 'Kadıköy Rıhtım'");
        $wpdb->query("DELETE FROM {$route_table} WHERE origin_id NOT IN (SELECT id FROM {$loc_table}) OR destination_id NOT IN (SELECT id FROM {$loc_table})");

        // 6. Comprehensive Cache Clearing (Nuclear Purge)
        if (\class_exists('\MHMRentiva\Admin\Utilities\Dashboard\DashboardPage')) {
            \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage::clear_dashboard_cache();
        }
        if (\class_exists('\MHMRentiva\Admin\Core\Utilities\CacheManager')) {
            \MHMRentiva\Admin\Core\Utilities\CacheManager::clear_cache();
        }

        // Nuclear purge for any lingering mhm transients (addresses naming inconsistencies)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_mhm_%',
            '_transient_timeout_mhm_%'
        ));

        // 7. WooCommerce Customer Lookup Cleanup (Sticky Customers)
        $wc_customer_table = $wpdb->prefix . 'wc_customer_lookup';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wc_customer_table))) {
            $wpdb->query("DELETE FROM {$wc_customer_table} WHERE email LIKE 'test%@localhost.com'");
        }

        return sprintf(
            /* translators: 1: Deleted count, 2: Protected count */
            __('🧹 Surgical cleanup completed: %1$d demo records removed. %2$d manual records/administrators protected.', 'mhm-rentiva'),
            $count,
            $protected_count
        );
    }

    /**
     * Seed demo users
     */
    private function seed_users(int $count): array
    {
        $user_ids = array();
        for ($i = 0; $i < $count; $i++) {
            $name_idx = $i % count($this->names);
            $name  = $this->names[$name_idx];
            $email = 'test' . ($i + 1) . '@localhost.com';
            $phone = '+90 555 ' . \wp_rand(100, 999) . ' ' . \wp_rand(1000, 9999);

            $existing = \get_user_by('email', $email);
            if ($existing) {
                if (\get_user_meta($existing->ID, '_mhm_is_demo_user', true)) $user_ids[] = $existing->ID;
                continue;
            }

            $s  = \explode(' ', $name);
            $fn = $s[0];
            $ln = $s[1] ?? '';
            $uid = \wp_create_user($email, 'demo123', $email);

            if (! \is_wp_error($uid)) {
                \wp_update_user(array('ID' => $uid, 'role' => 'customer', 'display_name' => $name, 'first_name' => $fn, 'last_name' => $ln));
                \update_user_meta($uid, '_mhm_is_demo_user', '1');
                \update_user_meta($uid, 'billing_phone', $phone);
                \update_user_meta($uid, 'phone', $phone);
                \update_user_meta($uid, 'mobile', $phone);
                \update_user_meta($uid, '_mhm_customer_phone', $phone);
                $user_ids[] = $uid;
            }
        }
        return $user_ids;
    }

    /**
     * Seed demo vehicles
     */
    private function seed_vehicles($img_id): array
    {
        $ids = array();

        // 🚗 Vehicle Fleet Configuration
        $blueprint = array(
            // 1. ECONOMY
            array('qty' => 3, 'brand' => 'Volkswagen', 'model' => 'Polo', 'price' => 80, 'trans' => 'manual', 'fuel' => 'petrol', 'year' => 2024, 'seats' => 5, 'luggage' => 2, 'cat' => 'Economy', 'color' => 'White', 'engine' => '1.0'),

            // 2. MID-RANGE
            array('qty' => 2, 'brand' => 'Toyota', 'model' => 'Corolla', 'price' => 120, 'trans' => 'auto', 'fuel' => 'hybrid', 'year' => 2024, 'seats' => 5, 'luggage' => 3, 'cat' => 'Mid-Range', 'color' => 'Gray', 'engine' => '1.8'),

            // 3. LUXURY
            array('qty' => 1, 'brand' => 'Mercedes-Benz', 'model' => 'S-Class', 'price' => 500, 'trans' => 'auto', 'fuel' => 'petrol', 'year' => 2025, 'seats' => 5, 'luggage' => 3, 'cat' => 'Luxury', 'color' => 'Black', 'engine' => '3.0'),

            // 4. VIP / TRANSFER
            array('qty' => 2, 'brand' => 'Mercedes-Benz', 'model' => 'V-Class', 'price' => 350, 'trans' => 'auto', 'fuel' => 'diesel', 'year' => 2025, 'seats' => 7, 'luggage' => 5, 'cat' => 'Minivan (VIP)', 'color' => 'Black', 'engine' => '2.2'),
        );

        foreach ($blueprint as $c) {
            for ($i = 0; $i < $c['qty']; $i++) {
                $plate = 'DEMO ' . \chr(\wp_rand(65, 90)) . \chr(\wp_rand(65, 90)) . ' ' . \wp_rand(100, 999);
                $title = $c['brand'] . ' ' . $c['model'] . ($c['qty'] > 1 ? ' (' . ($i + 1) . ')' : '');
                $id    = \wp_insert_post(array('post_title' => $title, 'post_type' => self::PT_VEHICLE, 'post_status' => 'publish'));
                if ($id) {
                    \update_post_meta($id, $this->keys['price'], $c['price']);
                    \update_post_meta($id, $this->keys['brand'], $c['brand']);
                    \update_post_meta($id, $this->keys['model'], $c['model']);
                    \update_post_meta($id, $this->keys['plate'], $plate);
                    \update_post_meta($id, $this->keys['year'], $c['year']);
                    \update_post_meta($id, $this->keys['color'], $c['color']);
                    \update_post_meta($id, $this->keys['engine'], $c['engine']);
                    \update_post_meta($id, $this->keys['km'], \wp_rand(5000, 30000));
                    \update_post_meta($id, $this->keys['seats'], $c['seats']);

                    \update_post_meta($id, $this->keys['trans_pax'], $c['seats']);
                    \update_post_meta($id, $this->keys['big_luggage'], $c['luggage']);
                    \update_post_meta($id, $this->keys['service_type'], 'both');

                    \update_post_meta($id, $this->keys['transmission'], $c['trans']);
                    \update_post_meta($id, $this->keys['fuel'], $c['fuel']);
                    \update_post_meta($id, $this->keys['status_veh'], 'active');

                    // Business Rule Fix: Always set deposit rate to 10%
                    \update_post_meta($id, $this->keys['deposit_rate'], 10);

                    \wp_set_object_terms($id, $c['cat'], self::TAX_CAT);
                    \update_post_meta($id, '_mhm_is_demo', '1');
                    if ($img_id) \set_post_thumbnail($id, $img_id);
                    $this->add_dummy_review($id);
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /**
     * Seed demo bookings
     */
    private function seed_bookings(array $vids, array $uids): void
    {
        $counter = 0;
        foreach ($vids as $vid) {
            $this->create_single_booking($vid, $uids, 'cancelled', '-45 days', 3, false, false);
            $force_deposit = ($counter % 2 === 0);
            $this->create_single_booking($vid, $uids, 'confirmed', '+' . \wp_rand(1, 10) . ' days', \wp_rand(3, 5), true, $force_deposit);
            $counter++;
        }
    }

    /**
     * Create individual booking record
     */
    private function create_single_booking(int $vid, array $uids, string $stat, string $off, int $dur, bool $order = false, bool $force_deposit = false): void
    {
        $uid = $uids[\array_rand($uids)];
        $u   = \get_userdata($uid);
        if (! $u) return;

        $d_p  = (int) \get_post_meta($vid, $this->keys['price'], true);
        $t_p  = $d_p * $dur;

        $is_d = $force_deposit;
        if ($stat === 'cancelled') $is_d = false;

        // Calculate deposit based on 10% rate
        $d_amt = $t_p * 0.10;

        $p_a  = (float) ($is_d ? $d_amt : ($stat === 'cancelled' ? 0 : $t_p));
        $remain = $t_p - $p_a;
        if ($remain < 0) $remain = 0;

        $pay_status = 'pending';
        if ($p_a >= $t_p && $p_a > 0) $pay_status = 'paid';
        elseif ($p_a > 0) $pay_status = 'partial';

        // Date/Time Calculation for schedule
        $pickup_date  = \gmdate('Y-m-d', \strtotime($off));
        $dropoff_date = \gmdate('Y-m-d', \strtotime("$off +$dur days"));
        $pickup_time  = '10:00';
        $dropoff_time = '10:00';

        $start_ts = \strtotime($pickup_date . ' ' . $pickup_time);
        $end_ts   = \strtotime($dropoff_date . ' ' . $dropoff_time);

        $id   = \wp_insert_post(array('post_title' => 'Booking', 'post_type' => self::PT_BOOKING, 'post_status' => 'publish', 'post_author' => $uid));
        if ($id) {
            $metas = array(
                'vehicle_id'   => $vid,
                'customer_id'  => $uid,
                'pickup_date'  => $pickup_date,
                'dropoff_date' => $dropoff_date,
                'pickup_time'  => $pickup_time,
                'dropoff_time' => $dropoff_time,
                'start_date'   => $pickup_date,
                'end_date'     => $dropoff_date,
                'start_time'   => $pickup_time,
                'end_time'     => $dropoff_time,
                'start_ts'     => $start_ts,
                'end_ts'       => $end_ts,
                'status'       => $stat,
                'total_price'  => (float) $t_p,
                'paid_amt'     => $p_a,
                'remain_amt'   => (float) $remain,
                'rental_days'  => $dur,
                'pay_status'   => $pay_status,
                'pay_type'     => ($is_d ? 'deposit' : 'full'),
                'cust_name'    => $u->display_name,
                'cust_email'   => $u->user_email,
                'cust_phone'   => \get_user_meta($uid, 'billing_phone', true)
            );
            foreach ($metas as $k => $v) {
                \update_post_meta($id, $this->keys[$k] ?? $k, $v);
            }
            \update_post_meta($id, '_mhm_is_demo', '1');

            // Trigger internal plugin hook for notifications/integrations
            // Passing an empty array as the second argument for compatibility with hooks expecting booking data
            do_action('mhm_rentiva_booking_created', $id, array());

            if ($order && \function_exists('wc_create_order')) {
                /** @var mixed $o */
                $o = \call_user_func('wc_create_order', array('customer_id' => $uid));
                if ($o && \is_object($o)) {
                    if (\class_exists('\MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge')) {
                        $p_id = \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::get_booking_product_id();
                        if ($p_id && \function_exists('wc_get_product')) {
                            $p = \call_user_func('wc_get_product', $p_id);
                            if ($p) $o->add_product($p, 1, array('total' => $p_a));
                        }
                    }
                    $o->set_total($p_a);
                    $o->set_billing_first_name($u->first_name);
                    $o->set_billing_last_name($u->last_name);
                    $o->set_billing_email($u->user_email);
                    $o->set_billing_phone((string) \get_user_meta($uid, 'billing_phone', true));
                    $o->update_status($stat === 'confirmed' ? 'completed' : 'on-hold');

                    // HPOS Compatible meta saving
                    $o->update_meta_data('_mhm_is_demo', '1');

                    $o->save();
                    \update_post_meta($id, $this->keys['wc_order_id'], $o->get_id());
                }
            }
        }
    }

    /**
     * Seed vehicle categories
     */
    private function seed_categories(): void
    {
        foreach ($this->categories as $c) {
            $term = \term_exists($c, self::TAX_CAT);
            if (! $term) {
                $inserted = \wp_insert_term($c, self::TAX_CAT);
                if (! \is_wp_error($inserted)) {
                    \add_term_meta((int) $inserted['term_id'], '_mhm_is_demo', '1');
                }
            } else {
                // If it exists but might be missing meta, ensure it has it
                \update_term_meta((int) $term['term_id'], '_mhm_is_demo', '1');
            }
        }
    }

    /**
     * Seed transfer locations via direct SQL for performance
     */
    private function seed_transfers_sql(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $ids = array();
        $locs = array(
            array('n' => 'Istanbul Airport (IST)', 't' => 'airport'),
            array('n' => 'Sabiha Gokcen Airport (SAW)', 't' => 'airport'),
            array('n' => 'Taksim Square', 't' => 'city'),
            array('n' => 'Kadikoy Port', 't' => 'city')
        );
        foreach ($locs as $l) {
            $wpdb->insert($table, array('name' => $l['n'], 'type' => $l['t'], 'priority' => 0, 'is_active' => 1));
            $ids[$l['n']] = $wpdb->insert_id;
        }
        return $ids;
    }

    /**
     * Seed transfer routes via direct SQL
     */
    private function seed_routes_sql(array $loc_ids): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        if (count($loc_ids) < 2) return;
        $routes = array(
            array('f' => 'Istanbul Airport (IST)', 't' => 'Taksim Square', 'k' => 45, 'p' => 60),
            array('f' => 'Sabiha Gokcen Airport (SAW)', 't' => 'Kadikoy Port', 'k' => 35, 'p' => 50)
        );
        foreach ($routes as $r) {
            if (isset($loc_ids[$r['f']], $loc_ids[$r['t']])) {
                $wpdb->insert($table, array(
                    'origin_id' => (int) $loc_ids[$r['f']],
                    'destination_id' => (int) $loc_ids[$r['t']],
                    'distance_km' => $r['k'],
                    'duration_min' => 60,
                    'pricing_method' => 'fixed',
                    'base_price' => (float) $r['p']
                ));
            }
        }
    }

    /**
     * Seed extra services/addons
     */
    private function seed_addons_verified(): void
    {
        $addons = array(
            array('t' => 'Full Insurance Coverage', 'p' => 25),
            array('t' => 'Child Seat (0-4 Years)', 'p' => 10)
        );
        foreach ($addons as $e) {
            $id = \wp_insert_post(array('post_title' => $e['t'], 'post_type' => self::PT_SERVICE, 'post_status' => 'publish'));
            if ($id) {
                \update_post_meta($id, 'addon_price', (float) $e['p']);
                \update_post_meta($id, 'addon_enabled', '1');
                \update_post_meta($id, '_mhm_is_demo', '1');
            }
        }
    }

    /**
     * Seed customer messages
     */
    private function seed_messages_expanded(array $uids): void
    {
        $msgs = array(
            array('s' => 'Payment Options', 'c' => 'payment', 'st' => 'pending', 'm' => 'Do you accept international credit cards?'),
            array('s' => 'Great Service', 'c' => 'general', 'st' => 'closed', 'm' => 'The car was very clean, thank you.'),
            array('s' => 'Invoice Request', 'c' => 'payment', 'st' => 'answered', 'm' => 'Please issue the invoice under my company name.'),
            array('s' => 'Vehicle Features', 'c' => 'booking', 'st' => 'pending', 'm' => 'Does the car have a built-in GPS?'),
            array('s' => 'Transfer Delay', 'c' => 'general', 'st' => 'answered', 'm' => 'What happens if my flight is delayed?')
        );
        foreach ($msgs as $m) {
            $uid = $uids[\array_rand($uids)];
            $id = \wp_insert_post(array('post_title' => $m['s'], 'post_content' => $m['m'], 'post_type' => self::PT_MESSAGE, 'post_status' => 'publish', 'post_author' => $uid));
            if ($id) {
                \update_post_meta($id, '_mhm_message_type', 'customer_to_admin');
                \update_post_meta($id, '_mhm_message_category', $m['c']);
                \update_post_meta($id, '_mhm_message_status', $m['st']);
                \update_post_meta($id, '_mhm_is_demo', '1');
            }
        }
    }

    /**
     * Utility to fetch a placeholder image ID
     */
    private function get_dummy_image_id()
    {
        $q = new \WP_Query(array('post_type' => 'attachment', 'posts_per_page' => 1, 'post_status' => 'inherit', 'mimetype' => 'image'));
        return $q->have_posts() ? $q->posts[0]->ID : false;
    }

    /**
     * Add dummy reviews to vehicles
     */
    private function add_dummy_review(int $pid): void
    {
        $name = $this->names[\array_rand($this->names)];
        \wp_insert_comment(array('comment_post_ID' => $pid, 'comment_content' => 'Excellent service and car quality.', 'comment_author' => $name, 'comment_approved' => 1, 'comment_type' => 'review'));
    }
}
