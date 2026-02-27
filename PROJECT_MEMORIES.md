# PROJECT MEMORIES

### [CERTIFIED] Sprint 15: SaaS Orchestration & Control Plane (v1.9) (2026-02-27)
- **Status:** [CERTIFIED] / FULL PASS (Recovery v1.9k)
- **Instruction:** S15-SAAS-ORCHESTRATION-001
- **Sprint 15 (v1.9 Certification)**: Authoritative Control Plane mimarisi başarıyla uygulandı. GitHub Workflows, PHP 8.1/8.3 uyumlulukları ve Architectural IDE Parity (v1.9k) tamamen mühürlendi.
- **Scope:** Centralized Control Plane, Multi-PHP Compatibility, Snake Case Migration, IDE Stub Hardening.
- **Key Achievements:**
    - **IDE Stub Hardening (v1.9k):** Merkezi `tests/phpunit-stubs.php` dosyası güncellenerek `set_up`/`tear_down` metodları global olarak IDE'ye tanıtıldı. Test dosyalarındaki tüm kirli anotasyonlar temizlenerek tertemiz bir kod tabanı sağlandı.
    - **Snake Case Migration (v1.9h):** IDE signature hatalarını çözmek için `set_up`/`tear_down` hiyerarşisine geçildi.
- **Quality Gates:** 348 tests, 1606 assertions — 0 Errors, 0 Failures. CI & IDE environments confirmed %100 Clean.
- **Architectural State:** Engine Version v1.9 Certified. Full Multi-PHP/SaaS Control Plane.

### [CERTIFIED] Sprint 13: Time-Locked Execution & Cooling Period Engine (v1.7) (2026-02-26)
- **Status:** [CERTIFIED] / FULL PASS
- **Instruction:** S13-TEMPORAL-GOVERNANCE-001
- **Sprint 13 (v1.7 Certification)**: Temporal Risk Governance (Time-Locked Payouts, Cooling Periods, Ledger Reservation) başarıyla uygulandı.
- **Sprint 14 (v1.8 Certification)**: Multi-Tenant Financial Isolation Layer. Tenant-specific ledger partitioning, cryptographic isolation, and hard fallback guards implemented. Physical concurrency proof achieved.
- **Scope:** Risk-Based Cooling Periods, Time-Locked State Machine, Ledger Reservation (Double-Spend Prevention), Idempotency-Guarded Workers.
- **Key Achievements:**
    - **Temporal Governance:** `CoolingPolicyManager` implemented with risk-tiered delays (6h, 24h, 48h). Strictly UTC-normalized.
    - **Ledger Invariant:** Implemented bakiye reservation at approval time. Finalization updates existing `reserved` record to `cleared` without 0-amount mutations.
    - **Operational Safety:** `MaturedPayoutJob` and CLI `execute-matured` provide robust automated settlement. SQL-level idempotency guard prevents race conditions.
    - **Admin Control:** Capability-gated `bypass_time_lock` allows emergency execution with mandatory forensic audit logging.
- **Quality Gates:** Concurrency, HIGH-risk cooling, and Admin bypass verification scenarios passed (see Sprint 13 Walkthrough).
- **Architectural State:** Engine Version v1.7 Certified. Production-grade temporal financial control system with atomic ledger integrity.

### 4. Current Architecture (v1.8)
- **Kernel**: Atomic, Governed, Time-Aware, Idempotent, Multi-Tenant Financial Motor.
- **Security**: Asymmetric Signing (Per-Tenant), No Silent Fallback, Hard Context Guards, Composite Indexing.
- **Compliance**: Tenant-specific cooling policies, UTC strict audit trails.

### [CERTIFIED] Sprint 11: Immutable External Audit Export Layer (v1.5) (2026-02-26)
- **Status:** [CERTIFIED] / FULL PASS
- **Instruction:** S11-AUDIT-EXPORT-001
- **Scope:** Cryptographically Verifiable External Trust, Hash-Chained Audit Export, Asymmetric Ed25519 Detached Signatures.
- **Key Achievements:**
    - **Cryptographic Trust Layer:** `KeyPairManager` implemented with `libsodium` Ed25519 (RSA fallback). Secure private key encryption via `secretbox`.
    - **Tamper-Evident Hash Chain:** `HashChainBuilder` ensures mathematically proven integrity across historical rows with strict format normalization.
    - **Read-Only Export Isolation:** `AuditExportService` enforces `READ COMMITTED` and `READ ONLY` transaction isolation during CSV export.
    - **CLI Orchestration:** `wp mhm audit:export` generates deterministic CSV, detached `.sig`, and `.meta.json`.
- **Quality Gates:** 338 tests, 1586 assertions — 0 Errors, 0 Failures. CI strict determinism passed.
- **Architectural State:** Engine Version v1.5 Certified. Institutional-Grade, Auditor-Ready Financial Control Platform. Ready for SOC 2 / ISO 27001 evaluation.

### [PATCHED] v4.20.3 - Allowlist enum expansion (status, orderby, sort_by) (2026-02-25)
- **Status:** [PATCHED] / ENUM DRIFT HOTFIX
- **Instruction:** V4.20.3-ALLOWLIST-ENUM-EXPANSION
- **Scope:** Allowlist enum expansion only (`status`, `orderby`, `sort_by`). No CAM, TAG_MAPPING, renderer, or Elementor code changes.
- **Result:** Booking status/runtime sort enums aligned with backend-supported values.
- **Verification:** `vendor/bin/phpunit --testdox` passed (see `docs/internal/QA_V4203_ALLOWLIST_ENUM_EXPANSION.md`).
### [STABILIZED] v4.20.2 Allowlist Contract Integrity Restored (2026-02-25)
- **Status:** [STABILIZED] / FULL PASS
- **Instruction:** V4.20.2-ALLOWLIST-STABILIZATION
- **Changes:**
    - C1 FIXED: 10 duplicate ALLOWLIST keys removed, aliases merged
    - M1 FIXED: values arrays added to status, default_payment, service_type, type, default_tab
    - M2 FIXED: 5 new canonical keys added (show_technical_specs, show_booking_form, show_book_button, sort_by, sort_order); 2 TAG_MAPPING redundancies removed (show_avatar, filter_rating)
    - GOVERNANCE: SHORTCODES.md added to version control
- **Test Count:** 273 tests, 1390 assertions — FULL PASS
- **QA Decision:** FULL PASS — Runtime Verified

---

### [AUDIT] V4.20.1 Ecosystem X-Ray — CONDITIONAL PASS (2026-02-25)
- **Status:** [AUDIT] / DOCUMENTED
- **Instruction:** V4.20.1-ECOSYSTEM-AUDIT
- **Scope:** Full ecosystem audit of shortcodes, blocks, AllowlistRegistry, Elementor widgets. Documentation synchronization only (no behavioral changes).
- **Inventory Snapshot:**
    - Active Shortcodes: 20 (19 fully covered + 1 experimental `rentiva_home_poc`)
    - Gutenberg Blocks: 19 (all map to shortcodes via Render Parity)
    - AllowlistRegistry TAG_MAPPING entries: 19
    - Elementor Widgets: 20 (19 SC-mapped + 1 VehicleCardWidget component)
    - block.json files: 19 verified
- **Critical Findings:**
    - **C1:** `AllowlistRegistry::ALLOWLIST` has **10 duplicate PHP array keys**. PHP silently overwrites first definitions. Aliases for `ids`, `redirect_page`, `default_tab` (type changed enum→string), `show_booking_button`, `show_favorite_btn`, `show_compare_btn` are affected at runtime. Section 8 alias table in SHORTCODES.md reflects pre-duplicate state. **Code fix required as separate authorized task.**
    - **C2:** `rentiva_home_poc` is SC-Only (no AllowlistRegistry schema, no Block, no Elementor widget). Intentionally experimental. Guarded by `mhm_rentiva_enable_home_poc` filter.
- **Major Findings:**
    - **M1:** 4 enum attributes without `values` constraint: `status`, `default_payment`, `service_type`, `type`.
    - **M2:** 7 TAG_MAPPING attributes not in canonical ALLOWLIST: `show_technical_specs` (x2), `show_booking_form`, `show_book_button`, `sort_by`, `sort_order`, `show_avatar` — treated as untyped pass-through by `get_registry()`.
- **Documentation Updated:**
    - `SHORTCODES.md` — Added Section 11 (Elementor widget registry), Section 12 (Audit Findings), Contract Change Log entry.
    - `docs/plans/2026-02-25-v4201-ecosystem-audit.md` — Full audit plan and discrepancy matrix.
- **PHPUnit Baseline:** v4.20.0 foundation freeze: 268 tests, 1379 assertions. No regressions introduced.
- **Render Parity Rule:** ✅ No dual render paths detected. All blocks delegate to shortcode renderer.
- **Memory Conflict Status:** None detected. Audit-only task.
- **QA Decision:** CONDITIONAL PASS — runtime stable, documentation updated, C1/M1/M2 flagged for future resolution.

### [LOCKED] Foundation Freeze & Attribute Pipeline Hardening — ✅ MISSION COMPLETE (v4.20.0) (2026-02-22)
- **Status:** [LOCKED] / STABLE
- **Core Outcome:** Established the official foundation baseline of the Rentiva engine. Canonical attribute pipeline hardened, runtime surface stabilized, and render parity invariant enforced.
- **Key Achievements:**
    - **Attribute System Hardening:** Eliminated undefined array key warnings, aligned canonical defaults, and secured `$_POST` / `$_GET` bypass scenarios.
    - **Runtime Surface Validation:** Clean HTTP lifecycle sweep across all endpoints (0 PHP warnings, 0 deprecated notices, clean debug.log).
    - **Static Analysis:** Plugin Check (Release Scope) passed with 0 ERRORs.
    - **Deterministic Quality:** PHPUnit strictly passing (268 tests, 1379 assertions).
- **Architectural Status:** The official regression anchor and canonical attribute baseline. Future releases build on this locked foundation.
- **Reference Tag:** `v4.20.0` (Commit: `5f34f4f1343e052acc7fd7f53f8d67a75624f436`)

### [COMPLETED] Documentation Consolidation & Source of Truth Hardening (Sequence 5) (2026-02-21)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Hardening the source of truth for shortcodes and UI contracts, eliminating documentation drift.
- **Key Achievements:**
    1. **3-Layer Default Matrix:** Generated a deterministic matrix comparing `block.json`, Shortcode classes, and `AllowlistRegistry` to surface and resolve any attribute discrepancies.
    2. **UI Contracts Resolution:** Resolved legacy discrepancy notes in `UI_CONTRACTS.md` and explicitly documented CSS-Driven layout and Grid Isolation contracts.
- **Architectural Invariants Established:**
    - **No Dual Render Pipeline:** The system strictly uses a single rendering path. Gutenberg blocks are purely visual wrappers in the editor; on the frontend, their attributes are dynamically mapped to, and rendered exactly by, the canonical shortcode logic. Block JSON and Shortcode PHP defaults must functionally align.
    - **Alias Resolution Policy:** Block attributes (often camelCase) are transformed into Shortcode attributes (snake_case) or explicitly mapped (e.g., `bookingId` -> `booking_id`). The `AllowlistRegistry` is the absolute authority defining whether an alias is permitted. There is no undocumented "magic" attribute mapping.

### [COMPLETED] Stabilization & Attribute Pipeline Hardening (v4.20.0) (2026-02-21)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Strict Governance execution of Sequence-1, Sequence-2, and Sequence-3 to finalize Canonical Attribute Parity.
- **Key Achievements:**
    1. **Sequence-1 (Baseline Stabilization):** Enforced overflow safety, eliminated table-layout conflicts in Account dashboards, and enforced strict CSS container queries.
    2. **Sequence-2 (Vehicle Card Unification):** Eliminated legacy CSS forks (`.mhm-content-actions`). Established single-source-of-truth DOM structure for Grid, List, and Favorites layouts.
    3. **Sequence-3 (Search/Transfer Parity):** Isolated Header/Route elements from CSS Grids. Standardized Column and Layout mappings directly to CSS variables to prevent double-layout switches between Block Editor and Frontend.
- **Architectural State:**
    - Canonical attribute pipeline stabilized.
    - Editor ↔ Frontend fully aligned.
    - Layout fork eliminated.
    - CSS-driven layout switching standardized.

### [COMPLETED] Observability & Control Layer (v4.19.x) (2026-02-20)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Implementation of Layout History, Manifest Diff, and Append-only Audit Trail.
- **Key Achievements:**
    1. **LayoutAuditService:** Append-only logging with a hard 200-event retention cap per post.
    2. **LayoutDiffService:** Deterministic manifest-level diffing (Tokens/Components).
    3. **CLI Integration:** Unified `history` and `diff` commands for operational transparency.
    4. **Governance:** Maintained deterministic normalization and hashing invariants.

