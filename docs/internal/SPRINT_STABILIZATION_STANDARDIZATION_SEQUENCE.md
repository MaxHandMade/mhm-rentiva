# Stabilization + Standardization Sprint Sequence

## Scope
This plan is sequence-based (no date/day dependency).  
Execute each step in order, but move faster/slower as needed.

## Sequence 1: Baseline Stabilization
1. Stabilize My Account endpoint layout and shared wrappers.
2. Remove endpoint-specific CSS drift and enforce one navigation/content structure.
3. Verify desktop and mobile overflow on all `hesabim/*` pages.

### Primary Files
- `assets/css/frontend/my-account.css`
- `src/Admin/Frontend/Account/WooCommerceIntegration.php`
- `templates/account/*.php`

## Sequence 2: Vehicle Card Unification
1. Make vehicle cards identical across Grid/List/Favorites contexts.
2. Keep icon rendering (favorite/compare/rating/features) consistent.
3. Ensure CTA/button alignment is stable across variable content lengths.

### Primary Files
- `assets/css/core/vehicle-card.css`
- `templates/partials/vehicle-card.php`
- `templates/partials/vehicle-card-base.php`
- `src/Admin/Frontend/Shortcodes/VehiclesList.php`

## Sequence 3: Search/Transfer Result Consistency
1. Fix layout and column behavior parity in transfer/search result blocks.
2. Ensure route/header areas do not incorrectly consume card columns.
3. Align block editor settings with frontend output.

### Primary Files
- `templates/shortcodes/transfer-results.php`
- `assets/css/frontend/transfer-results.css`
- `src/Admin/Transfer/Frontend/TransferResults.php`

## Sequence 4: Block / Elementor Parity Hardening
1. Map all controls used in Gutenberg blocks vs Elementor widgets.
2. Remove fake/non-functional controls.
3. Apply a single parity contract and document exceptions.

### Primary Files
- `src/Admin/Frontend/Widgets/Base/ElementorWidgetBase.php`
- `src/Blocks/BlockRegistry.php`
- `docs/internal/BLOCK_ELEMENTOR_PARITY_MATRIX.md`

## Sequence 5: Mobile Overflow and Spacing Contract
1. Enforce shared spacing and container rules for shortcode pages.
2. Eliminate horizontal scrollbar regressions.
3. Keep card paddings/margins in premium visual baseline.

### Primary Files
- `assets/css/core/golden-ratio-contract.css`
- `assets/css/frontend/gutenberg-blocks.css`
- `assets/css/frontend/featured-vehicles.css`
- `docs/internal/SHORTCODE_MOBILE_OVERFLOW_MATRIX.md`
- `docs/internal/SHORTCODE_PAGE_SPACING_VALIDATION.md`

## Sequence 6: Documentation as Source of Truth
1. Finalize shortcode parameter contract (classic + block attrs + dependencies).
2. Keep generic template docs reusable for future projects.
3. Ensure every behavior change is reflected in docs before closing.

### Primary Files
- `SHORTCODES.md`
- `docs/template/generic/README.md`
- `docs/template/generic/CI_CHECKLIST.md`
- `docs/template/generic/GOVERNANCE_GATES.md`
- `docs/template/generic/FOLDER_STRUCTURE.md`
- `docs/template/generic/walkthrough.md`

## Sequence 7: Final Hardening and Release Gate
1. Run full smoke checks for key shortcode pages and account endpoints.
2. Run coding standard checks and PHP syntax checks on changed files.
3. Confirm source/build sync and close with final report.

### Primary Files
- `docs/template/CI_CHECKLIST.md`
- `docs/internal/ELEMENTOR_WIDGETS_SMOKE_CHECKLIST.md`
- `docs/internal/BLOCK_ELEMENTOR_PARITY_MATRIX.md`

## Mandatory Verification at Every Sequence
1. PHP syntax check (`php -l`) for changed PHP files.
2. WPCS/PHPCS for changed scope.
3. Desktop + mobile visual check for touched screens.
4. Overflow check (`horizontal scroll = none`) for touched screens.
5. Source/build synchronization check for touched assets.

## Definition of Done
1. No layout break on My Account endpoints.
2. Vehicle cards are visually/structurally consistent across contexts.
3. Block and Elementor controls are parity-aligned and documented.
4. Shortcode documentation is complete and trustworthy.
5. All changes are verified with evidence and no unresolved regression.

