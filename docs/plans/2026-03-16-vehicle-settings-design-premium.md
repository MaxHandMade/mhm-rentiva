# Vehicle Settings — Premium Design & Responsive Cleanup

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Remove all inline `style=""` attributes, replace hardcoded colors with design tokens, add premium drop-zone and drag-handle styling, fix mobile responsive from JS to CSS, and replace `alert()` with `mhmShowNotice`.

**Architecture:** Two-phase approach — CSS first (add all missing classes), then PHP (swap inline styles for class names). JS changes are minimal: expose `showNotice` globally from `settings.js`, remove the `matchMedia` inline-CSS block.

**Tech Stack:** PHP 8.1, WordPress CSS custom properties (`--mhm-*`), jQuery, WPCS (WordPress Coding Standards)

---

## Context for the implementer

- Plugin path: `plugins/mhm-rentiva/`
- Main settings file: `src/Admin/Vehicle/Settings/VehicleSettings.php`
- Settings CSS: `assets/css/admin/vehicle-settings.css` (loaded only on vehicle-settings page)
- General admin CSS: `assets/css/admin/settings.css` (loaded on all settings pages)
- CSS variables source: `assets/css/core/css-variables.css` — use tokens from here only
- Notification helper: `assets/js/admin/settings.js` → function `showNotice(message, type)` — needs exposing globally
- The `render_display_tab()` and `render_definitions_tab()` methods are in `VehicleSettings.php`
- WPCS rule: no `style=""` attributes in PHP-rendered HTML; all styling goes in CSS files
- Dark mode CSS is in `assets/css/admin/dark-mode.css` — if you add token-based colors, dark mode adapts automatically

## Available CSS tokens (from `css-variables.css`)

```
--mhm-primary               (blue)
--mhm-text-primary          (#1f2937)
--mhm-text-secondary        (#50575e = gray-600)
--mhm-text-tertiary         (#646970 = gray-500)
--mhm-border-primary        (#c3c4c7 = gray-200)
--mhm-border-secondary      (#a7aaad = gray-300)
--mhm-border-focus          (= --mhm-primary)
--mhm-bg-primary / --mhm-bg-card    (white)
--mhm-bg-secondary          (#f9f9f9 = gray-50)
--mhm-bg-tertiary           (#f0f0f1 = gray-100)
--mhm-error                 (#d63638)
--mhm-gray-400              (#8c8f94)
--mhm-gray-500              (#646970)
--mhm-shadow-sm             (0 1px 2px 0 rgba(0,0,0,0.05))
--mhm-shadow-base           (subtle shadow)
--mhm-space-1..8            (0.25rem steps)
--mhm-radius-sm..2xl        (border-radius)
--mhm-font-medium           (500)
--mhm-font-semibold         (600)
--mhm-text-sm               (0.875rem)
--mhm-text-xs               (0.75rem)
```

---

### Task 1: Expose `showNotice` globally in `settings.js`

**Files:**
- Modify: `assets/js/admin/settings.js`

**What:** The `showNotice(message, type)` function inside the jQuery ready callback is only locally scoped. We need `window.mhmShowNotice` so the display-tab inline script can call it without `alert()`.

**Step 1: Find the showNotice function in settings.js**

It starts at approximately line 181:
```js
function showNotice(message, type) {
```

**Step 2: Add the global assignment after the function definition**

Find the closing `}` of the `showNotice` function (the `}` on line ~208, just before the closing `}` of the `document.ready` callback). Add one line immediately after it:

```js
		window.mhmShowNotice = showNotice;
```

The surrounding context after the change:
```js
		// Auto-dismiss after 5 seconds
			setTimeout(
				function () {
					$notice.fadeOut(
						500,
						function () {
							$(this).remove();
						}
					);
				},
				5000
			);
		}
		window.mhmShowNotice = showNotice;
	}
);
```

**Step 3: Verify**

Open browser console on any settings page, type `window.mhmShowNotice` — should return a function, not `undefined`.

**Step 4: Commit**

```
git add plugins/mhm-rentiva/assets/js/admin/settings.js
git commit -m "feat(admin): expose showNotice globally as window.mhmShowNotice"
```

---

### Task 2: Full CSS overhaul of `vehicle-settings.css`

**Files:**
- Modify: `assets/css/admin/vehicle-settings.css`

**What:** Replace all hardcoded hex colors with design tokens. Add new semantic CSS classes for every element currently styled inline in PHP. Add premium drop-zone and drag-handle styling. Fix mobile responsiveness at 782px using CSS only.

**Step 1: Replace the entire file content**

Replace the full contents of `assets/css/admin/vehicle-settings.css` with the following. Read the existing file first to confirm line count (~281 lines), then write the new version:

