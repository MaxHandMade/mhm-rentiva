<?php

/**
 * WordPress & WooCommerce Unified Stubs for IDE
 *
 * This file provides stub declarations for WordPress and WooCommerce functions/classes
 * to prevent "Undefined function" errors in IDE (Intelephense).
 *
 * This file is NOT executed at runtime - it's only for IDE analysis.
 *
 * Merged from: stubs.php + wp-stubs.php
 *
 * @package MHMRentiva
 * @since 4.6.0
 *
 * @phpcs:disable
 */

// Prevent direct access and execution
if (defined('ABSPATH')) {
    return;
}

// ============================================================================
// WooCommerce Minimal Stubs (from stubs.php)
// ============================================================================

/**
 * WooCommerce Stubs
 */
if (! function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url()
    {
        return '';
    }
}

if (! function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency()
    {
        return 'USD';
    }
}

// ============================================================================
// WordPress Test Functions
// ============================================================================

if (!function_exists('tests_add_filter')) {
    /**
     * Adds a filter to a mock WordPress environment for testing purposes.
     *
     * @param string   $tag             The filter hook to which the function is added.
     * @param callable $function_to_add The function to be called when the filter is executed.
     * @param int      $priority        Optional. The priority of the function. Default 10.
     * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
     * @return bool Always returns true.
     */
    function tests_add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}

// ============================================================================
// WooCommerce Core Functions
// ============================================================================

/**
 * @param string $page
 * @return int
 */
function wc_get_page_id(string $page): int
{
    return 0;
}

/**
 * @param string $page
 * @return string
 */
function wc_get_page_permalink(string $page): string
{
    return '';
}

/**
 * @param string $endpoint
 * @param string $value
 * @param string $permalink
 * @return string
 */
function wc_get_endpoint_url(string $endpoint, string $value = '', string $permalink = ''): string
{
    return '';
}

/**
 * @return string
 */
function wc_get_cart_url(): string
{
    return '';
}

/**
 * @return string
 */
function wc_get_checkout_url(): string
{
    return '';
}

/**
 * @param float $price
 * @param array $args
 * @return string
 */
function wc_price(float $price, array $args = []): string
{
    return '';
}

/**
 * @return WooCommerce
 */
function WC(): WooCommerce
{
    return new WooCommerce();
}

/**
 * @return bool
 */
function is_woocommerce(): bool
{
    return false;
}

/**
 * @return bool
 */
function is_cart(): bool
{
    return false;
}

/**
 * @return bool
 */
function is_checkout(): bool
{
    return false;
}

/**
 * @return bool
 */
function is_account_page(): bool
{
    return false;
}

/**
 * @param string $message
 * @param string $notice_type
 * @param array $data
 * @return void
 */
function wc_add_notice(string $message, string $notice_type = 'success', array $data = []): void {}

/**
 * @return void
 */
function wc_print_notices(): void {}

/**
 * @param string $currency
 * @return string
 */
function get_woocommerce_currency_symbol(string $currency = ''): string
{
    return '';
}

/**
 * @return string
 */
function get_woocommerce_currency(): string
{
    return '';
}

/**
 * @return bool
 */
function wc_tax_enabled(): bool
{
    return false;
}

/**
 * @return bool
 */
function wc_prices_include_tax(): bool
{
    return false;
}

/**
 * @param mixed $the_order
 * @return WC_Order|false
 */
function wc_get_order($the_order = false)
{
    return new WC_Order();
}

// ============================================================================
// WooCommerce Classes
// ============================================================================

class WooCommerce
{
    /** @var WC_Cart */
    public $cart;
    /** @var WC_Session */
    public $session;
}

class WC_Cart
{
    public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = [], $cart_item_data = [])
    {
        return '';
    }
    public function get_cart(): array
    {
        return [];
    }
    public function get_cart_contents(): array
    {
        return [];
    }
    public function get_cart_contents_count(): int
    {
        return 0;
    }
    public function get_total(): string
    {
        return '';
    }
    public function empty_cart(): void {}
}

class WC_Order
{
    public function get_id(): int
    {
        return 0;
    }
    public function get_status(): string
    {
        return '';
    }
    public function get_total(): string
    {
        return '';
    }
    public function get_items(): array
    {
        return [];
    }
    public function get_billing_email(): string
    {
        return '';
    }
    public function get_billing_first_name(): string
    {
        return '';
    }
    public function get_billing_last_name(): string
    {
        return '';
    }
    public function get_billing_phone(): string
    {
        return '';
    }
    public function update_status($status, $note = ''): bool
    {
        return true;
    }
    public function add_order_note($note): int
    {
        return 0;
    }
}

class WC_Product
{
    public function get_id(): int
    {
        return 0;
    }
    public function get_name(): string
    {
        return '';
    }
    public function get_price(): string
    {
        return '';
    }
    public function get_regular_price(): string
    {
        return '';
    }
    public function get_sale_price(): string
    {
        return '';
    }
    public function is_on_sale(): bool
    {
        return false;
    }
    public function is_in_stock(): bool
    {
        return true;
    }
}

class WC_Tax
{
    public static function get_rates($tax_class = ''): array
    {
        return [];
    }
    public static function calc_tax($price, $rates, $price_includes_tax = false): array
    {
        return [];
    }
    public static function get_rate_percent($rate_id): string
    {
        return '';
    }
}

class WC_Session
{
    public function get($key, $default = null)
    {
        return $default;
    }
    public function set($key, $value): void {}
}

// ============================================================================
// WordPress Unit Test Stubs
// ============================================================================

if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends \PHPUnit\Framework\TestCase
    {
        public $factory;
        public function assertWPError($actual, $message = '') {}
    }
}

if (!class_exists('WP_Ajax_UnitTestCase')) {
    class WP_Ajax_UnitTestCase extends WP_UnitTestCase
    {
        protected $_last_response = '';
        public function _handleAjax($action) {}
    }
}

if (!class_exists('WPAjaxDieContinueException')) {
    class WPAjaxDieContinueException extends \Exception {}
}

if (!class_exists('WPAjaxDieStopException')) {
    class WPAjaxDieStopException extends \Exception {}
}
