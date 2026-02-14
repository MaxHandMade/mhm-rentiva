# M5 Vehicles List Asset Audit

**Step 3B: Asset Analysis**
**Scenario:** `[rentiva_vehicles_list]`
**Total Plugin CSS:** 8 Files (Plus core/external)

## 1. Inventory

| Handle | File | Owner | Status | Analysis |
| :--- | :--- | :--- | :--- | :--- |
| `mhm-theme-header` | `header.css` | Theme Integration | **Keep** | Essential for layout integration. |
| `mhm-rentiva-my-account` | `my-account.css` | My Account | **REMOVE** | Irrelevant for public vehicle list. |
| `mhm-rentiva-stats-cards` | `stats-cards.css` | My Account | **REMOVE** | Dashboard legacy artifact. |
| `mhm-rentiva-booking-detail` | `booking-detail.css` | My Account | **REMOVE** | Detail view styles not needed here. |
| `mhm-rentiva-bookings-page` | `bookings-page.css` | My Account | **REMOVE** | User bookings list styles. |
| `mhm-rentiva-addons` | `addon-booking.css` | Booking Form | **Conditional** | Only needed if filters use addon logic (unlikely). |
| `mhm-rentiva-notifications` | `notifications.css` | Core | **Keep** | Toast notifications (e.g., "Added to compare"). |
| `mhm-rentiva-vehicles-list` | `vehicles-list.css` | Shortcode | **Keep** | **The Core Style**. |

## 2. Findings
*   **Leakage:** The "My Account" styles are heavily leaking. 4 out of 8 files belong to the user dashboard (`my-account.css`, `stats-cards.css`, etc.).
*   **Root Cause:** These are likely enqueued globally or by a shared parent controller/class that initializes the My Account assets regardless of the current shortcode.
*   **Target:** Removing the 4 My Account files + 1 Addon file reduces the count from 8 to **3 specific files**.

## 3. Optimization Strategy
*   **Action:** strict checking in `AssetManager` or the respective shortcode classes.
*   **Implementation:** Ensure `MyAccount` assets are wrapped in `is_account_page()` or similar checks, or only enqueued by the `[rentiva_my_account]` shortcode.

**Potential Reduction:** 8 Files -> 3 Files (62% Reduction).
