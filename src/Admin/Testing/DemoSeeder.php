<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * MHM Rentiva - Demo Yöneticisi (v13 - Variety Pack)
 * ----------------------------------------------------------------
 * 1. 🚗 Filo Genişletildi: Ekonomi, Orta, Lüks ve VIP araçlar eklendi.
 * 2. 🔑 Key Fix: Röntgen raporundan çıkan %100 doğru anahtarlar korundu.
 * 3. 🛡️ Cleanup: Temizlik ve kurulum stabil.
 * * BU DOSYA ARTIK "TAMAMLANDI" KABUL EDİLİP, KOD GELİŞTİRMEYE GEÇİLECEK.
 */
final class DemoSeeder
{

    public const PT_VEHICLE = 'vehicle';
    public const PT_BOOKING = 'vehicle_booking';
    public const PT_SERVICE = 'vehicle_addon';
    public const PT_MESSAGE = 'mhm_message';
    public const TAX_CAT    = 'vehicle_category';

    private array $keys = array(
        'price'         => '_mhm_rentiva_price_per_day',
        'deposit'       => '_mhm_rentiva_deposit',
        'deposit_rate'  => '_mhm_rentiva_deposit_rate',
        'brand'         => '_mhm_rentiva_brand',
        'model'         => '_mhm_rentiva_model',
        'year'          => '_mhm_rentiva_year',
        'color'         => '_mhm_rentiva_color',
        'engine'        => '_mhm_rentiva_engine_size',
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
        'pickup_date'   => '_mhm_pickup_date',
        'pickup_time'   => '_mhm_pickup_time',
        'dropoff_date'  => '_mhm_dropoff_date',
        'dropoff_time'  => '_mhm_dropoff_time',
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

    private array $categories = array('Ekonomi', 'Orta Sınıf', 'Lüks', 'SUV', 'Minivan (VIP)');

    private array $names = array(
        'Ahmet Yılmaz',
        'Ayşe Kaya',
        'Mehmet Demir',
        'Fatma Çelik',
        'Mustafa Yıldız',
        'Zeynep Arslan',
        'Ali Öztürk',
        'Selin Koç'
    );

    public function run(bool $do_cleanup = false): string
    {
        $msg = $do_cleanup ? $this->cleanup() . "\n" : '';

        // 1. Core Data
        $this->seed_categories();
        $loc_ids = $this->seed_transfers_sql();
        $this->seed_routes_sql($loc_ids);

        // 2. Users & Services
        $user_ids = $this->seed_users(8);
        $this->seed_addons_verified();
        $this->seed_messages_expanded($user_ids);

        // 3. Inventory & Transactions
        $img_id      = $this->get_dummy_image_id();
        $vehicle_ids = $this->seed_vehicles($img_id);
        $this->seed_bookings($vehicle_ids, $user_ids);

        return $msg . '✅ Demo v13 (Variety Pack) Tamamlandı! Araç çeşitliliği sağlandı.';
    }

    public function cleanup(): string
    {
        global $wpdb;
        $count        = 0;
        $post_types   = array(self::PT_VEHICLE, self::PT_BOOKING, self::PT_SERVICE, self::PT_MESSAGE, 'shop_order');
        $loc_table    = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $route_table  = $wpdb->prefix . 'mhm_rentiva_transfer_routes';

        foreach ($post_types as $pt) {
            $posts = \get_posts(array('post_type' => $pt, 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_mhm_is_demo', 'meta_value' => '1', 'fields' => 'ids'));
            foreach ($posts as $pid) {
                \wp_delete_post($pid, true);
                $count++;
            }
        }

        $users = \get_users(array('meta_key' => '_mhm_is_demo_user', 'meta_value' => '1', 'fields' => 'ID'));
        foreach ($users as $uid) {
            if (! \user_can($uid, 'manage_options')) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                \wp_delete_user($uid);
                $count++;
            }
        }

        $wpdb->query("TRUNCATE TABLE $loc_table");
        $wpdb->query("TRUNCATE TABLE $route_table");

        return "🧹 Temizlik yapıldı: $count veri silindi.";
    }

    private function seed_users(int $count): array
    {
        $user_ids = array();
        for ($i = 0; $i < $count; $i++) {
            $name_idx = $i % count($this->names);
            $name  = $this->names[$name_idx];
            $email = 'test' . ($i + 1) . '@localhost.com';
            $phone = '05' . \wp_rand(30, 55) . ' ' . \wp_rand(100, 999) . ' ' . \wp_rand(10, 99) . ' ' . \wp_rand(10, 99);

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

    private function seed_vehicles($img_id): array
    {
        $ids = array();

        // 🚗 ARAÇ FİLOSU ÇEŞİTLİLİĞİ
        $blueprint = array(
            // 1. EKONOMİ (Düşük Çarpan)
            array('qty' => 3, 'brand' => 'Fiat', 'model' => 'Egea', 'price' => 1200, 'trans' => 'manual', 'fuel' => 'diesel', 'year' => 2024, 'seats' => 5, 'luggage' => 2, 'cat' => 'Ekonomi', 'color' => 'Beyaz', 'engine' => '1.4', 'deposit' => 2000),

            // 2. ORTA SINIF (Standart Çarpan)
            array('qty' => 2, 'brand' => 'Renault', 'model' => 'Megane', 'price' => 1800, 'trans' => 'auto', 'fuel' => 'diesel', 'year' => 2024, 'seats' => 5, 'luggage' => 3, 'cat' => 'Orta Sınıf', 'color' => 'Gri', 'engine' => '1.5', 'deposit' => 3000),

            // 3. LÜKS (Yüksek Çarpan Testi İçin)
            array('qty' => 1, 'brand' => 'BMW', 'model' => '520i', 'price' => 6000, 'trans' => 'auto', 'fuel' => 'petrol', 'year' => 2025, 'seats' => 5, 'luggage' => 3, 'cat' => 'Lüks', 'color' => 'Siyah', 'engine' => '2.0', 'deposit' => 10000),

            // 4. VIP / TRANSFER (Transfer Odaklı)
            array('qty' => 2, 'brand' => 'Mercedes', 'model' => 'Vito', 'price' => 4500, 'trans' => 'auto', 'fuel' => 'diesel', 'year' => 2025, 'seats' => 9, 'luggage' => 6, 'cat' => 'Minivan (VIP)', 'color' => 'Siyah', 'engine' => '2.0', 'deposit' => 5000),
        );

        foreach ($blueprint as $c) {
            for ($i = 0; $i < $c['qty']; $i++) {
                $plate = '34 ' . \chr(\wp_rand(65, 90)) . \chr(\wp_rand(65, 90)) . ' ' . \wp_rand(100, 999);
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
                    \update_post_meta($id, $this->keys['km'], \wp_rand(10000, 50000));
                    \update_post_meta($id, $this->keys['seats'], $c['seats']);

                    \update_post_meta($id, $this->keys['trans_pax'], $c['seats']);
                    \update_post_meta($id, $this->keys['big_luggage'], $c['luggage']);
                    \update_post_meta($id, $this->keys['service_type'], 'both');

                    \update_post_meta($id, $this->keys['transmission'], $c['trans']);
                    \update_post_meta($id, $this->keys['fuel'], $c['fuel']);
                    \update_post_meta($id, $this->keys['deposit'], $c['deposit']);
                    \update_post_meta($id, $this->keys['status_veh'], 'active');

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

    private function create_single_booking(int $vid, array $uids, string $stat, string $off, int $dur, bool $order = false, bool $force_deposit = false): void
    {
        $uid = $uids[\array_rand($uids)];
        $u   = \get_userdata($uid);
        if (! $u) return;

        $d_p  = (int) \get_post_meta($vid, $this->keys['price'], true);
        $t_p  = $d_p * $dur;

        $is_d = $force_deposit;
        if ($stat === 'cancelled') $is_d = false;

        $d_amt = (int) \get_post_meta($vid, $this->keys['deposit'], true);
        if (!$d_amt) $d_amt = 2000;

        $p_a  = (float) ($is_d ? $d_amt : ($stat === 'cancelled' ? 0 : $t_p));
        $remain = $t_p - $p_a;
        if ($remain < 0) $remain = 0;

        $pay_status = 'pending';
        if ($p_a >= $t_p && $p_a > 0) $pay_status = 'paid';
        elseif ($p_a > 0) $pay_status = 'partial';

        // ⭐ DATE/TIME CALCULATION
        $pickup_date  = \gmdate('Y-m-d', \strtotime($off));
        $dropoff_date = \gmdate('Y-m-d', \strtotime("$off +$dur days"));
        $pickup_time  = '10:00';
        $dropoff_time = '10:00';

        // Calculate Unix Timestamps (Important for Availability Check)
        $start_ts = \strtotime($pickup_date . ' ' . $pickup_time);
        $end_ts   = \strtotime($dropoff_date . ' ' . $dropoff_time);

        $id   = \wp_insert_post(array('post_title' => 'Rezervasyon', 'post_type' => self::PT_BOOKING, 'post_status' => 'publish', 'post_author' => $uid));
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
                    $o->save();
                    \update_post_meta($id, $this->keys['wc_order_id'], $o->get_id());
                    \update_post_meta($o->get_id(), '_mhm_is_demo', '1');
                }
            }
        }
    }

    private function seed_categories(): void
    {
        foreach ($this->categories as $c) {
            if (! \term_exists($c, self::TAX_CAT)) \wp_insert_term($c, self::TAX_CAT);
        }
    }
    private function seed_transfers_sql(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $ids = array();
        $locs = array(array('n' => 'İstanbul Havalimanı (IST)', 't' => 'airport'), array('n' => 'Sabiha Gökçen Havalimanı (SAW)', 't' => 'airport'), array('n' => 'Taksim Meydanı', 't' => 'city'), array('n' => 'Kadıköy Rıhtım', 't' => 'city'));
        foreach ($locs as $l) {
            $wpdb->insert($table, array('name' => $l['n'], 'type' => $l['t'], 'priority' => 0, 'is_active' => 1));
            $ids[$l['n']] = $wpdb->insert_id;
        }
        return $ids;
    }
    private function seed_routes_sql(array $loc_ids): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        if (count($loc_ids) < 2) return;
        $routes = array(array('f' => 'İstanbul Havalimanı (IST)', 't' => 'Taksim Meydanı', 'k' => 45, 'p' => 1800), array('f' => 'Sabiha Gökçen Havalimanı (SAW)', 't' => 'Kadıköy Rıhtım', 'k' => 35, 'p' => 1400));
        foreach ($routes as $r) {
            if (isset($loc_ids[$r['f']], $loc_ids[$r['t']])) {
                $wpdb->insert($table, array('origin_id' => (int) $loc_ids[$r['f']], 'destination_id' => (int) $loc_ids[$r['t']], 'distance_km' => $r['k'], 'duration_min' => 60, 'pricing_method' => 'fixed', 'base_price' => (float) $r['p']));
            }
        }
    }
    private function seed_addons_verified(): void
    {
        foreach (array(array('t' => 'Tam Kapsamlı Sigorta', 'p' => 550), array('t' => 'Çocuk Koltuğu (0-4 Yaş)', 'p' => 200)) as $e) {
            $id = \wp_insert_post(array('post_title' => $e['t'], 'post_type' => self::PT_SERVICE, 'post_status' => 'publish'));
            if ($id) {
                \update_post_meta($id, 'addon_price', (float) $e['p']);
                \update_post_meta($id, 'addon_enabled', '1');
                \update_post_meta($id, '_mhm_is_demo', '1');
            }
        }
    }
    private function seed_messages_expanded(array $uids): void
    {
        $msgs = array(array('s' => 'Ödeme Seçenekleri', 'c' => 'payment', 'st' => 'pending', 'm' => 'Kredi kartına taksit imkanı var mı?'), array('s' => 'Teşekkürler', 'c' => 'general', 'st' => 'closed', 'm' => 'Araç çok temizdi, teşekkür ederiz.'), array('s' => 'Fatura Talebi', 'c' => 'payment', 'st' => 'answered', 'm' => 'Lütfen faturayı şirket adına kesin.'), array('s' => 'Araç Durumu', 'c' => 'booking', 'st' => 'pending', 'm' => 'Kiralamak istediğim araçta HGS var mı?'), array('s' => 'Transfer Saati', 'c' => 'general', 'st' => 'answered', 'm' => 'Uçak rötar yaparsa şoför bekliyor mu?'));
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
    private function get_dummy_image_id()
    {
        $q = new \WP_Query(array('post_type' => 'attachment', 'posts_per_page' => 1, 'post_status' => 'inherit', 'mimetype' => 'image'));
        return $q->have_posts() ? $q->posts[0]->ID : false;
    }
    private function add_dummy_review(int $pid): void
    {
        $name = $this->names[\array_rand($this->names)];
        \wp_insert_comment(array('comment_post_ID' => $pid, 'comment_content' => 'Çok memnun kaldım.', 'comment_author' => $name, 'comment_approved' => 1, 'comment_type' => 'review'));
    }
}