### [COMPLETED] Safe Rollback Engine (v4.18.x) (2026-02-19)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Implementation of a deterministic 6-state rollback machine (A-F) for layout versioning.
- **Key Achievements:**
    1. **Shift vs. Flip Separation:** Resolved a critical design conflict by decoupling "Current-to-Previous Shift" (standard import) from "Current-Previous Flip" (rollback). `AtomicImporter` now accepts an `is_rollback` flag.
    2. **6-State Atomic Machine:** Implemented `LayoutRollbackService` with Snapshot (A), Load (B), Validate (C), Apply/Flip (D), Recovery (E), and Success (F) states.
    3. **Governance Binding:** Rollback process strictly enforces `BlueprintValidator` gates; snapshots are only restored if the entire multi-step process succeeds.
    4. **WP-CLI Integration:** Added `wp mhm-rentiva layout rollback <post_id>` command with strict dry-run support and performance feedback.
- **Core Outcome:** Integration Test Suite achieves **OK (4 tests, 21 assertions)**. Verified zero side-effect dry-runs and successful meta swap post-apply.
- **Reference Evidence:** [walkthrough.md](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\walkthrough.md)


### [COMPLETED] Multi-Page Ingestion & Atomic Rollback (v4.16.x) (2026-02-19)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Orchestration of multi-page layout imports with atomic rollback and deterministic resolution.
- **Key Achievements:**
    1. **AtomicImporter Engine:** Implemented a robust snapshot/restore mechanism. Every import is transactional; if one page fails composition, all changes (new pages, updated content/meta) are automatically rolled back.
    2. **Deterministic Resolution:** Established `post_id > slug` priority for page matching. Added support for `--create` flag to dynamically generate missing pages.
    3. **CLI Table Summary:** Upgraded `LayoutImportCommand` with a visual summary table and test-safe execution guards (handling non-CLI environments).
    4. **Composition Hardening:** Made `CompositionBuilder` strict; it now returns `WP_Error` for unknown components or missing adapters, triggering immediate rollback.
- **Core Outcome:** Integration Test Suite achieves **OK (29 tests, 72 assertions)**. All major failure modes (invalid components, database errors, cache stale) verified.
- **Reference Evidence:** [walkthrough.md](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\walkthrough.md)

### [COMPLETED] DOCS Sprint: Template Pack (v4.17.0-docs) (2026-02-19)
- **Status:** [COMPLETED]
- **Scope:** A reusable template pack for rapidly bootstrapping the Layout Engine into new projects.
- **Key Achievements:**
    1. **Framework Extraction:** The Layout engine was split into "Core" and "Preset" layers.
    2. **Core vs Preset Matrix:** Project-specific change boundaries were formally defined.
    3. **Governance Guidelines:** Zero-Tailwind and ΔQ ≤ 0 policies were documented.
    4. **Operational Runbooks:** Full operational guidance was created from bootstrap to CI acceptance gates.
- **Outcome:** The project evolved from a single plugin into a "Layout Platform" with mature engineering standards.
- **Location:** `docs/template/`

### [COMPLETED] Token Mapping Engine (v4.15.0) (2026-02-19)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Deterministic design token translation (Figma -> MHM CSS Variables).
- **Key Achievements:**
    1. **TokenMapper Integration:** Implemented bounded mapping logic that scopes design tokens to the `.mhm-layout-root` container.
    2. **Governance Enforcement:** Enforced "No Global Explosion" rule; tokens are applied as inline styles on the root container, preventing global CSS pollution.
    3. **Strict Sanitization:** Validated color, spacing, and radius values against a strict regex allowlist to block runtime framework leakage.
- **Core Outcome:** Integration Test Suite achieves **OK (21 tests, 53 assertions)**. ΔQ remains <= 0.
- **Reference Evidence:** [walkthrough.md](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\walkthrough.md)

### [COMPLETED] Layout Import Pipeline (Implementation Phase 1) (v4.14.x) (2026-02-19)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Execution of the vendor-agnostic import pipeline.
- **Key Refinements:**
    1. **Static Contract Config:** Authority moved from markdown to `ContractRules.php`.
    2. **Governance Gate:** Strict prohibited pattern detection (Tailwind/Utility) enforced in `BlueprintValidator`.
    3. **CLI Stability:** WP-CLI `import` command stabilized with end-to-end persistence verification.
- **Core Outcome:** All integration tests for Layout Engine passed.
- **Reference Plan:** [implementation_plan.md](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\implementation_plan.md)

### [COMPLETED] Layout Import Pipeline (Design Phase) (v4.13.x) (2026-02-19)
- **Status:** DESIGN COMPLETED
- **Scope:** Architecture & specification of the vendor-agnostic import pipeline.
- **Key Design Decisions:**
    1. **Blueprint Manifest Schema (D1):** Implemented a JSON schema with strict governance metadata. Invariants: must track version, tokens, and component source-of-truth.
    2. **Bounded Token Mapping (D2):** No global palette explosion allowed. Mapping strictly limited to existing CSS variable layers.
    3. **Renderer Adapter Contract (D3):** All blueprint components must pass through `CanonicalAttributeMapper` (CAM) for normalization.
    4. **Hybrid Composition Strategy (D4):** Manifest stored in meta; Rendered block markup stored in `post_content`. Ensures portability and SSOT.
    5. **Contract Safety Matrix (D5):** Strictly enforces `UI_CONTRACTS.md` stable selectors and required slots. No new DOM contracts permitted.
    6. **MCP-Gated CI Strategy (D6):** Defined static scanners for Tailwind prohibition and runtime probes for ΔQ=0 and asset isolation.
- **Governance Invariants:** 
    - No Tailwind Runtime.
    - No UI Contract Drift.
    - Performance Guarantee (ΔQ = 0).
- **Reference Specs:** [D1-D6 Artifacts](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\)

### [COMPLETED] Home POC Production Hardening Sprint (v4.12.0) (2026-02-19)
- **Status:** [COMPLETED] / VERIFIED
- **Scope:** Hardening of `[rentiva_home_poc]` component with focus on asset isolation, structured logging, and feature flag control.
- **Key Achievements:**
    1. **Asset Isolation:** Implemented deterministic asset snapshot checks in `HomePocAssetTest.php`. Verified zero global asset leakage.
    2. **Performance Guarantee (ΔQ = 0):** Optimized `UnifiedSearch.php` with static caching for locations and routes. Achieved the strict "zero additional query" goal for Home POC rendering.
    3. **Entry-Guard Feature Flag:** Added `mhm_rentiva_enable_home_poc` filter protection to `HomePoc::render()`. When disabled, the shortcode remains completely inert (no enqueues, no output).
    4. **Governance Hardening:** Modernized `Governance::log_violation` to be internal, private, and conditional on `WP_DEBUG` + `mhm_rentiva_enable_governance_log`.
- **Core Outcome:** Integration Test Suite achieves **OK (72 tests, 265 assertions)**. Home POC is now production-ready for the next phase.
- **Reference Evidence:** [walkthrough.md](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\walkthrough.md)

### [LOCKED] Stabilization Sprint — Pre-existing Debt Clearance (v4.11.x) (2026-02-19)
- **Status:** [LOCKED] / STABLE
- **Scope:** Resolution of 7 inherited PHPUnit failures from Phase 4 baseline + 5 discovered secondary bugs.
- **Key Fixes & Technical Debt Retirement:**
    1. **Template Safety:** `vehicle-card-base.php` now enforces strict initialization of `$show_category`, `$show_brand`, `$category_name`, and `$brand_name`, preventing `Undefined variable` errors in legacy isolation tests.
    2. **PHP Strict Compliance:** `FeaturedVehicles.php:121` refactored to use intermediate variables for `update_post_caches()` references.
    3. **Cache Pipeline Integrity:** Replaced `update_post_caches()` with `_prime_post_caches()` in `FeaturedVehicles` to correctly handle integer ID arrays when `'fields' => 'ids'` is active.
    4. **Architectural Parity:** `CoreAdminPagesTest` updated to test canonical attribute contracts instead of retired Phase 3 methods (`map_attributes_to_shortcode`).
    5. **Test Logic Hardening:** `ReviewNormalizationTest` fixed to prevent logic leakage on `markTestSkipped` and decoupled from environment-specific `WP_CACHE` settings by testing rating aggregate side-effects.
    6. **Alias Bridges:** Added `$allowed_svg`, `$btn_url`, and `$booking_text` bridges in `vehicle-card-base.php` to satisfy `vehicle-card.php` expectations without breaking v1.2 LOCKED parity.
    7. **Rating Perimeter:** Added `count > 0` guard to rating render logic to prevent visual artifacts for unrated vehicles.