```css
/*
 * MHM Rentiva - Vehicle Settings Page Styles
 * v5.0.0 - Premium Design & Responsive Cleanup
 * All colors use CSS design tokens. No hardcoded hex values.
 */

/* ---------------------------------------------------------
   1. GLOBAL SCALE & CONTAINER NORMALIZATION
   --------------------------------------------------------- */
.mhm-settings-container {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: var(--mhm-space-4);
	margin-top: var(--mhm-space-4);
	width: 100%;
	box-sizing: border-box;
}

/* Compact Premium Card */
.mhm-settings-card {
	background: var(--mhm-bg-card);
	padding: var(--mhm-space-4);
	border-radius: var(--mhm-radius-lg);
	box-shadow: var(--mhm-shadow-sm);
	border: 1px solid var(--mhm-border-primary);
	box-sizing: border-box;
	height: fit-content;
	overflow: hidden;
}

.mhm-settings-card h2 {
	font-size: 1rem !important;
	margin-bottom: var(--mhm-space-1) !important;
	color: var(--mhm-text-primary);
	font-weight: var(--mhm-font-semibold) !important;
}

.mhm-settings-card p {
	font-size: var(--mhm-text-sm);
	color: var(--mhm-text-secondary);
	margin-bottom: var(--mhm-space-3);
}

/* ---------------------------------------------------------
   2. UNIFIED CHECKBOX COMPONENT
   --------------------------------------------------------- */
.mhm-checkbox-list {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: var(--mhm-space-2);
	margin-bottom: var(--mhm-space-4);
	width: 100%;
}

.mhm-checkbox-item {
	display: flex !important;
	justify-content: space-between !important;
	align-items: center !important;
	gap: var(--mhm-space-3) !important;
	padding: 6px 12px !important;
	background: var(--mhm-bg-card) !important;
	border: 1px solid var(--mhm-border-primary) !important;
	border-radius: var(--mhm-radius-base) !important;
	transition: all 0.15s ease !important;
	box-sizing: border-box !important;
	width: 100% !important;
	min-height: 44px !important;
	margin: 0 !important;
}

.mhm-checkbox-item:hover {
	background: var(--mhm-bg-secondary) !important;
	border-color: var(--mhm-border-secondary) !important;
}

.mhm-checkbox-label {
	display: flex !important;
	align-items: center !important;
	gap: 10px !important;
	flex-grow: 1 !important;
	cursor: pointer !important;
	margin: 0 !important;
}

/* Remove button */
.mhm-checkbox-item .button-link[class*="remove-"] {
	padding: 0 5px !important;
	font-size: 20px !important;
	line-height: 1 !important;
	text-decoration: none !important;
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	color: var(--mhm-error) !important;
	min-width: 24px;
	height: 24px;
}

/* Core details (no remove button) */
.mhm-checkbox-item:not(.mhm-removable-item) {
	justify-content: flex-start !important;
}

/* Custom Checkbox appearance */
.mhm-checkbox-item input[type="checkbox"],
.mhm-ui-checkbox input[type="checkbox"] {
	appearance: none !important;
	-webkit-appearance: none !important;
	background-color: var(--mhm-bg-card) !important;
	margin: 0 !important;
	padding: 0 !important;
	width: 16px !important;
	height: 16px !important;
	border: 2px solid var(--mhm-gray-400) !important;
	border-radius: 2px !important;
	cursor: pointer !important;
	position: relative !important;
	display: inline-block !important;
	vertical-align: middle !important;
	flex-shrink: 0 !important;
	opacity: 1 !important;
}

.mhm-checkbox-item input[type="checkbox"]:checked,
.mhm-ui-checkbox input[type="checkbox"]:checked {
	background-color: var(--mhm-primary) !important;
	border-color: var(--mhm-primary) !important;
}

.mhm-checkbox-item input[type="checkbox"]:checked:before,
.mhm-ui-checkbox input[type="checkbox"]:checked:before {
	content: "" !important;
	position: absolute !important;
	left: 4px !important;
	top: 1px !important;
	width: 4px !important;
	height: 8px !important;
	border: solid var(--mhm-bg-card) !important;
	border-width: 0 2px 2px 0 !important;
	transform: rotate(45deg) !important;
	margin: 0 !important;
	display: block !important;
}

.mhm-checkbox-item span {
	font-size: 14px !important;
	font-weight: var(--mhm-font-medium) !important;
	color: var(--mhm-text-primary) !important;
	line-height: 1.4 !important;
}

/* Section Sub-titles */
.mhm-card-section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: var(--mhm-space-4) !important;
	margin-top: var(--mhm-space-3);
}

.mhm-card-section-header h4 {
	margin: 0 !important;
	font-size: var(--mhm-text-xs) !important;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--mhm-text-tertiary);
}

/* Header Actions */
.mhm-category-actions {
	display: flex;
	gap: var(--mhm-space-1);
}

.mhm-category-actions .button {
	font-size: 11px !important;
	height: 24px !important;
	line-height: 22px !important;
	padding: 0 8px !important;
}

/* ---------------------------------------------------------
   3. CUSTOM FIELDS SECTION HEADERS & ADD ROW
   --------------------------------------------------------- */

/* Replaces inline: margin-top: 20px; font-size: 11px; text-transform: uppercase; color: #94a3b8; */
.mhm-custom-section-header {
	margin-top: var(--mhm-space-5) !important;
	margin-bottom: var(--mhm-space-2) !important;
	font-size: var(--mhm-text-xs) !important;
	font-weight: var(--mhm-font-semibold) !important;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--mhm-text-tertiary);
}

/* Replaces inline: margin-top: 15px */
.mhm-add-custom-wrapper {
	margin-top: var(--mhm-space-4);
}

/* "Add Custom" row — inputs and button */
.mhm-add-custom-row {
	display: flex;
	flex-wrap: wrap;
	gap: var(--mhm-space-2);
	align-items: center;
}

.mhm-add-custom-row input[type="text"] {
	flex: 1;
	min-width: 160px;
	max-width: 250px;
}

.mhm-add-custom-row select {
	min-width: 120px;
	max-width: 160px;
}

.mhm-add-custom-row .mhm-select-options-input {
	flex: 2;
	min-width: 200px;
}

/* ---------------------------------------------------------
   4. DROP ZONE & DRAG-AND-DROP (Premium)
   --------------------------------------------------------- */

/* Two-column layout for visible/available lists */
.mhm-card-fields-columns {
	display: flex;
	gap: var(--mhm-space-5);
}

.mhm-card-fields-column {
	flex: 1;
	background: var(--mhm-bg-card);
	padding: var(--mhm-space-4);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-lg);
	min-width: 0;
}

.mhm-card-fields-column h4 {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin: 0 0 var(--mhm-space-2) 0;
	font-size: 13px;
	font-weight: var(--mhm-font-semibold);
	color: var(--mhm-text-primary);
}

/* Search input inside column */
.mhm-card-field-search {
	width: 100% !important;
	margin: 0 0 var(--mhm-space-2) 0 !important;
	box-sizing: border-box;
}

/* Drop zone list */
.mhm-card-fields-list {
	min-height: 80px;
	max-height: 300px;
	overflow-y: auto;
	overflow-x: hidden;
	border: 2px dashed var(--mhm-border-primary);
	border-radius: var(--mhm-radius-md);
	padding: var(--mhm-space-2);
	background: var(--mhm-bg-secondary);
	transition: border-color 0.2s ease, background-color 0.2s ease;
	list-style: none;
	margin: 0;
}

.mhm-card-fields-list:hover,
.mhm-card-fields-list.ui-sortable-over {
	border-color: var(--mhm-primary);
	background: color-mix(in srgb, var(--mhm-primary) 4%, var(--mhm-bg-secondary));
}

.mhm-card-fields-list.is-empty::after {
	content: attr(data-empty-label);
	display: block;
	text-align: center;
	padding: var(--mhm-space-4);
	color: var(--mhm-text-tertiary);
	font-size: var(--mhm-text-sm);
	font-style: italic;
}

/* Footer tip */
.mhm-card-fields-footer {
	margin-top: var(--mhm-space-3) !important;
	font-size: var(--mhm-text-sm);
	color: var(--mhm-text-tertiary);
}

/* Drag sortable placeholder */
.mhm-card-fields-placeholder {
	height: 38px;
	background: color-mix(in srgb, var(--mhm-primary) 8%, transparent);
	border: 2px dashed var(--mhm-primary);
	border-radius: var(--mhm-radius-base);
	margin: 4px 0;
	list-style: none;
}

/* Individual draggable list item */
.mhm-card-field-item {
	padding: var(--mhm-space-2) var(--mhm-space-3);
	margin: 4px 0;
	background: var(--mhm-bg-card);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-base);
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: var(--mhm-space-2);
	cursor: grab;
	transition: box-shadow 0.15s ease, border-color 0.15s ease;
	list-style: none;
}

.mhm-card-field-item:hover {
	border-color: var(--mhm-border-secondary);
	box-shadow: var(--mhm-shadow-sm);
}

.mhm-card-field-item:active {
	cursor: grabbing;
	box-shadow: var(--mhm-shadow-base);
}

/* Drag handle icon */
.mhm-drag-handle {
	color: var(--mhm-gray-400);
	cursor: grab;
	flex-shrink: 0;
	font-size: 16px;
	width: 16px;
	height: 16px;
	line-height: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.6;
	transition: opacity 0.15s ease;
}

.mhm-card-field-item:hover .mhm-drag-handle {
	opacity: 1;
	color: var(--mhm-text-secondary);
}

/* Field label inside list item */
.mhm-card-field-label {
	flex: 1;
	font-weight: var(--mhm-font-medium);
	font-size: 13px;
	color: var(--mhm-text-primary);
}

/* Remove button inside list item */
.mhm-card-field-item .remove-field {
	color: var(--mhm-gray-400);
	font-size: 18px;
	line-height: 1;
	padding: 0 4px;
	text-decoration: none;
	display: flex;
	align-items: center;
	justify-content: center;
	min-width: 24px;
	height: 24px;
	transition: color 0.15s ease;
}

.mhm-card-field-item .remove-field:hover {
	color: var(--mhm-error);
}

/* Available items (not selected) — slightly muted */
.mhm-card-field-item:not(.selected) {
	background: var(--mhm-bg-secondary);
	cursor: pointer;
}

.mhm-card-field-item:not(.selected):hover {
	background: var(--mhm-bg-card);
}

/* ---------------------------------------------------------
   5. DISPLAY OPTIONS — SECTION DIVIDER & SAVE ACTIONS
   --------------------------------------------------------- */

/* Replaces <hr style="margin: 30px 0;"> */
.mhm-section-divider {
	border: none;
	border-top: 1px solid var(--mhm-border-primary);
	margin: var(--mhm-space-8) 0;
}

/* Replaces <div style="margin-top: 20px;"> around the save button */
.mhm-display-save-actions {
	margin-top: var(--mhm-space-5);
}

/* ---------------------------------------------------------
   6. COMPARISON TABLE FIELDS
   --------------------------------------------------------- */
.mhm-field-category {
	background: var(--mhm-bg-secondary);
	border: 1px solid var(--mhm-border-primary);
	border-radius: var(--mhm-radius-md);
	padding: var(--mhm-space-4);
	margin-bottom: var(--mhm-space-5);
}

.mhm-category-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: var(--mhm-space-3);
}

.mhm-category-header h4 {
	margin: 0;
	font-size: var(--mhm-text-sm);
	font-weight: var(--mhm-font-semibold);
	color: var(--mhm-text-primary);
}

.mhm-field-list {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: var(--mhm-space-2);
}

.mhm-section-subtitle {
	margin: 0 !important;
	margin-bottom: var(--mhm-space-2) !important;
	font-size: 13px !important;
	color: var(--mhm-text-secondary);
	font-weight: var(--mhm-font-semibold) !important;
}

/* ---------------------------------------------------------
   7. MOBILE RESPONSIVENESS — 782px (WordPress tablet breakpoint)
   --------------------------------------------------------- */
@media screen and (max-width: 782px) {

	/* Definitions tab: single column */
	.mhm-settings-container {
		grid-template-columns: 1fr !important;
		gap: var(--mhm-space-3) !important;
		width: 100% !important;
	}

	.mhm-settings-card {
		padding: var(--mhm-space-3) !important;
		width: 100% !important;
	}

	/* Checkbox grids: single column */
	.mhm-checkbox-list,
	.mhm-field-list {
		grid-template-columns: 1fr !important;
		gap: var(--mhm-space-2) !important;
	}

	.mhm-checkbox-item,
	.mhm-checkbox-label {
		display: flex !important;
		flex-direction: row !important;
		flex-wrap: nowrap !important;
		align-items: center !important;
		gap: var(--mhm-space-3) !important;
		width: 100% !important;
		box-sizing: border-box !important;
	}

	.mhm-checkbox-item {
		min-height: 48px !important;
		padding: 10px 15px !important;
	}

	.mhm-checkbox-item span {
		font-size: 14px !important;
		white-space: normal !important;
		flex-grow: 1 !important;
	}

	/* Display tab: card columns stack vertically */
	.mhm-card-fields-columns {
		flex-direction: column !important;
		gap: var(--mhm-space-3) !important;
	}

	.mhm-card-fields-list {
		max-height: 220px;
	}

	/* Add custom row: stack */
	.mhm-add-custom-row {
		flex-direction: column;
		align-items: stretch;
	}

	.mhm-add-custom-row input[type="text"],
	.mhm-add-custom-row select {
		max-width: none !important;
		width: 100% !important;
	}

	/* Sticky save button (definitions tab) */
	.mhm-vehicle-settings-wrapper .mhm-admin-header-actions {
		width: 100% !important;
		display: flex !important;
		flex-direction: column !important;
		gap: var(--mhm-space-2) !important;
		margin-top: var(--mhm-space-4) !important;
	}

	.mhm-vehicle-settings-wrapper .mhm-admin-header-actions .button {
		width: 100% !important;
		justify-content: center !important;
		font-size: 13px !important;
	}

	/* Sticky save — Display tab */
	.mhm-display-save-actions.submit-section {
		position: sticky;
		bottom: 0;
		background: var(--mhm-bg-card);
		padding: var(--mhm-space-3) var(--mhm-space-4);
		margin: 0 calc(-1 * var(--mhm-space-4)) calc(-1 * var(--mhm-space-4));
		box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
		border-top: 1px solid var(--mhm-border-primary);
		z-index: 100;
	}

	.mhm-display-save-actions.submit-section .button-primary {
		width: 100%;
		height: 44px;
		font-size: 16px;
	}
}

/* ---------------------------------------------------------
   8. OVERFLOW SHIELD
   --------------------------------------------------------- */
.mhm-rentiva-page-vehicle-settings #wpbody-content {
	overflow-x: hidden !important;
}
```

