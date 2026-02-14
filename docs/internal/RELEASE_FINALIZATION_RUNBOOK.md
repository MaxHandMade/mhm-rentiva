# Release Finalization Runbook (v4.9.8)

**Status:** 🔄 **IN PROGRESS**
**Artifact:** `mhm-rentiva.4.9.8.zip`
**Build Date:** 2026-02-14

---

## Phase 1: Staging Install & Smoke Test

**Objective:** Verify ZIP installs and functions correctly in a real WordPress environment.

### 1.1 Installation

- [ ] **Backup Staging:** Create full backup before installation
- [ ] **Remove Old Version:** Deactivate and delete existing `mhm-rentiva` plugin
- [ ] **Upload ZIP:** Upload `mhm-rentiva.4.9.8.zip` via Plugins → Add New → Upload
- [ ] **Activate:** Activate plugin and verify no fatal errors
- [ ] **Check Version:** Confirm version displays as `4.9.8` in Plugins list

### 1.2 Mandatory Smoke Scenarios

#### Frontend Tests

- [ ] **Booking Form**
  - [ ] Form renders correctly
  - [ ] Datepicker opens and functions
  - [ ] Price calculation works (select dates/vehicle)
  - [ ] Form submission (test mode if available)

- [ ] **Availability Calendar**
  - [ ] Calendar modal opens
  - [ ] Availability data renders
  - [ ] Date selection works

- [ ] **Vehicles List/Grid**
  - [ ] Vehicle cards render correctly
  - [ ] Compare functionality (if enabled)
  - [ ] Favorite/wishlist functionality (if enabled)
  - [ ] Images load properly

- [ ] **Search Results**
  - [ ] Filter dropdowns populate
  - [ ] Search executes without errors
  - [ ] Results display correctly
  - [ ] Pagination works
  - [ ] **Performance:** Hot path query completes in <500ms

#### Account Pages

- [ ] **My Bookings**
  - [ ] Page loads
  - [ ] Booking list displays
  - [ ] CSS loads ONLY on this page (no leak to other pages)

- [ ] **My Favorites**
  - [ ] Page loads
  - [ ] Favorites list displays
  - [ ] CSS isolation verified

- [ ] **Messages** (if enabled)
  - [ ] Page loads
  - [ ] Message threads display
  - [ ] CSS isolation verified

#### Admin Tests

- [ ] **Vehicle CPT**
  - [ ] Vehicle list loads
  - [ ] Edit vehicle screen loads
  - [ ] Meta boxes render correctly
  - [ ] Save/update works

- [ ] **Settings Pages**
  - [ ] General settings load
  - [ ] Transfer settings load (if applicable)
  - [ ] Payment settings load
  - [ ] Save settings works

- [ ] **Dashboard Widgets** (if any)
  - [ ] Widgets load without errors

### 1.3 Regression Checks

- [ ] **No PHP Warnings/Errors:** Check `wp-content/debug.log`
- [ ] **No JS Console Errors:** Check browser console on all tested pages
- [ ] **CSS Isolation:** Verify account page CSS doesn't leak to other pages
- [ ] **Asset Loading:** Confirm conditional loading (no unnecessary CSS/JS on pages that don't need them)

### 1.4 Evidence Documentation

Create `docs/internal/QA_RELEASE_STAGING_SMOKE.md` with:

- WordPress version
- PHP version
- Theme used
- Test date/time
- List of tested pages with PASS/FAIL status
- Screenshots of critical pages (optional but recommended)
- Any issues found and resolution status

---

## Phase 2: Git Tagging

**Objective:** Create official release tag in version control.

- [ ] **Commit All Changes:** Ensure all M4 changes are committed
- [ ] **Create Tag:** `git tag -a v4.9.8 -m "Release v4.9.8 - M4 Release Hygiene & Governance Complete"`
- [ ] **Push Tag:** `git push origin v4.9.8`
- [ ] **Verify Tag:** Confirm tag appears in GitHub/GitLab

---

## Phase 3: Production Deployment

**Objective:** Deploy verified release to production environment.

### 3.1 Pre-Deployment

- [ ] **Backup Production:** Full database + files backup
- [ ] **Maintenance Mode:** Enable maintenance mode (if applicable)
- [ ] **Verify Staging Success:** All smoke tests PASSED

### 3.2 Deployment

- [ ] **Upload ZIP:** Upload `mhm-rentiva.4.9.8.zip` to production
- [ ] **Activate:** Activate plugin
- [ ] **Verify Version:** Confirm `4.9.8` in production plugins list

### 3.3 Post-Deployment Verification

- [ ] **Quick Smoke Test:** Test 2-3 critical pages (booking form, vehicle list, admin dashboard)
- [ ] **Check Logs:** Verify no PHP errors in production logs
- [ ] **Monitor Performance:** Check page load times
- [ ] **Disable Maintenance Mode:** Return site to normal operation

### 3.4 Rollback Plan

If issues are found:
1. Deactivate `mhm-rentiva` v4.9.8
2. Restore previous version from backup
3. Document issues in `docs/internal/ROLLBACK_LOG.md`
4. Return to M4 hardening phase to address issues

---

## Phase 4: Release Announcement

- [ ] **Update Changelog:** Ensure `changelog.json` and `changelog-tr.json` reflect v4.9.8
- [ ] **Documentation:** Update any user-facing documentation
- [ ] **WordPress.org:** Submit to WordPress.org repository (if applicable)
- [ ] **Internal Notification:** Notify team of successful deployment

---

## Sign-Off

**Staging Tested By:** ___________________ Date: ___________

**Production Deployed By:** ___________________ Date: ___________

**Status:** 
- [ ] ✅ **COMPLETE** - All phases passed
- [ ] ⚠️ **PARTIAL** - Issues found, documented in rollback log
- [ ] ❌ **FAILED** - Rolled back to previous version
