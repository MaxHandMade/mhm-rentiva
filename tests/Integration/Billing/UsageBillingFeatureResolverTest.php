<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Billing;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingFeatureResolverTest extends \WP_UnitTestCase
{
    public function test_resolver_returns_false_when_disabled(): void
    {
        $this->require_class('\\MHMRentiva\\Core\\Billing\\Usage\\UsageBillingFeatureResolver');

        $resolver = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureResolver();

        $this->assertFalse($resolver->resolve(false));
    }

    public function test_resolver_returns_true_when_enabled(): void
    {
        $this->require_class('\\MHMRentiva\\Core\\Billing\\Usage\\UsageBillingFeatureResolver');

        $resolver = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureResolver();

        $this->assertTrue($resolver->resolve(true));
    }

    public function test_resolver_output_is_strict_boolean(): void
    {
        $this->require_class('\\MHMRentiva\\Core\\Billing\\Usage\\UsageBillingFeatureResolver');

        $resolver = new \MHMRentiva\Core\Billing\Usage\UsageBillingFeatureResolver();

        $resolved = $resolver->resolve(true);

        $this->assertIsBool($resolved);
        $this->assertTrue($resolved);
    }

    private function require_class(string $fqcn): void
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn)) {
            $this->fail('Missing class contract for RED phase: ' . $fqcn);
        }
    }
}
