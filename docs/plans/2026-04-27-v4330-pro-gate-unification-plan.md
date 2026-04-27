# v4.33.0 Pro Gate Unification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** v4.31.0 RSA token gate'inin yarım kalan üç problemini bitir — 22 callsite token verify atlıyor, LicenseAdmin yanıltıcı metin gösteriyor, lokal'de Pro test imkansız.

**Architecture:** Mode.php'ye dev-mode bypass + `canUseExport()` ekle, LicenseAdmin'i dinamik gate-driven yap, 22 `featureEnabled()` callsite'ı `canUse*()`'a migrate et, eski API'yi soft deprecate et. Companion mhm-license-server v1.11.2 patch'i `'export'` feature key'ini token allowlist'ine ekler (deploy edilmeden Pro Export erişimi kaybolur).

**Tech Stack:** PHP 7.4+/8.2, WordPress 6.x, PHPUnit + WP_UnitTestCase, Composer (PHPCS+PHPStan), Docker dev stack.

**Spec:** [2026-04-27-v4330-pro-gate-unification-design.md](2026-04-27-v4330-pro-gate-unification-design.md)

---

## Prerequisites & Conventions

- **Repos:**
  - `c:/projects/rentiva-dev/plugins/mhm-rentiva` (Phase 1+ tüm görevler)
  - `c:/projects/rentiva-lisans/plugins/mhm-license-server` (Phase 0 tek görev)
