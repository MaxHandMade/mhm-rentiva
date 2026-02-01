<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payment History Shortcode
 */
final class PaymentHistory extends AbstractAccountShortcode
{

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_payment_history';
    }

    protected static function get_template_path(): string
    {
        return 'account/payment-history';
    }

    protected static function get_default_attributes(): array
    {
        return array(
            'limit'    => '20',
            'hide_nav' => false,
        );
    }

    protected static function prepare_template_data(array $atts): array
    {
        return AccountRenderer::get_payment_history_data($atts);
    }
}
