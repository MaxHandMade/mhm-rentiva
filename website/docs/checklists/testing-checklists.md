# MHM Rentiva – Test Kontrol Listeleri

Bu doküman, canlıya çıkmadan önce yapılacak **lokal kabul testleri** ve canlı ortamda uygulanacak **hızlı doğrulama** adımlarını tek dosyada toplar. Her adımı tamamladıkça checkbox’ları işaretleyerek ilerleyebilirsiniz.

---

## ✅ Kurulum Öncesi Hızlı Kontrol
- [ ] `Settings > Shortcode Pages` ekranında tüm zorunlu sayfalar seçili (özellikle `[rentiva_booking_confirmation]`).
- [ ] `Pages` menüsünde “Booking Confirmation” sayfası yayımlı ve kısa kodu içeriyor.
- [ ] Page cache (ShortcodeUrlManager) temizlendi ya da sayfalar kaydedilerek URL’ler güncellendi.

## ✅ Lokal Kabul Testleri

### 1. Araç Yönetimi
- [x] En az 3 farklı araç oluştur (kategori, fiyat, özellik kombinasyonları değişken olmalı).
- [x] Her araç için galeriye 2–3 görsel ekle ve sürükle-bırak sıralamayı doğrula.
- [x] `Vehicle Settings` ekranında özellik/ekipman aç-kapa yap, araç düzenleme ekranında yansımasını kontrol et.
- [x] `Vehicle Display > Visible Card Items` panelinde alanları sürükle-bırak yap, grid/list/favorilerde sıra ve görünürlük değişikliklerinin yansıdığını doğrula.
- [x] Araç liste ekranında “Quick Edit” ile fiyat veya durum güncelle; değişikliğin kaydedildiğini doğrula.

### 2. Rezervasyon Akışı
- [ ] Frontend’de araç detay sayfasından rezervasyon oluştur; admin panelde doğru statü ile görünüyor mu bak.
- [ ] Admin panelden manuel rezervasyon oluştur; fiyat ve müşteri/araç atamasını doğrula.
- [ ] Rezervasyon statülerini sırayla değiştir (`Pending → Confirmed → Completed` vb.); ilgili e-posta ve log kayıtlarının tetiklendiğini doğrula.
- [ ] `Bookings > Calendar` görünümünde yeni rezervasyon tarihleri doğru şekilde listeleniyor mu kontrol et.

### 3. Ödeme Senaryoları
- [ ] Stripe sandbox anahtarlarıyla test ödemesi yap; booking ve ödeme statülerinin güncellendiğini doğrula.
- [ ] PayPal sandbox ile ödeme gerçekleştir; iade akışını da test et.
- [ ] Offline ödeme için makbuz yükle; admin onay/red akışının ve e-postaların çalıştığını kontrol et.
- [ ] Depozito tanımlayıp kısmi ödeme + kalan tutar tahsilat senaryosunu çalıştır.

### 4. Müşteri Portalı
- [ ] Frontend’de yeni müşteri kaydı yap (veya rezervasyon sırasında açılan hesabı kullan).
- [ ] `[rentiva_my_account]` panelinde rezervasyon geçmişi, favoriler, ödeme geçmişi ve mesaj fonksiyonlarını test et.
- [ ] Profil bilgisi güncelle, şifre değiştir, çıkış/yeniden giriş yaparak doğrula.

### 5. Mesajlaşma ve Bildirimler
- [ ] Müşteri panelinden yeni mesaj gönder; admin tarafında konu/kategori/durum doğru görünüyor mu kontrol et.
- [ ] Admin’den yanıt ver; müşterinin portal ve e-posta bildirimlerini aldığını doğrula.
- [ ] Mesaj statülerini (pending/answered/closed) değiştirip filtrelerin çalıştığını test et.

### 6. E-posta Şablonları
- [ ] Rezervasyon, ödeme, mesaj ve kayıt süreçlerinde ilgili e-postaların tetiklendiğini denetle (mail log/SMTP).
- [ ] `Email Templates` ekranında bir şablonu düzenle; yeni e-postalarda güncel metnin geldiğini kontrol et.
- [ ] Test e-postası gönder özelliğiyle SMTP ayarlarını doğrula.

