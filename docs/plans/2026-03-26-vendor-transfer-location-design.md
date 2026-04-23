# Vendor-Location-Transfer Architecture Design

**Date:** 2026-03-26
**Status:** Approved

## Problem

Vendor vehicles currently have no geographic binding to transfer locations/routes. An Ankara-based vendor sees and can select Istanbul transfer routes. The transfer search engine (`TransferSearchEngine`) doesn't filter vehicles by their assigned locations — it only checks `service_type`, `max_pax`, and luggage capacity. This creates a fundamental mismatch: vendors can claim to serve routes they can't physically reach.

Additionally:
- Vendor vehicles use `_mhm_rentiva_vehicle_city` (free text) while admin locations use `rentiva_transfer_locations` table (structured data) — no link between them.
- Transfer pricing is admin-controlled (`base_price` on routes), with no mechanism for vendor-specific pricing.
- Transfer vehicle metadata (max passengers, luggage capacity) has no vendor-facing input — only admin meta box.

## Decisions

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| 1 | Location hierarchy | City → Point | Each transfer location gets a `city` column. Vendor's city filters which locations/routes they see. |
| 2 | Transfer enrollment | Manual selection | Vendor picks locations/routes from their city (existing UI preserved). No auto-enrollment. |
| 3 | Multi-city | No — single city per vendor | Simplicity. Multiple cities = multiple vendor accounts. |
| 4 | Pricing | Vendor sets price within admin min/max range | Admin defines `min_price`/`max_price` per route. Vendor sets their price within that range. |
| 5 | City selection timing | At vendor application | City is set during `rentiva_vendor_apply`. Changed only by admin. |
| 6 | Transfer capacity fields | Vendor fills (except price multiplier) | Max passengers, luggage scores, bag limits — vendor knows their vehicle. Price multiplier stays admin-only. |

## Database Changes

### `rentiva_transfer_locations` table — add column

```sql
ALTER TABLE {prefix}rentiva_transfer_locations
ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT '' AFTER name;
```

- Each location point belongs to a city (e.g., "İstanbul Havalimanı (IST)" → city: "İstanbul")
- Admin fills this when creating/editing locations

### `rentiva_transfer_routes` table — add column

```sql
ALTER TABLE {prefix}rentiva_transfer_routes
ADD COLUMN max_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER min_price;
```

- `min_price`: floor (vendor can't go below)
- `max_price`: ceiling (vendor can't exceed). `0` = no ceiling.

### Vehicle post meta (existing keys, vendor-writable)

| Meta Key | Type | Description |
|----------|------|-------------|
| `_rentiva_transfer_max_pax` | int | Max passengers excluding driver |
| `_rentiva_transfer_max_luggage_score` | float | Total luggage score capacity |
| `_rentiva_vehicle_max_big_luggage` | int | Max big suitcases |
| `_rentiva_vehicle_max_small_luggage` | int | Max small bags |
| `_mhm_rentiva_transfer_locations` | array | Selected location IDs (filtered by city) |
| `_mhm_rentiva_transfer_routes` | array | Selected route IDs (filtered by city) |

### New vehicle post meta

| Meta Key | Type | Description |
|----------|------|-------------|
| `_mhm_rentiva_transfer_route_prices` | JSON | `{"route_id": price}` — vendor-specific price per route |

### Vendor user meta

| Meta Key | Type | Description |
|----------|------|-------------|
| `_mhm_rentiva_vendor_city` | string | Vendor's city (set at application, admin-changeable) |

## Flow Changes

### 1. Admin: Location Management
- **TransferAdmin.php** location form gets a `city` text/select input
- Existing locations need city assignment (one-time migration or manual)

### 2. Admin: Route Management
- **TransferAdmin.php** route form gets `max_price` input alongside existing `min_price`/`base_price`

### 3. Vendor: Application
- **VendorApplicationManager** already saves city. No change needed — `_mhm_rentiva_vendor_city` exists.

### 4. Vendor: Vehicle Submission (transfer mode)
- **VehicleSubmit.php** "Transfer Hizmeti Ayarları" section:
  - Reads vendor's city from user meta
  - Queries `rentiva_transfer_locations` WHERE `city = vendor_city`
  - Shows only matching locations as checkboxes
  - Queries `rentiva_transfer_routes` WHERE origin/destination in those location IDs
  - Shows only matching routes as checkboxes
  - Each selected route gets a price input (with min/max hint from route)
  - Transfer capacity fields: max passengers, luggage score, big/small bag limits
  - City field is read-only (auto-filled from vendor profile)

### 5. Vendor: Vehicle Edit
- Same filtering as submission. Existing selections preserved.

### 6. Transfer Search Engine
- **TransferSearchEngine.php** `search()` method:
  - After finding vehicles by service_type/capacity, also check `_mhm_rentiva_transfer_routes` contains the matched route ID
  - Use vendor's route price (`_mhm_rentiva_transfer_route_prices`) instead of route `base_price` when available
  - Fallback to route `base_price` if vendor hasn't set a price (shouldn't happen with validation, but defensive)

### 7. Vehicle Card (search results)
- City-based location name already resolved (fallback to `_mhm_rentiva_vehicle_city` implemented in this session)

## Files to Modify

| File | Change |
|------|--------|
| `DatabaseMigrator.php` | Add `city` column to locations, `max_price` to routes |
| `TransferAdmin.php` | City input on location form, max_price on route form |
| `LocationProvider.php` | `get_by_city(string $city)` method |
| `VehicleSubmit.php` | City-filtered location/route selection, price inputs, capacity fields |
| `TransferSearchEngine.php` | Route-based vehicle filtering, vendor pricing |
| `VehicleTransferMetaBox.php` | Respect vendor city for admin editing vendor vehicles |

## Not in Scope

- Multi-city vendor support
- Price multiplier for vendors (admin-only)
- Automatic "seat count - 1" calculation for max passengers
- Vendor route request/approval workflow (considered, rejected for simplicity — vendor selects from existing routes)
- Geographic validation (e.g., checking if vendor is actually in the city they claim)
