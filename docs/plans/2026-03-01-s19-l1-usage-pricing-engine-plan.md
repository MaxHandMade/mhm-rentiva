# Sprint-19 L1 Patch Plan
## Usage-Based Billing Extension - Pricing Engine (Strict)

Instruction ID: S19-USAGE-BASED-BILLING
Layer: L1 (Pure Pricing Engine, No Ledger Mutation)
Mode: Strict TDD

## Objective
Build a deterministic, side-effect-free usage pricing engine that converts closed-window usage snapshot data into `amount_cents` and emits drift-safe computation output.

## Boundaries
In scope:
- Usage snapshot DTO and validation
- Deterministic pricing engine
- Computation result value object
- Closed-window enforcement (`period_end_utc <= now_utc`, `recomputed_at_utc >= period_end_utc`)
- Deterministic output hash for rerun parity checks
- Integration tests under `tests/Integration/Billing`

Out of scope:
- Ledger writes
- Invoice/charge persistence
- Feature flag activation
- Retry/circuit logic
- Gateway changes

## Architecture
New module path:
- `src/Core/Billing/Usage/UsageSnapshotDTO.php`
- `src/Core/Billing/Usage/UsagePricingEngine.php`
- `src/Core/Billing/Usage/BillingComputationResult.php`

Design rules:
- Integer-only math (`amount_cents` as int)
- No float operations
- No DB writes
- No global mutable state
- UTC-only time handling
- Canonical deterministic calculation order

## Micro-Hardening Locks (Mandatory)
1. Pricing formula determinism:
   - `amount_cents = sum(usage_units_i * unit_price_cents_i)`
   - `usage_units` and `unit_price_cents` are strict integers
   - Metric order must be canonicalized before computation
   - Overflow guard is mandatory
2. Snapshot immutability:
   - `UsageSnapshotDTO` fields immutable (readonly semantics)
   - Constructor validation mandatory
   - Deep-copy and internal sorting of metrics required
3. Deterministic hash contract:
   - `BillingComputationResult` hash uses canonical JSON
   - Alphabetical keys and stable metric ordering
   - `sha256` lowercase hex output only
4. Integer safety:
   - Negative usage and negative price must be rejected
   - Overflow/unsafe integer results must fail fast
5. Clock isolation:
   - Engine cannot read runtime clock (`time`, `gmdate`, `DateTime*` now)
   - `now_utc` must be injected as method input

## TDD Tasks
1. Red: create failing tests for deterministic same-input same-output behavior.
2. Red: create failing tests for closed-window rejection and open-window skip.
3. Red: create failing tests for drift detection (`recomputed_at_utc < period_end_utc` or snapshot mismatch marker).
4. Red: add micro-hardening tests:
   - `test_metric_order_independent()`
   - `test_snapshot_immutability()`
   - `test_hash_canonical_stability()`
   - `test_negative_usage_rejected()`
   - `test_large_integer_guard()`
   - `test_now_must_be_injected_not_runtime()`
5. Green: implement `UsageSnapshotDTO` strict validation and immutable fields.
6. Green: implement `UsagePricingEngine` deterministic `amount_cents` computation.
7. Green: implement `BillingComputationResult` with stable hash payload.
8. Refactor: normalize ordering and remove duplication in tests/engine.

## Test Matrix (L1)
Required tests:
- Same snapshot computes identical `amount_cents` across double run.
- Result hash equality across reruns.
- Different metric ordering yields same output and same hash.
- Snapshot remains immutable after source array mutation.
- Canonical hash remains stable against key-order variation.
- Open billing window is rejected.
- Non-UTC / invalid timestamps are rejected.
- Float-like input is rejected.
- Negative usage is rejected.
- Negative price is rejected.
- Large integer overflow is explicitly guarded.
- Clock usage is dependency-injected only (`now_utc` input mandatory).
- Tenant isolation in computation context (no cross-tenant value bleed).
- No side-effect assertion (no ledger and no billing write path touched).

## Verification Commands
Container-only:
- `vendor/bin/phpunit tests/Integration/Billing --no-coverage`
- `vendor/bin/phpunit --filter UsagePricingEngine --no-coverage`
- `vendor/bin/phpunit --no-coverage`
- `COMPOSER_PROCESS_TIMEOUT=0 composer check-release`

## Acceptance Gate
Fail if:
- Any float usage appears in pricing module.
- Closed-window rule is bypassed.
- Same input produces different hash or `amount_cents`.
- Any DB mutation occurs in L1.
- Full suite regression appears.

Pass if:
- L1 targeted billing suite PASS.
- Filtered pricing suite PASS.
- Full suite PASS.
- Release gate exit code 0.

## Next Layer Handoff
L2 can start only after L1 PASS evidence is archived.
L2 scope: idempotent billing executor with transaction-safe ledger integration.