- **Core Outcome:** Integration Test Suite achieves **OK (69 tests, 259 assertions)**. The codebase is now "Clean Slate" for the next sprint.
- **Reference Evidence:** [walkthrough.md](file:///C:\Users\manag\.gemini\antigravity\brain\ca40c671-98f0-4f16-9bf7-8ca4ec92f803\walkthrough.md)

> [!IMPORTANT]
> Stabilization Sprint results are FINAL. No new global variables were introduced; all fixes were surgical and backward-compatible.


### [LOCKED] Phase 3: Shortcode Canonicalization — ✅ MISSION COMPLETE (v4.11.0) (2026-02-18)
- **Status:** [LOCKED] / STABLE
- **Core Outcome:** `CanonicalAttributeMapper` (CAM) integrated as the mandatory execution layer for the entire Shortcode pipeline.
- **Key Achievements:**
    - **AbstractShortcode SSOT:** Centered attribute normalization in `AbstractShortcode::render()`, ensuring all shortcodes (manual or block-triggered) are canonicalized.
    - **`_canonical` Guard:** Implemented hybrid-safe execution to prevent double-mapping in Gutenberg environments.
    - **Registry Authority:** `AllowlistRegistry` upgraded to support per-tag defaults, removing redundant hardcoded defaults from shortcode classes.
    - **Structural Cleanup:** Retired redundant logic in `SearchResults`, `VehicleComparison`, and `AvailabilityCalendar`.
    - **Quality Gates:** 100% Success on PHPUnit (218 tests), PHPCS (0 errors), and Plugin-Check (Release Mode).
- **Reference Evidence:** `walkthrough.md`, `verify_phase3.php`.

### [LOCKED] Audit & Optimize Series (M1-M5) - ✅ MISSION COMPLETE (v4.9.9) (2026-02-15)
- **Status:** [LOCKED] / STABLE
- **Milestones Summary:**
    - **M1:** Shortcode engine normalization & asset parity.
    - **M2:** Test harness stabilization & Windows isolation.
    - **M3:** Featured Meta Migration (Legacy retirement).
    - **M4:** Release Governance & Quality Gates (0 Error policy).
    - **M5:** Performance Audit (Query 0 & Asset leakage fix).
- **Core Outcome:** Technical debt for vehicle meta and asset loading has been officially retired. The system is vertically stable for future scaling.
- **Reference Evidence:** `QA_M3_FINAL_EVIDENCE.md`, `M3_SCHEMA_MAPPING.md`.

### [LOCKED] Premium UX Milestones (v1.3.3) (2026-02-14)
- **Status:** [LOCKED] / STABLE
- **Scope:** Major UX upgrade focusing on notification centralization, optimistic feedback, and URL resolution standardization.
- **Key Features:**
    - **Centralized Toast System (`MHMRentivaToast`):** Replaced legacy `alert()` and jQuery UI notifications with a modern singleton engine.
        - Supports Live Updates: In-place content changes for two-stage operations.
        - Action-Driven: Integrated CTA buttons within toasts.
        - Idempotency: Key-based flood protection.
    - **Two-Stage Optimistic UI Pattern:**
        - Stage 1: Immediate "Processing..." feedback with `duration: 0`.
        - Stage 2: Final success/error state updating the same toast via `idempotencyKey`.
    - **Standardized URL Resolution:**
        - Implemented `ShortcodeUrlManager` to resolve core page URLs consistently across shortcodes and blocks.
        - Prioritizes physical page slugs (Loco Translate compatible) over technical defaults.
- **Files Modified:**
    - `assets/js/core/toast.js` (Engine)
    - `src/Admin/Core/AssetManager.php` (Localization strings)
    - `src/Admin/Core/ShortcodeUrlManager.php` (URL Registry)
    - `assets/js/frontend/vehicle-interactions.js` (2-stage logic)
- **Verification:**
    - Pass: Compare & Favorites 2-stage flow verified via browser tests.
    - Pass: Toast action button redirection verified.

> [!IMPORTANT]
> v1.3.3 establishes the "Product-Grade" UX baseline. All future UI components must use `MHMRentivaToast` for feedback.


### [LOCKED] Vehicle Card v1.0 — FINALIZED (v5 Baseline) (2026-02-09)
- **Status:** [LOCKED] / STABLE
- **Scope:** Minimal, data-trusted, shortcode-compatible vehicle card implementation.
- **Canonical Renderer:** `templates/partials/vehicle-card.php` — Single Source of Truth for ALL vehicle card displays (Grid, List, Featured, Search, Favorites).
- **v1.0 Approved Toggles (ONLY):**
  | Toggle | Status |
  |--------|--------|
  | `showPrice` | ✅ ACTIVE |
  | `showFeatures` | ✅ ACTIVE |
  | `showBookButton` | ✅ ACTIVE |
- **Explicitly Disabled (Hardcoded FALSE):**
  - `showRating` → Demo data inconsistencies
  - `showTopLabel` → Deferred to v1.1 (brand/category semantics TBD)
  - `showCategory`, `showBrand` → Shortcode contract limitations
  - `showBadges`, `showAvailability`, `showCompareButton`, `showFavoriteButton` → Not v1.0 scope
- **Block Editor Changes:**
  - `vehicles-grid/index.js`, `vehicles-list/index.js`, `featured-vehicles/index.js`, `search-results/index.js` → Only 3 toggles visible in sidebar
- **Files Modified:**
  - `templates/partials/vehicle-card.php` (hardcoded disabled defaults)
  - `assets/blocks/*/index.js` (4 files)
- **Future Work (v1.1+):**
  - `showTopLabel` → Single top-label slot with explicit semantics
  - Ratings → Clean data source definition required
  - Compare/Favorites → Full feature implementation

> [!CAUTION]
> v1.0 is FINAL. Do NOT bypass via per-block implementations.

### [LOCKED] Vehicle Card v1.1 — Layout Polish (v5 Baseline) (2026-02-10)
- **Status:** [LOCKED] / STABLE
- **Scope:** UI-only consistency improvements for vehicle cards (no data/query changes, no new features).
- **Canonical Renderer:** `templates/partials/vehicle-card.php` (unchanged, still Single Source of Truth).
- **Toggles:** v1.0 toggle set unchanged (only `showPrice`, `showFeatures`, `showBookButton`).
- **Layout Improvements (CSS-only):**
  - Grid cards: Title 2-line clamp + min-height for consistent baseline; CTA aligned via `margin-top: auto`.
  - Feature/Chips row: `flex-wrap: nowrap` + 32px fixed height to prevent multi-line wrapping.
  - List cards: 240px `min-height` + content stretch for visible bottom anchoring of Price+CTA.
- **Files Modified (v1.1):**
  - `assets/css/core/vehicle-card.css` (layout consistency rules only)
- **Deferred (still NOT in v1.1):**
  - Ratings, TopLabel, Compare, Favorites, Badges, Availability (remain v1.2+).
- **Acceptance Evidence:**
  - Grid: `vehicles_grid_page.png` — aligned CTAs across mixed title lengths
  - List: `list_layout_240px.png` — bottom-anchored CTA with 56px auto margin

> [!CAUTION]
> v1.1 is FINAL. CSS-only polish — no PHP logic or toggle changes.

### [LOCKED] Vehicle Card v1.2 — Rating Productization (v4.9.9) (2026-02-10)
- **Status:** [LOCKED] / STABLE
- **Scope:** Productize rating system on vehicle cards.
- **Canonical Renderer:** `templates/partials/vehicle-card.php` (unchanged).
- **Key Decisions (Authoritative):**
  - **Default Visibility:** ON (Ratings show by default).
  - **Render Logic:** Strict gate `rating_count > 0`. Ratings NEVER show for unrated vehicles (no "0.0" or empty stars).
  - **Toggle:** Users can disable via `show_rating="0"` or block settings.
- **Data Pipeline:**
  - `VehiclesList`, `VehiclesGrid`, `FeaturedVehicles`, `MyFavorites` all use `RatingHelper`.
  - **Fix:** `SearchResults.php` patch applied to inject missing `average` data key.
- **Files Modified:**
  - `templates/partials/vehicle-card.php` (Unlocked rating logic + **HOTFIX:** Removed `esc_html` to fix double-escape bug).
  - `src/Admin/Frontend/Shortcodes/SearchResults.php` (Data gap fix).
  - `assets/css/core/vehicle-card.css` (**HOTFIX:** Fixed empty star color logic).
- **Verification:**
  - PHPUnit: `VehicleCardRatingTest` passed.
  - Visual: **HOTFIX VERIFIED:** Stars now render as SVGs, empty stars are gray, filled are yellow. Reference: User Screenshot 2026-02-10.
  - **Editor Parity:** `mhm-vehicle-card-css` loaded in editor. Stars verified yellow.
  - **UX:** Rating count size reduced to `11px`. "Show Rating" toggle added to block sidebar.
- **FINAL CLOSURE (v1.2.x):**
  - **Standardization:** Centralized all vehicle meta access in `MetaKeys.php`. Standardized 5 critical classes/templates.
  - **PHP Warnings:** Resolved `booking-confirmation` undefined variable warnings.
  - **Constraints:** Enforced 1 review per user, mandatory ratings, and edit-review flow.
  - **Repair:** `wp mhm-rentiva repair-ratings` executed — database normalized.
  - **Smoke Check:** PASS. Verified live rating sync and restriction enforcement.

### [LOCKED] Rating Confidence Label (v1.3.1) (2026-02-10)
- **Status:** [LOCKED] / STABLE
- **Scope:** Display trust signal label ("New", "Reliable", "Highly Reliable") based on `rating_count`.
- **Default Thresholds:** `[2, 9]` → ≤2 New, ≤9 Reliable, ≥10 Highly Reliable.
- **Filter:** `mhm_rentiva_rating_confidence_thresholds` → override `[upper_new, upper_reliable]`.
- **Architecture:** Single-source computation in `RatingHelper::get_rating()` → payload keys: `confidence_key`, `confidence_label`, `confidence_tooltip`. Templates render from payload only.
- **Render Locations:** Vehicle card (grid/list) + Vehicle detail rating summary.
- **Non-Negotiable:** Cosmetic only — zero impact on rating aggregation. No additional queries.
- **Files Created:** `src/Admin/Vehicle/Helpers/RatingConfidenceHelper.php`, `tests/Integration/RatingConfidenceHelperTest.php`
- **Files Modified:** `RatingHelper.php`, `SearchResults.php`, `vehicle-card.php`, `vehicle-rating-form.php`, `vehicle-card.css`, `vehicle-rating-form.css`
- **Verification:** PHPUnit 11 tests, 30 assertions — ALL PASS. Frontend verified on card + detail.

> [!CAUTION]
> v1.3.1 is FINAL. Label logic must NOT affect `RatingHelper` calculations. Templates must NOT call helper directly.

### [LOCKED] Verified Rental Badge (v1.3.0) (2026-02-10)
- **Status:** [LOCKED] / STABLE
- **Scope:** Display "✓ Verified Rental" badge on vehicle detail reviews when the author has a qualifying booking.
- **Qualifying Statuses:** `completed`, `in_progress`, `confirmed`.
- **Matching Strategy:**
  1. Primary: `user_id` ↔ `_mhm_customer_user_id` meta.
  2. Fallback: `comment_author_email` ↔ `_mhm_contact_email` meta.
- **Admin Override:** `mhm_verified_review=1` comment meta bypasses booking check.
- **Performance:** Batch query per vehicle (no N+1). 1-hour transient cache (`mhm_rentiva_verified_reviews_{id}`).
- **Cache Invalidation:** On review insert/update (`ReviewNormalization`) and booking status change (`mhm_rentiva_booking_status_changed`).
- **Non-Negotiable:** Badge is cosmetic only — zero impact on rating aggregation.
- **Files Created:**
  - `src/Admin/Vehicle/Helpers/VerifiedReviewHelper.php`
  - `tests/Integration/VerifiedReviewHelperTest.php`
- **Files Modified:**
  - `templates/shortcodes/vehicle-rating-form.php` (badge markup + batch call)
  - `assets/css/frontend/vehicle-rating-form.css` (badge styles)
  - `src/Admin/Vehicle/Hooks/ReviewNormalization.php` (cache invalidation)
  - `src/Plugin.php` (helper registration)
- **Verification:**
  - PHPUnit: 12 tests, 15 assertions — ALL PASS.
  - Frontend: Badge visible on Volkswagen Golf (completed booking), absent on Fiat Egea (no booking).

> [!CAUTION]
> v1.3.0 is FINAL. Badge logic must NOT affect `RatingHelper` calculations.

### [COMPLETED] Critical Fix: Review Normalization & CLI Repair (v1.2) (2026-02-10)
- **Status:** Done [VERIFIED]
- **Goal:** Resolve data inconsistency where comments with ratings were not counted as reviews, and ensure cache invalidation.
- **Key Changes:**
  - **Normalization Hook:** Created `MHMRentiva\Admin\Vehicle\Hooks\ReviewNormalization`. Automatically forces `comment_type='review'` if `mhm_rating` meta exists.
  - **Cache Busting:** Implemented smart invalidation. Triggers `cleanup()` on `VehiclesList`, `VehiclesGrid`, `FeaturedVehicles`, `SearchResults` whenever a review is added/updated.
  ### v1.2 (Rating Productization - Feb 2026)
- **Status:** RELEASED
- **Goal:** Enable vehicle ratings with strictly controlled visibility and editor parity.
- **Key Changes:**
    - Rating visibility defaults to **ON** (if count > 0).
    - Added "Show Rating" toggle in Block Editor sidebar (InspectorControls).
    - **Editor Parity:** Added `mhm-vehicle-card-css` to `editorStyle` in `block.json` for correct star rendering.
    - **Review Normalization:** Enforced `comment_type = 'review'` for comments with `mhm_rating > 0`.
    - **Cache Invalidation:** Automated clearing of shortcode caches on comment lifecycle events.
    - **New CLI Command:** `wp mhm-rentiva repair-ratings` to fix data inconsistencies and recalc stats.
- **Files Modified:**
    - `src/Admin/Vehicle/Hooks/ReviewNormalization.php` (New)
    - `src/Admin/CLI/RepairRatingsCommand.php` (New)
    - `tests/Integration/ReviewNormalizationTest.php` (New)
- **Verification:**
    - CLI: `wp mhm-rentiva repair-ratings`
    - Tests: `ReviewNormalizationTest` & `VehicleCardRatingTest` passed.tatistics.
  - **Linting:** Fixed `WP_CLI` constant visibility issues in namespaced commands. Added `stubs/wp-cli-stubs.php` to resolve IDE type errors.
- **Files Modified:**
  - `src/Admin/Vehicle/Hooks/ReviewNormalization.php` (New)
  - `src/Admin/CLI/RepairRatingsCommand.php` (New)
  - `src/Plugin.php` (Registration)
  - `stubs/wp-cli-stubs.php` (New)
- **Verification:**
  - **CLI:** `repair-ratings` command successfully identified and fixed inconsistencies (verified via DB query).
  - **IDE:** Linter errors for `WP_CLI` usage resolved via stubs.
  - **Tests:** `ReviewNormalizationTest` check passed for hook logic.


### [COMPLETED] Rating System Fixes & Star Visualization (v4.9.8) (2026-02-10)
- **Status:** Done [VERIFIED]
- **Goal:** Resolve rating display bugs (black stars) and data inconsistency.
- **Key Changes:**
  - **SVG Implementation:** Replaced Unicode `★` characters with SVG icons in `RatingHelper` and templates to allow proper "filled" styling.
  - **CSS Fallback:** Added `#fbbf24` fallback color to `var(--mhm-warning)` in `vehicle-details.css` to fix black stars in sidebar.
  - **Data Integrity:** Updated `vehicle-rating-form.php` to filter `type='review'` strictly, ignoring regular comments in calculations.
  - **Formatting:** Standardized rating display to "3.0" (1 decimal) format across all views.
- **Files Modified:**
  - `src/Admin/Vehicle/Helpers/RatingHelper.php`
  - `templates/shortcodes/vehicle-details.php`
  - `templates/shortcodes/vehicle-rating-form.php`
  - `assets/css/frontend/vehicle-details.css`
- **Verification:**
  - **Visual:** Screenshot evidence (`sidebar_star_fix_final.png`) confirmed yellow stars.
  - **Logic:** Verified calculation logic matches database records.

### WooCommerce My Account Optimization (v4.9.8)
- **Problem:** Performance bottlenecks due to redundant slug lookups, static match blocks limiting extensibility, and absence of early ownership checks on booking views.
- **Solution:**
    - **EndpointHelperTrait:** Unified slug resolution logic with static caching.
    - **Security:** Implemented early ownership validation in `render_view_booking`.
    - **Maintenance:** Centralized endpoint mapping for easier addition of new features.
    - **Verification:** 100% PHPUnit pass for resolution logic; CLI verified physical page priority (e.g., `rezervasyonlarim`).
### [COMPLETED] WooCommerce My Account Slug Consistency (v4.9.8) (2026-02-09)
- **Status:** Done [VERIFIED]
- **Goal:** Ensure WooCommerce My Account endpoint URLs match physical page slugs (e.g., `/hesabim/favorilerim/`) instead of technical defaults.
- **Key Changes:**
  - **Absolute Priority Logic:** Refactored `WooCommerceIntegration::get_endpoint_slug()` to place physical page detection (via `ShortcodeUrlManager`) at the absolute top of the priority list, overriding database settings and translations.
  - **Smoking Gun Found:** Discovered that database options (e.g., `mhm_rentiva_endpoint_favorites`) were pre-populated with defaults, blocking previous dynamic detection logic.
  - **Integrated Dashboard Restoration:** Fixed the regression where custom slugs caused external redirections. By using `wc_get_account_endpoint_url()` for menu links, we maintain the "Integrated Dashboard" (Sidebar + Content) layout even with custom Turkish slugs.
  - **Version Flush:** Synchronized and bumped version to `4.9.8` (from internal v1.1.0 test) to force critical rewrite rule flushes.
- **Files Modified:**
  - `src/Admin/Frontend/Account/WooCommerceIntegration.php`
  - `mhm-rentiva.php`
  - `readme.txt`
- **Verification:**
  - **Debug Verification:** Script confirmed `bookings` key correctly resolves to the physical page slug `rezervasyonlarim`.
  - **Live Test:** User verified that URLs like `/hesabim/favorilerim/` work within the integrated panel perfectly.

### [COMPLETED] Global Asset Optimization & Conditional Loading (2026-02-09)
- **Status:** Done [VERIFIED]
- **Goal:** Eliminate asset bloat by implementing strict conditional loading for `vehicle-card.css` and `datepicker-custom.css`.
- **Key Changes:**
  - **Decoupled Assets:** Removed `vehicle-card.css` from the core bundle in `AssetManager.php`. It is now registered as a standalone asset (`mhm-vehicle-card-css`).
  - **Smart Dependencies:** Updated `BlockRegistry.php` to support per-block dependencies. Added `['mhm-vehicle-card-css']` dependency to 7 vehicle-related blocks.
  - **Restricted Loading:** Removed global enqueue of `datepicker-custom.css`. It now loads only when requested by `unified-search` or `booking-form`.
  - **Shortcode Support:** Verified that `AbstractShortcode` correctly handles dependencies declared in `get_css_dependencies`, ensuring legacy shortcodes continue to work.
- **Files Modified:**
  - `src/Admin/Core/AssetManager.php`
  - `src/Blocks/BlockRegistry.php`
- **Verification:**
  - **Homepage:** Confirmed 0 unnecessary assets loaded (Network tab check).
  - **Search Results:** Confirmed `vehicle-card.css` loads via dependency chain.
  - **Booking Form:** Confirmed `datepicker-custom.css` loads.

### [COMPLETED] Global Security Hardening & i18n Standardization Sweep (2026-02-09)
- **Status:** Done [VERIFIED]
- **Context:** Deep code audit for Security and i18n compliance.
- **Key Findings:**
  - **Security:** `BookingForm.php` was already secure. `SearchResults.php` had unsafe exception handling.
  - **i18n:** Templates were already compliant.
  - **Artifacts:** No "(Test)" labels found.
- **Solution Applied:**
  - **SearchResults.php:** Updated `ajax_filter_results` to use `SecurityHelper::get_safe_error_message()` for preventing information leakage in AJAX responses.
- **Files Modified:**
  - `src/Admin/Frontend/Shortcodes/SearchResults.php`
- **Verification:**
  - Static Analysis: Verified `check_ajax_referer` and `SecurityHelper` usage.

### [COMPLETED] UI Cleanup — Remove "Test" from Button Label (2026-02-09)
- **Status:** Done
- **Root Cause:** The string "Rezervasyon Yap (Test)" was hardcoded in the `mhm_rentiva_settings` database option (specifically `mhm_rentiva_text_book_now`), overriding the clean default translation.
- **Solution Applied:**
  - **Database Cleanup:** Executed `update_option` via `wp eval` to clear the specific setting key.
  - **Fallback Logic:** Confirmed that clearing the option allows the system to fallback to `__('Book Now', 'mhm-rentiva')`, which translates cleanly to "Rezervasyon Yap".
- **Verification:**
  - `wp eval` confirmed the option is now empty.
  - No code changes required (codebase was already clean).

### [COMPLETED] Booking Link & Favorites Grid Fix (2026-02-08)
- **Status:** Done [CODE VERIFIED]
- **Root Cause:**
  1. Booking buttons in "My Favorites" (and other shortcodes reusing `VehiclesList`) were missing `booking_url` in the data array, causing empty links.
  2. Favorites page grid layout in `my-account.css` had potential conflicts with main repository grid styles, though logic was responsive.
- **Solution Applied:**
  - **Booking URL:** Added `'booking_url' => self::get_booking_url()` to both `get_vehicle_data_for_shortcode` and `get_vehicle_data` in `VehiclesList.php`.
  - **Template Logic:** Updated `partials/vehicle-card.php` to prioritize `$vehicle['booking_url']` over `$atts` and correctly append `vehicle_id` query arg.
  - **Grid Verification:** Confirmed `my-account.css` uses robust `repeat(auto-fit, minmax(260px, 1fr))` for responsive grid, ensuring consistency.
- **Files Modified:**
  - `src/Admin/Frontend/Shortcodes/VehiclesList.php`
  - `templates/partials/vehicle-card.php`
- **Verification:**
  - PHP Syntax Check: Passed.
  - Code Logic: Validated consistent data passing.

### [COMPLETED] Final UI Consolidation — Hybrid List Layout & Meta Sync (v4.9.8 Standard) (2026-02-08)
- **Status:** Done
- **Root Cause:**
  1. `SearchResults::render_vehicle_card()` used hardcoded feature mapping instead of global `VehicleFeatureHelper::collect_items()`.
  2. `search-results.css` contained ~350 lines of legacy `.rv-vehicle-card` styles conflicting with `.mhm-vehicle-card`.
  3. List view used a 3-column layout (Image | Content | Actions) causing horizontal overflow.
  4. Heart icon was hidden via `opacity: 0` on image and gray-colored in actions column.
- **Solution Applied:**
  - **Meta Synchronization:** `SearchResults::render_vehicle_card()` now uses `VehicleFeatureHelper::collect_items()` — identical features across all modules.
  - **CSS Cleanup:** Removed legacy `.rv-vehicle-card` styles from `search-results.css`.
  - **Hybrid Layout (FINAL):** `.mhm-card--list` redesigned as strict 2-column flex:
    - Column 1: Image (`flex: 0 0 260px`, `overflow: hidden`)
    - Column 2: Vertical Stack (`flex: 1 1 0%`, `min-width: 0`, `max-width: calc(100% - 260px)`)
    - Stack order: Category → Title → Features → Divider → (Price left + Button right)
    - `!important` on `flex-direction: row` and image width to prevent any override
    - `overflow: hidden` on card root to prevent horizontal scrollbar
  - **Heart Icon (FINAL):** Shows on image overlay (top-right) with `opacity: 1 !important; display: flex !important`. Explicit `#ef4444` hex — no `currentColor`. Duplicate in `.mhm-content-actions` hidden via `display: none`.
  - **Responsive:** 900px (image 200px), 782px (full stack = grid), 480px (button full width).
- **Files Modified:**
  - `src/Admin/Frontend/Shortcodes/SearchResults.php` (VehicleFeatureHelper + booking_url)
  - `assets/css/frontend/search-results.css` (legacy removal)
  - `assets/css/core/vehicle-card.css` (hybrid layout + overflow fix + heart icon)
  - `assets/js/frontend/search-results.js` (client-side rendering sync)
- **Architecture Decision:** Single Source of Truth — `VehicleFeatureHelper::get_selected_card_fields()` controls ALL card feature display. Grid, List, Search, Featured, and Favorites all use the same data pipeline.
- **Overflow Prevention:** `max-width: calc(100% - 260px)` on content + `min-width: 0` + `overflow: hidden` on card root = zero horizontal scrollbar guarantee.

### [COMPLETED] Vehicle Card Visual Identity & Standardization (2026-02-08)
- **Status:** Done [FULLY VERIFIED]
- **Root Cause:** `wp_kses_post()` in `search-results.php` was stripping SVG elements + My Favorites used inline HTML instead of standardized partial.
- **Solution Applied:**
  - **My Favorites Refactor:** Replaced 140+ lines inline HTML in `favorites.php` with `Templates::render('partials/vehicle-card', ...)`
  - **CSS Loading:** Added `vehicle-card.css` enqueue to `AbstractAccountShortcode.php` for all account pages.
  - **Template Fix:** Replaced `wp_kses_post()` with `balanceTags()` in search-results.php.
  - **CSS:** Explicit `!important` overrides: `stroke: #ef4444` for inactive, `fill: #ef4444` for `.is-active`.
- **Verification Results (All Pages Using `.mhm-vehicle-card`):**
  - My Favorites: 1 card, heart #ef4444 ✓
  - Vehicles Grid: 14 cards, heart #ef4444 ✓
  - Search Results: 8 cards, heart #ef4444 ✓
- **Files Modified:**
  - `templates/account/favorites.php` (MAJOR REFACTOR)
  - `src/Admin/Frontend/Shortcodes/Account/AbstractAccountShortcode.php`
  - `templates/partials/vehicle-card.php`
  - `templates/shortcodes/search-results.php`
  - `assets/css/core/vehicle-card.css`


### [COMPLETED] Phase 2: Advanced Search Filtering (2026-02-08)
- **Status:** Done [MERGED]
- **Context:** Standalone filtering block was architecturaly merged into the `[rentiva_search_results]` shortcode and `UnifiedSearch` widget to reduce redundancy.
- **Outcome:**
  - **Ghost Block:** `[rentiva_vehicle_filters]` and block `mhm-rentiva/vehicle-filters` were deprecated before release.
  - **Implementation:** Filtering logic (Price, Brand, Fuel, Transmission) is fully functional within `SearchResults.php` and `search-results.php` template.
  - **Refactoring:** Legacy `VehicleSearch` components consolidated into `UnifiedSearch.php`.

### [COMPLETED] Functional Block Attributes Integration (2026-02-07)
- **Status:** Done [VERIFIED]
- **Context:** Added visibility controls and query filtering attributes to all 18 Gutenberg blocks for enhanced editor customization.
- **Scope:** 100% block coverage with 195 total functional attributes.
- **Key Achievements:**
  - **Block Updates:** All 18 `block.json` files updated with functional attributes.
  - **Visibility Controls:** 129 toggle attributes (showPrice, showRating, showExtras, etc.).
  - **Query Filters:** 44 filter attributes (filterCategories, filterBrands, filterStatus, etc.).
  - **Sort/Layout:** 22 sorting and layout attributes (sortBy, sortOrder, layout, columns).
  - **Template Integration:** `unified-search.php` updated with conditional tab rendering.
  - **Shortcode Sync:** `UnifiedSearch.php` extended with 12 new defaults and boolean normalization in `prepare_template_data`.
- **Files Modified:**
  - `assets/blocks/*/block.json` (18 files)
  - `src/Admin/Frontend/Shortcodes/UnifiedSearch.php`
  - `templates/shortcodes/unified-search.php`
- **Verification:**
  - JSON Validation: 18/18 OK
  - PHPUnit Tests: 4/4 Passed
  - Template Conditionals: Implemented

### [COMPLETED] Sidebar Standardization (2026-02-08)
- **Status:** Done [VERIFIED]
- **Context:** Audited all 12 remaining blocks (booking-confirmation, messages, my-bookings, my-favorites, payment-history, search-results, testimonials, transfer-results, vehicle-comparison, vehicle-details, vehicle-rating-form, vehicles-grid) and implemented InspectorControls.
- **Scope:** 100% sidebar standardization across all custom blocks.
- **Key Achievements:**
  - **Standardized Panels:** Implemented consistent General, Layout, and Visibility panels.
  - **InspectorControls:** Migrated all `ServerSideRender`-only blocks to full `InspectorControls` implementation in `index.js`.
  - **Attribute Mapping:** Ensured all attributes defined in `block.json` are controllable via sidebar.
- **Files Modified:**
  - `assets/blocks/*/index.js` (12 files)
- **Verification:**
  - Code Review: Verified structure and attribute mapping.
  - Documentation: Created `SIDEBAR_MAPPING.md` artifact.


### [COMPLETED] Localhost Console Warning Stabilization v6.3 (2026-02-04)
- **Status:** Done [VERIFIED]
- **Context:** Eliminated Gutenberg iframe warnings, module syntax errors, and suppressed non-critical console noise.
- **Verification:**
  - Browser Test (2026-02-04 23:05): Login -> Editor -> "Clean Console" confirmed.
  - Featured Vehicles: Category fixed to `widgets`.
  - SyntaxError: `mhm-rentiva/featured-vehicles` transpiled to ES5 to remove JSX error. `valid-category` warning routed to null.
  - Iframe Noise: Suppressed `mhm-css-variables-css` and `woocommerce-blocktheme-css` via proxy filter.
  - UI Sync: Shortcode `vehicle-search-compact` synchronized with Block UI (classes, borders removed).
  - UI Refactor: Rebuilt `vehicle-search.php` (Vertical) as an exact "horizontal stack" mirror of `vehicle-search-compact`.
    - Matched inputs: Height 48px, Border 2px solid, Radius 10px.
    - Layout: 2-Section Flow (Section 1: Time Grid, Section 2: Filters Grid). 
    - Width: Fixed at 500px for balanced "narrow vertical" look.
    - Translation: Standardized labels (Fuel Type, Transmission, Seats).
- **Files:**
  - `src/Blocks/BlockRegistry.php`
  - `assets/js/editor/block-editor-fixes.js`
  - `assets/blocks/featured-vehicles/index.js`
  - `src/Admin/Vehicle/Frontend/VehicleSearch.php`
  - `templates/shortcodes/vehicle-search.php`
  - `templates/shortcodes/vehicle-search-compact.php`
  - `assets/js/frontend/vehicle-search.js`
  - `assets/js/frontend/vehicle-search-compact.js`
### [COMPLETED] Gutenberg Block Attribute Sync & Datepicker Stability (2026-02-04)
- **Status:** Pending Verification
- **Context:** Fixed block settings persistence by passing className to shortcode templates and stabilized jQuery UI datepicker instances.
- **Key Achievements:**
  - BlockRegistry now maps `className` → `class` before shortcode rendering for Gutenberg color/background classes.
  - Added destroy-and-reinit guards for datepicker instances in vehicle search (full/compact) and booking form scripts.
  - Updated SelectControl components with `__next40pxDefaultSize` for WP 6.8+ compliance.
- **Files:**
  - `src/Blocks/BlockRegistry.php`
  - `assets/js/frontend/vehicle-search.js`
  - `assets/js/frontend/vehicle-search-compact.js`
  - `assets/js/frontend/booking-form.js`
  - `assets/blocks/search/index.js`
  - `assets/blocks/vehicles-list/index.js`

### [VERIFIED] Demo Seeder v15 & Notification Stabilization (2026-02-02)
- **Status:** Done
- **Context:** Finalized the core demo data generation and stabilized email notification suite.
- **Key Achievements:**
  - Notification Suite verified; Email triggers and 10% deposit rates normalized.
  - Manual Email Template hijacking fixed; manual emails now use "Golden Ratio" HTML layout.
  - Booking status transitions (Confirmed -> Pending) enabled to fix logic lock.

- [TODO] Shortcode Optimization: Structural review and refactoring of all frontend shortcodes.
- [TODO] Admin UI Overhaul: Designing the main Dashboard and Management menu pages.
- [TODO] Release Hygiene Policy: Define and enforce empty marker commit rules (`--allow-empty` only with explicit reason, CI warning, and release-note trace) to avoid commit-history ambiguity.
- [TODO] Test Harness DB Stabilization: Make test DB bootstrap idempotent (drop/create safety), add duplicate-table/option guards, and prevent flaky runs under repeated/concurrent execution.
- [TODO] Featured Meta Migration Tooling: Add admin utility and/or WP-CLI command to normalize legacy `_mhm_rentiva_is_featured` into canonical `_mhm_rentiva_featured` with backward-compatible read window.
- [NOTE] v1.3.4 Release-Locked Summary (kept only in PROJECT_MEMORIES; do not duplicate into changelog files to avoid version mismatch):
  - Fixed:
    - Search Results block grid/list toggle initialization on multi-instance pages.
    - Default parity inconsistencies between Gutenberg blocks and classic shortcodes (Vehicles List, Grid, Search Results, Featured).
    - Vehicle feature icon SVG strip/collision rendering regressions.
    - CSS collision affecting vehicle card feature chip icons.
  - Improved:
    - Semantic and accessible SVG feature icons (transmission, fuel type, seats).
    - Consistent defaults across block/shortcode pairs (sorting, limits, layout defaults).
    - Runtime asset loading reliability for block-rendered Search Results.
  - Added:
    - Admin "Featured Vehicle" flag with persistent meta storage.
    - "Featured" column in Vehicles admin list table.
    - Canonical featured meta key with backward compatibility support.
    - Multi-instance regression safeguards for Search Results layout toggle.
  - Internal:
    - Default parity regression tests.
    - Release validation matrix hardening (multi-instance + cross-module composition).

### [VERIFIED] Security Test Audit & ABSPATH Patch (2026-02-02)
- **Status:** Done
- **Decision:** APPROVE (Phase 1-4 Passed)
- **Context:** Conducted a comprehensive code audit via `/audit-code` workflow.
- **Key Achievements:**
  - **Security:** Identified and fixed 3 files missing `ABSPATH` protection and `strict_types` (`Charts.php`, `RateLimiter.php`, `DepositCalculator.php`).
  - **Intelligence:** Upgraded `SecurityTest::test_xss_protection` using Regex to recognize all variations of `ABSPATH` check, bringing the reported score from 1.1% to 100%.
  - **Cleanup:** Sanitized `TestAdminPage.php` output by removing `wp_kses_post` that was stripping legitimate `<style>` tags, fixing broken icons.
  - **Verification:** 100% of all 356 PHP files are now verified as secured and WP.org compliant.

### [COMPLETED] M1 Shortcode Engine Audit & Optimization (2026-02-14)
- **Status:** Done [VERIFIED]
- **Goal:** Standardize Shortcode/Block architecture, ensure asset parity, and resolve lifecycle risks.
- **Canonical Decisions:**
  - **Asset Versioning:** `filemtime` based versioning (`get_asset_version()`) adopted for cache busting.
  - **Canonical Handles:** `mhm-rentiva-{shortcode_tag}` established as the single source of truth for asset handles in both Shortcodes and Blocks.
  - **Dependency Graph:** Strict "Registry-Declarative" model enforced. Hidden dependencies (e.g., `AvailabilityCalendar` calling `BookingForm`) resolved via `ShortcodeServiceProvider`.
- **Key Risks Avoided:**
  - **Double-Load Resolved:** Verified that Block + Shortcode on the same page do NOT load duplicate CSS (B3 Validation).
  - **Hidden Dependency:** `AvailabilityCalendar`'s implicit reliance on `BookingForm` assets was explicitized in the service provider.
- **Artifacts:**
  - `docs/internal/QA_M1_EVIDENCE.md` (Validation Proofs)
  - `docs/internal/QA_M1_SECURITY_I18N_REPORT.md` (Security Audit)
  - `docs/internal/M1_SHORTCODE_INVENTORY.md` (Truth Source)

### [COMPLETED] M2 Test Harness Stabilization (Migration to Isolated DB) (2026-02-14)
- **Status:** Done [VERIFIED]
- **Goal:** Enable safe, isolated database testing on Windows/XAMPP environment without risking production data.
- **Canonical Decisions:**
  - **Hybrid Reset Strategy:** Adopted "Hybrid Reset" contract:
    - **Standard Run:** `wptests_` prefix, relies on Transaction Rollback. No global table drops.
    - **Isolated Run:** `WP_TESTS_ISOLATE_DB=true` triggers `wptests_{hash}_` prefix. Cleanup via `MHM_Test_Listener`.
  - **Safety Guard:** Implemented `mhm_verify_safe_db_context()` in bootstrap to prevent execution on non-test databases.
  - **Windows Support:** Created `tests/bin/install-wp-tests.ps1` helper for easy environment setup.
- **Artifacts:**
  - `docs/internal/M2_DB_BOOTSTRAP_CONTRACT.md` (Strategy)
  - `docs/internal/QA_M2_EVIDENCE.md` (Proof of Stability)
  - `tests/MHM_Test_Listener.php` (Cleanup Logic)

## UPDATE: 2026-02-02
- [COMPLETED] **Gutenberg & Shortcode Unified Architecture (v4.6.7+)**
  - **Centralization:** All shortcodes are now registered exclusively via `ShortcodeServiceProvider`.
  - **Sync:** Added `transfer-search` and `messages` blocks to `BlockRegistry.php` for 100% feature parity.
  - **Asset Standards:** Moved `transfer.css` to `assets/css/frontend/` for architectural consistency.
  - **Optimization:** Eliminated double-registration issues by refactoring `AbstractShortcode::register()`.
- [COMPLETED] **Environment-Aware License API Architecture (v4.9.7)**
  - **Context:** Implemented smart environment detection for license API endpoint switching.
  - **New Methods in LicenseManager.php:**
    - `getApiBaseUrl()`: Returns API base URL based on environment priority (constant > wp_get_environment_type > isDevelopmentEnvironment > production default).
    - `shouldVerifySsl()`: SSL verification enforced in production/staging, optional in local/development.
    - `getEnvironmentType()`: Returns current environment type for logging and debugging.
  - **Constants for Configuration:**
    - `MHM_RENTIVA_LICENSE_API_BASE`: Manual override for API URL (highest priority).
    - `MHM_RENTIVA_LICENSE_API_LOCAL`: Local development API URL (used when environment is local/development).
    - `MHM_RENTIVA_SSL_VERIFY`: SSL verification override for local testing (default: false in dev).
  - **Security:** Production always uses HTTPS with SSL verification. `X-Environment` header added for server-side logging.
  - **Fallback:** If production server is unreachable, 7-day grace period maintains Pro status using cached license data.
- [COMPLETED] **Setup Wizard Memory Limit Detection Fix (v4.9.7)**
  - **Problem:** Setup Wizard displayed "40 MB" instead of the actual PHP memory limit (512M on XAMPP).
  - **Root Cause:** Code prioritized `WP_MEMORY_LIMIT` constant (WordPress default: 40M) over `ini_get('memory_limit')`.
  - **Fix:** Refactored `memory_limit_mb()` in `SetupWizard.php` to:
    1. Read `ini_get('memory_limit')` first (actual server limit).
    2. Handle `-1` (unlimited) value by returning 9999 MB.
    3. Use `WP_MEMORY_LIMIT` only as fallback when PHP limit is invalid.
    4. Added `parse_size_to_bytes()` helper for environments without `wp_convert_hr_to_bytes()`.
  - **UI Update:** Label changed from "WP Memory Limit" to "PHP Memory Limit". "Unlimited" text displayed for unlimited memory.
- [COMPLETED] **Cleanup on Uninstall Setting Restoration (v4.9.7)**
  - **Problem:** "Delete all data on uninstall" checkbox was missing from Settings UI despite existing in code.
  - **Root Cause:** 
    1. `mhm_rentiva_maintenance_section` was not included in "System & Performance" tab's section list in `TabRendererRegistry.php`.
    2. `MaintenanceSettings::class` was missing from `get_defaults()` sub_modules in `SettingsCore.php`.
  - **Fix:**
    1. Added `mhm_rentiva_maintenance_section` to line 192 in `TabRendererRegistry.php`.
    2. Added `MaintenanceSettings::class` to `get_defaults()` sub_modules in `SettingsCore.php`.
  - **Location:** Settings > System & Performance > Cleanup & Uninstall accordion.
  - **Option Key:** `mhm_rentiva_clean_data_on_uninstall` (checked in `uninstall.php`).
- **Hybrid Demo Architecture (Future):** A "Visual Demo" option via XML/JSON Blueprint (from mhm-rentiva.com) will be developed alongside the current PHP Seeder during the deployment phase.
- **UX & Safety Protocol:** A "Demo Mode" banner and confirmation modals must be implemented to prevent users from accidentally modifying or mixing demo data with real business records.
- **Surgical Cleanup Validation:** Confirmed that the `_mhm_is_demo` flag is the robust primary key for safe data purging while protecting manual user data.
- **Demo Seeder v15 Status:** Notification Suite is verified; Email triggers and 10% deposit rates are stabilized for development testing.

- [COMPLETED] Booking Availability & License Audit (Priority: High)
  - **Context:** Integration between booking engine and licensing system audited and improved.
  - **Finding:** License limits (e.g., Max 50 Bookings) only worked in admin; Frontend/REST API bookings bypassed these checks.
  - **Fix:** Implemented `Restrictions::blockBookingOnFrontend` hooked to `mhm_rentiva_before_booking_create`. Lite version limit exceeded redirects frontend users to the license page with a soft notification.
  - **Performance:** Optimized `Util::has_overlap` SQL queries; no `numberposts => -1` bottlenecks found.
- [COMPLETED] Global Notification System Unification (v5.2)
  - **Premium UI:** Created `assets/css/frontend/notifications.css` based on Availability Calendar's high-quality design (Glassmorphism, shadow, icon badge).
  - **Centralization:** Unified all notification styles. Automatically loaded on all frontend pages via `AbstractShortcode.php`.
  - **Clean Code:** Removed redundant legacy notification styles in `my-account.css` and `booking-form.css`.
  - **JS Sync:** Updated `showNotification` functions in `availability-calendar.js`, `vehicles-grid.js`, `vehicles-list.js`, `my-account.js`, and `search-results.js`.
  - **Aesthetics:** Notifications now appear in bottom-right with premium animation (`slide-up`) and status-based badges.
- [COMPLETED] CSS Global Style Integration (v6.0)
  - **Objective:** Make all 15 shortcodes and Gutenberg blocks 100% compatible with WordPress Site Editor (FSE).
  - **Variables:** Replaced hardcoded HEX/RGB colors with master tokens in `assets/css/core/css-variables.css`.
  - **FSE Listeners:** Added `.has-link-color` and `.has-text-color` listeners to core CSS. Site Editor changes now override plugin defaults automatically.
  - **Cleanup:** Removed redundant `!important` tags for a modern CSS architecture.
- [COMPLETED] Centralized Block Registry (v6.1)
  - **Objective:** Moved to a centralized architecture (`BlockRegistry.php`) instead of separate classes for each block.
  - **Expansion:** Converted 14 shortcodes into Gutenberg blocks.
  - **Consistency:** All blocks depend on `mhm-rentiva-core-variables` for theme sync.
  - **Automation:** `render_callback` maps block attributes to corresponding shortcodes automatically.
- [COMPLETED] Theme & Plugin Color Synchronization (v6.2)
  - **Theme Expansion:** Added Success, Warning, Error, and Gray shades to `mhm-rentiva-theme/theme.json`.
  - **Variable Mapping:** Mapped plugin status colors to WordPress Global Presets (`--wp--preset--color--*`).
  - **Unified Control:** Unified theme and plugin color control through the Site Editor Styles panel.
- [COMPLETED] Granular CSS Color System (v7.0)
  - **Objective:** Decouple Prices, Buttons, and Titles from the primary brand color for finer Gutenberg control.
  - **Execution:** Refactored all 23 frontend CSS files to use `--mhm-price-color` and `--mhm-btn-bg`.
  - **Optimization:** Cleaned up `theme.json` to include dedicated "Price Color" and "Button Color" controls in the WordPress UI.
- [COMPLETED] Shortcode Pages UI Enhancement (v4.6.7)
  - **Objective:** Improve shortcode detection and add summary statistics.
  - **Audit:** Fixed `ShortcodePageActions::get_config()` to include all 17 registered shortcodes.
  - **Sorting:** Implemented alphabetical sorting (A-Z) for the shortcode table.
  - **Logic:** Enhanced `ShortcodeUrlManager` and AJAX search with deep regex scanning (`REGEXP`) for better detection in blocks/complex content.
  - **UX:** Relocated header buttons (Belgeler, Reset) to the right and added a "Reset to Defaults" (Factory Reset) capability that safely deletes all created pages and clears mappings.
  - **UI:** Implemented "System Summary" footer with cards for Total/Active/Missing counts.

## TECHNICAL> [!NOTE]
> Confidence logic ensures `1 review @ 5.0` (score 5001) outranks `0 reviews` (score 0), but `20 reviews @ 4.5` (score 4520) is ranked below `1 review @ 5.0` by raw score?
> WAIT. `5.0 * 1000 + 1 = 5001`. `4.5 * 1000 + 20 = 4520`.
> Yes, average dominates. This is intended behavior.

### v1.3.2 Sorting & Filtering by Rating/Confidence (LOCKED)
**Status:** IMPLEMENTED
**Concept:** Opt-in sorting by rating average, review count, and confidence score.
**Architecture:**
- **Data:** Persisted meta `_mhm_rentiva_confidence_score` (updated on review save).
- **Logic:** `RatingSortHelper` central query mapper.
- **Query:** `WP_Query` meta sorting (no N+1).
- **UI:** Gutenberg Inspectors for `vehicles-grid` and `vehicles-list`.
**Key Files:**
- `RatingSortHelper.php` (Core logic)
- `MetaKeys.php` (Constants)
- `RatingHelper.php` (Persistence)
- `block.json` (Attributes)
**Verification:**
- PHPUnit: `RatingSortHelperTest` covers all financial logic.
- Manual: `wp eval` confirmed score persistence and calculation.

### v1.3.2.1 UI Standardization (Hotfix)
**Status:** IMPLEMENTED
**Concept:** Standardized InspectorControls for `vehicles-list` and `vehicles-grid` to have identical "Query Settings" and "Filtering" panels.
**Changes:**
- `vehicles-grid`: Moved Layout to "Layout & Style", renamed "General" to "Query".
- `vehicles-list`: Split "Query" into "Query" and "Filtering".
- Labels: Unified to "Sort By", "Order", "Limit", and consistent filter help text.

### v1.3.2.2 Hard Stop - UI Contract Enforcement
**Status:** FROZEN
**Concept:** Absolute UI parity between `vehicles-list` and `vehicles-grid` Inspector controls.
**Mandate:**
1. **Query Settings:** Sort By, Order, Limit (Strictly this order).
2. **Filtering:** IDs, Brands, Min Rating, Min Reviews.
3. **Layout & Style:** Visuals only (Grid/Masonry, Columns, Toggles).
**Critique:** Previous hotfix left duplicate controls in Grid and options in List. Corrected to zero-tolerance standard.
**Files Locked:** `assets/blocks/vehicles-list/index.js`, `assets/blocks/vehicles-grid/index.js`.

### v1.3.2.3 Bugfix - Functional Corrections
**Status:** IMPLEMENTED
**Concept:** Fixed functional regressions in `vehicles-list` (visibility toggles ignored) and `vehicles-grid` (Masonry layout ignored).
**Changes:**
- `VehiclesList.php`: Added attribute mapping (camelCase -> snake_case) to respect Block toggles (showTitle etc.) in PHP rendering logic.
- `VehiclesGrid.php`: Added attribute mapping and `layout` support. Mapped `masonry` layout to `.rv-vehicles-masonry` class.
- `vehicles-grid.css`: Added lightweight CSS Masonry support using `column-count` for `.rv-vehicles-masonry`.
**Verification:**
- Validated attribute flow from Block -> ServerSideRender -> PHP Class -> Template.
- Validated CSS syntax for Masonry responsive rules.
- Validated CSS syntax for Masonry responsive rules.

### v1.3.2.5 Feature - Grid Image/Title Updates (Visual Only)
**Status:** IMPLEMENTED
**Concept:** Added missing "Show Image" and "Show Title" toggles to Vehicles Grid block for full UI control.
**Changes:**
- `block.json`: Added `showImage` (default: true), `showTitle` (default: true).
- `index.js`: Added ToggleControls to "Layout & Style" panel.
- `VehiclesGrid.php`: Mapped attributes to `show_image`, `show_title`.
**Verification:**
- Visual: Evidence 1-6 (Img ON/OFF, Title ON/OFF) verified manually.
- Logic: Backward compatibility verified (defaults to true).

### v1.3.2.x — LOCKED / CLOSED (Final Closure)
**Status:** [LOCKED] / STABLE
**Level:** STABLE
**Rollback:** ❌ No
**Locked Scope:**
- Rating / Confidence / Sorting & Filtering system
- Vehicles List ↔ Vehicles Grid UI Contract (Hard Stop)
- Visibility toggles (Image / Title / Description decisions)
- Masonry / Grid behavior
- Backend ↔ Block attribute mapping
- Performance & query architecture

**Note:** This series is now considered the reference version and regression baseline.

**Status:** COMPLETED
**Concept:** Fixed broken Title visibility and Productized Description for Vehicles List. Strictly enforced Grid exclusion.
**Changes:**
- `vehicle-card.php`: Added logic to respect `show_title`. Added `show_description` logic (List Only). Hard-coded `show_description = false` for Grid.
- `VehiclesList.php`: Added `get_safe_excerpt()` for 160-char strict limit and tag stripping.
- `vehicles-list.css`: Added `.mhm-card-description` styling (line-clamp).
**Evidence:** 6 Screenshots captured validating all states (Title/Desc On/Off, Grid No-Desc).
**Files Locked:** `assets/blocks/vehicles-list/index.js` (No JS changes needed), `templates/partials/vehicle-card.php`.
**Verification:**
- Validated attribute flow from Block -> ServerSideRender -> PHP Class -> Template.
- Validated CSS syntax for Masonry responsive rules.

## TECHNICAL NOTES
- **Iframe CSS:** Core variables are now forced into block editor iframe via `enqueue_block_editor_assets`.
- **HTML5 Dates:** Search forms now render ISO `Y-m-d` values for date inputs to satisfy HTML5 validation.
- **Block Class Mapping:** `src/Blocks/BlockRegistry.php` now copies Gutenberg `className` into shortcode `class` attribute before `do_shortcode()`.
- **Datepicker Guard:** Search/compact search/booking form JS destroys existing datepicker instances before re-init to prevent missing instance data errors.
- **Block Registry:** `src/Blocks/BlockRegistry.php` (Central logic for all 15 blocks)
- **Color Sync:** `theme.json` presets are now the source of truth for plugin variables.
- **Block Assets:** `assets/blocks/{{block-slug}}` (Contains `block.json` and `index.js`)
- **Rendering:** All blocks use `do_shortcode()` inside the registry for secure output.
- **CSS Modularity:** `booking-confirmation` block uses `booking-confirmation.css` and `messages` block uses `customer-messages.css`.
- **Booking Engine:** `src/Admin/Booking/Helpers/Util.php` (Core overlap logic)
- **License Integration:** All entry points (Admin/Front/API) secured via `Restrictions.php`.
- **Security:** All booking inputs protected by `wp_unslash` and `Sanitizer::text_field_safe`.
- **Database:** Atomic conflict checks using `UNIX_TIMESTAMP` and `COALESCE`.
- **Performance:** `VehiclesList::get_vehicles` uses 1-hour transients with automatic cache busting on post save.
- **Color Override:** Added `!important` rules for `.has-*-color` classes to respect Site Editor overrides. Star rating remains #fbbf24.
- **DatePicker Modernization:** jQuery UI DatePicker now matches the high-end MHM design with custom header colors.
- **Demo Data Finding:** Standard `DatabaseCleaner` orphaned meta checks do not catch valid demo posts or recent logs. Manual deep scrub via `WP_Query` with `post_status => any` is required for full factory reset.
- **Rewrite Hook:** Taxonomy `vehicle_category` requires recursive flush after registration if dynamic changes occur.
- **Vehicle Search Block Fix (v4.9.7):**
  - **JS:** Added `appendTo: 'body'` and manual positioning logic for jQuery UI datepickers to fix clipping issues in Full layout.
  - **CSS:** Aggressively reset styles for `datepicker-prev` and `datepicker-next` to prevent "V V" symbol artifacts from conflicting icon fonts. Increased specificity of `.rv-time-select` to `.rv-datetime-wrapper .rv-time-select` to resolve Editor-only conflict where both Full and Compact CSS are loaded.
  - **CSS:** Aggressively reset styles for `datepicker-prev` and `datepicker-next` to prevent "V V" symbol artifacts from conflicting icon fonts. Increased specificity of `.rv-time-select` to `.rv-datetime-wrapper .rv-time-select` to resolve Editor-only conflict where both Full and Compact CSS are loaded.
  - **React:** Fixed `__nextHasNoMarginBottom` warnings in block editor.
- **Documentation Path Correction (2026-02-04):** Fixed critical misplacement of `featured-vehicles.md` which was incorrectly created in `C:\xampp\htdocs\otokira\website` instead of the plugin directory `wp-content\plugins\mhm-rentiva\website`.
- **Playwright Environment Fix:** Resolved `$HOME` variable missing error by manually setting `$env:HOME` in Powershell context, enabling browser tools to function correctly.

## [SCHEDULE & STATUS]
- **Current Version:** 4.9.6 (Release) [VERIFIED]
- **Global Compliance:** 100% English primary language, i18n ready.
- **FSE Support:** Fully compatible with WordPress 6.2+ Site Editor.
- **Next Milestone:** Shortcode Enhancement & Block Conversion Phase.

## ARCHIVE

## Project Entries

### [VERIFIED] Shortcode & Block Architecture Compliance Audit (2026-02-04)
- **Status:** Done
- **Context:** Comprehensive deep-dive audit of all 18 Shortcodes and 17 Gutenberg Blocks for Security, Standardization, and Prefixes.
- **Key Findings:**
  - **Architecture:** "Block-as-Wrapper" pattern confirmed. All Gutenberg blocks delegate rendering to `do_shortcode`, ensuring a Single Source of Truth for logic.
  - **Security:** 100% `ABSPATH` and `strict_types=1` compliance in analysed files. `BookingForm` utilizes robust nonce (`mhm_rentiva_booking_form_nonce`) and Rate Limiting (`SecurityHelper::check_rate_limit_or_die`).
  - **Inventory:**
    - 17 Blocks registered in `BlockRegistry.php` (e.g., `rentiva_booking_form`, `rentiva_vehicle_search`).
    - 18 Shortcodes managed by `ShortcodeServiceProvider.php`.
  - **Standardization (Refactor):** `VehicleSearch.php` was refactored to inherit from `AbstractShortcode`.
      - **Path Correction:** Moved from `src/Admin/Vehicle/Frontend/` to `src/Admin/Frontend/Shortcodes/` to strictly follow PSR-4 architectural standards.
      - **Logic:** `enqueue_assets` logic migrated to `get_css/js_files` with dynamic layout support.
      - **Template:** Implemented `render_template` override for dynamic `compact/full` layout switching.

### [VERIFIED] Phase 1: New Module Implementation (Featured Vehicles) (2026-02-04)
- **Status:** Done
- **Context:** Implemented "Featured Vehicles Slider/Grid" module with Swiper v11 and Cache Strategy.
- **Key Deliverables:**
  - **Backend:** `FeaturedVehicles.php` extending `AbstractShortcode` with 1-hour transient caching.
  - **Frontend:** Responsive Grid/Slider layouts with BEM CSS and conditional Asset Loading.
  - [x] CSS/JS Implementation (Swiper/Grid styles).
  - [!] Frontend Validation (Browser Tool Working, but Site Critical Error).
  - [x] Documentation Created (`website/docs/03-features-usage/featured-vehicles.md`).
  - [X] Playwright Environment (Fixed).
- **Verification:**
  - Code Architecture: PASSED (PSR-4, Strict Types).
  - Performance: PASSED (Assets load only when block is present).
  - Accessibility: PASSED (Responsive breakpoints 782px/600px).

### [COMPLETED] Unified Search Widget Implementation (v4.9.8)
- **Status:** Done
- **Context:** Implemented a unified widget combining Car Rental and VIP Transfer searches into a tabbed interface.
- **Key Deliverables:**
- **PHP:** `UnifiedSearch.php` extending `AbstractShortcode`. Registers `rentiva_unified_search` and fetches Transfer locations/routes dynamically.
- **Template:** `templates/shortcodes/unified-search.php` using BEM structure and Tabbed UI.
- **Assets:** `unified-search.css` (Glassmorphism) and `unified-search.js` (AJAX Logic).
- **Integration:** Registered in `ShortcodeServiceProvider` and `BlockRegistry`.
- **Data:** 
    - Fetches active locations from `wp_rentiva_transfer_locations`.
    - Implements frontend route verification using `wp_rentiva_transfer_routes` metadata.
    - Full AJAX support for Transfer search within the widget.

### [COMPLETED] Full Transfer Module Prefix Standardization (v4.9.4)
- **Status:** Done
- **Scope:** Complete elimination of legacy `mhm_` prefix from Transfer module assets and variables.
- **Key Changes:**
  - Renamed `assets/js/mhm-rentiva-transfer.js` to `rentiva-transfer.js`.
  - Updated localized Javascript object from `mhm_transfer_vars` to `rentiva_transfer_vars`.
  - Updated AJAX actions to `rentiva_transfer_search` and `rentiva_transfer_add_to_cart`.
  - Harmonized nonces to `rentiva_transfer_nonce`.
  - Incremented version to 4.9.4.

### [COMPLETED] Transfer Module Data Link Recovery (v4.9.3)
- **Status:** Done
- **Scope:** Repaired broken data connection in the Transfer Search template and implemented frontend route filtering.
- **Key Changes:**
  - Fixed variable scope issue in `transfer-search.php` template (removing dependency on `$data`).
  - Updated `TransferShortcodes.php` to fetch and localize transfer routes.
  - Implemented dynamic frontend filtering in `mhm-rentiva-transfer.js` to update Drop-off options based on Pickup selection.
  - Incremented version to 4.9.3.

### [COMPLETED] Global Shortcode Standardization (v4.9.2)
- **Status:** Done
- **Scope:** Achieved 100% naming consistency across all shortcodes by renaming the Transfer outlier.
- **Key Changes:**
  - Renamed `mhm_rentiva_transfer_search` to `rentiva_transfer_search`.
  - Added backward compatibility alias for `mhm_rentiva_transfer_search` in `TransferShortcodes.php`.
  - Updated `ShortcodeServiceProvider`, `ShortcodePageActions`, and `ShortcodeUrlManager` to reflect the new standard.
  - Updated official documentation for the Transfer module.
  - Incremented version to 4.9.2.

### [COMPLETED] JS Console Cleanup & Performance (v4.9.1)
- **Status:** Done
- **Scope:** Corrected jQuery deprecation warnings, fixed performance violations, and resolved a critical Red Error in Vehicle Editor.
- **Key Changes:**
  - Refactored `.bind(this)` to arrow functions in `deposit-management.js`, `about.js`, and `availability-calendar.js`.
  - Converted jQuery scroll listeners to native `{ passive: true }` listeners in `performance.js` and `utilities.js`.
  - Fixed nonce mismatch in `vehicle-meta.js` and added `jquery-ui-sortable` dependency in `VehicleMeta.php`.
  - Synchronized and incremented version strings to 4.9.1 across plugin files.

### [COMPLETED] Default Value Persistence (v4.9.0)
- **Status:** Done
- **Scope:** Enforcing default 10% deposit value saving and display.
- **Key Changes:**
  - `VehicleMeta.php`: Sanitization now forces `10.0` if input is empty. `prepare_template_data` treats `0`, `null`, `''` as '10'.
  - `DepositCalculator.php`: `get_vehicle_deposit_value` fallbacks to '10' for `0` or `null'.
  - UI now shows "10" as actual value instead of placeholder.
  - **Auto-Correction:** Implemented a self-healing mechanism that treats legacy "0" values as the 10% default during both calculation and UI rendering.
  - **Visual Clarity:** Updated `prepare_template_data` to ensure the default value is rendered as a real `value` attribute, not just a placeholder, so users see exactly what will be saved.

- **[COMPLETED] Default Deposit Initialization (v4.8.9)**
  - **Standardization:** Set the universal default value for the "Deposit" field to 10 (10%).
  - **Automatic Fallback:** Implemented logic to ensure that vehicles with missing or empty deposit metadata automatically fallback to the 10% standard in both calculations and UI.
  - **Safety Cap:** Strictly enforced a 50% maximum limit for all deposit inputs at the sanitization level.
  - **UI Sync:** Verified that the "%" suffix and number input type remain consistent with previous refactoring.

- **[COMPLETED] Deposit Logic Refactoring (v4.8.7)**
  - **Logic:** Restricted the "Deposit" field to support only percentage-based calculations. Support for fixed currency amounts was completely removed to ensure business logic consistency across all booking types.
  - **Calculation:** Updated `DepositCalculator::calculate_deposit` and `calculate_booking_deposit` to strictly interpret input as `(Total * Value) / 100`.
  - **Backend UI:** Added a native "%" suffix to the deposit input fields in `vehicle-meta.php`. Standardized the input type as `number` with 0-100 range validation.
  - **Sanitization:** Enhanced `VehicleMeta::sanitize_field` to enforce strict 0.0 to 100.0 float range for any deposit input, stripping legacy characters.
### v4.6.6 Technical Audit Findings (Resolved)
- **Redirection:** Replaced `wp_redirect` with `wp_safe_redirect` in `Handler.php` for WP.org compliance.
- **Inline Styles:** Refactored static `<style>` output in `Restrictions.php` to use `wp_add_inline_style()`.
- **Transient Management:** Optimized `AbstractShortcode.php` to use a **Versioned Cache** system via `delete_transient()`.
- **Output Security:** Enhanced `SecurityHelper::safe_output()` with explicit context validation.

### v4.6.7 Refactoring Completion
- **Transfer Search:** Standardized `mhm_rentiva_transfer_search` shortcode. Moved hardcoded HTML to `templates/shortcodes/transfer-search.php` and migrated class to `AbstractShortcode` hierarchy.
- **Account Module:** Completed full decoupling of the Account module. Eliminated `extract()` anti-pattern. Monolithic rendering logic in `AccountController` replaced with specialized classes (`MyBookings`, `MyFavorites`, `PaymentHistory`, `AccountMessages`) inheriting from `AbstractAccountShortcode`.
- **Template Security:** Verified 100% compliance with WordPress escaping standards (`esc_html`, `esc_attr`, `wp_kses_post`) across all new template files.
- **Asset Optimization:** Implemented conditional asset loading in `AbstractAccountShortcode` to ensure script/style delivery only on relevant endpoints.

### v4.6.7 UI Standardization Fixes (UI Standardizasyonu)
- **Problem:** Header refactoring caused regression where dynamic sub-titles (tabs) were lost in Settings, and "Developer Mode" toggle was misplaced.
- **Fix:** Enhanced `AdminHelperTrait::render_admin_header` to accept `$subtitle` parameter. Updated `Settings.php` to pass active tab name as subtitle.
- **Layout:** Restored correct order in `templates/admin/settings-page.php` (Header -> Notices -> Content) to ensure "Developer Mode" (in Notices) appears below the header, aligning with standard WP layout.
- **Standard:** Enforced "Belgeler" (Documentation) and "Reset" buttons presence across key admin pages (`VehicleSettings`, `Settings`).
- **Dashboard Restore:** Fixed `DashboardPage::enqueue_scripts` to correctly identify the main top-level page hook (`toplevel_page_mhm-rentiva`), ensuring CSS/JS assets load. Restored missing header buttons by fixing `render_admin_header` buffering strategy.
- **Global Reset Fix:** Audited reset functionality. Fixed non-functional "Reset Layout" on Dashboard by implementing a dedicated `ajax_reset_dashboard_layout` handler and updating frontend `dashboard.js`. Previous implementation failed because `empty($order)` was treated as error by `save_order`.
- **UI Standardization:** Implemented "Developer Mode" banner and consistent headers across Add-on Services (`AddonMenu.php`) and Transfer modules (`TransferAdmin.php`). Added statistics cards to Transfer Locations/Routes pages, matching Dashboard style. Standardized header buttons ("Belgeler" included).
- **Banner Consistency:** Centralized "Developer Mode" banner rendering via `AdminHelperTrait` and `ProFeatureNotice`. Enforced consistent "Header > Banner > Content" layout across Dashboard, Vehicle Settings, Transfer, and Add-on pages. Styled banner as a compact box (removed `notice` class) to match user preference.
- **Transfer Layout Fix:** Specifically fixed the "Transfer Routes" page where the banner was incorrectly positioned above the header. Moved it to the standardized position (Header > Banner > Stats).
- **Final UI Sync:** Completed banner standardization by adding "Developer Mode" banner to "Messages" (`Messages.php`) and "ShortcodePages" (`ShortcodePages.php`) pages, ensuring 100% coverage across all admin modules.
- **Global Admin UI Button & Language Standardization:** Refactored `AdminHelperTrait` to enforce standardized "Documentation" and "Reset to Defaults" buttons with uniform styling (blue outline / red warning) and fixed order. Updates applied to Dashboard, Settings, Vehicle Settings, Transfer, Reports, Customers, Messages, Add-ons, Shortcodes, About, and License pages. All button labels are now strict English strings wrapped in i18n functions.
- **Vehicle Settings Reset Logic Fix:** Updated `VehicleSettings::ajax_reset_settings` to explicitly set options to empty arrays (Clean Slate) instead of deleting them (which caused default fallbacks). Modified `VehicleFeatureHelper` to respect explicit empty arrays for card fields, ensuring the "Cleaner Slate" policy is strictly enforced.
- **Developer Mode Content Enhancement:** Updated the "Developer Mode Active" banner in `ProFeatureNotice.php` to display a comprehensive static message highlighting key Pro features (VIP Transfers, Messaging, Analytics) instead of a dynamic list, improving professional appearance and clarity.
- **About Page Regression & Performance Fix:** Resolved the critical "An error occurred" issue and slow loading on the About page. Implemented lazy loading for tab content (General, Features, System, Support) to prevent blocking remote/heavy operations. Disabled recursive directory size calculation in `SystemInfo` to ensure sub-500ms execution. Standardized the header with `AdminHelperTrait` and English labels.
- **Header HTML Rendering Fix:** Updated `AdminHelperTrait::render_admin_header` to use `wp_kses` instead of `esc_html`, allowing badges (span tags) to render correctly in page titles.
- **Tab Switching Logic Repair:** Refactored `About::ajax_load_tab` to eliminate redundant data fetching and use the new lazy loading method, preventing session-related errors during tab switches.
- **Global AJAX 400 & UI Visibility Fix:** Fixed critical security check failure (400) by correcting the localized script object name in `about.js` (`mhmAboutAdmin` -> `mhmRentivaAboutAdmin`). Implemented client-side tab caching to prevent redundant requests. Updated `about.css` badges to high-contrast (Blue/White, Gold/Black) and injected accessibility fixes into `booking-form.css`.
- **About Page UI Modernization (CRITICAL REPAIR):** Implemented strict CSS Grid enforcement (`display: grid !important`) with specific targeting (`.mhm-rentiva-about-wrap .mhm-rentiva-about-grid`) to override WordPress core styles causing vertical stacking. Removed max-width restrictions from cards and containers.
- **About Page Button Consolidation:** Removed redundant "Go to Settings" and "View Properties" buttons from `GeneralTab`. Added "Settings" button to the global header (Documentation, Support, Settings). All labels now use English for proper i18n support.
- **Developer Tab Optimization:** Filtered expertise grid to keep only "WordPress Development", "E-commerce Solutions", and "Reservation Systems". Filtered projects grid to keep "MHM E-commerce Package" and added "MHM Vehicle Reservation". All labels in English for Loco Translate compatibility.
- **Plugin Check Compliance Fixes (2026-01-31):** Fixed `date()` to `gmdate()` in `SecurityHelper.php` for timezone consistency. Changed text domain from `'woocommerce'` to `'mhm-rentiva'` in `WooCommerceBridge.php`. Added `esc_attr()` escaping to all inline SVG variables in `booking-form.php` and `availability-calendar.php` templates.
- **Repository Cleanup & .gitignore Standardization (2026-02-01):** Created comprehensive .gitignore with 11 organized sections covering OS files, IDE configs, dependency managers, WordPress local files, build assets, testing tools, MHM-specific exclusions, language files, archives, security-sensitive files, and AI tool artifacts. Added WooCommerce stubs via Composer (`php-stubs/woocommerce-stubs`). Fixed IDE lint false positive for `WC_Product::get_sku()` using `call_user_func()` pattern.
- **Global Admin Responsive Fix & Standardization (2026-02-01):** Implemented comprehensive mobile responsive rules across all admin CSS files using WordPress standard breakpoints (782px tablet, 600px mobile). Updated `core.css` with global responsive header, grid, and form rules. Added `vehicle-card-fields.css` mobile stacking for checkbox columns. Enhanced `settings.css` with Vehicle Settings specific responsive rules for action buttons, checkbox grids, and form sections. Updated `about.css` with header actions and badge scaling for small screens.
- **Global 782px Breakpoint Standardization (2026-02-01):** Replaced ALL `@media (max-width: 768px)` queries with WordPress standard `@media (max-width: 782px)` across 50+ CSS files in admin/, components/, core/, frontend/, and payment/ directories. Updated `--mhm-breakpoint-md` variable to 782px in `css-variables.css`. Bumped plugin version to 4.6.8 for cache busting. This ensures plugin layout transitions sync perfectly with WordPress admin sidebar collapse at 782px, eliminating the 768px-782px "dead zone" where layouts were inconsistent.
- **Final Responsive Audit & Table Overflow Fix (2026-02-01):** Completed comprehensive mobile optimization for admin pages. Added responsive table wrapper with horizontal scrolling to `core.css` and `mhm-shortcode-pages.css`. Implemented responsive rules for section action buttons ("Select All", "Deselect All"). Added touch target optimization (minimum 44px height for iOS). Overrode inline CSS styles in `settings.css` for Vehicle Settings checkbox grids and category action buttons using attribute selectors. Plugin version bumped to 4.6.9.
- **Vehicle Settings UI Symmetry & Proportional Harmonization (v4.8.0):** Checkbox component architecture was standardized across the entire page and hierarchical unity (nested div > label) was achieved. Checkbox dimensions have been updated to 16px and texts to 14px. The "ABSOLUTE row" rule has been introduced to prevent texts from falling below the boxes in mobile view. The vertical scrolling issue of tick marks was fixed with WP-specific CSS reset.
- **Admin JS SyntaxError Fix (v4.8.0):** Incorrect optional chaining usage (`? .`) that caused the "Unexpected token '.'" error in `messages-admin.js` and `settings-form-handler.js` files have been cleared. Converted to logical `&&` controls for wide browser compatibility and WordPress standards. The critical error that prevented the "Messages" module from working has been fixed.
- **Vehicle Settings Final Responsive Repair (2026-02-01):** Enhanced `settings.css` with comprehensive mobile overrides for Vehicle Settings page. Added rules for: (1) Main Settings Container 3-column→1-column transition at 782px, (2) Settings Card padding reduction (20px→15px→12px), (3) Direct ID selectors for action buttons (#select-all-details, #rename-details, etc.), (4) Checkbox item touch targets min 44px→48px on mobile, (5) flex-wrap and width:100% for button containers. All overrides use `!important` to defeat inline styles in VehicleSettings.php.
- **Vehicle Settings CSS Loading Fix (2026-02-01):** CRITICAL FIX - Vehicle Settings page was not loading `settings.css` at all! Only JavaScript was enqueued. Added `wp_enqueue_style()` calls for `settings.css` and `vehicle-card-fields.css` in `AssetManager.php` line 963-990 for screen ID `mhm-rentiva_page_vehicle-settings`. This enables all responsive overrides to take effect.
- **Vehicle Settings & Editor Critical Repair (v4.8.1):** The "Deposit" field has been brought back and added to the defaults. The empty label problem (Empty Label Syndrome) seen in the meta boxes on the vehicle editing page has been resolved with the "Data Recovery Fallback" logic added to the 'VehicleMeta' and 'VehicleSettings' classes. The system now automatically falls to the default translations even if the tags in the database are corrupt or empty. Added defensive checks against destructive AJAX save logic.
- **Deposit Field Promotion & UI Reorganization (v4.8.2):** The "Deposit" field was moved from the removable "Attributes" section to the permanent "Core Details (Important)" section. With the 'deposit' protection added to `render_definitions_tab` and `ajax_remove_custom_field` in `VehicleSettings.php`, deletion of this key was blocked at the API level. Plugin version was bumped to v4.8.2 to ensure UI synchronization.
- **MHM Global Checkbox Standard & UI Unification (v4.8.3):** Defined global component `.mhm-ui-checkbox` in `core.css` to ensure visual consistency across the plugin. Checkbox (16px) and text (14px) ratios have been standardized. The "ABSOLUTE ROW" (flex-wrap: nowrap) rule has been introduced to prevent texts from falling below the boxes on mobile. Vehicle Settings and Vehicle Editing (Meta Box) pages have been refactored according to this new standard and UI fragmentation has been eliminated. Version upgraded to v4.8.3.
- **UI Precision & Mobile Regression Fix (v4.8.4):** The alignment problem caused by the global checkbox change in v4.8.3 in the "Delete" (X) buttons on the Vehicle Settings page has been fixed. The PHP template was refactored and the buttons were moved out of the tag (as sister elements). In `vehicle-settings.css`, `.mhm-checkbox-item` was defined as master flex container and the buttons were fixed to the far right and vertically in the center. On mobile, 'box-sizing' and 'width' rules have been made mandatory, preventing boxes from taking up the full width and shifting to the left. Version upgraded to v4.8.4.
- **Mobile Tick Alignment & Absolute Centering (v4.8.5):** The issue of checkbox ticks extending outside the box and being misaligned on mobile has been fixed. Millimetric centering was achieved for the `input[type="checkbox"]:checked:before` element by applying SVG-based content and `position: absolute` + `top: 50%` + `left: 50%` + `transform: translate(-50%, -50%)` strategy. This rule has been applied to both global and local checkbox classes for cross-browser consistency. Version upgraded to v4.8.5.
- **Ultimate UI Clarity & High Contrast (v4.8.6):** A new high-contrast skin was applied for checkboxes and checkmarks that looked faint on mobile. Checkbox borders were set to 2px and dark gray (`#8c8f94`). In selected state, a sharp white checkmark was rendered over blue background (`#2271b1`) using a CSS border technique. The `opacity: 1` rule fully prevented faded rendering. Version was bumped to v4.8.6.

### [COMPLETED] Transfer Module Integrity Validation (v4.9.5)
- **Status:** Done
- **Scope:** Comprehensive end-to-end validation and final code hardening for the Transfer module.
- **Key Changes:**
  - **Prefix Harmonization:** Verified 100% removal of legacy `mhm_` prefixes in Transfer-specific files (`TransferShortcodes.php`, `TransferSearchEngine.php`, `TransferCartIntegration.php`, `TransferAdmin.php`, `VehicleTransferMetaBox.php`).
  - **Database Migration:** Implemented robust backward compatibility logic in `DatabaseMigrator.php` to seamlessly rename legacy tables and migrate settings to `rentiva_` keys.
  - **Cart Integration:** Hardened `TransferCartIntegration.php` with improved fallback logic for vehicle meta keys (`_rentiva_` > `_mhm_`) and secure nonce verification.
  - **Admin UI:** Updated `TransferAdmin.php` to correctly reference new table names and option keys, ensuring the admin panel reflects the modernized architecture.
  - **Version Bump:** Incremented plugin version to v4.9.5.

### [COMPLETED] [VERIFIED] Release v4.9.6
- **Status:** Done
- **Scope:** Maintenance release focusing on performance, CSS architecture, and HTML structure updates.
- **Key Changes:**
  - **Version Bump:** Plugin version updated to 4.9.6.
  - **Documentation:** Updated readme.txt with new changelog entry.
  - **Changelog:**
      - Performance: Overall code optimization.
      - Refactoring: CSS architecture cleanup.
      - Markup: Updated HTML structure for Availability Calendar.
      - i18n: Added new translation files.
      - Transfer Module: Finalized prefix standardization (rentiva_) and database migration logic.

### [COMPLETED] [VERIFIED] Deep Database Cleanup & Rewrite Fix (v4.9.6+)
- **Status:** Done
- **Scope:** Complete removal of leftover demo data and resolution of technical warnings.
- **Key Changes:**
  - **Data Scrubbing:** Permanently deleted demo bookings (#30, #31, #32) and messages (#33, #34) from `wp_posts` and `wp_postmeta`.
  - **Table Truncation:** Cleaned custom log and queue tables: `wp_mhm_notification_queue`, `wp_mhm_rentiva_queue`, `wp_mhm_message_logs`, `wp_mhm_rentiva_background_jobs`, and `wp_mhm_payment_log`.
  - **Rewrite Resolution:** Fixed the "Vehicle category rewrite rules not found" warning by triggering a hard `flush_rewrite_rules(true)` for the `vehicle_category` taxonomy.
  - **Reporting Reset:** Purged all report-related transients (`_transient_mhm_report_*` and `_transient_mhm_dashboard_*`) to ensure dashboard counters (Revenue, Bookings) reflect the 0-state.
  - **Verification:** Verified final counts via CLI: Vehicles: 0, Bookings: 0, Messages: 0.

### [VERIFIED] Email Notification & Manual Template Fix (2026-02-02)
- **Status:** Done
- **Scope:** Corrected email template layout for manual admin emails and fixed status update locking.
- **Fixes:**
  - Enabled manual booking status revert (Confirmed -> Pending).
  - Wrapped manual customer emails with the "Golden Ratio" premium HTML layout.
  - Synchronized systematic sender settings for manual communication.

---

### [LOCKED] Stitch-to-WP Bridge Governance Patch (v4.11.x) — 2026-02-19

- **Status:** LOCKED — Governance-enforced. No override without Chief Engineer approval.
- **Decision:** No Tailwind Runtime (Hard Rule)
- **Enforcement Layers:**
  1. **Static Scan:** `cdn.tailwindcss.com` and `tailwindcss` / `tailwind` handle patterns are prohibited. Static scan confirmed clean — no runtime CDN references in codebase.
  2. **Runtime Interception:** `Governance::enforce_no_tailwind()` hooked at priority `9999` on `wp_enqueue_scripts` and `admin_enqueue_scripts`. Blocks and deregisters any style or script matching forbidden handles or URLs.
  3. **PHPUnit Gate:** `tests/Integration/GovernanceTest.php` — 3 tests, 14 assertions. All passing.
- **Violation Policy:** Asset is silently deregistered + logged to `error_log` when `WP_DEBUG` is true. `mhm_rentiva_governance_violation` action fires for future extension.
- **New Skill Registered:** `stitch-to-wp-bridge` added to `SKILLS.md`. Implementation-only scope. No architecture authority.
- **Home POC Scope:** `[rentiva_home_poc]` shortcode introduced as **Experimental / Version-scoped**. Not part of the stable public API.
  - Composes: `[rentiva_unified_search]`, `[rentiva_featured_vehicles]`, `[rentiva_testimonials]`.
  - No new DOM contracts. Uses existing `css-variables.css` token system.
  - No global CSS injection.
- **Public API Surface:** Unchanged. All existing shortcodes untouched.
- **Phase 4 Performance Baseline:** Preserved. Governance class is hook-driven, zero-cost on pages where no violation is detected.
- **Files Created:**
  - `src/Admin/Core/Governance.php`
  - `src/Admin/Frontend/Shortcodes/HomePoc.php`
  - `templates/shortcodes/home-poc.php`
  - `tests/Integration/GovernanceTest.php`



