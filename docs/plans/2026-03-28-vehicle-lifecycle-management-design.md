# Vehicle Lifecycle Management — Design Document

**Date:** 2026-03-28
**Status:** Draft — Pending Approval
**Scope:** Pro-only (`Mode::canUseVendorMarketplace()`)
**Model:** Hybrid (Option A) — Brainstorming session 2026-03-28

---

## 1. Problem Statement

Vendor'lar araçlarını platforma ekledikten sonra **geri çekemiyor, duraklatamıyor veya yönetemiyor.** Bu durum:

- **Vendor memnuniyetsizliği:** Araç bakımdayken, tatildeyken veya satışa çıktığında listeyi kapatamıyor
- **Müşteri deneyimi bozulması:** Kullanılamayan araçlar arama sonuçlarında görünüyor
- **Kötü kullanım riski:** Vendor sürekli açıp kapatarak sistemi gaming'e açık bırakıyor
- **Listeleme kalitesi düşüklüğü:** Aktif olmayan ilanlar zamanla kirliliğe yol açıyor

Ayrıca **mevcut altyapıda kritik buglar** var:
1. `_mhm_vehicle_status = 'maintenance'` arama sorgularında kontrol edilmiyor — bakımdaki araçlar hâlâ görünüyor
2. `VendorOnboardingController::suspend()` vendor'ın araçlarını unpublish etmiyor — askıya alınan vendor'ın araçları canlı kalıyor

---

## 2. Business Goals

| # | Goal | Metric |
|---|------|--------|
| 1 | Vendor'lara araç yönetim kontrolü ver | Vendor self-service pause/withdraw oranı |
| 2 | Kötü kullanımı engelle (gaming prevention) | Ortalama listing süre kararlılığı |
| 3 | Listeleme kalitesini artır | Aktif listing / toplam listing oranı |
| 4 | Platform güvenilirliğini göster | Reliability score hesaplama ve gösterim |
| 5 | Gelecekte SMS/bildirim altyapısına hazır ol | Hook-based notification architecture |

---

## 3. Decisions

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| 1 | Vehicle state model | Active / Paused / Withdrawn / Expired | 4 durum + transition kuralları. Basit ama etkili |
| 2 | Listing süresi | 90 gün + yenileme | Turo 30 gün, Airbnb süresiz. 90 gün orta yol — kalite korur, vendor'ı yormaz |
| 3 | Ceza sistemi | Progressive (1., 2., 3.+ farklı) | Haberlenmeden ceza vermek adil değil. Kademeli yaklaşım |
| 4 | Geri çekme cooldown | 7 gün | Gaming önlemi — hemen tekrar listelemeyi engeller |
| 5 | Güvenilirlik skoru | Hesaplanır, vendor dashboard'da gösterilir | Vendor profil sayfası henüz yok — ilk gösterim dashboard'da |
| 6 | Vendor profil sayfası | Phase 5'te (gelecek) | Öncelik lifecycle, profil daha sonra |
| 7 | SMS bildirim | Gelecek planlarında, şimdi e-mail | SMS altyapısı yok, hook'lar hazır olacak |
| 8 | İptal edilen tarihleri blokla | Evet, anti-gaming | Vendor iptal ettikten sonra aynı tarihleri hemen açmasın |
| 9 | Duraklatma limiti | Ayda max 2 kez, max 30 gün | Sürekli duraklama ile gaming'i engelle |

---

## 4. Vehicle State Machine

### 4.1 State Diagram

```
                         ┌──────────────┐
                  ┌─────>│   EXPIRED    │
                  │      └──────────────┘
                  │             │
              (90 gün)    (renew within 7d)
                  │             │
                  │             v
┌──────────┐    ┌──────────────┐    ┌──────────────┐
│ PENDING  │───>│    ACTIVE    │<──>│   PAUSED     │
│ (review) │    │              │    │              │
└──────────┘    └──────────────┘    └──────────────┘
                       │                    │
                  (withdraw)          (withdraw)
                       │                    │
                       v                    v
                ┌──────────────┐
                │  WITHDRAWN   │
                │ (7d cooldown)│
                └──────────────┘
                       │
                  (relist after 7d)
                       │
                       v
                ┌──────────────┐
                │   PENDING    │ (re-review gerekli)
                └──────────────┘
```

### 4.2 States

