<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Gateways\Stripe;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Payment\Gateways\AbstractGatewayConfig;

final class Config extends AbstractGatewayConfig
{
    protected static function getGatewayPrefix(): string
    {
        return 'stripe';
    }
    
    public static function secretKey(): string
    {
        return self::getSetting('secret_key');
    }
    
    public static function accountCountry(): string
    {
        return self::getSetting('country', 'TR');
    }
    
    /**
     * Legacy methods from Core\Config.php
     * TODO: Migrate to new structure
     */
    public static function mode(): string
    {
        $opt = get_option('mhm_rentiva_stripe_mode');
        $mode = is_string($opt) && in_array($opt, ['test','live'], true) ? $opt : (defined('RENTIVA_STRIPE_MODE') ? (string) constant('RENTIVA_STRIPE_MODE') : 'test');
        return $mode === 'live' ? 'live' : 'test';
    }

    public static function pk(): ?string
    {
        $mode = self::mode();
        $key = $mode === 'live' ? get_option('mhm_rentiva_stripe_pk_live') : get_option('mhm_rentiva_stripe_pk_test');
        if (empty($key) && defined('RENTIVA_STRIPE_PK_' . strtoupper($mode))) {
            $key = (string) constant('RENTIVA_STRIPE_PK_' . strtoupper($mode));
        }
        $key = is_string($key) ? trim($key) : '';
        return $key !== '' ? $key : null;
    }

    public static function sk(): ?string
    {
        $mode = self::mode();
        $key = $mode === 'live' ? get_option('mhm_rentiva_stripe_sk_live') : get_option('mhm_rentiva_stripe_sk_test');
        if (empty($key) && defined('RENTIVA_STRIPE_SK_' . strtoupper($mode))) {
            $key = (string) constant('RENTIVA_STRIPE_SK_' . strtoupper($mode));
        }
        $key = is_string($key) ? trim($key) : '';
        return $key !== '' ? $key : null;
    }

    public static function webhookSecret(): ?string
    {
        $mode = self::mode();
        $key = $mode === 'live' ? get_option('mhm_rentiva_stripe_webhook_secret_live') : get_option('mhm_rentiva_stripe_webhook_secret_test');
        if (empty($key) && defined('RENTIVA_STRIPE_WEBHOOK_SECRET_' . strtoupper($mode))) {
            $key = (string) constant('RENTIVA_STRIPE_WEBHOOK_SECRET_' . strtoupper($mode));
        }
        $key = is_string($key) ? trim($key) : '';
        return $key !== '' ? $key : null;
    }
}
