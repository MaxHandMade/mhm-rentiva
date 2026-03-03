# Sprint-19 L3 Feature Flag Rollout Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Activate tenant-scoped rollout control for usage-based billing with deterministic ON/OFF behavior and rollback safety, without changing L2 executor financial guarantees.

**Architecture:** Introduce a tenant feature-flag repository and resolver as a thin control plane in front of `UsageBillingExecutor`. Read flag once per execution and pass immutable boolean to executor. Preserve existing L2 idempotency, transaction, and drift logic unchanged.

**Tech Stack:** PHP 8+, WordPress `$wpdb`, PHPUnit integration/stress suites, existing billing module.

---

## Constraints (Non-Negotiable)
- Default feature state is OFF.
- Storage is `TINYINT(1) NOT NULL DEFAULT 0` (no nullable truthy/falsy drift).
- Flag decision is read once at executor entry and must remain immutable for that execution.
- Telemetry failures must not affect billing path.
- No float, UTC only, no silent financial mutation.
- Cross-tenant isolation is mandatory.

## Files
- Create: `src/Core/Billing/Usage/UsageBillingFeatureFlagRepository.php`
- Create: `src/Core/Billing/Usage/UsageBillingFeatureResolver.php`
- Create: `src/Core/Database/Migrations/UsageBillingFeatureFlagMigration.php`
- Modify: `src/Admin/Core/Utilities/DatabaseMigrator.php`
- Modify: `src/Core/Billing/Usage/UsageBillingExecutor.php`
- Create: `tests/Integration/Billing/UsageBillingFeatureFlagRepositoryTest.php`
- Create: `tests/Integration/Billing/UsageBillingFeatureResolverTest.php`
- Create: `tests/Integration/Billing/UsageBillingFeatureRolloutIntegrationTest.php`
- Create: `tests/Stress/Billing/UsageBillingFeatureRolloutStressTest.php`

## Task 1: RED - Feature Flag Repository/Resolver Contracts

**Step 1: Write failing repository tests**
- `test_default_flag_is_off_when_row_missing`
- `test_set_enabled_persists_tinyint_one`
- `test_set_disabled_persists_tinyint_zero`
- `test_null_or_invalid_value_is_rejected`
- `test_cross_tenant_reads_are_isolated`

**Step 2: Write failing resolver tests**
- `test_resolver_returns_false_when_disabled`
- `test_resolver_returns_true_when_enabled`
- `test_resolver_cast_is_strict_boolean`

**Step 3: Run targeted RED tests**
- `vendor/bin/phpunit --filter UsageBillingFeatureFlag --no-coverage`
- Expected: FAIL (missing classes/contracts)

## Task 2: GREEN - Migration + Repository + Resolver

**Step 1: Create migration**
- Table: `{$wpdb->prefix}mhm_rentiva_usage_billing_feature_flags`
- Columns: `tenant_id` PK, `usage_based_billing_enabled` TINYINT(1) NOT NULL DEFAULT 0, UTC timestamps.

**Step 2: Implement repository**
- `is_enabled(int $tenant_id): bool`
- `set_enabled(int $tenant_id, bool $enabled, string $now_utc): bool`
- Strict input validation, strict cast from DB tinyint.

**Step 3: Implement resolver**
- Pure decision wrapper around repository.
- No side effects.

**Step 4: Run targeted tests**
- `vendor/bin/phpunit --filter UsageBillingFeatureFlag --no-coverage`
- Expected: PASS

## Task 3: RED/GREEN - Executor Integration (Read-Once Flag Snapshot)

**Step 1: Add failing integration tests**
- `test_executor_reads_flag_once_per_execution`
- `test_flag_off_routes_to_legacy_path`
- `test_flag_on_routes_to_usage_billing_path`

**Step 2: Implement minimal integration**
- Read flag once at execution boundary.
- Bind to local immutable bool.
- Pass bool into existing L2 execution path unchanged.

**Step 3: Run billing integration tests**
- `vendor/bin/phpunit tests/Integration/Billing --no-coverage`

## Task 4: RED/GREEN - Rollback Safety and Telemetry Isolation

**Step 1: Add failing tests**
- `test_toggle_on_then_off_does_not_mutate_existing_commits`
- `test_telemetry_emit_failure_does_not_break_execution`
- `test_cross_tenant_mixed_flags_are_isolated`

**Step 2: Implement minimal code**
- Add no-throw telemetry wrapper for rollout counters.
- Preserve existing committed ledger rows on rollback toggles.

**Step 3: Run targeted + stress tests**
- `vendor/bin/phpunit tests/Stress/Billing --no-coverage`

## Task 5: Final Gate

Run:
- `vendor/bin/phpunit tests/Integration/Billing --no-coverage`
- `vendor/bin/phpunit tests/Stress/Billing --no-coverage`
- `vendor/bin/phpunit --no-coverage`
- `COMPOSER_PROCESS_TIMEOUT=0 composer check-release`

Required:
- Errors: 0
- Failures: 0
- Exit code: 0

## Evidence Package
Archive under `docs/internal/evidence/s19_l3/`:
- `targeted_billing_feature_flags.log`
- `stress_billing_feature_flags.log`
- `fullsuite_l3.log`
- `release_gate_l3.log`
- `exit_codes.txt`
- `run_summary.md`

## Acceptance
Fail if:
- Flag OFF can still mutate usage billing path.
- Flag value is nullable/ambiguous.
- Executor re-reads flag mid-execution.
- Cross-tenant flag contamination occurs.
- Telemetry failure impacts financial execution.
- Full suite or release gate fails.

Pass if:
- Tenant-scoped rollout is deterministic.
- L2 idempotent/atomic behavior is preserved.
- Rollback is safe and non-mutating.
- Full governance gate passes.
