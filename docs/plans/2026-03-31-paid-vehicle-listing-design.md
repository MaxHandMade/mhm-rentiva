# Paid Vehicle Listing — Design Document

**Date:** 2026-03-31
**Status:** Approved
**Feature:** Charge vendors a fee when their vehicle goes live (new listing, renew, relist)

---

## Overview

Vendors pay a WooCommerce-based fee each time a vehicle is published. The feature is toggled and configured from admin settings. Existing commission system remains unchanged — listing fee is an independent revenue stream.

## Admin Settings (Settings → Vendor Marketplace)

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `listing_fee_enabled` | Toggle (bool) | `false` | Enable/disable paid listings |
| `listing_fee_model` | Select | `one_time` | `one_time` or `per_period` (90-day lifecycle) |
| `listing_fee_amount` | Decimal | `0` | Fee amount in store currency |

When disabled, all vehicle submission/renew/relist flows behave as before (no payment step).

## User Flow

### Happy Path

```
Vendor clicks "Add Vehicle" / "Renew" / "Relist"
  ↓
Is listing_fee_enabled? → No → existing free flow
  ↓ Yes
Vehicle form submitted → vehicle saved as draft (not pending_review yet)
  ↓
WC cart cleared → listing-fee product added with vehicle_id meta
  ↓
Redirect to WC Checkout
  ↓
Payment completed (order status = completed)
  ↓
Hook: woocommerce_order_status_completed
  ↓
Vehicle transitions to pending_review (new) or lifecycle renews (renew/relist)
```

### Edge Cases

- **Payment abandoned:** Vehicle stays as draft, not visible. Vendor can retry from listings page.
- **Payment refunded:** Vehicle reverts to draft (admin decision — not automatic).
- **Feature disabled mid-payment:** Order completes normally, vehicle published. Feature toggle only affects future submissions.
- **Price changed mid-session:** Cart uses price at checkout time (dynamic from settings, not stored on product).

## WooCommerce Product

- **Type:** `WC_Product_Simple`
- **SKU:** `mhm-rentiva-listing-fee`
- **Catalog visibility:** `hidden` (not shown in shop/search)
- **Created automatically** when feature is first enabled (similar to `mhm-rentiva-booking` pattern)
- **Price:** Read dynamically from `listing_fee_amount` setting at cart/checkout time (not stored on product)
- **Cart item meta:** `_mhm_listing_vehicle_id`, `_mhm_listing_action` (new/renew/relist)

## Vendor Listings UI — Remaining Time

Each vehicle card in vendor dashboard shows listing duration info:

- **Active vehicles:** "Remaining: X days" with color coding:
  - Green: > 50% time remaining
  - Yellow: 20–50% remaining
  - Red: < 20% remaining
- **Expired vehicles:** "Expired" badge (already exists)
- **Source:** `_mhm_vehicle_listing_started_at` and `_mhm_vehicle_listing_expires_at` meta

## Trigger Points

All three actions require payment when feature is enabled:

| Action | Current Behavior | New Behavior (fee enabled) |
|--------|-----------------|---------------------------|
| **Add Vehicle** | Form → pending_review | Form → draft → WC checkout → pending_review |
| **Renew** (expired) | AJAX → lifecycle renew | AJAX → WC checkout → lifecycle renew |
| **Relist** (withdrawn) | AJAX → lifecycle relist | AJAX → WC checkout → lifecycle relist |

## Existing Vehicles (Grandfather Rule)

When the feature is enabled for the first time:
- Already published/active vehicles are NOT affected
- When their 90-day listing expires, renewal requires payment
- No retroactive charges

## Relationship with Commission

- Listing fee and commission are **independent**
- Vendor pays listing fee (upfront, per-listing) AND commission (percentage, per-booking)
- No discount or offset between the two

## Settings Key Mapping

All settings stored under `mhm_rentiva_settings` serialized option:

```
mhm_rentiva_listing_fee_enabled  → bool
mhm_rentiva_listing_fee_model    → 'one_time' | 'per_period'
mhm_rentiva_listing_fee_amount   → float
```

## Out of Scope

- Credit/package system (buy N listings upfront)
- Ledger-based deduction from vendor balance
- Different prices per vehicle type/category
- Promotional/discount codes for listing fees
- Free trial listings for new vendors

## Dependencies

- WooCommerce active
- Vendor Marketplace enabled (`Mode::canUseVendorMarketplace()`)
- Vehicle Lifecycle Management (for renew/relist triggers and expiry dates)

## Files Expected to Change

- `SettingsCore.php` — new settings fields
- `VehicleSubmit.php` — payment gate before pending_review
- `VehicleLifecycleManager.php` — payment gate on renew/relist
- `vendor-listings.php` template — remaining time display + payment redirect for renew/relist
- New: `ListingFeeManager.php` — WC product creation, cart handling, order completion hook
- `languages/*.po` + `*.l10n.php` — translations
