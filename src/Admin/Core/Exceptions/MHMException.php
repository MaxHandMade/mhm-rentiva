<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ BASE EXCEPTION - MHM Rentiva Exception Hierarchy
 * 
 * Tüm MHM Rentiva exception'larının base sınıfı
 */
abstract class MHMException extends \Exception
{
    /**
     * Exception kategorisi
     */
    protected string $category;

    /**
     * Ek context bilgisi
     */
    protected array $context;

    /**
     * Constructor
     * 
     * @param string $message Exception mesajı
     * @param int $code Exception kodu
     * @param \Throwable|null $previous Önceki exception
     * @param string $category Exception kategorisi
     * @param array $context Ek context bilgisi
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        string $category = 'general',
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->category = $category;
        $this->context = $context;
    }

    /**
     * Exception kategorisini al
     * 
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Context bilgisini al
     * 
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Context bilgisini set et
     * 
     * @param array $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Exception'ı logla
     */
    public function log(): void
    {
        if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error($this->getMessage(), [
                'exception' => static::class,
                'code' => $this->getCode(),
                'category' => $this->getCategory(),
                'context' => $this->getContext(),
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => $this->getTraceAsString()
            ], \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM);
        }
    }
}
