<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Billing;

use MHMRentiva\Core\Billing\Usage\BillingComputationResult;
use MHMRentiva\Core\Billing\Usage\UsagePricingEngine;
use MHMRentiva\Core\Billing\Usage\UsageSnapshotDTO;

if (! defined('ABSPATH')) {
    exit;
}

final class UsagePricingEngineDeterminismTest extends \WP_UnitTestCase
{
    public function test_metric_order_independent(): void
    {
        $engine = $this->create_engine();

        $snapshot_a = $this->create_snapshot(array(
            $this->metric('api_calls', 5, 100),
            $this->metric('sms_sent', 3, 60),
            $this->metric('storage_gb', 2, 20),
        ));

        $snapshot_b = $this->create_snapshot(array(
            $this->metric('storage_gb', 2, 20),
            $this->metric('api_calls', 5, 100),
            $this->metric('sms_sent', 3, 60),
        ));

        $result_a = $engine->compute($snapshot_a, '2026-03-01T12:00:00Z');
        $result_b = $engine->compute($snapshot_b, '2026-03-01T12:00:00Z');

        $this->assertInstanceOf(BillingComputationResult::class, $result_a);
        $this->assertInstanceOf(BillingComputationResult::class, $result_b);
        $this->assertSame(720, $result_a->amount_cents());
        $this->assertSame(720, $result_b->amount_cents());
        $this->assertSame($result_a->hash(), $result_b->hash());
    }

    public function test_snapshot_immutability(): void
    {
        $metrics = array(
            $this->metric('api_calls', 2, 100),
            $this->metric('sms_sent', 1, 50),
        );

        $snapshot = $this->create_snapshot($metrics);
        $metrics[0]['usage_units'] = 999;

        $internal_metrics = $snapshot->metrics();

        $this->assertArrayHasKey('api_calls', $internal_metrics);
        $this->assertArrayHasKey('sms_sent', $internal_metrics);
        $this->assertSame(2, (int) $internal_metrics['api_calls']['usage_units']);
        $this->assertSame(50, (int) $internal_metrics['sms_sent']['unit_price_cents']);
    }

    public function test_hash_canonical_stability(): void
    {
        $engine = $this->create_engine();

        $snapshot_a = $this->create_snapshot(array(
            array(
                'metric_key' => 'api_calls',
                'usage_units' => 4,
                'unit_price_cents' => 110,
            ),
        ));

        $snapshot_b = $this->create_snapshot(array(
            array(
                'unit_price_cents' => 110,
                'metric_key' => 'api_calls',
                'usage_units' => 4,
            ),
        ));

        $result_a = $engine->compute($snapshot_a, '2026-03-01T12:00:00Z');
        $result_b = $engine->compute($snapshot_b, '2026-03-01T12:00:00Z');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result_a->hash());
        $this->assertSame($result_a->hash(), $result_b->hash());
    }

    public function test_negative_usage_rejected(): void
    {
        $engine = $this->create_engine();

        $this->expectException(\InvalidArgumentException::class);

        $snapshot = $this->create_snapshot(array(
            $this->metric('api_calls', -1, 100),
        ));

        $engine->compute($snapshot, '2026-03-01T12:00:00Z');
    }

    public function test_large_integer_guard(): void
    {
        $engine = $this->create_engine();

        $this->expectException(\OverflowException::class);

        $snapshot = $this->create_snapshot(array(
            $this->metric('api_calls', PHP_INT_MAX, 2),
        ));

        $engine->compute($snapshot, '2026-03-01T12:00:00Z');
    }

    public function test_now_must_be_injected_not_runtime(): void
    {
        $this->require_class(UsagePricingEngine::class);

        $method = new \ReflectionMethod(UsagePricingEngine::class, 'compute');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('now_utc', $parameters[1]->getName());
        $this->assertFalse($parameters[1]->isOptional());

        $reflection = new \ReflectionClass(UsagePricingEngine::class);
        $file = file_get_contents((string) $reflection->getFileName());
        $this->assertIsString($file);
        $this->assertSame(0, preg_match('/\btime\s*\(/', $file));
        $this->assertSame(0, preg_match('/\bgmdate\s*\(/', $file));
        $this->assertSame(0, preg_match('/\bnew\s+\\\\?DateTime\b/', $file));
        $this->assertSame(0, preg_match('/\bnew\s+\\\\?DateTimeImmutable\b/', $file));
    }

    public function test_open_window_rejected(): void
    {
        $engine = $this->create_engine();
        $this->expectException(\DomainException::class);

        $snapshot = $this->create_snapshot(
            array($this->metric('api_calls', 1, 10)),
            '2026-03-01T00:00:00Z',
            '2026-03-01T23:00:00Z',
            '2026-03-01T23:00:00Z'
        );

        $engine->compute($snapshot, '2026-03-01T22:59:59Z');
    }

    public function test_recomputed_before_period_end_rejected(): void
    {
        $engine = $this->create_engine();
        $this->expectException(\DomainException::class);

        $snapshot = $this->create_snapshot(
            array($this->metric('api_calls', 1, 10)),
            '2026-03-01T00:00:00Z',
            '2026-03-01T01:00:00Z',
            '2026-03-01T00:59:59Z'
        );

        $engine->compute($snapshot, '2026-03-01T12:00:00Z');
    }

    /**
     * @param array<int,array<string,int|string>> $metrics
     */
    private function create_snapshot(
        array $metrics,
        string $period_start_utc = '2026-03-01T00:00:00Z',
        string $period_end_utc = '2026-03-01T01:00:00Z',
        string $recomputed_at_utc = '2026-03-01T01:00:00Z'
    ): UsageSnapshotDTO
    {
        $this->require_class(UsageSnapshotDTO::class);

        return new UsageSnapshotDTO(
            1,
            101,
            $period_start_utc,
            $period_end_utc,
            $recomputed_at_utc,
            $metrics
        );
    }

    private function create_engine(): UsagePricingEngine
    {
        $this->require_class(UsagePricingEngine::class);
        return new UsagePricingEngine();
    }

    /**
     * @return array<string,int|string>
     */
    private function metric(string $key, int $usage_units, int $unit_price_cents): array
    {
        return array(
            'metric_key' => $key,
            'usage_units' => $usage_units,
            'unit_price_cents' => $unit_price_cents,
        );
    }

    private function require_class(string $fqcn): void
    {
        if (! class_exists($fqcn)) {
            $this->fail('Missing class contract for RED phase: ' . $fqcn);
        }
    }
}
