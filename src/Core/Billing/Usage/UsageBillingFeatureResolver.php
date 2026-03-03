<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

class UsageBillingFeatureResolver
{
    public function resolve(bool $flag_from_repository): bool
    {
        return $flag_from_repository;
    }
}
