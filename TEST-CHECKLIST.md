# MHM Rentiva - Kapsamlı Test Kontrol Listesi

## 🎯 Test Amacı
Yeni bir WordPress kurulumunda kullanıcıların karşılaşabileceği bug'lar, hatalar ve kullanıcı deneyimi sorunlarını tespit etmek.

---

## ✅ 1. İLK KURULUM VE AKTİVASYON TESTLERİ

### 1.1 Eklenti Aktivasyonu
- [ ] Eklentiyi WordPress admin panelinden aktifleştir
- [ ] Aktivasyon sırasında hata mesajı var mı kontrol et
- [ ] PHP versiyon kontrolü çalışıyor mu? (PHP 7.4+ gerekli)
- [ ] Aktivasyon sonrası admin notice'lar görünüyor mu?
- [ ] Setup wizard otomatik yönlendirme çalışıyor mu?

**Kontrol Edilecekler:**
- `wp-content/debug.log` dosyasında hata var mı?
- WordPress admin panelinde kırmızı hata mesajı var mı?
- Browser console'da JavaScript hatası var mı?

### 1.2 Developer Mode Kontrolü
- [ ] Developer Mode otomatik aktif oldu mu? (XAMPP localhost için)
- [ ] License sayfasında "Developer Mode" badge görünüyor mu?
- [ ] Pro özellikler aktif mi? (sınırsız araç, rezervasyon, vb.)
- [ ] Developer Mode banner admin panelinde görünüyor mu?

**Kontrol Edilecekler:**
- `Rentiva > License` sayfasına git
- Developer Mode durumunu kontrol et
- Pro özelliklerin aktif olduğunu doğrula

### 1.3 Custom Post Types ve Taxonomies
- [ ] `vehicle` post type kayıtlı mı?
- [ ] `vehicle_booking` post type kayıtlı mı?
- [ ] `vehicle_category` taxonomy kayıtlı mı?
- [ ] Permalink yapısı doğru çalışıyor mu?

**Kontrol Edilecekler:**
- `Vehicles > Add New` menüsü görünüyor mu?
- `Bookings` menüsü görünüyor mu?
- URL rewrite rules çalışıyor mu?

---

## ✅ 2. SETUP WIZARD TESTLERİ

### 2.1 Setup Wizard Yönlendirme
- [ ] İlk aktivasyondan sonra setup wizard'a yönlendirildi mi?
- [ ] Setup wizard sayfası açılıyor mu?
- [ ] "Skip wizard" butonu çalışıyor mu?

**URL:** `wp-admin/admin.php?page=mhm-rentiva-setup`

### 2.2 System Check (Step 1)
- [ ] PHP versiyon kontrolü doğru mu?
- [ ] WordPress versiyon kontrolü doğru mu?
- [ ] Gerekli PHP extension'lar kontrol ediliyor mu?
- [ ] Memory limit kontrolü yapılıyor mu?
- [ ] Tüm kontroller "OK" durumunda mı?

### 2.3 License (Step 2)
- [ ] Developer Mode otomatik algılandı mı?
- [ ] License key girişi çalışıyor mu? (opsiyonel - dev mode varsa gerek yok)
- [ ] "Continue" butonu çalışıyor mu?

### 2.4 Required Pages (Step 3)
- [ ] Sayfa oluşturma butonları çalışıyor mu?
- [ ] Sayfalar başarıyla oluşturuldu mu?
- [ ] Shortcode'lar sayfalara doğru eklendi mi?

**Oluşturulacak Sayfalar:**
- Booking Form (`[rentiva_booking_form]`)
- Booking Confirmation (`[rentiva_booking_confirmation]`)
- My Account (Dashboard) (`[rentiva_my_account]`)
- My Bookings (`[rentiva_my_bookings]`)
- Favorites (`[rentiva_my_favorites]`)
- Payment History (`[rentiva_payment_history]`)
- Login Form (`[rentiva_login_form]`)
- Registration Form (`[rentiva_register_form]`)
- Contact Form (`[rentiva_contact]`)

### 2.5 Email Settings (Step 4)
- [ ] Email ayarları kaydediliyor mu?
- [ ] Email test gönderimi çalışıyor mu?
- [ ] Form validasyonu çalışıyor mu?

### 2.6 Frontend & Display (Step 5)
- [ ] Currency seçimi çalışıyor mu?
- [ ] Date format seçimi çalışıyor mu?
- [ ] Ayarlar kaydediliyor mu?

