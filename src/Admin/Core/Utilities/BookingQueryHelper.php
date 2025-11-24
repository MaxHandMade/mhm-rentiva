<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Merkezi Booking Query Helper
 * 
 * Tüm booking sorguları için ortak fonksiyonları sağlar
 * ve kod tekrarını önler.
 */
final class BookingQueryHelper
{
    /**
     * Meta key ile booking bulma
     * 
     * @param string $meta_key Meta key
     * @param string $meta_value Meta value
     * @param string $post_type Post type (varsayılan: 'vehicle_booking')
     * @param string $post_status Post status (varsayılan: 'any')
     * @return int Booking ID (0 = bulunamadı)
     */
    public static function findBookingByMeta(
        string $meta_key, 
        string $meta_value, 
        string $post_type = 'vehicle_booking',
        string $post_status = 'any'
    ): int {
        if (empty($meta_key) || empty($meta_value)) {
            return 0;
        }

        $query_args = [
            'post_type'      => $post_type,
            'post_status'    => $post_status,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => $meta_key,
                    'value'   => $meta_value,
                    'compare' => '=',
                ]
            ]
        ];

        $query = new WP_Query($query_args);
        
        return $query->have_posts() ? (int) $query->posts[0] : 0;
    }



    /**
     * Customer email ile booking'leri bulma
     * 
     * @param string $email Customer email
     * @param array $statuses Post status'ları (varsayılan: ['publish'])
     * @param int $limit Limit (varsayılan: -1 = tümü)
     * @return array Booking ID'leri
     */
    public static function findBookingsByCustomerEmail(
        string $email, 
        array $statuses = ['publish'], 
        int $limit = -1
    ): array {
        if (empty($email)) {
            return [];
        }

        $query_args = [
            'post_type'      => 'vehicle_booking',
            'post_status'    => $statuses,
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_booking_customer_email',
                    'value'   => $email,
                    'compare' => '=',
                ]
            ],
            'orderby'        => 'date',
            'order'          => 'DESC'
        ];

        $query = new WP_Query($query_args);
        
        return $query->have_posts() ? array_map('intval', $query->posts) : [];
    }

    /**
     * Vehicle ID ile booking'leri bulma
     * 
     * @param int $vehicle_id Vehicle ID
     * @param array $statuses Post status'ları (varsayılan: ['publish'])
     * @param int $limit Limit (varsayılan: -1 = tümü)
     * @return array Booking ID'leri
     */
    public static function findBookingsByVehicle(
        int $vehicle_id, 
        array $statuses = ['publish'], 
        int $limit = -1
    ): array {
        if ($vehicle_id <= 0) {
            return [];
        }

        $query_args = [
            'post_type'      => 'vehicle_booking',
            'post_status'    => $statuses,
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_booking_vehicle_id',
                    'value'   => $vehicle_id,
                    'compare' => '=',
                ]
            ],
            'orderby'        => 'date',
            'order'          => 'DESC'
        ];

        $query = new WP_Query($query_args);
        
        return $query->have_posts() ? array_map('intval', $query->posts) : [];
    }

    /**
     * Tarih aralığında booking'leri bulma
     * 
     * @param string $start_date Başlangıç tarihi (Y-m-d)
     * @param string $end_date Bitiş tarihi (Y-m-d)
     * @param array $statuses Post status'ları (varsayılan: ['publish'])
     * @param int $limit Limit (varsayılan: -1 = tümü)
     * @return array Booking ID'leri
     */
    public static function findBookingsByDateRange(
        string $start_date, 
        string $end_date, 
        array $statuses = ['publish'], 
        int $limit = -1
    ): array {
        if (empty($start_date) || empty($end_date)) {
            return [];
        }

        $query_args = [
            'post_type'      => 'vehicle_booking',
            'post_status'    => $statuses,
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_booking_pickup_date',
                    'value'   => [$start_date, $end_date],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE'
                ]
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => '_booking_pickup_date',
            'order'          => 'ASC'
        ];

        $query = new WP_Query($query_args);
        
        return $query->have_posts() ? array_map('intval', $query->posts) : [];
    }

    /**
     * Payment status ile booking'leri bulma
     * 
     * @param string $payment_status Payment status
     * @param array $post_statuses Post status'ları (varsayılan: ['publish'])
     * @param int $limit Limit (varsayılan: -1 = tümü)
     * @return array Booking ID'leri
     */
    public static function findBookingsByPaymentStatus(
        string $payment_status, 
        array $post_statuses = ['publish'], 
        int $limit = -1
    ): array {
        if (empty($payment_status)) {
            return [];
        }

        $query_args = [
            'post_type'      => 'vehicle_booking',
            'post_status'    => $post_statuses,
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_booking_payment_status',
                    'value'   => $payment_status,
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_payment_status',
                    'value'   => $payment_status,
                    'compare' => '=',
                ]
            ],
            'orderby'        => 'date',
            'order'          => 'DESC'
        ];

        $query = new WP_Query($query_args);
        
        return $query->have_posts() ? array_map('intval', $query->posts) : [];
    }

    /**
     * Booking'in payment gateway bilgisini al
     * 
     * @param int $booking_id Booking ID
     * @return string Payment gateway
     */
    public static function getBookingPaymentGateway(int $booking_id): string
    {
        if ($booking_id <= 0) {
            return '';
        }

        // Yeni meta key'den dene
        $gateway = get_post_meta($booking_id, '_booking_payment_gateway', true);
        if (!empty($gateway)) {
            return $gateway;
        }

        // Eski meta key'den dene
        $gateway = get_post_meta($booking_id, '_mhm_payment_gateway', true);
        if (!empty($gateway)) {
            return $gateway;
        }

        return 'unknown';
    }

    /**
     * Booking'in ödeme durumunu al
     * 
     * @param int $booking_id Booking ID
     * @return string Payment status
     */
    public static function getBookingPaymentStatus(int $booking_id): string
    {
        if ($booking_id <= 0) {
            return 'unknown';
        }

        // Yeni meta key'den dene
        $status = get_post_meta($booking_id, '_booking_payment_status', true);
        if (!empty($status)) {
            return $status;
        }

        // Eski meta key'den dene
        $status = get_post_meta($booking_id, '_mhm_payment_status', true);
        if (!empty($status)) {
            return $status;
        }

        return 'unknown';
    }

    /**
     * Booking'in toplam fiyatını al
     * 
     * @param int $booking_id Booking ID
     * @return float Total price
     */
    public static function getBookingTotalPrice(int $booking_id): float
    {
        if ($booking_id <= 0) {
            return 0.0;
        }

        // Yeni meta key'den dene
        $price = get_post_meta($booking_id, '_booking_total_price', true);
        if (is_numeric($price)) {
            return (float) $price;
        }

        // Eski meta key'den dene
        $price = get_post_meta($booking_id, '_mhm_total_price', true);
        if (is_numeric($price)) {
            return (float) $price;
        }

        return 0.0;
    }

    /**
     * Booking'in müşteri bilgilerini al
     * 
     * @param int $booking_id Booking ID
     * @return array Customer information
     */
    public static function getBookingCustomerInfo(int $booking_id): array
    {
        if ($booking_id <= 0) {
            return [];
        }

        // Yeni meta key'lerden al (ad/soyad ayrı)
        $first_name = get_post_meta($booking_id, '_mhm_customer_first_name', true);
        $last_name = get_post_meta($booking_id, '_mhm_customer_last_name', true);
        $email = get_post_meta($booking_id, '_mhm_customer_email', true);
        $phone = get_post_meta($booking_id, '_mhm_customer_phone', true);
        
        // Eğer yeni key'lerde veri yoksa, eski key'lerden dene
        if (empty($first_name)) {
            $first_name = get_post_meta($booking_id, '_booking_customer_first_name', true) ?: 
                         get_post_meta($booking_id, '_mhm_contact_name', true) ?: '';
        }
        
        if (empty($email)) {
            $email = get_post_meta($booking_id, '_booking_customer_email', true) ?: 
                    get_post_meta($booking_id, '_mhm_contact_email', true) ?: '';
        }
        
        if (empty($phone)) {
            $phone = get_post_meta($booking_id, '_booking_customer_phone', true) ?: 
                    get_post_meta($booking_id, '_mhm_contact_phone', true) ?: '';
        }

        return [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
        ];
    }

    /**
     * Booking'in araç bilgilerini al
     * 
     * @param int $booking_id Booking ID
     * @return array Vehicle information
     */
    public static function getBookingVehicleInfo(int $booking_id): array
    {
        if ($booking_id <= 0) {
            return [];
        }

        $vehicle_id = (int) (get_post_meta($booking_id, '_booking_vehicle_id', true) ?: 
                            get_post_meta($booking_id, '_mhm_vehicle_id', true) ?: 0);

        if ($vehicle_id <= 0) {
            return [];
        }

        $vehicle = get_post($vehicle_id);
        
        return [
            'id' => $vehicle_id,
            'title' => $vehicle ? $vehicle->post_title : '',
            'price_per_day' => get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0,
            'featured_image' => get_the_post_thumbnail_url($vehicle_id, 'medium') ?: '',
        ];
    }

    /**
     * Booking'in tarih bilgilerini al
     * 
     * @param int $booking_id Booking ID
     * @return array Date information
     */
    public static function getBookingDateInfo(int $booking_id): array
    {
        if ($booking_id <= 0) {
            return [];
        }

        return [
            'pickup_date' => get_post_meta($booking_id, '_booking_pickup_date', true) ?: 
                           get_post_meta($booking_id, '_mhm_pickup_date', true) ?: '',
            'return_date' => get_post_meta($booking_id, '_booking_return_date', true) ?: 
                           get_post_meta($booking_id, '_mhm_dropoff_date', true) ?: '',
            'rental_days' => (int) (get_post_meta($booking_id, '_booking_rental_days', true) ?: 
                                   get_post_meta($booking_id, '_mhm_rental_days', true) ?: 0),
        ];
    }

    /**
     * Booking istatistikleri al
     * 
     * @param array $filters Filters (status, payment_status, date_range, etc.)
     * @return array Statistics
     */
    public static function getBookingStats(array $filters = []): array
    {
        $query_args = [
            'post_type'      => 'vehicle_booking',
            'post_status'    => $filters['post_status'] ?? ['publish'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        // Meta query ekle
        $meta_query = [];
        
        if (!empty($filters['payment_status'])) {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => '_booking_payment_status',
                    'value'   => $filters['payment_status'],
                    'compare' => '=',
                ],
                [
                    'key'     => '_mhm_payment_status',
                    'value'   => $filters['payment_status'],
                    'compare' => '=',
                ]
            ];
        }

        if (!empty($filters['date_range'])) {
            $meta_query[] = [
                'key'     => '_booking_pickup_date',
                'value'   => $filters['date_range'],
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            ];
        }

        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($query_args);
        $booking_ids = $query->have_posts() ? $query->posts : [];

        // İstatistikleri hesapla
        $total_bookings = count($booking_ids);
        $total_revenue = 0.0;
        $payment_statuses = [];
        $gateways = [];

        foreach ($booking_ids as $booking_id) {
            $total_revenue += self::getBookingTotalPrice((int) $booking_id);
            $payment_status = self::getBookingPaymentStatus((int) $booking_id);
            $gateway = self::getBookingPaymentGateway((int) $booking_id);
            
            $payment_statuses[$payment_status] = ($payment_statuses[$payment_status] ?? 0) + 1;
            $gateways[$gateway] = ($gateways[$gateway] ?? 0) + 1;
        }

        return [
            'total_bookings' => $total_bookings,
            'total_revenue' => $total_revenue,
            'payment_statuses' => $payment_statuses,
            'payment_gateways' => $gateways,
            'average_revenue' => $total_bookings > 0 ? $total_revenue / $total_bookings : 0.0,
        ];
    }
}
