# Release WPCS Compliance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the release package of `plugins/mhm-rentiva` pass a release-focused WPCS review suitable for WordPress.org submission.

**Architecture:** Define a release-only PHPCS surface first, then remediate findings in descending risk order: bootstrap, security, database, output/i18n, and finally structural conventions. Keep the mirrored runtime copy out of scope and validate every batch with Docker-based commands.

**Tech Stack:** PHP 8.1+, WordPress 6.7+, PHPCS/WPCS, plugin-check, Docker Compose, PowerShell, existing PHPUnit coverage where available.

---

## File Structure

Primary source of truth:

- `plugins/mhm-rentiva/`

Expected files to modify during execution:

- `plugins/mhm-rentiva/phpcs.xml` or a new release-specific ruleset such as `plugins/mhm-rentiva/phpcs-release.xml`
- `plugins/mhm-rentiva/composer.json`
- `plugins/mhm-rentiva/mhm-rentiva.php`
- `plugins/mhm-rentiva/uninstall.php`
- release-scope PHP files under `plugins/mhm-rentiva/src/`
- release-scope PHP files under `plugins/mhm-rentiva/templates/`
- `plugins/mhm-rentiva/docs/reports/` for baseline and validation reports

Paths intentionally excluded from execution scope:

- `plugins/mhm-rentiva/vendor/`
- `plugins/mhm-rentiva/tests/`
- `plugins/mhm-rentiva/build/`
- `plugins/mhm-rentiva/docs/stitch/`
- `plugins/mhm-rentiva/website/`
- `wp/wp-content/plugins/mhm-rentiva/`

### Task 1: Define the Release PHPCS Surface

**Files:**
- Modify: `plugins/mhm-rentiva/phpcs.xml` or create `plugins/mhm-rentiva/phpcs-release.xml`
- Modify: `plugins/mhm-rentiva/composer.json`
- Test: release-scope PHPCS command only

- [ ] **Step 1: Replace the narrow scan scope with a release-focused scope**

Use a ruleset that targets only distributable PHP files. The ruleset should include:

```xml
<file>mhm-rentiva.php</file>
<file>uninstall.php</file>
<file>src</file>
<file>templates</file>
<file>assets/blocks</file>

<exclude-pattern>*/vendor/*</exclude-pattern>
<exclude-pattern>*/tests/*</exclude-pattern>
<exclude-pattern>*/build/*</exclude-pattern>
<exclude-pattern>*/docs/*</exclude-pattern>
<exclude-pattern>*/bin/*</exclude-pattern>
<exclude-pattern>*/stubs/*</exclude-pattern>
<exclude-pattern>*/website/*</exclude-pattern>
<exclude-pattern>*.js</exclude-pattern>
<exclude-pattern>*.css</exclude-pattern>
```

- [ ] **Step 2: Add a dedicated release script to Composer**

Use explicit commands so future validation is repeatable:

```json
"scripts": {
  "phpcs": "@php ./vendor/bin/phpcs --standard=phpcs.xml",
  "phpcs:release": "@php ./vendor/bin/phpcs --standard=phpcs.xml mhm-rentiva.php uninstall.php src templates assets/blocks",
  "phpcbf:release": "@php ./vendor/bin/phpcbf --standard=phpcs.xml mhm-rentiva.php uninstall.php src templates assets/blocks"
}
```

- [ ] **Step 3: Run the first release-scope PHPCS scan**

Run:

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs:release"
```

Expected: non-zero exit with a large finding set. This is the baseline, not a failure of the plan.

- [ ] **Step 4: Save the raw baseline report**

Run:

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --standard=phpcs.xml --report=full mhm-rentiva.php uninstall.php src templates assets/blocks > docs/reports/2026-04-21-release-wpcs-baseline.txt"
```

Expected: `docs/reports/2026-04-21-release-wpcs-baseline.txt` created with the full baseline.