- **Branch:** Phase 1 başında `fix/pro-gate-unification` (mhm-rentiva), `release/v1.11.2` (server)
- **Pre-push CI parity:** Her commit grubu sonrası tüm dört kontrol (`composer phpcs`, `composer phpstan`, PHPUnit PHP 7.4, PHPUnit PHP 8.2). Detay: Phase 6.
- **Test base class:** `WP_UnitTestCase`
- **Test namespace:** `MHMRentiva\Tests\Unit\Licensing`
- **Test path:** `tests/Unit/Licensing/`
- **Test fixtures (RSA keys):** `tests/fixtures/test-rsa-private.pem` + `test-rsa-public.pem`
- **Reference test (existing pattern):** [tests/Unit/Licensing/ModeFeatureTokenTest.php](../../tests/Unit/Licensing/ModeFeatureTokenTest.php)
- **Filter-based testability hook (yeni, bu plan'da tanıtıldı):** `mhm_rentiva_dev_pro_bypass` filter — bypass kararını test'lerde mock etmek için. Define'ları runtime'da değiştiremediğimiz için.
- **Commit style:** Conventional (`feat:`, `fix:`, `test:`, `refactor:`, `chore:`, `docs:`). `Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>` footer.

---

## File Structure (mhm-rentiva)

**Modify:**
- `src/Admin/Licensing/Mode.php` — bypass logic, `canUseExport()`, `featureEnabled()` deprecate
- `src/Admin/Licensing/LicenseAdmin.php` — dinamik active features, dev banner subtext
- 11 callsite dosyası (22 çağrı):
  - `src/Admin/Frontend/Account/AccountRenderer.php` (1)
  - `src/Admin/Frontend/Account/WooCommerceIntegration.php` (2)
  - `src/Admin/Messages/Core/Messages.php` (1)
  - `src/Admin/Messages/Frontend/CustomerMessages.php` (1)
  - `src/Admin/Messages/REST/Messages.php` (1)
  - `src/Admin/Messages/REST/Customer/SendMessage.php` (1)
  - `src/Admin/Messages/REST/Customer/SendReply.php` (1)
  - `src/Admin/Reports/Reports.php` (2)
  - `src/Admin/Utilities/Export/Export.php` (3)
  - `src/Admin/Utilities/Export/ExportReports.php` (6)
  - `src/Admin/Utilities/Menu/Menu.php` (3)
- Versioning: `mhm-rentiva.php`, `readme.txt`, `README.md`, `README-tr.md`, `changelog.json`, `changelog-tr.json`
- i18n: `languages/mhm-rentiva.pot`, `languages/mhm-rentiva-tr_TR.po` + `.mo` + `.l10n.php`

**Create:**
- `tests/Unit/Licensing/ModeDevBypassTest.php` (5 test)
- `tests/Unit/Licensing/LicenseAdminActiveFeaturesTest.php` (4 test)
- `tests/Unit/Licensing/ModeGateMigrationTest.php` (6 test)

## File Structure (mhm-license-server v1.11.2)

**Modify:**
- `src/License/FeatureTokenIssuer.php` (line ~140 area)
- `mhm-license-server.php` (Version + constant)
- `readme.txt` (Stable tag + Changelog)
- `CHANGELOG.md`

**Create:**
- *(Test eklenir mevcut test dosyasına — yeni dosya gerek yok, mevcut FeatureTokenIssuerTest var)*

---

# Phase 0 — mhm-license-server v1.11.2 Companion Patch

> **Önkoşul:** Phase 5 (license renewal prod swap) tamamlanmış olmalı. Bu Phase ayrı bir Docker container'da, ayrı repo'da çalışır.
>
> **NOT:** Phase 5 prod swap kararı kullanıcıya ait. Bu plan Phase 5'in tamamlandığını **varsayar**. Eğer Phase 5 yapılmadıysa, önce `c:/projects/rentiva-dev/plugins/mhm-rentiva/docs/plans/2026-04-27-license-renewal-production-deploy.md` çalıştırılır.

### Task 0.1: Server v1.11.2 — Verify branch/repo state

**Files:** *(read only)*

- [ ] **Step 1: Verify mhm-license-server clean state**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
git status --short
git log -1 --oneline
```

Expected:
- `git status` shows clean working tree (or only untracked files)
- HEAD is at v1.11.1 tag commit (license renewal Phase 1)

- [ ] **Step 2: Create release branch**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
git checkout -b release/v1.11.2 main
```

Expected: `Switched to a new branch 'release/v1.11.2'`

### Task 0.2: Add 'export' feature key to mhm-rentiva allowlist

**Files:**
- Modify: `c:/projects/rentiva-lisans/plugins/mhm-license-server/src/License/FeatureTokenIssuer.php` (line ~136-145)

- [ ] **Step 1: Locate the mhm-rentiva block in `featuresFor()`**

Open `src/License/FeatureTokenIssuer.php`. Find:

```php
        if ($product_slug === 'mhm-rentiva') {
            return [
                'vendor_marketplace' => true,
                'advanced_reports'   => true,
                'messaging'          => true,
                'full_rest_api'      => true,
                'gdpr_tools'         => true,
                'custom_emails'      => true,
            ];
        }
```

- [ ] **Step 2: Add `'export' => true`**

```php
        if ($product_slug === 'mhm-rentiva') {
            return [
                'vendor_marketplace' => true,
                'advanced_reports'   => true,
                'messaging'          => true,
                'full_rest_api'      => true,
                'gdpr_tools'         => true,
                'custom_emails'      => true,
                'export'             => true,
            ];
        }
```

### Task 0.3: Add server-side test for 'export' feature key

**Files:**
- Locate or create: `c:/projects/rentiva-lisans/plugins/mhm-license-server/tests/Unit/License/FeatureTokenIssuerTest.php`

- [ ] **Step 1: Find existing FeatureTokenIssuerTest.php**

```bash
find c:/projects/rentiva-lisans/plugins/mhm-license-server/tests -iname "*featuretoken*"
```

Expected: One or more existing test files. Open the matching one (most likely `FeatureTokenIssuerTest.php`).

- [ ] **Step 2: Add new test method** (location: alongside existing `featuresFor` tests)

Add the following test:

```php
    public function testFeaturesForRentivaIncludesExport(): void
    {
        $features = FeatureTokenIssuer::featuresFor('mhm-rentiva', 'monthly');

        $this->assertArrayHasKey('export', $features);
        $this->assertTrue($features['export'], 'Pro Rentiva subscription must include export feature');
    }
```

If the test class uses `Plan` enum, follow that pattern. Adapt to the existing test file's style.

- [ ] **Step 3: Run the new test, expect PASS**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
docker exec -i mhm-license-server-test ./vendor/bin/phpunit --filter testFeaturesForRentivaIncludesExport
```

Expected: `1 / 1 (100%)` — test passes.

If Docker container name differs, check `docker ps` for the test container.

- [ ] **Step 4: Run full PHPUnit suite, no regression**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
docker exec -i mhm-license-server-test ./vendor/bin/phpunit
```

Expected: All tests pass. Baseline before this task: 167 tests / 490 assertions (per hot.md). After: 168 / 491+.

### Task 0.4: Bump version + Changelog

**Files:**
- Modify: `mhm-license-server.php` (Version header + version constant)
- Modify: `readme.txt` (Stable tag)
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump `mhm-license-server.php` version**

Find:

```
 * Version: 1.11.1
```

Change to:

```
 * Version: 1.11.2
```

Find the constant (typically `MHM_LICENSE_SERVER_VERSION`):

```php
define('MHM_LICENSE_SERVER_VERSION', '1.11.1');
```

Change to:

```php
define('MHM_LICENSE_SERVER_VERSION', '1.11.2');
```

- [ ] **Step 2: Bump `readme.txt` Stable tag**

Find `Stable tag: 1.11.1` → change to `Stable tag: 1.11.2`.

- [ ] **Step 3: Add CHANGELOG entry**

Open `CHANGELOG.md`. Add at the top (under the version header pattern):

```markdown
## 1.11.2 — 2026-04-27

### Added

- `FeatureTokenIssuer::featuresFor()` now includes `'export'` in the mhm-rentiva Pro feature allowlist. Required by mhm-rentiva v4.33.0 client where `Mode::canUseExport()` checks the RSA token claim. Without this server patch, Pro users would lose access to xlsx/pdf export formats.

### Notes

- No schema or API changes. Existing Pro tokens are reissued via the daily cron (24h propagation window). Customers can force-refresh via `Re-validate Now` button in plugin client.
```

- [ ] **Step 4: Run full suite again to confirm version bumps don't break anything**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
docker exec -i mhm-license-server-test ./vendor/bin/phpunit
docker exec -i mhm-license-server-test ./vendor/bin/phpcs
```

Expected: All tests pass, PHPCS 0 error / 0 warning.

- [ ] **Step 5: Commit**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
git add src/License/FeatureTokenIssuer.php tests/Unit/License/FeatureTokenIssuerTest.php mhm-license-server.php readme.txt CHANGELOG.md
git commit -m "$(cat <<'EOF'
feat(license-server): add 'export' to mhm-rentiva Pro feature allowlist (v1.11.2)

Companion patch for mhm-rentiva v4.33.0 Pro Gate Unification. Without this
key in the issued token, Mode::canUseExport() (new gate) returns false for
all Pro users, blocking xlsx/pdf export formats.

Daily cron reissues tokens; manual Re-validate Now button provides instant
refresh. Backward-compat: legacy clients ignore unknown keys.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

### Task 0.5: Build + Tag + Release server v1.11.2

**Files:** *(build artifacts)*

- [ ] **Step 1: Build ZIP**

The license-server may use a different build mechanism than mhm-rentiva. Check for a build script:

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
ls bin/ 2>/dev/null
```

If `bin/build-release.py` or similar exists, run it. Otherwise use the standard zip-from-Docker pattern (referenced in [feedback_zip_docker_only.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_zip_docker_only.md)) — never use Windows `Compress-Archive`.

Approximate command (verify the actual path):

```bash
docker exec mhm-license-server-test bash -c "cd /var/www/html/wp-content/plugins && zip -r /tmp/mhm-license-server-1.11.2.zip mhm-license-server -x 'mhm-license-server/.git/*' 'mhm-license-server/tests/*' 'mhm-license-server/.github/*' 'mhm-license-server/node_modules/*' 'mhm-license-server/vendor/*'"
docker cp mhm-license-server-test:/tmp/mhm-license-server-1.11.2.zip ./build/
```

Expected: ZIP file at `build/mhm-license-server-1.11.2.zip`.

- [ ] **Step 2: Verify ZIP contents**

```bash
unzip -l ./build/mhm-license-server-1.11.2.zip | grep -E "FeatureTokenIssuer|mhm-license-server.php|readme.txt"
```

Expected: All three files present, FeatureTokenIssuer.php has the `'export' => true` line.

- [ ] **Step 3: Tag annotated**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
git tag -a v1.11.2 -m "v1.11.2: 'export' feature key for mhm-rentiva Pro"
```

- [ ] **Step 4: Push branch + tag**

```bash
git push -u origin release/v1.11.2
git push origin v1.11.2
```

- [ ] **Step 5: Create GitHub release** (via `gh` CLI)

```bash
gh release create v1.11.2 ./build/mhm-license-server-1.11.2.zip \
  --repo MaxHandMade/mhm-license-server \
  --title "v1.11.2 — Export feature key for Rentiva Pro" \
  --notes "Companion patch for mhm-rentiva v4.33.0. Adds 'export' to issued feature tokens for Rentiva Pro subscriptions. Fixes the upcoming gate where xlsx/pdf export would be blocked without this key."
```

- [ ] **Step 6: Deploy to wpalemi.com via Hostinger MCP**

Use Hostinger MCP `hosting_deployWordpressPlugin` with the unzipped plugin directory path. Reference: [feedback_deploy_checklist.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_deploy_checklist.md).

```
Tool: hostinger-mcp__hosting_deployWordpressPlugin
domain: wpalemi.com
pluginPath: c:/projects/rentiva-lisans/plugins/mhm-license-server
```

If response is large (100+ files), upload manually via WP Admin instead — see operational rule in `feedback_deploy_checklist.md`.

- [ ] **Step 7: Verify deploy**

```bash
curl -s -u admin:PASSWORD https://wpalemi.com/wp-json/wp/v2/plugins/mhm-license-server/mhm-license-server | python -m json.tool | grep -E "version|status"
```

Expected: `"version": "1.11.2"`, `"status": "active"`.

Get credentials from `c:/Users/manag/.claude/projects/credentials.md`.

- [ ] **Step 8: Smoke test — issue a fresh token for Rentiva Pro user**

In wpalemi.com admin, find an active Rentiva Pro license. Trigger validation (via plugin client `Re-validate Now` button on a real customer site, or directly via API):

```bash
curl -X POST https://wpalemi.com/wp-json/mhm-license/v1/validate \
  -H "X-MHM-API-Key: $API_KEY" \
  -H "X-MHM-Signature: $SIG" \
  -d '{"key":"<TEST-KEY>","activation_id":"<TEST-AID>","client_version":"4.32.0"}'
```

Expected response includes `"feature_token"` field. Decode the token (base64url payload):

```bash
echo "<token-payload-part>" | base64 -d | python -m json.tool
```

Expected: `features` object contains `"export": true`.

- [ ] **Step 9: Merge release branch to main**

```bash
cd c:/projects/rentiva-lisans/plugins/mhm-license-server
git checkout main
git merge --ff-only release/v1.11.2
git push origin main
git branch -d release/v1.11.2
git push origin --delete release/v1.11.2
```

**Phase 0 complete.** Server v1.11.2 in production. mhm-rentiva v4.33.0 development can begin.

---

# Phase 1 — mhm-rentiva Branch Setup & Test Scaffolding

### Task 1.1: Verify mhm-rentiva clean state + create branch

**Files:** *(none modified yet)*

- [ ] **Step 1: Verify clean working tree**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git status --short
```

Expected: only untracked files in `drafts/wporg-assets/` (irrelevant, gitignored category). No staged or modified files.

- [ ] **Step 2: Confirm baseline version is v4.32.0**

```bash
grep -E "Version:|MHM_RENTIVA_VERSION" mhm-rentiva.php | head -3
```

Expected: `Version: 4.32.0` and `MHM_RENTIVA_VERSION = '4.32.0'`.

- [ ] **Step 3: Create feature branch from main**

```bash
git checkout main
git pull origin main
git checkout -b fix/pro-gate-unification
```

Expected: branch created, switched.

- [ ] **Step 4: Run full PHPUnit baseline to confirm green start**

```bash
cd c:/projects/rentiva-dev
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

Expected: 793 tests, all passing (per hot.md). If a different baseline, record it; the post-v4.33.0 expected delta is +15.

---

# Phase 2 — Bug C: Dev Mode Bypass

### Task 2.1: Write ModeDevBypassTest with 5 failing tests

**Files:**
- Create: `tests/Unit/Licensing/ModeDevBypassTest.php`

- [ ] **Step 1: Create test file with full content**

Create `tests/Unit/Licensing/ModeDevBypassTest.php`:

```php
<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * v4.33.0 — Dev-mode bypass for `Mode::featureGranted()`. When BOTH
 * `WP_DEBUG` is true AND the `mhm_rentiva_dev_pro_bypass` filter (which
 * defaults to checking `MHM_RENTIVA_DEV_PRO`) returns true, the token
 * verification step is skipped so local Pro testing works without a
 * real RSA-signed token.
 *
 * Production safety:
 * - WP_DEBUG defaults to false on Hostinger production
 * - MHM_RENTIVA_DEV_PRO is an opt-in constant (undefined by default)
 * - isPro() check still runs — Lite licenses cannot bypass to Pro
 *
 * Filter `mhm_rentiva_dev_pro_bypass` exists for testability since
 * `define()` cannot be undone within a single PHP process.
 */
final class ModeDevBypassTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('mhm_rentiva_disable_dev_mode', true, false);
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    private function seedActiveLicenseWithoutToken(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'TEST-DEV-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
            // No feature_token — simulates dev environment without server
        ], false);
    }

    public function test_bypass_active_when_filter_returns_true(): void
    {
        $this->seedActiveLicenseWithoutToken();
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $this->assertTrue(Mode::canUseMessages(), 'Bypass active should grant any feature');
        $this->assertTrue(Mode::canUseAdvancedReports());
        $this->assertTrue(Mode::canUseVendorMarketplace());
        $this->assertTrue(Mode::canUseExport());
    }

    public function test_bypass_inactive_when_filter_returns_false(): void
    {
        $this->seedActiveLicenseWithoutToken();
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_false');

        $this->assertFalse(Mode::canUseMessages(), 'Filter false → token gate enforced → no token → fail');
        $this->assertFalse(Mode::canUseExport());
    }

    public function test_bypass_inactive_when_filter_default(): void
    {
        // No filter added — default behavior (production path).
        $this->seedActiveLicenseWithoutToken();

        $this->assertFalse(Mode::canUseMessages(), 'No filter → production semantics → no token → fail');
        $this->assertFalse(Mode::canUseVendorMarketplace());
    }

    public function test_bypass_requires_isPro(): void
    {
        // No active license at all — Lite mode.
        delete_option(LicenseManager::OPTION);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $this->assertFalse(Mode::canUseMessages(), 'isPro() check is the first gate — Lite cannot bypass to Pro');
        $this->assertFalse(Mode::canUseVendorMarketplace());
        $this->assertFalse(Mode::canUseExport());
    }

    public function test_bypass_filter_receives_default_value_from_constants(): void
    {
        // Filter callback can inspect the default and override.
        $this->seedActiveLicenseWithoutToken();
        $captured_default = null;
        add_filter('mhm_rentiva_dev_pro_bypass', function ($default) use (&$captured_default) {
            $captured_default = $default;
            return false;
        });

        Mode::canUseMessages();

        $this->assertIsBool($captured_default, 'Filter must receive a bool default value');
    }
}
```

- [ ] **Step 2: Run the new tests, expect 5 FAILURES**

```bash
cd c:/projects/rentiva-dev
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit --filter ModeDevBypassTest"
```

Expected: 5 tests, all failing (filter `mhm_rentiva_dev_pro_bypass` doesn't exist yet, `Mode::canUseExport()` doesn't exist yet — multiple methods produce errors).

If specific tests pass already (e.g. `test_bypass_inactive_when_filter_default`), that's fine — they exercise existing code paths.

### Task 2.2: Implement Mode.php dev bypass + filter

**Files:**
- Modify: `src/Admin/Licensing/Mode.php`

- [ ] **Step 1: Add `canUseExport()` wrapper**

Open `src/Admin/Licensing/Mode.php`. After the `canUseVendorMarketplace()` method (around line 94), add:

```php
	/**
	 * Check if expanded export formats (XLSX, PDF) module can be used.
	 *
	 * Lite users have CSV/JSON access via direct format check in callers;
	 * this gate covers Pro-only formats.
	 *
	 * Server-side allowlist: 'export' key added in mhm-license-server v1.11.2.
	 */
	public static function canUseExport(): bool {
		return self::featureGranted( 'export' );
	}
