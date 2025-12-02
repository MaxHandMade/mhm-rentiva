# MHM Rentiva - Test Kılavuzu

## 🎯 Test Amacı

Bu kılavuz, XAMPP localhost ortamında MHM Rentiva eklentisini test etmek için hazırlanmıştır. Amaç, kullanıcıların karşılaşabileceği bug'lar, hatalar ve kullanıcı deneyimi sorunlarını tespit etmektir.

---

## 🚀 Hızlı Başlangıç

### 1. Hızlı Test Scripti

En hızlı yöntem, otomatik test scriptini kullanmaktır:

1. **Tarayıcıda şu URL'yi açın:**
   ```
   http://localhost/otokira/wp-content/plugins/mhm-rentiva/quick-test.php
   ```

2. **Script otomatik olarak şunları kontrol eder:**
   - ✅ Eklenti durumu
   - ✅ Developer Mode
   - ✅ Custom Post Types
   - ✅ Taxonomies
   - ✅ User Roles
   - ✅ Veritabanı durumu
   - ✅ Settings
   - ✅ Shortcode'lar
   - ✅ Gerekli sayfalar
   - ✅ Asset dosyaları
   - ✅ PHP extension'lar
   - ✅ Hata logları

3. **Sonuçları kontrol edin:**
   - Yeşil ✅ = Başarılı
   - Kırmızı ❌ = Hata var
   - Sarı ⚠️ = Uyarı

**⚠️ GÜVENLİK UYARISI:** Test sonrası `quick-test.php` dosyasını silin veya erişimi kısıtlayın!

---

## 📋 Detaylı Test Süreci

### Adım 1: İlk Kurulum Testi

1. **WordPress Admin Panel'e giriş yapın**
2. **Eklentiyi aktifleştirin:**
   - `Eklentiler > Yüklü Eklentiler`
   - "MHM Rentiva" eklentisini bulun
   - "Etkinleştir" butonuna tıklayın

3. **Kontrol edin:**
   - ✅ Aktivasyon sırasında hata var mı?
   - ✅ Setup wizard otomatik açıldı mı?
   - ✅ Admin notice'lar görünüyor mu?

### Adım 2: Developer Mode Kontrolü

XAMPP localhost ortamında Developer Mode otomatik aktif olmalıdır.

1. **License sayfasına gidin:**
   - `Rentiva > License`

2. **Kontrol edin:**
   - ✅ "Developer Mode" badge görünüyor mu?
   - ✅ Pro özellikler aktif mi?
   - ✅ Sınırsız araç/rezervasyon limiti var mı?

3. **Eğer Developer Mode aktif değilse:**
   - Site URL'inin `localhost` veya `127.0.0.1` olduğundan emin olun
   - `wp-config.php` dosyasında `WP_DEBUG` aktif mi kontrol edin

### Adım 3: Setup Wizard Testi

1. **Setup Wizard'a gidin:**
   - `Rentiva > Setup Wizard`
   - Veya ilk aktivasyondan sonra otomatik yönlendirilirsiniz

2. **Her adımı test edin:**
   - **Step 1 - System Check:** Tüm kontroller "OK" olmalı
   - **Step 2 - License:** Developer Mode algılanmalı
   - **Step 3 - Pages:** Gerekli sayfaları oluşturun
   - **Step 4 - Email:** Email ayarlarını yapılandırın
   - **Step 5 - Frontend:** Currency ve date format seçin
   - **Step 6 - Summary:** Test suite'i çalıştırın

### Adım 4: Temel İşlevler Testi