- [ ] **Step 5: Commit the scope-definition change**

```bash
git -C plugins/mhm-rentiva add phpcs.xml composer.json docs/reports/2026-04-21-release-wpcs-baseline.txt
git -C plugins/mhm-rentiva commit -m "chore(qa): define release WPCS scope and baseline"
```

### Task 2: Classify the Baseline Into a Fix Backlog

**Files:**
- Create: `plugins/mhm-rentiva/docs/reports/2026-04-21-release-wpcs-backlog.md`
- Test: no runtime tests, report generation only

- [ ] **Step 1: Group findings by blocker, release-critical, and cleanup**

Backlog sections must use these exact headings:

```md
## Blocker
## Release-Critical
## Cleanup
```

Each item should record:

- file path
- sniff code
- line number
- short remediation note

- [ ] **Step 2: Prioritize bootstrap and security-heavy modules first**

Use this initial ordering in the backlog:

```md
1. mhm-rentiva.php
2. uninstall.php
3. src/Admin/** security and output hotspots
4. src/Core/** shared sanitization, SQL, and helper code
5. templates/** escaping and output fixes
6. remaining structural conventions
```

- [ ] **Step 3: Save the backlog document**

The backlog must explicitly state that `wp/wp-content/plugins/mhm-rentiva` is excluded from remediation.

- [ ] **Step 4: Commit the backlog**

```bash
git -C plugins/mhm-rentiva add docs/reports/2026-04-21-release-wpcs-backlog.md
git -C plugins/mhm-rentiva commit -m "docs(qa): classify release WPCS backlog"
```

### Task 3: Remediate Bootstrap and Entry Points

**Files:**
- Modify: `plugins/mhm-rentiva/mhm-rentiva.php`
- Modify: `plugins/mhm-rentiva/uninstall.php`
- Test: targeted PHPCS plus affected PHPUnit tests if present

- [ ] **Step 1: Run PHPCS only for the entry points**

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --standard=phpcs.xml mhm-rentiva.php uninstall.php"
```

Expected: a narrow list of findings limited to the two entry files.

- [ ] **Step 2: Fix entry-point issues without changing plugin behavior**

Priority inside these files:

- remove unsafe direct output patterns where escaping is required
- tighten bootstrap guards and direct-access handling
- replace broad inline closures only when they materially improve clarity or compliance
- keep the main plugin file as a loader, not a logic dump

- [ ] **Step 3: Re-run targeted PHPCS for the entry points**

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --standard=phpcs.xml mhm-rentiva.php uninstall.php"
```

Expected: zero errors in these two files.

- [ ] **Step 4: Run targeted tests if bootstrap-adjacent coverage exists**

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter 'Settings|Security|Bootstrap' --no-coverage"
```

Expected: tests pass or reveal a real regression to fix before continuing.

- [ ] **Step 5: Commit the entry-point remediation**

```bash
git -C plugins/mhm-rentiva add mhm-rentiva.php uninstall.php
git -C plugins/mhm-rentiva commit -m "fix(core): harden release entry points for WPCS"
```

### Task 4: Remediate Blocker-Class Findings in `src/`

**Files:**
- Modify: blocker-flagged files under `plugins/mhm-rentiva/src/`
- Modify: blocker-flagged PHP templates under `plugins/mhm-rentiva/templates/`
- Test: targeted PHPCS and existing PHPUnit suites for touched modules

- [ ] **Step 1: Work one blocker category at a time**

Use this sequence:

```md
1. nonce verification
2. sanitize and unslash discipline
3. escaping output
4. capability checks
5. prepared SQL and database helper issues
```

- [ ] **Step 2: For each touched module, run targeted PHPCS before editing**

Example:

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpcs --standard=phpcs.xml src/Admin/Settings src/Core"
```

Expected: output narrowed to the module being fixed.

- [ ] **Step 3: Apply the smallest safe fix that removes the sniff**

Allowed fix styles:

- centralize sanitization in existing helper classes when already present
- add `wp_unslash()` before sanitization when request data is read
- wrap dynamic output in the correct `esc_*()` function
- replace raw SQL interpolation with `$wpdb->prepare()`
- add `current_user_can()` checks where admin actions mutate state

- [ ] **Step 4: Re-run targeted tests for each touched area**

Use the smallest relevant PHPUnit filter, for example:

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter 'SettingsSanitizer|SecurityHelper|WebhookRateLimiter' --no-coverage"
```

Expected: pass for touched modules.

- [ ] **Step 5: Commit each blocker batch separately**

Example commit messages:

```bash
git -C plugins/mhm-rentiva commit -m "fix(admin): sanitize request handling for WPCS"
git -C plugins/mhm-rentiva commit -m "fix(core): prepare SQL in reporting queries"
git -C plugins/mhm-rentiva commit -m "fix(templates): escape dynamic frontend output"
```

### Task 5: Remediate Release-Critical and Cleanup Findings

**Files:**
- Modify: remaining release-scope PHP files with non-blocker findings
- Modify: `plugins/mhm-rentiva/readme.txt` only if a release consistency issue is confirmed
- Test: full release PHPCS run plus plugin check

- [ ] **Step 1: Fix text-domain and i18n inconsistencies**

Use these rules consistently:

- text domain must be `mhm-rentiva`
- translatable strings must not concatenate unsafe dynamic content
- translator comments must stay directly above format strings

- [ ] **Step 2: Fix uninstall and metadata inconsistencies**

Confirm:

- `uninstall.php` has direct access protection
- destructive cleanup is intentionally scoped
- `readme.txt` and plugin header do not contradict supported versions or feature claims

- [ ] **Step 3: Fix naming and structural WPCS debt only after blocker and release-critical findings are clear**

Do not spend time on stylistic cleanup while blocker findings remain.

- [ ] **Step 4: Run full release PHPCS again**

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer phpcs:release"
```

Expected: exit code 0, or warnings only if they are intentional and documented.

- [ ] **Step 5: Run plugin check for release confidence**

```bash
docker compose exec wpcli bash -lc "cd /var/www/html/wp-content/plugins/mhm-rentiva && composer plugin-check:release"
```

Expected: no new release blocker introduced by the remediation.

- [ ] **Step 6: Commit the non-blocker cleanup**

```bash
git -C plugins/mhm-rentiva add .
git -C plugins/mhm-rentiva commit -m "chore(release): finish WPCS cleanup for submission"
```

### Task 6: Final Validation and Submission Readiness Report

**Files:**
- Create: `plugins/mhm-rentiva/docs/reports/2026-04-21-release-wpcs-validation.md`
- Test: final PHPCS, plugin-check, and any targeted PHPUnit suites used during remediation

- [ ] **Step 1: Capture final command output summary**

The validation report must list the exact commands used:

```md
- composer phpcs:release
- composer plugin-check:release
- vendor/bin/phpunit --filter '...'
```

- [ ] **Step 2: Record any accepted warnings or narrow ignores**

Each accepted exception must include:

- file path
- sniff code
- reason it is safe
- why a broader fix was rejected

- [ ] **Step 3: Record release decision**

Use one of these exact outcomes:

```md
Outcome: Ready for WordPress.org submission
Outcome: Not ready for submission
```

- [ ] **Step 4: Commit the validation report**

```bash
git -C plugins/mhm-rentiva add docs/reports/2026-04-21-release-wpcs-validation.md
git -C plugins/mhm-rentiva commit -m "docs(qa): record final release WPCS validation"
```

## Self-Review

- The plan covers scope definition, baseline generation, backlog classification, blocker remediation, structural cleanup, and final validation.
- No task relies on `wp/wp-content/plugins/mhm-rentiva` as an edit target.
- The plan keeps release-blocking findings ahead of cosmetic cleanup.
