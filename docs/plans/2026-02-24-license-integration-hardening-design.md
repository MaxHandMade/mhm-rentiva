# License Integration Hardening Design

Date: 2026-02-24
Owner: Ramazan / Codex
Scope: `mhm-rentiva` client licensing integration with `mhm-license-server-local`

## 1. Problem Statement

The current licensing flow works, but environment routing is not deterministic enough for Docker/local development and lacks explicit security hardening controls for production usage.  
Goal: implement a dynamic, deterministic configuration strategy with secure defaults, while preserving backward compatibility and WordPress Coding Standards (WPCS) compliance.

## 2. Goals

1. Route license API requests dynamically by environment with explicit override support.
2. Keep production safe by default (HTTPS and SSL verification policies).
3. Introduce hardened request/response handling and consistent error codes.
4. Add CI and GitHub process files to enforce WPCS and test quality.
5. Preserve existing plugin behavior and migration compatibility.

## 3. Non-Goals

1. Rewriting the entire licensing architecture.
2. Breaking existing active licenses or stored option schema.
3. Introducing proprietary infrastructure dependencies.

## 4. Current State Summary

Client plugin (`mhm-rentiva`):

1. License logic is centralized in `src/Admin/Licensing/LicenseManager.php`.
2. Endpoints used: `/licenses/activate`, `/licenses/validate`, `/licenses/deactivate`.
3. API base supports constant overrides, but local routing depends on optional constants.

License server (`mhm-license-server-local`):

1. REST namespace: `mhm-license/v1`.
2. Public lifecycle endpoints exist (activate/validate/deactivate/reactivate).
3. HTTPS enforcement is configurable in server settings.
4. License keys are encrypted at rest and hashed for lookup.

## 5. Proposed Architecture

## 5.1 Dynamic Configuration Resolver (Client)

Add/normalize resolver methods in `LicenseManager`:

1. `resolve_api_base_url()`
2. `resolve_ssl_verify()`
3. `resolve_request_headers()`

Resolution precedence:

1. Explicit override constant (`MHM_RENTIVA_LICENSE_API_BASE`).
2. Local override constant for local/development (`MHM_RENTIVA_LICENSE_API_LOCAL`).
3. Environment mapping via `wp_get_environment_type()`.
4. Safe fallback to production API.

This keeps behavior deterministic and Docker-friendly.

## 5.2 Security Baselines

Client:

1. Validate final URL with `wp_http_validate_url()` and `wp_parse_url()`.
2. Allow only `http`/`https`.
3. Force HTTPS outside local/development unless explicitly overridden.
4. Normalize request timeout and headers consistently.

Server:

1. Keep HTTPS enforcement toggle, defaulting to secure settings in production.
2. Add optional request-signature verification (phase 3).
3. Add per-IP/per-license rate limiting (phase 3).

## 5.3 Error Contract

Standardize machine-readable error codes across client/server:

1. `missing_parameters`
2. `invalid_key`
3. `inactive_license`
4. `expired_license`
5. `activation_limit_reached`
6. `license_connection`
7. `license_http`
8. `license_config_error`

Human-readable messages remain i18n-ready via `__()`/`esc_html__()`.

## 6. Data Flow (Lifecycle)

Activation:

1. User submits key in admin.
2. Client resolves endpoint/SSL config.
3. Client sends payload (`license_key`, `site_hash`, `site_url`, `is_staging`).
4. Server validates license and activation capacity.
5. Client stores normalized license state in option.

Validation:

1. Triggered by cron/manual/status checks.
2. On transport failures, grace strategy is applied according to current logic.
3. On explicit inactive response, client clears activation binding.

Deactivation:

1. Client requests server deactivation when activation metadata exists.
2. Local option is always cleared to prevent stale active state.

## 7. WPCS and Coding Rules

1. No inline scripts/styles added for this change set.
2. All input sanitized with `sanitize_*` + `wp_unslash` where required.
3. All output escaped with proper `esc_*` function.
4. Strict comparisons and early returns preferred.
5. SQL access must use `$wpdb->prepare()` for dynamic values.
6. Keep class-level responsibilities separated (resolver logic stays in licensing domain).

## 8. GitHub Integration Files

Add/update:

1. `.github/workflows/phpcs.yml` (WPCS gate)
2. `.github/workflows/phpunit.yml` (test gate)
3. `.github/pull_request_template.md` (security + WPCS checklist)
4. `.github/ISSUE_TEMPLATE/bug_report.yml`
5. `.github/ISSUE_TEMPLATE/feature_request.yml`
6. `.github/CODEOWNERS`
7. `.editorconfig` (if missing)

## 9. Testing Strategy

1. Unit tests for resolver precedence and SSL policy.
2. Integration tests for activate/validate/deactivate against local server endpoint.
3. Regression tests for local environment not accidentally falling to wrong endpoint.
4. CI gates: PHPCS and PHPUnit required for merge.

## 10. Rollout Plan

Phase 1:

1. Implement resolver refactor (backward-compatible).
2. Wire environment-driven values from Docker/wp-config constants.

Phase 2:

1. Add GitHub CI/process files.
2. Verify WPCS + tests in CI.

Phase 3:

1. Implement signature verification and rate limiting in license server.
2. Add corresponding client headers and fallback logic.

Phase 4:

1. Update docs for setup and environment variables.
2. Run smoke tests in local + staging.

## 11. Risks and Mitigations

1. Risk: Wrong endpoint in local.
   Mitigation: deterministic precedence + explicit constants.

2. Risk: SSL mismatch in dev.
   Mitigation: local override + environment-aware SSL verification.

3. Risk: Breaking existing licenses.
   Mitigation: keep option schema backward-compatible and add regression tests.

## 12. Success Criteria

1. Local Docker setup can target local license server without code edits.
2. Production remains HTTPS/SSL-safe by default.
3. PHPCS and test workflows pass in CI.
4. License lifecycle behaves consistently across activate/validate/deactivate flows.