**Step 2: Verify**

```bash
# No errors expected; this is CSS only
php -l plugins/mhm-rentiva/assets/css/admin/vehicle-settings.css 2>&1 || echo "CSS — no PHP lint needed"
```

Visually verify: the class names added here will be used by PHP in subsequent tasks.

**Step 3: Commit**

```
git add plugins/mhm-rentiva/assets/css/admin/vehicle-settings.css
git commit -m "style(vehicle-settings): replace hardcoded colors with tokens, add premium drop-zone and drag-handle CSS, CSS-only mobile responsive"
```

---

### Task 3: PHP — `render_settings_page()` `<br>` removal

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` (lines ~182–190)

**What:** Remove the `<br>` spacer after the nav tabs. The margin is now handled in WordPress core's `nav-tab-wrapper` which already has `margin-bottom`. If extra spacing is needed, add `mhm-nav-tab-wrapper` class and CSS.

**Step 1: Find the target in `render_settings_page()`**

```php
		<nav class="nav-tab-wrapper">
			<a href="?page=vehicle-settings&tab=definitions" class="nav-tab <?php echo $active_tab === 'definitions' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Field Definitions', 'mhm-rentiva' ); ?>
			</a>
			<a href="?page=vehicle-settings&tab=display" class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Display Options', 'mhm-rentiva' ); ?>
			</a>
		</nav>
		<br>
