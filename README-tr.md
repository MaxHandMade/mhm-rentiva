# MHM Rentiva - WordPress Araç Kiralama Eklentisi

<div align="right">

**🌐 Dil / Language:** 
[![TR](https://img.shields.io/badge/Language-Turkce-red)](README-tr.md) 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![Degisiklikler TR](https://img.shields.io/badge/Changelog-TR-orange)](changelog-tr.json) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json)

</div>

![Version](https://img.shields.io/badge/version-4.26.1-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
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
- **WooCommerce Hesabım Entegrasyonu**: Müşteriler standart WooCommerce "Hesabım" sayfasını kullanır; eklenti bu sayfaya Rezervasyonlarım, Favorilerim, Ödeme Geçmişi ve Mesajlar gibi özel sekmeler ekler
- **Vendor Marketplace** *(Pro)*: Araç sahiplerinin platforma başvurmasına, araçlarını frontend üzerinden listelemesine (araç gönderme formu), finansal hareketlerini takip etmesine ve vendor dashboard üzerinden durumlarını görüntülemesine olanak tanıyan çok satıcılı pazar yeri sistemi
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
- **Pazar Yeri İşletmeleri**: Vendor Marketplace ile çok satıcılı araç kiralama platformu kurun *(Pro)*
- **Çeviriye Hazır**: Eklenti İngilizce ve Türkçe ile gelir; WooCommerce uyumlu para birimi desteği. Loco Translate üzerinden her dile çevrilebilir

---

## ✨ Temel Özellikler

### 🚗 Araç Yönetim Sistemi

**Temel Araç Özellikleri:**
- **Özel Post Tipi**: Araçlar için yerel WordPress post tipi
- **Araç Galerisi**: WordPress Medya Kütüphanesi entegrasyonu ile görsel yükleme (Lite: 3 görsel / Pro: 50'ye kadar, ayarlanabilir)
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
- **Komisyon Politikası**: Platforma kayıtlı satıcılar için zaman bazlı komisyon oranı tanımlanabilir (`CommissionPolicy`).
- **Kademe Sistemi**: Hacim bazlı komisyon indirimi — satıcı ne çok kazanırsa komisyon oranı o kadar düşer (`TierService`).
- **Finansal Defter (Ledger)**: Satıcı bazında tüm kazanç ve kesinti kayıtları izlenebilir.
- **Ödeme Yönetimi (Payout)**: Yönetici satıcılara ödeme işleyebilir; tüm hareketler loglanır.

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

| Özellik | Lite (Ücretsiz) | Pro (Premium) |
| :--- | :--- | :--- |
| **Maksimum Araç** | 5 Araç | **Sınırsız** |
| **Maksimum Rezervasyon** | 50 Rezervasyon | **Sınırsız** |
| **Maksimum Müşteri** | 10 Müşteri | **Sınırsız** |
| **Ek Hizmetler** | 4 Hizmet | **Sınırsız** |
| **VIP Transfer Rotası** | 3 Rota | **Sınırsız** |
| **Galeri Resmi** | 5 Resim / Araç | **Sınırsız (ayarlanabilir)** |
| **Rapor Tarih Aralığı** | Son 30 Gün | **Sınırsız** |
| **Rapor Satır Limiti** | 500 Satır | **Sınırsız** |
| **Mesajlaşma Sistemi** | ❌ Yok | ✅ Var |
| **Vendor & Payout** | ❌ Yok | ✅ Var |
| **E-posta Bildirimleri** | ✅ Var | ✅ Var |
| **Dışa Aktarım** | Sadece CSV | CSV + JSON |
| **Ödeme Altyapısı** | WooCommerce | WooCommerce |
| **REST API Erişimi** | Sınırlı | Tam Erişim |
| **Gelişmiş Raporlar** | ❌ Sınırlı | ✅ Tam Erişim |

> **Not:** Lite sürümü küçük işletmeler ve test amaçlı tasarlanmıştır. Sınırsız erişim için Pro sürüme geçiş yapın.

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
- Başvuru alındı (satıcıya + admin)
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

**Çok Satıcılı Yönetim:**
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
- `[rentiva_vendor_apply]` — Yeni satıcı (vendor) başvuru formu.
- `[rentiva_vehicle_submit]` — Frontend üzerinden araç ekleme/düzenleme (Vendor).
- `[rentiva_vendor_ledger]` — Satıcı finansal dökümü ve bakiye tablosu (Vendor).
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
- **Test Edilen**: 6.9
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
- `mhm_rentiva_vendor_approved` — Satıcı başvurusu onaylandığında tetiklenir.
- `mhm_rentiva_vehicle_approved` — Araç ilanı onaylandığında tetiklenir.
- `mhm_rentiva_email_sent` — Sistem tarafından bir e-posta gönderildiğinde tetiklenir.

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

### Sürüm 4.26.0 (07.04.2026)
- **Kalan Ödeme**: Depozito ile oluşturulan rezervasyonlarda müşteriler, Hesabım → Rezervasyon Detayı sayfasından "Kalan Ödemeyi Yap" butonuyla kalan bakiyeyi ödeyebilir.
- **WooCommerce Native Akışı**: Kalan tutar için programatik minimal WC siparişi oluşturulur, müşteri WC'nin native order-pay sayfasına yönlendirilir — aktif tüm ödeme altyapıları kod değişikliği gerektirmeden çalışır.
- **Tekrar Sipariş Koruması**: Bekleyen kalan ödeme siparişi varsa yeni sipariş oluşturulmaz, mevcut sipariş yeniden kullanılır.
- **Düzeltme**: CSS kapsam — tüm genel hesap sayfası sınıfları `.mhm-rentiva-account-page` sarmalayıcısı altına alındı; tema çakışmaları engellendi.

### Sürüm 4.25.1 (07.04.2026)
- **Düzeltme**: CSS kapsam — genel sınıflar `.mhm-rentiva-account-page` altına scope'landı; WoodMart ve diğer temalarda çakışma sorunu giderildi.
- **Düzeltme**: WC Hesabım ızgara düzeni düzeltmesi (`grid-column: 1/-1`).

### Sürüm 4.23.0 (26.03.2026)
- **Mimari**: Vendor Transfer Lokasyon sistemi — Şehir→Nokta hiyerarşisi
- **Fiyatlandırma**: Vendor rota bazlı fiyatlandırma (admin min/max aralığında)
- **Arama**: Transfer arama motoru rota bazlı araç filtreleme + vendor fiyatı
- **Veritabanı**: v3.4.0 migration (lokasyonlara city, rotalara max_price sütunu)
- **Dashboard**: 11 widget düzeltmesi (timezone, cache, WC email, istatistik tasarımı, Lite gating)
- **Dışa Aktarım**: 4 hata düzeltmesi (post_type, kayıt sayısı, geçmiş silme, PHP 8 strict types)
- **Elementor**: 7 widget attribute iyileştirmesi
- **Test**: 567 test, 2036 assertion

### Sürüm 4.22.2 (25.03.2026)
- **Bildirimler**: Tüm Lite limit bildirimleri birleşik yüzde formatında standartlaştırıldı
- **Limitler**: Galeri görselleri limiti 5'e güncellendi, karşılaştırma tablosu yeniden tasarlandı
- **Düzeltmeler**: Emoji bozulması ve dışa aktarım özellik hataları düzeltildi

### Sürüm 4.22.0 (24.03.2026)
- **Denetim**: Kapsamlı AllowlistRegistry, BlockRegistry ve Elementor widget denetimi
- **Test**: 13 shortcode render test dosyası, 4 SettingsSanitizer test dosyası
- **Düzeltmeler**: 6 PHPUnit hatası çözüldü, block.json varsayılanları düzeltildi

### Sürüm 4.21.2 (11.03.2026)
- **Güvenlik**: REST API altyapısı `SecurityHelper` ve `AuthHelper` ile zırhlandırıldı.
- **Shortcode'lar**: Tüm kısa kodlar `ShortcodeServiceProvider` üzerinden konsolide edildi.
- **Altyapı**: PHP 8.1+ ve WP 6.7+ gereksinimleri standardize edildi.
- **VIP Transfer**: Nokta-tabanlı rota fiyatlandırma motoru devreye alındı.

### Sürüm 4.9.8 (09.02.2026)

### Versiyon: 4.6.7 (2026-02-01)

**🛡️ GÜVENLİK & STANDARTLAR**
- **Güvenli Yönlendirme**: Proje genelinde yönlendirme güvenliğini artırmak için `wp_safe_redirect` kullanımına geçildi.
- **Varlık Standartları**: Yönetici paneli satır içi stilleri (inline css), resmi `wp_add_inline_style` API'sine taşındı.
- **Performans Senkronizasyonu**: Doğrudan SQL tabanlı önbellek temizliği, `delete_transient()` kullanan akıllı bir versiyonlama sistemine dönüştürüldü.
- **Güvenlik Yardımcısı**: `SecurityHelper::safe_output` güvenilir bağlam doğrulaması ve JSON desteğiyle modernize edildi.

### Versiyon: 4.6.6 (2026-01-28)

**🐛 HATA DÜZELTMELERİ & ARAYÜZ İYİLEŞTİRMELERİ**
- **Araç İkonları**: Rezervasyon formunda kaybolan araç özellik ikonları (yakıt, vites vb.) sorunu çözüldü.
- **Arayüz Optimizasyonu**: Rezervasyon formunda ikon boyutlandırması ve görsel sunum iyileştirildi.
- **Mantıksal Düzeltme**: Araç özellik SVG'leri için veri işleme mantığı düzeltildi.
- **Temizlik**: Tutarlı stil sağlamak için çakışan eski CSS kodları temizlendi.

### Versiyon: 4.6.5 (2026-01-26)

**🛡️ GÜVENLİK & STANDARTLAR**
- **WPCS Uyumluluğu**: Girdi Sanitizasyonu ve Veritabanı İnterpolasyonu ile ilgili 50'den fazla güvenlik sorunu çözüldü.
- **XSS Güçlendirme**: `Handler.php` ve `AccountController.php` gelişmiş XSS saldırılarına karşı korumaya alındı.
- **Otomatik Refactoring**: Proje genelinde 110.000'den fazla stil hatası, WordPress Kodlama Standartları (WPCS) ile tam uyum için otomatik olarak düzeltildi.
- **Log Motoru**: Eski `error_log` yapısından yeni yüksek performanslı `AdvancedLogger` sistemine geçildi.

### Versiyon: 4.6.4 (2026-01-26)

**🛡️ GÜVENLİK & VERİ BÜTÜNLÜĞÜ**
- **Çıktı Kaçırma**: Admin sekmeleri ve sistem bilgi ekranları `esc_html` ile güçlendirildi.
- **Sanitizasyon**: Meta kutularındaki fiyat ve ID alanları için sanitizasyon iyileştirildi.
- **Yerelleştirme**: Yönetim metinlerinin İngilizce çevirileri tamamlandı.

### Versiyon: 4.6.3 (2026-01-25)

**🛡️ GÜVENLİK & GÜVENİLİRLİK**
- **SQL Güçlendirme**: Mesaj aramalarında SQL Injection koruması.
- **AJAX Hook'ları**: Backend entegrasyon ayarları için güvenilirlik artırıldı.

### Versiyon: 4.6.2 (2026-01-21)

**🛡️ GÜVENLİK DENETİMİ**
- **Nonce Güçlendirme**: Proje genelinde WPCS uyumlu nonce doğrulaması uygulandı.

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
- **AJAX Arama**: Yeni `[rentiva_transfer_search]` shortcode'u.
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