#### 4.1 Araç Ekleme
1. `Vehicles > Add New` menüsüne gidin
2. Bir araç ekleyin:
   - Başlık, açıklama
   - Görseller (10'a kadar)
   - Fiyatlandırma
   - Araç özellikleri
3. Yayınlayın ve kontrol edin

#### 4.2 Rezervasyon Oluşturma
1. Frontend'de booking form sayfasına gidin
2. Bir rezervasyon oluşturun:
   - Tarih seçin
   - Araç seçin
   - Müşteri bilgilerini girin
3. Rezervasyonu tamamlayın

#### 4.3 Admin Panel Kontrolleri
- `Rentiva > Dashboard` - İstatistikler görünüyor mu?
- `Vehicles` - Araç listesi çalışıyor mu?
- `Bookings` - Rezervasyon listesi çalışıyor mu?
- `Settings` - Tüm tab'lar açılıyor mu?

### Adım 5: Frontend Testi

1. **Oluşturulan sayfaları ziyaret edin:**
   - My Account sayfası
   - Booking Form sayfası
   - Vehicles Grid sayfası

2. **Kontrol edin:**
   - ✅ Sayfalar yükleniyor mu?
   - ✅ Shortcode'lar çalışıyor mu?
   - ✅ Form'lar çalışıyor mu?
   - ✅ JavaScript hataları var mı? (F12 > Console)

### Adım 6: Otomatik Test Suite

1. **Test Suite sayfasına gidin:**
   - `Rentiva > 🧪 Test Suite`

2. **Tüm test suite'leri seçin:**
   - Activation Tests
   - Security Tests
   - Functional Tests
   - Performance Tests
   - Integration Tests

3. **"Run Tests" butonuna tıklayın**

4. **Sonuçları kontrol edin:**
   - Tüm testler geçiyor mu?
   - Hata varsa detayları inceleyin
   - Test raporunu indirin

---

## 🐛 Yaygın Hatalar ve Çözümleri

### Hata 1: "Plugin failed to load"
**Çözüm:**
- `wp-content/debug.log` dosyasını kontrol edin
- PHP versiyonunun 7.4+ olduğundan emin olun
- Eklentiyi yeniden yükleyin

### Hata 2: Developer Mode Aktif Değil
**Çözüm:**
- Site URL'inin `http://localhost/otokira` formatında olduğundan emin olun
- `wp-config.php` dosyasına ekleyin:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  ```

### Hata 3: Shortcode'lar Çalışmıyor
**Çözüm:**
- `Settings > Permalinks` sayfasına gidin
- "Save Changes" butonuna tıklayın (permalink yapısını yenileyin)
- Sayfaların oluşturulduğundan emin olun

### Hata 4: JavaScript Hataları
**Çözüm:**
- Browser console'u kontrol edin (F12 > Console)
- Asset dosyalarının yüklendiğinden emin olun
- jQuery'nin yüklü olduğundan emin olun

### Hata 5: Email Gönderilmiyor
**Çözüm:**
- Email ayarlarını kontrol edin (`Rentiva > Settings > Email`)
- WordPress email fonksiyonunun çalıştığını test edin
- Gerekirse SMTP plugin kullanın

### Hata 6: WooCommerce Entegrasyonu Çalışmıyor
**Çözüm:**
- WooCommerce'in kurulu ve aktif olduğundan emin olun
- `Rentiva > Settings > Payment Settings` sayfasında WooCommerce durumunu kontrol edin
- WooCommerce > Settings > Payments sayfasında en az bir gateway'in aktif olduğundan emin olun
- Browser console'u kontrol edin (F12 > Console)

---

## 📝 Test Raporu Oluşturma

### Manuel Test Raporu

`TEST-CHECKLIST.md` dosyasını kullanarak:

1. Her test maddesini kontrol edin
2. Bulduğunuz hataları not edin
3. Screenshot'lar ekleyin
4. Test raporunu doldurun

### Otomatik Test Raporu

1. Test Suite'i çalıştırın
2. "Download Report" butonuna tıklayın
3. HTML veya JSON formatında indirin

---

## ✅ Test Tamamlama Kontrolü

Test sürecini tamamladığınızda şunları kontrol edin:

- [ ] Tüm temel işlevler test edildi
- [ ] Bulunan hatalar dokümante edildi
- [ ] Screenshot'lar alındı
- [ ] Test raporu oluşturuldu
- [ ] `quick-test.php` dosyası silindi (güvenlik)

---

## 🔍 Debug İpuçları

### WordPress Debug Log
```php
// wp-config.php dosyasına ekleyin
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Log dosyası: `wp-content/debug.log`

### Browser Console
- F12 tuşuna basın
- Console tab'ını açın
- JavaScript hatalarını kontrol edin

### Network Tab
- F12 > Network tab
- Asset dosyalarının yüklendiğini kontrol edin
- 404 hatalarını kontrol edin

---

## 📞 Destek

Test sırasında sorun yaşarsanız:

1. `TEST-CHECKLIST.md` dosyasını kontrol edin
2. `quick-test.php` scriptini çalıştırın
3. Debug log'u kontrol edin
4. Geliştiriciye rapor edin

---

**İyi testler! 🚀**