```

**Step 2: Remove the `<br>` tag**

Replace the block above with:

```php
		<nav class="nav-tab-wrapper">
			<a href="?page=vehicle-settings&tab=definitions" class="nav-tab <?php echo $active_tab === 'definitions' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Field Definitions', 'mhm-rentiva' ); ?>
			</a>
			<a href="?page=vehicle-settings&tab=display" class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Display Options', 'mhm-rentiva' ); ?>
			</a>
		</nav>
```

**Step 3: Verify**

```bash
php -l plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
```

**Step 4: Commit**

```
git add plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "style(vehicle-settings): remove <br> spacer after nav tabs"
```

---

### Task 4: PHP — `render_display_tab()` inline style cleanup + `alert()` → `mhmShowNotice`

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` (lines ~339–636)

**What:** Remove all `style=""` attributes from `render_display_tab()`. Replace the `<hr style>` with `<hr class>`. Remove the `matchMedia` JS block. Replace `alert()` with `window.mhmShowNotice`.

**Step 1: `.mhm-card-fields-columns` — remove inline style**

Find (appears twice, for card fields and detail fields):
```php
					<div class="mhm-card-fields-columns" style="display: flex; gap: 20px;">
```
Replace both occurrences with:
```php
					<div class="mhm-card-fields-columns">
```

