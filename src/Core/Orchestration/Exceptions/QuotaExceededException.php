<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Orchestration\Exceptions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thrown when a tenant exceeds their allocated resource quota.
 *
 * Automatically triggers an 'ACTION_QUOTA_BLOCKED' audit event for forensics.
 *
 * @since 4.23.0
 */
class QuotaExceededException extends \RuntimeException {

    private int $tenant_id;
    private string $metric_type;

    public function __construct(int $tenant_id, string $metric_type, string $message = '')
    {
        $this->tenant_id   = $tenant_id;
        $this->metric_type = $metric_type;

        if (empty($message)) {
            $message = 'Resource quota exceeded for the requested operation.';
        }

        parent::__construct($message);

        // Audit the event
        $this->audit_block();
    }

    private function audit_block(): void
    {
        /**
         * @var \MHMRentiva\Core\Financial\Audit\AuditManager $audit_manager
         */
        if (class_exists('\\MHMRentiva\\Admin\\PostTypes\\Logs\\AdvancedLogger')) {
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning(
                'Quota block triggered.',
                [
                    'tenant_id'   => $this->tenant_id,
                    'metric_type' => $this->metric_type,
                    'event'       => 'ACTION_QUOTA_BLOCKED',
                ],
                'saas_control_plane'
            );
        }
    }

    public function get_tenant_id(): int
    {
        return $this->tenant_id;
    }

    public function get_metric_type(): string
    {
        return $this->metric_type;
    }
}
