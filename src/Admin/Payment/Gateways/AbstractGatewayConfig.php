<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base configuration class for payment gateways
 * 
 * This class provides common functionality for all payment gateways
 * and prevents code duplication.
 */
abstract class AbstractGatewayConfig
{
    /**
     * Caches WordPress settings
     */
    private static ?array $settings_cache = null;
    
    /**
     * Returns gateway prefix (overridden in child classes)
     * Examples: 'offline'
     */
    abstract protected static function getGatewayPrefix(): string;
    
    /**
     * Gets WordPress settings (cached)
     */
    protected static function getSettings(): array
    {
        if (self::$settings_cache === null) {
            self::$settings_cache = get_option('mhm_rentiva_settings', []);
        }
        
        return self::$settings_cache;
    }
    
    /**
     * Checks if gateway is active
     */
    public static function enabled(): bool
    {
        $settings = self::getSettings();
        $key = 'mhm_rentiva_' . self::getGatewayPrefix() . '_enabled';
        return (string) ($settings[$key] ?? '0') === '1';
    }
    
    /**
     * Checks if test mode is active
     */
    public static function testMode(): bool
    {
        $settings = self::getSettings();
        $key = 'mhm_rentiva_' . self::getGatewayPrefix() . '_test_mode';
        return (string) ($settings[$key] ?? '1') === '1';
    }
    
    /**
     * Gets setting for specified key
     */
    protected static function getSetting(string $key, string $default = ''): string
    {
        $settings = self::getSettings();
        $fullKey = 'mhm_rentiva_' . self::getGatewayPrefix() . '_' . $key;
        return (string) ($settings[$fullKey] ?? $default);
    }
    
    /**
     * Gets boolean setting for specified key
     */
    protected static function getBooleanSetting(string $key, bool $default = false): bool
    {
        $settings = self::getSettings();
        $fullKey = 'mhm_rentiva_' . self::getGatewayPrefix() . '_' . $key;
        return (string) ($settings[$fullKey] ?? ($default ? '1' : '0')) === '1';
    }
    
    /**
     * Gets integer setting for specified key
     */
    protected static function getIntegerSetting(string $key, int $default = 0): int
    {
        $settings = self::getSettings();
        $fullKey = 'mhm_rentiva_' . self::getGatewayPrefix() . '_' . $key;
        $value = $settings[$fullKey] ?? $default;
        return is_numeric($value) ? (int) $value : $default;
    }
    
    /**
     * Clears cache (called when settings change)
     */
    public static function clearCache(): void
    {
        self::$settings_cache = null;
    }
}