**Step 2: `.mhm-card-fields-column` — remove inline style (4 occurrences)**

Find ALL four instances of:
```php
						<div class="mhm-card-fields-column" style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
```
Replace all four with:
```php
						<div class="mhm-card-fields-column">
```

**Step 3: "Clear All" button — remove `style="float: right;"` (2 occurrences)**

Find:
```php
							<h4><?php echo esc_html__( 'Visible Items', 'mhm-rentiva' ); ?>
								<button type="button" id="clear-card-fields" class="button button-small" style="float: right;"><?php echo esc_html__( 'Clear All', 'mhm-rentiva' ); ?></button>
							</h4>
```
Replace with:
```php
							<h4><?php echo esc_html__( 'Visible Items', 'mhm-rentiva' ); ?>
								<button type="button" id="clear-card-fields" class="button button-small"><?php echo esc_html__( 'Clear All', 'mhm-rentiva' ); ?></button>
							</h4>
```

Find:
```php
							<h4><?php echo esc_html__( 'Visible in Vehicle Detail', 'mhm-rentiva' ); ?>
								<button type="button" id="clear-detail-fields" class="button button-small" style="float: right;"><?php echo esc_html__( 'Clear All', 'mhm-rentiva' ); ?></button>
							</h4>
```
Replace with:
```php
							<h4><?php echo esc_html__( 'Visible in Vehicle Detail', 'mhm-rentiva' ); ?>
								<button type="button" id="clear-detail-fields" class="button button-small"><?php echo esc_html__( 'Clear All', 'mhm-rentiva' ); ?></button>
							</h4>
```

**Step 4: Search input `style="width: 100%; margin..."` — 4 occurrences**

Find all 4 instances of `style="width: 100%; margin: 0 0 10px 0;"` on the search inputs and remove those `style` attributes. The CSS class `mhm-card-field-search` already handles this.

Example — find:
```php
							<input type="search" class="regular-text mhm-card-field-search" data-target="#mhm-card-fields-selected" placeholder="<?php echo esc_attr__( 'Search visible items...', 'mhm-rentiva' ); ?>" style="width: 100%; margin: 0 0 10px 0;">
```
Replace with:
```php
							<input type="search" class="regular-text mhm-card-field-search" data-target="#mhm-card-fields-selected" placeholder="<?php echo esc_attr__( 'Search visible items...', 'mhm-rentiva' ); ?>">
```
Do the same for all 4 search inputs.

**Step 5: Drop zone ULs — remove inline styles (4 occurrences)**

