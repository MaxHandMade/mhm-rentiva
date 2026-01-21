# MHM Rentiva - WordPress Araç Kiralama Eklentisi

<div align="right">

**🌐 Dil / Language:** 
[![TR](https://img.shields.io/badge/Dil-Türkçe-red)](README-tr.md) 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![Değişiklikler](https://img.shields.io/badge/Değişiklikler-TR-orange)](changelog-tr.json) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json)

</div>

![Version](https://img.shields.io/badge/version-4.6.2-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

**WordPress için profesyonel araç kiralama yönetim sistemi.** Araç kiralama, rezervasyon, ödeme, müşteri yönetimi ve kapsamlı raporlama için eksiksiz, kurumsal düzeyde bir çözüm. WordPress en iyi uygulamalarıyla geliştirilmiş, tam uluslararasılaştırma desteği ile küresel pazarlara hazır.

---

## 📋 İçindekiler

- [Genel Bakış](#genel-bakış)
- [Temel Özellikler](#temel-özellikler)
- [Kurulum](#kurulum)
- [Yapılandırma](#yapılandırma)
- [Kullanım Kılavuzu](#kullanım-kılavuzu)
- [Shortcode Referansı](#shortcode-referansı)
- [REST API Dokümantasyonu](#rest-api-dokümantasyonu)
- [Ödeme Ağ Geçitleri](#ödeme-ağ-geçitleri)
- [Proje Yapısı](#proje-yapısı)
- [Gereksinimler](#gereksinimler)
- [Geliştirme](#geliştirme)
- [Katkıda Bulunma](#katkıda-bulunma)
- [Değişiklik Geçmişi](#değişiklik-geçmişi)
- [Lisans](#lisans)

---

## 🎯 Genel Bakış

MHM Rentiva, araç kiralama işletmeleri için tasarlanmış kapsamlı bir WordPress eklentisidir. Araba kiralama şirketi, bisiklet/motosiklet kiralama hizmeti veya herhangi bir araç tabanlı kiralama işletmesi yönetiyorsanız, bu eklenti operasyonlarınızı verimli bir şekilde yönetmek için ihtiyacınız olan her şeyi sağlar.

### Bu Eklenti Ne Yapar?

- **Araç Yönetimi**: Galeri, kategoriler, fiyatlandırma ve müsaitlik ile eksiksiz araç envanter yönetimi
- **Rezervasyon Sistemi**: Gerçek zamanlı müsaitlik kontrolü, rezervasyon yönetimi ve otomatik iptal
- **Ödeme İşleme**: Tüm frontend rezervasyonları için WooCommerce entegrasyonu ile güvenli ödeme işlemleri
- **Müşteri Portalı**: Rezervasyon geçmişi, favoriler ve mesajlaşma ile tam özellikli müşteri hesap sistemi
- **Analitik ve Raporlama**: Gelir, müşteri ve araç içgörüleri ile kapsamlı analitik dashboard
- **E-posta Sistemi**: Özelleştirilebilir HTML şablonları ile otomatik e-posta bildirimleri
- **Mesajlaşma Sistemi**: Thread yönetimi ile yerleşik müşteri destek mesajlaşması
- **VIP Transfer Modülü**: Mesafe tabanlı fiyatlandırma ve araç seçimi ile noktadan noktaya rezervasyon sistemi
- **REST API**: Üçüncü taraf entegrasyonları ve mobil uygulamalar için eksiksiz REST API

### Bu Eklenti Kimler İçin?

- **Araba Kiralama Şirketleri**: Filo, rezervasyon ve müşteri ilişkilerini yönetin
- **Bisiklet/Motosiklet Kiralama**: Müsaitliği takip edin ve ödemeleri işleyin
- **Ekipman Kiralama İşletmeleri**: Her türlü araç veya ekipmanı kiralayın
- **Çok Lokasyonlu Kiralama**: Birden fazla lokasyon ve para birimi desteği
- **Küresel İşletmeler**: 60+ dil ve 47 para birimi ile tam uluslararasılaştırma

---

## ✨ Temel Özellikler

### 🚗 Araç Yönetim Sistemi

**Temel Araç Özellikleri:**
- **Özel Post Tipi**: Araçlar için yerel WordPress post tipi
- **Araç Galerisi**: WordPress Medya Kütüphanesi kullanarak araç başına 10'a kadar görsel yükleme
- **Sürükle-Bırak Sıralama**: Sezgisel sürükle-bırak arayüzü ile araç görsellerini yeniden sıralama
- **Araç Kategorileri**: Araçları organize etmek için hiyerarşik taksonomi sistemi
- **Araç Meta Verileri**: 
  - Günlük, haftalık, aylık fiyatlandırma
  - Araç özellikleri (marka, model, yıl, yakıt tipi, şanzıman, vb.)
  - Özellik ve ekipman listeleri
  - Depozito ayarları (sabit veya yüzde)
  - Müsaitlik durumu
  - Öne çıkan araç seçeneği
- **Hızlı Düzenleme**: Liste tablosundan araçları toplu düzenleme
- **Arama ve Filtreleme**: Kategori, durum ve fiyat aralığına göre gelişmiş filtreleme
- **Araç Karşılaştırma**: Birden fazla aracı yan yana karşılaştırma

**Araç Görüntüleme Seçenekleri:**
- Özelleştirilebilir sütunlara sahip grid görünümü
- Detaylı bilgi içeren liste görünümü
- Tek araç detay sayfaları
- Gelişmiş filtrelerle arama sonuçları
- Müsaitlik takvimi entegrasyonu

### 📅 Rezervasyon Sistemi

**Rezervasyon Yönetimi:**
- **Gerçek Zamanlı Müsaitlik**: Otomatik çakışma tespiti ve önleme
- **Veritabanı Kilitleme**: Satır düzeyinde kilitleme ile çift rezervasyonu önler
- **Rezervasyon Durumları**: 
  - Beklemede (ödeme bekleniyor)
  - Onaylandı (ödeme alındı)
  - Aktif (şu anda kiralanmış)
  - Tamamlandı (iade edildi)
  - İptal edildi
  - İade edildi
- **Otomatik İptal**: Ödenmemiş rezervasyonlar için yapılandırılabilir otomatik iptal (varsayılan: 30 dakika)
- **Manuel Rezervasyonlar**: Yönetici, doğrudan yönetim panelinden rezervasyon oluşturabilir
- **Rezervasyon Takvimi**: Tüm rezervasyonların görsel takvim görünümü
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
- **VIP Transfer Modülü Entegrasyonu**: Şoförlü hizmetlerin sorunsuz yönetimi

### 💳 Ödeme Sistemi

**1. Frontend (Müşteri) Ödemeleri (WooCommerce ile)**
- **WooCommerce Entegrasyonu**: Tüm frontend rezervasyonları WooCommerce üzerinden güvenle işlenir.
- **Ödeme Yöntemleri**: WooCommerce tarafından desteklenen tüm yöntemleri (Kredi Kartı, Banka Havalesi, PayPal, Kapıda Ödeme, vb.) kabul edin.
- **Otomatik Durum Güncellemeleri**: Rezervasyon durumları, WooCommerce sipariş durumuna göre otomatik güncellenir.

**2. Manuel Ödemeler (Sadece Yönetici)**
- **Offline Ödeme**: Yöneticiler, arka uçtan oluşturulan manuel rezervasyonlar için ödemeleri (Nakit/Havale) manuel olarak kaydedebilir.
- **Makbuz Yönetimi**: Yöneticiler manuel rezervasyonlara ödeme kanıtı ekleyebilir.

**Ödeme Özellikleri:**
- Rezervasyon başına çoklu ödeme yöntemi
- Kısmi ödeme desteği (Depozito sistemi)
- WooCommerce üzerinden iade yönetimi
- Ödeme durumu takibi
- Güvenli işlem yönetimi

### 👥 Müşteri Yönetimi

**Müşteri Hesap Sistemi:**
- **WordPress Yerel Entegrasyonu**: Standart WordPress kullanıcı sistemini kullanır
- **Müşteri Rolü**: WordPress "Customer" rolünün otomatik atanması
- **Hesabım Dashboard**: WooCommerce benzeri hesap yönetim arayüzü
- **Hesap Özellikleri**:
  - İstatistikli dashboard
  - Filtre seçenekleri ile rezervasyon geçmişi
  - Favori araçlar listesi
  - Ödeme geçmişi
  - Hesap detaylarını düzenleme
  - Şifre yönetimi
  - Mesaj merkezi

**Müşteri Portalı Shortcode'ları:**
- `[rentiva_my_bookings]` - Rezervasyon geçmişi
- `[rentiva_my_favorites]` - Favori araçlar
- `[rentiva_payment_history]` - Ödeme işlemleri
- `[rentiva_account_details]` - Profil düzenleme
- `[rentiva_login_form]` - Giriş formu
- `[rentiva_register_form]` - Kayıt formu

**Müşteri Özellikleri:**
- Rezervasyon sırasında otomatik hesap oluşturma
- İsimden kullanıcı adı oluşturma (e-posta yerine)
- E-posta doğrulama
- Şifre sıfırlama işlevi
- Rezervasyon bildirimleri
- E-posta bildirimleri
- Mesaj bildirimleri

### 📊 Raporlama ve Analitik

**Analitik Dashboard:**
- **Gelir Analitiği**: 
  - Toplam gelir
  - Dönem bazlı gelir (günlük, haftalık, aylık, yıllık)
  - Araç bazlı gelir
  - Ödeme yöntemi dağılımı
- **Rezervasyon Analitiği**:
  - Toplam rezervasyonlar
  - Rezervasyon durum dağılımı
  - Rezervasyon trendleri
  - En yoğun rezervasyon dönemleri
- **Araç Analitiği**:
  - En çok kiralanan araçlar
  - Araç kullanım oranları
  - Araç başına gelir
  - Müsaitlik istatistikleri
- **Müşteri Analitiği**:
  - Toplam müşteri sayısı
  - Müşteri segmentasyonu
  - Müşteri yaşam döngüsü analizi
  - Tekrar eden müşteri oranı
  - Müşteri kazanım trendleri

**Rapor Özellikleri:**
- Gerçek zamanlı veri güncellemeleri
- Özel tarih aralığı seçimi
- Excel/CSV formatında dışa aktarma
- Görsel grafikler ve şemalar
- Mobil uyumlu tasarım
- Yazdırma dostu görünümler

### 📧 E-posta Bildirim Sistemi

**E-posta Şablonları:**
1. **Rezervasyon E-postaları**:
   - Rezervasyon oluşturuldu (müşteri)
   - Rezervasyon oluşturuldu (admin)
   - Rezervasyon iptal edildi
   - Rezervasyon durumu değişti
   - Rezervasyon hatırlatıcı

2. **Ödeme E-postaları**:
   - Ödeme alındı
   - Makbuz yüklendi (admin bildirimi)
   - Makbuz onaylandı (müşteri)
   - Makbuz reddedildi (müşteri)
   - İade işlendi

3. **Hesap E-postaları**:
   - Hoşgeldin e-postası
   - Hesap oluşturuldu
   - Şifre sıfırlama

4. **Mesaj E-postaları**:
   - Yeni mesaj alındı (admin)
   - Mesaj yanıtlandı (müşteri)
   - Mesaj durumu değişti

**E-posta Özellikleri:**
- **Modern HTML Şablonlar**: Responsive tasarım, tüm e-posta istemcilerinde çalışır
- **Özelleştirilebilir**: Admin ayarlardan konu ve içeriği değiştirebilir
- **Çoklu Dil**: Birden fazla dil desteği
- **Şablon Sistemi**: Şablon geçersiz kılma (override) ile kolay özelleştirme
- **E-posta Loglama**: Hata ayıklama için tüm e-postalar loglanır

### 💬 Mesajlaşma Sistemi

**Mesaj Özellikleri:**
- **Konu Tabanlı İletişim**: Konuşmalar konular (thread) halinde organize edilir
- **Mesaj Kategorileri**: Genel, Rezervasyon, Ödeme, Teknik Destek, Şikayet, Öneri
- **Mesaj Durumları**: Beklemede, Yanıtlandı, Kapalı, Acil
- **Öncelik Seviyeleri**: Normal, Yüksek, Acil
- **Admin Arayüzü**: WordPress yönetim panelinde tam mesaj yönetimi
- **Müşteri Arayüzü**: Müşteriler için frontend mesaj merkezi
- **E-posta Bildirimleri**: Yeni mesajlar için otomatik e-posta bildirimleri
- **REST API**: Mesaj operasyonları için tam REST API

**Mesaj Yönetimi:**
- Admin panelinde tüm mesajları görüntüleme
- Müşteri mesajlarına yanıt verme
- Mesaj durumunu değiştirme
- Öncelik atama
- Toplu işlemler (silme, okundu olarak işaretleme)
- Mesaj arama ve filtreleme
- Mesaj istatistikleri

### 🚐 VIP Transfer Modülü (Şoförlü Hizmet)

**Temel Transfer Özellikleri:**
- **Noktadan Noktaya Rezervasyon**: Önceden tanımlanmış bölgelerden alış ve bırakış konumları seçimi.
- **Mesafe Bazlı Fiyatlandırma**: Rota mesafesine veya sabit bölgeden bölgeye oranlara göre maliyet hesaplama.
- **Araç Seçimi**: Farklı kapasitelere sahip transfer hizmetleri için özel araç atama.
- **Buffer/Hazırlık Süresi**: Rezervasyonlar arasında araç hazırlığını sağlamak için operasyonel tampon süresi.
- **AJAX Arama**: Gerçek zamanlı sonuçlar içeren modern transfer arama arayüzü.
- **WooCommerce Entegrasyonu**: Transfer rezervasyonlarını sepete sorunsuz ekleme (Depozito veya Tam Ödeme).
- **Frontend Takibi**: Müşteriler transfer detaylarını "Hesabım" alanında görüntüleyebilir.

**Transfer Görüntüleme Seçenekleri:**
- Özel arama shortcode'u: `[mhm_rentiva_transfer_search]`
- Müşteri hesabında transfer detay görünümü
- Admin transfer yönetim paneli

### 🌍 Uluslararasılaştırma ve Yerelleştirme

**Dil Desteği:**
- **60+ Dil**: 60+ WordPress locale için tam destek
- **Merkezi Yönetim**: Birleşik dil yönetimi için `LanguageHelper` sınıfı
- **Otomatik Algılama**: WordPress locale ayarını kullanır
- **JavaScript Yerelleştirme**: JavaScript tarih/saat kütüphaneleri için locale dönüşümü
- **Çeviriye Hazır**: Tüm metinler WordPress çeviri fonksiyonlarını kullanır

**Para Birimi Desteği:**
- **47 Para Birimi**: 47 farklı para birimi desteği
- **Merkezi Yönetim**: Birleşik para birimi yönetimi için `CurrencyHelper` sınıfı
- **Para Birimi Sembolleri**: Tüm para birimleri için doğru sembol gösterimi
- **Para Birimi Konumu**: Yapılandırılabilir sembol konumu (sol/sağ, boşluklu/boşluksuz)
- **Desteklenen Ağ Geçitleri**: Tüm WooCommerce Ağ Geçitleri (Frontend), Yerel Offline (Yönetici Manuel Sadece)

**Desteklenen Para Birimleri:**
TRY, USD, EUR, GBP, JPY, CAD, AUD, CHF, CNY, INR, BRL, RUB, KRW, MXN, SGD, HKD, NZD, SEK, NOK, DKK, PLN, CZK, HUF, RON, BGN, HRK, RSD, UAH, BYN, KZT, UZS, KGS, TJS, TMT, AZN, GEL, AMD, AED, SAR, QAR, KWD, BHD, OMR, JOD, LBP, EGP, ILS

### 🔒 Güvenlik Özellikleri

**Güvenlik Önlemleri:**
- **XSS Koruması**: Tüm çıktılar uygun şekilde escaped edilir
- **SQL Enjeksiyon Önleme**: Tüm veritabanı sorguları için prepared statements kullanılır

---

## 🏗️ Lisans Yönetimi

**Lisans Sayfası Konumu**: `Rentiva > License`

**Lisans Durumu Görüntüleme:**
- Pro Lisans Aktif (yeşil rozet)
- Lite Sürüm (sarı rozet)
- Geliştirici Modu (bilgi rozeti)
- Lisans sona erme uyarıları
- Son doğrulama zaman damgası

### 💻 Geliştirici Modu

**Otomatik Geliştirici Modu:**
- **Amaç**: Geliştirme ortamında Pro özellikleri otomatik olarak etkinleştirir
- **Güvenlik**: Sadece localhost/geliştirme alan adlarında çalışır
- **Algılama**: Güvenilir algılama için çoklu kriterler
- **Lisans Gerekmez**: Geliştirme ortamında lisans anahtarına ihtiyaç duyulmaz

**Geliştirici Modu Özellikleri:**
- Tüm Pro özellikleri etkinleştirilir
- Miktar sınırı yok
- Tüm ödeme ağ geçitleri kullanılabilir (WooCommerce ile)
- Tüm dışa aktarma formatları kullanılabilir
- Tam mesajlaşma sistemi
- Gelişmiş raporlar etkin

---

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
- Hesabım sayfası (`[rentiva_my_account]` kullanın)
- Rezervasyon Formu sayfası (`[rentiva_booking_form]` kullanın)
- Araç Listesi/Grid sayfası (`[rentiva_vehicles_grid]` veya `[rentiva_vehicles_list]`)

**İsteğe Bağlı Sayfalar:**
- Arama sayfası (`[rentiva_search]` kullanın)
- İletişim sayfası (`[rentiva_contact]` kullanın)
- Giriş sayfası (`[rentiva_login_form]` kullanın)
- Kayıt sayfası (`[rentiva_register_form]` kullanın)
- VIP Transfer Arama (`[mhm_rentiva_transfer_search]` kullanın)

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

## ⚙️ Yapılandırma

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
4. Fiyatlandırmayı ayarlayın (günlük, haftalık, aylık)
5. Araç özelliklerini ekleyin
6. Yayınlayın

#### Rezervasyon Yönetimi

1. **Rezervasyonlar** sayfasına gidin
2. Filtreleri kullanarak belirli rezervasyonları bulun
3. Rezervasyona tıklayarak düzenleyin
4. Durumu değiştirin, notlar ekleyin, iadeleri işleyin

---

## 🎯 Shortcode Referansı

### Hesap Yönetimi Shortcode'ları

### Hesap Yönetimi Shortcode'ları

#### `[rentiva_my_bookings]`
**Amaç**: Müşteri rezervasyon geçmişini göster

#### `[rentiva_booking_form]`
**Amaç**: Araç kiralama için ana rezervasyon formu

**Kullanım**:
```php
[rentiva_booking_form vehicle_id="123"]
```

#### `[rentiva_my_favorites]`
**Amaç**: Müşteri favori araç listesini göster

**Kullanım**:
```php
[rentiva_my_favorites columns="3" limit="12"]
```



#### `[rentiva_vehicles_grid]`
**Amaç**: Araçları grid düzeninde göster

**Kullanım**:
```php
[rentiva_vehicles_grid columns="3" limit="12"]
```

#### `[mhm_rentiva_transfer_search]`
**Amaç**: VIP Transfer ve şoförlü araç arama formu.

---

## 🔌 REST API Dokümantasyonu

### Base URL

```
/wp-json/mhm-rentiva/v1
```

### Kimlik Doğrulama

REST API, API anahtarları ile kimlik doğrulama gerektirir. API anahtarlarını şuradan oluşturun:
**Rentiva > Settings > Integration > REST API > API Keys**

### Mevcut Endpoint'ler

#### Müsaitlik

**Araç Müsaitliğini Kontrol Et**
```
GET /availability
```

#### Rezervasyonlar

**Rezervasyon Oluştur**
```
POST /bookings
```

---

## 📁 Proje Yapısı

```text
mhm-rentiva/
├── changelog.json                 # Sürüm geçmişi (İngilizce)
├── changelog-tr.json              # Sürüm geçmişi (Türkçe)
├── LICENSE                        # GPL Lisans bilgisi
├── mhm-rentiva.php                # Ana giriş dosyası
├── readme.txt                     # WordPress.org meta verileri
├── README.md                      # Dokümantasyon (İngilizce)
├── README-tr.md                   # Dokümantasyon (Türkçe)
├── uninstall.php                  # Silme işlemi temizlik mantığı
├── assets/
│   ├── css/
│   │   ├── admin/
│   │   │   ├── about.css
│   │   │   ├── addon-admin.css
│   │   │   ├── addon-list.css
│   │   │   ├── admin-reports.css
│   │   │   ├── booking-calendar.css
│   │   │   ├── booking-edit-meta.css
│   │   │   ├── booking-list.css
│   │   │   ├── booking-meta.css
│   │   │   ├── customers.css
│   │   │   ├── dark-mode.css
│   │   │   ├── dashboard-tooltips.css
│   │   │   ├── dashboard.css
│   │   │   ├── database-cleanup.css
│   │   │   ├── deposit-management.css
│   │   │   ├── elementor-editor.css
│   │   │   ├── email-templates.css
│   │   │   ├── export.css
│   │   │   ├── gutenberg-blocks-editor.css
│   │   │   ├── log-metabox.css
│   │   │   ├── manual-booking-meta.css
│   │   │   ├── message-list.css
│   │   │   ├── messages-admin.css
│   │   │   ├── messages-settings.css
│   │   │   ├── monitoring.css
│   │   │   ├── reports-stats.css
│   │   │   ├── rest-api-keys.css
│   │   │   ├── settings-testing.css
│   │   │   ├── settings.css
│   │   │   ├── test-suite.css
│   │   │   ├── vehicle-card-fields.css
│   │   │   └── vehicle-gallery.css
│   │   ├── components/
│   │   │   ├── addon-booking.css
│   │   │   ├── calendars.css
│   │   │   ├── simple-calendars.css
│   │   │   ├── stats-cards.css
│   │   │   └── vehicle-meta.css
│   │   ├── core/
│   │   │   ├── animations.css
│   │   │   ├── core.css
│   │   │   ├── css-variables.css
│   │   │   └── ux-notifications.css
│   │   ├── frontend/
│   │   │   ├── availability-calendar.css
│   │   │   ├── booking-confirmation.css
│   │   │   ├── booking-detail.css
│   │   │   ├── booking-form.css
│   │   │   ├── bookings-page.css
│   │   │   ├── contact-form.css
│   │   │   ├── customer-messages-standalone.css
│   │   │   ├── customer-messages.css
│   │   │   ├── deposit-system.css
│   │   │   ├── elementor-widgets.css
│   │   │   ├── gutenberg-blocks.css
│   │   │   ├── integrated-account.css
│   │   │   ├── my-account.css
│   │   │   ├── search-results.css
│   │   │   ├── testimonials.css
│   │   │   ├── vehicle-comparison.css
│   │   │   ├── vehicle-details.css
│   │   │   ├── vehicle-rating-form.css
│   │   │   ├── vehicle-search-compact.css
│   │   │   ├── vehicle-search.css
│   │   │   ├── vehicles-grid.css
│   │   │   └── vehicles-list.css
│   │   ├── payment/
│   │   │   └── woocommerce-checkout.css
│   │   └── transfer.css
│   ├── images/
│   │   ├── mhm-logo.png
│   │   └── placeholder-avatar.svg
│   └── js/
│       ├── admin/
│       │   ├── about.js
│       │   ├── addon-admin.js
│       │   ├── addon-list.js
│       │   ├── addon-settings.js
│       │   ├── booking-bulk-actions.js
│       │   ├── booking-calendar.js
│       │   ├── booking-edit-meta.js
│       │   ├── booking-email-send.js
│       │   ├── booking-filters.js
│       │   ├── booking-list-filters.js
│       │   ├── booking-meta.js
│       │   ├── cron-monitor.js
│       │   ├── customers-calendar.js
│       │   ├── customers.js
│       │   ├── dark-mode.js
│       │   ├── dashboard.js
│       │   ├── database-cleanup.js
│       │   ├── deposit-management.js
│       │   ├── elementor-editor.js
│       │   ├── email-templates.js
│       │   ├── export.js
│       │   ├── gutenberg-blocks.js
│       │   ├── log-metabox.js
│       │   ├── manual-booking-meta.js
│       │   ├── message-list.js
│       │   ├── messages-admin.js
│       │   ├── messages-settings.js
│       │   ├── monitoring.js
│       │   ├── reports-charts.js
│       │   ├── reports.js
│       │   ├── rest-api-keys.js
│       │   ├── settings-form-handler.js
│       │   ├── settings.js
│       │   ├── uninstall.js
│       │   ├── vehicle-card-fields.js
│       │   └── vehicle-gallery.js
│       ├── components/
│       │   ├── addon-booking.js
│       │   ├── vehicle-meta.js
│       │   └── vehicle-quick-edit.js
│       ├── core/
│       │   ├── admin-notices.js
│       │   ├── charts.js
│       │   ├── core.js
│       │   ├── i18n.js
│       │   ├── module-loader.js
│       │   ├── performance.js
│       │   └── utilities.js
│       ├── frontend/
│       │   ├── account-messages.js
│       │   ├── account-privacy.js
│       │   ├── availability-calendar.js
│       │   ├── booking-cancellation.js
│       │   ├── booking-confirmation.js
│       │   ├── booking-form.js
│       │   ├── contact-form.js
│       │   ├── customer-messages.js
│       │   ├── elementor-widgets.js
│       │   ├── my-account.js
│       │   ├── privacy-controls.js
│       │   ├── search-results.js
│       │   ├── testimonials.js
│       │   ├── vehicle-comparison.js
│       │   ├── vehicle-details.js
│       │   ├── vehicle-rating-form.js
│       │   ├── vehicle-search-compact.js
│       │   ├── vehicle-search.js
│       │   ├── vehicles-grid.js
│       │   └── vehicles-list.js
│       ├── vendor/
│       │   └── chart.min.js
│       └── mhm-rentiva-transfer.js
├── languages/
│   ├── mhm-rentiva.pot
│   ├── mhm-rentiva-tr_TR.mo
│   └── mhm-rentiva-tr_TR.po
├── src/
│   ├── Admin/
│   │   ├── About/
│   │   │   ├── Tabs/
│   │   │   │   ├── DeveloperTab.php
│   │   │   │   ├── FeaturesTab.php
│   │   │   │   ├── GeneralTab.php
│   │   │   │   ├── SupportTab.php
│   │   │   │   └── SystemTab.php
│   │   │   ├── About.php
│   │   │   ├── Helpers.php
│   │   │   └── SystemInfo.php
│   │   ├── Actions/
│   │   │   └── Actions.php
│   │   ├── Addons/
│   │   │   ├── AddonListTable.php
│   │   │   ├── AddonManager.php
│   │   │   ├── AddonMenu.php
│   │   │   ├── AddonMeta.php
│   │   │   ├── AddonPostType.php
│   │   │   └── AddonSettings.php
│   │   ├── Auth/
│   │   │   ├── LockoutManager.php
│   │   │   ├── SessionManager.php
│   │   │   └── TwoFactorManager.php
│   │   ├── Booking/
│   │   │   ├── Actions/
│   │   │   │   └── DepositManagementAjax.php
│   │   │   ├── Addons/
│   │   │   │   └── AddonBooking.php
│   │   │   ├── Core/
│   │   │   │   ├── Handler.php
│   │   │   │   ├── Hooks.php
│   │   │   │   └── Status.php
│   │   │   ├── Exceptions/
│   │   │   │   └── BookingException.php
│   │   │   ├── Helpers/
│   │   │   │   ├── Cache.php
│   │   │   │   ├── CancellationHandler.php
│   │   │   │   ├── Locker.php
│   │   │   │   └── Util.php
│   │   │   ├── ListTable/
│   │   │   │   └── BookingColumns.php
│   │   │   ├── Meta/
│   │   │   │   ├── BookingDepositMetaBox.php
│   │   │   │   ├── BookingEditMetaBox.php
│   │   │   │   ├── BookingMeta.php
│   │   │   │   ├── BookingPortalMetaBox.php
│   │   │   │   ├── BookingRefundMetaBox.php
│   │   │   │   └── ManualBookingMetaBox.php
│   │   │   └── PostType/
│   │   │       └── Booking.php
│   │   ├── CLI/
│   │   │   └── DatabaseCleanupCommand.php
│   │   ├── Core/
│   │   │   ├── Exceptions/
│   │   │   │   ├── MHMException.php
│   │   │   │   └── ValidationException.php
│   │   │   ├── Helpers/
│   │   │   │   └── Sanitizer.php
│   │   │   ├── MetaBoxes/
│   │   │   │   └── AbstractMetaBox.php
│   │   │   ├── PostTypes/
│   │   │   │   └── AbstractPostType.php
│   │   │   ├── Tabs/
│   │   │   │   └── AbstractTab.php
│   │   │   ├── Traits/
│   │   │   │   └── AdminHelperTrait.php
│   │   │   ├── Utilities/
│   │   │   │   ├── AbstractListTable.php
│   │   │   │   ├── BookingQueryHelper.php
│   │   │   │   ├── CacheManager.php
│   │   │   │   ├── DatabaseCleaner.php
│   │   │   │   ├── DatabaseMigrator.php
│   │   │   │   ├── DebugHelper.php
│   │   │   │   ├── ErrorHandler.php
│   │   │   │   ├── I18nHelper.php
│   │   │   │   ├── License.php
│   │   │   │   ├── MetaQueryHelper.php
│   │   │   │   ├── ObjectCache.php
│   │   │   │   ├── QueueManager.php
│   │   │   │   ├── RateLimiter.php
│   │   │   │   ├── RestApiFixer.php
│   │   │   │   ├── Styles.php
│   │   │   │   ├── TaxonomyMigrator.php
│   │   │   │   ├── Templates.php
│   │   │   │   ├── TypeValidator.php
│   │   │   │   ├── UXHelper.php
│   │   │   │   └── WordPressOptimizer.php
│   │   │   ├── AssetManager.php
│   │   │   ├── CurrencyHelper.php
│   │   │   ├── LanguageHelper.php
│   │   │   ├── MetaKeys.php
│   │   │   ├── PerformanceHelper.php
│   │   │   ├── ProFeatureNotice.php
│   │   │   ├── SecurityHelper.php
│   │   │   ├── ShortcodeServiceProvider.php
│   │   │   └── ShortcodeUrlManager.php
│   │   ├── Customers/
│   │   │   ├── AddCustomerPage.php
│   │   │   ├── CustomersListPage.php
│   │   │   ├── CustomersOptimizer.php
│   │   │   └── CustomersPage.php
│   │   ├── Emails/
│   │   │   ├── Core/
│   │   │   │   ├── BookingDataProviderInterface.php
│   │   │   │   ├── BookingQueryHelperAdapter.php
│   │   │   │   ├── EmailFormRenderer.php
│   │   │   │   ├── EmailTemplates.php
│   │   │   │   ├── Mailer.php
│   │   │   │   └── Templates.php
│   │   │   ├── Notifications/
│   │   │   │   ├── BookingNotifications.php
│   │   │   │   ├── RefundNotifications.php
│   │   │   │   └── ReminderScheduler.php
│   │   │   ├── PostTypes/
│   │   │   │   └── EmailLog.php
│   │   │   ├── Settings/
│   │   │   │   ├── EmailTemplateTestAction.php
│   │   │   │   └── EmailTestAction.php
│   │   │   └── Templates/
│   │   │       ├── BookingNotifications.php
│   │   │       ├── EmailPreview.php
│   │   │       ├── OfflinePayment.php
│   │   │       └── RefundEmails.php
│   │   ├── Frontend/
│   │   │   ├── Account/
│   │   │   │   ├── AccountAssets.php
│   │   │   │   ├── AccountController.php
│   │   │   │   ├── AccountRenderer.php
│   │   │   │   └── WooCommerceIntegration.php
│   │   │   ├── Blocks/
│   │   │   │   ├── Base/
│   │   │   │   │   └── GutenbergBlockBase.php
│   │   │   │   └── Gutenberg/
│   │   │   │       ├── BookingFormBlock.php
│   │   │   │       ├── GutenbergIntegration.php
│   │   │   │       ├── VehicleCardBlock.php
│   │   │   │       └── VehiclesListBlock.php
│   │   │   ├── Shortcodes/
│   │   │   │   ├── Core/
│   │   │   │   │   └── AbstractShortcode.php
│   │   │   │   ├── AvailabilityCalendar.php
│   │   │   │   ├── BookingConfirmation.php
│   │   │   │   ├── BookingForm.php
│   │   │   │   ├── ContactForm.php
│   │   │   │   ├── SearchResults.php
│   │   │   │   ├── Testimonials.php
│   │   │   │   ├── VehicleComparison.php
│   │   │   │   ├── VehicleDetails.php
│   │   │   │   ├── VehicleRatingForm.php
│   │   │   │   ├── VehiclesGrid.php
│   │   │   │   └── VehiclesList.php
│   │   │   └── Widgets/
│   │   │       ├── Base/
│   │   │       │   └── ElementorWidgetBase.php
│   │   │       └── Elementor/
│   │   │           ├── AvailabilityCalendarWidget.php
│   │   │           ├── BookingConfirmationWidget.php
│   │   │           ├── BookingFormWidget.php
│   │   │           ├── ContactFormWidget.php
│   │   │           ├── ElementorIntegration.php
│   │   │           ├── LoginFormWidget.php
│   │   │           ├── MyAccountWidget.php
│   │   │           ├── MyBookingsWidget.php
│   │   │           ├── MyFavoritesWidget.php
│   │   │           ├── PaymentHistoryWidget.php
│   │   │           ├── RegisterFormWidget.php
│   │   │           ├── SearchResultsWidget.php
│   │   │           ├── TestimonialsWidget.php
│   │   │           ├── VehicleCardWidget.php
│   │   │           ├── VehicleComparisonWidget.php
│   │   │           ├── VehicleDetailsWidget.php
│   │   │           ├── VehicleRatingWidget.php
│   │   │           ├── VehicleSearchWidget.php
│   │   │           └── VehiclesListWidget.php
│   │   ├── Licensing/
│   │   │   ├── LicenseAdmin.php
│   │   │   ├── LicenseManager.php
│   │   │   ├── Mode.php
│   │   │   └── Restrictions.php
│   │   ├── Messages/
│   │   │   ├── Admin/
│   │   │   │   └── MessageListTable.php
│   │   │   ├── Core/
│   │   │   │   ├── MessageCache.php
│   │   │   │   ├── MessageQueryHelper.php
│   │   │   │   ├── Messages.php
│   │   │   │   └── MessageUrlHelper.php
│   │   │   ├── Frontend/
│   │   │   │   └── CustomerMessages.php
│   │   │   ├── Monitoring/
│   │   │   │   ├── MessageLogger.php
│   │   │   │   ├── MonitoringManager.php
│   │   │   │   └── PerformanceMonitor.php
│   │   │   ├── Notifications/
│   │   │   │   └── MessageNotifications.php
│   │   │   ├── REST/
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── GetMessage.php
│   │   │   │   │   ├── GetMessages.php
│   │   │   │   │   ├── ReplyToMessage.php
│   │   │   │   │   └── UpdateStatus.php
│   │   │   │   ├── Customer/
│   │   │   │   │   ├── CloseMessage.php
│   │   │   │   │   ├── GetBookings.php
│   │   │   │   │   ├── GetMessages.php
│   │   │   │   │   ├── GetThread.php
│   │   │   │   │   ├── SendMessage.php
│   │   │   │   │   └── SendReply.php
│   │   │   │   ├── Helpers/
│   │   │   │   │   ├── Auth.php
│   │   │   │   │   ├── MessageFormatter.php
│   │   │   │   │   └── MessageQuery.php
│   │   │   │   └── Messages.php
│   │   │   ├── Settings/
│   │   │   │   └── MessagesSettings.php
│   │   │   └── Utilities/
│   │   │       └── MessageUtilities.php
│   │   ├── Notifications/
│   │   │   └── NotificationManager.php
│   │   ├── Payment/
│   │   │   ├── Core/
│   │   │   │   ├── PaymentException.php
│   │   │   │   └── PaymentGatewayInterface.php
│   │   │   ├── Gateways/
│   │   │   │   └── Offline/
│   │   │   │       └── API/
│   │   │   ├── Refunds/
│   │   │   │   ├── RefundCalculator.php
│   │   │   │   ├── RefundValidator.php
│   │   │   │   └── Service.php
│   │   │   └── WooCommerce/
│   │   │       └── WooCommerceBridge.php
│   │   ├── PostTypes/
│   │   │   ├── Logs/
│   │   │   │   ├── AdvancedLogger.php
│   │   │   │   ├── MetaBox.php
│   │   │   │   ├── PostType.php
│   │   │   │   └── PostType.php
│   │   │   ├── Maintenance/
│   │   │   │   ├── AutoCancel.php
│   │   │   │   ├── EmailLogRetention.php
│   │   │   │   └── LogRetention.php
│   │   │   ├── Message/
│   │   │   │   └── Message.php
│   │   │   └── Utilities/
│   │   │       └── ClientUtilities.php
│   │   ├── Privacy/
│   │   │   ├── DataRetentionManager.php
│   │   │   └── GDPRManager.php
│   │   ├── Reports/
│   │   │   ├── BusinessLogic/
│   │   │   │   ├── BookingReport.php
│   │   │   │   ├── CustomerReport.php
│   │   │   │   └── RevenueReport.php
│   │   │   ├── Repository/
│   │   │   │   └── ReportRepository.php
│   │   │   ├── BackgroundProcessor.php
│   │   │   ├── Charts.php
│   │   │   └── Reports.php
│   │   ├── REST/
│   │   │   ├── Helpers/
│   │   │   │   ├── AuthHelper.php
│   │   │   │   ├── SecureToken.php
│   │   │   │   └── ValidationHelper.php
│   │   │   ├── Settings/
│   │   │   │   └── RESTSettings.php
│   │   │   ├── APIKeyManager.php
│   │   │   ├── Availability.php
│   │   │   ├── EndpointListHelper.php
│   │   │   └── ErrorHandler.php
│   │   ├── Security/
│   │   │   └── SecurityManager.php
│   │   ├── Settings/
│   │   │   ├── Comments/
│   │   │   │   └── CommentsSettings.php
│   │   │   ├── Core/
│   │   │   │   ├── RateLimiter.php
│   │   │   │   ├── SettingsCore.php
│   │   │   │   ├── SettingsHelper.php
│   │   │   │   └── SettingsSanitizer.php
│   │   │   ├── Groups/
│   │   │   │   ├── AddonSettings.php
│   │   │   │   ├── BookingSettings.php
│   │   │   │   ├── CommentsSettingsGroup.php
│   │   │   │   ├── CoreSettings.php
│   │   │   │   ├── CustomerManagementSettings.php
│   │   │   │   ├── EmailSettings.php
│   │   │   │   ├── GeneralSettings.php
│   │   │   │   ├── LicenseSettings.php
│   │   │   │   ├── LogsSettings.php
│   │   │   │   ├── MaintenanceSettings.php
│   │   │   │   ├── PaymentSettings.php
│   │   │   │   ├── ReconcileSettings.php
│   │   │   │   ├── SecuritySettings.php
│   │   │   │   ├── VehicleComparisonSettings.php
│   │   │   │   └── VehicleManagementSettings.php
│   │   │   ├── Testing/
│   │   │   │   └── SettingsTester.php
│   │   │   ├── APIKeysPage.php
│   │   │   ├── Settings.php
│   │   │   ├── SettingsHandler.php
│   │   │   ├── SettingsView.php
│   │   │   └── ShortcodePages.php
│   │   ├── Setup/
│   │   │   └── SetupWizard.php
│   │   ├── Testing/
│   │   │   ├── ActivationTest.php
│   │   │   ├── FunctionalTest.php
│   │   │   ├── IntegrationTest.php
│   │   │   ├── PerformanceAnalyzer.php
│   │   │   ├── PerformanceTest.php
│   │   │   ├── SecurityTest.php
│   │   │   ├── ShortcodeTestHandler.php
│   │   │   ├── TestAdminPage.php
│   │   │   └── TestRunner.php
│   │   ├── Transfer/
│   │   │   ├── Engine/
│   │   │   │   └── TransferSearchEngine.php
│   │   │   ├── Frontend/
│   │   │   │   └── TransferShortcodes.php
│   │   │   ├── Integration/
│   │   │   │   ├── TransferBookingHandler.php
│   │   │   │   └── TransferCartIntegration.php
│   │   │   ├── TransferAdmin.php
│   │   │   └── VehicleTransferMetaBox.php
│   │   ├── Utilities/
│   │   │   ├── Actions/
│   │   │   │   └── Actions.php
│   │   │   ├── Cron/
│   │   │   │   ├── CronMonitor.php
│   │   │   │   └── CronMonitorPage.php
│   │   │   ├── Dashboard/
│   │   │   │   └── DashboardPage.php
│   │   │   ├── Database/
│   │   │   │   ├── DatabaseCleanupPage.php
│   │   │   │   ├── DatabaseInitialization.php
│   │   │   │   └── MetaKeysDocumentation.php
│   │   │   ├── Export/
│   │   │   │   ├── Export.php
│   │   │   │   ├── ExportFilters.php
│   │   │   │   ├── ExportHistory.php
│   │   │   │   ├── ExportReports.php
│   │   │   │   └── ExportStats.php
│   │   │   ├── ListTable/
│   │   │   │   ├── CustomersListTable.php
│   │   │   │   └── LogColumns.php
│   │   │   ├── Menu/
│   │   │   │   └── Menu.php
│   │   │   ├── Performance/
│   │   │   │   └── AdminOptimizer.php
│   │   │   └── Uninstall/
│   │   │       ├── Uninstaller.php
│   │   │       └── UninstallPage.php
│   │   └── Vehicle/
│   │       ├── Deposit/
│   │       │   ├── DepositAjax.php
│   │       │   └── DepositCalculator.php
│   │       ├── Frontend/
│   │       │   └── VehicleSearch.php
│   │       ├── Helpers/
│   │       │   ├── VehicleDataHelper.php
│   │       │   └── VehicleFeatureHelper.php
│   │       ├── ListTable/
│   │       │   └── VehicleColumns.php
│   │       ├── Meta/
│   │       │   ├── VehicleGallery.php
│   │       │   └── VehicleMeta.php
│   │       ├── PostType/
│   │       │   └── Vehicle.php
│   │       ├── Reports/
│   │       │   └── VehicleReport.php
│   │       ├── Settings/
│   │       │   ├── VehiclePricingSettings.php
│   │       │   └── VehicleSettings.php
│   │       ├── Taxonomies/
│   │       │   └── VehicleCategory.php
│   │       └── Templates/
│   │           ├── vehicle-gallery.php
│   │           └── vehicle-meta.php
│   └── Plugin.php
└── templates/
    ├── account/
    │   ├── account-details.php
    │   ├── booking-detail.php
    │   ├── bookings.php
    │   ├── dashboard.php
    │   ├── favorites.php
    │   ├── login-form.php
    │   ├── messages.php
    │   ├── navigation.php
    │   ├── payment-history.php
    │   └── register-form.php
    ├── admin/
    │   ├── booking-meta/
    │   │   ├── booking-status.php
    │   │   ├── offline-box.php
    │   │   ├── payment-box.php
    │   │   └── receipt-box.php
    │   └── reports/
    │       ├── bookings.php
    │       ├── customers.php
    │       ├── overview.php
    │       ├── revenue.php
    │       ├── stats-cards.php
    │       └── vehicles.php
    ├── emails/
    │   ├── booking-cancelled.html.php
    │   ├── booking-created-admin.html.php
    │   ├── booking-created-customer.html.php
    │   ├── booking-reminder-customer.html.php
    │   ├── booking-status-changed-admin.html.php
    │   ├── booking-status-changed-customer.html.php
    │   ├── message-received-admin.html.php
    │   ├── message-replied-customer.html.php
    │   ├── offline-receipt-uploaded-admin.html.php
    │   ├── offline-verified-approved-customer.html.php
    │   ├── offline-verified-rejected-customer.html.php
    │   ├── receipt-status-email.html.php
    │   ├── refund-admin.html.php
    │   ├── refund-customer.html.php
    │   └── welcome-customer.html.php
    ├── messages/
    │   ├── admin-message-email.html.php
    │   ├── customer-reply-email.html.php
    │   ├── customer-status-change-email.html.php
    │   ├── message-reply-form.html.php
    │   └── message-thread-view.html.php
    ├── shortcodes/
    │   ├── availability-calendar.php
    │   ├── booking-confirmation.php
    │   ├── booking-form.php
    │   ├── contact-form.php
    │   ├── search-results.php
    │   ├── testimonials.php
    │   ├── thank-you.php
    │   ├── vehicle-comparison.php
    │   ├── vehicle-details.php
    │   ├── vehicle-rating-form.php
    │   ├── vehicle-search-compact.php
    │   ├── vehicle-search.php
    │   ├── vehicles-grid.php
    │   └── vehicles-list.php
    ├── archive-vehicle.php
    └── single-vehicle.php
```

---

## 📋 Gereksinimler

### WordPress
- **Minimum Versiyon**: 5.0
- **Test Edildi**: 6.8'e kadar
- **Multisite**: Desteklenir

### PHP
- **Minimum Versiyon**: 7.4
- **Önerilen**: 8.0 veya üzeri
- **Gerekli Uzantılar**:
  - `json`
  - `curl`
  - `mbstring`
  - `openssl`

### Veritabanı
- **MySQL**: 5.7 veya üzeri
- **MariaDB**: 10.3 veya üzeri

### Sunucu
- **HTTPS**: Ödeme işlemleri için önerilir
- **Bellek Limiti**: Minimum 128MB (256MB önerilir)
- **Yükleme Boyutu**: Makbuz yüklemeleri için minimum 10MB

### WordPress İzinleri
- `manage_options` - Admin ayarları için gerekli
- `edit_posts` - Rezervasyon yönetimi için gerekli
- `upload_files` - Araç görselleri ve makbuzlar için gerekli

---

## 🛠 Geliştirme

### Geliştirme Kurulumu

```bash
# Depoyu klonlayın
git clone [repository-url] mhm-rentiva
cd mhm-rentiva

# wp-config.php dosyasında geliştirme modunu etkinleştirin
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SCRIPT_DEBUG', true);
```

### Kod Standartları

- **WordPress Kodlama Standartları (WPCS)**: Tam uyumluluk
- **PSR-4 Autoloading**: Namespace tabanlı otomatik yükleme
- **Type Hinting**: PHP 8.0+ tip tanımlamaları
- **Strict Types**: Tüm dosyalarda `declare(strict_types=1)`
- **Namespace**: `MHMRentiva\Admin\*`

### Mimari

- **Modüler Tasarım**: Her özellik kendi dizininde
- **Endişelerin Ayrılması**: Core, Admin, Frontend ayrımı
- **Singleton Pattern**: Uygun yerlerde kullanıldı
- **Factory Pattern**: Örnek oluşturma için
- **Observer Pattern**: WordPress hook sistemi

### Yeni Özellik Ekleme

1. Uygun konumda özellik dizini oluşturun
2. Ana sınıf dosyasını oluşturun
3. `register()` statik metodunu uygulayın
4. `Plugin.php` içinde kaydedin
5. `register()` metodunda hook'ları ekleyin
6. WordPress kodlama standartlarına uyun

### Test

**Manuel Test**:
- WordPress admin panelinde test edin
- Frontend işlevselliğini test edin
- Ödeme akışlarını test edin
- E-posta bildirimlerini test edin

**Otomatik Test**:
- Aktivasyon testleri
- Güvenlik testleri
- Fonksiyonel testler
- Performans testleri

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

## 📝 Değişiklik Geçmişi

### Versiyon: 4.6.1 (2026-01-21)

**🛡️ KRİTİK GÜNCELLEME & GÜVENLİK**
- **DatabaseCleaner**: Veri kaybını önlemek için 40+ kritik meta anahtarı (WooCommerce siparişleri, ödeme detayları) korumaya alındı.
- **SQL Güvenliği**: `BookingColumns` ve `ExportStats` içindeki SQL sorguları `wpdb->prepare()` ile güçlendirildi.

**🛍️ WOOCOMMERCE & ÖDEMELER**
- **Atomik Çakışma Kilidi**: WooCommerce üzerinden çift rezervasyon yapılmasını önleyen kilit mekanizması eklendi.
- **Vergi Hesaplama**: Depozito ödemelerinde bile verginin toplam tutar üzerinden hesaplanması sağlandı.
- **Ödeme Ayarları**: WooCommerce aktif olduğunda Ödeme Ayarları sayfasına yönlendirici uyarı eklendi.

### Son Versiyon: 4.6.0 (2026-01-18)

**🚐 VIP TRANSFER MODÜLÜ**
- **Noktadan Noktaya Rezervasyon**: Dinamik alış/varış konumu yönetimi.
- **Fiyatlandırma Motoru**: Mesafe bazlı veya sabit rota fiyatlandırması.
- **WooCommerce Entegrasyonu**: Sepet ve ödeme sayfasında transfer desteği.
- **AJAX Arama**: Yeni `[mhm_rentiva_transfer_search]` shortcode'u.
- **Operasyonel Kontrol**: Araç hazırlığı için Buffer Time (Hazırlık Süresi) mantığı.

### Versiyon: 4.5.5 (2026-01-15)

**🎨 ÖN YÜZ İYİLEŞTİRMELERİ & DÜZELTMELER**
- **Araç Detay**: "Kullanım Dışı" rozet mantığı ve yerleşimi düzeltildi.
- **Arama Sonuçları**: Buton renkleri standartlaştırıldı ve durum göstergeleri eklendi.
- **Karşılaştırma Sayfası**: Kart hizalamaları ve mobil görünüm iyileştirildi.
- **Rezervasyonlarım**: Tablo düzeni kompakt hale getirildi.

Tam değişiklik geçmişi için [changelog-tr.json](changelog-tr.json) dosyasına bakın.

---

## 📄 Lisans

Bu proje **GPL-2.0+** lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakın.

---

## 👨‍💻 Geliştirici

**MaxHandMade**
- Website: [maxhandmade.com](https://maxhandmade.com)
- Destek: info@maxhandmade.com

---

## 📞 Destek

Sorular, sorunlar veya özellik istekleri için:
- **E-posta**: info@maxhandmade.com
- **Website**: https://maxhandmade.com

---

## ⭐ Projeyi Yıldızlayın

Bu eklentiyi faydalı bulursanız, lütfen GitHub'da yıldız vermeyi düşünün!

---

**WordPress topluluğu için ❤️ ile yapıldı**
