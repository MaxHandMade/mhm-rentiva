# Sprint-19 L2 Patch Plan
## Usage-Based Billing Extension - Idempotent Billing Executor (Strict)

Instruction ID: S19-USAGE-BASED-BILLING
Layer: L2 (Transaction-Safe Executor + Ledger Integration)
Mode: Strict TDD
Risk Level: High (Revenue Path)

## Objective
Implement an idempotent, transaction-safe usage billing executor that converts an already computed L1 deterministic result into a single authoritative financial mutation path (invoice/billing record + ledger write) with restart-safe duplicate protection.

## Boundaries
In scope:
- Usage billing idempotency schema and repository
- Idempotent executor orchestration with one DB transaction boundary
- Duplicate/no-op behavior for reruns and concurrent workers
- Rollback safety for partial failure scenarios
- Drift mismatch guard (no ledger write)
- Tenant-scoped feature flag gate (OFF -> legacy/no-op contract for L2)
- Integration + stress tests under Billing

Out of scope:
- Pricing math changes (L1 already certified)
- Gateway/API changes
- Monitoring/alert pipeline mutation
- External calls
- Retry loops in executor

## Architecture
New files:
- `src/Core/Billing/Usage/UsageBillingExecutor.php`
- `src/Core/Billing/Usage/UsageBillingIdempotencyRepository.php`
- `src/Core/Database/Migrations/UsageBillingMigration.php`
- `tests/Integration/Billing/UsageBillingIdempotencyRepositoryTest.php`
- `tests/Integration/Billing/UsageBillingExecutorIntegrationTest.php`
- `tests/Stress/Billing/UsageBillingExecutorStressTest.php`

Changed files:
- `src/Admin/Core/Utilities/DatabaseMigrator.php` (migration wiring + version bump)
- `tests/bootstrap.php` or shared test helpers only if new seed helper is required

## Data Model (L2)
Create `{$wpdb->prefix}mhm_rentiva_usage_billing` append-style idempotency table:
- `id` BIGINT UNSIGNED PK
- `tenant_id` BIGINT UNSIGNED NOT NULL
- `subscription_id` BIGINT UNSIGNED NOT NULL
- `period_start_utc` DATETIME NOT NULL
- `period_end_utc` DATETIME NOT NULL
- `idempotency_key` VARCHAR(191) NOT NULL
- `amount_cents` BIGINT NOT NULL
- `computation_hash` CHAR(64) NOT NULL
- `ledger_transaction_uuid` VARCHAR(64) NULL
- `status` VARCHAR(24) NOT NULL (`pending|committed|skipped_drift`)
- `created_at_utc` DATETIME NOT NULL
- `updated_at_utc` DATETIME NOT NULL

Constraints:
- `UNIQUE KEY uniq_usage_billing_idempotency (idempotency_key)`
- `UNIQUE KEY uniq_usage_billing_window (tenant_id, subscription_id, period_start_utc)`
- `KEY tenant_period_idx (tenant_id, period_start_utc)`

Idempotency key contract:
- `sha256("{tenant_id}|{subscription_id}|{period_start_utc}")` lowercase hex

## Transaction Contract (Non-Negotiable)
Single atomic boundary:
1. `START TRANSACTION`
2. Feature flag check (tenant scoped)
3. Idempotency insert attempt (unique constrained)
4. Drift/hash guard check (if mismatch -> rollback + telemetry, no ledger write)
5. Ledger write (exactly once)
6. Mark idempotency row `committed` with ledger UUID
7. `COMMIT`

Failure path:
- Any exception -> `ROLLBACK`
- No orphan idempotency row with committed status
- No partial ledger mutation
- No silent catch

## Determinism and Financial Locks
1. L2 performs no pricing recompute; it only consumes `BillingComputationResult` from L1.
2. All financial values are `amount_cents` integers in L2 state and checks.
3. No float arithmetic in executor/repository; only legacy ledger boundary mapping is allowed once, explicitly audited.
4. UTC-only timestamps (`gmdate('Y-m-d H:i:s')`).
5. Double/triple run returns deterministic no-op after first commit.
6. Parallel execution cannot create duplicate ledger write.
7. Drift mismatch produces telemetry event and blocks ledger mutation.

## Final Financial Hardening Addendum (Mandatory)
1. Idempotency insert must be race-safe:
   - No select-before-insert check.
   - Use direct `INSERT` with unique-key handling.
   - Duplicate key must route to deterministic no-op branch.
2. Status transitions are locked:
   - `pending -> committed`
   - `pending -> skipped_drift`
   - rollback only via transaction rollback (no persisted `rollback` state)
   - Illegal transitions are rejected.
3. Ledger UUID binding guard:
   - `pending` rows must keep `ledger_transaction_uuid = NULL`.
   - `committed` rows must have non-empty `ledger_transaction_uuid`.
