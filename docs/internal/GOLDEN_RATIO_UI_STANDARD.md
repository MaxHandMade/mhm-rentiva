# Golden Ratio UI Standard (Shortcodes + Blocks + Elementor)

## Goal
- Keep all frontend modules visually consistent without per-widget manual fixes.
- Use one rhythm system for spacing, card sizing, CTA sizing, and mobile breakpoints.

## Contract
- Core file: `assets/css/core/golden-ratio-contract.css`
- Global spacing scale:
`8px -> 13px -> 21px -> 34px`
- Shared mobile breakpoint:
`782px` (WordPress standard)
- Shared surface gutter:
desktop `13px`, mobile `13px` (safe-area aware)

## Enforcement
- Frontend core enqueue:
`src/Admin/Core/AssetManager.php`
- Block/editor enqueue:
`src/Blocks/BlockRegistry.php`
- Global wrapper coverage:
search, transfer, vehicles grid/list, booking/contact/details/testimonials wrappers
- Opt-out class:
`rv-surface--flush`

## Transfer/Search Parity Fix
- Canonical transfer card template:
`templates/partials/transfer-card.php`
- SSR transfer results uses canonical card:
`templates/shortcodes/transfer-results.php`
- AJAX transfer results uses same canonical card:
`src/Admin/Transfer/Frontend/TransferShortcodes.php`

## QA Checks
1. Compare `BLOCK`, `CLASSIC`, and `Elementor` output on same page.
2. Verify card width/spacing and button size at:
`1440px`, `1024px`, `782px`, `480px`.
3. Verify transfer and search results use same card rhythm and mobile stack behavior.

## Rollout Status (Phase 2)
- `transfer-results`: canonicalized (SSR + AJAX use same partial).
- `search-results`: golden-ratio spacing tokens applied.
- `vehicles-grid`: mobile breakpoint aligned to `782px`.
- `vehicles-list`: spacing and radius tokens aligned.
- `transfer.css`: legacy result card rules scoped to legacy container to prevent modern card conflicts.

## Rollout Status (Phase 3)
- `search-results`: surface gutter reset removed (`padding-inline: 0` eliminated).
- `vehicles-grid`: surface gutter reset removed and mobile gutter token applied.
- `vehicles-list`: desktop gutter tokens applied and background-card spacing/radius tokenized.
- `premium transfer wrapper`: included in shared surface contract (`.mhm-premium-transfer-search`).

## Rollout Status (Phase 4)
- `featured-vehicles`: wrapper horizontal zero-padding removed (`padding: 2rem 0` -> tokenized inline + block spacing).
- Shared contract wrapper list expanded for cross-shortcode surface parity:
`rv-unified-search`, `mhm-transfer-search-wrapper`, `mhm-rentiva-featured-wrapper`, `mhm-rentiva-account-page`, `rv-unified-ratings-section`.
- Page-by-page validation report added:
`docs/internal/SHORTCODE_PAGE_SPACING_VALIDATION.md`.
