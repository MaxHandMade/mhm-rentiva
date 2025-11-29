<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Gateway Interface
 * 
 * Defines the contract for payment gateway implementations.
 * This allows the plugin to work with different payment systems
 * (WooCommerce, custom gateways, etc.) without tight coupling.
 * 
 * @since 4.0.0
 */
interface PaymentGatewayInterface
{
    /**
     * Check if this payment gateway is available/active
     * 
     * @return bool True if gateway is available
     */
    public function is_available(): bool;

    /**
     * Get gateway name/identifier
     * 
     * @return string Gateway identifier (e.g., 'woocommerce', 'stripe', 'paypal')
     */
    public function get_gateway_name(): string;

    /**
     * Get gateway display name
     * 
     * @return string Human-readable gateway name
     */
    public function get_display_name(): string;

    /**
     * Add booking data to payment system (cart, session, etc.)
     * 
     * @param array<string, mixed> $booking_data Booking data array
     * @param float $amount Amount to charge
     * @return bool True on success, false on failure
     */
    public function add_booking_to_payment(array $booking_data, float $amount): bool;

    /**
     * Get payment/checkout URL
     * 
     * @return string URL to redirect user for payment
     */
    public function get_checkout_url(): string;

    /**
     * Process payment for a booking
     * 
     * @param int $booking_id Booking ID
     * @param float $amount Payment amount
     * @param array<string, mixed> $payment_data Additional payment data
     * @return array<string, mixed> Payment result with 'success', 'message', 'transaction_id', etc.
     */
    public function process_payment(int $booking_id, float $amount, array $payment_data = []): array;

    /**
     * Validate payment data before processing
     * 
     * @param array<string, mixed> $payment_data Payment data to validate
     * @return array<string, mixed> Validation result with 'valid' (bool) and 'errors' (array)
     */
    public function validate_payment_data(array $payment_data): array;
}