Find:
```php
							<ul id="mhm-card-fields-selected" class="mhm-card-fields-list" style="min-height: 80px; max-height: 280px; overflow-y: auto; overflow-x: hidden; border: 1px dashed #ccc; padding: 10px;">
```
Replace with:
```php
							<ul id="mhm-card-fields-selected" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items selected', 'mhm-rentiva' ); ?>">
```

Find:
```php
							<ul id="mhm-card-fields-available" class="mhm-card-fields-list" style="min-height: 80px; max-height: 380px; overflow-y: auto; overflow-x: hidden; border: 1px dashed #ccc; padding: 10px;">
```
Replace with:
```php
							<ul id="mhm-card-fields-available" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items available', 'mhm-rentiva' ); ?>">
```

Find:
```php
							<ul id="mhm-detail-fields-selected" class="mhm-card-fields-list" style="min-height: 80px; max-height: 280px; overflow-y: auto; overflow-x: hidden; border: 1px dashed #ccc; padding: 10px;">
```
Replace with:
```php
							<ul id="mhm-detail-fields-selected" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items selected', 'mhm-rentiva' ); ?>">
```

Find:
```php
							<ul id="mhm-detail-fields-available" class="mhm-card-fields-list" style="min-height: 80px; max-height: 380px; overflow-y: auto; overflow-x: hidden; border: 1px dashed #ccc; padding: 10px;">
```
Replace with:
```php
							<ul id="mhm-detail-fields-available" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items available', 'mhm-rentiva' ); ?>">
```

**Step 6: Footer p — remove inline style (2 occurrences)**

Find:
```php
				<p class="description mhm-card-fields-footer" style="margin-top: 10px;">
```
Replace both occurrences with:
```php
				<p class="description mhm-card-fields-footer">
```

**Step 7: `<hr>` → semantic class**

Find:
```php
		<hr style="margin: 30px 0;">
```
Replace with:
```php
		<hr class="mhm-section-divider">
```

**Step 8: Save button wrapper → semantic classes**

Find:
```php
		<div style="margin-top: 20px;">
			<input type="hidden" name="action" value="save_vehicle_settings">
			<input type="hidden" name="sub_action" value="save_display_settings">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'vehicle_settings_nonce' ) ); ?>">
			<button type="submit" id="save-display-settings" class="button button-primary button-large"><?php echo esc_html__( 'Save Display Settings', 'mhm-rentiva' ); ?></button>
		</div>
```
Replace with:
```php
		<div class="mhm-display-save-actions submit-section">
			<input type="hidden" name="action" value="save_vehicle_settings">
			<input type="hidden" name="sub_action" value="save_display_settings">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'vehicle_settings_nonce' ) ); ?>">
			<button type="submit" id="save-display-settings" class="button button-primary button-large"><?php echo esc_html__( 'Save Display Settings', 'mhm-rentiva' ); ?></button>
		</div>
```

**Step 9: Remove `matchMedia` JS block and replace `alert()` with `mhmShowNotice`**

Find the block (inside the inline `<script>` in `render_display_tab()`):
```js
			if (window.matchMedia('(max-width: 1280px)').matches) {
				$('.mhm-card-fields-columns').css({
					display: 'grid',
					gridTemplateColumns: '1fr',
					gap: '14px'
				});
			}
```
Delete this block entirely (CSS now handles responsive).

Find in the display form submit handler:
```js
					if (response.success) {
						alert('<?php echo esc_js( __( 'Settings saved successfully!', 'mhm-rentiva' ) ); ?>');
						window.location.reload();
					} else {
						alert('<?php echo esc_js( __( 'Error saving settings.', 'mhm-rentiva' ) ); ?>');
					}
```
Replace with:
```js
					if (response.success) {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice('<?php echo esc_js( __( 'Settings saved successfully!', 'mhm-rentiva' ) ); ?>', 'success');
						}
						window.location.reload();
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice('<?php echo esc_js( __( 'Error saving settings.', 'mhm-rentiva' ) ); ?>', 'error');
						}
					}
```

**Step 10: PHP lint check**

```bash
php -l plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
```
Expected: `No syntax errors detected`

**Step 11: Commit**

```
git add plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "style(vehicle-settings): remove all inline styles from render_display_tab, replace alert() with mhmShowNotice, remove matchMedia JS"
```

---

### Task 5: PHP — `render_definitions_tab()` inline style cleanup

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` (lines ~643–853 plus rename modal script ~1330)

**What:** Remove remaining inline styles from the definitions tab and rename modal `alert()` calls.

**Step 1: `mhm-card-section-header h4` — remove inline `style="margin: 0;"`**

Find ALL occurrences of the pattern `<h4 style="margin: 0;">` inside `.mhm-card-section-header` divs. There are 3 (Details, Features, Equipment):
```php
						<h4 style="margin: 0;"><?php echo esc_html__( 'Attributes & Custom Details', 'mhm-rentiva' ); ?></h4>
```
```php
						<h4 style="margin: 0;"><?php echo esc_html__( 'Standard Features', 'mhm-rentiva' ); ?></h4>
```
```php
						<h4 style="margin: 0;"><?php echo esc_html__( 'Standard Equipment', 'mhm-rentiva' ); ?></h4>
