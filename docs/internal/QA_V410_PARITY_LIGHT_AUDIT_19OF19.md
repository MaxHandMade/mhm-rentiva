# 🔎 v4.10.0 — 19/19 Shortcode Light Parity Audit

🎯 **Amaç**: 19 shortcode'un tamamı için alias ihtiyacı, default drift, type drift ve block ↔ shortcode surface senkronizasyonunu hızlı ama kanıtlı olarak doğrulamak.

> [!IMPORTANT]
> Bu denetim PASS olmadan v4.10.0 release-freeze yapılmaz.

---

## Audit Tablosu

| # | Shortcode | Block Slug | Special Alias? | Default Drift? | Type Drift? | Evidence |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | `rentiva_availability_calendar` | `availability-calendar` | No | No | No | No special casing required — verified |
| 2 | `rentiva_booking_confirmation` | `booking-confirmation` | No | No | No | No special casing required — verified |
| 3 | `rentiva_booking_form` | `booking-form` | **Yes** (scope-isolated) | **No** (dynamic defaults preserved) | No | [PR-03B Evidence](QA_V410_PR03B_BOOKING_FORM_EVIDENCE.md) |
| 4 | `rentiva_contact` | `contact` | No | No | No | No special casing required — verified |
| 5 | `rentiva_featured_vehicles` | `featured-vehicles` | No | No | No | No special casing required — verified |
| 6 | `rentiva_messages` | `messages` | No | No | No | No special casing required — verified (1 key: `hide_nav`) |
| 7 | `rentiva_my_bookings` | `my-bookings` | No | No | No | No special casing required — verified (5 keys) |
| 8 | `rentiva_my_favorites` | `my-favorites` | No | No | No | No special casing required — verified |
| 9 | `rentiva_payment_history` | `payment-history` | No | No | No | No special casing required — verified (2 keys) |
| 10 | `rentiva_search_results` | `search-results` | No | No | No | No special casing required — verified |
| 11 | `rentiva_testimonials` | `testimonials` | **Yes** (global aliases) | **No** | No | [PR-04 Evidence](QA_V410_PR04_TESTIMONIALS_EVIDENCE.md) |
| 12 | `rentiva_transfer_search` | `transfer-search` | No | No | No | [Transfer Evidence](QA_V410_TRANSFER_SEARCH_EVIDENCE.md) |
| 13 | `rentiva_transfer_results` | `transfer-results` | No | No | No | No special casing required — verified (2 keys) |
| 14 | `rentiva_unified_search` | `unified-search` | No | No | No | No special casing required — verified |
| 15 | `rentiva_vehicle_comparison` | `vehicle-comparison` | **Yes** (scope-isolated) | **No** | **Yes** (`showFeatures` transform) | [PR-05 Evidence](QA_V410_PR05_VEHICLE_COMPARISON_EVIDENCE.md) |
| 16 | `rentiva_vehicle_details` | `vehicle-details` | No | No | No | No special casing required — verified |
| 17 | `rentiva_vehicle_rating_form` | `vehicle-rating-form` | No | No | No | No special casing required — verified (4 keys) |
| 18 | `rentiva_vehicles_list` | `vehicles-list` | No | No | No | [PR-01 Evidence](QA_V410_PR01_VEHICLES_LIST_EVIDENCE.md) |
| 19 | `rentiva_vehicles_grid` | `vehicles-grid` | No | No | No | [PR-02 Evidence](QA_V410_PR02_VEHICLES_GRID_EVIDENCE.md) |

---

## Kontrol Soruları Özeti

### ☑ Block slug ↔ Shortcode slug eşleşmesi
19/19 doğru eşleşme. `BlockRegistry::register_all_blocks()` her blok için doğru shortcode tag'ini kullanıyor.

### ☑ Explicit alias gereksinimi
- **3 shortcode** explicit alias gerektiriyor: `booking-form` (scope aliases), `vehicle-comparison` (scope aliases + transform), `testimonials` (global aliases).
- **16 shortcode** standart `camelCase → snake_case` dönüşümüyle çalışıyor.

### ☑ block.json default ↔ shortcode default uyumu
- Tüm PR-kapsamındaki shortcode'lar (PR-01 → PR-05 + Transfer Search) SOT ile senkronize edildi.
- Kalan 13 shortcode'da block.json default'ları shortcode SOT ile uyumlu. Kritik drift yok.
- **Not**: `booking-form` dinamik default'ları (`defaultDays`, `minDays`, `maxDays`) block.json'da `default` tanımsız bırakılarak SOT korunuyor.

### ☑ Ek transform gerekliliği
- Sadece `vehicle-comparison` → `showFeatures`: `true` → `'all'`, `false` → `'basic'` (scope-isolated).
- Başka hiçbir shortcode'da ek value transform yok.

---

## Alias Precedence Rule (Doğrulandı)

```
1. Explicit alias (BlockRegistry::$aliases)     → Highest priority
2. Block-specific override ($tag condition)      → Scope-isolated
3. camelCase → snake_case auto-convert           → Default fallback
4. Ignored keys ($ignored_keys)                  → Filtered out
```

---

## 🏁 Audit Result

| Kriter | Durum |
| :--- | :--- |
| 19/19 Block Slug Match | ✅ PASS |
| Special Alias (only where needed) | ✅ PASS (3/19) |
| Default Drift | ✅ PASS (0 drift) |
| Type Drift | ✅ PASS (1 isolated transform) |
| Evidence Coverage | ✅ PASS (6 PR + 13 verified) |

**Final Decision**: ✅ **19/19 Deterministic Surface — PASS**

> v4.10.0 teknik olarak tam parity release sayılır.

---

**Audit Date**: 2026-02-18
**Auditor**: Antigravity AI (Automated Cross-Reference + Manual Verification)
