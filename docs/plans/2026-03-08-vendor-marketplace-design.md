# Vendor Marketplace — Design Document

**Date:** 2026-03-08
**Status:** Approved
**Scope:** Pro-only (`Mode::canUseVendorMarketplace()`)

---

## 1. Business Model

P2P (peer-to-peer) araç kiralama ve transfer platformu. Bireysel araç sahipleri araçlarını platforma ekler, müşteriler rezervasyon yapar, platform escrow ile ödemeyi tutar ve kiralama tamamlandıktan sonra vendor'a iletir.

---

## 2. Architecture Decision

**Seçilen yaklaşım:** Mevcut plugin altyapısı üzerine genişletme.

- Mevcut `CommissionBridge`, `Ledger`, `PayoutService` dokunulmadan korunur
- `DashboardContext` küçük eklemeyle vendor_application_pending durumu alır
- Yeni bileşenler mevcut pattern'lere uygun şekilde eklenir
- Hiçbir production dependency eklenmez

---

## 3. Onboarding Model

**İki kademeli sıkı onay:**

1. Vendor başvurusu → admin inceler → rol atanır
2. Her araç → admin inceler → yayına girer

---

## 4. Data Model

### 4.1 `mhm_vendor_application` (Custom Post Type)

| Meta Key | Tip | Zorunlu | Açıklama |
|----------|-----|---------|---------|
| `_vendor_phone` | string | Evet | Telefon numarası |
| `_vendor_city` | string | Evet | Şehir |
| `_vendor_iban` | string | Evet | IBAN (şifreli) |
| `_vendor_service_areas` | array | Evet | Hizmet bölgeleri (transfer için kritik) |
| `_vendor_profile_bio` | string | Hayır | Kısa tanıtım metni |
| `_vendor_tax_number` | string | Hayır | Vergi numarası (opsiyonel) |
| `_vendor_doc_id` | int | Evet | Kimlik belgesi attachment ID |
| `_vendor_doc_license` | int | Evet | Sürücü belgesi attachment ID |
| `_vendor_doc_address` | int | Evet | Adres belgesi attachment ID |
| `_vendor_doc_insurance` | int | Evet | Araç sigortası örneği attachment ID |
| `_vendor_status` | enum | — | pending / approved / rejected |
| `_vendor_rejection_note` | string | — | Admin red gerekçesi |
| `_vendor_approved_at` | datetime | — | Onay tarihi |
| `_vendor_approved_by` | int | — | Onaylayan admin user ID |

**Post statuses:** `pending` (inceleniyor), `publish` (onaylandı), `trash` (reddedildi)

### 4.2 `vehicle` post type — ek meta

