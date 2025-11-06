<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ KOD KALİTESİ İYİLEŞTİRMESİ - Meta Query Helper
 * 
 * Tekrarlanan meta query paternlerini merkezi olarak yönetir
 */
final class MetaQueryHelper
{
    /**
     * Meta field için LEFT JOIN oluştur
     */
    public static function left_join_meta(string $alias, string $meta_key): string
    {
        global $wpdb;
        $safe_meta_key = $wpdb->prepare('%s', $meta_key);
        return "LEFT JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = {$safe_meta_key}";
    }

    /**
     * Meta field için INNER JOIN oluştur
     */
    public static function inner_join_meta(string $alias, string $meta_key): string
    {
        global $wpdb;
        $safe_meta_key = $wpdb->prepare('%s', $meta_key);
        return "INNER JOIN {$wpdb->postmeta} {$alias} ON p.ID = {$alias}.post_id AND {$alias}.meta_key = {$safe_meta_key}";
    }

    /**
     * Birden fazla meta field için JOIN'ler oluştur
     */
    public static function build_meta_joins(array $meta_fields, string $join_type = 'LEFT'): array
    {
        $joins = [];
        $selects = [];
        
        foreach ($meta_fields as $alias => $config) {
            $meta_key = $config['meta_key'];
            $select_alias = $config['select_alias'] ?? $alias;
            $default_value = $config['default_value'] ?? '';
            
            if ($join_type === 'INNER') {
                $joins[] = self::inner_join_meta($alias, $meta_key);
            } else {
                $joins[] = self::left_join_meta($alias, $meta_key);
            }
            
            if ($default_value !== '') {
                $selects[] = "COALESCE({$alias}.meta_value, '{$default_value}') as {$select_alias}";
            } else {
                $selects[] = "{$alias}.meta_value as {$select_alias}";
            }
        }
        
        return [
            'joins' => $joins,
            'selects' => $selects
        ];
    }

    /**
     * Message meta fields için standart JOIN'ler
     */
    public static function get_message_meta_joins(): array
    {
        $meta_fields = [
            'pm_customer_name' => [
                'meta_key' => '_mhm_customer_name',
                'select_alias' => 'customer_name',
                'default_value' => ''
            ],
            'pm_customer_email' => [
                'meta_key' => '_mhm_customer_email',
                'select_alias' => 'customer_email',
                'default_value' => ''
            ],
            'pm_category' => [
                'meta_key' => '_mhm_message_category',
                'select_alias' => 'category',
                'default_value' => 'general'
            ],
            'pm_status' => [
                'meta_key' => '_mhm_message_status',
                'select_alias' => 'status',
                'default_value' => 'pending'
            ],
            'pm_thread' => [
                'meta_key' => '_mhm_thread_id',
                'select_alias' => 'thread_id',
                'default_value' => 'p.ID'
            ],
            'pm_read' => [
                'meta_key' => '_mhm_is_read',
                'select_alias' => 'is_read',
                'default_value' => '0'
            ],
            'pm_parent' => [
                'meta_key' => '_mhm_parent_message_id',
                'select_alias' => 'parent_message_id',
                'default_value' => '0'
            ],
            'pm_priority' => [
                'meta_key' => '_mhm_message_priority',
                'select_alias' => 'priority',
                'default_value' => 'normal'
            ]
        ];

        return self::build_meta_joins($meta_fields);
    }

    /**
     * Booking meta fields için standart JOIN'ler
     */
    public static function get_booking_meta_joins(): array
    {
        $meta_fields = [
            'email_meta' => [
                'meta_key' => 'mhm_rentiva_customer_email',
                'select_alias' => 'customer_email',
                'default_value' => ''
            ],
            'name_meta' => [
                'meta_key' => 'mhm_rentiva_customer_name',
                'select_alias' => 'customer_name',
                'default_value' => ''
            ],
            'phone_meta' => [
                'meta_key' => 'mhm_rentiva_customer_phone',
                'select_alias' => 'customer_phone',
                'default_value' => ''
            ],
            'price_meta' => [
                'meta_key' => 'mhm_rentiva_total_price',
                'select_alias' => 'total_price',
                'default_value' => '0'
            ]
        ];

        return self::build_meta_joins($meta_fields);
    }

    /**
     * Vehicle meta fields için standart JOIN'ler
     */
    public static function get_vehicle_meta_joins(): array
    {
        $meta_fields = [
            'price_meta' => [
                'meta_key' => '_mhm_rentiva_price_per_day',
                'select_alias' => 'price_per_day',
                'default_value' => '0'
            ],
            'featured_meta' => [
                'meta_key' => '_mhm_rentiva_featured',
                'select_alias' => 'featured',
                'default_value' => '0'
            ]
        ];

        return self::build_meta_joins($meta_fields);
    }

    /**
     * Meta query için WHERE koşulu oluştur
     */
    public static function build_meta_where(string $alias, string $value, string $operator = '='): string
    {
        global $wpdb;
        return $wpdb->prepare("{$alias}.meta_value {$operator} %s", $value);
    }

    /**
     * Meta query için IN koşulu oluştur
     */
    public static function build_meta_in(string $alias, array $values): string
    {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($values), '%s'));
        return $wpdb->prepare("{$alias}.meta_value IN ({$placeholders})", $values);
    }
}