| State | `post_status` | `_mhm_vehicle_lifecycle_status` | Aramada Görünür | Açıklama |
|-------|--------------|-------------------------------|----------------|----------|
| **Pending** | `pending` | `pending_review` | Hayır | Yeni veya tekrar inceleme bekliyor |
| **Active** | `publish` | `active` | Evet | Canlı, aranabilir, rezerve edilebilir |
| **Paused** | `publish` | `paused` | Hayır | Vendor tarafından geçici olarak durdurulmuş |
| **Expired** | `publish` | `expired` | Hayır | 90 gün dolmuş, yenileme bekliyor |
| **Withdrawn** | `draft` | `withdrawn` | Hayır | Vendor kalıcı olarak geri çekmiş |

> **Neden `post_status` = `publish` Paused/Expired için?**
> WordPress'te `draft` geri alma araçlar admin'de farklı davranır. `publish` tutup meta ile filtrelemek daha tutarlı.
> **Withdrawn** ise `draft`'a alınır çünkü kalıcı çıkış — admin tarafında da "taslak" olarak görünmeli.

### 4.3 Transition Rules

| From | To | Trigger | Conditions | Side Effects |
|------|----|---------|------------|-------------|
| pending → active | Admin onay | `_vehicle_review_status = 'approved'` | `_mhm_vehicle_lifecycle_status = 'active'`, listing timer başlar |
| active → paused | Vendor buton | Aktif booking yok (o araç için) | `_mhm_vehicle_lifecycle_status = 'paused'`, `_mhm_vehicle_paused_at` kaydedilir |
| paused → active | Vendor buton | Pause süresi < 30 gün, aylık limit (2) aşılmamış | `_mhm_vehicle_lifecycle_status = 'active'` |
| active → withdrawn | Vendor buton | Aktif/onaylanmış booking yok | Ceza hesapla, `post_status = 'draft'`, cooldown başlar |
| paused → withdrawn | Vendor buton | — | Ceza hesapla, `post_status = 'draft'`, cooldown başlar |
| active → expired | Cron (90 gün) | `listing_expires_at < now()` | E-mail gönder, aramadan kaldır |
| expired → active | Vendor yenile | 7 gün içinde yenileme | Yeni 90 gün dönem, `listing_expires_at` güncellenir |
| expired → withdrawn | Vendor veya cron | 7 gün geçti yenilenmedi | `post_status = 'draft'` |
| withdrawn → pending | Vendor tekrar listeleme | Cooldown (7 gün) dolmuş | Yeni inceleme süreci, `post_status = 'pending'` |

### 4.4 Search Query Entegrasyonu

**Mevcut BUG:** Tüm arama shortcode'ları yalnızca `'post_status' => 'publish'` kontrol ediyor. `_mhm_vehicle_status` veya yeni lifecycle meta'yı kontrol etmiyor.

**Çözüm — Merkezi `meta_query` enjeksiyonu:**

```php
// QueryHelper veya yeni VehicleVisibilityFilter sınıfı:
'meta_query' => array(
    array(
        'key'     => '_mhm_vehicle_lifecycle_status',
        'value'   => 'active',
        'compare' => '=',
    ),
),
```

