<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Exceptions;

use MHMRentiva\Exceptions\MHMException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ BOOKING EXCEPTION - Custom exception for booking operations
 */
final class BookingException extends MHMException
{
    /**
     * Booking exception codes
     */
    public const CODE_NOT_FOUND = 404;
    public const CODE_INVALID_STATUS = 400;
    public const CODE_CONFLICT = 409;
    public const CODE_UNAUTHORIZED = 401;
    public const CODE_VALIDATION_FAILED = 422;
    public const CODE_PAYMENT_FAILED = 402;

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
        parent::__construct($message, $code, $previous, 'booking', $context);
    }

    /**
     * Booking not found exception
     * 
     * @param int $booking_id Booking ID
     * @param array $context Additional context
     * @return static
     */
    public static function notFound(int $booking_id, array $context = []): self
    {
        return new self(
            sprintf(__('Booking #%d not found.', 'mhm-rentiva'), $booking_id),
            self::CODE_NOT_FOUND,
            null,
            array_merge($context, ['booking_id' => $booking_id])
        );
    }

    /**
     * Invalid status exception
     * 
     * @param string $current_status Current status
     * @param string $target_status Target status
     * @param array $context Additional context
     * @return static
     */
    public static function invalidStatus(string $current_status, string $target_status, array $context = []): self
    {
        return new self(
            /* translators: 1: Current status, 2: Target status */
            sprintf(__('Invalid status transition from "%1$s" to "%2$s".', 'mhm-rentiva'), $current_status, $target_status),
            self::CODE_INVALID_STATUS,
            null,
            array_merge($context, [
                'current_status' => $current_status,
                'target_status' => $target_status
            ])
        );
    }

    /**
     * Conflict exception (e.g. date conflict)
     * 
     * @param string $reason Reason for conflict
     * @param array $context Additional context
     * @return static
     */
    public static function conflict(string $reason, array $context = []): self
    {
        return new self(
            sprintf(__('Booking conflict: %s', 'mhm-rentiva'), $reason),
            self::CODE_CONFLICT,
            null,
            array_merge($context, ['reason' => $reason])
        );
    }

    /**
     * Authorization error exception
     * 
     * @param int $user_id User ID
     * @param array $context Additional context
     * @return static
     */
    public static function unauthorized(int $user_id, array $context = []): self
    {
        return new self(
            sprintf(__('User #%d is unauthorized for this booking.', 'mhm-rentiva'), $user_id),
            self::CODE_UNAUTHORIZED,
            null,
            array_merge($context, ['user_id' => $user_id])
        );
    }

    /**
     * Validation error exception
     * 
     * @param array $errors Validation errors
     * @param array $context Additional context
     * @return static
     */
    public static function validationFailed(array $errors, array $context = []): self
    {
        return new self(
            sprintf(__('Validation failed: %s', 'mhm-rentiva'), implode(', ', $errors)),
            self::CODE_VALIDATION_FAILED,
            null,
            array_merge($context, ['validation_errors' => $errors])
        );
    }

    /**
     * Payment error exception
     * 
     * @param string $reason Reason for payment error
     * @param array $context Additional context
     * @return static
     */
    public static function paymentFailed(string $reason, array $context = []): self
    {
        return new self(
            sprintf(__('Payment failed: %s', 'mhm-rentiva'), $reason),
            self::CODE_PAYMENT_FAILED,
            null,
            array_merge($context, ['payment_reason' => $reason])
        );
    }
}