```
Replace all three — remove `style="margin: 0;"`. The `.mhm-card-section-header h4` CSS rule handles this.

**Step 2: Remove button inline `style="color: #dc3545; line-height: 1;"` (3 occurrences)**

Find all 3 instances of:
```php
								<button type="button" class="button-link remove-custom-detail" data-key="<?php echo esc_attr( $key ); ?>" style="color: #dc3545; line-height: 1;">&times;</button>
```
```php
								<button type="button" class="button-link remove-custom-feature" data-key="<?php echo esc_attr( $key ); ?>" style="color: #dc3545; line-height: 1;">&times;</button>
```
```php
								<button type="button" class="button-link remove-custom-equipment" data-key="<?php echo esc_attr( $key ); ?>" style="color: #dc3545; line-height: 1;">&times;</button>
```
Remove `style="color: #dc3545; line-height: 1;"` from all three. The `.mhm-checkbox-item .button-link[class*="remove-"]` CSS rule handles color.

**Step 3: "Add Custom" wrapper divs — `style="margin-top: 15px;"` → class (3 occurrences)**

Find all 3:
```php
					<div style="margin-top: 15px;">
						<div class="mhm-add-custom-row">
```
Replace with:
```php
					<div class="mhm-add-custom-wrapper">
						<div class="mhm-add-custom-row">
```

**Step 4: Custom detail input — `style="width: 200px;"` → class**

Find:
```php
							<input type="text" id="new-custom-detail-name" placeholder="<?php esc_attr_e( 'Custom detail name', 'mhm-rentiva' ); ?>" style="width: 200px;">
```
Replace with:
```php
							<input type="text" id="new-custom-detail-name" placeholder="<?php esc_attr_e( 'Custom detail name', 'mhm-rentiva' ); ?>">
```

**Step 5: Custom detail type select — `style="width: 120px;"` → class**

Find:
```php
							<select id="new-custom-detail-type" style="width: 120px;">
```
Replace with:
```php
							<select id="new-custom-detail-type">
```

**Step 6: Custom detail options input — `style="width: 300px;"` → class + `mhm-select-options-input`**

Find:
```php
								<input type="text" id="new-custom-detail-options" placeholder="<?php esc_attr_e( 'Options (comma separated: Petrol, Diesel)', 'mhm-rentiva' ); ?>" style="width: 300px;">
```
Replace with:
```php
								<input type="text" id="new-custom-detail-options" class="mhm-select-options-input" placeholder="<?php esc_attr_e( 'Options (comma separated: Petrol, Diesel)', 'mhm-rentiva' ); ?>">
```

**Step 7: Custom feature/equipment name inputs — `style="width: 250px;"` (2 occurrences)**

Find:
```php
							<input type="text" id="new-custom-feature-name" placeholder="<?php esc_attr_e( 'Custom feature name', 'mhm-rentiva' ); ?>" style="width: 250px;">
```
Replace with:
```php
							<input type="text" id="new-custom-feature-name" placeholder="<?php esc_attr_e( 'Custom feature name', 'mhm-rentiva' ); ?>">
```

Find:
```php
							<input type="text" id="new-custom-equipment-name" placeholder="<?php esc_attr_e( 'Custom equipment name', 'mhm-rentiva' ); ?>" style="width: 250px;">
```
Replace with:
```php
							<input type="text" id="new-custom-equipment-name" placeholder="<?php esc_attr_e( 'Custom equipment name', 'mhm-rentiva' ); ?>">
```

**Step 8: Custom Features and Equipment section headers — replace inline h4 with class**

Find:
```php
						<h4 style="margin-top: 20px; font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;"><?php echo esc_html__( 'Custom Features', 'mhm-rentiva' ); ?></h4>
```
Replace with:
```php
						<h4 class="mhm-custom-section-header"><?php echo esc_html__( 'Custom Features', 'mhm-rentiva' ); ?></h4>
```

Find:
```php
						<h4 style="margin-top: 20px;"><?php echo esc_html__( 'Custom Equipment', 'mhm-rentiva' ); ?></h4>
```
Replace with:
```php
						<h4 class="mhm-custom-section-header"><?php echo esc_html__( 'Custom Equipment', 'mhm-rentiva' ); ?></h4>
```

**Step 9: Custom list wrappers — `style="margin-top: 10px;"` (2 occurrences)**

Find:
```php
						<div class="mhm-custom-list" id="custom-features-list" style="margin-top: 10px;">
```
Replace with:
```php
						<div class="mhm-custom-list" id="custom-features-list">
```

Find:
```php
						<div class="mhm-custom-list" id="custom-equipment-list" style="margin-top: 10px;">
```
Replace with:
```php
						<div class="mhm-custom-list" id="custom-equipment-list">
```

**Step 10: Rename modal `alert()` → `mhmShowNotice` (3 occurrences in JS block ~line 1332)**

Find:
```js
							alert('<?php echo esc_js( __( 'Field names updated and saved!', 'mhm-rentiva' ) ); ?>');
