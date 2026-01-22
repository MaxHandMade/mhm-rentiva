---
name: mhm-architect
description: Yeni özellikler için teknik analiz, veritabanı tasarımı ve spec oluşturma rehberi.
---

# MHM Architect Skill

Bu skill, MHM Rentiva projesine eklenecek yeni özelliklerin teknik planlamasını yapmak, veritabanı şemasını tasarlamak ve kodlama öncesi yol haritasını (`specs`) çizmek için kullanılır.

**Temel Kural:** "Önce Düşün, Sonra Kodla." (Plan First, Code Later).

## 1. İş Akışı ve Tetikleyici (Workflow)
Kodlama başlamadan önce, **Faz 0** aşamasında devreye girer. Çıktısı bir PHP kodu değil, `.md` formatında bir teknik rapordur.

### Spec Dosyası Oluşturma Standardı

Her yeni özellik için `specs/` klasörü altında `[ozellik-adi].md` dosyası oluşturulmalıdır.

**Standart Şablon Yapısı:**

```markdown
# [Özellik Adı] - Teknik Spesifikasyon

## 1. Genel Bakış
Bu özellik ne işe yarayacak? Kullanıcı hikayesi nedir?

## 2. Veritabanı Şeması (Database Schema)
Eklenecek yeni tablolar ve ilişkiler.
- Tablo Adı: `wp_mhm_rentiva_[isim]`
- Motor: InnoDB
- Collation: utf8mb4_unicode_ci

## 3. Sınıf Mimarisi (Class Structure)
Hangi PHP sınıfları oluşturulacak?
- `MHMRentiva\Admin\...`
- `MHMRentiva\Frontend\...`

## 4. Kullanıcı Arayüzü ve UX (UI/UX)
Yeni eklenecek ekranlar, menüler ve shortcode'lar.
- Admin Menü Yolu: `Araçlar > Rentiva > ...`
- Shortcode: `[mhm_rentiva_...]`

## 5. API ve Hook Noktaları
Dışarıya açılacak API endpoint'leri ve geliştiriciler için filter/action hook'ları.
- Filter: `apply_filters('mhm_rentiva_...', $value)`
- Action: `do_action('mhm_rentiva_...', $arg)`

## 6. Güvenlik ve İzinler (Security)
Bu özelliğe kimler erişebilir?
- Capability: `manage_options`, `read` vb.
- Nonce Action: `mhm_rentiva_Action_nonce`

## 7. Risk Analizi
- Buffer Time çakışması var mı?
- Mevcut WooCommerce akışını bozar mı?

## 8. Dosya ve Klasör Yapısı (File Map)
Yeni oluşturulacak veya değiştirilecek dosyaların ağaç yapısı.
```text
src/Admin/
├── NewFeature/
│   ├── Core/
│   │   └── Manager.php
│   └── Views/
│       └── admin-page.php
```

## 9. Göç ve Veri Uyumluluğu (Migration)
- SQL güncellemesi gerekir mi?
- `mhm_rentiva_db_update` versiyonu artırılmalı mı?
- Mevcut veriler nasıl dönüştürülecek?

## 10. Test Stratejisi
- **Unit Test:** Hangi methodlar test edilecek?
- **Integration Test:** WooCommerce sipariş akışı test edilmeli mi?
- **Manual Test:** Admin panelinde hangi adımlar izlenmeli?

## 11. Geri Alma Planı (Rollback)
İşler ters giderse özellik nasıl devre dışı bırakılır?
- Feature Flag: `mhm_feature_x_enabled`
- DB Tablosu drop edilmeli mi?

## 12. Onay Kontrol Listesi (Checklist)
Kodlamaya başlamadan önce:
- [ ] Veritabanı şeması 3NF'ye uygun mu?
- [ ] İsimlendirme standardı (WPCS) kontrol edildi mi?
- [ ] Güvenlik (Nonce, Capability) eksiksiz mi?