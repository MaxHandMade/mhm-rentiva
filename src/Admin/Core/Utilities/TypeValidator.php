<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\I18nHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ TİP GÜVENLİĞİ İYİLEŞTİRMESİ - Type Validator
 * 
 * Tip güvenliği ve validasyon için merkezi sınıf
 */
final class TypeValidator
{
    /**
     * Integer değeri doğrula ve döndür
     */
    public static function validateInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }
        
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        
        return $default;
    }

    /**
     * String değeri doğrula ve döndür
     */
    public static function validateString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        return $default;
    }

    /**
     * Array değeri doğrula ve döndür
     */
    public static function validateArray(mixed $value, array $default = []): array
    {
        if (is_array($value)) {
            return $value;
        }
        
        return $default;
    }

    /**
     * Boolean değeri doğrula ve döndür
     */
    public static function validateBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        return $default;
    }

    /**
     * Float değeri doğrula ve döndür
     */
    public static function validateFloat(mixed $value, float $default = 0.0): float
    {
        if (is_float($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        return $default;
    }

    /**
     * Null olmayan değer döndür
     */
    public static function validateNotNull(mixed $value, mixed $default = null): mixed
    {
        return $value !== null ? $value : $default;
    }

    /**
     * Post ID doğrula
     */
    public static function validatePostId(mixed $value): ?int
    {
        $id = self::validateInt($value);
        return $id > 0 && get_post($id) ? $id : null;
    }

    /**
     * User ID doğrula
     */
    public static function validateUserId(mixed $value): ?int
    {
        $id = self::validateInt($value);
        return $id > 0 && get_user_by('id', $id) ? $id : null;
    }

    /**
     * Email adresi doğrula
     */
    public static function validateEmail(mixed $value): ?string
    {
        $email = self::validateString($value);
        return is_email($email) ? $email : null;
    }

    /**
     * URL doğrula
     */
    public static function validateUrl(mixed $value): ?string
    {
        $url = self::validateString($value);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Date string doğrula
     */
    public static function validateDate(mixed $value, string $format = 'Y-m-d'): ?string
    {
        $date = self::validateString($value);
        if (empty($date)) {
            return null;
        }
        
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date ? $date : null;
    }

    /**
     * Enum değeri doğrula
     */
    public static function validateEnum(mixed $value, array $allowed_values, mixed $default = null): mixed
    {
        if (in_array($value, $allowed_values, true)) {
            return $value;
        }
        
        return $default;
    }

    /**
     * Array içindeki tüm değerleri doğrula
     */
    public static function validateArrayOf(mixed $value, string $type, mixed $default = []): array
    {
        $array = self::validateArray($value, $default);
        $validated = [];
        
        foreach ($array as $item) {
            switch ($type) {
                case 'int':
                    $validated[] = self::validateInt($item);
                    break;
                case 'string':
                    $validated[] = self::validateString($item);
                    break;
                case 'bool':
                    $validated[] = self::validateBool($item);
                    break;
                case 'float':
                    $validated[] = self::validateFloat($item);
                    break;
                default:
                    $validated[] = $item;
            }
        }
        
        return $validated;
    }

    /**
     * WordPress post array'i doğrula
     */
    public static function validatePostArray(mixed $value): array
    {
        $array = self::validateArray($value);
        
        // Gerekli alanları kontrol et
        $required_fields = ['id', 'name', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($array[$field])) {
                $array[$field] = '';
            }
        }
        
        return $array;
    }

    /**
     * WordPress user array'i doğrula
     */
    public static function validateUserArray(mixed $value): array
    {
        $array = self::validateArray($value);
        
        // Gerekli alanları kontrol et
        $required_fields = ['id', 'name', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($array[$field])) {
                $array[$field] = '';
            }
        }
        
        return $array;
    }

    /**
     * Type assertion - runtime'da tip kontrolü
     */
    public static function assertType(mixed $value, string $expected_type): mixed
    {
        $actual_type = gettype($value);
        
        if ($actual_type !== $expected_type) {
            throw new \TypeError(
                I18nHelper::sprintf(
                    'Beklenen tip %s, alınan tip %s',
                    $expected_type,
                    $actual_type
                )
            );
        }
        
        return $value;
    }

    /**
     * Safe type casting
     */
    public static function safeCast(mixed $value, string $target_type): mixed
    {
        switch ($target_type) {
            case 'int':
                return self::validateInt($value);
            case 'string':
                return self::validateString($value);
            case 'bool':
                return self::validateBool($value);
            case 'float':
                return self::validateFloat($value);
            case 'array':
                return self::validateArray($value);
            default:
                return $value;
        }
    }
}
