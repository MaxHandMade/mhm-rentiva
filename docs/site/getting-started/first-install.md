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

1. `MHM Rentiva > Payments` menüsünden Stripe, PayPal, PayTR ve Offline seçeneklerini sırasıyla yapılandırın.
2. Sandbox/test modunda başlayın. `Testing` sekmesindeki sandbox anahtarlarını girin.
3. Webhook URL’lerini ilgili servislerde tanımlayın (Stripe için `https://domain.com/?mhm_stripe_webhook=1`).

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
2. Araç galerisine görseller ekleyin, **Vehicle Settings** üzerinden seçtiğiniz alanların doğru göründüğünü kontrol edin.
3. Frontend’de oluşturduğunuz “Araç Arama” sayfasına giderek rezervasyon formunu doldurun; booking’in admin paneline düştüğünü ve doğru statüyle işlendiğini doğrulayın.

## 11. Checkliste Göre Doğrulama

Lokal ve canlı ortam testleri için hazırlanan [test checklist’i](../../checklists/testing-checklists.md) açın. Araç yönetimi, rezervasyon, ödeme, müşteri portalı ve mesajlaşma adımlarını tamamlayıp işaretleyin.

## 12. Yedekleme ve Yayınlama

- Veri tabanı ve dosya yedeğini alın.
- Canlı ortamda testi yapılacak adımları not edin; canlı doğrulama kısa listesi dokümanda mevcuttur.

---

Bir sonraki adım: [Zorunlu Sayfalar ve Kısa Kodlar](required-pages.md)