```
Replace with:
```js
							if (typeof window.mhmShowNotice === 'function') {
								window.mhmShowNotice('<?php echo esc_js( __( 'Field names updated and saved!', 'mhm-rentiva' ) ); ?>', 'success');
							}
```

Find:
```js
							alert('<?php echo esc_js( __( 'Error: Field names could not be saved!', 'mhm-rentiva' ) ); ?>');
```
Replace with:
```js
							if (typeof window.mhmShowNotice === 'function') {
								window.mhmShowNotice('<?php echo esc_js( __( 'Error: Field names could not be saved!', 'mhm-rentiva' ) ); ?>', 'error');
							}
```

Find:
```js
						alert('<?php echo esc_js( __( 'An error occurred!', 'mhm-rentiva' ) ); ?>');
```
Replace with:
```js
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice('<?php echo esc_js( __( 'An error occurred!', 'mhm-rentiva' ) ); ?>', 'error');
						}
```

**Step 11: PHP lint check**

```bash
php -l plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
```
Expected: `No syntax errors detected`

**Step 12: Commit**

```
git add plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "style(vehicle-settings): remove inline styles from render_definitions_tab, replace alert() in rename modal with mhmShowNotice"
```

---

### Task 6: PHP — `render_card_field_list_item()` inline style removal + drag handle

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` (method `render_card_field_list_item`, lines ~1350–1374)

**What:** The `<li>` and `<span>` in the list item helper use `style=""` directly inside the `sprintf` string. Replace with class-based styling. Add a drag handle `<span>` before the label.

**Step 1: Find the method**

```php
	private static function render_card_field_list_item( string $type, string $key, string $label, bool $selected ): string {
		$type  = sanitize_key( $type );
		$key   = sanitize_key( $key );
		$label = esc_html( $label );

		$remove_button = $selected
			? '<button type="button" class="button-link remove-field" aria-label="' . esc_attr__( 'Remove item', 'mhm-rentiva' ) . '">&times;</button>'
			: '';

		// Add class for styling
		$class = 'mhm-card-field-item';
		if ( $selected ) {
			$class .= ' selected';
		}

		return sprintf(
			'<li class="%5$s" data-field-type="%1$s" data-field-key="%2$s" style="padding: 8px; margin: 5px 0; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; cursor: move;">
                <span class="mhm-card-field-label" style="font-weight: 500;">%3$s</span>
                %4$s
            </li>',
			esc_attr( $type ),
			esc_attr( $key ),
			$label,
			$remove_button,
			$class
		);
	}
```

**Step 2: Replace the method body**

Replace the entire `return sprintf(...)` call and the `$remove_button` assignment with the version below. Note: the drag handle uses a `dashicons dashicons-menu` span (the ⠿ grid icon) which is available in WordPress admin. We add it before the label.

```php
	private static function render_card_field_list_item( string $type, string $key, string $label, bool $selected ): string {
		$type  = sanitize_key( $type );
		$key   = sanitize_key( $key );
		$label = esc_html( $label );

		$remove_button = $selected
			? '<button type="button" class="button-link remove-field" aria-label="' . esc_attr__( 'Remove item', 'mhm-rentiva' ) . '">&times;</button>'
			: '';

		$class = 'mhm-card-field-item';
		if ( $selected ) {
			$class .= ' selected';
		}

		$drag_handle = '<span class="mhm-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>';

		return sprintf(
			'<li class="%5$s" data-field-type="%1$s" data-field-key="%2$s">%6$s<span class="mhm-card-field-label">%3$s</span>%4$s</li>',
			esc_attr( $type ),
			esc_attr( $key ),
			$label,
			$remove_button,
			$class,
			$drag_handle
		);
	}
```

**Step 3: PHP lint check**

```bash
php -l plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
```

**Step 4: Commit**

```
git add plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "style(vehicle-settings): remove inline styles from render_card_field_list_item, add drag handle dashicon"
```

---

## Final Verification Checklist

After all tasks complete, verify:

1. **No remaining `style=""` in `render_display_tab()`** — grep check:
   ```bash
   grep -n 'style="' plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php | grep -v '// phpcs\|#new-custom-detail-options-wrapper'
   ```
   Expected: zero results (the only acceptable `style` is the JS-controlled `display:none` on `#new-custom-detail-options-wrapper`)

2. **No `alert()` in VehicleSettings.php**:
   ```bash
   grep -n "alert('" plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
   ```
   Expected: zero results

3. **No hardcoded hex in vehicle-settings.css**:
   ```bash
   grep -n '#[0-9a-fA-F]\{3,6\}' plugins/mhm-rentiva/assets/css/admin/vehicle-settings.css
   ```
   Expected: zero results

4. **PHP syntax clean**:
   ```bash
   php -l plugins/mhm-rentiva/src/Admin/Vehicle/Settings/VehicleSettings.php
   ```

5. **Visual check in browser**: Open Vehicle Settings → Field Definitions tab → drag-handle icons visible on list items → mobile at 375px → card columns stacking vertically via CSS.
