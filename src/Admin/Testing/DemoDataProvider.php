<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * DemoDataProvider
 *
 * Locale-aware demo data provider for MHM Rentiva demo seeding.
 * Returns TR data for tr_* locales, EN data for everything else.
 *
 * @package MHMRentiva\Admin\Testing
 * @since   4.25.1
 */
final class DemoDataProvider
{
    /**
     * Detect whether the current locale is Turkish.
     */
    public static function is_turkish(): bool
    {
        return str_starts_with(get_locale(), 'tr');
    }

    /**
     * Return locale-aware vehicle data (5 entries — one per category).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_vehicles(): array
    {
        if (self::is_turkish()) {
            return self::vehicles_tr();
        }

        return self::vehicles_en();
    }

    /**
     * Return locale-aware customer data (8 entries).
     *
     * @return array<int, array<string, string>>
     */
    public static function get_customers(): array
    {
        if (self::is_turkish()) {
            return self::customers_tr();
        }

        return self::customers_en();
    }

    /**
     * Return locale-aware location data (4 entries).
     *
     * @return array<int, array<string, string>>
     */
    public static function get_locations(): array
    {
        if (self::is_turkish()) {
            return self::locations_tr();
        }

        return self::locations_en();
    }

    /**
     * Return locale-aware addon data (3 entries).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_addons(): array
    {
        if (self::is_turkish()) {
            return self::addons_tr();
        }

        return self::addons_en();
    }

    // -------------------------------------------------------------------------
    // Private: TR datasets
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function vehicles_tr(): array
    {
        return array(
            array(
                'title'         => 'Fiat Egea 1.4 Fire',
                'brand'         => 'Fiat',
                'model'         => 'Egea 1.4 Fire',
                'year'          => 2022,
                'category'      => 'Ekonomi',
                'price_per_day' => 850,
                'color'         => 'Beyaz',
                'engine_power'  => '95 HP',
                'license_plate' => '34 AA 001',
                'mileage'       => 18500,
                'seats'         => 5,
                'doors'         => 4,
                'transmission'  => 'manual',
                'fuel_type'     => 'gasoline',
                'features'      => 'Klima, Bluetooth, Geri Vites Kamerasi',
                'image_file'    => 'economy-01.webp',
            ),
            array(
                'title'         => 'Renault Clio 1.0 TCe Joy',
                'brand'         => 'Renault',
                'model'         => 'Clio',
                'year'          => 2024,
                'category'      => 'Mid-Range',
                'price_per_day' => 1100,
                'color'         => 'Kırmızı',
                'engine_power'  => '100 HP',
                'license_plate' => '34 DMO 02',
                'mileage'       => 10000,
                'seats'         => '7',
                'doors'         => '5',
                'transmission'  => 'manual',
                'fuel_type'     => 'gasoline',
                'features'      => 'Klima, Bluetooth, USB, Geri Görüş Kamerası',
                'image_file'    => 'midrange-01.webp',
            ),
            array(
                'title'         => 'BMW 520i M Sport',
                'brand'         => 'BMW',
                'model'         => '520i M Sport',
                'year'          => 2023,
                'category'      => 'Lüks',
                'price_per_day' => 2500,
                'color'         => 'Siyah',
                'engine_power'  => '184 HP',
                'license_plate' => '34 CC 003',
                'mileage'       => 8200,
                'seats'         => 5,
                'doors'         => 4,
                'transmission'  => 'automatic',
                'fuel_type'     => 'gasoline',
                'features'      => 'Deri Koltuk, Harman Kardon, Panoramik Tavan, M Spor Paket',
                'image_file'    => 'luxury-01.webp',
            ),
            array(
                'title'         => 'Volkswagen Golf 1.6 TDI Comfortline',
                'brand'         => 'Volkswagen',
                'model'         => 'Golf',
                'year'          => 2023,
                'category'      => 'SUV',
                'price_per_day' => 1400,
                'color'         => 'Gümüş',
                'engine_power'  => '115 HP',
                'license_plate' => '34 DMO 04',
                'mileage'       => 18000,
                'seats'         => '5',
                'doors'         => '4',
                'transmission'  => 'automatic',
                'fuel_type'     => 'diesel',
                'features'      => 'Klima, Navigasyon, Park Sensörü, Adaptif Hız Sabitleyici',
                'image_file'    => 'suv-01.webp',
            ),
            array(
                'title'         => 'Mercedes Vito Tourer',
                'brand'         => 'Mercedes',
                'model'         => 'Vito Tourer',
                'year'          => 2023,
                'category'      => 'Minivan (VIP)',
                'price_per_day' => 3500,
                'color'         => 'Siyah',
                'engine_power'  => '190 HP',
                'license_plate' => '34 EE 005',
                'mileage'       => 15000,
                'seats'         => 8,
                'doors'         => 5,
                'transmission'  => 'automatic',
                'fuel_type'     => 'diesel',
                'features'      => 'Deri Koltuk, Klima, Navigasyon, VIP Kabini, USB Sarj',
                'image_file'    => 'vip-01.webp',
            ),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function customers_tr(): array
    {
        return array(
            array( 'name' => 'Ahmet Yılmaz',  'first' => 'Ahmet',  'last' => 'Yılmaz',  'email' => 'demo1@example.com', 'phone' => '+90 532 111 0001' ),
            array( 'name' => 'Ayşe Demir',    'first' => 'Ayşe',   'last' => 'Demir',   'email' => 'demo2@example.com', 'phone' => '+90 532 111 0002' ),
            array( 'name' => 'Mehmet Kaya',   'first' => 'Mehmet', 'last' => 'Kaya',    'email' => 'demo3@example.com', 'phone' => '+90 532 111 0003' ),
            array( 'name' => 'Fatma Çelik',   'first' => 'Fatma',  'last' => 'Çelik',   'email' => 'demo4@example.com', 'phone' => '+90 532 111 0004' ),
            array( 'name' => 'Ali Öztürk',    'first' => 'Ali',    'last' => 'Öztürk',  'email' => 'demo5@example.com', 'phone' => '+90 532 111 0005' ),
            array( 'name' => 'Zeynep Arslan', 'first' => 'Zeynep', 'last' => 'Arslan',  'email' => 'demo6@example.com', 'phone' => '+90 532 111 0006' ),
            array( 'name' => 'Hasan Şahin',   'first' => 'Hasan',  'last' => 'Şahin',   'email' => 'demo7@example.com', 'phone' => '+90 532 111 0007' ),
            array( 'name' => 'Elif Koç',      'first' => 'Elif',   'last' => 'Koç',     'email' => 'demo8@example.com', 'phone' => '+90 532 111 0008' ),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function locations_tr(): array
    {
        return array(
            array( 'name' => 'İstanbul Havalimanı',          'code' => 'IST' ),
            array( 'name' => 'Sabiha Gökçen Havalimanı',     'code' => 'SAW' ),
            array( 'name' => 'Taksim Meydanı',               'code' => 'TAK' ),
            array( 'name' => 'Kadıköy İskele',               'code' => 'KAD' ),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function addons_tr(): array
    {
        return array(
            array( 'title' => 'Bebek Koltuğu',  'price' => 150, 'type' => 'per_day' ),
            array( 'title' => 'Ek Surucu',      'price' => 200, 'type' => 'per_day' ),
            array( 'title' => 'GPS Navigasyon', 'price' => 100, 'type' => 'per_day' ),
        );
    }

    // -------------------------------------------------------------------------
    // Private: EN datasets
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function vehicles_en(): array
    {
        return array(
            array(
                'title'         => 'Fiat Tipo 1.4',
                'brand'         => 'Fiat',
                'model'         => 'Tipo 1.4',
                'year'          => 2022,
                'category'      => 'Economy',
                'price_per_day' => 45,
                'color'         => 'White',
                'engine_power'  => '95 HP',
                'license_plate' => 'AB12 CDE',
                'mileage'       => 18500,
                'seats'         => 5,
                'doors'         => 4,
                'transmission'  => 'manual',
                'fuel_type'     => 'gasoline',
                'features'      => 'Air Conditioning, Bluetooth, Rear Camera',
                'image_file'    => 'economy-01.webp',
            ),
            array(
                'title'         => 'Renault Clio 1.0 TCe Joy',
                'brand'         => 'Renault',
                'model'         => 'Clio',
                'year'          => 2024,
                'category'      => 'Mid-Range',
                'price_per_day' => 60,
                'color'         => 'Red',
                'engine_power'  => '100 HP',
                'license_plate' => 'DMO-002',
                'mileage'       => 10000,
                'seats'         => '7',
                'doors'         => '5',
                'transmission'  => 'manual',
                'fuel_type'     => 'gasoline',
                'features'      => 'AC, Bluetooth, USB, Rear Camera',
                'image_file'    => 'midrange-01.webp',
            ),
            array(
                'title'         => 'BMW 520i M Sport',
                'brand'         => 'BMW',
                'model'         => '520i M Sport',
                'year'          => 2023,
                'category'      => 'Luxury',
                'price_per_day' => 130,
                'color'         => 'Black',
                'engine_power'  => '184 HP',
                'license_plate' => 'EF56 GHI',
                'mileage'       => 8200,
                'seats'         => 5,
                'doors'         => 4,
                'transmission'  => 'automatic',
                'fuel_type'     => 'gasoline',
                'features'      => 'Leather Seats, Harman Kardon, Panoramic Roof, M Sport Package',
                'image_file'    => 'luxury-01.webp',
            ),
            array(
                'title'         => 'Volkswagen Golf 1.6 TDI Comfortline',
                'brand'         => 'Volkswagen',
                'model'         => 'Golf',
                'year'          => 2023,
                'category'      => 'SUV',
                'price_per_day' => 75,
                'color'         => 'Silver',
                'engine_power'  => '115 HP',
                'license_plate' => 'DMO-004',
                'mileage'       => 18000,
                'seats'         => '5',
                'doors'         => '4',
                'transmission'  => 'automatic',
                'fuel_type'     => 'diesel',
                'features'      => 'AC, Navigation, Parking Sensors, Adaptive Cruise Control',
                'image_file'    => 'suv-01.webp',
            ),
            array(
                'title'         => 'Mercedes Vito Tourer',
                'brand'         => 'Mercedes',
                'model'         => 'Vito Tourer',
                'year'          => 2023,
                'category'      => 'Minivan (VIP)',
                'price_per_day' => 180,
                'color'         => 'Black',
                'engine_power'  => '190 HP',
                'license_plate' => 'IJ90 KLM',
                'mileage'       => 15000,
                'seats'         => 8,
                'doors'         => 5,
                'transmission'  => 'automatic',
                'fuel_type'     => 'diesel',
                'features'      => 'Leather Seats, Climate Control, Navigation, VIP Cabin, USB Charging',
                'image_file'    => 'vip-01.webp',
            ),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function customers_en(): array
    {
        return array(
            array( 'name' => 'John Smith',    'first' => 'John',    'last' => 'Smith',   'email' => 'demo1@example.com', 'phone' => '+1 555 000 0001' ),
            array( 'name' => 'Jane Doe',      'first' => 'Jane',    'last' => 'Doe',     'email' => 'demo2@example.com', 'phone' => '+1 555 000 0002' ),
            array( 'name' => 'Michael Brown', 'first' => 'Michael', 'last' => 'Brown',   'email' => 'demo3@example.com', 'phone' => '+1 555 000 0003' ),
            array( 'name' => 'Emily Davis',   'first' => 'Emily',   'last' => 'Davis',   'email' => 'demo4@example.com', 'phone' => '+1 555 000 0004' ),
            array( 'name' => 'David Wilson',  'first' => 'David',   'last' => 'Wilson',  'email' => 'demo5@example.com', 'phone' => '+1 555 000 0005' ),
            array( 'name' => 'Sarah Taylor',  'first' => 'Sarah',   'last' => 'Taylor',  'email' => 'demo6@example.com', 'phone' => '+1 555 000 0006' ),
            array( 'name' => 'James Miller',  'first' => 'James',   'last' => 'Miller',  'email' => 'demo7@example.com', 'phone' => '+1 555 000 0007' ),
            array( 'name' => 'Linda White',   'first' => 'Linda',   'last' => 'White',   'email' => 'demo8@example.com', 'phone' => '+1 555 000 0008' ),
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function locations_en(): array
    {
        return array(
            array( 'name' => 'Istanbul Airport',      'code' => 'IST' ),
            array( 'name' => 'Sabiha Gokcen Airport', 'code' => 'SAW' ),
            array( 'name' => 'Taksim Square',         'code' => 'TAK' ),
            array( 'name' => 'Kadikoy Ferry Port',    'code' => 'KAD' ),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function addons_en(): array
    {
        return array(
            array( 'title' => 'Baby Seat',           'price' => 10, 'type' => 'per_day' ),
            array( 'title' => 'Additional Driver',   'price' => 15, 'type' => 'per_day' ),
            array( 'title' => 'GPS Navigation',      'price' =>  8, 'type' => 'per_day' ),
        );
    }
}