4. Drift/hash guard must use strict comparison only:
   - `!==` comparison for `computation_hash`.
   - No loose cast or coercion.
5. Transaction scope enforcement:
   - Single `START TRANSACTION` / `COMMIT` / `ROLLBACK` in executor path.
   - No nested transaction behavior.
   - Exceptions must bubble after rollback (no silent catch).
6. Mutating call order is fixed:
   - Insert pending idempotency row.
   - Drift/hash guard.
   - Ledger write.
   - Mark committed.
   - Ledger write is forbidden before idempotency insert and drift guard.
7. Integer guard extension:
   - Reject non-int and negative `amount_cents` before transaction.
   - Guard against overflow/wrap before ledger mutation.

## TDD Execution Plan
### Task 1 - RED: Idempotency Repository Contracts
Add failing tests for:
- insert first-time success
- duplicate key second insert -> no-op path
- cross-tenant same period allowed
- invalid idempotency key rejected

### Task 2 - GREEN: UsageBillingIdempotencyRepository
Implement minimal repository methods:
- `build_idempotency_key(int $tenantId, int $subscriptionId, string $periodStartUtc): string`
- `insert_pending(array $row): bool`
- `mark_committed(string $idempotencyKey, string $ledgerUuid): bool`
- `find_by_key(string $idempotencyKey): ?array`

### Task 3 - RED: Executor Idempotent Double/Triple Run
Failing tests:
- double execution -> exactly 1 ledger entry
- triple execution -> still 1 ledger entry
- existing committed row -> deterministic no-op response

### Task 4 - GREEN: UsageBillingExecutor Minimal Atomic Flow
Implement executor with one transaction boundary and explicit rollback behavior.

### Task 5 - RED: Partial Failure Rollback Safety
Failing test:
- force failure after idempotency insert and before ledger commit
- assert rollback: no committed idempotency row, no ledger row

### Task 6 - GREEN: Drift Guard and No-Write on Drift
Failing tests then implementation:
- computation hash mismatch -> no ledger write
- telemetry `usage_billing_drift_detected` emitted

### Task 7 - RED/GREEN: Concurrency and Cross-Tenant Isolation
Stress tests:
- parallel execution simulation for same key -> 1 ledger entry
- tenant A and tenant B same window -> isolated billing rows and ledger effects

### Task 8 - RED/GREEN: Feature Flag OFF Legacy Contract
Failing test then implementation:
- when usage billing flag OFF for tenant, executor returns legacy/no-op path
- no usage billing row and no ledger mutation from L2 path

## Mandatory Test Matrix (L2)
- `test_double_execution_creates_single_ledger_entry()`
- `test_triple_execution_still_single_ledger_entry()`
- `test_parallel_execution_is_idempotent()`
- `test_partial_failure_rolls_back_without_orphan_writes()`
- `test_cross_tenant_isolation_under_same_window()`
- `test_drift_mismatch_blocks_ledger_and_emits_metric()`
- `test_feature_flag_off_uses_legacy_path()`
- `test_idempotency_key_contract_sha256_lowercase()`
- `test_parallel_insert_yields_single_pending_row_and_no_duplicate_ledger()`
- `test_committed_row_cannot_be_marked_committed_again()`
- `test_pending_uuid_and_committed_uuid_binding_contract()`
- `test_strict_hash_inequality_blocks_write_without_type_coercion()`
- `test_exception_in_ledger_write_rolls_back_and_rethrows()`
- `test_ledger_write_happens_after_pending_insert_and_drift_guard()`
- `test_amount_cents_must_be_non_negative_int()`

## Verification Commands (Container Only)
- `vendor/bin/phpunit tests/Integration/Billing --no-coverage`
- `vendor/bin/phpunit tests/Stress/Billing --no-coverage`
- `vendor/bin/phpunit --filter UsageBilling --no-coverage`
- `vendor/bin/phpunit --no-coverage`
- `COMPOSER_PROCESS_TIMEOUT=0 composer check-release`

## Acceptance Gate
Fail if:
- Duplicate billing is possible.
- Ledger write occurs when drift/hash mismatch exists.
- Partial transaction leaves orphan state.
- Feature flag OFF still executes usage billing write path.
- Any float arithmetic appears in new L2 module.
- Full suite regression or release gate non-zero.

Pass if:
- All L2 targeted tests PASS.
- Stress Billing suite PASS.
- Full suite PASS.
- Release gate exit code 0.

## Evidence and Handoff
Archive under `docs/internal/evidence/s19_l2/`:
- `targeted_billing_l2.log`
- `stress_billing_l2.log`
- `fullsuite_l2.log`
- `release_gate_l2.log`
- `exit_codes.txt`
- `S19_L2_BILLING_EXECUTOR_CERTIFICATION.md`

L3 may start only after L2 governance PASS and evidence archive is complete.