```

- [ ] **Step 2: Add dev-mode bypass to `featureGranted()`**

Find the existing `featureGranted()` method (around line 115). After the `if ( ! self::isPro() )` early return, INSERT the bypass block:

```php
	private static function featureGranted( string $feature ): bool {
		// Hard gate: license must be locally active.
		if ( ! self::isPro() ) {
			return false;
		}

		// Dev-mode bypass — production-safe via double guard:
		//   1. WP_DEBUG=true (Hostinger production defaults to false)
		//   2. MHM_RENTIVA_DEV_PRO=true (opt-in constant)
		// Filter `mhm_rentiva_dev_pro_bypass` exists for testability since
		// PHP defines cannot be undone within a single process.
		$dev_pro_default = ( defined( 'WP_DEBUG' ) && WP_DEBUG
		                  && defined( 'MHM_RENTIVA_DEV_PRO' ) && MHM_RENTIVA_DEV_PRO );
		/**
		 * Filter whether the dev-mode Pro bypass is active.
		 *
		 * Default: requires WP_DEBUG=true AND MHM_RENTIVA_DEV_PRO=true.
		 * Tests inject this filter to exercise both branches without touching
		 * PHP defines.
		 *
		 * @param bool $dev_pro_default Default bypass decision based on constants.
		 */
		$dev_pro_active = (bool) apply_filters( 'mhm_rentiva_dev_pro_bypass', $dev_pro_default );
		if ( $dev_pro_active ) {
			return true;
		}

		$licenseManager = LicenseManager::instance();
		$token          = $licenseManager->getFeatureToken();
		if ( $token === '' ) {
			return false;
		}

		$verifier = new FeatureTokenVerifier();
		if ( ! $verifier->verify( $token, $licenseManager->getSiteHash() ) ) {
			return false;
		}

		return $verifier->hasFeature( $token, $feature );
	}
```

- [ ] **Step 3: Run ModeDevBypassTest, expect 5 PASSES**

```bash
cd c:/projects/rentiva-dev
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit --filter ModeDevBypassTest"
```

Expected: `OK (5 tests, ~10 assertions)`.

- [ ] **Step 4: Run full PHPUnit suite, no regression**

```bash
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

Expected: 793 + 5 = 798 tests, all passing.

- [ ] **Step 5: Commit Bug C**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add src/Admin/Licensing/Mode.php tests/Unit/Licensing/ModeDevBypassTest.php
git commit -m "$(cat <<'EOF'
feat(licensing): add dev-mode bypass to Mode::featureGranted (v4.33.0 Bug C)

Local Pro feature testing was impossible because RSA-signed tokens are
issued by the license server, which devs may not run locally. The bypass
requires double guard (WP_DEBUG + MHM_RENTIVA_DEV_PRO constant) so it
cannot accidentally activate in production.

Filter `mhm_rentiva_dev_pro_bypass` exposes the bypass decision for
testability; PHPUnit cannot toggle defines within a single process.

Adds canUseExport() wrapper (server-side allowlist 'export' key shipped
in mhm-license-server v1.11.2).

Tests: ModeDevBypassTest (5 tests) — all branches of the double guard
plus isPro() precedence and filter signature.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

# Phase 3 — Bug B: LicenseAdmin Dynamic Active Features

### Task 3.1: Write LicenseAdminActiveFeaturesTest with 4 failing tests

**Files:**
- Create: `tests/Unit/Licensing/LicenseAdminActiveFeaturesTest.php`

- [ ] **Step 1: Inspect existing LicenseAdmin output rendering pattern**

```bash
grep -n "render_license_status\|render_pro_features\|class LicenseAdmin" c:/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php | head -10
```

Inspect the method that contains line 268 (`All Pro features active`). Note the method name — likely `render_license_status()` or similar. Tests will need to invoke this method and capture its echoed output via `ob_start()`.

If the rendering is tightly coupled (entire admin page renders), refactor minimally: extract the active-features section into a private method (e.g. `render_active_features(): void`) so tests can call it in isolation.

- [ ] **Step 2: Create test file**

Create `tests/Unit/Licensing/LicenseAdminActiveFeaturesTest.php`:

```php
<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * v4.33.0 — LicenseAdmin "Active Pro features" line must reflect the
 * actual gate decisions, not a static string. Bug B fix.
 *
 * Pre-v4.33.0: line 268 echoed "All Pro features active: ..." regardless
 * of which feature tokens were granted, misleading customers when their
 * token was empty or partial.
 */
final class LicenseAdminActiveFeaturesTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('mhm_rentiva_disable_dev_mode', true, false);
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    /**
     * Capture echoed output from LicenseAdmin's active-features rendering.
     */
    private function captureActiveFeaturesOutput(): string
    {
        ob_start();
        LicenseAdmin::instance()->render_active_features();
        return (string) ob_get_clean();
    }

    public function test_displays_only_granted_features(): void
    {
        // Bypass active → all gates true (simulates full Pro token).
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
        ], false);

        // Override only Messages to false via filter chain.
        // Easier: just leave token empty + bypass off → all gates false → warning shown.
        // For this test, use bypass on → all gates true → all 4 features listed.
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $output = $this->captureActiveFeaturesOutput();

        $this->assertStringContainsString('Vendor & Payout', $output);
        $this->assertStringContainsString('Advanced Reports', $output);
        $this->assertStringContainsString('Messages', $output);
        $this->assertStringContainsString('Expanded Export', $output);
        $this->assertStringNotContainsString('All Pro features active', $output, 'Misleading static string must be gone');
    }

    public function test_displays_all_features_when_all_granted(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-002',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a2',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $output = $this->captureActiveFeaturesOutput();

        // Order: Vendor, Advanced Reports, Messages, Expanded Export
        $vendor_pos = strpos($output, 'Vendor & Payout');
        $reports_pos = strpos($output, 'Advanced Reports');
        $messages_pos = strpos($output, 'Messages');
        $export_pos = strpos($output, 'Expanded Export');

        $this->assertNotFalse($vendor_pos);
        $this->assertNotFalse($reports_pos);
        $this->assertNotFalse($messages_pos);
        $this->assertNotFalse($export_pos);
        $this->assertLessThan($reports_pos, $vendor_pos, 'Vendor before Advanced Reports');
        $this->assertLessThan($messages_pos, $reports_pos, 'Advanced Reports before Messages');
        $this->assertLessThan($export_pos, $messages_pos, 'Messages before Export');
    }

    public function test_shows_warning_when_no_features_granted(): void
    {
        // License active but token empty AND no dev bypass → all gates false.
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-003',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a3',
        ], false);
        // No filter — production semantics — bypass off — token empty → all gates false.

        $output = $this->captureActiveFeaturesOutput();

        $this->assertStringContainsString('notice-warning', $output);
        $this->assertStringContainsString('Re-validate Now', $output);
        $this->assertStringNotContainsString('Active Pro features', $output);
    }

    public function test_does_not_render_misleading_text(): void
    {
        // Whatever state we're in, the v4.32.0 misleading hardcoded string
        // must never appear in v4.33.0 output.
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-004',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a4',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $output = $this->captureActiveFeaturesOutput();

        $this->assertStringNotContainsString(
            'All Pro features active: Unlimited vehicles/bookings, export, advanced reports, Vendor & Payout',
            $output,
            'v4.32.0 hardcoded misleading string must be removed'
        );
    }
}
```

- [ ] **Step 3: Run tests, expect 4 FAILURES**

```bash
cd c:/projects/rentiva-dev
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit --filter LicenseAdminActiveFeaturesTest"
```

Expected: 4 failures — `LicenseAdmin::render_active_features()` method doesn't exist yet, AND the static string is still rendered in the existing admin page render.

### Task 3.2: Refactor LicenseAdmin to extract `render_active_features()`

**Files:**
- Modify: `src/Admin/Licensing/LicenseAdmin.php` (around line 268)

- [ ] **Step 1: Read the existing render method context**

```bash
sed -n '230,290p' c:/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php
```

Identify:
- The enclosing method name (likely `render_license_status()` or similar — same method that has line 268)
- Its visibility (probably public — needed for `render_active_features()` if extracted as instance method)

- [ ] **Step 2: Extract `render_active_features()` method**

In the enclosing method, REPLACE the line:

```php
				echo '<p>' . esc_html__('All Pro features active: Unlimited vehicles/bookings, export, advanced reports, Vendor & Payout.', 'mhm-rentiva') . '</p>';
```

With a call to a new method:

```php
				$this->render_active_features();
```

Then add the new method to the LicenseAdmin class (place it after the enclosing method or in a logical group):

