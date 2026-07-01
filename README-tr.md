# MHM Rentiva - WordPress Araç Kiralama Eklentisi

<div align="right">

**🌐 Dil / Language:** 
[![TR](https://img.shields.io/badge/Language-Turkce-red)](README-tr.md) 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![Degisiklikler TR](https://img.shields.io/badge/Changelog-TR-orange)](changelog-tr.json) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json)

</div>

<p align="center">
  <img src=".wordpress-org/banner-1544x500.png" alt="MHM Rentiva — WordPress için Araç Kiralama ve Transfer Rezervasyon Sistemi" width="800">
</p>

![Version](https://img.shields.io/badge/version-4.62.2-blue.svg)
![Lisans Güvenliği](https://img.shields.io/badge/lisans%20g%C3%BCvenli%C4%9Fi-RSA--2048-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

**WordPress için profesyonel araç kiralama yönetim sistemi.** Araç kiralama, rezervasyon, ödeme, müşteri yönetimi ve kapsamlı raporlama için eksiksiz, kurumsal düzeyde bir çözüm. WordPress en iyi uygulamalarıyla geliştirilmiş, tam uluslararasılaştırma desteği ile küresel pazarlara hazır.

---

## 📋 İçindekiler

- [Genel Bakış](#-genel-bakış)
- [Temel Özellikler](#-temel-özellikler)
- [Lisans Yönetimi](#-lisans-yönetimi)
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
- **WooCommerce Hesabım Entegrasyonu**: Müşteriler standart WooCommerce "Hesabım" sayfasını kullanır; eklenti bu sayfaya Rezervasyonlarım, Favorilerim, Ödeme Geçmişi ve Mesajlar gibi özel sekmeler ekler
- **Vendor Marketplace** *(Pro)*: Araç sahiplerinin platforma başvurmasına, araçlarını frontend üzerinden listelemesine (araç gönderme formu), finansal hareketlerini takip etmesine ve vendor dashboard üzerinden durumlarını görüntülemesine olanak tanıyan çok bayili pazar yeri sistemi
- **Araç Yaşam Döngüsü Yönetimi** *(Pro, v4.24.0)*: 90 gün listeleme süresi, vendor self-servis (duraklat/devam/geri çek/yenile), artan ceza sistemi, güvenilirlik puanı, anti-gaming tarih bloklama
- **Analitik ve Raporlama**: Gelir, müşteri ve araç içgörüleri ile kapsamlı analitik dashboard
- **E-posta Sistemi**: Özelleştirilebilir HTML şablonları ile otomatik e-posta bildirimleri
- **Mesajlaşma Sistemi**: Thread yönetimi ile yerleşik müşteri destek mesajlaşması
- **VIP Transfer Modülü**: Mesafe tabanlı fiyatlandırma ve araç seçimi ile noktadan noktaya rezervasyon sistemi
- **REST API**: Üçüncü taraf entegrasyonları ve mobil uygulamalar için eksiksiz REST API

### Bu Eklenti Kimler İçin?

- **Araba Kiralama Şirketleri**: Filo, rezervasyon ve müşteri ilişkilerini yönetin
- **Bisiklet/Motosiklet Kiralama**: Müsaitliği takip edin ve ödemeleri işleyin
- **Ekipman Kiralama İşletmeleri**: Her türlü araç veya ekipmanı kiralayın
- **Transfer Lokasyon Yönetimi**: VIP Transfer modülü ile birden fazla alış/bırakış noktası ve para birimi desteği
- **Pazar Yeri İşletmeleri**: Vendor Marketplace ile çok bayili araç kiralama platformu kurun *(Pro)*
- **Çeviriye Hazır**: Eklenti İngilizce ve Türkçe ile gelir; WooCommerce uyumlu para birimi desteği. Loco Translate üzerinden her dile çevrilebilir

---

## ✨ Temel Özellikler

### 🚗 Araç Yönetim Sistemi

**Temel Araç Özellikleri:**
- **Özel Post Tipi**: Araçlar için yerel WordPress post tipi
- **Araç Galerisi**: WordPress Medya Kütüphanesi entegrasyonu ile görsel yükleme (Lite: 5 görsel / araç, Pro: sınırsız, ayarlanabilir)
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
- **VIP Transfer Modülü Entegrasyonu**: Şoförlü araç rezervasyonları da bu sistem üzerinden yönetilir *(bkz. [VIP Transfer Modülü](#-vip-transfer-modülü-şoförlü-hizmet) bölümü)*

### 💳 Ödeme Sistemi

**1. Frontend (Müşteri) Ödemeleri (WooCommerce ile)**
- **WooCommerce Entegrasyonu**: Tüm frontend rezervasyonları WooCommerce üzerinden güvenle işlenir.
- **Ödeme Yöntemleri**: WooCommerce tarafından desteklenen tüm yöntemleri (Kredi Kartı, Banka Havalesi, PayPal, Kapıda Ödeme, vb.) kabul edin.
- **Otomatik Durum Güncellemeleri**: Rezervasyon durumları, WooCommerce sipariş durumuna göre otomatik güncellenir.

**2. Manuel Ödemeler (Sadece Yönetici)**
- **Manuel Ödeme Kaydı**: Yöneticiler manuel oluşturulan rezervasyonlar için ödemeleri (Nakit/Havale) sisteme işleyebilir.
- **Makbuz Yönetimi**: Yöneticiler manuel rezervasyonlara ödeme kanıtı ekleyebilir.

**3. Vendor Finansal Sistemi** *(Pro)*
- **Komisyon Politikası**: Platforma kayıtlı bayiler için zaman bazlı komisyon oranı tanımlanabilir (`CommissionPolicy`).
- **Kademe Sistemi**: Hacim bazlı komisyon indirimi — bayi ne çok kazanırsa komisyon oranı o kadar düşer (`TierService`).
- **Finansal Defter (Ledger)**: Bayi bazında tüm kazanç ve kesinti kayıtları izlenebilir.
- **Ödeme Yönetimi (Payout)**: Yönetici bayilere ödeme işleyebilir; tüm hareketler loglanır.

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
  - Mesaj merkezi *(Pro)*
  - Vendor başvuru formu *(Pro)*

**Hesap Shortcode'ları:**
- `[rentiva_user_dashboard]` - Kullanıcı/Vendor dashboard (giriş durumuna göre içerik değişir)
- `[rentiva_my_bookings]` - Rezervasyon geçmişi
- `[rentiva_my_favorites]` - Favori araçlar
- `[rentiva_payment_history]` - Ödeme işlemleri
- `[rentiva_messages]` - Mesaj merkezi *(Pro)*
- `[rentiva_vendor_ledger]` - Vendor finansal hareketleri *(Pro)*

> **Not:** Giriş, kayıt ve hesap detayları WooCommerce'in kendi sayfaları üzerinden yönetilir.

**Müşteri Özellikleri:**
- Rezervasyon sırasında otomatik hesap oluşturma
- Şifre sıfırlama işlevi
- Rezervasyon bildirimleri
- E-posta bildirimleri
- Mesaj bildirimleri *(Pro)*

### 📊 Raporlama ve Analitik

**Admin Rapor Sayfası** *(5 sekme, tarih aralığı filtresi ile)*

- **Genel Bakış (Overview)**: Gelir, rezervasyon, müşteri ve araç verilerini özetleyen birleşik görünüm
- **Gelir Raporu**: Dönem bazlı gelir analizi
- **Rezervasyon Raporu**: Durum dağılımı ve rezervasyon verileri
- **Araç Raporu**: En çok kiralanan araçlar, araç başına gelir, kategori performansı, doluluk oranı
- **Müşteri Raporu**: Müşteri harcamaları, yeni / tekrar eden müşteri ayrımı

> **Lite sürümde** maksimum 30 günlük veri görüntülenir. Daha uzun aralıklar `Pro` gerektirir.

**WordPress Dashboard Widget'ları:**
- İstatistik kartları (toplam rezervasyon, aylık gelir, aktif kiralama, doluluk oranı)
- Gelir grafiği (son 30 gün)
- Yaklaşan işlemler listesi (kiralama + transfer)

**Analitik Özellikleri:**
- Tarih aralığı bazlı filtreleme
- Araç kategorisi performans karşılaştırması
- Müşteri segmentasyonu (yeni / tekrar eden)
- Rapor önbelleği ve manuel önbellek temizleme

### 🚀 Lite ve Pro Sürüm Karşılaştırması

Lite sürümü, küçük işletmeler ve test amaçlı tasarlanmıştır; temel kiralama akışını sınırlı miktarlarda çalıştırır. Pro sürümü tüm sınırları kaldırır ve gelişmiş özellikleri (mesajlaşma, vendor pazaryeri, araç yaşam döngüsü, gelişmiş raporlar) ekler. Detaylı özellik karşılaştırması için aşağıdaki [Özellik Karşılaştırma Tablosu](#özellik-karşılaştırma-tablosu) bölümüne bakın.

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

**3. Mesaj E-postaları** *(Pro)*:
- Yeni mesaj bildirimi

**4. Vendor E-postaları** *(Pro — `VendorNotifications`)*:
- Başvuru alındı (bayiye + admin)
- Vendor başvurusu onaylandı / reddedildi
- Araç listesi onaylandı / reddedildi
- Ödeme (payout) onaylandı / reddedildi
- IBAN değişikliği onaylandı / reddedildi

> **Not:** Hesap oluşturma, şifre sıfırlama gibi hesap e-postaları WooCommerce tarafından yönetilir.

**E-posta Özellikleri:**
- **Özelleştirilebilir**: Admin ayarlarından her şablonun konu ve içeriği değiştirilebilir
- **HTML Şablonlar**: Dinamik placeholder desteği (`{booking_id}`, `{vehicle_title}`, vb.)
- **E-posta Loglama**: Hata ayıklama için tüm e-postalar `EmailLog` post tipiyle loglanır
- **Şablon Sistemi**: Merkezi `Mailer::send()` üzerinden standart gönderim

### 💬 Mesajlaşma Sistemi (Pro)

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
- **Noktadan Noktaya Rezervasyon**: Önceden tanımlanmış lokasyonlardan alış ve bırakış noktası seçimi.
- **Güzergah Bazlı Fiyatlandırma**: Admin panelinde tanımlanan güzergah çiftlerine (origin → destination) göre sabit fiyat.
- **Araç Seçimi**: Farklı kapasitelere sahip transfer araçları (yolcu sayısı, bagaj kapasitesi).
- **AJAX Arama**: Gerçek zamanlı sonuçlar içeren transfer arama arayüzü; yolcu sayısı ve bagaj kriterleri ile filtreleme.
- **WooCommerce Entegrasyonu**: Transfer rezervasyonlarını sepete sorunsuz ekleme (Depozito veya Tam Ödeme).
- **Frontend Takibi**: Müşteriler transfer detaylarını WooCommerce "Hesabım" alanında görüntüleyebilir.

**Transfer Shortcode'ları:**
- `[rentiva_transfer_search]` — Alış/bırakış, tarih, saat, yolcu ve bagaj arama formu
- `[rentiva_transfer_results]` — Arama sonuçlarını listeler

**Admin Özellikleri:**
- Transfer lokasyon yönetimi
- Güzergah (rota) tanımlama ve fiyat belirleme
- Transfer rezervasyon yönetim paneli
- Dışa/İçe aktarma (`TransferExportImport`)

**v4.23.0 Yenilikleri:**
- **Şehir → Nokta Hiyerarşisi**: Her lokasyona şehir alanı eklendi; vendor'lar yalnızca kendi şehirlerindeki lokasyonları görür.
- **Vendor Rota Fiyatlandırması**: Admin'in belirlediği min/max aralığında vendor'lar rota bazlı fiyat belirleyebilir.
- **Rota Bazlı Araç Filtreleme**: Transfer arama motoru, rota ataması, yolcu ve bagaj kapasitesine göre araçları filtreler. Vendor fiyatı yoksa rotanın `base_price` değeri kullanılır.

### 🏪 Vendor Pazaryeri (Pro)

**Çok Bayili Yönetim:**
- **Vendor Rolü**: İzole izinlerle özel `rentiva_vendor` WordPress rolü
- **Vendor Başvurusu**: Belge yükleme destekli frontend başvuru formu (kimlik, ehliyet, adres belgesi, sigorta)
- **Onboarding İş Akışı**: Admin başvuruları onaylama/reddetme/askıya alma
- **IBAN Şifreleme**: AES-256-CBC ile banka hesap bilgisi şifreleme

**Vendor Araç Yönetimi:**
- **Frontend Araç Ekleme**: Vendor'lar `[rentiva_vehicle_submit]` shortcode'u ile araç ekler
- **Araç İnceleme**: Admin onaylama/reddetme (kritik/minör alan ayrımı)
- **Medya İzolasyonu**: Vendor başına ayrı medya kütüphanesi
- **Sahiplik Kontrolü**: Vendor yalnızca kendi araçlarını düzenleyebilir

**Vendor Transfer İşlemleri (v4.23.0):**
- **Şehir Bazlı Filtreleme**: Vendor'lar yalnızca kendi şehirlerindeki lokasyon ve rotaları görür
- **Rota Fiyatlandırması**: Admin min/max aralığında vendor rota bazlı fiyat belirler
- **Transfer Arama Entegrasyonu**: Arama motoru vendor fiyatını kullanır, yoksa base_price fallback

**Finansal Sistem:**
- **Komisyon Yönetimi**: Vendor başına esnek komisyon oranları
- **Finansal Defter (Ledger)**: Tüm finansal hareket geçmişi
- **Ödeme Talepleri (Payout)**: Vendor ödeme takibi ve onaylama
- **İade Kayıtları**: İptallerde otomatik ters defter kaydı

**Vendor Paneli (`/panel/`):**
- İlanlar: Satır içi ekleme formu ile araç yönetimi
- Rezervasyon Talepleri: Gelen rezervasyon yönetimi
- Defter & Ödemeler: Finansal genel bakış ve ödeme talepleri

**Vendor Bildirimleri (15 E-posta Şablonu):**
- Başvuru gönderildi/onaylandı/reddedildi
- Araç onaylandı/reddedildi
- Ödeme onaylandı/reddedildi
- Yaşam döngüsü: aktifleştirme/duraklatma/devam/geri çekme/süre dolumu/uyarılar/yenileme/yeniden listeleme

### 🔄 Araç Yaşam Döngüsü Yönetimi (Pro, v4.24.0)

**Durum Makinesi:**
- **5 Durum**: Onay Bekliyor, Aktif, Duraklatılmış, Süresi Dolmuş, Geri Çekilmiş
- **Geçiş Kuralları**: Zorunlu durum makinesi ile izin verilen geçişler
- **90 Gün Listeleme**: Cron tabanlı otomatik süre dolumu

**Vendor Self-Servis:**
- Duraklat/Devam Et: İlanı geçici olarak gizle (zamanlayıcı devam eder)
- Geri Çek: Kalıcı olarak kaldır, 7 gün bekleme süresi sonra yeniden listeleme
- Yenile: Aktif ilanı 90 gün daha uzat
- Yeniden Listele: Geri çekilen aracı admin onayına tekrar gönder

**Artan Ceza Sistemi:**
- 1. geri çekme: Ücretsiz
- 2. geri çekme: Aylık ortalama gelirin %10'u
- 3.+ geri çekme: Aylık ortalama gelirin %25'i
- 12 aylık kayan pencere, deftere entegre ceza kaydı

**Güvenilirlik Puanı (0-100):**
- Tüm vendor'lar için günlük cron ile yeniden hesaplama
- Formül: Baz 100, -5/iptal, -10/geri çekme, -2/duraklatma, +5/tamamlama (maks +20)
- Etiketler: Mükemmel (90+), İyi (70+), Orta (50+), Zayıf (<50)

**Anti-Gaming Koruması:**
- Vendor iptal ettiği rezervasyon tarihleri 30 gün boyunca yeniden bloklanır
- İptal et-yeniden listele taktiğiyle fiyat manipülasyonunu engeller

**Admin Arayüzü:**
- Araç listesinde yaşam döngüsü durumu sütunu (renkli rozetler + kalan gün)
- Araç düzenleme ekranında salt okunur yaşam döngüsü meta kutusu
- Kullanıcı listesinde vendor güvenilirlik puanı sütunu (sıralanabilir)

**Otomatik Bildirimler:**
- 10 gün ve 3 gün süre dolumu uyarı e-postaları
- Durum değişikliği bildirimleri (aktif, duraklatılmış, devam, geri çekilmiş, süresi dolmuş)
- Yenileme ve yeniden listeleme onay e-postaları

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
- **Lite Sürüm Limiti**: Lite sürümde maksimum 4 ek hizmet (Pro'da sınırsız)
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
- **CSV**: Virgülle ayrılmış değerler (Lite ve Pro)
- **JSON**: JSON formatı (sadece Pro)

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

### 🔒 Gizlilik ve GDPR Uyumluluğu (Pro)

**GDPR Özellikleri:**
- **Veri Saklama**: Yapılandırılabilir veri saklama süresi (varsayılan: 2550 gün)
- **Veri Anonimleştirme**: Silme yerine kullanıcı verilerini anonimleştirme
- **Veri Dışa Aktarma**: Müşteri tüm verilerini dışa aktarabilir
- **Veri Silme**: Müşteri hesap silme talebinde bulunabilir
- **Onay Yönetimi**: Kullanıcı onaylarını takip ve yönetme
- **Gizlilik Kontrolleri**: Müşteri panelinde gizlilik kontrolleri

**Müşteri Panelindeki Gizlilik Kontrolleri:**
- Kişisel verileri dışa aktar (JSON formatı)
- Veri işleme onayını geri çek
- Hesabı ve ilişkili tüm verileri sil
- Gizlilik politikasını görüntüle

**Otomatik Temizlik:**
- Aktif olmayan kullanıcıların planlı temizliği
- Eski tamamlanmış/iptal edilmiş rezervasyonları temizleme
- Saklama süresi ayarlarına uyar
- Silmeden önce anonimleştirme seçeneği

---

## 🏗 Lisans Yönetimi

MHM Rentiva, Lite (ücretsiz) ve Pro (ücretli) sürümlerle bir **freemium modeli** kullanır. Eklenti lisans durumunu otomatik algılar ve özellikleri buna göre etkinleştirir/kısıtlar.

### Lisans Genel Bakış

**Lisans Aktivasyonu:**
- **Konum**: `Rentiva > Lisans`
- **Lisans Anahtarı Formatı**: Tireli alfanümerik (örn. `XXXX-XXXX-XXXX-XXXX`)
- **Aktivasyon Süreci**:
  1. Lisans sayfasında lisans anahtarını girin
  2. "Lisansı Aktive Et" butonuna tıklayın
  3. Sistem lisans sunucusuyla doğrular
  4. Pro özellikleri otomatik olarak etkinleştirilir
- **Lisans Doğrulama**: WordPress cron ile otomatik günlük doğrulama
- **Lisans Sona Erme**: Sona erme tarihinden 14 gün önce uyarı gösterilir

**Lisans Sunucu Entegrasyonu:**
- **API Endpoint'leri**:
  - `/licenses/activate` — Lisansı aktive et
  - `/licenses/validate` — Lisansı doğrula
  - `/licenses/deactivate` — Lisansı deaktive et
- **Site Hash**: Lisans bağlama için benzersiz site tanımlayıcısı
- **Staging Desteği**: Staging ortamlarının otomatik algılanması
- **Çoklu Site Desteği**: Lisans WordPress multisite genelinde çalışır

**Geliştirici Modu:**
- **Otomatik Algılama**: Geliştirme ortamlarında Pro özellikleri otomatik etkinleştirilir
- **Algılama Kriterleri**:
  - Localhost alan adları (localhost, 127.0.0.1, ::1)
  - Yerel TLD'ler (.local, .test, .dev, .staging)
  - Geliştirme portları (8080, 8081, 3000, vb.)
  - XAMPP/WAMP/MAMP sunucu yazılımları
  - WordPress hata ayıklama modu (WP_DEBUG)
  - Geliştirme ortamı sabiti (WP_ENV)
- **Güvenlik**: Sadece localhost/geliştirme alan adlarında çalışır (güvenli)

### Lite Sürüm (Ücretsiz) - Özellik Sınırlamaları

**Miktar Sınırları:**
- **Araçlar**: Maksimum **5 araç** (yayında, beklemede ve özel durumdaki araçlar dahil)
- **Rezervasyonlar**: Maksimum **50 rezervasyon** (yayında, beklemede ve özel durumdaki rezervasyonlar dahil)
- **Müşteriler**: Maksimum **10 müşteri** (rezervasyonu olan WordPress kullanıcıları)
- **Ek Hizmetler**: Maksimum **4 ek hizmet**

**Ödeme Ağ Geçidi:**
- ✅ **Frontend Ödemeleri**: WooCommerce üzerinden (tüm ağ geçitleri desteklenir)
- ✅ **Manuel Ödemeler**: Yerel çevrim dışı ödeme (sadece yönetici)

**Dışa Aktarma Kısıtlamaları:**
- ✅ **CSV Dışa Aktarma**: Mevcut (tüm sürümlerde)
- ❌ **JSON Dışa Aktarma**: Mevcut değil (sadece Pro)

**Rapor Kısıtlamaları:**
- **Tarih Aralığı**: Maksimum **30 gün** (otomatik filtrelenir)
- **Rapor Satırları**: Dışa aktarma başına maksimum **500 satır**
- **Gelişmiş Raporlar**: Mevcut değil (sadece Pro)
- **Rapor Dışa Aktarma**: Sadece CSV ile sınırlı

**Mesajlaşma Sistemi:**
- ❌ **Müşteri Mesajlaşma**: Mevcut değil (sadece Pro)
- ❌ **Yönetici Mesajlaşma**: Mevcut değil (sadece Pro)
- ❌ **Mesaj İş Parçacıkları**: Mevcut değil (sadece Pro)

**Diğer Sınırlamalar:**
- **Gelişmiş Raporlar Özelliği**: Mevcut değil (sadece temel raporlar)
- **Rapor Dışa Aktarma Formatları**: Lite raporlar için sadece CSV ile sınırlı

**Lite Sürüm Kısıtlama Arayüzü:**
- Yönetici bildirimleri mevcut kullanımı gösterir (örn. "5/5 araç kullanıldı")
- Limit dolduğunda "Yeni Ekle" düğmeleri gizlenir
- Pro'ya kilitli bölümler "Pro" rozeti gösterir
- Yönetici arayüzü boyunca yükseltme istemleri

### Pro Sürüm - Tam Özellik Erişimi

**Sınırsız Miktarlar:**
- **Araçlar**: **Sınırsız** araç
- **Rezervasyonlar**: **Sınırsız** rezervasyon
- **Müşteriler**: **Sınırsız** müşteri
- **Ek Hizmetler**: **Sınırsız** ek hizmet

**Tüm Ödeme Ağ Geçitleri:**
- ✅ **Frontend Ödemeleri**: WooCommerce üzerinden (tüm ağ geçitleri desteklenir)
- ✅ **Manuel Ödemeler**: Yerel çevrim dışı ödeme (sadece yönetici)

**Dışa Aktarma Formatları:**
- ✅ **CSV Dışa Aktarma**: Mevcut (tüm sürümlerde, Excel uyumluluğu için UTF-8 BOM dahil)
- ✅ **JSON Dışa Aktarma**: Mevcut (sadece Pro — metadata sarmalayıcı içeren yapılandırılmış veri ihracı)

**Gelişmiş Raporlar:**
- **Sınırsız Tarih Aralığı**: Tarih kısıtlaması yok (Lite: maks. 30 gün)
- **Sınırsız Satır**: Satır limiti yok (Lite: maks. 500 satır)
- **Rapor Türleri**: Gelir, Rezervasyonlar, Araçlar, Müşteriler raporları
- **Kontrol Paneli Widget'ları**: İstatistik ve gelir grafik widget'ları
- **Çoklu Format Dışa Aktarma**: Raporları CSV veya JSON olarak dışa aktarma
- **Rapor Önbelleği**: Performans için otomatik önbellekleme

**Mesajlaşma Sistemi:**
- ✅ **Müşteri Mesajlaşma**: Ön yüz müşteri mesaj arayüzü
- ✅ **Yönetici Mesajlaşma**: Yönetici mesaj yönetim arayüzü
- ✅ **Mesaj İş Parçacıkları**: İş parçacığı tabanlı konuşma sistemi (UUID tabanlı)
- ✅ **E-posta Bildirimleri**: Yeni mesajlar ve yanıtlar için otomatik e-posta bildirimleri
- ✅ **Mesaj Durumu**: Açık, Devam Ediyor, Kapalı durum yönetimi
- ✅ **Mesaj Kategorileri**: Kategorize edilmiş mesaj organizasyonu (Genel, Destek, Rezervasyon, vb.)
- ✅ **Mesaj Önceliği**: Öncelik seviyeleri (Düşük, Normal, Yüksek, Acil)
- ✅ **REST API**: Mesaj işlemleri için REST endpoint'leri
- ✅ **Sınırsız Mesaj**: Pro sürümde mesaj sınırı yok

**Ek Pro Özellikleri:**
- **REST API Erişimi**: Entegrasyon için tam REST API endpoint'leri
- **E-posta Bildirimleri**: Rezervasyonlar için otomatik e-posta bildirimleri
- **Loglama Sistemi**: Hata ayıklama için kapsamlı loglama
- **GDPR Uyumluluğu**: Veri ihracı, anonimleştirme ve silme özellikleri
- **Veritabanı Bakımı**: Veritabanı temizliği için WP-CLI komutları
- **Cron İşleri**: Otomatik arka plan görevleri

### Özellik Karşılaştırma Tablosu

| Özellik | Lite Sürüm | Pro Sürüm |
|---------|------------|-----------|
| **Maksimum Araç** | 5 | Sınırsız |
| **Maksimum Rezervasyon** | 50 | Sınırsız |
| **Maksimum Müşteri** | 10 | Sınırsız |
| **Maksimum Ek Hizmet** | 4 | Sınırsız |
| **VIP Transfer Rotası** | 3 | Sınırsız |
| **Galeri Görseli** | 5 / Araç | Sınırsız |
| **Frontend Ödemeleri** | WooCommerce üzerinden | WooCommerce üzerinden |
| **Manuel Ödemeler** | Yerel Çevrim Dışı | Yerel Çevrim Dışı |
| **Dışa Aktarma Formatları** | Sadece CSV | CSV, JSON |
| **Rapor Tarih Aralığı** | Maks. 30 gün | Sınırsız |
| **Rapor Satırları** | Maks. 500 | Sınırsız |
| **Gelişmiş Raporlar** | ❌ | ✅ |
| **Mesajlaşma Sistemi** | ❌ | ✅ |
| **Vendor Pazaryeri** | ❌ | ✅ (Pro) |
| **Araç Yaşam Döngüsü Yönetimi** | ❌ | ✅ (Pro) |
| **API Erişimi** | Sınırlı | Tam REST API |

### Lisans Yönetim Sayfası

**Lisans Sayfası Konumu**: `Rentiva > Lisans`

**Lisans Durumu Görüntüleme:**
- Pro Lisans Aktif (yeşil rozet)
- Lite Sürüm (sarı rozet)
- Geliştirici Modu (bilgi rozeti)
- Lisans sona erme uyarıları
- Son doğrulama zaman damgası

**Lisans Eylemleri:**
- **Lisansı Aktive Et**: Lisans anahtarını girin ve aktive edin
- **Lisansı Deaktive Et**: Lisansı siteden kaldırın
- **Lisansı Doğrula**: Lisans durumunu manuel olarak kontrol edin
- **Lisansı Değiştir**: Eskisini deaktive edin, yenisini aktive edin

**Görüntülenen Lisans Bilgileri:**
- Lisans anahtarı (maskelenmiş)
- Lisans durumu (aktif/pasif)
- Sona erme tarihi
- Son doğrulama zamanı
- Site hash (destek için)

**Kısıtlama Uygulaması:**
- **Otomatik Limitler**: Sistem Lite limitlerinin aşılmasını engeller
- **Yönetici Bildirimleri**: Limitlere yaklaşıldığında uyarılar
- **Özellik Kapıları**: Pro özellikleri Lite'da otomatik olarak devre dışı
- **Arayüz Kaplamaları**: Pro'ya kilitli bölümler görsel olarak işaretlenir
- **Yükseltme İstemleri**: Pro'ya geçiş için net yollar

### Lisans Doğrulama

**Otomatik Doğrulama:**
- **Günlük Cron İşi**: Lisansı her 24 saatte bir doğrular
- **Aktivasyon Anında**: Aktive edildiğinde anında doğrular
- **Sayfa Yüklemede**: Yönetici panelinde lisans durumunu kontrol eder
- **Pro Özelliklerinden Önce**: Pro özellikleri etkinleştirmeden önce doğrular

**Doğrulama Süreci:**
1. Lisans anahtarını lisans sunucusuna gönderir
2. Site bağlama için site hash gönderir
3. Sunucu lisans durumunu doğrular
4. Lisans bilgilerini döner (durum, sona erme, plan)
5. Yerel lisans verilerini günceller
6. Özellikleri buna göre etkinleştirir/kısıtlar

**Site Bağlama:**
- **Site Hash**: Site URL'si, WordPress sürümü, PHP sürümünden üretilir
- **Lisans Paylaşımını Engeller**: Lisans belirli bir siteye bağlıdır
- **Staging Desteği**: Staging siteleri ayrı aktivasyon sayılmaz
- **Multisite**: Lisans ağ siteleri arasında çalışır

**Lisans Sona Erme:**
- **Uyarı Periyodu**: Sona erme tarihinden 14 gün önce uyarı gösterilir
- **Ağ Bağlantısı Ek Süresi**: Lisans sunucusuna geçici olarak ulaşılamadığında, son başarılı doğrulamadan itibaren 7 gün boyunca Pro özellikler çalışmaya devam eder (çevrim dışı tolerans). Bu süre sona erme tarihini uzatmaz.
- **Sona Erme İşlemi**: Sona erme tarihi geçtiğinde Pro özellikler anında devre dışı kalır (lisans kaydı yenileme için saklanır)
- **Yenileme**: Lisans sayfasından yeni lisans anahtarı girerek tekrar aktive edin

### 💻 Geliştirici Modu

**Otomatik Geliştirici Modu:**
- **Amaç**: Geliştirme ortamında Pro özellikleri otomatik olarak etkinleştirir
- **Güvenlik**: Sadece localhost/geliştirme alan adlarında çalışır
- **Algılama**: Güvenilir algılama için çoklu kriterler
- **Lisans Gerekmez**: Geliştirme ortamında lisans anahtarına ihtiyaç duyulmaz

**Geliştirme Ortamı Algılaması:**
1. **Host Kontrolü**: localhost, .local, .test, .dev, .staging alan adları
2. **Sunucu Yazılımı**: XAMPP, WAMP, MAMP, LAMP algılaması
3. **Port Kontrolü**: Geliştirme portları (8080, 3000, vb.)
4. **WordPress Hata Ayıklama**: WP_DEBUG sabit kontrolü
5. **Ortam Sabiti**: WP_ENV development kontrolü

**Geliştirici Modu Özellikleri:**
- Tüm Pro özellikleri etkinleştirilir
- Miktar sınırı yok
- Tüm ödeme ağ geçitleri kullanılabilir (WooCommerce ile)
- CSV ve JSON dışa aktarma formatları kullanılabilir
- Tam mesajlaşma sistemi
- Gelişmiş raporlar etkin

**Güvenlik Konuları:**
- Sadece localhost/geliştirme alan adlarında çalışır
- Üretim sitelerinde aktive edilemez
- Lisans anahtarı paylaşımını engeller
- Güvenli otomatik algılama

### Lisans Sorun Giderme

**Sık Karşılaşılan Sorunlar:**

1. **Lisans Aktive Olmuyor**
   - Lisans anahtarı formatını kontrol edin
   - Lisans sunucusu bağlantısını doğrulayın
   - Site hash üretimini kontrol edin
   - Staging ortam algılamasını doğrulayın

2. **Özellikler Etkinleşmiyor**
   - Lisansı manuel olarak doğrulayın
   - Lisans sona ermesini kontrol edin
   - Site hash eşleşmesini doğrulayın
   - Geliştirici modu durumunu kontrol edin

3. **Lisans Sona Ermesi**
   - Lisansı sona ermeden önce yenileyin
   - Lisans sayfasında sona erme tarihini kontrol edin
   - Yenileme sorunları için destek ile iletişime geçin

4. **Staging Ortamı**
   - Staging siteleri otomatik algılanır
   - Ayrı aktivasyon olarak sayılmazlar
   - Lisans staging sitelerinde çalışır

**Destek:**
- Lisans sorunları: Lisans anahtarınızla destek ekibine başvurun
- Özellik soruları: Özellik karşılaştırma tablosunu kontrol edin
- Yükseltme talepleri: Yükseltme seçenekleri için lisans sayfasını ziyaret edin

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
- Panel sayfası (`[rentiva_user_dashboard]` kullanın - Giriş/Kayıt ve Hesap yönetimi için)
- Rezervasyon Formu sayfası (`[rentiva_booking_form]` kullanın)
- Araç Listesi/Grid sayfası (`[rentiva_vehicles_grid]` veya `[rentiva_vehicles_list]`)

**İsteğe Bağlı Sayfalar:**
- Arama sayfası (`[rentiva_unified_search]` kullanın)
- İletişim sayfası (`[rentiva_contact]` kullanın)
- VIP Transfer Arama (`[rentiva_transfer_search]` kullanın)

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
- `[rentiva_user_dashboard]` — Müşteri/Vendor ana dashboard'u.
- `[rentiva_my_bookings]` — Müşterinin mevcut ve geçmiş rezervasyonları.
- `[rentiva_my_favorites]` — Favoriye eklenen araçlar listesi.
- `[rentiva_payment_history]` — Ödeme geçmişi ve makbuz detayları.
- `[rentiva_messages]` — Müşteri ve yönetici arası mesajlaşma (Pro).

### Vendor & Transfer
- `[rentiva_vendor_apply]` — Yeni bayi (vendor) başvuru formu.
- `[rentiva_vehicle_submit]` — Frontend üzerinden araç ekleme/düzenleme (Vendor).
- `[rentiva_vendor_ledger]` — Bayi finansal dökümü ve bakiye tablosu (Vendor).
- `[rentiva_transfer_search]` — VIP Transfer / Şoförlü hizmet arama formu.
- `[rentiva_transfer_results]` — Transfer arama sonuçları sayfası.

---

## 🔌 REST API Dokümantasyonu

> **Lite:** Sınırlı API erişimi. **Pro:** Tüm endpointlere tam erişim.

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
- **PHPUnit**: 1.352 test / 4.053 doğrulama (v4.62.2 — son stabil sürüm).
- **CI Matrisi**: PHP 8.1 / 8.2 / 8.3 × WP 6.7 / latest = 6 paralel iş.
- **PHPCS**: Tam WordPress Coding Standards uyumluluğu (0 hata).
- **Test Yönetim Sayfası**: Rentiva menüsünden erişilebilir, raporlar indirilebilir.
- **Belgelenmiş Baseline**: 7 hata (saas_block ortam kotası — kapsam dışı, kasıtlı), 15 atlanan test.

### ⚓ Geliştirici Kancaları (Hooks)

#### Önemli Filtreler (Filters)
- `mhm_rentiva_lite_max_vehicles` — Lite sürümdeki araç limitini filtreler.
- `mhm_rentiva_currency_symbols` — Desteklenen para birimi sembollerini değiştirir.
- `mhm_rentiva_attribute_registry` — Araç özellik listesini genişletir.
- `mhm_rentiva_location_types` — Transfer lokasyon tiplerini düzenler.
- `mhm_rentiva_dashboard_kpis` — Dashboard istatistik panellerini filtreler.

#### Önemli Aksiyonlar (Actions)
- `mhm_rentiva_booking_created` — Yeni rezervasyon oluşturulduğunda tetiklenir.
- `mhm_rentiva_booking_status_changed` — Rezervasyon durumu değiştiğinde tetiklenir.
- `mhm_rentiva_vendor_approved` — Bayi başvurusu onaylandığında tetiklenir.
- `mhm_rentiva_vehicle_approved` — Araç ilanı onaylandığında tetiklenir.
- `mhm_rentiva_email_sent` — Sistem tarafından bir e-posta gönderildiğinde tetiklenir.

---

## ⚛ Modern React Admin Arayüzü

Tüm büyük admin sayfaları eski jQuery/WP_List_Table altyapısından REST API destekli React SPA'larına geçirildi. Geçiş v4.49.0 itibarıyla tamamlandı.

**Geçirilen Sayfalar:**

| Sayfa | Sürüm | React Bileşenleri | REST Uç Noktaları |
| :--- | :---: | :--- | :--- |
| **Dashboard** | v4.36.0 | DashboardPage, StatsCards, RecentBookings, TransferWidget, QuickActions | `/mhm-rentiva/v1/dashboard/*` |
| **Raporlar** | v4.37.x | ReportsPage, BookingsTab, RevenueTab, VehiclesTab, CustomersTab (Chart.js) | `/mhm-rentiva/v1/reports/*` |
| **Müşteriler** | v4.39.0 | CustomerTable, CustomerPanel, SearchBar, FilterBar, Pagination | `/mhm-rentiva/v1/customers`, `/customers/{id}`, `/customers/bulk` |
| **Mesajlar** | v4.40.0–v4.41.0 | MessagesPage, MessageTable, ThreadView, SettingsView | `/mhm-rentiva/v1/messages/*` |
| **Bayi Raporlar** | v4.40.0 | VendorReportsPage, FilterBar, ReportTable, DetailView, ActionForm | `/mhm-rentiva/v1/vendor-reports`, `/vendor-reports/{id}` |
| **Bayi Yönetim** | v4.40.0 | VendorManagementPage, ApplicationTable, ApplicationDetailPage, IbanRequestsTab | `/mhm-rentiva/v1/vendor-management/*` |

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




