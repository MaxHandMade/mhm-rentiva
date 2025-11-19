<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ VALIDATION EXCEPTION - Validation işlemleri için özel exception
 */
final class ValidationException extends MHMException
{
    /**
     * Validation exception kodları
     */
    public const CODE_REQUIRED_FIELD = 422;
    public const CODE_INVALID_FORMAT = 422;
    public const CODE_INVALID_RANGE = 422;
    public const CODE_DUPLICATE_VALUE = 409;
    public const CODE_INVALID_TYPE = 422;
    public const CODE_TOO_LONG = 422;
    public const CODE_TOO_SHORT = 422;
    public const CODE_INVALID_CHOICE = 422;

    /**
     * Validation hataları
     */
    protected array $validation_errors = [];

    /**
     * Constructor
     * 
     * @param string $message Exception mesajı
     * @param int $code Exception kodu
     * @param \Throwable|null $previous Önceki exception
     * @param array $context Ek context bilgisi
     * @param array $validation_errors Validation hataları
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
        array $validation_errors = []
    ) {
        parent::__construct($message, $code, $previous, 'validation', $context);
        $this->validation_errors = $validation_errors;
    }

    /**
     * Validation hatalarını al
     * 
     * @return array
     */
    public function getValidationErrors(): array
    {
        return $this->validation_errors;
    }

    /**
     * Validation hatalarını set et
     * 
     * @param array $errors
     */
    public function setValidationErrors(array $errors): void
    {
        $this->validation_errors = $errors;
    }

    /**
     * Gerekli alan eksik exception'ı
     * 
     * @param string $field_name Alan adı
     * @param array $context Ek context
     * @return static
     */
    public static function requiredField(string $field_name, array $context = []): self
    {
        return new self(
            /* translators: %s: field label. */
            sprintf(__('"%s" field is required.', 'mhm-rentiva'), $field_name),
            self::CODE_REQUIRED_FIELD,
            null,
            array_merge($context, ['field_name' => $field_name]),
            [$field_name => __('This field is required.', 'mhm-rentiva')]
        );
    }

