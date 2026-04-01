<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Manages paid vehicle listing fees via WooCommerce.
 *
 * Handles fee configuration checks, WC product creation,
 * cart management, and order completion hooks that transition
 * vehicles through the lifecycle after successful payment.
 *
 * @since 4.25.0
 */
final class ListingFeeManager
{
    /** @var string WooCommerce product SKU for listing fee items. */
    public const PRODUCT_SKU = 'mhm-rentiva-listing-fee';

    /** @var string Order item meta key for the associated vehicle ID. */
    public const META_VEHICLE_ID = '_mhm_listing_vehicle_id';

    /** @var string Order item meta key for the listing action type. */
    public const META_ACTION = '_mhm_listing_action';

    /** @var string[] Allowed fee model values. */
    private const ALLOWED_FEE_MODELS = array('one_time', 'per_listing', 'per_renewal');

    /** @var string[] Actions that may require payment. */
    private const PAYABLE_ACTIONS = array('new', 'renew', 'relist');

    // ── Task 2: Core Settings ────────────────────────────────

    /**
     * Check whether listing fees are enabled and configured.
     *
     * Returns true only when the admin toggle is on AND a positive fee amount is set.
     */
    public static function is_enabled(): bool
    {
        $enabled = SettingsCore::get('mhm_rentiva_listing_fee_enabled', false);
        $amount  = self::get_fee_amount();

        return (bool) $enabled && $amount > 0;
    }

    /**
     * Get the configured listing fee amount.
     *
     * @return float Fee amount (0.0 when not configured).
     */
    public static function get_fee_amount(): float
    {
        return (float) SettingsCore::get('mhm_rentiva_listing_fee_amount', 0);
    }

    /**
     * Get the configured fee model.
     *
     * @return string One of 'one_time', 'per_listing', 'per_renewal'.
     */
    public static function get_fee_model(): string
    {
        $model = (string) SettingsCore::get('mhm_rentiva_listing_fee_model', 'one_time');

        if (! in_array($model, self::ALLOWED_FEE_MODELS, true)) {
            return 'one_time';
        }

        return $model;
    }

    /**
     * Determine whether a given action requires payment.
     *
     * @param string $action One of 'new', 'renew', 'relist'.
     * @return bool True when fees are enabled and the action is payable.
     */
    public static function requires_payment(string $action): bool
    {
        if (! self::is_enabled()) {
            return false;
        }

        return in_array($action, self::PAYABLE_ACTIONS, true);
    }

    // ── Task 3: WC Product & Cart ────────────────────────────

    /**
     * Get the listing fee WC product ID, creating one if it does not exist.
     *
     * The product is hidden, virtual, sold individually, with price 0
     * (actual price is set dynamically in the cart).
     *
     * @return int Product ID (0 on failure / WC not available).
     */
    public static function get_or_create_product(): int
    {
        $product_id = 0;
        if (function_exists('wc_get_product_id_by_sku')) {
            $product_id = (int) call_user_func('wc_get_product_id_by_sku', self::PRODUCT_SKU);
        }

        if ($product_id) {
            return $product_id;
        }

        $product_class = '\WC_Product_Simple';
        if (class_exists($product_class)) {
            /** @var mixed $product */
            $product = new $product_class();
            $product->set_name(__('Vehicle Listing Fee', 'mhm-rentiva'));
            $product->set_sku(self::PRODUCT_SKU);
            $product->set_price(0);
            $product->set_regular_price(0);
            $product->set_virtual(true);
            $product->set_sold_individually(true);
            $product->set_status('publish');
            $product->set_catalog_visibility('hidden');
            $product->save();

            return $product->get_id();
        }

        return 0;
    }

    /**
     * Add the listing fee to the WooCommerce cart and return the checkout URL.
     *
     * Empties the existing cart first to ensure a clean checkout experience.
     *
     * @param int    $vehicle_id Vehicle post ID.
     * @param string $action     Listing action ('new', 'renew', 'relist').
     * @return string Checkout page URL (empty string on failure).
     */
    public static function add_to_cart(int $vehicle_id, string $action): string
    {
        if (! function_exists('WC') || ! WC()->cart) {
            return '';
        }

        $product_id = self::get_or_create_product();
        if (! $product_id) {
            return '';
        }

        WC()->cart->empty_cart();

        $cart_item_data = array(
            'mhm_listing_vehicle_id' => $vehicle_id,
            'mhm_listing_action'     => $action,
            'mhm_listing_amount'     => self::get_fee_amount(),
        );

        WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        return wc_get_checkout_url();
    }

    // ── Task 4: WC Hooks ─────────────────────────────────────