### 2.7 Summary & Tests (Step 6)
- [ ] Test suite çalışıyor mu?
- [ ] Tüm testler geçiyor mu?
- [ ] "Finish Setup" butonu çalışıyor mu?

---

## ✅ 3. ADMIN PANEL TESTLERİ

### 3.1 Menü Yapısı
- [ ] Tüm menü öğeleri görünüyor mu?
- [ ] Menü sıralaması doğru mu?
- [ ] Icon'lar görünüyor mu?

**Kontrol Edilecek Menüler:**
- Dashboard
- Vehicles
- Bookings
- Customers
- Addons
- Reports
- Settings
- License
- About

### 3.2 Dashboard Sayfası
- [ ] Dashboard açılıyor mu?
- [ ] İstatistik kartları görünüyor mu?
- [ ] Grafikler yükleniyor mu?
- [ ] JavaScript hataları var mı?

### 3.3 Settings Sayfası
- [ ] Tüm settings tab'ları açılıyor mu?
- [ ] Form kaydetme çalışıyor mu?
- [ ] Validation mesajları görünüyor mu?
- [ ] Nonce kontrolü çalışıyor mu?

**Settings Tab'ları:**
- General
- Booking
- Payment
- Email
- Frontend
- Integration
- Privacy
- Maintenance

---

## ✅ 4. ARAÇ (VEHICLE) YÖNETİMİ TESTLERİ

### 4.1 Araç Ekleme
- [ ] "Add New Vehicle" butonu çalışıyor mu?
- [ ] Form alanları görünüyor mu?
- [ ] Görsel yükleme çalışıyor mu? (10 görsele kadar)
- [ ] Drag & drop sıralama çalışıyor mu?
- [ ] Fiyatlandırma alanları çalışıyor mu?
- [ ] Meta box'lar görünüyor mu?

**Kontrol Edilecek Alanlar:**
- Başlık, açıklama
- Görseller (gallery)
- Fiyatlandırma (günlük, haftalık, aylık)
- Araç özellikleri (marka, model, yıl, yakıt, şanzıman)
- Depozito ayarları
- Müsaitlik durumu

### 4.2 Araç Listesi
- [ ] Araç listesi görünüyor mu?
- [ ] Filtreleme çalışıyor mu?
- [ ] Arama çalışıyor mu?
- [ ] Bulk actions çalışıyor mu?
- [ ] Quick edit çalışıyor mu?
- [ ] Pagination çalışıyor mu?

### 4.3 Araç Düzenleme
- [ ] Araç düzenleme sayfası açılıyor mu?
- [ ] Tüm alanlar kaydediliyor mu?
- [ ] Görsel güncelleme çalışıyor mu?
- [ ] Meta box'lar kaydediliyor mu?

---

## ✅ 5. REZERVASYON (BOOKING) SİSTEMİ TESTLERİ

### 5.1 Rezervasyon Oluşturma (Frontend)
- [ ] Booking form sayfası açılıyor mu?
- [ ] Tarih seçici çalışıyor mu?
- [ ] Araç seçimi çalışıyor mu?
- [ ] Müsaitlik kontrolü çalışıyor mu?
- [ ] Fiyat hesaplama doğru mu?
- [ ] Addon seçimi çalışıyor mu?
- [ ] Form gönderimi çalışıyor mu?

**Test Senaryosu:**
1. Frontend'de booking form sayfasına git
2. Tarih seç (bugünden ileri)
3. Araç seç
4. Müşteri bilgilerini gir
5. Rezervasyonu tamamla

### 5.2 Rezervasyon Yönetimi (Admin)
- [ ] Rezervasyon listesi görünüyor mu?
- [ ] Rezervasyon detay sayfası açılıyor mu?
- [ ] Durum değiştirme çalışıyor mu?
- [ ] Manuel rezervasyon oluşturma çalışıyor mu?
- [ ] Rezervasyon iptali çalışıyor mu?
- [ ] İade işlemi çalışıyor mu?

### 5.3 Müsaitlik Kontrolü
- [ ] Çakışan rezervasyonlar engelleniyor mu?
- [ ] Gerçek zamanlı müsaitlik kontrolü çalışıyor mu?
- [ ] Takvim görünümü çalışıyor mu?
- [ ] Müsait olmayan tarihler gösteriliyor mu?

---

## ✅ 6. ÖDEME SİSTEMİ TESTLERİ