    /**
     * Geçersiz format exception'ı
     * 
     * @param string $field_name Alan adı
     * @param string $expected_format Beklenen format
     * @param array $context Ek context
     * @return static
     */
    public static function invalidFormat(string $field_name, string $expected_format, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Expected format */
            sprintf(__('"%1$s" field has invalid format. Expected: %2$s', 'mhm-rentiva'), $field_name, $expected_format),
            self::CODE_INVALID_FORMAT,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'expected_format' => $expected_format
            ]),
            /* translators: %s placeholder. */
            [$field_name => sprintf(__('Invalid format. Expected: %s', 'mhm-rentiva'), $expected_format)]
        );
    }

    /**
     * Geçersiz aralık exception'ı
     * 
     * @param string $field_name Alan adı
     * @param mixed $value Değer
     * @param mixed $min Min değer
     * @param mixed $max Max değer
     * @param array $context Ek context
     * @return static
     */
    public static function invalidRange(string $field_name, $value, $min, $max, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Minimum value, 3: Maximum value */
            sprintf(__('"%1$s" value is out of range. Min: %2$s, Max: %3$s', 'mhm-rentiva'), $field_name, $min, $max),
            self::CODE_INVALID_RANGE,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'value' => $value,
                'min' => $min,
                'max' => $max
            ]),
            /* translators: 1: Minimum value, 2: Maximum value */
            [$field_name => sprintf(__('Out of range. Min: %1$s, Max: %2$s', 'mhm-rentiva'), $min, $max)]
        );
    }

    /**
     * Duplicate value exception'ı
     * 
     * @param string $field_name Alan adı
     * @param mixed $value Duplicate değer
     * @param array $context Ek context
     * @return static
     */
    public static function duplicateValue(string $field_name, $value, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Value */
            sprintf(__('"%1$s" value already exists: %2$s', 'mhm-rentiva'), $field_name, $value),
            self::CODE_DUPLICATE_VALUE,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'value' => $value
            ]),
            /* translators: %s placeholder. */
            [$field_name => sprintf(__('This value already exists: %s', 'mhm-rentiva'), $value)]
        );
    }

    /**
     * Geçersiz tip exception'ı
     * 
     * @param string $field_name Alan adı
     * @param string $expected_type Beklenen tip
     * @param string $actual_type Gerçek tip
     * @param array $context Ek context
     * @return static
     */
    public static function invalidType(string $field_name, string $expected_type, string $actual_type, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Expected type, 3: Actual type */
            sprintf(__('"%1$s" field has invalid type. Expected: %2$s, Actual: %3$s', 'mhm-rentiva'), $field_name, $expected_type, $actual_type),
            self::CODE_INVALID_TYPE,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'expected_type' => $expected_type,
                'actual_type' => $actual_type
            ]),
            /* translators: %s placeholder. */
            [$field_name => sprintf(__('Invalid type. Expected: %s', 'mhm-rentiva'), $expected_type)]
        );
    }

    /**
     * Çok uzun exception'ı
     * 
     * @param string $field_name Alan adı
     * @param int $length Mevcut uzunluk
     * @param int $max_length Max uzunluk
     * @param array $context Ek context
     * @return static
     */
    public static function tooLong(string $field_name, int $length, int $max_length, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Current length, 3: Maximum length */
            sprintf(__('"%1$s" is too long. Current: %2$d, Max: %3$d', 'mhm-rentiva'), $field_name, $length, $max_length),
            self::CODE_TOO_LONG,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'length' => $length,
                'max_length' => $max_length
            ]),
            /* translators: %d placeholder. */
            [$field_name => sprintf(__('Too long. Max: %d characters', 'mhm-rentiva'), $max_length)]
        );
    }

    /**
     * Çok kısa exception'ı
     * 
     * @param string $field_name Alan adı
     * @param int $length Mevcut uzunluk
     * @param int $min_length Min uzunluk
     * @param array $context Ek context
     * @return static
     */
    public static function tooShort(string $field_name, int $length, int $min_length, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Current length, 3: Minimum length */
            sprintf(__('"%1$s" is too short. Current: %2$d, Min: %3$d', 'mhm-rentiva'), $field_name, $length, $min_length),
            self::CODE_TOO_SHORT,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'length' => $length,
                'min_length' => $min_length
            ]),
            /* translators: %d placeholder. */
            [$field_name => sprintf(__('Too short. Min: %d characters', 'mhm-rentiva'), $min_length)]
        );
    }

    /**
     * Geçersiz seçim exception'ı
     * 
     * @param string $field_name Alan adı
     * @param mixed $value Seçilen değer
     * @param array $allowed_values İzin verilen değerler
     * @param array $context Ek context
     * @return static
     */
    public static function invalidChoice(string $field_name, $value, array $allowed_values, array $context = []): self
    {
        return new self(
            /* translators: 1: Field name, 2: Allowed values */
            sprintf(__('"%1$s" invalid choice. Allowed: %2$s', 'mhm-rentiva'), $field_name, implode(', ', $allowed_values)),
            self::CODE_INVALID_CHOICE,
            null,
            array_merge($context, [
                'field_name' => $field_name,
                'value' => $value,
                'allowed_values' => $allowed_values
            ]),
            /* translators: %s placeholder. */
            [$field_name => sprintf(__('Invalid choice. Allowed: %s', 'mhm-rentiva'), implode(', ', $allowed_values))]
        );
    }

    /**
     * Multiple validation errors exception'ı
     * 
     * @param array $errors Validation hataları
     * @param array $context Ek context
     * @return static
     */
    public static function multipleErrors(array $errors, array $context = []): self
    {
        return new self(
            /* translators: %d placeholder. */
            sprintf(__('Validation failed: %d errors', 'mhm-rentiva'), count($errors)),
            self::CODE_REQUIRED_FIELD,
            null,
            array_merge($context, ['error_count' => count($errors)]),
            $errors
        );
    }
}