    /**
     * Register WooCommerce hooks for listing fee processing.
     *
     * Safe to call unconditionally — hooks are only added when WooCommerce is active.
     */
    public static function register(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_before_calculate_totals', array(self::class, 'set_cart_item_price'), 20, 1);
        add_filter('woocommerce_get_item_data', array(self::class, 'display_cart_item_meta'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array(self::class, 'save_order_item_meta'), 10, 4);
        add_action('woocommerce_order_status_completed', array(self::class, 'on_order_completed'), 10, 1);
    }

    /**
     * Set dynamic price on listing fee cart items.
     *
     * @param mixed $cart WC_Cart instance.
     */
    public static function set_cart_item_price($cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (! isset($cart_item['mhm_listing_amount'])) {
                continue;
            }

            $product = $cart_item['data'] ?? null;
            if ($product && is_callable(array($product, 'set_price'))) {
                $product->set_price((float) $cart_item['mhm_listing_amount']);
            }
        }
    }

    /**
     * Display vehicle name and action type in the cart item data.
     *
     * @param array<int, array<string, string>> $item_data Existing item data.
     * @param array<string, mixed>              $cart_item Cart item.
     * @return array<int, array<string, string>> Modified item data.
     */
    public static function display_cart_item_meta(array $item_data, array $cart_item): array
    {
        if (! isset($cart_item['mhm_listing_vehicle_id'])) {
            return $item_data;
        }

        $vehicle_id = (int) $cart_item['mhm_listing_vehicle_id'];
        $action     = $cart_item['mhm_listing_action'] ?? '';

        $vehicle_title = get_the_title($vehicle_id);
        if ($vehicle_title) {
            $item_data[] = array(
                'name'  => __('Vehicle', 'mhm-rentiva'),
                'value' => $vehicle_title,
            );
        }

        $action_labels = array(
            'new'    => __('New Listing', 'mhm-rentiva'),
            'renew'  => __('Renewal', 'mhm-rentiva'),
            'relist' => __('Relist', 'mhm-rentiva'),
        );

        if (isset($action_labels[$action])) {
            $item_data[] = array(
                'name'  => __('Action', 'mhm-rentiva'),
                'value' => $action_labels[$action],
            );
        }

        return $item_data;
    }

    /**
     * Save vehicle ID and action to WC order line item meta.
     *
     * @param mixed  $item          WC_Order_Item_Product instance.
     * @param string $cart_item_key Cart item key.
     * @param array<string, mixed> $values Cart item values.
     * @param mixed  $order         WC_Order instance.
     */
    public static function save_order_item_meta($item, $cart_item_key, $values, $order): void
    {
        if (isset($values['mhm_listing_vehicle_id'])) {
            $item->add_meta_data(self::META_VEHICLE_ID, (int) $values['mhm_listing_vehicle_id']);
            $item->add_meta_data(self::META_ACTION, sanitize_text_field($values['mhm_listing_action'] ?? ''));
        }
    }

    /**
     * Handle WooCommerce order completion — process listing fee items.
     *
     * @param int $order_id WC Order ID.
     */
    public static function on_order_completed(int $order_id): void
    {
        $order_class = '\WC_Order';
        if (! class_exists($order_class)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (! $product) {
                continue;
            }

            $sku = $product->get_sku();
            if ($sku !== self::PRODUCT_SKU) {
                continue;
            }

            $vehicle_id = (int) $item->get_meta(self::META_VEHICLE_ID);
            $action     = (string) $item->get_meta(self::META_ACTION);

            if ($vehicle_id && $action) {
                self::process_completed_order($vehicle_id, $action);
            }
        }
    }

    /**
     * Process a completed listing fee payment for a specific vehicle.
     *
     * For 'new' and 'relist': transitions the vehicle to pending + pending_review
     * so it enters the admin approval queue.
     *
     * For 'renew': delegates to VehicleLifecycleManager::renew() which handles
     * the full renewal flow (grace period check, timer reset, etc.).
     *
     * @param int    $vehicle_id Vehicle post ID.
     * @param string $action     Listing action ('new', 'renew', 'relist').
     */
    public static function process_completed_order(int $vehicle_id, string $action): void
    {
        if ($action === 'renew') {
            $vendor_id = (int) get_post_field('post_author', $vehicle_id);
            if ($vendor_id) {
                VehicleLifecycleManager::renew($vehicle_id, $vendor_id);
            }
            return;
        }

        // For 'new' and 'relist': set to pending review for admin approval.
        if (in_array($action, array('new', 'relist'), true)) {
            wp_update_post(array(
                'ID'          => $vehicle_id,
                'post_status' => 'pending',
            ));

            update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PENDING_REVIEW);
            update_post_meta($vehicle_id, '_vehicle_review_status', 'pending_review');

            /**
             * Fires after a listing fee payment completes and the vehicle is set to pending review.
             *
             * @since 4.25.0
             *
             * @param int    $vehicle_id Vehicle post ID.
             * @param string $action     'new' or 'relist'.
             */
            do_action('mhm_rentiva_listing_fee_completed', $vehicle_id, $action);
        }
    }
}
