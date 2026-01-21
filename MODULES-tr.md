# MHM Rentiva - Modül Mimarisi ve Teknik Dökümantasyon

<div align="right">

**🌐 Dil / Language:** 
[![TR](https://img.shields.io/badge/Dil-Türkçe-red)](MODULES-tr.md) 
[![EN](https://img.shields.io/badge/Language-English-blue)](MODULES.md)

</div>

![Version](https://img.shields.io/badge/version-4.6.2-blue.svg)
![Modules](https://img.shields.io/badge/modüller-22-green.svg)
![Security](https://img.shields.io/badge/güvenlik-WPCS%20Uyumlu-brightgreen.svg)
![Last Audit](https://img.shields.io/badge/son%20denetim-2026--01--21-blue.svg)

> **Teknik Anayasa** - Bu belge, MHM Rentiva eklentisinin mimari referansı olarak hizmet verir; tüm 22 modülü, sorumluluklarını, ilişkilerini ve güvenlik durumlarını detaylandırır.

---

## 📋 İçindekiler

- [Mimari Genel Bakış](#mimari-genel-bakış)
- [Modül Kategorileri](#modül-kategorileri)
- [İş Mantığı Modülleri](#-iş-mantığı-modülleri)
- [Kullanıcı Operasyon Modülleri](#-kullanıcı-operasyon-modülleri)
- [Sistem Altyapı Modülleri](#-sistem-altyapı-modülleri)
- [Güvenlik ve API Modülleri](#-güvenlik-ve-api-modülleri)
- [Modül İlişkileri Grafiği](#modül-ilişkileri-grafiği)
- [Güvenlik Denetimi Özeti](#güvenlik-denetimi-özeti)

---

## 🏗️ Mimari Genel Bakış

MHM Rentiva, net sorumluluk ayrımı ile **modüler monolit** mimarisini takip eder:

```
┌─────────────────────────────────────────────────────────────────┐
│                        WORDPRESS ÇEKİRDEK                       │
├─────────────────────────────────────────────────────────────────┤
│                     MHM RENTIVA EKLENTİSİ                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                      İŞ MANTIĞI                          │   │
│  │   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐       │   │
│  │   │Rezervasn│ │  Araç   │ │Transfer │ │  Ödeme  │       │   │
│  │   └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘       │   │
│  │        │           │           │           │             │   │
│  │        └───────────┴─────┬─────┴───────────┘             │   │
│  │                          │                                │   │
│  │  ┌───────────────────────┴───────────────────────┐       │   │
│  │  │              ÇEKİRDEK ALTYAPI                 │       │   │
│  │  │  Ayarlar │ E-posta │ Raporlar │ Araçlar      │       │   │
│  │  └───────────────────────────────────────────────┘       │   │
│  │                          │                                │   │
│  │  ┌───────────────────────┴───────────────────────┐       │   │
│  │  │              KULLANICI OPERASYONLARİ          │       │   │
│  │  │  Müşteriler │ Mesajlar │ Ön Yüz │ Hesap      │       │   │
│  │  └───────────────────────────────────────────────┘       │   │
│  │                          │                                │   │
│  │  ┌───────────────────────┴───────────────────────┐       │   │
│  │  │              GÜVENLİK VE API                  │       │   │
│  │  │   Kimlik │ Gizlilik │ Lisans │ REST │ Eklenti│       │   │
│  │  └───────────────────────────────────────────────┘       │   │
│  └─────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                    WOOCOMMERCE (Opsiyonel)                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📂 Modül Kategorileri

| Kategori | Modül Sayısı | Açıklama |
|----------|--------------|----------|
| **İş Mantığı** | 4 | Temel kiralama operasyonları (Rezervasyon, Araç, Transfer, Ödeme) |
| **Kullanıcı Operasyonları** | 4 | Müşteri odaklı özellikler (Müşteriler, Mesajlar, Ön Yüz, Hesap) |
| **Sistem Altyapısı** | 8 | Arka uç servisleri (Ayarlar, E-posta, Raporlar, Araçlar, Çekirdek, Kurulum, PostTypes, Hakkında) |
| **Güvenlik ve API** | 6 | Güvenlik ve entegrasyon (Kimlik, Gizlilik, Lisans, REST, Eklentiler, Test) |

---

## 💼 İş Mantığı Modülleri

### 📦 Rezervasyon (Booking)
* **Dizin:** `src/Admin/Booking/`
* **Tür:** Temel İş Mantığı
* **Tanım:** Oluşturmadan tamamlanmaya kadar müsaitlik kontrolleri, fiyat hesaplamaları ve durum yönetimi dahil tüm rezervasyon yaşam döngüsünü yönetir.
* **Kritik Dosyalar:**
  - `Core/Handler.php` - Ana rezervasyon form işleyici
  - `Core/BookingManager.php` - Rezervasyon CRUD işlemleri
  - `Actions/DepositManagementAjax.php` - Depozito ödeme işleme
  - `Meta/BookingMeta.php` - Rezervasyon meta veri yönetimi
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 21 konum
* **İlişkiler:** Araç, Ödeme, Müşteriler, WooCommerce, E-posta

---

### 📦 Araç (Vehicle)
* **Dizin:** `src/Admin/Vehicle/`
* **Tür:** Temel İş Mantığı
* **Tanım:** Galeriler, özellikler, müsaitlik kuralları ve fiyat kademeleri dahil eksiksiz araç envanter yönetimi.
* **Kritik Dosyalar:**
  - `Meta/VehicleMeta.php` - Araç meta veri işleyici
  - `Meta/VehicleGallery.php` - Görsel galeri yönetimi
  - `Settings/VehicleSettings.php` - Araç yapılandırması
  - `ListTable/VehicleColumns.php` - Admin liste özelleştirmesi
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 12 konum
* **İlişkiler:** Rezervasyon, Ön Yüz, PostTypes

---

### 📦 Transfer (VIP)
* **Dizin:** `src/Admin/Transfer/`
* **Tür:** Temel İş Mantığı
* **Tanım:** Konum/rota yönetimi, mesafe tabanlı fiyatlandırma ve araç seçimi ile noktadan noktaya şoför hizmeti.
* **Kritik Dosyalar:**
  - `Frontend/TransferShortcodes.php` - Arama formu ve sonuçlar
  - `Integration/TransferCartIntegration.php` - WooCommerce sepet köprüsü
  - `TransferSearchEngine.php` - Rota ve araç eşleştirme
  - `TransferAdmin.php` - Admin panel yönetimi
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 7 konum
* **İlişkiler:** WooCommerce, Araç, Ödeme, Ön Yüz

---

### 📦 Ödeme (Payment)
* **Dizin:** `src/Admin/Payment/`
* **Tür:** Temel İş Mantığı
* **Tanım:** WooCommerce entegrasyonu, depozito sistemi, iadeler ve çoklu ödeme ağ geçidi desteği ile ödeme işleme.
* **Kritik Dosyalar:**
  - `WooCommerce/WooCommerceBridge.php` - WooCommerce entegrasyonu
  - `Refunds/Service.php` - İade işleme
  - `DepositCalculator.php` - Depozito tutarı hesaplama
  - `PaymentGatewayManager.php` - Ağ geçidi yönetimi
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 2 konum
* **İlişkiler:** Rezervasyon, Transfer, WooCommerce, E-posta

---

## 👥 Kullanıcı Operasyon Modülleri

### 📦 Müşteriler (Customers)
* **Dizin:** `src/Admin/Customers/`
* **Tür:** Kullanıcı Operasyonları
* **Tanım:** Profil yönetimi, rezervasyon geçmişi ve yönetim araçları ile müşteri yönetim sistemi.
* **Kritik Dosyalar:**
  - `CustomersPage.php` - Admin müşteri listesi
  - `AddCustomerPage.php` - Müşteri oluşturma formu
  - `CustomerProfile.php` - Profil yönetimi
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 3 konum
* **İlişkiler:** Rezervasyon, Mesajlar, Ön Yüz

---

### 📦 Mesajlar (Messages)
* **Dizin:** `src/Admin/Messages/`
* **Tür:** Kullanıcı Operasyonları
* **Tanım:** Thread yönetimi ve bildirimler ile müşteriler ve yöneticiler arasında dahili mesajlaşma sistemi.
* **Kritik Dosyalar:**
  - `Core/Messages.php` - Temel mesajlaşma mantığı
  - `Core/MessageUrlHelper.php` - URL oluşturma
  - `Monitoring/MessageLogger.php` - Mesaj denetim günlükleri
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 2 konum
* **İlişkiler:** Müşteriler, E-posta, Ön Yüz

---

### 📦 Ön Yüz (Frontend)
* **Dizin:** `src/Admin/Frontend/`
* **Tür:** Kullanıcı Operasyonları
* **Tanım:** Shortcode'lar, hesap sayfaları, bloklar ve müşteri portalı dahil tüm ön yüze bakan bileşenler.
* **Kritik Dosyalar:**
  - `Shortcodes/BookingForm.php` - Rezervasyon formu shortcode
  - `Shortcodes/VehiclesList.php` - Puanlama ile araç listesi
  - `Shortcodes/VehicleRatingForm.php` - Müşteri puanlama sistemi
  - `Account/AccountController.php` - Hesabım sayfa kontrolcüsü
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 18 konum
* **İlişkiler:** Rezervasyon, Araç, Müşteriler, Mesajlar, WooCommerce

---

### 📦 Hesap (Account)
* **Dizin:** `src/Admin/Frontend/Account/`
* **Tür:** Kullanıcı Operasyonları
* **Tanım:** Rezervasyon yönetimi, favoriler, profil düzenleme ve belge yüklemeleri ile müşteri self-servis portalı.
* **Kritik Dosyalar:**
  - `AccountController.php` - Ana hesap koordinatörü
  - `Tabs/BookingsTab.php` - Rezervasyon geçmişi görünümü
  - `Tabs/FavoritesTab.php` - Favori araçlar
  - `Tabs/ProfileTab.php` - Profil düzenleme
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **İlişkiler:** Ön Yüz, Müşteriler, Rezervasyon, Mesajlar

---

## ⚙️ Sistem Altyapı Modülleri

### 📦 Ayarlar (Settings)
* **Dizin:** `src/Admin/Settings/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Gruplandırılmış ayarlar, sanitizasyon ve doğrulama ile merkezi yapılandırma yönetimi.
* **Kritik Dosyalar:**
  - `Core/SettingsCore.php` - Temel ayarlar API'si
  - `SettingsHandler.php` - Ayarları kaydetme/sıfırlama işleyici
  - `Groups/*.php` - Gruplandırılmış ayar tanımları
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 4 konum
* **İlişkiler:** Tüm modüller (merkezi yapılandırma)

---

### 📦 E-postalar (Emails)
* **Dizin:** `src/Admin/Emails/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Özelleştirilebilir HTML şablonları ve tetiklemeli gönderim ile otomatik e-posta bildirim sistemi.
* **Kritik Dosyalar:**
  - `Core/EmailTemplates.php` - Şablon yönetimi
  - `Sender/EmailSender.php` - E-posta gönderimi
  - `Templates/*.php` - Bireysel e-posta şablonları
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 1 konum
* **İlişkiler:** Rezervasyon, Müşteriler, Mesajlar, Ayarlar

---

### 📦 Raporlar (Reports)
* **Dizin:** `src/Admin/Reports/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Gelir, müşteri ve araç içgörüleri ile kapsamlı analitik dashboard.
* **Kritik Dosyalar:**
  - `Reports.php` - Ana raporlar koordinatörü
  - `BusinessLogic/RevenueReport.php` - Gelir analitiği
  - `BusinessLogic/BookingReport.php` - Rezervasyon istatistikleri
  - `Charts.php` - Chart.js entegrasyonu
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 0 (form gönderimi yok)
* **İlişkiler:** Rezervasyon, Araç, Ödeme, Ayarlar

---

### 📦 Araçlar (Utilities)
* **Dizin:** `src/Admin/Utilities/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Dışa aktarma, veritabanı temizliği, cron izleme ve kaldırma işleyicileri dahil sistem bakım araçları.
* **Kritik Dosyalar:**
  - `Export/Export.php` - Veri dışa aktarma işlevselliği
  - `Actions/Actions.php` - Yardımcı eylemler (log temizleme, vb.)
  - `Database/DatabaseCleanupPage.php` - VT bakımı
  - `Cron/CronMonitorPage.php` - Zamanlanmış görev izleme
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 4 konum
* **İlişkiler:** Tüm modüller (bakım operasyonları)

---

### 📦 Çekirdek (Core)
* **Dizin:** `src/Admin/Core/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Soyut sınıflar, trait'ler, yardımcılar ve temel araçlar dahil paylaşılan altyapı bileşenleri.
* **Kritik Dosyalar:**
  - `Utilities/AbstractListTable.php` - Temel liste tablosu sınıfı
  - `Traits/AdminHelperTrait.php` - Ortak admin yardımcıları
  - `MetaBoxes/AbstractMetaBox.php` - Temel meta kutusu sınıfı
  - `Helpers/Sanitizer.php` - Merkezi sanitizasyon araçları
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 3 konum
* **İlişkiler:** Tüm modüller (temel altyapı)

---

### 📦 Kurulum (Setup)
* **Dizin:** `src/Admin/Setup/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Eklenti kurulum sihirbazı ve başlangıç yapılandırma rehberliği.
* **Kritik Dosyalar:**
  - `SetupWizard.php` - Adım adım kurulum
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - Nonce işlemi yok)
* **Nonce Yamaları:** 0
* **İlişkiler:** Ayarlar, PostTypes

---

### 📦 Hakkında (About)
* **Dizin:** `src/Admin/About/`
* **Tür:** Sistem Altyapısı
* **Tanım:** Sistem teşhisleri, özellik genel görünümü ve destek kaynakları ile eklenti bilgi sayfası.
* **Kritik Dosyalar:**
  - `About.php` - Ana hakkında sayfası
  - `SystemInfo.php` - Sistem teşhisleri
  - `Tabs/*.php` - Bilgi sekmeleri
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 1 konum
* **İlişkiler:** Ayarlar, Lisans

---

## 🔒 Güvenlik ve API Modülleri

### 📦 Kimlik Doğrulama (Auth)
* **Dizin:** `src/Admin/Auth/`
* **Tür:** Güvenlik ve API
* **Tanım:** Gelişmiş hesap güvenliği için iki faktörlü kimlik doğrulama sistemi.
* **Kritik Dosyalar:**
  - `TwoFactorManager.php` - 2FA uygulaması (TOTP)
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 3 konum
* **İlişkiler:** Müşteriler, Ön Yüz, Ayarlar

---

### 📦 Gizlilik (Privacy)
* **Dizin:** `src/Admin/Privacy/`
* **Tür:** Güvenlik ve API
* **Tanım:** Veri dışa aktarma, silme ve onay yönetimi dahil GDPR uyumluluk özellikleri.
* **Kritik Dosyalar:**
  - `GDPRManager.php` - GDPR işlemleri işleyici
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 3 konum
* **İlişkiler:** Müşteriler, Ayarlar, Rezervasyon

---

### 📦 Lisanslama (Licensing)
* **Dizin:** `src/Admin/Licensing/`
* **Tür:** Güvenlik ve API
* **Tanım:** Pro özellik aktivasyonu ve doğrulama için lisans anahtarı yönetimi.
* **Kritik Dosyalar:**
  - `LicenseManager.php` - Lisans API entegrasyonu
  - `LicenseAdmin.php` - Admin lisans sayfası
  - `Mode.php` - Pro/Lite mod algılama
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 3 konum
* **İlişkiler:** Ayarlar, Hakkında, Tüm Pro özellikler

---

### 📦 REST
* **Dizin:** `src/Admin/REST/`
* **Tür:** Güvenlik ve API
* **Tanım:** Üçüncü taraf entegrasyonları ve mobil uygulamalar için eksiksiz REST API.
* **Kritik Dosyalar:**
  - `VehicleEndpoint.php` - Araç API
  - `BookingEndpoint.php` - Rezervasyon API
  - `AuthEndpoint.php` - Kimlik doğrulama API
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WordPress REST nonce sistemi kullanır)
* **Nonce Yamaları:** 0 (WordPress REST kimlik doğrulamayı yönetir)
* **İlişkiler:** Tüm kamuya açık modüller

---

### 📦 Eklentiler (Addons)
* **Dizin:** `src/Admin/Addons/`
* **Tür:** Güvenlik ve API
* **Tanım:** Fiyatlandırma ve entegrasyon ile rezervasyon eklentileri/ekstraları yönetimi.
* **Kritik Dosyalar:**
  - `AddonManager.php` - Eklenti CRUD ve fiyatlandırma
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 2 konum
* **İlişkiler:** Rezervasyon, Ödeme, Araç

---

### 📦 Test (Testing)
* **Dizin:** `src/Admin/Testing/`
* **Tür:** Güvenlik ve API
* **Tanım:** Shortcode testi ve güvenlik doğrulama için geliştirme ve QA araçları.
* **Kritik Dosyalar:**
  - `ShortcodeTestHandler.php` - Shortcode testi
  - `SecurityTest.php` - Güvenlik denetim araçları
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 1 konum
* **İlişkiler:** Ön Yüz, Tüm shortcode modülleri

---

### 📦 Eylemler (Actions)
* **Dizin:** `src/Admin/Actions/`
* **Tür:** Güvenlik ve API
* **Tanım:** İadeler, log yönetimi ve sayfa oluşturma dahil global admin eylemleri.
* **Kritik Dosyalar:**
  - `Actions.php` - Global eylem işleyicileri
* **Güvenlik Durumu:** ✅ Doğrulandı (v4.6.2 - WPCS & Nonce Güçlendirildi)
* **Nonce Yamaları:** 1 konum
* **İlişkiler:** Rezervasyon, Ödeme, Ayarlar, Araçlar

---

## 🔗 Modül İlişkileri Grafiği

```
                              ┌──────────────┐
                              │   AYARLAR    │
                              └──────┬───────┘
                                     │ (tümünü yapılandırır)
         ┌───────────────────────────┼───────────────────────────┐
         │                           │                           │
    ┌────┴────┐                ┌─────┴─────┐              ┌──────┴─────┐
    │  ARAÇ   │◄───────────────│REZERVASYON│──────────────►│   ÖDEME   │
    └────┬────┘                └─────┬─────┘              └──────┬─────┘
         │                           │                           │
         │    ┌──────────────────────┼──────────────────────┐   │
         │    │                      │                      │   │
         ▼    ▼                      ▼                      ▼   ▼
    ┌─────────────┐            ┌───────────┐         ┌───────────────┐
    │   ÖN YÜZ   │◄───────────│ MÜŞTERİLER│────────►│  WOOCOMMERCE  │
    │ (Shortcode)│            └─────┬─────┘         └───────────────┘
    └──────┬──────┘                  │
           │                         │
           ▼                         ▼
    ┌─────────────┐            ┌───────────┐
    │  TRANSFER   │            │  MESAJLAR │
    │   (VIP)     │            └─────┬─────┘
    └──────┬──────┘                  │
           │                         ▼
           └────────────────────►┌───────────┐
                                 │  E-POSTA  │
                                 └───────────┘
```

---

## 🛡️ Güvenlik Denetimi Özeti

### v4.6.2 Güvenlik Yamaları (2026-01-21)

| Metrik | Değer |
|--------|-------|
| **Denetlenen Toplam Modül** | 22 |
| **Değiştirilen Toplam Dosya** | 59+ |
| **Nonce Güçlendirme Yamaları** | 91 |
| **WPCS Uyumluluğu** | %100 |
| **Kritik Güvenlik Açıkları** | 0 |

### Uygulanan Güvenlik Standartları

1. **Nonce Doğrulama**: Tüm `wp_verify_nonce()` çağrıları `sanitize_text_field(wp_unslash())` ile sarıldı
2. **Girdi Sanitizasyonu**: `Sanitizer::text_field_safe()` yardımcısının tutarlı kullanımı
3. **SQL Enjeksiyon Önleme**: Tüm sorgular prepared statement kullanır
4. **XSS Önleme**: Tüm çıktılar `esc_html()`, `esc_attr()`, `wp_kses_post()` ile düzgün escape edildi
5. **CSRF Koruması**: Tüm formlar ve AJAX işleyicileri nonce ile korundu

---

## 📚 Ek Kaynaklar

- [README.md](README.md) - Ana dökümantasyon (İngilizce)
- [README-tr.md](README-tr.md) - Türkçe dökümantasyon
- [changelog.json](changelog.json) - Sürüm geçmişi (İngilizce)
- [changelog-tr.json](changelog-tr.json) - Sürüm geçmişi (Türkçe)
- [readme.txt](readme.txt) - WordPress.org meta verileri

---

## 📝 Belge Meta Verileri

| Özellik | Değer |
|---------|-------|
| **Belge Sürümü** | 1.0.0 |
| **Eklenti Sürümü** | 4.6.2 |
| **Son Güncelleme** | 2026-01-21 |
| **Yazar** | MHM Geliştirme Ekibi |
| **Lisans** | GPL-2.0+ |

---

*Bu belge, MHM Rentiva geliştirme sürecinin bir parçası olarak otomatik oluşturulur ve bakımı yapılır.*
