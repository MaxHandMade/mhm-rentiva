# Release WPCS Compliance - Design Document

**Date:** 2026-04-21
**Status:** Approved
**Feature:** Bring the WordPress.org release package into defensible WPCS compliance before submission

---

## Overview

The goal is not to make the entire repository cosmetically perfect. The goal is to make the code that will actually ship to WordPress.org pass a release-focused WPCS review with no critical security or standards violations.

The authoritative source for this work is `plugins/mhm-rentiva`. The mirrored runtime copy under `wp/wp-content/plugins/mhm-rentiva` is not the source of truth for remediation work.

## Release Scope

The compliance scope is limited to PHP files that belong to the distributable plugin package:

- `mhm-rentiva.php`
- `uninstall.php`
- `src/**/*.php`
- `templates/**/*.php`
- PHP entry files in block directories such as `assets/blocks/**/index.php`
- Any additional PHP files intentionally included in the release archive

The following paths are explicitly outside the release WPCS scope for this phase:

- `vendor/`
- `tests/`
- `build/`
- `docs/`
- `bin/`
- `stubs/`
- `website/`
- JS, CSS, images, and other non-PHP assets

## Current State

The existing `phpcs.xml` does not represent release readiness. It currently scans only a narrow subset of the codebase, focused on `src/Blocks` and `src/Helpers`, while the bulk of the plugin logic lives elsewhere.

Known high-risk areas based on initial inspection:

- `mhm-rentiva.php` bootstrap logic and direct output patterns
- `src/Admin/**` where most legacy administrative logic lives
- `src/Core/**` where shared utilities and infrastructure may trigger WPCS security/database sniffs
- `src/Layout/**`, `src/Api/**`, and `templates/**` where escaping and output discipline matter

## Recommended Approach

Use a release-package-first remediation strategy with risk prioritization.

### Approach Options Considered

1. Full repository WPCS cleanup
   - Broadest visibility
   - Too noisy for the immediate WordPress.org submission goal

2. Release package only
   - Matches reviewer reality
   - Most efficient for submission readiness

3. Risk-first hybrid inside the release package
   - Clean release files first
   - Within that scope, fix blocker-class findings before formatting debt

### Selected Approach

Use option 3: release package scope plus risk-first prioritization.

This keeps the work aligned with WordPress.org review expectations while reducing the chance of spending time on non-blocking noise before the real submission risks are removed.

## Analysis Flow

1. Define a release-focused PHPCS ruleset that matches the actual distributable PHP surface.
2. Run an initial full release-scope PHPCS scan and store the baseline output.
3. Classify findings by severity and remediation type.
4. Fix bootstrap and entry-point issues first.
5. Fix security and reviewer-blocker findings next.
6. Fix structural WPCS issues after critical findings are under control.
7. Re-run PHPCS and targeted plugin checks until the release scope is defensible.

## Finding Categories

### Blocker

These findings are assumed to be release blockers until disproven:

- output escaping
- input sanitization and unslashing
- nonce verification
- capability checks
- prepared SQL usage
- direct file access protection
- unsafe redirects or dangerous runtime behavior

### Release-Critical

- plugin header consistency
- text domain and i18n correctness
- uninstall safety
- package file structure and release metadata consistency
- hook naming or slug issues likely to trigger review friction

### Cleanup

- naming consistency
- docblock gaps where required by ruleset
- whitespace and formatting debt
- low-risk structural conventions

## Success Criteria

- PHPCS `error` count is zero for release-scope PHP files.
- Any remaining `warning` is intentional, documented, and acceptable for submission.
- No unresolved findings remain in the blocker category.
- `readme.txt`, plugin header, and uninstall behavior are internally consistent.
- Validation commands are repeatable in the Docker-based project workflow.

## Risk Management

### False Confidence Risk

The current narrow PHPCS scope can create a misleading sense of readiness. The first implementation task must correct that scope before any success claim is made.

### Regression Risk

WPCS remediation can accidentally change runtime behavior, especially in bootstrap code, AJAX handlers, and SQL helpers. Fixes should be staged by module and verified after each batch.

### Scope Drift Risk

The mirrored copy in `wp/wp-content/plugins/mhm-rentiva` must not become a parallel editing target. All remediation work should originate from `plugins/mhm-rentiva`.

### Ignore-Abuse Risk

`phpcs:ignore` and ruleset exclusions are allowed only when the reason is explicit, narrow, and review-defensible.

## Deliverables

- release-scope WPCS rules definition
- baseline findings report
- prioritized remediation backlog
- final validation report for submission readiness