### 6.1 WooCommerce Entegrasyonu (Zorunlu)
- [ ] WooCommerce kurulu ve aktif mi?
- [ ] WooCommerce entegrasyonu algılanıyor mu?
- [ ] Payment Settings sayfasında WooCommerce bilgilendirmesi görünüyor mu?
- [ ] Checkout sayfası çalışıyor mu?
- [ ] Ödeme gateway'leri görünüyor mu? (Stripe, PayPal, Bank Transfer, vb.)
- [ ] Rezervasyon durumu otomatik güncelleniyor mu?
- [ ] WooCommerce order oluşturuluyor mu?
- [ ] İade işlemleri WooCommerce üzerinden çalışıyor mu?

**Kontrol Edilecekler:**
- `Settings > Payment Settings` sekmesinde WooCommerce durumu
- WooCommerce > Settings > Payments sayfasında aktif gateway'ler
- Test rezervasyonu oluşturup WooCommerce order kontrolü

---

## ✅ 7. MÜŞTERİ (CUSTOMER) YÖNETİMİ TESTLERİ

### 7.1 Müşteri Hesabı Oluşturma
- [ ] Rezervasyon sırasında otomatik hesap oluşturuluyor mu?
- [ ] Email doğrulama çalışıyor mu?
- [ ] Customer rolü atanıyor mu?

### 7.2 My Account Sayfası
- [ ] My Account sayfası açılıyor mu?
- [ ] Dashboard görünüyor mu?
- [ ] Rezervasyon geçmişi görünüyor mu?
- [ ] Favoriler çalışıyor mu?
- [ ] Ödeme geçmişi görünüyor mu?
- [ ] Hesap detayları düzenlenebiliyor mu?

**Frontend URL:** Oluşturulan My Account sayfası

---

## ✅ 8. SHORTCODE TESTLERİ

### 8.1 Temel Shortcode'lar
- [ ] `[rentiva_vehicles_grid]` çalışıyor mu?
- [ ] `[rentiva_vehicles_list]` çalışıyor mu?
- [ ] `[rentiva_booking_form]` çalışıyor mu?
- [ ] `[rentiva_my_account]` çalışıyor mu?
- [ ] `[rentiva_search]` çalışıyor mu?

### 8.2 Shortcode Parametreleri
- [ ] Shortcode parametreleri çalışıyor mu?
- [ ] Hatalı parametrelerde hata mesajı gösteriliyor mu?

**Test Örneği:**
```
[rentiva_vehicles_grid columns="3" limit="12"]
```

---

## ✅ 9. EMAIL SİSTEMİ TESTLERİ

### 9.1 Email Şablonları
- [ ] Tüm email şablonları yükleniyor mu?
- [ ] Email preview çalışıyor mu?
- [ ] Email test gönderimi çalışıyor mu?

**Email Türleri:**
- Booking created (customer)
- Booking created (admin)
- Booking cancelled
- Booking status changed
- Payment received (WooCommerce)
- Refund emails (customer & admin)
- Welcome email

### 9.2 Email Gönderimi
- [ ] Rezervasyon oluşturulduğunda email gönderiliyor mu?
- [ ] Email log kaydediliyor mu?
- [ ] Email içeriği doğru mu?

---

## ✅ 10. REST API TESTLERİ

### 10.1 API Endpoint'leri
- [ ] REST API endpoint'leri kayıtlı mı?
- [ ] API key authentication çalışıyor mu?
- [ ] Availability endpoint çalışıyor mu?

**Test URL:**
```
/wp-json/mhm-rentiva/v1/availability?vehicle_id=1&start_date=2025-01-01&end_date=2025-01-05
```

### 10.2 API Key Yönetimi
- [ ] API key oluşturma çalışıyor mu?
- [ ] API key silme çalışıyor mu?
- [ ] API key listesi görünüyor mu?

---

## ✅ 11. PERFORMANS VE HATA KONTROLLERİ

### 11.1 JavaScript Hataları
- [ ] Browser console'da JavaScript hatası var mı?
- [ ] AJAX istekleri başarılı mı?
- [ ] Asset'ler yükleniyor mu? (CSS/JS)

**Kontrol:**
- F12 > Console tab
- Kırmızı hata mesajları var mı?

### 11.2 PHP Hataları
- [ ] `wp-content/debug.log` dosyasında hata var mı?
- [ ] Admin panelinde PHP notice/warning var mı?
- [ ] Fatal error var mı?

**Kontrol:**
```bash
# XAMPP'ta debug.log konumu
C:\xampp\htdocs\otokira\wp-content\debug.log
```

### 11.3 Database Kontrolleri
- [ ] Veritabanı tabloları oluşturuldu mu?
- [ ] Meta key'ler doğru kaydediliyor mu?
- [ ] Orphaned data var mı?

