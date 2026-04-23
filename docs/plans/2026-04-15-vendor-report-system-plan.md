# Vendor Report / Appeal System — Plan

**Target version:** v4.29.0 (originally v4.27.0; renumbered 2026-04-23 as the v4.27.x slots filled up: v4.27.0 WPCS release prep, v4.27.1 i18n locale-leak hotfix, v4.27.2 Arama UX + Shortcode Review Backlog, v4.28.0 Popüler Rotalar. A standalone new subsystem with its own custom table warrants a minor bump.)
**Created:** 2026-04-15
**Status:** 📋 Planned — not yet started
**Estimated effort:** 3-4 hours
**Priority:** Medium-High (gates fair trust-score enforcement)

---

## 1. Motivation

Rentiva şu anda bayi → yönetici arasında yapılandırılmış bir bildirim/itiraz kanalı sunmuyor. Eksikliği şu noktalarda hissediyoruz:

1. **Rezervasyon sorunları**: Müşteri gelmedi, araç hasarı, uygunsuz iptal — bayinin bunu yöneticiye ulaştırma yolu yok. `Onayla/Reddet` butonları kaldırıldı (ödeme sonrası bayinin reddetme yetkisi olması marketplace modeline uymuyor).
2. **Araç aksiyonu itirazları**: Otomatik duraklatma/çekme (ör. ceza sistemi tetiklemesi) olduğunda bayi "haksız işlem" diyemiyor — puanı otomatik düşer, itiraz yolu yok.
3. **Güvenilirlik & Ceza sistemi**: Mevcut `project_vendor_marketplace.md` bünyesinde ceza puan düşüşü otomatik. İtiraz mekanizması yok → adaletsiz puan kayıpları birikebilir.
4. **Genel iletişim**: Bayi panelinde "yöneticiye ulaş" için yapılandırılmış kanal yok.

**Hedef:** Tek bir paylaşılan altyapı — tüm bu bağlamlarda kullanılacak "Vendor Report" sistemi.

---

## 2. Scope

### In scope
- Custom tablo (`wp_mhm_rentiva_vendor_reports`) + migration
- Repository + Service + Ajax handler
- Bayi panel modal (paylaşılan JS — her bağlamdan tetiklenebilir)
- Admin listesi + detay + çözüm sayfası
- Güvenilirlik puan sistemine entegrasyon (`suspend_pending_report` hook)
- E-posta bildirimi (bayi → admin + admin cevap → bayi)
- i18n (tüm yeni string'ler .po/.pot'a)
- PHPUnit suite (min. 12 test)

### Out of scope (ileri sürümlerde)
- Ekran görüntüsü/dosya eki upload
- Chat-tarzı threaded discussion
- Rapor kategorileri için kullanıcı tanımlı taxonomy
- Çoklu dil destekli rapor içeriği
- SMS / WhatsApp bildirimi

---

## 3. Database schema

### Tablo: `{$wpdb->prefix}mhm_rentiva_vendor_reports`

| Alan | Tip | Null | Index | Açıklama |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | NO | PRI | Rapor ID |
| `vendor_id` | BIGINT UNSIGNED | NO | IDX | `wp_users.ID` — bayi |
| `context_type` | VARCHAR(32) | NO | IDX | `booking` \| `vehicle` \| `penalty` \| `general` |
| `context_id` | BIGINT UNSIGNED | YES | IDX | `booking_id` \| `vehicle_id` \| `penalty_record_id` — yoksa NULL |
| `title` | VARCHAR(255) | NO | — | Kısa başlık |
| `description` | LONGTEXT | NO | — | Detaylı açıklama |
| `status` | ENUM('open','in_review','resolved','rejected') | NO | IDX | Varsayılan `open` |
| `admin_note` | LONGTEXT | YES | — | Admin çözüm notu |
| `admin_user_id` | BIGINT UNSIGNED | YES | — | İşlemi yapan admin |
| `created_at` | DATETIME | NO | IDX | UTC |
| `updated_at` | DATETIME | NO | — | UTC |
| `resolved_at` | DATETIME | YES | — | UTC |

**Migration:** `src/Admin/Database/Migrations/0042_create_vendor_reports.php`
Aynı pattern: mevcut migration'larla uyumlu (`dbDelta` + `mhm_rentiva_db_version` option).

---

## 4. Code structure

