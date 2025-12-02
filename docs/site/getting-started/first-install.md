# Rentiva’yı İlk Kez Kurarken 12 Adım

Bu rehber, Rentiva eklentisini sıfırdan kuran yöneticilerin ilk 30 dakikada tamamlaması gereken adımları sıralar. Her adım tamamlandığında bir sonraki aşamaya geçmeden önce sonuçları kontrol edin.

## 1. Ön Gereksinimler

- WordPress 5.0+ ve PHP 7.4+ sürümlerinin kurulu olduğundan emin olun.
- Permalink ayarının `Post name` veya benzeri SEO dostu bir seçenek olduğundan emin olun.
- SSL (https) aktif değilse, ödeme ağ geçitlerini test ederken sanal ortamınızı ssl ile çalıştırın.

## 2. Eklentiyi Yükleme

1. `Plugins > Add New > Upload Plugin` yolundan `mhm-rentiva.zip` dosyasını yükleyin.
2. Eklentiyi aktif edin; aktivasyon sonrası yönetim menüsünde **MHM Rentiva** menüsü görünecek.

## 3. Temel Ayar Sihirbazı (Opsiyonel)

Eğer sihirbaz açık ise sizi “Getting Started” uyarısı karşılar. Sihirbaz yoksa aşağıdaki adımları manuel uygulayın.

1. `MHM Rentiva > Settings` ekranına gidin.
2. Şirket adı, varsayılan para birimi, tarih/saat formatı gibi genel alanları doldurun.
3. “Kaydet” butonuna basın; bu nokta, her sekme için nonce doğrulamasının yapıldığından emin olur.

## 4. Zorunlu Sayfaların Oluşturulması

Rentiva 15’ten fazla kısa kod kullanır. `Pages > Add New` üzerinden aşağıdaki sayfaları oluşturun ve dokümandaki kısa kodları ekleyin:

| Sayfa Adı | Kısa Kod |
|-----------|----------|
| Araç Arama | `[rentiva_vehicle_search]` |
| Rezervasyon Formu | `[rentiva_booking_form]` |
| Müşteri Paneli | `[rentiva_my_account]` |
| Favorilerim | `[rentiva_favorites]` |
| Mesaj Merkezi | `[rentiva_messages]` |

> Tüm zorunlu sayfaları ve kısa kodları ayrıntılı tablolardan görmek için [Zorunlu Sayfalar rehberine](required-pages.md) bakın.

## 5. Sayfaları Ayarlara Bağlama

`MHM Rentiva > Settings > Shortcode Pages` sekmesinde az önce oluşturduğunuz sayfaları ilgili dropdown alanlarına bağlayın. Kaydettiğinizde kısa kod URL önbelleği güncellenir.

## 6. Lisans ve Versiyon Bilgisi

- `MHM Rentiva > License` sayfasına gidin.
- Lisans anahtarınızı girip doğrulayın; Lite sürüm kullanıyorsanız bu adım pas geçilebilir.
- Lisans panelinde güncelleme kanallarını kontrol edin.

## 7. Ödeme Ağ Geçitlerinin Ayarlanması

**WooCommerce Entegrasyonu (Önerilen):**
1. WooCommerce eklentisini kurun ve aktif edin.
2. `MHM Rentiva > Settings > Payment` sekmesinde WooCommerce entegrasyonunun aktif olduğunu kontrol edin.
3. WooCommerce ayarlarından istediğiniz ödeme ağ geçidini yapılandırın (Stripe, PayPal, vb.).
4. Rezervasyon formu artık WooCommerce checkout sayfasına yönlendirecektir.

**Offline Ödeme (Opsiyonel):**
1. `MHM Rentiva > Settings > Payment` sekmesinde offline ödeme ayarlarını yapılandırın.
2. Makbuz yükleme ve onay süreçlerini ayarlayın.

> **Versiyon 4.4.4 Notu:** WooCommerce entegrasyonu ile tüm ödeme işlemleri WooCommerce üzerinden yönetilir. Para birimi otomatik olarak WooCommerce ayarlarından alınır.

## 8. E-posta Şablonlarının Test Edilmesi

`MHM Rentiva > Email Templates` sekmesinde:

1. Genel e-posta gönderen adı ve adresini ayarlayın.
2. “Send Test Email” özelliğiyle SMTP yapılandırmasını doğrulayın.
3. Her kritik şablon (booking created, payment received, message replied) için en az bir test gönderimi yapın.

## 9. Araç Kategorileri ve Varlık Yönetimi

- `MHM Rentiva > Vehicle Categories` ile başlangıç kategorileri (SUV, Sedan, vb.) ekleyin.
- `MHM Rentiva > Additional Services` üzerinden en sık kullanılan addon’ları (GPS, çocuk koltuğu vb.) oluşturun.

## 10. İlk Araç ve Rezervasyon Testi

1. `MHM Rentiva > Vehicles > Add New` ile örnek bir araç oluşturun.
2. Araç galerisine görseller ekleyin (maksimum 50 resim - v4.4.4+), **Vehicle Settings** üzerinden seçtiğiniz alanların doğru göründüğünü kontrol edin.
3. **Versiyon 4.4.4 Yeni Özellikler:**
   - Yakıt tipi seçiminde LPG, CNG ve Hidrojen seçeneklerini görebilirsiniz.
   - Vites tipi seçiminde Yarı Otomatik ve CVT seçeneklerini görebilirsiniz.
   - Seats, Doors ve Engine Size alanları için maksimum değerler ayarlardan yapılandırılabilir.
4. Frontend'de oluşturduğunuz "Araç Arama" sayfasına giderek rezervasyon formunu doldurun:
   - **Versiyon 4.4.4:** Rezervasyon formu artık tek sütun düzenindedir (daha iyi mobil deneyim).
   - Teslim alma saati zorunludur, teslim etme saati otomatik olarak teslim alma saatine eşitlenir.
   - İletişim bilgileri, ödeme yöntemi ve hizmet şartları alanları kaldırıldı (WooCommerce tarafından yönetiliyor).
   - Ödeme tipi seçimi (Depozito/Tam Ödeme) WooCommerce checkout sayfasında yapılır.
5. Booking'in admin paneline düştüğünü ve doğru statüyle işlendiğini doğrulayın.
6. Rezervasyon detay ekranında yeni alanları kontrol edin:
   - Rezervasyon referans numarası (BK- formatında)
   - Rezervasyon tipi (Online/Manuel)
   - Özel notlar/talepler alanı

## 11. Checkliste Göre Doğrulama

Lokal ve canlı ortam testleri için hazırlanan [test checklist’i](../../checklists/testing-checklists.md) açın. Araç yönetimi, rezervasyon, ödeme, müşteri portalı ve mesajlaşma adımlarını tamamlayıp işaretleyin.

## 12. Yedekleme ve Yayınlama

- Veri tabanı ve dosya yedeğini alın.
- Canlı ortamda testi yapılacak adımları not edin; canlı doğrulama kısa listesi dokümanda mevcuttur.

---

Bir sonraki adım: [Zorunlu Sayfalar ve Kısa Kodlar](required-pages.md)

