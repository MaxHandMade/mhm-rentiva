<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Gateway Interface - Interface for polymorphism
 * 
 * Interface that all payment gateways must implement
 */
interface PaymentGatewayInterface
{
    /**
     * Process payment
     * 
     * @param array $payment_data Payment data
     * @return PaymentResult Payment result
     * @throws \MHMRentiva\Exceptions\PaymentException Payment error
     */
    public function processPayment(array $payment_data): PaymentResult;

    /**
     * Process refund
     * 
     * @param string $transaction_id Transaction ID
     * @param float $amount Refund amount (optional, null for full amount)
     * @return RefundResult Refund result
     * @throws \MHMRentiva\Exceptions\PaymentException Payment error
     */
    public function refund(string $transaction_id, ?float $amount = null): RefundResult;

    /**
     * Query payment status
     * 
     * @param string $transaction_id Transaction ID
     * @return PaymentStatus Payment status
     * @throws \MHMRentiva\Exceptions\PaymentException Payment error
     */
    public function getPaymentStatus(string $transaction_id): PaymentStatus;

    /**
     * Verify webhook
     * 
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool Verification status
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Check if gateway is active
     * 
     * @return bool Active status
     */
    public function isActive(): bool;

    /**
     * Get supported currencies for gateway
     * 
     * @return array Supported currencies
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get supported payment methods for gateway
     * 
     * @return array Supported payment methods
     */
    public function getSupportedPaymentMethods(): array;

    /**
     * Validate gateway settings
     * 
     * @return bool Validation status
     * @throws \MHMRentiva\Exceptions\PaymentException Configuration error
     */
    public function validateConfiguration(): bool;

    /**
     * Get gateway name
     * 
     * @return string Gateway name
     */
    public function getGatewayName(): string;

    /**
     * Get gateway version
     * 
     * @return string Gateway version
     */
    public function getGatewayVersion(): string;
}

/**
 * Payment Result DTO
 */
class PaymentResult
{
    public bool $success;
    public string $transaction_id;
    public string $status;
    public ?string $error_message;
    public ?array $metadata;
    public ?string $redirect_url;
    public ?string $payment_intent_id;

    public function __construct(
        bool $success,
        string $transaction_id = '',
        string $status = '',
        ?string $error_message = null,
        ?array $metadata = null,
        ?string $redirect_url = null,
        ?string $payment_intent_id = null
    ) {
        $this->success = $success;
        $this->transaction_id = $transaction_id;
        $this->status = $status;
        $this->error_message = $error_message;
        $this->metadata = $metadata;
        $this->redirect_url = $redirect_url;
        $this->payment_intent_id = $payment_intent_id;
    }
}

/**
 * Refund Result DTO
 */
class RefundResult
{
    public bool $success;
    public string $refund_id;
    public float $amount;
    public string $status;
    public ?string $error_message;
    public ?array $metadata;

    public function __construct(
        bool $success,
        string $refund_id = '',
        float $amount = 0.0,
        string $status = '',
        ?string $error_message = null,
        ?array $metadata = null
    ) {
        $this->success = $success;
        $this->refund_id = $refund_id;
        $this->amount = $amount;
        $this->status = $status;
        $this->error_message = $error_message;
        $this->metadata = $metadata;
    }
}

/**
 * Payment Status DTO
 */
class PaymentStatus
{
    public string $transaction_id;
    public string $status;
    public float $amount;
    public string $currency;
    public ?string $customer_id;
    public ?string $payment_method;
    public ?array $metadata;
    public \DateTime $created_at;
    public ?\DateTime $updated_at;

    public function __construct(
        string $transaction_id,
        string $status,
        float $amount,
        string $currency,
        ?string $customer_id = null,
        ?string $payment_method = null,
        ?array $metadata = null,
        ?\DateTime $created_at = null,
        ?\DateTime $updated_at = null
    ) {
        $this->transaction_id = $transaction_id;
        $this->status = $status;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->customer_id = $customer_id;
        $this->payment_method = $payment_method;
        $this->metadata = $metadata;
        $this->created_at = $created_at ?? new \DateTime();
        $this->updated_at = $updated_at;
    }
}
