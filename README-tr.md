# MHM Rentiva - WordPress Araç Kiralama Eklentisi

<div align="right">

**🌐 Dil / Language:** 
[![TR](https://img.shields.io/badge/Language-Turkce-red)](README-tr.md) 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![Degisiklikler TR](https://img.shields.io/badge/Changelog-TR-orange)](changelog-tr.json) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json)

</div>

<p align="center">
  <img src=".wordpress-org/banner-1544x500.png" alt="MHM Rentiva — WordPress için Araç Kiralama Rezervasyon Sistemi" width="800">
</p>

![Version](https://img.shields.io/badge/version-5.0.1-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

**WordPress için profesyonel araç kiralama yönetim sistemi.** Araç kiralama, rezervasyon, ödeme, müşteri yönetimi ve kapsamlı raporlama için eksiksiz, kurumsal düzeyde bir çözüm. WordPress en iyi uygulamalarıyla geliştirilmiş, tam uluslararasılaştırma desteği ile küresel pazarlara hazır.

Aşağıdaki **Lite** sütununda yer alan her şey eksiksiz çalışır: araç, rezervasyon veya ilan sınırı, özellik sayacı ya da kilitli ekran yoktur.

---

## 🧩 Sürümler — Lite ve Pro farkı

**Bu depo MHM Rentiva (Lite)'dır** — WordPress.org'da yayınlanan ücretsiz sürüm. Deneme sürümü değil, eksiksiz bir kiralama sistemidir: aşağıdaki **Lite** sütunundaki her şey tam çalışır; araç, rezervasyon veya ilan sınırı, özellik sayacı ya da kilitli ekran yoktur. Lite'ta hiçbir şey Pro'yu özendirmek için kısıtlanmamıştır ve Lite, yönetici panelinizde Pro reklamı yapmaz.

**MHM Rentiva Pro**, Lite'ın yanına kurulan *ayrı ücretli bir eklentidir*. Lite'ın yerini almaz — üzerine pazaryeri, transfer ve uyumluluk katmanını ekler.

| Yetenek | Lite (bu depo) | Pro eklentisi |
| --- | :---: | :---: |
| Filo ve araç yönetimi — sınırsız | ✅ | ✅ |
| Uygunluk, rezervasyon motoru ve yönetici takvimi | ✅ | ✅ |
| Müşteriler, rezervasyon geçmişi, müşteri CSV'si | ✅ | ✅ |
| WooCommerce ödemesi + çevrimdışı manuel rezervasyon | ✅ | ✅ |
| Düzenlenebilir şablonlarla e-posta bildirimleri | ✅ | ✅ |
| Müşteri hesap sayfaları — rezervasyonlar, favoriler, ödeme geçmişi | ✅ | ✅ |
| 16 kısa kod · 16 Gutenberg bloğu · 17 Elementor widget'ı | ✅ | ✅ |
| Değerlendirmeler, yorumlar, araç karşılaştırma, iletişim formu | ✅ | ✅ |
| REST API — uygunluk, müşteriler, kontrol paneli (+ API anahtarları) | ✅ | ✅ |
| **Çok bayili pazaryeri** — bayi başvurusu, bayi paneli, bayi ilanları | — | ✅ |
| **Bayi hakedişleri, komisyon ve defter (ledger)** | — | ✅ |
| **Bayi raporları ve itirazlar** | — | ✅ |
| **VIP transfer + konum bazlı rotalar** | — | ✅ |
| **Müşteri mesajlaşması** | — | ✅ |
| **Gelişmiş raporlar** | — | ✅ |
| **Ayrı dışa aktarma ekranı** | — | ✅ |
| **KVKK/GDPR ve veri saklama araçları** | — | ✅ |

Pro yetenekleri yalnızca gizlenmez, **geçerli bir lisansa bağlanır** — Pro kurulu ama lisanssızsa o yüzeyler kapalı kalır.

Pro için: **[wpalemi.com/rentiva](https://wpalemi.com/rentiva/)**. Her iki sürümün özellik bazlı tam dokümantasyonu: [dokümantasyon sitesi](https://maxhandmade.github.io/mhm-rentiva-docs/).

> Bu dosya geliştiricilere yöneliktir ve dağıtılan eklenti paketine **girmez** (`.distignore`). WordPress.org listelemesini `readme.txt` besler; o dosyada — projenin Karar A'sı gereği — karşılaştırma tablosu ve satın alma çağrısı bulunmaz.

---

## 📋 İçindekiler

- [Sürümler — Lite ve Pro farkı](#-sürümler--lite-ve-pro-farkı)
- [Genel Bakış](#-genel-bakış)
- [Temel Özellikler](#-temel-özellikler)
- [Silme Sistemi](#-silme-sistemi)
- [Kurulum](#-kurulum)
- [Yapılandırma](#-yapılandırma)
- [Kullanım Kılavuzu](#-kullanım-kılavuzu)
- [Shortcode Referansı](#-shortcode-referansı)
- [REST API Dokümantasyonu](#-rest-api-dokümantasyonu)
- [Proje Yapısı](#-proje-yapısı)
- [Gereksinimler](#-gereksinimler)
- [Geliştirme](#-geliştirme)
- [Modern React Admin Arayüzü](#-modern-react-admin-arayüzü)
- [Katkıda Bulunma](#-katkıda-bulunma)
- [Değişiklik Geçmişi](#-değişiklik-geçmişi-changelog)
- [Lisans](#-lisans)
- [Geliştirici](#-geliştirici)
- [Destek](#-destek)
- [Projeyi Yıldızlayın](#-projeyi-yıldızlayın)

---

## 🎯 Genel Bakış

MHM Rentiva, araç kiralama işletmeleri için tasarlanmış kapsamlı bir WordPress eklentisidir. Araba kiralama şirketi, bisiklet/motosiklet kiralama hizmeti veya herhangi bir araç tabanlı kiralama işletmesi yönetiyorsanız, bu eklenti operasyonlarınızı verimli bir şekilde yönetmek için ihtiyacınız olan her şeyi sağlar.

### Bu Eklenti Ne Yapar?

- **Araç Yönetimi**: Galeri, kategoriler, fiyatlandırma ve müsaitlik ile eksiksiz araç envanter yönetimi
- **Rezervasyon Sistemi**: Gerçek zamanlı müsaitlik kontrolü, rezervasyon yönetimi ve otomatik iptal
- **Ödeme İşleme**: Tüm frontend rezervasyonları için WooCommerce entegrasyonu ile güvenli ödeme işlemleri
- **WooCommerce Hesabım Entegrasyonu**: Müşteriler standart WooCommerce "Hesabım" sayfasını kullanır; eklenti bu sayfaya Rezervasyonlarım, Favorilerim ve Ödeme Geçmişi sekmelerini ekler
- **Raporlama**: Gelir, müşteri ve araç içgörüleri ile kontrol paneli
- **E-posta Sistemi**: Özelleştirilebilir HTML şablonları ile otomatik e-posta bildirimleri
- **REST API**: Üçüncü taraf entegrasyonları ve mobil uygulamalar için REST API

### Bu Eklenti Kimler İçin?

- **Araba Kiralama Şirketleri**: Filo, rezervasyon ve müşteri ilişkilerini yönetin
- **Bisiklet/Motosiklet Kiralama**: Müsaitliği takip edin ve ödemeleri işleyin
- **Ekipman Kiralama İşletmeleri**: Her türlü araç veya ekipmanı kiralayın
- **Çeviriye Hazır**: Eklenti İngilizce ve Türkçe ile gelir; WooCommerce uyumlu para birimi desteği. Loco Translate üzerinden her dile çevrilebilir

---

## ✨ Temel Özellikler

### 🚗 Araç Yönetim Sistemi

**Temel Araç Özellikleri:**
- **Özel Post Tipi**: Araçlar için yerel WordPress post tipi
- **Araç Galerisi**: WordPress Medya Kütüphanesi entegrasyonu ile görsel yükleme (üst sınır ayarlardan yönetilir, varsayılan 50)
- **Sürükle-Bırak Sıralama**: Sezgisel sürükle-bırak arayüzü ile araç görsellerini yeniden sıralama
- **Araç Kategorileri**: Araçları organize etmek için hiyerarşik taksonomi sistemi
- **Araç Meta Verileri**: 
  - Günlük fiyatlandırma
  - Araç özellikleri (marka, model, yıl, yakıt tipi, şanzıman, vb.)
  - Özellik ve ekipman listeleri
  - Depozito ayarları (sabit veya yüzde)
  - Müsaitlik durumu
  - Öne çıkan araç seçeneği
- **Arama ve Filtreleme**: Kategori, durum ve fiyat aralığına göre gelişmiş filtreleme
- **Araç Karşılaştırma**: Birden fazla aracı yan yana karşılaştırma

**Araç Görüntüleme Seçenekleri:**
- Grid ve liste görünümleri (`[rentiva_vehicles_grid]`, `[rentiva_vehicles_list]`)
- Öne çıkan araçlar slider/grid görünümü (`[rentiva_featured_vehicles]`)
- Tek araç detay sayfaları
- Gelişmiş filtrelerle arama sonuçları
- Müsaitlik takvimi entegrasyonu (`[rentiva_availability_calendar]`)

### 📅 Rezervasyon Sistemi

**Rezervasyon Yönetimi:**
- **Gerçek Zamanlı Müsaitlik**: Otomatik çakışma tespiti ve önleme
- **Veritabanı Kilitleme**: Satır düzeyinde kilitleme ile çift rezervasyonu önler
- **Rezervasyon Durumları**: 
  - Taslak (`draft`)
  - Ödeme Bekleniyor (`pending_payment`) — WooCommerce siparişi oluşturuldu, ödeme bekleniyor
  - Beklemede (`pending`) — Admin onayı bekleniyor
  - Onaylandı (`confirmed`) — Ödeme alındı
  - Devam Ediyor (`in_progress`) — Araç teslim edildi, kiralama sürüyor
  - Tamamlandı (`completed`) — Araç iade edildi
  - İptal Edildi (`cancelled`)
  - İade Edildi (`refunded`)
  - Gelmedi (`no_show`)
- **Otomatik İptal**: Ödenmemiş rezervasyonlar için yapılandırılabilir otomatik iptal (varsayılan: 30 dakika)
- **Manuel Rezervasyonlar**: Yönetici, doğrudan yönetim panelinden rezervasyon oluşturabilir
- **Rezervasyon Takvimi**: Admin panelinde tüm rezervasyonların aylık takvim görünümü
- **Rezervasyon Geçmişi**: Müşteriler ve admin için tam rezervasyon geçmişi

**Rezervasyon Özellikleri:**
- Doğrulamalı tarih aralığı seçimi
- Müsaitlik kontrollü araç seçimi
- Ek hizmetler entegrasyonu
- Müşteri bilgisi toplama
- Ödeme işlem entegrasyonu
- Offline ödemeler için makbuz yükleme (Manuel rezervasyonlar)
- E-posta onayları
- Rezervasyon hatırlatıcıları

### 💳 Ödeme Sistemi

**1. Frontend (Müşteri) Ödemeleri (WooCommerce ile)**
- **WooCommerce Entegrasyonu**: Tüm frontend rezervasyonları WooCommerce üzerinden güvenle işlenir.
- **Ödeme Yöntemleri**: WooCommerce tarafından desteklenen tüm yöntemleri (Kredi Kartı, Banka Havalesi, PayPal, Kapıda Ödeme, vb.) kabul edin.
- **Otomatik Durum Güncellemeleri**: Rezervasyon durumları, WooCommerce sipariş durumuna göre otomatik güncellenir.

**2. Manuel Ödemeler (Sadece Yönetici)**
- **Manuel Ödeme Kaydı**: Yöneticiler manuel oluşturulan rezervasyonlar için ödemeleri (Nakit/Havale) sisteme işleyebilir.
- **Makbuz Yönetimi**: Yöneticiler manuel rezervasyonlara ödeme kanıtı ekleyebilir.

**Ödeme Özellikleri:**
- Kısmi ödeme desteği — Depozito sistemi (yüzde bazlı)
- **Kalan Ödeme**: Depozito ile oluşturulan rezervasyonlarda müşteriler, Hesabım → Rezervasyon Detayı sayfasından kalan bakiyeyi ödeyebilir — aktif tüm WooCommerce ödeme altyapıları kod değişikliği gerektirmeden çalışır
- Kısmi ve tam iade desteği (WooCommerce API üzerinden)
- Ödeme durumu takibi
- Güvenli işlem yönetimi

### 👥 Müşteri Yönetimi

**Müşteri Hesap Sistemi:**
- **WordPress & WooCommerce Entegrasyonu**: Standart WordPress kullanıcı sistemi ve WooCommerce `customer` rolü kullanılır
- **WooCommerce Hesabım Entegrasyonu**: Müşteriler WooCommerce'in "Hesabım" sayfasını kullanır; eklenti bu sayfaya özel sekmeler ekler
- **Hesap Sekmeleri** (WooCommerce Hesabım içinde):
  - Filtre seçenekleri ile rezervasyon geçmişi
  - Favori araçlar listesi
  - Ödeme geçmişi

**Hesap Shortcode'ları:**
- `[rentiva_user_dashboard]` - Müşteri panosu (giriş durumuna göre içerik değişir)
- `[rentiva_my_bookings]` - Rezervasyon geçmişi
- `[rentiva_my_favorites]` - Favori araçlar
- `[rentiva_payment_history]` - Ödeme işlemleri

> **Not:** Giriş, kayıt ve hesap detayları WooCommerce'in kendi sayfaları üzerinden yönetilir.

**Müşteri Özellikleri:**
- Rezervasyon sırasında otomatik hesap oluşturma
- Şifre sıfırlama işlevi
- Rezervasyon bildirimleri
- E-posta bildirimleri

### 📊 Raporlama ve Analitik

**Admin Rapor Sayfası** *(5 sekme, tarih aralığı filtresi ile)*

- **Genel Bakış (Overview)**: Gelir, rezervasyon, müşteri ve araç verilerini özetleyen birleşik görünüm
- **Gelir Raporu**: Dönem bazlı gelir analizi
- **Rezervasyon Raporu**: Durum dağılımı ve rezervasyon verileri
- **Araç Raporu**: En çok kiralanan araçlar, araç başına gelir, kategori performansı, doluluk oranı
- **Müşteri Raporu**: Müşteri harcamaları, yeni / tekrar eden müşteri ayrımı

**WordPress Dashboard Widget'ları:**
- İstatistik kartları (toplam rezervasyon, aylık gelir, aktif kiralama, doluluk oranı)
- Gelir grafiği (son 30 gün)
- Yaklaşan işlemler listesi

**Analitik Özellikleri:**
- Tarih aralığı bazlı filtreleme
- Araç kategorisi performans karşılaştırması
- Müşteri segmentasyonu (yeni / tekrar eden)
- Rapor önbelleği ve manuel önbellek temizleme

### 📧 E-posta Bildirim Sistemi

**1. Rezervasyon E-postaları:**
- Yeni rezervasyon oluşturuldu (müşteriye)
- Yeni rezervasyon oluşturuldu (admin bildirimi)
- Rezervasyon durumu değişti (müşteriye)
- Ödeme süresi doldu — otomatik iptal bildirimi
- Manuel iptal bildirimi (müşteriye)
- Teslim hatırlatıcısı (pickup reminder)
- Hoşgeldin e-postası (ilk rezervasyon sonrası)

**2. İade E-postaları:**
- İade işlendi bildirimi (`RefundNotifications`)

> **Not:** Hesap oluşturma, şifre sıfırlama gibi hesap e-postaları WooCommerce tarafından yönetilir.

**E-posta Özellikleri:**
- **Özelleştirilebilir**: Admin ayarlarından her şablonun konu ve içeriği değiştirilebilir
- **HTML Şablonlar**: Dinamik placeholder desteği (`{booking_id}`, `{vehicle_title}`, vb.)
- **E-posta Loglama**: Hata ayıklama için tüm e-postalar `EmailLog` post tipiyle loglanır
- **Şablon Sistemi**: Merkezi `Mailer::send()` üzerinden standart gönderim

### 🌍 Uluslararasılaştırma ve Yerelleştirme

**Dil Desteği:**
- **57 Locale**: WordPress locale formatında 57 dil/bölge için destek (`en_US`, `tr_TR`, `de_DE`, `ar` vb.)
- **Merkezi Yönetim**: `LanguageHelper` sınıfı ile birleşik locale yönetimi
- **Otomatik Algılama**: WordPress `get_locale()` fonksiyonunu kullanır
- **JavaScript Locale Dönüşümü**: WordPress locale formatını (`en_US`) JS/API formatına (`en-US`) dönüştürür
- **Çeviriye Hazır**: Tüm metinler `__()` / `_e()` gibi WordPress çeviri fonksiyonlarını kullanır; Loco Translate ile özelleştirilebilir

**Para Birimi Desteği:**
- **47 Para Birimi**: `CurrencyHelper` sınıfında tanımlanmış 47 para birimi sembolü
- **WooCommerce Önceliği**: WooCommerce aktifse para birimi WooCommerce ayarından alınır; değilse eklenti ayarından
- **Para Birimi Sembolleri**: Tüm para birimleri için Unicode sembol desteği
- **Para Birimi Konumu**: Sol / Sağ / Boşluklu + Boşluksuz (WooCommerce `currency_pos` ayarına uyumlu)
- **Genişletilebilir**: `mhm_rentiva_currency_symbols` filter hook ile özel para birimi eklenebilir

**Desteklenen Para Birimleri:**
TRY, USD, EUR, GBP, JPY, CAD, AUD, CHF, CNY, INR, BRL, RUB, KRW, MXN, SGD, HKD, NZD, SEK, NOK, DKK, PLN, CZK, HUF, RON, BGN, HRK, RSD, UAH, BYN, KZT, UZS, KGS, TJS, TMT, AZN, GEL, AMD, AED, SAR, QAR, KWD, BHD, OMR, JOD, LBP, EGP, ILS

### 🔒 Güvenlik Özellikleri

MHM Rentiva, WordPress güvenlik standartlarına (WPCS) tam uyumlu olarak geliştirilmiştir:

- **Veri Temizleme (Sanitization)**: Tüm kullanıcı girdileri `sanitize_text_field()`, `absint()` ve eklentiye özel `Sanitizer::text_field_safe()` yardımcı sınıfı ile temizlenir.
- **Çıktı Güvenliği (Escaping)**: Tüm çıktılar bağlama uygun olarak `esc_html()`, `esc_attr()`, `esc_url()` veya `SecurityHelper::safe_output()` ile kaçırılır (XSS koruması).
- **SQL Enjeksiyon Önleme**: Veritabanı sorguları istisnasız `$wpdb->prepare()` kullanılarak parametize edilir.
- **Nonce Doğrulama**: Tüm form gönderimleri ve AJAX istekleri (`SecurityHelper::verify_ajax_request`) nonce kontrolü ile korunur.
- **Yetki Kontrolü**: Tüm admin işlemleri `current_user_can('manage_options')` ve hassas işlemler için ek yetki kontrolleri içerir.

### 🎁 Ek Hizmet Sistemi

**Ek Hizmet Yönetimi:**
- **Özel Yazı Türü**: Ek hizmetler için `vehicle_addon` özel yazı türü (CPT)
- **Ek Hizmet Özellikleri**:
  - Başlık, açıklama ve fiyat
  - Ek hizmet başına etkinleştir/devre dışı bırak
  - Görüntüleme sırası ayarları
  - Fiyat görüntüleme seçenekleri
  - Çoklu seçim desteği
- **Rezervasyon Entegrasyonu**: Ek hizmetler rezervasyon toplamına otomatik eklenir
- **Toplu Eylemler**: Birden fazla ek hizmeti aynı anda etkinleştir/devre dışı bırak/ekle/kaldır
- **Ek Hizmet Ayarları**: Ek hizmet görüntüleme ve davranışı için genel ayarlar

**Varsayılan Ek Hizmetler** (otomatik olarak oluşturulabilir):
- GPS Navigasyonu
- Çocuk Koltuğu
- Ek Sürücü
- Tam Sigorta
- Ve daha fazlası...

### 📤 Veri Dışa Aktarma Sistemi

**Dışa Aktarma Formatları:**
- **CSV**: Virgülle ayrılmış değerler (`customers-YYYY-AA-GG.csv`)

**Dışa Aktarılabilir Veri:**
- **Rezervasyonlar**: Filtrelerle tüm rezervasyon verileri
- **Araçlar**: Araç envanteri
- **Loglar**: Sistem logları
- **Raporlar**: Analitik veriler

**Dışa Aktarma Özellikleri:**
- **Gelişmiş Filtreleme**: Tarih aralığı, durum, araç, müşteri ile filtreleme
- **Dışa Aktarma Geçmişi**: Tüm dışa aktarmaları takip et
- **Dışa Aktarma İstatistikleri**: Dışa aktarma kullanımını görüntüle
- **Toplu Dışa Aktarma**: Birden fazla veri türünü aynı anda dışa aktar
- **Özel Alanlar**: Belirli alanları dahil et/hariç tut
- **Tarih Aralığı Seçimi**: Esnek tarih filtreleme


## 🚮 Silme Sistemi

**Silme Özellikleri:**
- **Veri Temizleme Seçeneği**: Eklenti silindiğinde tüm verileri kaldırma seçeneği
- **Seçici Temizleme**: Nelerin silineceğini seçme:
  - Araçlar
  - Rezervasyonlar
  - Müşteri verileri
  - Ayarlar
  - Loglar
- **Yedekleme Hatırlatması**: Veri silinmeden önce uyarı
- **Silme Onayı**: Silme işleminden önce onay sayfası

---

## 🚀 Kurulum

### Adım 1: Eklentiyi Yükle

1. Eklenti dosyalarını indirin
2. `/wp-content/plugins/mhm-rentiva/` klasörüne yükleyin
3. WordPress admin panelinden eklentiyi etkinleştirin

### Adım 2: İlk Kurulum

1. **WordPress Admin > Rentiva > Settings** sayfasına gidin
2. Temel ayarları yapılandırın:
   - **Para Birimi**: Varsayılan para biriminizi seçin
   - **Tarih Formatı**: Tercih ettiğiniz tarih formatını ayarlayın
   - **Şirket Bilgileri**: Şirket detaylarınızı ekleyin
   - **E-posta Ayarları**: E-posta gönderen bilgilerini yapılandırın

### Adım 3: Gerekli Sayfaları Oluştur

Eklenti shortcode'lar için sayfaları otomatik olarak oluşturur veya manuel olarak oluşturabilirsiniz:

**Gerekli Sayfalar:**
- Panel sayfası (`[rentiva_user_dashboard]` kullanın - Giriş/Kayıt ve Hesap yönetimi için)
- Rezervasyon Formu sayfası (`[rentiva_booking_form]` kullanın)
- Araç Listesi/Grid sayfası (`[rentiva_vehicles_grid]` veya `[rentiva_vehicles_list]`)

**İsteğe Bağlı Sayfalar:**
- Arama sayfası (`[rentiva_unified_search]` kullanın)
- İletişim sayfası (`[rentiva_contact]` kullanın)

### Adım 4: Ödeme Ağ Geçitlerini Yapılandır

1. **Rentiva > Settings > Payment** sayfasına gidin.
2. Ödeme yöntemlerinizi yapılandırın:
   - **Ödeme**: Para birimi ve konumunu ayarlayın.
   - **WooCommerce**: Online ödemeler için WooCommerce ayarlarını kullanın.
   - **Offline (Manuel)**: Sadece manuel admin rezervasyonları için makbuz yükleme ayarlarını yapılandırın.

### Adım 5: Araç Ekle

1. **Vehicles > Add New** sayfasına gidin.
2. Araç bilgilerini doldurun:
   - Başlık, açıklama, görseller
   - Fiyatlandırma (günlük, haftalık, aylık)
   - Araç özellikleri
   - Özellikler ve ekipmanlar
   - Depozito ayarları
3. Aracı yayınlayın.

### Adım 6: Rezervasyon Akışını Test Et

1. Rezervasyon formu sayfanızı ziyaret edin.
2. Tarihleri ve bir araç seçin.
3. Müşteri bilgilerini doldurun.
4. Test rezervasyonunu tamamlayın.
5. E-posta bildirimlerini doğrulayın.

---

## ⚙ Yapılandırma

### Genel Ayarlar

**Konum**: `Rentiva > Settings > General`

- **Para Birimi**: Varsayılan para birimini seçin (47 para birimi desteklenir)
- **Para Birimi Konumu**: Boşluklu/boşluksuz Sol/Sağ
- **Tarih Formatı**: Tarih görüntüleme formatını özelleştirin
- **Varsayılan Kiralama Günleri**: Minimum kiralama süresi
- **Şirket Bilgileri**: İsim, web sitesi, e-posta, destek e-postası
- **Site URL'leri**: Rezervasyon, giriş, kayıt, hesap URL'leri

### Rezervasyon Ayarları

**Konum**: `Rentiva > Settings > Booking`

- **İptal Son Tarihi**: Rezervasyon başlangıcından önceki saatler (varsayılan: 24)
- **Ödeme Son Tarihi**: Ödemeyi tamamlamak için gereken dakika (varsayılan: 30)
- **Otomatik İptal Etkin**: Ödenmemiş rezervasyonları otomatik iptal et
- **Onay E-postaları Gönder**: Rezervasyon e-postalarını aç/kapat
- **Hatırlatma E-postaları Gönder**: Rezervasyon hatırlatıcılarını etkinleştir
- **Admin Bildirimleri**: Yeni rezervasyonlarda yöneticiyi bilgilendir

### Ödeme Ayarları

**Offline Ödeme Ayarları (Admin Manuel Rezervasyonlar İçin):**

**Kurulum**:
1. `Rentiva > Settings > Payment > Offline` yolunu izleyin.
2. Offline ödemeleri (Makbuz yükleme) etkinleştirin.
3. Makbuz yükleme ayarlarını yapılandırın.
4. Onay süresini belirleyin.

---

## 📖 Kullanım Kılavuzu

### Yöneticiler İçin

#### Araç Ekleme

1. **Araçlar > Yeni Ekle** sayfasına gidin
2. Araç başlığı ve açıklamasını girin
3. WordPress Medya Kütüphanesi kullanarak görseller yükleyin (10'a kadar)
4. Fiyatlandırmayı ayarlayın (günlük)
5. Araç özelliklerini ekleyin
6. Yayınlayın

#### Rezervasyon Yönetimi

1. **Rezervasyonlar** sayfasına gidin
2. Filtreleri kullanarak belirli rezervasyonları bulun
3. Rezervasyona tıklayarak düzenleyin
4. Durumu değiştirin, notlar ekleyin, iadeleri işleyin

---

## 🎯 Shortcode Referansı

Eklenti, esnek yerleşimler için kapsamlı bir shortcode setine sahiptir.

### Rezervasyon & Araç Görüntüleme
- `[rentiva_booking_form]` — Ana rezervasyon formu (ID parametresi alabilir).
- `[rentiva_vehicles_grid]` — Araçları grid (ızgara) görünümünde listeler.
- `[rentiva_vehicles_list]` — Araçları liste görünümünde listeler.
- `[rentiva_featured_vehicles]` — Öne çıkan araçları (slider/grid) gösterir.
- `[rentiva_vehicle_details]` — Tek bir aracın detaylarını gösterir.
- `[rentiva_search_results]` — Arama sonuçları listesi.
- `[rentiva_unified_search]` — Gelişmiş tekil arama formu.
- `[rentiva_availability_calendar]` — Araç müsaitlik takvimi.
- `[rentiva_testimonials]` — Müşteri yorumları slider'ı.
- `[rentiva_vehicle_rating_form]` — Araç değerlendirme formu.

### Müşteri Paneli
- `[rentiva_user_dashboard]` — Müşteri ana panosu.
- `[rentiva_my_bookings]` — Müşterinin mevcut ve geçmiş rezervasyonları.
- `[rentiva_my_favorites]` — Favoriye eklenen araçlar listesi.
- `[rentiva_payment_history]` — Ödeme geçmişi ve makbuz detayları.

---

## 🔌 REST API Dokümantasyonu

### Temel URL (Base URL)
```
/wp-json/mhm-rentiva/v1
```

### Kimlik Doğrulama (Auth)
REST API, `AuthHelper` sınıfı üzerinden yönetilen çok katmanlı bir güvenlik yapısına sahiptir:
- **X-WP-Nonce**: Oturum açmış kullanıcılar için standart WordPress nonce doğrulaması.
- **Secure Tokens**: `SecureToken::create_customer_token` ile oluşturulan, zaman aşımına sahip güvenli müşteri belirteçleri.
- **API Keys**: Üçüncü taraf entegrasyonlar için `Rentiva > Ayarlar` üzerinden yönetilen anahtarlar.

### Hız Sınırlama (Rate Limiting)
`RateLimiter` sistemi ile Brute Force saldırılarına karşı korunmaktadır:
- **Genel Limit**: Dakikada 60 istek (Varsayılan).
- **Hassas İşlemler**: Rezervasyon oluşturma ve ödeme gibi işlemler için daha katı sınırlamalar uygulanır.
- **Headers**: Yanıtlarda `X-RateLimit-*` başlıkları ile kalan kota bilgisi döner.

### Başlıca Endpoint'ler
- `GET /vehicles` — Araç listeleme ve filtreleme.
- `GET /availability` — Belirli tarihler için araç müsaitlik kontrolü.
- `POST /bookings` — Yeni rezervasyon oluşturma.
- `GET /locations` — Aktif kiralama lokasyonları listesi.
- `GET /orders` — Müşteri sipariş detayları.

---

## 📁 Proje Yapısı

```text
mhm-rentiva/
├── assets/                 # CSS, JS, Grafikler (Minify edilmiş)
├── docs/                   # Teknik dokümantasyon ve API kılavuzları
├── languages/              # Dil dosyaları (.pot, .po, .mo)
├── src/                    # PSR-4 Çekirdek PHP (MHMRentiva\*)
│   ├── Admin/              # Yönetim Paneli Kontrolcüleri ve Servisler
│   ├── Api/                # Özel REST API Uç Noktaları
│   ├── Blocks/             # Gutenberg Blok tanımlamaları
│   ├── CLI/                # WP-CLI Komutları
│   ├── Core/               # Finansal motor ve Temel Servisler
│   ├── Helpers/            # Yardımcı ve Temizleme sınıfları
│   ├── Integrations/       # Dış dünya köprüleri (WooCommerce vb.)
│   └── Plugin.php          # Ana başlatıcı sınıf
├── templates/              # Ön yüz ve E-posta şablonları
├── mhm-rentiva.php         # Ana giriş dosyası
└── uninstall.php           # Silme işlemi temizlik dosyası
```

---

## 📋 Gereksinimler

### WordPress & PHP
- **Minimum WordPress**: 6.7
- **Test Edilen**: 7.0
- **Minimum PHP**: 8.1 (Önerilen: 8.2+)
- **Bellek Limiti**: Minimum 128MB (256MB önerilir)

### Gerekli Uzantılar
- `json` — API ve ayarlar için
- `curl` — Lisans ve dış entegrasyonlar için
- `mbstring` — Çoklu dil desteği için
- `openssl` — Güvenli veri şifreleme için
- `imagick` veya `gd` — Araç görselleri için

### Bağımlılıklar
- **WooCommerce**: Aktif olmalıdır (Ön yüz ödemeleri ve müşteri yönetimi için).
- **Veritabanı**: MySQL 5.7+ veya MariaDB 10.3+.

---

## 🛠 Geliştirme

### Kod Standartları
- **PSR-4 Autoloading**: `MHMRentiva\*` namespace yapısı.
- **Strict Types**: Tüm dosyalarda `declare(strict_types=1);` zorunluluğu.
- **Prefixing**: Fonksiyonlar için `mhm_rentiva_`, sınıflar için `MHMRentiva` prefixi.
- **Güvenlik**: Raw SQL yasaktır, her zaman `$wpdb->prepare()` kullanılır.

### 🧪 Otomatik Test Süiti
- **PHPUnit**: 868 test / 2.823 doğrulama (v5.0.0 — son stabil sürüm).
- **CI Matrisi**: PHP 8.1 / 8.2 / 8.3 × WP 6.7 / latest = 6 paralel iş.
- **PHPCS**: Tam WordPress Coding Standards uyumluluğu (0 hata).
- **Test Yönetim Sayfası**: Rentiva menüsünden erişilebilir, raporlar indirilebilir.
- **Belgelenmiş Baseline**: 7 hata (saas_block ortam kotası — kapsam dışı, kasıtlı), 15 atlanan test.

### ⚓ Geliştirici Kancaları (Hooks)

#### Önemli Filtreler (Filters)
- `mhm_rentiva_currency_symbols` — Desteklenen para birimi sembollerini değiştirir.
- `mhm_rentiva_attribute_registry` — Araç özellik listesini genişletir.

#### Önemli Aksiyonlar (Actions)
- `mhm_rentiva_booking_created` — Yeni rezervasyon oluşturulduğunda tetiklenir.
- `mhm_rentiva_booking_status_changed` — Rezervasyon durumu değiştiğinde tetiklenir.
- `mhm_rentiva_email_sent` — Sistem tarafından bir e-posta gönderildiğinde tetiklenir.

---

## ⚛ Modern React Admin Arayüzü

Tüm büyük admin sayfaları eski jQuery/WP_List_Table altyapısından REST API destekli React SPA'larına geçirildi. Geçiş v4.49.0 itibarıyla tamamlandı.

**Geçirilen Sayfalar:**

| Sayfa | Sürüm | React Bileşenleri | REST Uç Noktaları |
| :--- | :---: | :--- | :--- |
| **Dashboard** | v4.36.0 | DashboardPage, StatsCards, RecentBookings, QuickActions | `/mhm-rentiva/v1/dashboard/*` |
| **Müşteriler** | v4.39.0 | CustomerTable, CustomerPanel, SearchBar, FilterBar, Pagination | `/mhm-rentiva/v1/customers`, `/customers/{id}`, `/customers/bulk` |
| **Shortcode Sayfaları** | v4.49.0 | ShortcodePagesPage, ShortcodeTable, StatusBadge | `/mhm-rentiva/v1/shortcode-pages/*` |
| **Hakkında** | v5.0.0 | AboutPage, TabNav, GeneralTab, SystemTab, SupportTab, DeveloperTab | `/mhm-rentiva/v1/about` |

**Mimari Öne Çıkanlar:**
- **REST API Önce**: Tüm veriler kimlik doğrulamalı WP REST API uç noktaları üzerinden alınır (manage_options yetkisi)
- **Paylaşılan Bileşen Kütüphanesi**: `shared/admin.css` — stats grid, KPI kutuları, durum rozetleri, sayfalama tüm sayfalarda ortaklaştırıldı
- **jQuery Bağımlılığı Yok**: Tüm sayfalar React 18 hook'ları, fetch API ve wp.i18n kullanır
- **Mobil Duyarlı**: Tüm admin sayfaları WP admin breakpoint'lerinde (782px / 480px) tam duyarlı
- **WP Flash Deseni**: Eylem sonrası bildirimler `wp_localize_script` flash anahtarı ile iletilir (React yüklenmeden önce `common.js` tarafından silinen URL parametreleri değil)
- **Build Pipeline**: Webpack + `npm run build` ile `src-react/` → `build/admin/` sayfa başına derlenir

---

## 🤝 Katkıda Bulunma

Katkılarınızı bekliyoruz! Lütfen şu yönergeleri izleyin:

1. **Depoyu fork edin**
2. **Özellik dalı oluşturun**: `git checkout -b feature/YeniOzellik`
3. **Kod standartlarına uyun**: WordPress Kodlama Standartları
4. **Net commit mesajları yazın**: Conventional commits kullanın
5. **Kapsamlı test yapın**: Tüm işlevleri test edin
6. **Pull request gönderin**: Değişikliklerin açıklamasını ekleyin

---

## 📝 Değişiklik Geçmişi (Changelog)

Tüm sürüm geçmişi [`changelog.json`](changelog.json) (İngilizce) ve [`changelog-tr.json`](changelog-tr.json) (Türkçe) dosyalarında tutulur ve [mhm-rentiva-docs/blog](https://maxhandmade.github.io/mhm-rentiva-docs/blog) sitesinde blog yazıları olarak yayınlanır.

Asset'leri içeren GitHub yayınları için [Releases](https://github.com/MaxHandMade/mhm-rentiva/releases) sayfasına bakın.

---

## 📄 Lisans

Bu proje **GPL-2.0+** lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakın.

---

## 👨‍💻 Geliştirici

**MaxHandMade**
- Website: [wpalemi.com](https://wpalemi.com)
- Destek: support@wpalemi.com

---

## 📞 Destek

Sorular, sorunlar veya özellik istekleri için:
- **E-posta**: support@wpalemi.com
- **Website**: https://wpalemi.com

---

## ⭐ Projeyi Yıldızlayın

Bu eklentiyi faydalı bulursanız, lütfen GitHub'da yıldız vermeyi düşünün!

---

**WordPress topluluğu için ❤️ ile yapıldı**




