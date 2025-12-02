# MHM Rentiva - WordPress Araç Kiralama Eklentisi

<div align="right">

**🌐 Dil / Language:** 
[![TR](https://img.shields.io/badge/Dil-Türkçe-red)](README-tr.md) 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![Değişiklikler](https://img.shields.io/badge/Değişiklikler-TR-orange)](changelog-tr.json) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json)

</div>

![Version](https://img.shields.io/badge/version-4.4.5-blue.svg)
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
- **Ödeme İşleme**: Makbuz yönetimi ve WooCommerce entegrasyonu ile offline ödeme ağ geçidi
- **Müşteri Portalı**: Rezervasyon geçmişi, favoriler ve mesajlaşma ile tam özellikli müşteri hesap sistemi
- **Analitik ve Raporlama**: Gelir, müşteri ve araç içgörüleri ile kapsamlı analitik dashboard
- **E-posta Sistemi**: Özelleştirilebilir HTML şablonları ile otomatik e-posta bildirimleri
- **Mesajlaşma Sistemi**: Thread yönetimi ile yerleşik müşteri destek mesajlaşması
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

### 💳 Ödeme Sistemi

**Desteklenen Ödeme Ağ Geçitleri:**

1. **Offline Ödeme** (Banka havalesi, nakit veya diğer offline yöntemler)
   - Makbuz yükleme sistemi
   - Yönetici onay iş akışı
   - Belirlenen sürede onaylanmazsa otomatik rezervasyon iptali

2. **WooCommerce Entegrasyonu**
   - WooCommerce ile sorunsuz entegrasyon
   - WooCommerce tarafından desteklenen herhangi bir ödeme ağ geçidini kullanın
   - Sipariş durumuna göre otomatik rezervasyon durumu güncellemeleri

### 👥 Müşteri Yönetimi

**Müşteri Hesap Sistemi:**
- **WordPress Yerel Entegrasyonu**: Standart WordPress kullanıcı sistemi kullanır
- **Müşteri Rolü**: WordPress "Customer" rolünün otomatik atanması
- **Hesabım Dashboard**: WooCommerce benzeri hesap yönetim arayüzü

### 📊 Raporlama ve Analitik

**Analitik Dashboard:**
- **Gelir Analitiği**: Toplam gelir, dönem bazlı gelir, araç bazlı gelir
- **Rezervasyon Analitiği**: Toplam rezervasyonlar, durum dağılımı, rezervasyon trendleri
- **Araç Analitiği**: En çok kiralanan araçlar, araç kullanım oranları
- **Müşteri Analitiği**: Toplam müşteriler, müşteri segmentasyonu, tekrar eden müşteri oranı

### 🌍 Uluslararasılaştırma ve Yerelleştirme

**Dil Desteği:**
- **60+ Dil**: 60+ WordPress locale için tam destek
- **Merkezi Yönetim**: Birleşik dil yönetimi için `LanguageHelper` sınıfı

**Para Birimi Desteği:**
- **47 Para Birimi**: 47 farklı para birimi desteği
- **Merkezi Yönetim**: Birleşik para birimi yönetimi için `CurrencyHelper` sınıfı

### 🎨 Elementor Widget'leri Entegrasyonu

**Eksiksiz Widget Paketi (19 Widget):**

**Araç Widget'leri:**
- **Araç Arama Widget'ı**: Gelişmiş filtrelerle araç arama
- **Araç Kartı Widget'ı**: Özelleştirilebilir düzen ile tek araç görüntüleme
- **Araçlar Listesi Widget'ı**: Gelişmiş sorgu seçenekleri ile grid veya liste görünümü
- **Araç Detayları Widget'ı**: Detaylı araç bilgisi görüntüleme
- **Araç Karşılaştırma Widget'ı**: Yan yana araç karşılaştırma
- **Araç Değerlendirme Widget'ı**: Araç değerlendirme ve yorum görüntüleme
- **Arama Sonuçları Widget'ı**: Filtreleme ile arama sonuçları görüntüleme
- **Müsaitlik Takvimi Widget'ı**: İnteraktif müsaitlik takvimi

**Rezervasyon Widget'leri:**
- **Rezervasyon Formu Widget'ı**: Araç seçimi, ek hizmetler ve ödeme seçenekleri ile eksiksiz rezervasyon formu
- **Rezervasyon Onayı Widget'ı**: Rezervasyon onayı görüntüleme
- **Teşekkür Widget'ı**: Rezervasyon tamamlandıktan sonra teşekkür sayfası

**Hesap Widget'leri:**
- **Hesabım Widget'ı**: Müşteri hesap dashboard'u
- **Rezervasyonlarım Widget'ı**: Müşteri rezervasyon geçmişi
- **Favorilerim Widget'ı**: Favori araçlar yönetimi
- **Ödeme Geçmişi Widget'ı**: Ödeme işlem geçmişi
- **Hesap Detayları Widget'ı**: Hesap profil düzenleme

**Kimlik Doğrulama Widget'leri:**
- **Giriş Formu Widget'ı**: Müşteri giriş formu
- **Kayıt Formu Widget'ı**: Müşteri kayıt formu

**Diğer Widget'ler:**
- **İletişim Formu Widget'ı**: İletişim formu entegrasyonu
- **Müşteri Yorumları Widget'ı**: Müşteri yorumları görüntüleme

**Widget Özellikleri:**
- **Yerel Elementor Entegrasyonu**: Elementor 3.5+ API ile tam uyumluluk
- **Özel Kategori**: Tüm widget'ler "MHM Rentiva" kategorisi altında organize edildi
- **Gelişmiş Kontroller**: Tipografi, renkler, kenarlıklar, gölgeler ve boşluk dahil kapsamlı stil seçenekleri
- **Canlı Önizleme**: Elementor editöründe gerçek zamanlı önizleme
- **Sürükle-Bırak Builder**: Kolay widget yerleştirme ve düzenleme
- **Responsive Kontroller**: Elementor'un responsive kontrolleri ile tam responsive tasarım
- **Özel Stil**: Widget görünümü ve düzeni üzerinde tam kontrol
- **Sorgu Seçenekleri**: Araç widget'ları için gelişmiş sorgu ayarları (kategoriler, etiketler, öne çıkan, vb.)
- **Düzen Seçenekleri**: Birden fazla düzen seçeneği (grid, liste, kart, vb.)
- **Varlık Yönetimi**: Widget'lar kullanıldığında otomatik CSS/JS yükleme

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

Eklenti shortcode'lar için sayfaları otomatik olarak oluşturur veya manuel olarak oluşturabilirsiniz.

---

## ⚙️ Yapılandırma

### Genel Ayarlar

**Konum**: `Rentiva > Settings > General`

- **Para Birimi**: Varsayılan para birimini seçin (47 para birimi desteklenir)
- **Para Birimi Konumu**: Boşluklu/boşluksuz Sol/Sağ
- **Tarih Formatı**: Tarih görüntüleme formatını özelleştirin

### Rezervasyon Ayarları

**Konum**: `Rentiva > Settings > Booking`

- **İptal Son Tarihi**: Rezervasyon başlangıcından önceki saatler (varsayılan: 24)

### Ödeme Ayarları

**Offline Ödeme Ayarları:**
- Offline ödemeleri Etkinleştir/Devre Dışı Bırak
- Başlık ve açıklama ayarla
- Müşteriler için talimatları yapılandır

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

#### `[rentiva_my_account]`
**Amaç**: Ana müşteri hesap dashboard'u

#### `[rentiva_my_bookings]`
**Amaç**: Müşteri rezervasyon geçmişini göster

#### `[rentiva_booking_form]`
**Amaç**: Araç kiralama için ana rezervasyon formu

**Kullanım**:
```php
[rentiva_booking_form vehicle_id="123"]
```

#### `[rentiva_vehicles_grid]`
**Amaç**: Araçları grid düzeninde göster

**Kullanım**:
```php
[rentiva_vehicles_grid columns="3" limit="12"]
```

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


### 💳 Ödeme Ağ Geçitleri

**Offline Ödeme**:
- Varsayılan olarak etkindir
- Banka havalesi ve nakit ödemeler için idealdir
- Müşteriler ödeme makbuzu yükleyebilir

**WooCommerce Entegrasyonu**:
- Pro sürümde mevcuttur
- WooCommerce ödeme altyapısını kullanır
- Kredi kartı ve diğer online ödeme yöntemleri için WooCommerce eklentilerini kullanabilirsiniz

---

## 📋 Gereksinimler

### WordPress
- **Minimum Versiyon**: 5.0
- **Test Edildi**: 6.8'e kadar
- **Multisite**: Desteklenir

### PHP
- **Minimum Versiyon**: 7.4
- **Önerilen**: 8.0 veya üzeri
- **Gerekli Uzantılar**: `json`, `curl`, `mbstring`, `openssl`

### Veritabanı
- **MySQL**: 5.7 veya üzeri
- **MariaDB**: 10.3 veya üzeri

---

## 📝 Değişiklik Geçmişi

### Son Versiyon: 4.4.5 (2025-11-25)

**📦 VERSİYON GÜNCELLEMESİ**
- Bakım ve kararlılık iyileştirmeleri için versiyon 4.4.5'e yükseltildi.

**🎨 ELEMENTOR WIDGET'LERİ İYİLEŞTİRMESİ**
- Tüm eklenti özelliklerini kapsayan 19 kapsamlı widget ile geliştirilmiş Elementor entegrasyonu
- Tüm widget'ler Elementor'da özel "MHM Rentiva" kategorisi altında organize edildi
- Tüm widget'lere gelişmiş stil ve özelleştirme seçenekleri eklendi
- Mobil öncelikli yaklaşımla tam responsive tasarım
- Elementor'un canlı önizleme ve sürükle-bırak builder'ı ile sorunsuz entegrasyon

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