```
src/Admin/VendorReport/
├── Core/
│   ├── VendorReportRepository.php         # CRUD, DB layer
│   ├── VendorReportService.php            # Business logic, penalty hooks
│   └── VendorReportStatus.php             # Status enum
├── Ajax/
│   └── VendorReportAjaxHandler.php        # create, list-mine, get
├── Admin/
│   ├── VendorReportsAdminPage.php         # List + detail + resolve UI
│   └── VendorReportNotifier.php           # Email notifications
└── Hooks/
    └── PenaltySuspensionHook.php          # Skor askıya alma

assets/
├── js/frontend/vendor-report-modal.js     # Shared modal trigger
└── css/frontend/vendor-report-modal.css   # Modal styles

templates/
├── account/partials/vendor-report-modal.php
└── admin/vendor-reports/
    ├── list.php
    └── detail.php
```

---

## 5. User flows

### 5.1 Bayi rapor oluşturma (rezervasyon bağlamı)
1. Bayi `Rezervasyon Talepleri` sayfasında kartta "Sorun Bildir" butonuna basar
2. Modal açılır — başlık (zorunlu) + açıklama (zorunlu, min. 20 karakter)
3. `context_type=booking`, `context_id=<booking_id>` ile AJAX POST
4. Yanıt: başarı → modal kapanır, toast + karta `.has-open-report` rozeti
5. E-posta → admin (rapor detay link'li)

### 5.2 Bayi rapor oluşturma (ceza itirazı)
1. Bayi `Güvenilirlik & Ceza` sayfasında "Puan düşüşü itirazı" butonuna basar
2. Aynı modal — `context_type=penalty`, `context_id=<penalty_record_id>`
3. Servis: `VendorReportService::suspend_pending_penalty()` tetiklenir — puan düşüşü `on_hold` olur (henüz uygulanmaz)
4. Admin `resolved` → puan iptal edilir; `rejected` → puan uygulanır

### 5.3 Admin çözümleme
1. Yönetici menüsünde "Rapor Yönetimi" sayfası — liste (filtreler: status, context_type, vendor)
2. Rapor detayına tıklar → tam görünüm + bağlam linki (rezervasyon/araç/ceza kaydı)
3. `admin_note` girer + status seçer (`resolved` / `rejected` / `in_review`)
4. Save → servis:
   - Durum değişirse `updated_at` güncelle
   - `resolved` veya `rejected` → `resolved_at` set, ilgili hook çalıştır
   - Bayi'ye e-posta (çözüm + admin not)

---

## 6. Penalty system integration

### Hook: `mhm_rentiva_before_apply_penalty`

```php
add_filter( 'mhm_rentiva_before_apply_penalty', static function( $apply, $penalty_id, $vendor_id ) {
    $has_open = VendorReportRepository::has_open_report_for(
        $vendor_id,
        'penalty',
        $penalty_id
    );
    return $has_open ? false : $apply; // askıya al
}, 10, 3 );
```

### Admin rapor çözümlemesi
- `resolved` (bayi lehine) → `PenaltyService::cancel( $penalty_id )`
- `rejected` (admin haklı diyor) → `PenaltyService::apply_pending( $penalty_id )`

---

## 7. UI touch points (bayi panel)

| Sayfa | Nereden tetiklenir | `context_type` |
|---|---|---|
| Rezervasyon Talepleri (kart action) | "Sorun Bildir" butonu | `booking` |
| İlanlar (kart action — yalnız `paused`/`withdrawn` durumunda) | "İtiraz Et" butonu | `vehicle` |
| Güvenilirlik & Ceza (son ceza kaydı altında) | "Bu cezaya itiraz et" butonu | `penalty` |
| Genel Bakış (her sayfada footer) | "Yöneticiye yaz" linki | `general` |

---

## 8. Email notifications

### Admin'e (bayi → admin yönünde)
- Subject: `[Rentiva] Yeni bayi raporu: {title}`
- Body: Bayi adı + bağlam tipi + açıklama + admin rapor link'i

### Bayi'ye (admin → bayi yönünde)
- Subject: `[Rentiva] Raporunuz güncellendi: {title}`
- Body: Durum (Çözüldü/Reddedildi) + admin notu + rapor detay link'i

---

## 9. i18n strings (en az)

```
Report Issue | Sorun Bildir
Report Submitted | Rapor gönderildi
Your report has been sent to the administrator. | Raporunuz yöneticiye iletildi.
Report Title | Rapor Başlığı
Describe the issue in detail... | Sorunu detaylı açıklayın...
Open | Açık
In Review | İncelemede
Resolved | Çözüldü
Rejected | Reddedildi
Administrator Note | Yönetici Notu
Submit Report | Raporu Gönder
Related Booking | İlgili Rezervasyon
Related Vehicle | İlgili Araç
Related Penalty | İlgili Ceza
Vendor Reports | Bayi Raporları
```

---

## 10. Testing

### PHPUnit (min. 12 test)
- `VendorReportRepositoryTest`: create, update, find_by_vendor, has_open_report_for
- `VendorReportServiceTest`: create_report, resolve, reject, penalty suspension hook
- `VendorReportAjaxHandlerTest`: validation (auth, nonce, title/desc min length), context validation

### Manual QA
- Rezervasyon kartından rapor oluştur → admin'e mail gitti mi?
- Ceza tetiklen sonra hemen itiraz → puan düştü mü yoksa askıda mı?
- Admin `resolved` → puan iptal edildi mi?
- Bayi'ye çözüm maili geldi mi?

---

## 11. Build sequence (step-by-step)

1. Migration dosyası + tablo oluşturma (v4.29.0 activation hook)
2. `VendorReportRepository` + unit test
3. `VendorReportService` + unit test (penalty hook dahil)
4. `VendorReportAjaxHandler` + nonce/auth/validation testleri
5. Modal template + CSS + JS (paylaşılan)
6. Rezervasyon kartına "Sorun Bildir" butonu bağlanır
7. Güvenilirlik & Ceza sayfasına "İtiraz Et" butonu bağlanır
8. İlanlar sayfasına (yalnız `paused`/`withdrawn`) "İtiraz Et" butonu bağlanır
9. Admin `Rapor Yönetimi` sayfası — liste + detay + çözümleme
10. E-posta template'leri (admin bildirimi + bayi cevap maili)
11. `PenaltyService` içinde `mhm_rentiva_before_apply_penalty` filter'ı kancalanır
12. i18n: tüm string'ler .po/.pot'a, .mo + .l10n.php regenerate
13. Changelog, README, readme.txt, version bump v4.29.0
14. ZIP + GitHub Release + push

---

## 12. Risks

| Risk | Etki | Azaltma |
|---|---|---|
| Açık rapor varken bayi çalkala edip yeni aynı bağlamlı rapor açar | Spam | `VendorReportService::create()` → aynı vendor + context için açık rapor varsa 409 döndür |
| Ceza askıya alma hook'u — eski ceza kayıtları (migration öncesi) ne olacak? | Geriye dönük tutarlılık | Migration sadece ileriye dönük; eski kayıtlar değişmez |
| E-posta göndermeme riski (SMTP) | Admin haberdar olmaz | Admin panelinde "Yeni rapor" rozeti (adminbar notice) — e-postaya bağlı kalmaz |
| Yüksek rapor hacmi → admin UI yavaşlar | Performans | Liste sayfasında pagination + status filter default `open` |

---

## 13. Future extensions (v4.28+)

- Dosya eki upload (ekran görüntüsü, fatura PDF)
- Threaded reply — bayi ↔ admin sohbeti
- Rapor kategorisi taxonomy (müşteri problemi / araç arızası / ödeme / diğer)
- Rapor SLA sayacı (admin X gün içinde cevap vermeli)
- Bayi profili: açık rapor sayısı + ortalama çözüm süresi
- Puan sistemi: adminin haksız cevap oranı düşerse otomatik güven skoru artışı

---

## 14. References

- Ceza sistemi mevcut mimari: `src/Admin/Penalty/` (mevcut kod)
- Marketplace genel plan: `docs/plans/2026-03-08-vendor-marketplace-plan.md`
- Rezervasyon kart kaldırılan Approve/Decline butonları: `templates/account/partials/vendor-bookings.php` (v4.26.5, 2026-04-15)
- Memory: `C:\Users\manag\.claude\projects\c--projects-rentiva-dev\memory\project_vendor_marketplace.md`

---

**Bu plan v4.29.0'ın çekirdek teslimatı. v4.27.0 (WPCS release prep), v4.27.1 (i18n hotfix), v4.27.2 (Arama UX + Shortcode backlog) ve v4.28.0 (Popüler Rotalar shortcode) tamamlandıktan ve stabil olduktan sonra implementation başlar.**
