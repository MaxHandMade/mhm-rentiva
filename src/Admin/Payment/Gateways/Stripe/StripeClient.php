<?php declare(strict_types=1);

namespace MHMRentiva\Payments;

use MHMRentiva\Interfaces\PaymentGatewayInterface;
use MHMRentiva\Interfaces\PaymentResult;
use MHMRentiva\Interfaces\RefundResult;
use MHMRentiva\Interfaces\PaymentStatus;
use MHMRentiva\Exceptions\PaymentException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ STRIPE CLIENT - PaymentGatewayInterface implementation.
 *
 * Provides a mocked Stripe payment gateway implementation.
 */
final class StripeClient implements PaymentGatewayInterface
{
    /**
     * Stripe configuration
     */
    private string $api_key;
    private string $webhook_secret;
    private bool $is_test_mode;

    /**
     * Constructor
     * 
     * @param string $api_key Stripe API key
     * @param string $webhook_secret Webhook secret
     * @param bool $is_test_mode Test mode
     */
    public function __construct(string $api_key, string $webhook_secret = '', bool $is_test_mode = false)
    {
        $this->api_key = $api_key;
        $this->webhook_secret = $webhook_secret;
        $this->is_test_mode = $is_test_mode;
    }

    /**
     * Process payment request.
     *
     * @param array $payment_data Payment data
     * @return PaymentResult
     * @throws PaymentException
     */
    public function processPayment(array $payment_data): PaymentResult
    {
        try {
            // Stripe API call simulation
            $amount = $payment_data['amount'] ?? 0;
            $currency = $payment_data['currency'] ?? 'USD';
            $payment_method = $payment_data['payment_method'] ?? 'card';
            
            // Validation
            if ($amount <= 0) {
                throw PaymentException::invalidAmount($amount);
            }

            if (!in_array($currency, $this->getSupportedCurrencies())) {
                throw PaymentException::invalidAmount($amount, ['currency' => $currency]);
            }

            // Simulate Stripe payment processing
            $transaction_id = 'pi_' . uniqid();
            $success = $this->simulatePaymentProcessing($payment_data);

            if (!$success) {
                throw PaymentException::cardDeclined('Insufficient funds or card declined');
            }

            return new PaymentResult(
                success: true,
                transaction_id: $transaction_id,
                status: 'succeeded',
                metadata: [
                    'gateway' => 'stripe',
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $payment_method
                ]
            );

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw PaymentException::gatewayError('stripe', $e->getMessage());
        }
    }

    /**
     * Process refund request.
     *
     * @param string $transaction_id
     * @param float|null $amount Optional partial amount (null for full)
     * @return RefundResult
     * @throws PaymentException
     */
    public function refund(string $transaction_id, ?float $amount = null): RefundResult
    {
        try {
            // Simulate Stripe refund processing
            $refund_id = 're_' . uniqid();
            $refund_amount = $amount ?? 100.0; // Default amount for simulation
            
            // Simulate refund processing
            $success = $this->simulateRefundProcessing($transaction_id, $refund_amount);

            if (!$success) {
                throw PaymentException::gatewayError('stripe', 'Refund failed');
            }

            return new RefundResult(
                success: true,
                refund_id: $refund_id,
                amount: $refund_amount,
                status: 'succeeded',
                metadata: [
                    'gateway' => 'stripe',
                    'original_transaction_id' => $transaction_id
                ]
            );

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw PaymentException::gatewayError('stripe', $e->getMessage());
        }
    }

    /**
     * Retrieve payment status.
     *
     * @param string $transaction_id
     * @return PaymentStatus
     * @throws PaymentException
     */
    public function getPaymentStatus(string $transaction_id): PaymentStatus
    {
        try {
            // Simulate Stripe payment status retrieval
            return new PaymentStatus(
                transaction_id: $transaction_id,
                status: 'succeeded',
                amount: 100.0,
                currency: 'USD',
                customer_id: 'cus_' . uniqid(),
                payment_method: 'card',
                metadata: ['gateway' => 'stripe']
            );

        } catch (\Exception $e) {
            throw PaymentException::gatewayError('stripe', $e->getMessage());
        }
    }

    /**
     * Verify webhook signature.
     *
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhook(array $payload, string $signature): bool
    {
        // Simulate webhook verification
        return !empty($signature) && $signature !== 'invalid';
    }

    /**
     * Determine if gateway is active.
     */
    public function isActive(): bool
    {
        return !empty($this->api_key);
    }

    /**
     * Supported currencies.
     *
     * @return array
     */
    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'TRY'];
    }

    /**
     * Supported payment methods.
     *
     * @return array
     */
    public function getSupportedPaymentMethods(): array
    {
        return ['card', 'bank_transfer', 'wallet'];
    }

    /**
     * Validate gateway configuration.
     *
     * @return bool
     * @throws PaymentException
     */
    public function validateConfiguration(): bool
    {
        if (empty($this->api_key)) {
            throw PaymentException::configurationError('api_key');
        }

        if ($this->is_test_mode && !str_starts_with($this->api_key, 'sk_test_')) {
            throw PaymentException::configurationError('test_mode_api_key');
        }

        if (!$this->is_test_mode && !str_starts_with($this->api_key, 'sk_live_')) {
            throw PaymentException::configurationError('live_mode_api_key');
        }

        return true;
    }

    /**
     * Get gateway label.
     */
    public function getGatewayName(): string
    {
        return 'Stripe';
    }

    /**
     * Get gateway version.
     */
    public function getGatewayVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Simulate payment processing
     * 
     * @param array $payment_data Payment data
     * @return bool Success status
     */
    private function simulatePaymentProcessing(array $payment_data): bool
    {
        // Simulate payment processing logic
        $amount = $payment_data['amount'] ?? 0;
        
        // Simulate failure for amounts > 1000
        if ($amount > 1000) {
            return false;
        }

        // Simulate network delay
        usleep(100000); // 0.1 second
        
        return true;
    }

    /**
     * Simulate refund processing
     * 
     * @param string $transaction_id Transaction ID
     * @param float $amount Refund amount
     * @return bool Success status
     */
    private function simulateRefundProcessing(string $transaction_id, float $amount): bool
    {
        // Simulate refund processing logic
        if (empty($transaction_id)) {
            return false;
        }

        // Simulate network delay
        usleep(50000); // 0.05 second
        
        return true;
    }
}
