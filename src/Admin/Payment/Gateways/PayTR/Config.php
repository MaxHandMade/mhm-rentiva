<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\PayTR;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Payment\Gateways\AbstractGatewayConfig;

final class Config extends AbstractGatewayConfig
{
    protected static function getGatewayPrefix(): string
    {
        return 'paytr';
    }
    
    public static function merchantId(): string
    {
        return self::getSetting('merchant_id');
    }
    
    public static function merchantKey(): string
    {
        return self::getSetting('merchant_key');
    }
    
    public static function merchantSalt(): string
    {
        return self::getSetting('merchant_salt');
    }
    
    public static function noInstallment(): bool
    {
        return self::getBooleanSetting('no_installment', true);
    }
    
    public static function maxInstallment(): int
    {
        return self::getIntegerSetting('max_installment', 1);
    }
    
    public static function non3d(): bool
    {
        return self::getBooleanSetting('non_3d');
    }
    
    public static function timeoutLimit(): int
    {
        $timeout = self::getIntegerSetting('timeout_limit', 30);
        
        // PayTR timeout limitleri: minimum 10, maksimum 120 dakika
        if ($timeout < 10) $timeout = 10;
        if ($timeout > 120) $timeout = 120;
        
        return $timeout;
    }
    
    public static function debugOn(): bool
    {
        return self::getBooleanSetting('debug_on');
    }
}
