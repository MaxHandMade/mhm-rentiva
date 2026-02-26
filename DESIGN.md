# Design System: mhm-rentiva-ui-beta
**Project ID:** 15079435806262180565

## 1. Visual Theme and Atmosphere
MHM Rentiva has a modern and premium SaaS aesthetic.
Design intent is "Airy", "Premium", and "Minimalist".
The UI is built on a pure white (`#FFFFFF`) base and creates depth with soft, diffused shadows and generous whitespace rather than heavy shadows.

## 2. Color Palette and Roles
- **Modern Blue (`#137FEC`)**: Primary brand color. Use for key CTAs (for example "Rezervasyon Yap"), active tabs, and major emphasis.
- **Pure White (`#FFFFFF`)**: Main page and card background color.
- **Slate Navy (`#1E293B`)**: Primary heading and high-importance text color.
- **Soft Cloud Gray (`#F8FAFC`)**: Section separators and secondary surface backgrounds.
- **Neutral Gray (`#64748B`)**: Supporting text, helper content, and icon color.

## 3. Typography Rules
- **Font Family**: Plus Jakarta Sans (primary font family).
- **Headers**: `#1E293B`, bold/semi-bold, clear H1-H3 hierarchy.
- **Body**: Comfortable reading density with `line-height: 1.6`.

## 4. Component Styling
- **Buttons**:
  - Primary: Modern Blue (`#137FEC`) background with white text.
  - Radius: `16px`.
  - Size: Large, touch-friendly height.
- **Cards and Containers**:
  - Radius: `16px`.
  - Background: White.
  - Shadow: Soft diffused shadow.
  - Padding: `20px-24px`.
- **Vehicle Meta Chips**:
  - Light gray, pill-shaped tags.
  - Preferred order: Transmission -> Fuel/Type -> Seat Count.

## 5. Layout Principles
- **Grid**: On desktop, prefer two-column structures (for example `35/65` or `Sidebar/Content`).
- **Spacing Scale**: Keep spacing consistent and generous to preserve premium feel.
- **Sticky Elements**: Header remains sticky for quick navigation.
- **Mobile First**: On mobile, cards and forms collapse into clean vertical stacking.

## 6. Implementation Notes
- This document defines visual direction and design tokens.
- Theme and template implementation must follow `UI_CONTRACTS.md` for stable wrappers and slots.
- Do not bind styling to internal DOM structure or internal-only selectors.
