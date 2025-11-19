<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

use MHMRentiva\Admin\Reports\Reports;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper utilities for vehicle card field configuration and rendering.
 *
 * @since 4.3.9
 */
final class VehicleFeatureHelper
{
    public const TYPE_DETAIL    = 'detail';
    public const TYPE_FEATURE   = 'feature';
    public const TYPE_EQUIPMENT = 'equipment';

    /**
     * Default card field layout (maintains backwards compatibility).
     */
    public static function get_default_card_fields(): array
    {
        return [
            ['type' => self::TYPE_DETAIL, 'key' => 'fuel_type'],
            ['type' => self::TYPE_DETAIL, 'key' => 'transmission'],
            ['type' => self::TYPE_DETAIL, 'key' => 'seats'],
            ['type' => self::TYPE_DETAIL, 'key' => 'year'],
            ['type' => self::TYPE_DETAIL, 'key' => 'mileage'],
        ];
    }

    /**
     * Returns selected card fields, sanitized against current settings.
     */
    public static function get_selected_card_fields(): array
    {
        $raw = SettingsCore::get('mhm_rentiva_vehicle_card_fields', self::get_default_card_fields());
        return self::sanitize_card_field_selection($raw);
    }

    /**
     * Sanitize raw card field selection payload.
     *
     * @param mixed $raw
     * @return array<int, array{type:string,key:string}>
     */
    public static function sanitize_card_field_selection($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw)) {
            $raw = [];
        }

        $available = self::get_available_fields_map();
        $sanitized = [];
        $dedupe    = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            $key  = isset($item['key']) ? sanitize_key($item['key']) : '';

            if ($type === '' || $key === '') {
                continue;
            }

            if (!isset($available[$type][$key])) {
                // Skip fields that are no longer active/available.
                continue;
            }

            $dedupe_key = $type . ':' . $key;
            if (isset($dedupe[$dedupe_key])) {
                continue;
            }

            $dedupe[$dedupe_key] = true;
            $sanitized[] = [
                'type' => $type,
                'key'  => $key,
            ];
        }

        if (empty($sanitized)) {
            // Fall back to defaults filtered by availability
            foreach (self::get_default_card_fields() as $item) {
                $type = $item['type'];
                $key  = $item['key'];

                if (isset($available[$type][$key])) {
                    $sanitized[] = ['type' => $type, 'key' => $key];
                }
            }
        }

        return $sanitized;
    }

    /**
     * Returns available vehicle fields grouped by type.
     *
     * @return array<string, array<string, array{label:string,meta_key:string,type:string}>>
     */
    public static function get_available_fields_map(): array
    {
        $result = [
            self::TYPE_DETAIL    => [],
            self::TYPE_FEATURE   => [],
            self::TYPE_EQUIPMENT => [],
        ];

        // Details
        $selected_details = (array) get_option('mhm_selected_details', VehicleSettings::get_default_selected_details());
        $all_details      = (array) get_option('mhm_vehicle_details', VehicleSettings::get_default_details());

        foreach ($selected_details as $key) {
            $key = sanitize_key($key);
            if ($key === '' || !isset($all_details[$key])) {
                continue;
            }

            $result[self::TYPE_DETAIL][$key] = [
                'label'    => self::sanitize_label($all_details[$key], $key),
                'meta_key' => self::map_detail_meta_key($key),
                'type'     => self::TYPE_DETAIL,
                'key'      => $key,
            ];
        }

        // Features
        $selected_features = (array) get_option('mhm_selected_features', VehicleSettings::get_default_selected_features());
        $all_features      = (array) get_option('mhm_vehicle_features', VehicleSettings::get_default_features());

        foreach ($selected_features as $key) {
            $key = sanitize_key($key);
            if ($key === '' || !isset($all_features[$key])) {
                continue;
            }

            $result[self::TYPE_FEATURE][$key] = [
                'label'    => self::sanitize_label($all_features[$key], $key),
                'meta_key' => '_mhm_rentiva_features',
                'type'     => self::TYPE_FEATURE,
                'key'      => $key,
            ];
        }

        // Equipment
        $selected_equipment = (array) get_option('mhm_selected_equipment', VehicleSettings::get_default_selected_equipment());
        $all_equipment      = (array) get_option('mhm_vehicle_equipment', VehicleSettings::get_default_equipment());

        foreach ($selected_equipment as $key) {
            $key = sanitize_key($key);
            if ($key === '' || !isset($all_equipment[$key])) {
                continue;
            }

            $result[self::TYPE_EQUIPMENT][$key] = [
                'label'    => self::sanitize_label($all_equipment[$key], $key),
                'meta_key' => '_mhm_rentiva_equipment',
                'type'     => self::TYPE_EQUIPMENT,
                'key'      => $key,
            ];
        }

        return $result;
    }

    /**
     * Collect card display items for a vehicle according to current configuration.
     *
     * @return array<int, array{text:string,icon:string|null,type:string,key:string}>
     */
    public static function collect_items(int $vehicle_id): array
    {
        if (SettingsCore::get('mhm_rentiva_vehicle_show_features', '1') !== '1') {
            return [];
        }

        $selected = self::get_selected_card_fields();
        if (empty($selected)) {
            return [];
        }

        $available = self::get_available_fields_map();
        $details_meta   = self::preload_detail_meta($vehicle_id);
        $features_meta  = self::preload_multi_meta($vehicle_id, '_mhm_rentiva_features');
        $equipment_meta = self::preload_multi_meta($vehicle_id, '_mhm_rentiva_equipment');

        $items = [];

        foreach ($selected as $item) {
            $type = $item['type'];
            $key  = $item['key'];

            if (!isset($available[$type][$key])) {
                continue;
            }

            $label = $available[$type][$key]['label'];

            if ($type === self::TYPE_DETAIL) {
                $meta_key = $available[$type][$key]['meta_key'];
                $raw      = $details_meta[$meta_key] ?? '';
                if (($raw === '' || $raw === null) && $key === 'year') {
                    $raw = $details_meta['_mhm_rentiva_model_year'] ?? $details_meta['_mhm_rentiva_year'] ?? '';
                }
                $formatted = self::format_detail_value($key, $raw);

                if ($formatted !== null) {
                    $items[] = [
                        'text' => $formatted['text'],
                        'icon' => $formatted['icon'],
                        'type' => $type,
                        'key'  => $key,
                    ];
                }
            } elseif ($type === self::TYPE_FEATURE) {
                if (in_array($key, $features_meta, true)) {
                    $items[] = [
                        'text' => $label,
                        'icon' => 'default',
                        'type' => $type,
                        'key'  => $key,
                    ];
                }
            } elseif ($type === self::TYPE_EQUIPMENT) {
                if (in_array($key, $equipment_meta, true)) {
                    $items[] = [
                        'text' => $label,
                        'icon' => 'default',
                        'type' => $type,
                        'key'  => $key,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Helper: sanitize label output.
     */
    private static function sanitize_label($label, string $fallback): string
    {
        $label = is_string($label) ? $label : $fallback;
        $label = trim($label);

        return $label !== '' ? $label : ucfirst(str_replace('_', ' ', $fallback));
    }

    /**
     * Map detail keys to post meta keys.
     */
    private static function map_detail_meta_key(string $key): string
    {
        $map = [
            'price_per_day' => '_mhm_rentiva_price_per_day',
            'year'          => '_mhm_rentiva_year',
            'model_year'    => '_mhm_rentiva_model_year',
            'mileage'       => '_mhm_rentiva_mileage',
            'license_plate' => '_mhm_rentiva_license_plate',
            'color'         => '_mhm_rentiva_color',
            'brand'         => '_mhm_rentiva_brand',
            'model'         => '_mhm_rentiva_model',
            'seats'         => '_mhm_rentiva_seats',
            'doors'         => '_mhm_rentiva_doors',
            'transmission'  => '_mhm_rentiva_transmission',
            'fuel_type'     => '_mhm_rentiva_fuel_type',
            'engine_size'   => '_mhm_rentiva_engine_size',
            'availability'  => '_mhm_vehicle_status',
            'deposit'       => '_mhm_rentiva_deposit',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        // Custom details fallback
        return '_mhm_rentiva_' . $key;
    }

    /**
     * Preload detailed meta values for a vehicle.
     *
     * @return array<string, mixed>
     */
    private static function preload_detail_meta(int $vehicle_id): array
    {
        $meta_keys = [
            '_mhm_rentiva_price_per_day',
            '_mhm_rentiva_model_year',
            '_mhm_rentiva_year',
            '_mhm_rentiva_mileage',
            '_mhm_rentiva_license_plate',
            '_mhm_rentiva_color',
            '_mhm_rentiva_brand',
            '_mhm_rentiva_model',
            '_mhm_rentiva_seats',
            '_mhm_rentiva_doors',
            '_mhm_rentiva_transmission',
            '_mhm_rentiva_fuel_type',
            '_mhm_rentiva_engine_size',
            '_mhm_rentiva_deposit',
            '_mhm_vehicle_status',
        ];

        $meta = [];
        foreach ($meta_keys as $key) {
            $meta[$key] = get_post_meta($vehicle_id, $key, true);
        }

        $custom_details = (array) get_option('mhm_custom_details', []);
        foreach (array_keys($custom_details) as $custom_key) {
            $meta['_mhm_rentiva_' . $custom_key] = get_post_meta($vehicle_id, '_mhm_rentiva_' . $custom_key, true);
        }

        return $meta;
    }

    /**
     * Preload array meta values (features/equipment).
     *
     * @return string[]
     */
    private static function preload_multi_meta(int $vehicle_id, string $meta_key): array
    {
        $value = get_post_meta($vehicle_id, $meta_key, true);

        if (is_array($value)) {
            return array_values(array_unique(array_map('sanitize_key', $value)));
        }

        return [];
    }

    /**
     * Format detail values and choose icons.
     *
     * @return array{text:string,icon:string}|null
     */
    private static function format_detail_value(string $key, $raw): ?array
    {
        $text = '';
        $icon = 'default';

        switch ($key) {
            case 'fuel_type':
                $map = [
                    'petrol'   => __('Petrol', 'mhm-rentiva'),
                    'diesel'   => __('Diesel', 'mhm-rentiva'),
                    'hybrid'   => __('Hybrid', 'mhm-rentiva'),
                    'electric' => __('Electric', 'mhm-rentiva'),
                ];
                $raw = sanitize_key($raw);
                $text = $map[$raw] ?? ucfirst($raw);
                $icon = 'fuel';
                break;

            case 'transmission':
                $map = [
                    'auto'     => __('Automatic', 'mhm-rentiva'),
                    'automatic'=> __('Automatic', 'mhm-rentiva'),
                    'manual'   => __('Manual', 'mhm-rentiva'),
                ];
                $raw = sanitize_key($raw);
                $text = $map[$raw] ?? ucfirst($raw);
                $icon = 'gear';
                break;

            case 'seats':
                $count = intval($raw);
                if ($count <= 0) {
                    return null;
                }
                /* translators: %d placeholder. */
                $text = sprintf(__('%d People', 'mhm-rentiva'), $count);
                $icon = 'people';
                break;

            case 'doors':
                $count = intval($raw);
                if ($count <= 0) {
                    return null;
                }
                /* translators: %d placeholder. */
                $text = sprintf(__('%d Doors', 'mhm-rentiva'), $count);
                break;

            case 'year':
            case 'model_year':
                $value = trim((string) $raw);
                if ($value === '') {
                    return null;
                }
                $text = $value;
                $icon = 'calendar';
                break;

            case 'mileage':
                $numeric = floatval(str_replace(['.', ',', ' '], '', (string) $raw));
                if ($numeric <= 0) {
                    return null;
                }
                $text = number_format($numeric, 0, ',', '.') . ' ' . __('km', 'mhm-rentiva');
                $icon = 'speedometer';
                break;

            case 'price_per_day':
                $numeric = floatval($raw);
                if ($numeric <= 0) {
                    return null;
                }
                $text = self::format_price($numeric);
                break;

            case 'deposit':
                $numeric = floatval($raw);
                if ($numeric <= 0) {
                    return null;
                }
                $text = __('Deposit:', 'mhm-rentiva') . ' ' . self::format_price($numeric);
                break;

            case 'engine_size':
                $engine = floatval($raw);
                if ($engine <= 0) {
                    return null;
                }
                $text = number_format($engine, 1, '.', ',') . ' ' . __('L', 'mhm-rentiva');
                break;

            case 'availability':
                $map = [
                    'active'      => __('Active', 'mhm-rentiva'),
                    'inactive'    => __('Inactive', 'mhm-rentiva'),
                    'maintenance' => __('Maintenance', 'mhm-rentiva'),
                    'passive'     => __('Inactive', 'mhm-rentiva'),
                ];
                $raw = sanitize_key($raw);
                $text = $map[$raw] ?? ucfirst($raw);
                break;

            default:
                $value = trim((string) $raw);
                if ($value === '') {
                    return null;
                }
                $text = $value;
                break;
        }

        if ($text === '') {
            return null;
        }

        return [
            'text' => $text,
            'icon' => $icon,
        ];
    }

    /**
     * Format price with plugin currency configuration.
     */
    private static function format_price(float $price): string
    {
        $symbol   = Reports::get_currency_symbol();
        $position = SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
        $formatted = number_format($price, 0, ',', '.');

        switch ($position) {
            case 'left':
                return $symbol . $formatted;
            case 'left_space':
                return $symbol . ' ' . $formatted;
            case 'right':
                return $formatted . $symbol;
            default:
                return $formatted . ' ' . $symbol;
        }
    }
}