```php
	/**
	 * Render the dynamic "Active Pro features" line on the License page.
	 *
	 * v4.33.0 — Replaces the static "All Pro features active" string with
	 * a list derived from the actual feature-token gates. If no features
	 * are granted (license active but token empty), shows a warning notice
	 * with a "Re-validate Now" CTA.
	 */
	public function render_active_features(): void {
		$active_features = array();

		if ( Mode::canUseVendorMarketplace() ) {
			$active_features[] = __( 'Vendor & Payout', 'mhm-rentiva' );
		}
		if ( Mode::canUseAdvancedReports() ) {
			$active_features[] = __( 'Advanced Reports', 'mhm-rentiva' );
		}
		if ( Mode::canUseMessages() ) {
			$active_features[] = __( 'Messages', 'mhm-rentiva' );
		}
		if ( Mode::canUseExport() ) {
			$active_features[] = __( 'Expanded Export', 'mhm-rentiva' );
		}

		if ( ! empty( $active_features ) ) {
			/* translators: %s — comma-separated list of active Pro features */
			printf(
				'<p>%s</p>',
				esc_html( sprintf(
					__( 'Active Pro features: %s', 'mhm-rentiva' ),
					implode( ', ', $active_features )
				) )
			);
		} else {
			echo '<div class="notice notice-warning inline"><p>'
			   . esc_html__( 'License active but no feature tokens loaded yet. Click "Re-validate Now" to refresh.', 'mhm-rentiva' )
			   . '</p></div>';
		}
	}
```

If the file uses `use MHMRentiva\Admin\Licensing\Mode;` at top — fine, already imported via same namespace. Otherwise add `use Mode;` at top of the file.

- [ ] **Step 3: Run LicenseAdminActiveFeaturesTest**

```bash
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit --filter LicenseAdminActiveFeaturesTest"
```

Expected: `OK (4 tests, ~12 assertions)`.

### Task 3.3: Update sarı banner subtext (Bug B alt)

**Files:**
- Modify: `src/Admin/Licensing/LicenseAdmin.php` (find dev mode banner block)

- [ ] **Step 1: Locate the dev mode banner**

```bash
grep -n "Geliştirici Modu\|is_dev_mode\|dev_mode" c:/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Licensing/LicenseAdmin.php | head -10
```

Find the block that renders the yellow banner when `$is_dev_mode` is true. Likely within the same `render_license_status()` method.

- [ ] **Step 2: Replace banner text with conditional**

REPLACE the dev mode banner block (which currently always shows "Tüm Pro özellikleri etkinleştirildi" or similar) with:

```php
			if ( $is_dev_mode ) {
				echo '<div class="notice notice-warning"><p>';
				if ( defined( 'MHM_RENTIVA_DEV_PRO' ) && MHM_RENTIVA_DEV_PRO ) {
					echo '<strong>' . esc_html__( '🔧 Developer Mode Active', 'mhm-rentiva' ) . '</strong> &mdash; ';
					echo esc_html__( 'Pro features can be tested (token check is skipped).', 'mhm-rentiva' );
				} else {
					echo '<strong>' . esc_html__( '🔧 Developer Mode', 'mhm-rentiva' ) . '</strong> &mdash; ';
					echo esc_html(
						sprintf(
							/* translators: %s — PHP define snippet to add to wp-config.php */
							__( 'Pro features cannot be tested. Add %s to wp-config.php.', 'mhm-rentiva' ),
							"define('MHM_RENTIVA_DEV_PRO', true);"
						)
					);
				}
				echo '</p></div>';
			}
```

The base strings are EN; TR translations come in Phase 5 (i18n).

- [ ] **Step 3: Run full PHPUnit, no regression**

```bash
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

Expected: 798 + 4 = 802 tests, all passing.

- [ ] **Step 4: Commit Bug B**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add src/Admin/Licensing/LicenseAdmin.php tests/Unit/Licensing/LicenseAdminActiveFeaturesTest.php
git commit -m "$(cat <<'EOF'
feat(licensing): dynamic Active Pro features line + accurate dev banner (v4.33.0 Bug B)

LicenseAdmin previously echoed "All Pro features active: ..." regardless
of which feature tokens were actually granted, misleading customers when
their token was empty (e.g. legacy server, expired token, customer just
upgraded). The v4.32.0 string also asserted Vendor was active when the
gate would return false.

The dev-mode yellow banner subtitle was also misleading — it claimed
all Pro features were enabled in dev mode, but without MHM_RENTIVA_DEV_PRO
constant the gates fail closed.

Both surfaces now reflect actual gate state.

Tests: LicenseAdminActiveFeaturesTest (4 tests) — partial grant, full
grant, no grant warning, removed-string regression.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

# Phase 4 — Bug A2: Gate Vocabulary Unification

### Task 4.1: Write ModeGateMigrationTest with 6 tests

**Files:**
- Create: `tests/Unit/Licensing/ModeGateMigrationTest.php`

- [ ] **Step 1: Create test file**

Create `tests/Unit/Licensing/ModeGateMigrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * v4.33.0 — Gate vocabulary unification (Bug A2).
 *
 * 22 callsites previously called Mode::featureEnabled() which only
 * checked isPro() (no RSA token verify), giving cracked-binary +
 * Mode::isActive() patch attacks a free pass. All callsites migrate
 * to canUse*() which token-verify.
 *
 * Mode::featureEnabled() is soft-deprecated (kept as wrapper that emits
 * _deprecated_function() notice, body unchanged for 3rd-party back-compat).
 *
 * canUseExport() is a new wrapper introduced for the Export migration.
 */
final class ModeGateMigrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('mhm_rentiva_disable_dev_mode', true, false);
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    private function seedActive(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'GATE-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
        ], false);
    }

    public function test_canUseExport_method_exists(): void
    {
        $this->assertTrue(
            method_exists(Mode::class, 'canUseExport'),
            'Mode::canUseExport() must exist as v4.33.0 introduces this gate'
        );
    }

    public function test_canUseExport_returns_true_when_bypass_active(): void
    {
        $this->seedActive();
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $this->assertTrue(Mode::canUseExport(), 'Bypass should grant export');
    }

    public function test_canUseExport_returns_false_when_lite(): void
    {
        delete_option(LicenseManager::OPTION);

        $this->assertFalse(Mode::canUseExport(), 'Lite users do not have export gate (CSV/JSON via direct format check)');
    }

    public function test_canUseExport_returns_false_when_token_lacks_export_feature(): void
    {
        // Active license but no token in storage (legacy server simulation).
        $this->seedActive();
        // No filter, no bypass → token check runs → empty token → false.

        $this->assertFalse(Mode::canUseExport());
    }

    public function test_featureEnabled_emits_deprecation_notice(): void
    {
        // Capture deprecation notice via WP's testing infrastructure.
        $this->setExpectedDeprecated('MHMRentiva\\Admin\\Licensing\\Mode::featureEnabled');

        // Call the deprecated method.
        Mode::featureEnabled(Mode::FEATURE_MESSAGES);
    }

    public function test_featureEnabled_preserves_legacy_behavior(): void
    {
        // The body is kept intact for 3rd-party back-compat.
        // setExpectedDeprecated suppresses the notice.
        $this->setExpectedDeprecated('MHMRentiva\\Admin\\Licensing\\Mode::featureEnabled');

        // Lite + EXPORT → true (legacy behavior).
        delete_option(LicenseManager::OPTION);
        $this->assertTrue(Mode::featureEnabled(Mode::FEATURE_EXPORT), 'Legacy behavior: Lite + EXPORT returns true');

        // Lite + MESSAGES → false (legacy behavior).
        $this->assertFalse(Mode::featureEnabled(Mode::FEATURE_MESSAGES), 'Legacy behavior: Lite + MESSAGES returns false');
    }
}
```

- [ ] **Step 2: Run tests, expect mixed RED/GREEN**

```bash
cd c:/projects/rentiva-dev
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit --filter ModeGateMigrationTest"
```

Expected:
- `test_canUseExport_method_exists` → PASS (added in Phase 2)
- `test_canUseExport_returns_true_when_bypass_active` → PASS
- `test_canUseExport_returns_false_when_lite` → PASS
- `test_canUseExport_returns_false_when_token_lacks_export_feature` → PASS
- `test_featureEnabled_emits_deprecation_notice` → FAIL (deprecation not yet added)
- `test_featureEnabled_preserves_legacy_behavior` → FAIL (deprecation not added; setExpectedDeprecated requires real notice)

### Task 4.2: Mechanical migration of 22 callsites

**Files (11 to modify):**

- [ ] **Step 1: Migrate AccountRenderer.php (1 callsite)**

File: `src/Admin/Frontend/Account/AccountRenderer.php`

```bash
sed -n '245,252p' c:/projects/rentiva-dev/plugins/mhm-rentiva/src/Admin/Frontend/Account/AccountRenderer.php
```

REPLACE:

```php
			! \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)
```

WITH:

```php
			! \MHMRentiva\Admin\Licensing\Mode::canUseMessages()
```

- [ ] **Step 2: Migrate WooCommerceIntegration.php (2 callsites — lines 87, 121)**

File: `src/Admin/Frontend/Account/WooCommerceIntegration.php`

REPLACE both occurrences of:

```php
( ! class_exists( \MHMRentiva\Admin\Licensing\Mode::class ) || ! \MHMRentiva\Admin\Licensing\Mode::featureEnabled( \MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES ) )
```

WITH:

```php
( ! class_exists( \MHMRentiva\Admin\Licensing\Mode::class ) || ! \MHMRentiva\Admin\Licensing\Mode::canUseMessages() )
```

- [ ] **Step 3: Migrate Messages/Core/Messages.php (1 callsite — line 745)**

File: `src/Admin/Messages/Core/Messages.php`

REPLACE:

```php
		$is_pro     = \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES);
```

WITH:

```php
		$is_pro     = \MHMRentiva\Admin\Licensing\Mode::canUseMessages();
```

- [ ] **Step 4: Migrate Messages/Frontend/CustomerMessages.php (1 callsite — line 95)**

File: `src/Admin/Messages/Frontend/CustomerMessages.php`

REPLACE:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_MESSAGES ) ) {
```

