<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Gateway Interface
 * 
 * Defines the contract for payment gateway implementations.
 * All payment gateways must implement this interface.
 * 
 * @since 3.0.0
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
     * @return string Gateway identifier
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
     * @return string|null Checkout URL or null if not available
     */
    public function get_payment_url(): ?string;
}