---

## ✅ 12. KULLANICI DENEYİMİ (UX) TESTLERİ

### 12.1 Admin Panel UX
- [ ] Menü navigasyonu sezgisel mi?
- [ ] Butonlar ve linkler çalışıyor mu?
- [ ] Loading indicator'lar görünüyor mu?
- [ ] Hata mesajları anlaşılır mı?
- [ ] Başarı mesajları görünüyor mu?

### 12.2 Frontend UX
- [ ] Sayfalar responsive mi?
- [ ] Form validasyonu kullanıcı dostu mu?
- [ ] Hata mesajları görünüyor mu?
- [ ] Loading state'ler çalışıyor mu?

### 12.3 Çoklu Dil Desteği
- [ ] Dil dosyaları yükleniyor mu?
- [ ] Çeviriler çalışıyor mu?
- [ ] Tarih/saat formatları doğru mu?

---

## ✅ 13. GÜVENLİK TESTLERİ

### 13.1 Input Sanitization
- [ ] Form input'ları sanitize ediliyor mu?
- [ ] SQL injection koruması var mı?
- [ ] XSS koruması var mı?

### 13.2 Nonce Kontrolü
- [ ] Tüm form'larda nonce var mı?
- [ ] Nonce doğrulaması çalışıyor mu?

### 13.3 Capability Kontrolü
- [ ] Yetki kontrolleri çalışıyor mu?
- [ ] Yetkisiz erişim engelleniyor mu?

---

## ✅ 14. OTOMATİK TEST SUİTE

### 14.1 Test Suite Çalıştırma
- [ ] Test suite sayfasına git: `Rentiva > 🧪 Test Suite`
- [ ] Tüm test suite'leri seç
- [ ] "Run Tests" butonuna tıkla
- [ ] Test sonuçlarını kontrol et

**Test Suite'ler:**
- Activation Tests
- Security Tests
- Functional Tests
- Performance Tests
- Integration Tests

### 14.2 Test Sonuçları
- [ ] Tüm testler geçiyor mu?
- [ ] Hata varsa detayları kontrol et
- [ ] Test raporunu indir

---

## 🐛 YAYGIN HATALAR VE ÇÖZÜMLERİ

### Hata 1: Eklenti Aktifleştirilemiyor
**Kontrol:**
- PHP versiyonu 7.4+ mı?
- WordPress versiyonu 5.0+ mı?
- `debug.log` dosyasını kontrol et

### Hata 2: Developer Mode Aktif Değil
**Kontrol:**
- Site URL'i localhost mu? (`http://localhost/otokira`)
- `wp-config.php`'de `WP_DEBUG` aktif mi?
- License sayfasında durumu kontrol et

### Hata 3: Shortcode'lar Çalışmıyor
**Kontrol:**
- Sayfalar oluşturuldu mu?
- Shortcode'lar sayfalara eklendi mi?
- Permalink yapısı doğru mu? (Settings > Permalinks > Save)

### Hata 4: JavaScript Hataları
**Kontrol:**
- Browser console'u kontrol et
- Asset'ler yükleniyor mu?
- jQuery yüklü mü?

### Hata 5: Email Gönderilmiyor
**Kontrol:**
- Email ayarları yapılandırıldı mı?
- WordPress email fonksiyonu çalışıyor mu?
- SMTP plugin gerekli mi?

### Hata 6: WooCommerce Entegrasyonu Çalışmıyor
**Kontrol:**
- WooCommerce kurulu ve aktif mi?
- Payment Settings sayfasında WooCommerce durumu kontrol et
- WooCommerce > Settings > Payments sayfasında gateway'ler aktif mi?
- Browser console'da JavaScript hatası var mı?

---

## 📝 TEST RAPORU ŞABLONU

### Test Tarihi: _______________
### Test Ortamı: XAMPP Localhost
### WordPress Versiyonu: _______________
### PHP Versiyonu: _______________
### Eklenti Versiyonu: 4.4.4

### Bulunan Hatalar:
1. 
2. 
3. 

### Bulunan UX Sorunları:
1. 
2. 
3. 

### Öneriler:
1. 
2. 
3. 

---

## ✅ TEST TAMAMLAMA KONTROLÜ

- [ ] Tüm testler tamamlandı
- [ ] Hatalar dokümante edildi
- [ ] Test raporu oluşturuldu
- [ ] Geliştiriciye rapor edildi

---

**Not:** Bu checklist'i test sırasında doldurun ve bulduğunuz her hatayı detaylı olarak not edin.

