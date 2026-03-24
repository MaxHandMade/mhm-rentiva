<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Versioning;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Deterministic blueprint manifest normalizer.
 *
 * Produces canonical arrays for hashing and structural diff operations.
 */
final class LayoutNormalization {
    /**
     * Normalize a validated manifest into deterministic structure.
     *
     * @param array $manifest Validated manifest.
     * @return array
     */
    public static function normalize(array $manifest): array
    {
        $normalized = self::normalize_value($manifest, null);

        if (! is_array($normalized)) {
            return array();
        }

        return $normalized;
    }

    /**
     * Normalize any value recursively.
     *
     * @param mixed       $value Value to normalize.
     * @param string|null $key Current key.
     * @return mixed
     */
    private static function normalize_value($value, ?string $key)
    {
        if (is_array($value)) {
            return self::is_list($value)
                ? self::normalize_list($value)
                : self::normalize_assoc($value);
        }

        return self::normalize_scalar($value, $key);
    }

    /**
     * Normalize associative array by pruning null values and sorting keys.
     *
     * @param array $value Associative array.
     * @return array
     */
    private static function normalize_assoc(array $value): array
    {
        $normalized = array();

        foreach ($value as $item_key => $item_value) {
            if (! is_string($item_key) && ! is_int($item_key)) {
                continue;
            }

            if ($item_value === null) {
                continue;
            }

            $normalized_key                = (string) $item_key;
            $normalized[ $normalized_key ] = self::normalize_value($item_value, $normalized_key);
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * Normalize indexed list while preserving element order.
     *
     * @param array $value List array.
     * @return array
     */
    private static function normalize_list(array $value): array
    {
        $normalized = array();

        foreach ($value as $item_value) {
            $normalized[] = self::normalize_value($item_value, null);
        }

        return $normalized;
    }

    /**
     * Normalize scalar values using deterministic canonicalization.
     *
     * @param mixed       $value Scalar value.
     * @param string|null $key Current key.
     * @return mixed
     */
    private static function normalize_scalar($value, ?string $key)
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $value;
        }

        $lower = strtolower($trimmed);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if (self::is_sensitive_identifier_key($key)) {
            return $value;
        }

        if (preg_match('/^-?(?:0|[1-9]\d*)$/', $trimmed) === 1) {
            return (int) $trimmed;
        }

        if (preg_match('/^-?(?:0|[1-9]\d*)\.\d+$/', $trimmed) === 1) {
            return (float) $trimmed;
        }

        return $value;
    }

    /**
     * Determine if current key likely represents identity and should be kept as string.
     *
     * @param string|null $key Key name.
     * @return bool
     */
    private static function is_sensitive_identifier_key(?string $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        $lower = strtolower($key);

        if ($lower === 'id' || $lower === 'slug') {
            return true;
        }

        return str_ends_with($lower, '_id') || str_ends_with($lower, '_slug');
    }

    /**
     * WordPress-compatible list detection for PHP 8.1.
     *
     * @param array $value Array to inspect.
     * @return bool
     */
    private static function is_list(array $value): bool
    {
        $index = 0;

        foreach ($value as $key => $_) {
            if ($key !== $index) {
                return false;
            }

            ++$index;
        }

        return true;
    }
}