### 7. Raporlama ve Export
- [ ] Dashboard KPI’larında oluşturduğun rezervasyon/ödemeler grafiğe yansıyor mu kontrol et.
- [ ] `Reports` sekmesinde tarih filtresi ve grafiklerin çalıştığını test et.
- [ ] `Export` ekranından Booking verilerini CSV (ve gerekiyorsa diğer formatlarda) dışa aktar; dosya içeriğini incele.

### 8. Add-on Hizmetleri
- [ ] `Additional Services` menüsünden yeni bir addon oluştur, aktif/pasif durumlarını test et.
- [ ] Rezervasyon formunda addon seçimlerinin toplam fiyata yansıdığını ve booking detayında göründüğünü doğrula.

### 9. REST & Shortcode Senaryoları (Opsiyonel)
- [ ] REST API endpoint’lerini (`/wp-json/mhm-rentiva/v1/...`) test token ile çağır; yetkilendirme ve cevap yapısını doğrula.
- [ ] Temel shortcode’ları (`availability calendar`, `search`, `comparison`, `my account`) demo sayfalarında çalıştır.

### 10. Bakım ve Sistem Araçları
- [ ] `Settings > Database Cleanup` sayfasında “dry-run” çalıştır, raporu incele; gerçek temizlikten önce yedek planını kontrol et.
- [ ] `Cron Monitor` ve `System Info` ekranlarında hata veya eksik cron var mı denetle.
- [ ] WP-CLI varsa `wp mhm-rentiva cleanup analyze` gibi komutların sorunsuz çalıştığını test et.

### 11. Performans & Güvenlik Kontrolleri
- [ ] Sayfa kaynaklarında gereksiz CSS/JS yüklenmediğini kontrol et (özellikle frontend kısa kod sayfalarında).
- [ ] Form gönderimlerinde nonce/sanitize/escape yapılarının hata üretmediğini debug log üzerinden izle.
- [ ] PHP 8.1+ uyumluluğu için hata log’larını takip ederek kritik sayfalarda gezin.

---

## 🚀 Canlı Ortam Hızlı Doğrulama (10 Dakika)

### Stripe
- [ ] Test Mode **ON**; test anahtarları (Publishable/Secret + webhook secret) girildi.
- [ ] Yeni rezervasyon → Stripe ödeme sayfası açılıyor.
- [ ] Başarılı dönüşte booking = confirmed, payment = paid.
- [ ] İptal/başarısız dönüşte hata gösteriliyor, payment = failed.
- [ ] Webhook olayları (`payment_intent.succeeded/failed`) işleniyor.

### PayPal
- [ ] Sandbox **ON**; Client ID/Secret girildi.
- [ ] Checkout başarıyla açılıyor; success/cancel URL’leri çalışıyor.
- [ ] Başarılı dönüşte booking = confirmed, payment = paid.

### PayTR
- [ ] Test mode **ON**; Merchant Id/Key/Salt girildi.
- [ ] İframe açılıyor ve sonuçlar işleniyor.
- [ ] 3D/Non-3D ve taksit ayarları beklediğin gibi.
- [ ] Notify/Webhook (varsa) çalışıyor.

### Offline Payment
- [ ] “Activate offline payment method” seçeneği aktif.
- [ ] Rezervasyon sonrası ödeme talimatları doğru görüntüleniyor.
- [ ] Makbuz yüklenince admin e-postası geliyor.
- [ ] Admin onay/red sonrası müşteri bilgilendirme e-postası gidiyor.

### Email & Notifications
- [ ] Email Sending Enabled = **ON**.
- [ ] Test Mode = **ON** ve test adresi tanımlı.
- [ ] Booking created → müşteri/admin e-postaları test adresine düşüyor.
- [ ] Status change (confirmed → completed) → status_changed e-postaları geliyor.
- [ ] Cancellation → cancellation e-postası geliyor.
- [ ] Reminder scheduler: “Reminder Hours Before” için cron kayıtlı ve çalışıyor.

### Genel Doğrulamalar
- [ ] Booking formda online/offline ödeme görünürlüğü gateway durumuna göre doğru.
- [ ] Payment Settings kaydedildiğinde diğer sekmelerdeki ayarlar korunuyor.
- [ ] Booking list filtreleri, bulk actions ve Empty Trash çalışıyor.
- [ ] Takvim prev/next butonları çalışıyor; renk/legend sabit.
- [ ] `debug.log` dosyasında fatal/notice yok; nonce hatası yok.

> **Not:** Canlı anahtarlarla production testine geçmeden önce Test Mode’u kapatmayı unutma.
