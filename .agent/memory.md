# MHM Rentiva - Project Memory

> Last Updated: 2026-01-27T11:19:47+03:00

## 📌 Project Overview

**Plugin Name:** MHM Rentiva  
**Type:** WordPress Vehicle Rental Plugin  
**Location:** `c:\xampp\htdocs\otokira\wp-content\plugins\mhm-rentiva`  
**Development Server:** XAMPP (Windows)

---

## 🎯 Current Session Objectives (2026-01-27)

### Completed Tasks ✅

1. **Frontend Vehicle Display Fixes**
   - Fixed gallery image retrieval with multiple meta key fallbacks
   - Added short description (excerpt) display
   - Refined booking button width (fit-content, min 220px)
   - Reduced rating stars and text size

2. **Rating System AJAX 404 Fix**
   - Synchronized AJAX action names between JS and PHP
   - Fixed `ajaxUrl` property naming (camelCase vs underscore)
   - Added both `ajaxUrl` and `ajax_url` to base shortcode localized data
   - Added smart fallback URL logic for subdirectory installations

3. **Rating Logic & UI Polish**
   - **CRITICAL FIX:** Star rating value mismatch - reversed for loop (5→1) to match CSS row-reverse
   - **Privacy:** Implemented name masking (e.g., "Fatma Çelik" → "Fatma Ç.")
   - **Fallback:** Added author name fallback (user display_name → "Anonymous")
   - **UI:** Removed avatar display from review items
   - **Styling:** Improved review list spacing with border separators

4. **Rating Sync & Star Visualization Fix**
   - **Cache Disabled:** Commented out transient cache (15 min) for real-time rating updates
   - **Star Color Bug:** Fixed CSS - default stars gray (#d1d5db), `.filled` gold (#ecc94b)
   - **Float Cast:** Added explicit `(float)` cast for rating average comparison

5. **UX Polish & Toast Notifications**
   - **Author Name Fix:** Added fallback if comment_author is empty
   - **Modern Buttons:** `.rv-rating-submit` flex container, side-by-side layout
   - **Danger Button:** Ghost/outline style (transparent bg, red border)
   - **Toast System:** Fixed notification at top-right with slide-in animation
   - **Auto-dismiss:** Toast notifications disappear after 4 seconds

6. **Delete Rating Bug Fix**
   - **Root Cause:** JS sent `vehicle_id` but PHP expected `comment_id`
   - **Solution:** PHP now accepts both - finds user's comment via vehicle_id if comment_id not provided
   - **Better Errors:** Specific error messages for security, login, not found, permission denied

---

## 📁 Modified Files Summary

| File | Key Changes |
|------|-------------|
| `VehicleDetails.php` | Gallery fallback, **cache disabled**, rating data |
| `VehicleRatingForm.php` | AJAX handlers refactored, **delete supports vehicle_id** |
| `AbstractShortcode.php` | Added `ajax_url` to localized data |
| `vehicle-details.php` | Short description, **star rating float cast** |
| `vehicle-rating-form.php` | **Name fallback**, masking, avatar removed, star order |
| `vehicle-details.css` | Button width, **star color fix** |
| `vehicle-rating-form.css` | **Toast system**, button container, danger button |
| `vehicle-rating-form.js` | **Toast notifications**, fixed AJAX delete, improved errors |

---

## 🏗️ Architecture Notes

### Shortcode System
- All shortcodes extend `AbstractShortcode` base class
- Assets (CSS/JS) conditionally loaded via `enqueue_assets_once()`
- Localized data via `get_localized_data()` method
- Template rendering through `Templates::render()`

### Rating System
- Uses WordPress Comments API with `mhm_rating` meta
- AJAX actions:
  - `mhm_rentiva_submit_rating` - Submit/update rating
  - `mhm_rentiva_delete_rating` - Delete (accepts vehicle_id OR comment_id)
  - `mhm_rentiva_get_vehicle_rating_list` - Fetch ratings list
- Settings from `CommentsSettings::get_settings()`

### Toast Notification System
- Container: `.rv-toast-container` (fixed, top-right, z-index: 99999)
- Toast element: `.rv-toast` with type classes (success, error, info)
- Animations: `slideInRight` / `slideOutRight`
- Auto-dismiss: 4 seconds

---

## ✅ Verified Working

- [x] Rating submission (logged-in user)
- [x] Rating update (existing rating)
- [x] Rating deletion via "Derecelendirme Sil" button
- [x] Star visualization (correct color based on average)
- [x] Real-time rating sync (no cache)
- [x] Toast notifications on success/error
- [x] Author name with privacy masking
- [x] Side-by-side button layout

---

## 🔑 Key Constants & Paths

```php
MHM_RENTIVA_PLUGIN_PATH  // Plugin directory path
MHM_RENTIVA_PLUGIN_URL   // Plugin URL
MHM_RENTIVA_VERSION      // Current version
```

---

## 📚 Available Skills

| Skill | Purpose |
|-------|---------|
| mhm-architect | Architecture planning |
| mhm-cli-operator | CLI operations |
| mhm-db-master | Database operations |
| mhm-doc-writer | Documentation |
| mhm-git-ops | Git operations |
| mhm-polyglot | Translations |
| mhm-release-manager | Release management |
| mhm-security-guard | Security audits |
| mhm-test-architect | Testing |
| mhm-translator | Language files |

---

## 📋 Workflows

| Workflow | Description |
|----------|-------------|
| `/audit-code` | Code quality audit |
| `/full-optimize` | Full optimization pass |