| Meta Key | Tip | Açıklama |
|----------|-----|---------|
| `_vehicle_review_status` | enum | pending_review / approved / rejected / partial_edit |
| `_vehicle_rejection_note` | string | Red gerekçesi (vendor'a gösterilir) |

### 4.3 `rentiva_vendor` WordPress Rolü

```php
add_role('rentiva_vendor', __('Rentiva Vendor', 'mhm-rentiva'), [
    'read'              => true,
    'upload_files'      => true,
]);
```

Plugin.php'deki `register_roles()` metoduna eklenir. Mevcut `customer` rolü kaydı pattern'i izlenir.

### 4.4 User Meta (Onay anında senkronize edilir)

| Meta Key | Kaynak |
|----------|--------|
| `_rentiva_vendor_phone` | `_vendor_phone` |
| `_rentiva_vendor_city` | `_vendor_city` |
| `_rentiva_vendor_iban` | `_vendor_iban` |
| `_rentiva_vendor_service_areas` | `_vendor_service_areas` |
| `_rentiva_vendor_bio` | `_vendor_profile_bio` |
| `_rentiva_vendor_approved_at` | `_vendor_approved_at` |

---

## 5. New Components

| Bileşen | Tür | Sorumluluk |
|---------|-----|-----------|
| `VendorApplicationPostType` | PHP sınıfı | CPT kaydı, metabox, list table |
| `VendorApplicationManager` | PHP sınıfı | CRUD, durum geçişleri, unique check |
| `VendorOnboardingController` | PHP sınıfı | Rol atama, meta sync, email tetikleme |
| `VendorMediaIsolation` | PHP sınıfı | `ajax_query_attachments_args` hook'u |
| `VendorVehicleReviewManager` | PHP sınıfı | Araç onay/red, partial_edit mantığı |
| `[rentiva_vendor_apply]` | Shortcode | Frontend başvuru formu |
| `[rentiva_vehicle_submit]` | Shortcode | Vendor araç ekleme formu |
| Admin: Vendor Applications | WP admin sayfası | Başvuru listesi + onay/red UI |
| `Mode::canUseVendorMarketplace()` | Mode sınıfı | Pro-only feature flag |

---

## 6. User Flows

### Akış 1 — Vendor Başvurusu

```
1. Ziyaretçi [rentiva_vendor_apply] sayfasına gider
2. VendorApplicationManager::can_apply() kontrol eder:
   a. Kullanıcı zaten rentiva_vendor rolüne sahip mi? → "Zaten vendorsınız" göster
   b. Pending başvuru var mı? → "Başvurunuz inceleniyor" göster
3. Form doldurulur + belgeler yüklenir (WordPress media upload)
4. mhm_vendor_application oluşur (post_status: pending)
5. Vendor'a "Başvurunuz alındı" emaili gönderilir
6. Admin → WP Admin > Rentiva > Vendor Başvuruları
7. Admin belgelerle birlikte başvuruyu inceler
8a. Onay:
    - user'a rentiva_vendor rolü atanır
    - User meta'ya vendor verisi kopyalanır
    - "Hoş geldiniz, araç ekleyebilirsiniz" emaili gönderilir
8b. Red:
    - _vendor_rejection_note kaydedilir
    - Red gerekçesiyle email gönderilir
    - Kullanıcı yeniden başvurabilir
```

### Akış 2 — Araç Ekleme

```
1. Onaylı vendor /panel/ → "Araçlarım" → "Araç Ekle"
2. [rentiva_vehicle_submit] formu:
   - Marka, model, yıl, renk
   - Günlük fiyat
   - Açıklama
   - Fotoğraflar (izole edilmiş media library)
   - Şehir / hizmet bölgesi
   - Servis türü: kiralama | transfer | her ikisi
3. vehicle post oluşur (post_status: pending, _vehicle_review_status: pending_review)
4. Admin → WP Admin > Araçlar > "İnceleme Bekliyor"
5a. Onay: post_status → publish, vendor'a email
5b. Red: _vehicle_rejection_note eklenir, vendor panelde gerekçeyi görür

Düzenleme Mantığı (yayındaki araç):
- Kritik alanlar (fiyat, servis türü, şehir): _vehicle_review_status → pending_review
- Minor alanlar (başlık, açıklama, fotoğraf): Anında güncellenir
```

### Akış 3 — Ödeme & Payout (Escrow)

```
1. Müşteri rezervasyon yapar → WC Order (status: processing)
2. CommissionBridge hook (woocommerce_order_status_completed):
   a. booking_id order meta'dan alınır
   b. vehicle.post_author = vendor_id
   c. CommissionResolver::calculate(total, vendor_id)
   d. LedgerEntry(type: commission_credit, status: pending)
3. Admin order'ı "completed" yapar → LedgerEntry: pending → cleared
4. Vendor /panel/ → Ledger sekmesinde bakiyesini görür
5. "Ödeme Talebi" gönderir → mhm_payout (status: pending)
6. Admin onaylar → LedgerEntry(type: payout_debit) → IBAN transfer

İptal/İade Senaryoları:
A. Kiralama tamamlanmadan iptal:
   LedgerEntry status: pending → cancelled (cleared olmaz, payout hakkı doğmaz)

B. Tamamlanmış kiralamada iade:
   PayoutService::create_refund_entry($vendor_id, $booking_id, $amount)
   Yeni LedgerEntry(type: refund, amount: -X)
   Vendor'ın sonraki payoutundan düşülür
```

---

## 7. Security & Integrity

### Media Isolation

```php
// VendorMediaIsolation sınıfı
add_filter('ajax_query_attachments_args', function($query) {
    if (current_user_can('rentiva_vendor') && !current_user_can('manage_options')) {
        $query['author'] = get_current_user_id();
    }
    return $query;
});
```

### Vehicle Ownership Enforcement

Vendor sadece kendi araçlarını düzenleyebilir:
```php
// vehicle post type map_meta_cap ile
// post_author === current_user_id() kontrolü
```

### IBAN Storage

`_vendor_iban` değeri `openssl_encrypt()` ile şifreli saklanır, admin görüntüleme sayfasında maskelenir (son 4 hane görünür).

---

## 8. Commission Model

- **Tip:** Sabit global oran
- **Kaynak:** `CommissionPolicy` tablosundaki aktif policy (`vendor_id IS NULL`)
- **Hesaplama:** Toplam WC order tutarı (ek hizmetler dahil) üzerinden
- **Admin UI:** Yeni settings sekmesi → "Komisyon Oranı" input alanı
- **Backend:** Mevcut `CommissionResolver` değişmez

---

## 9. Pro Gating

```php
// Mode sınıfına eklenecek
public static function canUseVendorMarketplace(): bool {
    return self::isPro();
}
```

**Lite sürümde:**
- `[rentiva_vendor_apply]` shortcode → "Bu özellik Pro sürümde mevcuttur" mesajı
- `[rentiva_vehicle_submit]` shortcode → aynı
- Admin menüsünde "Vendor Başvuruları" → gizli
- /panel/ sayfası çalışmaya devam eder (mevcut müşteriler etkilenmez)

---

## 10. Email Notifications

| Tetikleyici | Alıcı | Konu |
|-------------|-------|------|
| Başvuru alındı | Vendor | "Başvurunuz alındı, inceleniyor" |
| Başvuru onaylandı | Vendor | "Hoş geldiniz! Araç ekleyebilirsiniz" |
| Başvuru reddedildi | Vendor | "Başvurunuz hakkında bilgi" + gerekçe |
| Araç onaylandı | Vendor | "Aracınız yayında!" |
| Araç reddedildi | Vendor | "Aracınız hakkında" + gerekçe |
| Yeni başvuru | Admin | "Yeni vendor başvurusu inceleme bekliyor" |
| Yeni araç | Admin | "Yeni araç inceleme bekliyor" |
| Payout onaylandı | Vendor | "Ödemeniz işleme alındı" |

---

## 11. Out of Scope (MVP Sonrası)

- Vendor puanlama / yorum sistemi
- Otomatik ledger clearing (iade süresi dolunca)
- Kademeli komisyon oranları
- Vendor self-service komisyon görüntüleme
- KYC entegrasyonu (Stripe Identity vb.)
- Vendor rozet sistemi ("Doğrulanmış", "Süper Vendor")

---

## 12. Affected Files (Mevcut)

Değiştirilecek:
- `src/Plugin.php` — `rentiva_vendor` rol kaydı + `Mode::canUseVendorMarketplace()`
- `src/Core/Dashboard/DashboardContext.php` — `vendor_application_pending` durumu
- `src/Core/Financial/PayoutService.php` — `create_refund_entry()` methodu
- `src/Admin/Core/Mode.php` — `canUseVendorMarketplace()` methodu

Dokunulmayacak:
- `src/Core/Financial/Ledger.php`
- `src/Core/Financial/CommissionBridge.php`
- `src/Core/Financial/CommissionResolver.php`
- `src/Admin/PostTypes/Payouts/PostType.php`
