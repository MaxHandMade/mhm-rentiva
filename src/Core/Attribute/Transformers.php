<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Attribute Transformers
 *
 * Stateless utility for strict value coercion and normalization.
 *
 * @package MHMRentiva\Core\Attribute
 * @since 4.11.0
 */
final class Transformers
{

    /**
     * Transforms a value based on the specified type.
     *
     * @param mixed  $value  Raw value.
     * @param string $type   Target type (bool, int, float, date, idlist, url, enum, string).
     * @param array  $config Attribute configuration.
     * @return mixed Transformed value.
     */
    public static function transform($value, string $type, array $config = [])
    {
        switch ($type) {
            case 'bool':
                return self::to_bool_string($value);

            case 'int':
                return self::to_int($value, $config);

            case 'float':
                return self::to_float($value, $config);

            case 'date':
                return self::to_date($value, $config);

            case 'idlist':
                return self::to_idlist($value);

            case 'url':
                return self::to_url($value);

            case 'enum':
                return self::to_enum($value, $config);

            case 'string':
            default:
                return is_scalar($value) ? sanitize_text_field((string) $value) : ($config['default'] ?? '');
        }
    }

    /**
     * Coerce to "1" or "0", but preserve "default" for internal resolution.
     */
    private static function to_bool_string($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
            return '1';
        }
        if ('default' === $value) {
            return 'default';
        }
        return '0';
    }

    /**
     * Strict integer with clamping
     */
    private static function to_int($value, array $config): int
    {
        $int_val = (int) $value;
        if (isset($config['min'])) {
            $int_val = max($int_val, (int) $config['min']);
        }
        if (isset($config['max'])) {
            $int_val = min($int_val, (int) $config['max']);
        }
        return $int_val;
    }

    /**
     * Float for ratings/deposits
     */
    private static function to_float($value, array $config): float
    {
        $float_val = (float) $value;
        if (isset($config['min'])) {
            $float_val = max($float_val, (float) $config['min']);
        }
        if (isset($config['max'])) {
            $float_val = min($float_val, (float) $config['max']);
        }
        return $float_val;
    }

    /**
     * ISO Date Enforcement (Y-m-d)
     */
    private static function to_date($value, array $config): string
    {
        if (empty($value)) {
            return (string) ($config['default'] ?? '');
        }

        $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
        if ($d && $d->format('Y-m-d') === $value) {
            return $value;
        }

        // Try to parse other formats and normalize to Y-m-d
        try {
            $d = new \DateTime((string) $value);
            return $d->format('Y-m-d');
        } catch (\Exception $e) {
            return (string) ($config['default'] ?? '');
        }
    }

    /**
     * Parse comma-separated IDs
     */
    private static function to_idlist($value): string
    {
        if (empty($value)) {
            return '';
        }

        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $clean_ids = array_filter(array_map('absint', $ids));

        return implode(',', array_unique($clean_ids));
    }

    /**
     * Protocol-safe URL cleanup
     */
    private static function to_url($value): string
    {
        return esc_url_raw((string) $value);
    }

    /**
     * Strict Enum validation
     */
    private static function to_enum($value, array $config): string
    {
        $allowed = $config['values'] ?? [];
        if (in_array((string) $value, $allowed, true)) {
            return (string) $value;
        }
        return (string) ($config['default'] ?? ($allowed[0] ?? ''));
    }
}
