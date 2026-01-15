# Kurulum ve İlk Ayarlar Rehberi

MHM Rentiva eklentisini kurmak ve işletmeye hazır hale getirmek için izlemeniz gereken adımlar aşağıdadır. Bu rehber, kurulumdan ilk rezervasyon denemesine kadar olan süreci kapsar.

## 1. Gereksinimler

*   **WordPress:** 5.0 veya üzeri.
*   **PHP:** 7.4 veya üzeri.
*   **Permalink:** `Ayarlar > Kalıcı Bağlantılar` menüsünde "Yazı ismi" (Post name) seçili olmalıdır.
*   **SSL:** Güvenli ödeme işlemleri için HTTPS gereklidir.

## 2. Eklentiyi Yükleme

### Yöntem A: WordPress Admin Paneli (Önerilen)
1.  WordPress yönetim paneline giriş yapın.
2.  **Eklentiler > Yeni Ekle** sayfasına gidin.
3.  **Eklenti Yükle** butonuna tıklayın.
4.  `mhm-rentiva.zip` dosyasını seçin ve **Şimdi Kur**'a tıklayın.
5.  Kurulum sonrası **Etkinleştir** butonuna basın.

### Yöntem B: FTP ile Yükleme
1.  `mhm-rentiva.zip` dosyasını bilgisayarınızda açın.
2.  FTP istemciniz ile sitenize bağlanın.
3.  `wp-content/plugins/` klasörüne gidin.
4.  `mhm-rentiva` klasörünü buraya yükleyin.
5.  WordPress panelinden **Eklentiler** sayfasına gidip etkinleştirin.

## 3. İlk Ayarlar ve Sihirbaz

Eklenti etkinleştirildiğinde bir kurulum sihirbazı veya "Getting Started" uyarısı görebilirsiniz. Eğer görmezseniz manuel olarak:

1.  Admin panelinde sol menüden **MHM Rentiva > Settings**'e gidin.
2.  **General Settings** sekmesinde Şirket Adı, Para Birimi ve Tarih formatını ayarlayıp kaydedin.

## 4. Zorunlu Sayfaların Oluşturulması

Eklentinin çalışması için bazı sayfaların oluşturulması ve içine ilgili kısa kodların eklenmesi gerekir. Yeni bir sayfa oluşturun (`Sayfalar > Yeni Ekle`) ve aşağıdaki kısa kodları ekleyin:

| Sayfa Adı | Kısa Kod (Shortcode) | Önerilen URL | Açıklama |
|-----------|----------------------|--------------|----------|
| **Araç Arama / Listeleme** | `[rentiva_vehicle_search]` | `/rentiva/vehicle-search/` | Müşterilerin araçları göreceği ana sayfa. |
| **Rezervasyon Formu** | `[rentiva_booking_form]` | `/rentiva/booking-form/` | Rezervasyon işlemlerinin yapıldığı sayfa. |
| **Müşteri Paneli (Hesabım)**| `[rentiva_my_account]` | `/rentiva/account/dashboard/` | Müşterilerin giriş yapıp rezervasyonlarını göreceği yer. |
| **Teşekkür Sayfası** | `[rentiva_thank_you]` | `/rentiva/thank-you/` | Rezervasyon tamamlanınca yönlendirilecek sayfa. |
| **Rezervasyon Onayı** | `[rentiva_booking_confirmation]` | `/rentiva/booking-confirmation/` | Ödeme sonrası özet ve durum bilgisi. |

> **Not:** Detaylı kısa kod listesi için [Kısa Kodlar Kılavuzu](../usage/shortcodes.md)'na bakabilirsiniz.

## 5. Sayfaları Eklentiye Tanıtma

Oluşturduğunuz sayfaları eklenti ayarlarına bağlamanız gerekir:

1.  **MHM Rentiva > Settings > Frontend & Display** (veya konfigürasyonunuza göre ilgili sekme) yolunu izleyin.
2.  Oluşturduğunuz sayfaları ilgili ayar alanlarında (örn: "Search Page", "Checkout Page") seçin.
3.  Ayarları kaydedin.

## 6. Ödeme Ayarları (WooCommerce)

MHM Rentiva, ödeme işlemleri için WooCommerce altyapısını kullanır.

1.  WooCommerce eklentisinin kurulu ve aktif olduğundan emin olun.
2.  **MHM Rentiva > Settings > Payment Settings** sekmesine gidin.
3.  WooCommerce entegrasyonunun açık olduğunu doğrulayın.
4.  WooCommerce ayarlarından (Banka Havalesi, Kredi Kartı vb.) ödeme yöntemlerinizi yapılandırın.

## 7. İlk Araç Girişi ve Test

1.  **Araçlar (Vehicles) > Yeni Ekle** menüsüne gidin.
2.  Örnek bir araç adı yazın (örn: "Fiat Egea Dizel Otomatik").
3.  **Araç Bilgileri** panelinden Plaka, Fiyat, Yakıt Tipi, Vites gibi detayları doldurun.
4.  Araca bir **Öne Çıkan Görsel** ekleyin.
5.  **Yayımla** butonuna basın.
6.  Sitenizin ön yüzünde "Araç Arama" sayfasını açın ve aracın listelendiğini görün.
7.  Bir rezervasyon denemesi yaparak sürecin (Seçim -> Form -> Ödeme -> Onay) çalıştığını doğrulayın.

## 8. Kontrol Listesi

Kurulumu tamamlamak için son kontroller:
- [ ] Permalink ayarları "Post name" mi?
- [ ] Zorunlu sayfalar oluşturuldu ve ayarlara bağlandı mı?
- [ ] Ödeme yöntemi (WooCommerce) aktif mi?
- [ ] Test rezervasyonu admin paneline düştü mü?

Tebrikler! MHM Rentiva kullanıma hazır.