**Etkilenen dosyalar (Phase 0'da düzeltilecek):**
- `SearchResults.php` (satır 357)
- `VehiclesGrid.php` (satır 161)
- `VehiclesList.php` (satır 210)
- `FeaturedVehicles.php` (satır 131)
- `VehicleDetails.php` (satır 177)
- `VehicleComparison.php` (satır 491, 644, 673)
- `BookingForm.php` (satır 524, 585)
- `AbstractShortcode.php` (satır 689)

---

## 5. Listing Duration (90 Gün)

### 5.1 Meta Keys

| Meta Key | Type | Description |
|----------|------|-------------|
| `_mhm_vehicle_listing_started_at` | datetime | İlk publish tarihi (UTC) |
| `_mhm_vehicle_listing_expires_at` | datetime | 90 gün sonrası (UTC) |
| `_mhm_vehicle_listing_renewed_at` | datetime | Son yenileme tarihi (UTC) |
| `_mhm_vehicle_listing_renewal_count` | int | Toplam yenileme sayısı |

### 5.2 Cron Job: `ListingExpiryJob`

```
Hook: mhm_rentiva_check_listing_expiry
Interval: twice_daily (12 saatte bir)
Pattern: AutoCancel.php ile aynı (static register/maybe_schedule/run)
```

**Logic:**
1. `WP_Query`: `post_status = 'publish'` AND `_mhm_vehicle_lifecycle_status = 'active'` AND `_mhm_vehicle_listing_expires_at < current_time('mysql', true)`
2. Her eşleşen araç için:
   - `_mhm_vehicle_lifecycle_status = 'expired'`
   - `do_action('mhm_rentiva_vehicle_expired', $vehicle_id, $vendor_id)`
3. E-mail: "Araç listelemeniz süresi doldu. 7 gün içinde yenileyin."

### 5.3 Expiry Warning E-mails

| Gün | E-mail |
|-----|--------|
| 80. gün (10 gün kala) | "Araç listelemeniz 10 gün içinde sona erecek" |
| 87. gün (3 gün kala) | "Son 3 gün! Araç listelemenizi yenileyin" |
| 90. gün (expired) | "Araç listelemeniz sona erdi — 7 gün içinde yenileyin" |
| 97. gün (7 gün sonra) | Auto-withdraw (cron) |

**Cron: `ListingExpiryWarningJob`**
```
Hook: mhm_rentiva_listing_expiry_warnings
Interval: daily
```

### 5.4 Renewal Flow

Vendor dashboard'da "Yenile" butonu:
1. Yeni `listing_expires_at` = `current_time + 90 days`
2. `listing_renewed_at` = now
3. `renewal_count` += 1
4. `lifecycle_status` = `active` (expired ise)
5. `do_action('mhm_rentiva_vehicle_renewed', $vehicle_id)`

---

## 6. Progressive Cancellation Penalty System

### 6.1 Penalty Seviyeleri

Vendor'ın **tüm araçları üzerindeki** 6 aylık penceredeki toplam geri çekme sayısına göre:

| Geri Çekme # | Ceza | Açıklama |
|-------------|------|----------|
| 1. | Uyarı (₺0) | İlk kez tolerans, e-mail uyarı |
| 2. | Vendor bakiyesinden %5 kesinti (min ₺50, max ₺500) | İkinci kez mali yaptırım başlar |
| 3.+ | Vendor bakiyesinden %10 kesinti (min ₺100, max ₺1.000) + güvenilirlik skoru düşüşü | Tekrarlayan davranış ciddi yaptırım |

> **6 aylık kayan pencere:** Son 180 gün içindeki geri çekme sayısı. Eski geri çekmeler otomatik olarak "affedilir."

### 6.2 Ceza Hesaplama

```php
$withdrawal_count = count of withdrawals in last 180 days for this vendor;

if ($withdrawal_count === 1) {
    // Warning only — no financial penalty
    $penalty = 0.0;
} elseif ($withdrawal_count === 2) {
    $balance = vendor's available balance;
    $penalty = max(50, min(500, $balance * 0.05));
} else {
    $balance = vendor's available balance;
    $penalty = max(100, min(1000, $balance * 0.10));
}
```

### 6.3 Ledger Entegrasyonu

Mevcut `mhm_rentiva_ledger` tablosu penalty'yi destekleyebilir:

| Column | Value |
|--------|-------|
| `type` | `'withdrawal_penalty'` |
| `amount` | Negatif değer (kesinti) |
| `vendor_id` | Cezalanan vendor |
| `booking_id` | `NULL` (booking'e bağlı değil) |
| `order_id` | `NULL` |
| `context` | `'vehicle_withdrawal'` |
| `status` | `'cleared'` |

> **Mevcut type'lar:** `commission_credit`, `commission_refund`. Yeni `withdrawal_penalty` type eklenir.

### 6.4 Date Blocking (Anti-Gaming)

Vendor araç geri çektiğinde, **o araçtaki mevcut onaylanmış rezervasyonların tarihleri** bloklanır:

1. İptal edilen bookinglerin tarih aralıkları kaydedilir: `_mhm_vehicle_blocked_dates` (JSON array)
2. Vendor aynı aracı tekrar listelediğinde (pending → active sonrası), bloklanmış tarihler **30 gün boyunca** korunur
3. Bu tarihler availability calendar'da "dolu" olarak gösterilir
4. 30 gün sonra bloklanmış tarihler otomatik temizlenir

```json
{
    "blocked_ranges": [
        {
            "start": "2026-04-15",
            "end": "2026-04-20",
            "reason": "withdrawal_cancellation",
            "blocked_at": "2026-03-28",
            "expires_at": "2026-04-27"
        }
    ]
}
```

---

## 7. Reliability Score (Güvenilirlik Skoru)

### 7.1 Formula

```
Score = (Base × CompletionBonus × CancellationPenalty × WithdrawalPenalty × ResponseBonus) × 100

Clamp: min 0, max 100
Display: Tam sayı + 5'li yıldız karşılığı (0-20: 1★, 21-40: 2★, 41-60: 3★, 61-80: 4★, 81-100: 5★)
```

| Factor | Calculation | Range |
|--------|------------|-------|
| Base | 0.70 (başlangıç) | Sabit |
| CompletionBonus | `1 + (completed_bookings / 50) × 0.30` | 1.00 – 1.30 |
| CancellationPenalty | `1 - (vendor_cancellations / total_bookings) × 0.50` | 0.50 – 1.00 |
| WithdrawalPenalty | `1 - (withdrawals_6mo × 0.05)` | 0.75 – 1.00 |
| ResponseBonus | `1 + (avg_response_hours < 24 ? 0.10 : 0)` | 1.00 – 1.10 |

**Örnek:** 30 tamamlanmış, 2 iptal, 1 geri çekme, hızlı yanıt:
```
= (0.70 × 1.18 × 0.967 × 0.95 × 1.10) × 100
= (0.70 × 1.18 × 0.967 × 0.95 × 1.10) × 100
= 83.4 → 83 puan → 5★
```

### 7.2 Storage

| Meta Key | Type | On |
|----------|------|-----|
| `_rentiva_vendor_reliability_score` | int (0-100) | user_meta |
| `_rentiva_vendor_reliability_updated_at` | datetime | user_meta |

**Hesaplama zamanlaması:**
- Cron: Günlük (`mhm_rentiva_calculate_reliability_scores`)
- Event-triggered: Booking completed, booking cancelled, vehicle withdrawn

### 7.3 Display

**Şu an (Phase 4):** Vendor kendi dashboard'unda görür (overview tab KPI kartı olarak).

**Gelecek (Phase 5 — Vendor Profil Sayfası):**
- `/vendor/{slug}/` — Herkese açık vendor profil sayfası
- Güvenilirlik skoru + yıldız, tamamlanmış kiralama sayısı, üyelik süresi
- Bu Phase ayrı bir design document gerektirir

**Arama sonuçlarında (Phase 5+):**
- Araç kartlarında vendor güvenilirlik badge'i (★ sayısı)

---

## 8. Vendor Dashboard UI Değişiklikleri

### 8.1 Listings Tab — Araç Kartı Aksiyonları

Mevcut: Araç kartında sadece "Düzenle" butonu var.

**Yeni butonlar:**

```html
<!-- Active araç -->
<button class="mhm-vehicle-action" data-action="pause">⏸ Duraklat</button>
<button class="mhm-vehicle-action mhm-vehicle-action--danger" data-action="withdraw">✕ Geri Çek</button>

<!-- Paused araç -->
<button class="mhm-vehicle-action" data-action="resume">▶ Devam Et</button>
<button class="mhm-vehicle-action mhm-vehicle-action--danger" data-action="withdraw">✕ Geri Çek</button>

<!-- Expired araç -->
<button class="mhm-vehicle-action mhm-vehicle-action--primary" data-action="renew">🔄 Yenile</button>
<button class="mhm-vehicle-action mhm-vehicle-action--danger" data-action="withdraw">✕ Geri Çek</button>

<!-- Withdrawn araç (cooldown dolmuşsa) -->
<button class="mhm-vehicle-action" data-action="relist">📋 Tekrar Listele</button>
```

### 8.2 Listings Tab — Durum Badge'leri

| Status | Badge | Color |
|--------|-------|-------|
| Active | `Aktif` | `#28a745` (green) |
| Paused | `Duraklatıldı` | `#ffc107` (amber) |
| Expired | `Süresi Doldu` | `#fd7e14` (orange) |
| Withdrawn | `Geri Çekildi` | `#dc3545` (red) |
| Pending | `İnceleniyor` | `#6c757d` (gray) |

### 8.3 Overview Tab — Reliability Score KPI

```html
<div class="mhm-rentiva-dashboard__kpi">
    <span class="mhm-rentiva-dashboard__kpi-label">Güvenilirlik Skoru</span>
    <span class="mhm-rentiva-dashboard__kpi-value" data-count="83">0</span>
    <span class="mhm-rentiva-dashboard__kpi-unit">/100</span>
    <div class="mhm-rentiva-dashboard__kpi-stars">★★★★★</div>
</div>
```

### 8.4 Confirmation Dialogs

**Pause:** "Bu aracı duraklatmak istediğinize emin misiniz? Araç arama sonuçlarından kaldırılacaktır. (Bu ay kalan duraklatma hakkı: X)"

**Withdraw — İlk kez:** "Bu aracı kalıcı olarak geri çekmek istediğinize emin misiniz? 7 günlük bekleme süresi sonrası tekrar listeleyebilirsiniz. (Bu ilk geri çekmenizdir, ceza uygulanmayacaktır.)"

**Withdraw — 2. kez:** "⚠️ Bu, son 6 ayda 2. geri çekmeniz olacaktır. Bakiyenizden %5 (₺XX) ceza kesilecektir. Devam etmek istiyor musunuz?"

---

## 9. Notification E-mails (Yeni Şablonlar)

### 9.1 New Templates

| Template Key | Subject | Trigger |
|-------------|---------|---------|
| `vehicle_paused` | "Aracınız duraklatıldı — {{site.name}}" | Vendor pause |
| `vehicle_resumed` | "Aracınız tekrar aktif — {{site.name}}" | Vendor resume |
| `vehicle_withdrawn` | "Aracınız geri çekildi — {{site.name}}" | Vendor withdraw |
| `vehicle_withdrawn_penalty` | "Araç geri çekme — ceza uygulandı — {{site.name}}" | Withdraw with penalty |
| `vehicle_expired` | "Araç listelemeniz süresi doldu — {{site.name}}" | Cron expiry |
| `vehicle_expiry_warning` | "Araç listelemeniz X gün içinde sona erecek — {{site.name}}" | 10-day / 3-day warning |
| `vehicle_renewed` | "Araç listelemeniz yenilendi — {{site.name}}" | Vendor renew |
| `vehicle_relist_available` | "Aracınızı tekrar listeleyebilirsiniz — {{site.name}}" | Cooldown completed |
| `reliability_score_drop` | "Güvenilirlik skorunuz düştü — {{site.name}}" | Score dropped >10 pts |

### 9.2 Future SMS Hooks

Her e-mail gönderiminden hemen önce bir hook ateşlenir, gelecekte SMS entegrasyonu için:

```php
do_action('mhm_rentiva_notify_vendor', $vendor_id, $template_key, $context);
// Default handler: email. Future handler: SMS gateway.
```

---

## 10. Data Model

### 10.1 New Vehicle Post Meta

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_mhm_vehicle_lifecycle_status` | string | `'active'` | active/paused/expired/withdrawn |
| `_mhm_vehicle_listing_started_at` | datetime | — | İlk aktivasyon tarihi (UTC) |
| `_mhm_vehicle_listing_expires_at` | datetime | — | 90 gün sonrası (UTC) |
| `_mhm_vehicle_listing_renewed_at` | datetime | — | Son yenileme tarihi |
| `_mhm_vehicle_listing_renewal_count` | int | 0 | Toplam yenileme sayısı |
| `_mhm_vehicle_paused_at` | datetime | — | Son duraklama tarihi |
| `_mhm_vehicle_pause_count_month` | string | `'YYYY-MM:0'` | Ay:sayı formatı (aylık reset) |
| `_mhm_vehicle_withdrawn_at` | datetime | — | Geri çekme tarihi |
| `_mhm_vehicle_cooldown_ends_at` | datetime | — | 7 gün cooldown bitiş tarihi |
| `_mhm_vehicle_blocked_dates` | JSON | `[]` | Anti-gaming bloklanmış tarihler |

### 10.2 New Vendor User Meta

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_rentiva_vendor_reliability_score` | int | 70 | 0-100 güvenilirlik skoru |
| `_rentiva_vendor_reliability_updated_at` | datetime | — | Son hesaplama tarihi |
| `_rentiva_vendor_withdrawal_count_6mo` | int | 0 | Son 6 ayda geri çekme sayısı (cache, cron ile güncellenir) |

### 10.3 Ledger Type Extension

Mevcut `type` VARCHAR(30) alanına yeni değer:

| Type Value | Context | Usage |
|------------|---------|-------|
| `withdrawal_penalty` | `vehicle_withdrawal` | Geri çekme cezası (negatif amount) |

### 10.4 Migration

**Yeni tablo gerekmez.** Tüm veriler post_meta, user_meta ve mevcut ledger tablosunda tutulur.

**Mevcut araçlar için migration (one-time):**
- Tüm `post_status = 'publish'` araçlar → `_mhm_vehicle_lifecycle_status = 'active'`
- `_mhm_vehicle_listing_started_at` = `post_date` (veya mevcut publish tarihi)
- `_mhm_vehicle_listing_expires_at` = `listing_started_at + 90 days`

---

## 11. Admin UI Değişiklikleri

### 11.1 Vehicle List — Lifecycle Status Column

Admin araç listesinde yeni "Lifecycle" kolonu:
- Active / Paused / Expired / Withdrawn badge'leri
- Filtreleme: Tüm lifecycle durumlarına göre filtreleme dropdown'u

### 11.2 Vehicle Edit — Lifecycle Meta Box

Admin araç düzenleme ekranında yeni meta box:
- Mevcut lifecycle status (read-only display)
- Listing started at / expires at tarihleri
- Admin override: Force Active / Force Expired / Force Withdrawn
- Pause/Withdrawal history log

### 11.3 Vendor List — Reliability Score Column

Admin vendor listesinde güvenilirlik skoru kolonu:
- Skor + yıldız gösterimi
- Sıralama/filtreleme desteği

---

## 12. Phased Implementation

### Phase 0: Critical Bug Fixes (Öncelik: ACİL)

**Bağımsız, lifecycle öncesi yapılmalı.**

1. **Search query fix:** Tüm arama shortcode'larında `_mhm_vehicle_lifecycle_status = 'active'` (veya mevcut `_mhm_vehicle_status`) meta_query ekle
2. **Vendor suspension fix:** `VendorOnboardingController::suspend()` içinde vendor'ın tüm araçlarını `draft`'a al
3. Mevcut `_mhm_vehicle_status = 'maintenance'` araçları aramadan çıkar

**Dosyalar:**
- `SearchResults.php`, `VehiclesGrid.php`, `VehiclesList.php`, `FeaturedVehicles.php`, `VehicleDetails.php`, `VehicleComparison.php`, `BookingForm.php`, `AbstractShortcode.php`
- `VendorOnboardingController.php`

### Phase 1: Vehicle State Machine (Core)

1. `VehicleLifecycleManager` sınıfı — state transitions, validation, side effects
2. `_mhm_vehicle_lifecycle_status` meta key migration (mevcut araçlara `active` set)
3. Search query entegrasyonu (`lifecycle_status = 'active'` filtresi)
4. `VehicleLifecycleStatus` enum/constants sınıfı
5. PHPUnit tests

**Dosyalar:**
- Yeni: `src/Admin/Vehicle/VehicleLifecycleManager.php`
- Yeni: `src/Admin/Vehicle/VehicleLifecycleStatus.php`
- Değişiklik: Tüm search shortcode'ları (Phase 0 ile birlikte)
- Yeni: `tests/Integration/Vehicle/VehicleLifecycleManagerTest.php`

### Phase 2: 90-Day Listing Duration

1. `ListingExpiryJob` cron — twice_daily, expired araçları tespit et
2. `ListingExpiryWarningJob` cron — daily, 10/3 gün uyarıları
3. Listing başlangıç/bitiş tarihlerini VehicleSubmit onay sonrası set et
4. Renewal flow (vendor dashboard butonu + AJAX)
5. PHPUnit tests

**Dosyalar:**
- Yeni: `src/Admin/PostTypes/Maintenance/ListingExpiryJob.php`
- Yeni: `src/Admin/PostTypes/Maintenance/ListingExpiryWarningJob.php`
- Değişiklik: `VendorVehicleReviewManager.php` (onay → listing timer başlatma)
- Değişiklik: `UserDashboard.php` veya listings template (renewal buton)
- Yeni: `tests/Integration/Vehicle/ListingExpiryTest.php`

### Phase 3: Vendor Self-Service (Pause/Resume/Withdraw)

1. Vendor dashboard listing kartlarına aksiyon butonları
2. AJAX endpoint'ler: `mhm_vehicle_pause`, `mhm_vehicle_resume`, `mhm_vehicle_withdraw`
3. Withdrawal penalty hesaplama + ledger entegrasyonu
4. Cooldown enforcement (7 gün)
5. Date blocking (anti-gaming)
6. Relist flow (cooldown sonrası, pending'e geri dön)
7. Confirmation dialogs (JS)
8. PHPUnit tests

**Dosyalar:**
- Değişiklik: `VehicleLifecycleManager.php` (pause/resume/withdraw logic)
- Yeni: `src/Admin/Vehicle/WithdrawalPenaltyCalculator.php`
- Değişiklik: Vendor listings template
- Değişiklik: `user-dashboard.js` (AJAX + dialogs)
- Yeni: `tests/Integration/Vehicle/VehicleWithdrawalPenaltyTest.php`

### Phase 4: Reliability Score

1. `ReliabilityScoreCalculator` sınıfı — formula implementation
2. `ReliabilityScoreJob` cron — daily calculation
3. Event-driven recalculation hooks
4. Vendor dashboard KPI kartı
5. PHPUnit tests

**Dosyalar:**
- Yeni: `src/Admin/Vendor/ReliabilityScoreCalculator.php`
- Yeni: `src/Admin/PostTypes/Maintenance/ReliabilityScoreJob.php`
- Değişiklik: `UserDashboard.php` (KPI kartı)
- Yeni: `tests/Unit/Vendor/ReliabilityScoreCalculatorTest.php`

### Phase 5: Vendor Public Profile Page (Gelecek)

> **Bu phase ayrı bir design document gerektirir.**
> Tahmini kapsam: Yeni shortcode `[rentiva_vendor_profile]`, `/vendor/{slug}/` rewrite, araç kartlarında vendor badge, SEO (schema.org Person/Organization).

### Phase 6: Notification E-mails

1. 9 yeni e-mail template (HTML)
2. `VendorNotifications.php` genişletme (yeni hook listener'lar)
3. Future SMS hook: `mhm_rentiva_notify_vendor`

**Dosyalar:**
- Değişiklik: `VendorNotifications.php`
- Yeni: `templates/emails/` altında 9 yeni template
- Yeni: `src/Admin/Notifications/VendorNotificationBridge.php` (SMS hook için)

### Phase 7: Admin UI

1. Vehicle list — lifecycle kolonu + filtreleme
2. Vehicle edit — lifecycle meta box
3. Vendor list — reliability score kolonu
4. Admin override aksiyonları (force state change)

**Dosyalar:**
- Değişiklik: `VehicleMeta.php` (meta box)
- Değişiklik: Vehicle CPT columns
- Değişiklik: `AdminVendorApplicationsPage.php` (reliability kolonu)

---

## 13. Mevcut Altyapı Uyumluluğu

### 13.1 Status Layer Mapping

Mevcut 3 katmanlı status sistemiyle uyum:

| Layer | Existing | New Addition | Notes |
|-------|----------|-------------|-------|
| `post_status` | publish/pending/draft | — | Withdrawn = draft olur |
| `_vehicle_review_status` | pending_review/approved/rejected | — | Dokunulmaz |
| `_mhm_vehicle_status` | active/maintenance | **DEPRECATED** | `_mhm_vehicle_lifecycle_status` ile değiştirilir |
| `_mhm_vehicle_lifecycle_status` | — | active/paused/expired/withdrawn | **YENİ — tek kaynak** |

> **Migration strategy:** `_mhm_vehicle_status = 'active'` → `_mhm_vehicle_lifecycle_status = 'active'`, `_mhm_vehicle_status = 'maintenance'` → `_mhm_vehicle_lifecycle_status = 'paused'`. Eski meta key korunur (backward compat) ama yeni kod yalnızca `_mhm_vehicle_lifecycle_status` okur.

### 13.2 Booking Integration

- Pause/Withdraw **mevcut onaylanmış booking'leri iptal etmez** — booking kendi FSM'ine devam eder
- Pause: Yeni booking alınamaz, mevcut booking'ler tamamlanır
- Withdraw: Mevcut onaylanmış booking'ler varsa **withdraw engellenebilir** veya admin onayı gerekir (tercih: engellenir)

### 13.3 Cron Compatibility

Yeni cron'lar mevcut pattern'i takip eder:
- `AutoCancel` pattern: `register()` → `maybe_schedule()` → `run()`
- Custom schedule'lar: `cron_schedules` filter
- Mevcut schedule'lar: `mhm_rentiva_5min`, `mhm_rentiva_15min` — yeni `twice_daily` ve `daily` WP built-in'leri kullanılır

### 13.4 Ledger Compatibility

- `withdrawal_penalty` yeni type olarak eklenir, mevcut `commission_credit` / `commission_refund` akışı dokunulmaz
- `LedgerEntry` constructor'ı nullable `booking_id` destekliyor → penalty entry'ler `booking_id = NULL` olabilir
- `AnalyticsService` mevcut filtreleri: `type IN ('commission_credit', 'commission_refund')` — penalty tipi analytics'i etkilemez

---

## 14. Not in Scope

- **Vendor profil sayfası** — Phase 5, ayrı design document
- **SMS bildirimler** — Hook hazır olacak, gateway entegrasyonu ileride
- **Otomatik fiyat düşürme** (listing süresi dolmaya yakın) — gelecek feature
- **Auction/bidding modeli** — kapsam dışı
- **Multi-vehicle batch operations** — tek tek aksiyon yeterli şimdilik
- **Vendor fatura kesme** — ayrı feature, not alındı (bkz. `memory/project_vendor_invoice.md`)
- **Listing boost/premium listing** — monetizasyon planı ayrı
- **Otomatik re-approval** (yenileme sonrası) — yenileme review gerektirmez, withdraw → relist ise review gerektirir

---

## 15. Risk Analysis

| Risk | Impact | Mitigation |
|------|--------|------------|
| Mevcut araçlara migration yanlış çalışır | Tüm araçlar aramadan kaybolur | Migration'ı dry-run ile test et, rollback script hazırla |
| Cron job miss (WP Cron güvenilmez) | Araçlar expire olmaz | Action Scheduler fallback düşün, CronMonitorPage ile izle |
| Penalty hesaplama hatası | Vendor güveni kaybolur | Unit test %100 coverage, admin override imkanı |
| Vendor'lar yeni sistemi anlamaz | Destek talepleri artar | Onboarding e-mail + dashboard açıklayıcı tooltip'ler |
| Search query performance düşüşü | Meta query yavaşlama | `_mhm_vehicle_lifecycle_status` için INDEX ekle (post_meta performans notu: WP meta tablosu zaten indexed) |

---

## 16. Test Plan

### Unit Tests
- `VehicleLifecycleManager`: Tüm transition'lar (valid + invalid)
- `WithdrawalPenaltyCalculator`: Her seviye + edge case'ler
- `ReliabilityScoreCalculator`: Formula doğruluğu + boundary values

### Integration Tests (WP_UnitTestCase)
- Vehicle oluştur → approve → lifecycle = active, listing timer set
- Pause → aramada görünmez
- Resume → aramada tekrar görünür
- Withdraw → post_status = draft, cooldown aktif
- Withdraw with penalty → ledger entry oluşur
- Expired → renewal → yeni 90 gün
- Suspend vendor → tüm araçları draft (Phase 0 fix)
- Blocked dates → availability calendar'da dolu görünür

### Browser Tests (Chrome DevTools MCP)
- Vendor dashboard: Pause/Resume/Withdraw butonları çalışır
- Confirmation dialog gösterilir
- Status badge'leri doğru renk ve metin
- Expired araçta "Yenile" butonu çalışır

---

## Appendix A: Related Existing Classes

| Class | Role | File |
|-------|------|------|
| `AutoCancel` | Cron pattern reference | `src/Admin/PostTypes/Maintenance/AutoCancel.php` |
| `MaturedPayoutJob` | Cron pattern reference | `src/Core/Financial/Automation/MaturedPayoutJob.php` |
| `Ledger` | Append-only financial ledger | `src/Core/Financial/Ledger.php` |
| `LedgerEntry` | Immutable value object | `src/Core/Financial/LedgerEntry.php` |
| `Status` | Booking FSM | `src/Admin/Booking/Core/Status.php` |
| `VendorOnboardingController` | Vendor approve/reject/suspend | `src/Admin/Vendor/VendorOnboardingController.php` |
| `VendorVehicleReviewManager` | Vehicle approve/reject | `src/Admin/Vendor/VendorVehicleReviewManager.php` |
| `VendorNotifications` | 12 email templates | `src/Admin/Emails/Notifications/VendorNotifications.php` |
| `MetaKeys` | Meta key constants | `src/Admin/Core/MetaKeys.php` |
| `VehicleSubmit` | Vehicle submission shortcode | `src/Admin/Frontend/Shortcodes/Vendor/VehicleSubmit.php` |

## Appendix B: Hook Reference (New)

| Hook | Type | Params | Phase |
|------|------|--------|-------|
| `mhm_rentiva_vehicle_paused` | action | `$vehicle_id, $vendor_id` | 3 |
| `mhm_rentiva_vehicle_resumed` | action | `$vehicle_id, $vendor_id` | 3 |
| `mhm_rentiva_vehicle_withdrawn` | action | `$vehicle_id, $vendor_id, $penalty_amount` | 3 |
| `mhm_rentiva_vehicle_expired` | action | `$vehicle_id, $vendor_id` | 2 |
| `mhm_rentiva_vehicle_renewed` | action | `$vehicle_id` | 2 |
| `mhm_rentiva_vehicle_relist` | action | `$vehicle_id, $vendor_id` | 3 |
| `mhm_rentiva_withdrawal_penalty_applied` | action | `$vendor_id, $vehicle_id, $amount` | 3 |
| `mhm_rentiva_reliability_score_updated` | action | `$vendor_id, $old_score, $new_score` | 4 |
| `mhm_rentiva_notify_vendor` | action | `$vendor_id, $template_key, $context` | 6 |
| `mhm_rentiva_vehicle_lifecycle_changed` | action | `$vehicle_id, $old_status, $new_status` | 1 |
