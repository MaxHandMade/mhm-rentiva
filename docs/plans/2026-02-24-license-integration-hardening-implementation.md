# License Integration Hardening Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make license API routing deterministic across local/staging/production, add security hardening hooks, and enforce WPCS/test quality through GitHub workflows.

**Architecture:** Keep license integration centered in `LicenseManager`, introducing explicit resolver methods for API base URL, SSL verification, and request headers. Preserve current option schema and activation lifecycle while adding strict config validation and test coverage. Use phased delivery to avoid regressions.

**Tech Stack:** WordPress plugin PHP (strict types), WP HTTP API, PHPUnit (WordPress test bootstrap), PHPCS with WPCS, GitHub Actions.

---

### Task 1: Add Resolver Unit Test Scaffold

**Files:**
- Create: `tests/Core/Licensing/LicenseManagerResolverTest.php`
- Modify: `tests/bootstrap.php`

**Step 1: Write the failing test**

```php
public function test_resolver_prefers_explicit_base_constant(): void {
    $manager = \MHMRentiva\Admin\Licensing\LicenseManager::instance();
    $value   = $manager->debug_resolve_api_base_url_for_test();
    $this->assertSame( 'https://example.test/v1', $value );
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php --filter test_resolver_prefers_explicit_base_constant`
Expected: FAIL (test helper method missing).

**Step 3: Write minimal implementation**

Add temporary test-only debug accessor behind `defined( 'PHPUNIT_RUNNING' )` gate or equivalent existing test gate.

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php --filter test_resolver_prefers_explicit_base_constant`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Core/Licensing/LicenseManagerResolverTest.php tests/bootstrap.php src/Admin/Licensing/LicenseManager.php
git commit -m "test: add resolver baseline test for license api base"
```

### Task 2: Implement Deterministic API Base Resolver

**Files:**
- Modify: `src/Admin/Licensing/LicenseManager.php`
- Modify: `tests/Core/Licensing/LicenseManagerResolverTest.php`

**Step 1: Write failing tests for precedence**

Add tests for:
1. `MHM_RENTIVA_LICENSE_API_BASE` highest priority.
2. Local env + `MHM_RENTIVA_LICENSE_API_LOCAL` second priority.
3. Fallback to production URL when no overrides.

**Step 2: Run tests to verify failure**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php`
Expected: FAIL on missing precedence behavior.

**Step 3: Write minimal implementation**

Implement `resolve_api_base_url()` and route current request path builder to this method.

**Step 4: Run tests to verify pass**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Admin/Licensing/LicenseManager.php tests/Core/Licensing/LicenseManagerResolverTest.php
git commit -m "feat: add deterministic license api base resolver"
```

### Task 3: Implement SSL Verify + URL Validation Guards

**Files:**
- Modify: `src/Admin/Licensing/LicenseManager.php`
- Modify: `tests/Core/Licensing/LicenseManagerResolverTest.php`

**Step 1: Write failing tests**

Add tests for:
1. Invalid URL returns `WP_Error( 'license_config_error', ... )`.
2. Production/staging enforces SSL verify.
3. Local can use override via `MHM_RENTIVA_SSL_VERIFY`.

**Step 2: Run tests to verify failure**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php`
Expected: FAIL on missing guard logic.

**Step 3: Write minimal implementation**

1. Add `resolve_ssl_verify()`.
2. Validate resolved URL with `wp_http_validate_url()` and scheme checks.
3. Return normalized `WP_Error` for config problems before remote request.

**Step 4: Run tests to verify pass**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Admin/Licensing/LicenseManager.php tests/Core/Licensing/LicenseManagerResolverTest.php
git commit -m "feat: harden license request config and ssl policy"
```

### Task 4: Normalize Request Headers and Error Codes

**Files:**
- Modify: `src/Admin/Licensing/LicenseManager.php`
- Modify: `tests/Core/Licensing/LicenseManagerResolverTest.php`

**Step 1: Write failing tests**

Test header contract:
1. `Content-Type`, `Accept`, `User-Agent`, `X-Environment` present.
2. HTTP errors map to known codes (`license_http`, `license_connection`).

**Step 2: Run tests to verify failure**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

1. Add `resolve_request_headers()` method.
2. Centralize error mapping in one method.

**Step 4: Run tests to verify pass**

Run: `vendor/bin/phpunit tests/Core/Licensing/LicenseManagerResolverTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Admin/Licensing/LicenseManager.php tests/Core/Licensing/LicenseManagerResolverTest.php
git commit -m "refactor: standardize license request headers and error mapping"
```

### Task 5: Wire Docker/Config Environment Variables

**Files:**
- Modify: `docker-compose.yml`
- Modify: `wp/wp-config.php`
- Modify: `.env` (if project policy allows; otherwise `.env.example`)

**Step 1: Write failing integration check**

Document and script a check in notes:
1. `WP_ENVIRONMENT_TYPE=local`
2. `MHM_RENTIVA_LICENSE_API_LOCAL` not empty
3. Request target prints expected local URL

**Step 2: Run check to verify failure**

Run: `docker compose exec -T wordpress wp eval "echo defined('MHM_RENTIVA_LICENSE_API_LOCAL') ? MHM_RENTIVA_LICENSE_API_LOCAL : 'missing';"`
Expected: missing/incorrect before wiring.

**Step 3: Write minimal implementation**

1. Pass env vars to `wordpress` service.
2. Define constants in `wp-config.php` from env.

**Step 4: Run check to verify pass**

Run: `docker compose exec -T wordpress wp eval "echo MHM_RENTIVA_LICENSE_API_LOCAL;"`
Expected: local endpoint URL.

**Step 5: Commit**

