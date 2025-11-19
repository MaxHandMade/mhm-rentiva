<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Core;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Exceptions\MHMException;

/**
 * Payment Exception - Custom exception for payment operations
 */
final class PaymentException extends MHMException
{
    /**
     * Payment exception codes
     */
    public const CODE_GATEWAY_ERROR = 500;
    public const CODE_INVALID_AMOUNT = 400;
    public const CODE_CARD_DECLINED = 402;
    public const CODE_INSUFFICIENT_FUNDS = 402;
    public const CODE_EXPIRED_CARD = 402;
    public const CODE_INVALID_CARD = 400;
    public const CODE_NETWORK_ERROR = 503;
    public const CODE_TIMEOUT = 408;
    public const CODE_CONFIGURATION_ERROR = 500;

    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     * @param array $context Additional context information
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, 'payment', $context);
    }

    /**
     * Gateway error exception
     * 
     * @param string $gateway Gateway name
     * @param string $reason Error reason
     * @param array $context Additional context
     * @return static
     */
    public static function gatewayError(string $gateway, string $reason, array $context = []): self
    {
        return new self(
            /* translators: 1: Payment gateway name, 2: Error reason */
            sprintf(__('Payment gateway "%1$s" error: %2$s', 'mhm-rentiva'), $gateway, $reason),
            self::CODE_GATEWAY_ERROR,
            null,
            array_merge($context, [
                'gateway' => $gateway,
                'reason' => $reason
            ])
        );
    }

    /**
     * Invalid amount exception
     * 
     * @param float $amount Amount
     * @param array $context Additional context
     * @return static
     */
    public static function invalidAmount(float $amount, array $context = []): self
    {
        return new self(
            /* translators: %s placeholder. */
            sprintf(__('Invalid amount: %s', 'mhm-rentiva'), number_format($amount, 2)),
            self::CODE_INVALID_AMOUNT,
            null,
            array_merge($context, ['amount' => $amount])
        );
    }

    /**
     * Card declined exception
     * 
     * @param string $reason Decline reason
     * @param array $context Additional context
     * @return static
     */
    public static function cardDeclined(string $reason, array $context = []): self
    {
        return new self(
            /* translators: %s placeholder. */
            sprintf(__('Card declined: %s', 'mhm-rentiva'), $reason),
            self::CODE_CARD_DECLINED,
            null,
            array_merge($context, ['decline_reason' => $reason])
        );
    }

    /**
     * Insufficient funds exception
     * 
     * @param float $required_amount Required amount
     * @param float $available_amount Available amount
     * @param array $context Additional context
     * @return static
     */
    public static function insufficientFunds(float $required_amount, float $available_amount, array $context = []): self
    {
        return new self(
            /* translators: 1: Required amount, 2: Available amount */
            sprintf(
                /* translators: 1: %1$s; 2: %2$s. */
                __('Insufficient funds. Required: %1$s, Available: %2$s', 'mhm-rentiva'),
                number_format($required_amount, 2),
                number_format($available_amount, 2)
            ),
            self::CODE_INSUFFICIENT_FUNDS,
            null,
            array_merge($context, [
                'required_amount' => $required_amount,
                'available_amount' => $available_amount
            ])
        );
    }

    /**
     * Expired card exception
     * 
     * @param string $expiry_date Expiry date
     * @param array $context Additional context
     * @return static
     */
    public static function expiredCard(string $expiry_date, array $context = []): self
    {
        return new self(
            /* translators: %s placeholder. */
            sprintf(__('Card expired: %s', 'mhm-rentiva'), $expiry_date),
            self::CODE_EXPIRED_CARD,
            null,
            array_merge($context, ['expiry_date' => $expiry_date])
        );
    }

    /**
     * Invalid card exception
     * 
     * @param string $reason Error reason
     * @param array $context Additional context
     * @return static
     */
    public static function invalidCard(string $reason, array $context = []): self
    {
        return new self(
            /* translators: %s placeholder. */
            sprintf(__('Invalid card: %s', 'mhm-rentiva'), $reason),
            self::CODE_INVALID_CARD,
            null,
            array_merge($context, ['reason' => $reason])
        );
    }

    /**
     * Network error exception
     * 
     * @param string $reason Network error reason
     * @param array $context Additional context
     * @return static
     */
    public static function networkError(string $reason, array $context = []): self
    {
        return new self(
            /* translators: %s placeholder. */
            sprintf(__('Network error: %s', 'mhm-rentiva'), $reason),
            self::CODE_NETWORK_ERROR,
            null,
            array_merge($context, ['reason' => $reason])
        );
    }

    /**
     * Timeout exception
     * 
     * @param int $timeout_seconds Timeout duration in seconds
     * @param array $context Additional context
     * @return static
     */
    public static function timeout(int $timeout_seconds, array $context = []): self
    {
        return new self(
            /* translators: %d placeholder. */
            sprintf(__('Payment timeout: %d seconds', 'mhm-rentiva'), $timeout_seconds),
            self::CODE_TIMEOUT,
            null,
            array_merge($context, ['timeout_seconds' => $timeout_seconds])
        );
    }

    /**
     * Configuration error exception
     * 
     * @param string $config_key Configuration key
     * @param array $context Additional context
     * @return static
     */
    public static function configurationError(string $config_key, array $context = []): self
    {
        return new self(
            /* translators: %s placeholder. */
            sprintf(__('Payment configuration error: %s', 'mhm-rentiva'), $config_key),
            self::CODE_CONFIGURATION_ERROR,
            null,
            array_merge($context, ['config_key' => $config_key])
        );
    }
}
