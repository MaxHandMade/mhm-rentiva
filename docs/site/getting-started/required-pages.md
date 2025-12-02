# Zorunlu Sayfalar ve Kısa Kodlar

Rentiva, müşteri portalı ve rezervasyon akışını çalıştırmak için bir dizi sayfa ve kısa kod kullanır. Aşağıdaki tablo, varsayılan sayfa adlarını, kısa kodlarını ve önerilen URL yapısını listeler.

| Sayfa Adı | Kısa Kod | Önerilen URL | Açıklama |
|-----------|----------|--------------|----------|
| Araç Arama | `[rentiva_vehicle_search]` | `/rentiva/vehicle-search/` | Filtreli araç arama ve listeleme ekranı |
| Araç Detay (Template) | — | — | Tekil araç şablonu `templates/single-vehicle.php` üzerinden yüklenir |
| Rezervasyon Formu | `[rentiva_booking_form]` | `/rentiva/booking-form/` | Çok adımlı rezervasyon, add-on seçimi ve müşteri bilgileri |
| Rezervasyon Onayı | `[rentiva_booking_confirmation]` | `/rentiva/booking-confirmation/` | Ödeme sonrası özet ve durum bilgisi |
| Teşekkür Sayfası | `[rentiva_thank_you]` | `/rentiva/thank-you/` | Rezervasyon onayı için özel teşekkür sayfası (v4.4.4+) |
| Müşteri Paneli (Dashboard) | `[rentiva_my_account]` | `/rentiva/account/dashboard/` | WooCommerce benzeri müşteri ana ekranı |
| Rezervasyon Geçmişi | `[rentiva_my_bookings]` | `/rentiva/account/bookings/` | Müşteri rezervasyon listesi ve filtreleri |
| Favorilerim | `[rentiva_favorites]` | `/rentiva/account/favorites/` | Favori araçlar |
| Mesaj Merkezi | `[rentiva_messages]` | `/rentiva/account/messages/` | İleti izle / yanıtla ekranı |
| Ödeme Geçmişi | `[rentiva_payment_history]` | `/rentiva/account/payments/` | Online ve offline ödeme kayıtları |
| Profil Düzenleme | `[rentiva_account_details]` | `/rentiva/account/details/` | Müşteri bilgileri ve parola değişikliği |
| Giriş Formu | `[rentiva_login_form]` | `/rentiva/account/login/` | Tek başına giriş sayfası (gerekirse) |
| Kayıt Formu | `[rentiva_register_form]` | `/rentiva/account/register/` | Tek başına kayıt sayfası |
| Araç Karşılaştırma | `[rentiva_vehicle_comparison]` | `/rentiva/vehicle-comparison/` | Seçilen araçların kıyas tablosu |
| Testimonial Listesi | `[rentiva_testimonials]` | `/rentiva/testimonials/` | Varsayılan testimonial grid |

> **Önemli:** Rezervasyon tamamlandıktan sonra onay ekranının açılabilmesi için **Rezervasyon Onayı** sayfasını mutlaka oluşturup yayınlayın. Bu sayfayı `Settings > Shortcode Pages > Booking Confirmation` alanında seçmezseniz, yönlendirme WordPress ana sayfasına düşer.

> **Versiyon 4.4.4:** Yeni **Teşekkür Sayfası** (`[rentiva_thank_you]`) shortcode'u eklendi. Bu sayfa rezervasyon onayı için özel bir teşekkür ekranı sunar. `Settings > Booking > Thank You Page` ayarından bu sayfayı seçebilirsiniz.

## Sayfaların Otomatik Oluşturu lması

Rentiva’nın bazı sürümlerinde “Page Setup” aracı tüm sayfaları tek tuşla ekleyebilir. Eğer araç yoksa, sayfaları manuel oluşturup kısa kodu Gutenberg veya klasik editörde eklemeniz yeterli.

> **İpucu:** Sayfayı oluşturduktan sonra `MHM Rentiva > Settings > Shortcode Pages` sekmesinde ilgili dropdown’dan doğru sayfayı seçtiğinizden emin olun. Bu seçim, kısa kod URL cache’ini güncel tutar.

## Menü ve Navigasyon Önerileri

- Ana menüde “Araç Arama” ve “Müşteri Paneli” linklerini gösterin.
- Müşteri Paneli alt sayfaları için WordPress menü yönetimiyle ikinci bir menü oluşturup hesap sayfasında otomatik gösterebilirsiniz.
- Giriş/Kayıt sayfalarını ekstra menü öğeleri olarak eklemek, henüz hesap açmamış kullanıcılar için faydalıdır.

## Kısa Kod Parametreleri

Birçok kısa kod, farklı görünümler için parametre alır. Örnek:

```shortcode
[rentiva_vehicle_search layout="compact" category="suv" per_page="12"]
```

- `layout`: `default`, `compact`, `grid`, `list`
- `category`: Belirli bir araç kategori slug’ı
- `per_page`: Sayfa başına gösterilecek araç sayısı

Tüm parametreleri `src/Admin/Frontend/Shortcodes` dizinindeki ilgili sınıflardan inceleyebilirsiniz.

---

Sonraki adım: [Kurulum Sonrası Kontrol Listesi](post-install-checklist.md)