```bash
git add docker-compose.yml wp/wp-config.php .env.example
git commit -m "chore: wire env-driven license api config for docker"
```

### Task 6: Add Client Integration Test for License Lifecycle

**Files:**
- Create: `tests/Integration/Licensing/LicenseLifecycleIntegrationTest.php`
- Modify: `phpunit.xml.dist` (if suite registration needed)

**Step 1: Write failing test**

Test scenario:
1. Mock/sandbox endpoint or local test endpoint.
2. Activate returns active + activation ID.
3. Validate returns active.
4. Deactivate clears local state.

**Step 2: Run test to verify failure**

Run: `vendor/bin/phpunit tests/Integration/Licensing/LicenseLifecycleIntegrationTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

Use existing integration helpers and only add minimum hooks needed for deterministic endpoint injection.

**Step 4: Run test to verify pass**

Run: `vendor/bin/phpunit tests/Integration/Licensing/LicenseLifecycleIntegrationTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Integration/Licensing/LicenseLifecycleIntegrationTest.php phpunit.xml.dist src/Admin/Licensing/LicenseManager.php
git commit -m "test: add integration coverage for license lifecycle"
```

### Task 7: Add GitHub WPCS and PHPUnit Workflows

**Files:**
- Create: `.github/workflows/phpcs.yml`
- Create: `.github/workflows/phpunit.yml`
- Modify: `.github/workflows/testing.yml` (if dedup required)

**Step 1: Write failing CI expectation**

Define required checks in workflow names:
1. `WPCS / PHPCS`
2. `PHPUnit`

**Step 2: Run local lint check to verify baseline**

Run: `vendor/bin/phpcs --standard=phpcs.xml src`
Expected: Current baseline output (can fail before cleanup).

**Step 3: Write minimal implementation**

Create workflows with:
1. Composer install
2. PHPCS run
3. PHPUnit run

**Step 4: Validate workflow syntax**

Run: `node -e "const fs=require('fs'); ['phpcs.yml','phpunit.yml'].forEach(f=>console.log(fs.existsSync('.github/workflows/'+f)?f+':ok':f+':missing'))"`
Expected: both present.

**Step 5: Commit**

```bash
git add .github/workflows/phpcs.yml .github/workflows/phpunit.yml .github/workflows/testing.yml
git commit -m "ci: add dedicated phpcs and phpunit workflows"
```

### Task 8: Add GitHub Collaboration Templates

**Files:**
- Create: `.github/pull_request_template.md`
- Create: `.github/ISSUE_TEMPLATE/bug_report.yml`
- Create: `.github/ISSUE_TEMPLATE/feature_request.yml`
- Create: `.github/CODEOWNERS`
- Create or Modify: `.editorconfig`

**Step 1: Write failing policy checklist (manual)**

Ensure templates include:
1. WPCS checklist
2. Security checklist
3. Test evidence section

**Step 2: Verify missing files before creation**

Run: `rg --files .github | rg -n "pull_request_template|ISSUE_TEMPLATE|CODEOWNERS"`
Expected: Missing or partial.

**Step 3: Write minimal implementation**

Add templates with concise, enforceable sections.

**Step 4: Verify files exist**

Run: `rg --files .github | rg -n "pull_request_template|ISSUE_TEMPLATE|CODEOWNERS"`
Expected: Present.

**Step 5: Commit**

```bash
git add .github/pull_request_template.md .github/ISSUE_TEMPLATE/bug_report.yml .github/ISSUE_TEMPLATE/feature_request.yml .github/CODEOWNERS .editorconfig
git commit -m "docs: add github templates and code ownership rules"
```

### Task 9: Optional Server Hardening Phase (Signature + Rate Limit)

**Files:**
- Modify: `../.tmp/mhm-license-server-local/src/REST/RESTController.php`
- Create: `../.tmp/mhm-license-server-local/src/Security/RequestGuard.php`
- Modify: `../.tmp/mhm-license-server-local/src/Settings/SettingsManager.php`
- Modify: `../.tmp/mhm-license-server-local/readme.txt`

**Step 1: Write failing tests (or manual guard checks)**

1. Missing signature rejected when enforced.
2. Old timestamp rejected.
3. Rate-limited requests return 429.

**Step 2: Run to verify failure**

Run targeted plugin tests (or local curl scripts).
Expected: Failures/unguarded behavior.

**Step 3: Write minimal implementation**

Implement request guard + transient rate limit + settings toggle.

**Step 4: Re-run checks**

Expected: Guarded responses and valid signed request success.

**Step 5: Commit**

```bash
git add src/REST/RESTController.php src/Security/RequestGuard.php src/Settings/SettingsManager.php readme.txt
git commit -m "feat: add signed request verification and rate limit guard"
```

### Task 10: Final Verification and Documentation

**Files:**
- Modify: `README.md`
- Modify: `README-tr.md`
- Modify: `docs/plans/2026-02-24-license-integration-hardening-design.md` (link to implementation outcomes)

**Step 1: Run full verification**

Run:
1. `vendor/bin/phpcs --standard=phpcs.xml src tests`
2. `vendor/bin/phpunit`
3. Docker smoke check for resolved endpoint and activation lifecycle.

Expected: Green or clearly documented known baseline gaps.

**Step 2: Update docs**

Add environment variable table and local/prod setup examples.

**Step 3: Commit**

```bash
git add README.md README-tr.md docs/plans/2026-02-24-license-integration-hardening-design.md
git commit -m "docs: document dynamic license endpoint and validation flow"
```

## Execution Notes

1. Keep each commit scoped to one task.
2. Do not bundle unrelated template/language changes currently in the working tree.
3. Preserve backward compatibility in license option storage.
4. Follow WPCS and existing project naming conventions.