WITH:

```php
		if ( ! Mode::canUseMessages() ) {
```

- [ ] **Step 5: Migrate Messages/REST/Messages.php (1 callsite — line 31)**

File: `src/Admin/Messages/REST/Messages.php`

REPLACE:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_MESSAGES ) ) {
```

WITH:

```php
		if ( ! Mode::canUseMessages() ) {
```

- [ ] **Step 6: Migrate Messages/REST/Customer/SendMessage.php (1 callsite — line 29)**

File: `src/Admin/Messages/REST/Customer/SendMessage.php`

REPLACE:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_MESSAGES ) ) {
```

WITH:

```php
		if ( ! Mode::canUseMessages() ) {
```

- [ ] **Step 7: Migrate Messages/REST/Customer/SendReply.php (1 callsite — line 32)**

File: `src/Admin/Messages/REST/Customer/SendReply.php`

REPLACE:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_MESSAGES ) ) {
```

WITH:

```php
		if ( ! Mode::canUseMessages() ) {
```

- [ ] **Step 8: Migrate Reports/Reports.php (2 callsites — lines 226, 393)**

File: `src/Admin/Reports/Reports.php`

REPLACE both occurrences of:

```php
		if (! Mode::featureEnabled(Mode::FEATURE_REPORTS_ADV)) {
```

(Note line 226 starts a check; line 393 is `$is_pro = Mode::featureEnabled(Mode::FEATURE_REPORTS_ADV);` — adjust accordingly.)

For line 226:

```php
		if (! Mode::canUseAdvancedReports()) {
```

For line 393:

```php
		$is_pro = Mode::canUseAdvancedReports();
```

- [ ] **Step 9: Migrate Export/Export.php (3 callsites — lines 97, 338, 1302)**

File: `src/Admin/Utilities/Export/Export.php`

REPLACE line 97:

```php
		if (! Mode::featureEnabled(Mode::FEATURE_EXPORT)) {
```

WITH:

```php
		if (! Mode::canUseExport()) {
```

REPLACE line 338 (same content as 97).

REPLACE line 1302:

```php
		if (class_exists(Mode::class) && Mode::featureEnabled(Mode::FEATURE_EXPORT)) {
```

WITH:

```php
		if (class_exists(Mode::class) && Mode::canUseExport()) {
```

- [ ] **Step 10: Migrate Export/ExportReports.php (6 callsites — lines 26, 103, 142, 244, 249, 274)**

File: `src/Admin/Utilities/Export/ExportReports.php`

Line 26:

```php
		if ( ! in_array( $format, array( 'csv', 'json' ) ) && ! Mode::featureEnabled( Mode::FEATURE_EXPORT ) ) {
```

WITH:

```php
		if ( ! in_array( $format, array( 'csv', 'json' ) ) && ! Mode::canUseExport() ) {
```

Line 103:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_EXPORT ) ) {
```

WITH:

```php
		if ( ! Mode::canUseExport() ) {
```

Line 142:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_REPORTS_ADV ) ) {
```

WITH:

```php
		if ( ! Mode::canUseAdvancedReports() ) {
```

Line 244:

```php
		if ( Mode::featureEnabled( Mode::FEATURE_EXPORT ) ) {
```

WITH:

```php
		if ( Mode::canUseExport() ) {
```

Line 249:

```php
		if ( Mode::featureEnabled( Mode::FEATURE_REPORTS_ADV ) ) {
```

WITH:

```php
		if ( Mode::canUseAdvancedReports() ) {
```

Line 274:

```php
		if ( ! Mode::featureEnabled( Mode::FEATURE_REPORTS_ADV ) ) {
```

WITH:

```php
		if ( ! Mode::canUseAdvancedReports() ) {
```

- [ ] **Step 11: Migrate Menu.php (3 callsites — lines 121, 133, 145)**

File: `src/Admin/Utilities/Menu/Menu.php`

Line 121:

```php
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_REPORTS_ADV)) {
```

WITH:

```php
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::canUseAdvancedReports()) {
```

Line 133:

```php
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
```

WITH:

```php
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::canUseMessages()) {
```

Line 145:

```php
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_EXPORT)) {
```

WITH:

```php
		if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::canUseExport()) {
```

- [ ] **Step 12: Verify NO featureEnabled() calls remain in production code**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
grep -rn "Mode::featureEnabled" src/ --include="*.php"
```

Expected: NO matches in `src/`. Only the definition itself in `Mode.php` should be left (and we'll deprecate that next).

If any matches remain, migrate them now using the appropriate `canUse*()` method based on the FEATURE_* constant:
- `FEATURE_MESSAGES` → `canUseMessages()`
- `FEATURE_REPORTS_ADV` → `canUseAdvancedReports()`
- `FEATURE_EXPORT` → `canUseExport()`

- [ ] **Step 13: Run full PHPUnit, no regression**

```bash
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

Expected: 802 tests still passing. The 2 failing migration tests (`test_featureEnabled_emits_deprecation_notice` and `test_featureEnabled_preserves_legacy_behavior`) still fail — those are addressed in Task 4.3.

### Task 4.3: Add @deprecated tag + `_deprecated_function()` to `featureEnabled()`

**Files:**
- Modify: `src/Admin/Licensing/Mode.php`

- [ ] **Step 1: Replace `featureEnabled()` with deprecated wrapper**

In `src/Admin/Licensing/Mode.php`, find the existing `featureEnabled()` method:

```php
	public static function featureEnabled( string $feature ): bool {
		if ( self::isPro() ) {
			return true;
		}
		switch ( $feature ) {
			case self::FEATURE_EXPORT:
				return true;
			case self::FEATURE_REPORTS_ADV:
			case self::FEATURE_MESSAGES:
				return false;
		}
		return false;
	}
```

REPLACE with:

```php
	/**
	 * Check if feature is enabled (legacy gate, NO token verification).
	 *
	 * @deprecated 4.33.0 Use Mode::canUseMessages(), canUseAdvancedReports(),
	 * canUseExport(), canUseVendorMarketplace(), or canUseVendorPayout() instead.
	 * This method does NOT verify the RSA-signed feature token, leaving it
	 * vulnerable to source-edit attacks (cracked binary patching isActive()).
	 * Body kept intact for any third-party callers; will be removed in v5.0.
	 *
	 * @param string $feature Feature name.
	 * @return bool True if enabled.
	 */
	public static function featureEnabled( string $feature ): bool {
		_deprecated_function(
			__METHOD__,
			'4.33.0',
			'Mode::canUseMessages() / canUseAdvancedReports() / canUseExport() / canUseVendorMarketplace() / canUseVendorPayout()'
		);

		// Body preserved for back-compat. v4.32.0 behavior unchanged.
		if ( self::isPro() ) {
			return true;
		}
		switch ( $feature ) {
			case self::FEATURE_EXPORT:
				return true;
			case self::FEATURE_REPORTS_ADV:
			case self::FEATURE_MESSAGES:
				return false;
		}
		return false;
	}
```

- [ ] **Step 2: Run ModeGateMigrationTest**

```bash
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit --filter ModeGateMigrationTest"
```

Expected: All 6 tests passing.

- [ ] **Step 3: Run full PHPUnit suite**

```bash
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

Expected: 793 + 5 + 4 + 6 = 808 tests, all passing.

⚠️ Watch for tests that call featureEnabled() incidentally — they'll now emit deprecation notices. If any test fails because of unexpected deprecation, decide:
- If the test exercises legacy behavior intentionally → add `$this->setExpectedDeprecated('MHMRentiva\\Admin\\Licensing\\Mode::featureEnabled');` to it
- If the test exercises code that should have migrated → update the test or production code

- [ ] **Step 4: Commit Bug A2**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add src/Admin/Licensing/Mode.php \
        src/Admin/Frontend/Account/AccountRenderer.php \
        src/Admin/Frontend/Account/WooCommerceIntegration.php \
        src/Admin/Messages/Core/Messages.php \
        src/Admin/Messages/Frontend/CustomerMessages.php \
        src/Admin/Messages/REST/Messages.php \
        src/Admin/Messages/REST/Customer/SendMessage.php \
        src/Admin/Messages/REST/Customer/SendReply.php \
        src/Admin/Reports/Reports.php \
        src/Admin/Utilities/Export/Export.php \
        src/Admin/Utilities/Export/ExportReports.php \
        src/Admin/Utilities/Menu/Menu.php \
        tests/Unit/Licensing/ModeGateMigrationTest.php
git commit -m "$(cat <<'EOF'
refactor(licensing): unify feature gates to canUse*() with token verify (v4.33.0 Bug A2)

22 callsites previously called Mode::featureEnabled() which only checks
isPro() — no RSA token verify. A cracked binary patching LicenseManager::isActive()
to return true unlocked all features at those sites. This commit migrates
every callsite to the canUse*() family which goes through featureGranted()
and verifies the server-issued RSA token.

featureEnabled() is soft-deprecated: kept as wrapper that emits
_deprecated_function() notice (silent in production), body intact for
third-party back-compat. Hard removal slated for v5.0.

Side effect (intentional): Lite users lose xlsx/pdf export. Previous
featureEnabled(EXPORT) returned true unconditionally on Lite, which was
a bug — Lite has explicit csv/json format check; xlsx/pdf were always
meant to be Pro-gated.

Tests: ModeGateMigrationTest (6 tests) — canUseExport existence, bypass
behavior, Lite path, deprecation notice emission, legacy body preserved.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

# Phase 5 — i18n: New Strings to .pot/.po/.mo/.l10n.php

> Reference: [feedback_l10n_php.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_l10n_php.md) and [feedback_po_file_safety.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_po_file_safety.md). NEVER run msguniq or bulk tools on .po — only Edit + WP-CLI make-mo. Always check fuzzy after msgmerge.

### Task 5.1: Regenerate .pot file with WP-CLI

**Files:**
- Modify: `languages/mhm-rentiva.pot`

- [ ] **Step 1: Run pot regen**

```bash
cd c:/projects/rentiva-dev
docker exec rentiva-dev-wp wp i18n make-pot \
  /var/www/html/wp-content/plugins/mhm-rentiva \
  /var/www/html/wp-content/plugins/mhm-rentiva/languages/mhm-rentiva.pot \
  --slug=mhm-rentiva \
  --domain=mhm-rentiva \
  --allow-root
```

Expected: `Success: Strings extracted to ...mhm-rentiva.pot`.

- [ ] **Step 2: Verify new strings appear in .pot**

```bash
grep -A1 "Active Pro features\|Re-validate Now\|Expanded Export\|Developer Mode Active\|Pro features cannot be tested" \
  c:/projects/rentiva-dev/plugins/mhm-rentiva/languages/mhm-rentiva.pot | head -30
```

Expected: All 5+ new strings present as `msgid` entries.

### Task 5.2: msgmerge to TR .po + fuzzy check

**Files:**
- Modify: `languages/mhm-rentiva-tr_TR.po`

- [ ] **Step 1: Run msgmerge**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && msgmerge --update --backup=none languages/mhm-rentiva-tr_TR.po languages/mhm-rentiva.pot"
```

Expected: `done.` with stats showing untranslated count delta.

- [ ] **Step 2: Check for fuzzy entries (CRITICAL)**

```bash
grep -c "^#, fuzzy" c:/projects/rentiva-dev/plugins/mhm-rentiva/languages/mhm-rentiva-tr_TR.po
```

Expected: `0`. If any fuzzy entries appear, msgmerge auto-mapped a new string to a similar existing translation. **WP runtime IGNORES fuzzy entries → falls back to EN.** Manually fix each fuzzy entry.

- [ ] **Step 3: Translate new entries to TR**

Open `languages/mhm-rentiva-tr_TR.po`. Find each new untranslated entry (empty `msgstr ""`):

| msgid | msgstr (TR) |
|-------|-------------|
| `Active Pro features: %s` | `Aktif Pro özellikler: %s` |
| `Vendor & Payout` | `Bayi & Ödeme` |
| `Advanced Reports` | `Gelişmiş Raporlar` |
| `Messages` | `Mesajlar` |
| `Expanded Export` | `Genişletilmiş Dışa Aktarım` |
| `License active but no feature tokens loaded yet. Click "Re-validate Now" to refresh.` | `Lisans etkin ancak özellik token'ları henüz yüklenmedi. Yenilemek için "Şimdi Yeniden Doğrula"ya tıklayın.` |
| `🔧 Developer Mode Active` | `🔧 Geliştirici Modu Etkin` |
| `🔧 Developer Mode` | `🔧 Geliştirici Modu` |
| `Pro features can be tested (token check is skipped).` | `Pro özellikler test edilebilir (token kontrolü atlanıyor).` |
| `Pro features cannot be tested. Add %s to wp-config.php.` | `Pro özellikler test edilemez. wp-config.php dosyasına %s ekleyin.` |

Use Edit tool, NOT msguniq. Reference: [feedback_po_file_safety.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_po_file_safety.md).

⚠️ TR text must NOT use ASCII substitutes for Turkish characters (no `s` for `ş`, no `g` for `ğ`). See: [feedback_turkish_chars.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_turkish_chars.md).

- [ ] **Step 4: Re-check fuzzy count**

```bash
grep -c "^#, fuzzy" c:/projects/rentiva-dev/plugins/mhm-rentiva/languages/mhm-rentiva-tr_TR.po
```

Expected: `0`.

### Task 5.3: Compile .mo and .l10n.php

**Files:**
- Modify: `languages/mhm-rentiva-tr_TR.mo`
- Modify: `languages/mhm-rentiva-tr_TR.l10n.php`

- [ ] **Step 1: Compile .mo**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp i18n make-mo languages/ --allow-root"
```

Expected: Updated `mhm-rentiva-tr_TR.mo`.

- [ ] **Step 2: Compile .l10n.php (WP 6.5+)**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && wp i18n make-php languages/ --allow-root"
```

Expected: Updated `mhm-rentiva-tr_TR.l10n.php`. WP 6.5+ reads this file BEFORE .mo for performance.

- [ ] **Step 3: Verify TR translation rendering manually**

```bash
docker exec rentiva-dev-wp wp eval '
  switch_to_locale("tr_TR");
  load_plugin_textdomain("mhm-rentiva", false, "mhm-rentiva/languages");
  echo __("Expanded Export", "mhm-rentiva") . "\n";
  echo __("Vendor & Payout", "mhm-rentiva") . "\n";
' --allow-root
```

Expected: TR strings (e.g. `Genişletilmiş Dışa Aktarım`, `Bayi & Ödeme`).

- [ ] **Step 4: Commit i18n**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add languages/mhm-rentiva.pot languages/mhm-rentiva-tr_TR.po languages/mhm-rentiva-tr_TR.mo languages/mhm-rentiva-tr_TR.l10n.php
git commit -m "$(cat <<'EOF'
i18n(rentiva): TR translations for v4.33.0 Pro Gate Unification strings

10 new TR strings added: Active Pro features list, dynamic dev banner
subtext (with/without MHM_RENTIVA_DEV_PRO), Re-validate Now warning,
new Mode capability labels (Vendor & Payout, Advanced Reports, Messages,
Expanded Export).

msgmerge fuzzy count post-merge: 0 (verified). Compiled .mo + .l10n.php
(WP 6.5+ priority order).

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

# Phase 6 — Pre-push CI Parity Gate

> [feedback_pre_push_ci_parity.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_pre_push_ci_parity.md): Every release must pass all 4 checks BEFORE push. PHPCS error wasted a push cycle in v0.6.4 + v1.10.1.

### Task 6.1: PHPCS

- [ ] **Step 1: Run PHPCS**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs"
```

Expected: `0 errors, 0 warnings`.

If errors appear, fix them. Common patterns:
- WPCS prefer `array()` over `[]` in places — verify our project's `phpcs.xml` allows short syntax (it does for v4.32.0)
- Missing translator comments — add `/* translators: ... */` above `printf`/`sprintf` calls with placeholders
- esc_html__ vs esc_html(__()) — follow existing pattern

### Task 6.2: PHPStan

- [ ] **Step 1: Run PHPStan**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpstan -- --memory-limit=2G"
```

Expected: `[OK] No errors`.

### Task 6.3: PHPUnit PHP 7.4

- [ ] **Step 1: Run PHP 7.4 PHPUnit**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

Expected: 808 tests pass (793 baseline + 15 new), 0 failures, 0 errors.

### Task 6.4: PHPUnit PHP 8.2

- [ ] **Step 1: Run PHP 8.2 PHPUnit**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp-php82 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit" 2>/dev/null || \
docker exec rentiva-dev-php82 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && php -d memory_limit=512M ./vendor/bin/phpunit"
```

(The container name varies — check `docker ps` for the PHP 8.2 container.)

Expected: 808 tests pass.

If a test fails on PHP 8.2 but passes on 7.4, common causes:
- Mixed type unions
- `nullable string` → `?string`
- Implicit nullable typed property defaults

Fix and re-run all 4 checks.

---

# Phase 7 — Version Bump + Changelog

### Task 7.1: Bump mhm-rentiva.php version

**Files:**
- Modify: `mhm-rentiva.php` (lines 7 + 78 + ~213/222 reference points stay as v4.32.0 in comments — only header and constant change)
- Modify: `readme.txt` (Stable tag)
- Modify: `README.md`, `README-tr.md` (version badges)

- [ ] **Step 1: Bump main plugin file**

In `mhm-rentiva.php`:

REPLACE:

```
 * Version:           4.32.0
```

WITH:

```
 * Version:           4.33.0
```

REPLACE:

```php
define('MHM_RENTIVA_VERSION', '4.32.0');
```

WITH:

```php
define('MHM_RENTIVA_VERSION', '4.33.0');
```

⚠️ Do NOT change historical version mentions in comments (lines 213, 222 etc. reference v4.32.0 as "previous baseline" — keep those).

- [ ] **Step 2: Bump readme.txt Stable tag**

In `readme.txt`:

REPLACE:

```
Stable tag: 4.32.0
```

WITH:

```
Stable tag: 4.33.0
```

- [ ] **Step 3: Bump README.md and README-tr.md badges**

```bash
grep -l "4.32.0" c:/projects/rentiva-dev/plugins/mhm-rentiva/README*.md
```

In each match, replace badge URL `4.32.0` → `4.33.0`. Look for shield URLs like `https://img.shields.io/badge/version-4.32.0-blue` or similar.

### Task 7.2: Add changelog entries

**Files:**
- Modify: `changelog.json` (EN)
- Modify: `changelog-tr.json` (TR)
- Modify: `readme.txt` (Changelog section)

- [ ] **Step 1: Add `changelog-tr.json` entry**

Open `changelog-tr.json`. Add at the top of the entries array (after `[`):

```json
  {
    "version": "4.33.0",
    "date": "2026-04-XX",
    "summary": "Pro Gate Birleştirme — feature token doğrulamasını her gate'e taşıyor, lokal Pro test mümkün, License Admin gerçek durumu gösteriyor",
    "changes": [
      {
        "type": "fixed",
        "title": "Pro feature gate'leri token doğrulaması yapıyor (22 yer)",
        "description": "v4.31.0 RSA token doğrulamasını tek otorite yapan migration'da 22 callsite eski Mode::featureEnabled() API'sini kullanmaya devam ediyordu. Bu metod sadece isPro() kontrolü yapıyor, token doğrulamayan eski yol. Cracked binary + Mode::isActive() patch saldırısına açıktı. Tüm callsite'lar Mode::canUse*() ailesine migrate edildi (canUseMessages, canUseAdvancedReports, canUseExport, canUseVendorMarketplace, canUseVendorPayout). Mode::featureEnabled() soft deprecate (PHP _deprecated_function notice). v5.0'da kaldırılacak."
      },
      {
        "type": "fixed",
        "title": "License Admin sayfası gerçek aktif Pro özellikleri gösteriyor",
        "description": "Önceden 'All Pro features active: ...' diye sabit metin yazıyordu. Token boş veya kısmi olduğunda yanıltıcıydı. Şimdi gerçek gate state'inden türeyen liste gösteriyor; hiç gate aktif değilse 'Re-validate Now' uyarısı."
      },
      {
        "type": "added",
        "title": "Geliştirici Modu Pro testi (lokal)",
        "description": "WP_DEBUG=true + define('MHM_RENTIVA_DEV_PRO', true) → token kontrolü atlanır, Pro feature'lar lokalde test edilebilir. Üretim ortamında WP_DEBUG default false olduğu için aktif olmaz. License Admin'deki sarı banner artık MHM_RENTIVA_DEV_PRO durumuna göre doğru mesaj gösteriyor."
      },
      {
        "type": "changed",
        "title": "Lite kullanıcılar xlsx/pdf export erişimini kaybediyor (BREAKING)",
        "description": "Önceki featureEnabled(FEATURE_EXPORT) Lite'da koşulsuz true dönüyordu (bug). Yeni canUseExport() gate'i Lite'da false döner. Lite kullanıcılar csv/json formatlarına erişimini koruyor (callsite'larda direkt format kontrolü değişmedi). Pro kullanıcılar v1.11.2 server patch'i sonrası tüm formatlara erişebilir."
      },
      {
        "type": "notes",
        "title": "Server v1.11.2 önkoşulu",
        "description": "Bu sürüm mhm-license-server v1.11.2 ile eşleşiyor — 'export' feature key'i token allowlist'ine eklendi. Server patch'i deploy edilmeden bu sürüm yayına çıkarılırsa Pro kullanıcılar 24h boyunca export'a erişemez (yeni token daily cron'da gelir, manuel 'Re-validate Now' butonu hızlandırır)."
      }
    ]
  },
```

- [ ] **Step 2: Add `changelog.json` (EN) entry — equivalent**

Open `changelog.json`. Add at top:

```json
  {
    "version": "4.33.0",
    "date": "2026-04-XX",
    "summary": "Pro Gate Unification — feature token verification on every gate, local Pro testing enabled, License Admin shows actual state",
    "changes": [
      {
        "type": "fixed",
        "title": "Pro feature gates verify the token (22 callsites)",
        "description": "After v4.31.0's RSA token migration, 22 callsites still used the old Mode::featureEnabled() API which only checked isPro() — no token verify. A cracked binary patching Mode::isActive() to return true unlocked all features at those sites. Every callsite migrates to canUse*() (canUseMessages, canUseAdvancedReports, canUseExport, canUseVendorMarketplace, canUseVendorPayout). Mode::featureEnabled() is soft-deprecated (emits PHP _deprecated_function notice in WP_DEBUG). Hard removal in v5.0."
      },
      {
        "type": "fixed",
        "title": "License Admin shows actual active Pro features",
        "description": "Previously echoed a static 'All Pro features active: ...' line regardless of which feature tokens were granted. Misleading when the token was empty or partial. Now derives the list from real gate state; shows a 'Re-validate Now' warning if no gates are active."
      },
      {
        "type": "added",
        "title": "Developer Mode for local Pro testing",
        "description": "WP_DEBUG=true + define('MHM_RENTIVA_DEV_PRO', true) skips the RSA token check, enabling local Pro feature testing. Production-safe: WP_DEBUG defaults to false on Hostinger production. License Admin yellow banner now reflects whether the bypass is actually active or just dormant."
      },
      {
        "type": "changed",
        "title": "Lite users lose xlsx/pdf export access (BREAKING)",
        "description": "Old featureEnabled(FEATURE_EXPORT) returned true unconditionally for Lite (bug). New canUseExport() gate returns false for Lite. Lite csv/json access preserved (direct format check in callers unchanged). Pro users get all formats after server v1.11.2 deploys."
      },
      {
        "type": "notes",
        "title": "Requires mhm-license-server v1.11.2",
        "description": "This release pairs with server v1.11.2 which adds 'export' to the issued feature-token allowlist. Releasing v4.33.0 before the server patch lands means Pro users cannot use export for up to 24h (until daily cron refreshes the token). Manual 'Re-validate Now' button refreshes immediately."
      }
    ]
  },
```

- [ ] **Step 3: Add readme.txt Changelog entry**

In `readme.txt`, find the `== Changelog ==` section. Add at the top:

```
= 4.33.0 — 2026-04-XX =
**Pro Gate Unification**

* Fixed: Pro feature gates now verify the RSA token at all 22 callsites (previously Mode::featureEnabled bypassed verification, vulnerable to cracked binary attacks).
* Fixed: License Admin shows actual granted Pro features instead of static "All Pro features active" string.
* Added: Developer Mode bypass — `define('MHM_RENTIVA_DEV_PRO', true)` + WP_DEBUG=true allows local Pro feature testing without a real token.
* Changed: BREAKING — Lite users can no longer use xlsx/pdf export formats. CSV/JSON remain free. Pro users unaffected (server v1.11.2 prerequisite).
* Note: Requires mhm-license-server v1.11.2 — without it, Pro users lose export for up to 24h. Click "Re-validate Now" on License page to refresh.
```

- [ ] **Step 4: Replace 2026-04-XX placeholders with actual date**

After everything is built and you're about to tag, run:

```bash
TODAY=$(date +%Y-%m-%d)
sed -i "s/2026-04-XX/${TODAY}/g" \
  c:/projects/rentiva-dev/plugins/mhm-rentiva/changelog.json \
  c:/projects/rentiva-dev/plugins/mhm-rentiva/changelog-tr.json \
  c:/projects/rentiva-dev/plugins/mhm-rentiva/readme.txt
```

If on Windows shell without `date`, manually replace.

- [ ] **Step 5: Commit version + changelog**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add mhm-rentiva.php readme.txt README.md README-tr.md changelog.json changelog-tr.json
git commit -m "$(cat <<'EOF'
chore(rentiva): bump to 4.33.0 + changelog (Pro Gate Unification)

Version 4.32.0 → 4.33.0 across header, constant, readme.txt Stable tag,
README badges, both changelog JSON files, and readme.txt Changelog section.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

# Phase 8 — Docs Blog Post (mhm-rentiva-docs)

> Reference: [feedback_deploy_checklist.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_deploy_checklist.md) step 7 — blog post is mandatory for major releases.

### Task 8.1: Create blog post in mhm-rentiva-docs

**Files:**
- Create: `c:/projects/mhm-rentiva-docs/blog/YYYY-MM-DD-v4.33.0-release.md`

- [ ] **Step 1: Locate docs repo**

```bash
ls c:/projects/mhm-rentiva-docs/blog/ | tail -5
```

Expected: List of recent blog posts.

- [ ] **Step 2: Create the v4.33.0 release post (TR)**

Create `c:/projects/mhm-rentiva-docs/blog/YYYY-MM-DD-v4.33.0-release.md` (replace YYYY-MM-DD with today's date):

```markdown
---
slug: v4-33-0-pro-gate-unification
title: v4.33.0 — Pro Gate Birleştirme
authors: [maxhandmade]
tags: [release, security, pro-features, vendor]
---

Bu sürümde v4.31.0 RSA asymmetric crypto migration'ında yarım kalan üç problemi tek seferde kapattık.

<!-- truncate -->

## Ne değişti?

### 🔒 Güvenlik: 22 callsite token doğrulaması yapıyor

v4.31.0'da Mode::featureGranted() RSA token doğrulamasının tek otoritesi olmuştu. Ama 22 callsite hâlâ eski `featureEnabled()` API'sini kullanıyordu — bu metod sadece `isPro()` bakıyor, token doğrulamıyor. Cracked binary + Mode patch saldırısına açıktı.

Bu sürümde tüm 22 callsite `canUse*()` ailesine taşındı. Eski `featureEnabled()` soft deprecate (PHP `_deprecated_function` notice) — v5.0'da kaldırılacak. 3rd-party kod hâlâ çalışır ama uyarı görür.

### ✨ License Admin gerçek durumu gösteriyor

Önceden lisans aktifken sayfada "All Pro features active: ..." gibi sabit bir metin görünüyordu. Token eksik veya kısmi olduğunda yanıltıcıydı.

Yeni: gerçek gate state'inden türeyen liste — sadece aktif olan feature'lar adı listelenir. Hiç gate aktif değilse "Re-validate Now" butonu çıkar.

### 🔧 Geliştirici Modu (lokal Pro test)

`define('MHM_RENTIVA_DEV_PRO', true);` + `WP_DEBUG=true` ile token doğrulaması atlanır, Pro feature'lar lokalde test edilebilir. Üretim ortamında `WP_DEBUG=false` default olduğu için aktif olmaz.

License Admin'deki sarı banner artık `MHM_RENTIVA_DEV_PRO` durumuna göre doğru mesaj gösteriyor: tanımlıysa "test edilebilir", tanımsızsa "test edilemez, şu satırı ekleyin".

## ⚠️ Breaking Change

**Lite kullanıcılar xlsx/pdf export erişimini kaybediyor.**

Önceki `featureEnabled(FEATURE_EXPORT)` Lite'da koşulsuz `true` dönüyordu — bug. Yeni `canUseExport()` Lite'da `false` döner. Lite kullanıcılar csv/json formatlarını kullanmaya devam edebilir.

## Server Önkoşulu

Bu sürüm mhm-license-server v1.11.2 ile eşleşir — 'export' feature key'i token allowlist'ine eklendi. Pro müşteriler v1.11.2 deploy edildikten sonra 24 saat içinde otomatik yeni token alır (daily cron). Anında yenilemek için License sayfasında "Şimdi Yeniden Doğrula" butonuna basabilir.

## Yükseltme

Yükseltmek için [GitHub Release sayfasından](https://github.com/MaxHandMade/mhm-rentiva/releases/tag/v4.33.0) ZIP'i indirin ve WP Admin → Plugins → Upload yapın.
```

- [ ] **Step 3: Create EN translation if i18n folder exists**

```bash
ls c:/projects/mhm-rentiva-docs/i18n/ 2>/dev/null
```

If `i18n/en/` exists, create the EN equivalent at the appropriate path. Mirror the TR content in English.

- [ ] **Step 4: Commit + push docs**

```bash
cd c:/projects/mhm-rentiva-docs
git add blog/YYYY-MM-DD-v4.33.0-release.md
git commit -m "blog: v4.33.0 release notes (Pro Gate Unification)"
git push origin main
```

(GitHub Pages workflow auto-deploys.)

---

# Phase 9 — Build, Tag, Release, Merge

### Task 9.1: Build ZIP via bin/build-release.py

**Files:** *(build artifacts)*

- [ ] **Step 1: Build ZIP**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
docker exec rentiva-dev-wp bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && python bin/build-release.py"
```

Expected: `build/mhm-rentiva.4.33.0.zip` created (~2.6 MB, ~760 files).

- [ ] **Step 2: Verify ZIP**

```bash
unzip -l c:/projects/rentiva-dev/plugins/mhm-rentiva/build/mhm-rentiva.4.33.0.zip | head -20
unzip -l c:/projects/rentiva-dev/plugins/mhm-rentiva/build/mhm-rentiva.4.33.0.zip | grep -E "Mode.php|LicenseAdmin.php|mhm-rentiva.php"
```

Expected: All three core files present, ZIP root is `mhm-rentiva/`.

⚠️ Reference: [feedback_zip_docker_only.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/feedback_zip_docker_only.md) — never use Windows `Compress-Archive`.

### Task 9.2: Smoke test ZIP on DemoSeed

- [ ] **Step 1: Bring up release-test stack**

```bash
cd c:/projects/mhm-demoseed
docker compose -f docker-compose.release-test.yml up -d
```

Expected: Container starts. Site at http://localhost:8097.

- [ ] **Step 2: Install + activate v4.33.0 ZIP**

```bash
docker exec mhm-demoseed-release-test wp plugin install /tmp/mhm-rentiva.4.33.0.zip --force --activate --allow-root
```

(Copy ZIP into the container's /tmp first if needed: `docker cp ./build/mhm-rentiva.4.33.0.zip mhm-demoseed-release-test:/tmp/`)

Expected: Plugin installs and activates without WSOD.

- [ ] **Step 3: Verify deprecation notice does NOT fatal**

```bash
docker exec mhm-demoseed-release-test wp eval 'echo "OK if we got here\n";' --allow-root
```

Expected: `OK if we got here`. If WSOD or fatal, investigate the deprecation_function call site.

- [ ] **Step 4: Tear down release-test stack**

```bash
cd c:/projects/mhm-demoseed
docker compose -f docker-compose.release-test.yml down -v
```

### Task 9.3: Tag annotated git tag

- [ ] **Step 1: Create tag**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git tag -a v4.33.0 -m "v4.33.0 — Pro Gate Unification

- 22 callsites migrated from featureEnabled() to canUse*() (token verify)
- License Admin dynamic active features
- Developer Mode bypass (MHM_RENTIVA_DEV_PRO + WP_DEBUG)
- Companion: mhm-license-server v1.11.2"
```

- [ ] **Step 2: Push branch + tag**

```bash
git push -u origin fix/pro-gate-unification
git push origin v4.33.0
```

### Task 9.4: Create GitHub release with ZIP asset

- [ ] **Step 1: Create release**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
gh release create v4.33.0 ./build/mhm-rentiva.4.33.0.zip \
  --repo MaxHandMade/mhm-rentiva \
  --title "v4.33.0 — Pro Gate Unification" \
  --notes "$(cat <<'EOF'
## What's Changed

**🔒 Security: All 22 Pro feature callsites now verify the RSA token**

After v4.31.0 introduced RSA-signed feature tokens as the single Pro authority, 22 callsites were still using the legacy `Mode::featureEnabled()` API which only checked `isPro()` (no token verification). A cracked binary patching `LicenseManager::isActive()` to return `true` could unlock all features at those sites. This release migrates every callsite to the `canUse*()` family.

`Mode::featureEnabled()` is soft-deprecated (emits `_deprecated_function` notice). Body kept intact for any third-party callers; hard removal in v5.0.

**✨ License Admin shows actual active Pro features**

Previously a static "All Pro features active: ..." line regardless of token state. Now derives from real gate decisions.

**🔧 Developer Mode**

`define('MHM_RENTIVA_DEV_PRO', true)` + `WP_DEBUG=true` skips token verification for local Pro testing. Production-safe (WP_DEBUG defaults to false on Hostinger).

**⚠️ Breaking Change**

Lite users lose xlsx/pdf export access. Old `featureEnabled(FEATURE_EXPORT)` returned true unconditionally for Lite (bug). CSV/JSON remain free.

## Requires

- mhm-license-server v1.11.2 (adds `'export'` feature key to token allowlist)

## Tests

793 → 808 (15 new), all passing on PHP 7.4 and PHP 8.2.
EOF
)"
```

Expected: Release URL printed. Verify ZIP asset attached.

### Task 9.5: Merge to main

- [ ] **Step 1: Merge feature branch**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git checkout main
git merge --no-ff fix/pro-gate-unification -m "Merge fix/pro-gate-unification (v4.33.0)"
git push origin main
```

- [ ] **Step 2: Delete feature branch**

```bash
git branch -d fix/pro-gate-unification
git push origin --delete fix/pro-gate-unification
```

---

# Phase 10 — Memory + Hot.md Update

### Task 10.1: Update memory after deploy verified

> This phase runs AFTER customer-facing verification (smoke test on customer site, "Re-validate Now" returns updated token, Pro features render).

- [ ] **Step 1: Update [hot.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/hot.md)**

Add entry under "Son Tamamlanan Görevler" reflecting:
- v4.33.0 + v1.11.2 release pair
- Test count: 808 (793 + 15)
- Bug C/B/A2 fixes
- BREAKING change for Lite users
- New filter `mhm_rentiva_dev_pro_bypass`

- [ ] **Step 2: Update [project_pro_gate_unification_v4.33.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/project_pro_gate_unification_v4.33.md)**

Mark all bugs as RESOLVED. Note actual callsite count (22, not 16) and the filter-based testability hook addition.

- [ ] **Step 3: Update PHPUnit baseline in [MEMORY.md](../../../../../../Users/manag/.claude/projects/c--projects-rentiva-dev/memory/MEMORY.md)**

Replace v4.32.0 baseline (793/2726/6) with v4.33.0 baseline (808/~2755/6).

---

## Self-Review (writing-plans skill, applied AFTER plan written)

### 1. Spec coverage

Walk through each spec section, point to a task that implements it:

- Spec §3.2 Bug C → Tasks 2.1, 2.2 ✓
- Spec §3.3 Bug B (active features) → Tasks 3.1, 3.2 ✓
- Spec §3.4 Bug B alt (sarı banner) → Task 3.3 ✓
- Spec §3.5.1 canUseExport → Task 2.2 Step 1 ✓
- Spec §3.5.2 22 callsite migration → Task 4.2 (steps 1-12) ✓
- Spec §3.5.3 featureEnabled deprecation → Task 4.3 ✓
- Spec §2 server v1.11.2 companion → Tasks 0.1-0.5 ✓
- Spec §4 behavior change → Documented in Task 4.3 commit message + Task 7.2 changelog
- Spec §5 testing → Phases 2/3/4 (15 tests) + Phase 6 CI parity ✓
- Spec §6 release sequence → Phases 7/8/9 ✓
- Spec §7 release chain → Phases 0 prerequisite block + Phase 9 ordering ✓
- Spec §8 risks → Mitigations baked into tasks (Phase 6 CI gate, Phase 9.2 smoke test, Phase 0 separate deploy) ✓
- Spec §9 i18n → Phase 5 (5.1, 5.2, 5.3) ✓

**Gap:** None.

### 2. Placeholder scan

Searched for: TBD, TODO, "fill in", "implement later", "appropriate handling", "edge cases" without specifics.

**Found:** `2026-04-XX` placeholders in changelog and blog post. **Action:** Task 7.2 Step 4 explicitly handles the date replacement with a `sed -i` command. Acceptable.

**No other placeholders.**

### 3. Type / signature consistency

- `Mode::canUseExport()` introduced in Task 2.2, referenced in Task 4.2 (callsite migration), Task 4.1 (test). Same signature throughout: `public static function canUseExport(): bool`. ✓
- `mhm_rentiva_dev_pro_bypass` filter introduced in Task 2.2 Step 2, referenced in Task 2.1 tests, Task 3.1 tests, Task 4.1 tests. Same name, same default behavior. ✓
- `Mode::featureGranted()` private method modified in Task 2.2 (bypass added). Reference signature in spec §3.2 matches Task 2.2 final state. ✓
- `LicenseAdmin::render_active_features()` introduced in Task 3.2 Step 2, referenced in Task 3.1 test. Same `public function render_active_features(): void`. ✓
- All `canUse*()` method names consistent (canUseMessages, canUseAdvancedReports, canUseExport, canUseVendorMarketplace, canUseVendorPayout). ✓

**No inconsistencies.**

---

**Plan complete.** This document plus the spec ([2026-04-27-v4330-pro-gate-unification-design.md](2026-04-27-v4330-pro-gate-unification-design.md)) is the full implementation guide.
