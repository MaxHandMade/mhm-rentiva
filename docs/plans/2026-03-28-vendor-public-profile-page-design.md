# Phase 5: Vendor Public Profile Page — Design (DRAFT)

**Status:** Ertelendi — tasarım kararları alınmadı
**Date:** 2026-03-28
**Depends on:** Vehicle Lifecycle Phase 0-4, 6-7 (tamamlandı)

---

## Context

Vehicle Lifecycle Management implementasyonu sırasında Phase 5 (Vendor Public Profile Page) ertelendi.
Sebep: Sistemde public vendor sayfası altyapısı yok — sıfırdan tasarım gerekiyor.

## Mevcut Altyapı (Kullanılabilir)

| Bileşen | Dosya | Durum |
|---------|-------|-------|
| Vendor meta (bio, şehir, telefon) | `VendorOnboardingController.php` | Mevcut |
| Reliability score (0-100) | `ReliabilityScoreCalculator.php` | Mevcut |
| Araç rating (1-5 yıldız) | `RatingHelper.php` | Mevcut |
| Verified review sistemi | `VerifiedReviewHelper.php`, `ReviewEnforcer.php` | Mevcut |
| Araç kartları + vendor badge | `vehicle-card-base.php` | Mevcut |
| Vendor panel (private) | `templates/account/partials/vendor-*` | Mevcut |
| Account routing | `AccountController.php` | Mevcut |

## Tasarım Kararları (Bekliyor)

### 1. Birincil Amaç
- **A)** Güven oluşturma — Müşteri "Bu vendor güvenilir mi?" sorusuna cevap
- **B)** Vendor vitrin — Mini mağaza sayfası (tüm araçlar + bio)
- **C)** İkisinin birleşimi

### 2. URL Yapısı
- **A)** `/vendor/{slug}/` — Basit, evrensel
- **B)** `/arac-kiralama/{sehir}/{vendor}/` — SEO-friendly, lokasyon bazlı
- **C)** WP author archive override — `/{vendor-slug}/`

### 3. Gösterilecek Bilgiler
- Vendor adı, bio, şehir
- Reliability score (badge)
- Aktif araç sayısı + araç grid'i
- Ortalama rating + review sayısı
- Üyelik süresi
- Yanıt süresi (?)
- Tamamlanan kiralama sayısı (?)

### 4. Vendor Dizin Sayfası
- Tüm aktif vendor'ları listeleyen bir sayfa gerekli mi?
- Şehir/puan bazlı filtreleme?

### 5. SEO
- Schema.org markup: `LocalBusiness` / `AutoRental`
- Open Graph meta tags
- Canonical URL

### 6. Gizlilik
- Vendor hangi bilgileri gizleyebilmeli?
- Telefon/vergi bilgileri kesinlikle gizli
- Bio, şehir opsiyonel?

## Teknik Notlar

### Shortcode Yaklaşımı (Muhtemel)
```
[rentiva_vendor_profile] — Tek vendor profil sayfası
[rentiva_vendor_directory] — Vendor dizin/arama (opsiyonel)
```

### Reuse Edilecek Bileşenler
- `ReliabilityScoreCalculator::get()`, `get_label()`, `get_color()`
- `RatingHelper::get_rating()`, `get_star_html()`
- `vehicle-card.php` template (araç grid'i için)
- `AccountController` rewrite endpoint pattern'i

## Sonraki Adım

Brainstorming oturumu ile yukarıdaki kararlar alınacak, ardından bu belge güncellenerek implementasyon planı oluşturulacak.
