# Performance Next Targets (Plugin)

## Scope
This page defines the next low-risk, high-ROI optimization backlog after the current hardening cycle.

## Current Baseline (Done)
- Stable asset versioning (`filemtime`) in block/editor asset registration.
- Duplicate localization guards in `AssetManager`.
- Admin-only registration scoping in plugin bootstrap.
- Composer scripts pinned to project-local binaries.

## Priority Backlog

### P1 - Bootstrap Cost Profiling (High ROI, Low Risk)
- Target: `src/Plugin.php` service init path.
- Action:
  - Measure hook count and execution time per init phase.
  - Identify heavy registrations that can move behind capability/screen checks.
- Success metric:
  - Lower total hooks added on frontend requests.
  - No behavioral regression in admin menu/pages.

### P2 - Admin Asset Payload Split (High ROI, Medium Risk)
- Target: `src/Admin/Core/AssetManager.php`.
- Action:
  - Split large localized payloads by screen.
  - Keep only universal fields in global localization.
- Success metric:
  - Reduced admin page HTML size and localized JS payload.

### P3 - Block Render Path Micro-Optimizations (Medium ROI, Low Risk)
- Target: `src/Blocks/BlockRegistry.php`.
- Action:
  - Cache frequently used block config lookups per request.
  - Reduce repeated array merges/string transforms in hot render paths.
- Success metric:
  - Lower render callback CPU time on block-heavy pages.

### P4 - Template Loading Guardrails (Medium ROI, Medium Risk)
- Target: `Plugin::load_vehicle_templates()`.
- Action:
  - Add early bails and strict checks before include path resolution.
  - Ensure no redundant global state setup for non-target requests.
- Success metric:
  - Reduced template redirect overhead on non-vehicle routes.

## Measurement Method
- Local profiling with Query Monitor + timing logs (development-only).
- Compare before/after on:
  - Frontend single vehicle page
  - Admin dashboard
  - Admin booking edit screen

## Definition of Done
- `composer test` passes.
- `composer phpcs` passes.
- CI workflows green.
- Contract docs updated for any behavior change.
