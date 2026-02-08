# MHM Rentiva Kısa Kod (Shortcode) Listesi

Bu belgede, MHM Rentiva eklentisi tarafından sunulan tüm kısa kodlar, işlevleri ve destekledikleri parametreler listelenmiştir.

---

## 1. Rezervasyon Modülü (Reservation)

### `[rentiva_booking_form]`
**İşlev:** Araç Rezervasyon Formu
Araç seçimi, tarih/saat belirleme, ek hizmetler ve ödeme seçeneklerini içeren ana rezervasyon formudur.

**Parametreler:**
| Parametre | Varsayılan | Açıklama |
| :--- | :--- | :--- |
| `vehicle_id` | *(Boş)* | Belirli bir aracın ID'si girilirse form o araç seçili olarak açılır. |
| `show_vehicle_selector` | `1` | Araç seçim kutusunu göster/gizle (`1`/`0`). |
| `show_addons` | `1` | Ek hizmetler (sigorta, GPS vb.) bölümünü göster/gizle. |
| `enable_deposit` | `1` | Depozito ödeme seçeneğini aktif eder. |
| `redirect_url` | *(Boş)* | Başarılı rezervasyon sonrası yönlendirilecek URL. |

### `[rentiva_availability_calendar]`
**İşlev:** Müsaitlik Takvimi
Belirli bir aracın veya tüm araçların doluluk durumunu interaktif bir takvim üzerinde gösterir.

### `[rentiva_booking_confirmation]`
**İşlev:** Rezervasyon Onayı
Ödeme sonrası rezervasyon detaylarını ve referans numarasını içeren onay sayfasıdır. Parametre gerektirmez, URL üzerinden sipariş ID'sini okur.

---

## 2. Araç Görüntüleme (Vehicle Display)

### `[rentiva_vehicles_list]`
**İşlev:** Araç Listesi
Mevcut araçları filtreleme özellikleriyle birlikte liste görünümünde sunar. En kapsamlı kısa koddur.

**Temel Parametreler:**
| Parametre | Varsayılan | Açıklama |
| :--- | :--- | :--- |
| `limit` | `12` | Görüntülenecek maksimum araç sayısı. |
| `category` | *(Boş)* | Sadece belirli bir kategorideki araçları listeler (örn: `sedan,suv`). |
| `orderby` | `title` | Sıralama kriteri: `title`, `date`, `price`, `featured`. |
| `order` | `ASC` | Sıralama yönü: `ASC` (Artan), `DESC` (Azalan). |
| `show_price` | `1` | Fiyat bilgisini göster/gizle. |
| `show_features` | `1` | Araç özelliklerini (vites, yakıt vb.) göster/gizle. |
| `columns` | `1` | Liste görünümü olduğu için genelde `1` olarak kullanılır. |

### `[rentiva_featured_vehicles]`
**İşlev:** Öne Çıkan Araçlar
"Öne çıkan" (featured) olarak işaretlenen araçları özel bir bölümde sergiler.

**Parametreler:**
| Parametre | Varsayılan | Açıklama |
| :--- | :--- | :--- |
| `layout` | `slider` | Görünüm düzeni: `slider` (slayt) veya `grid` (ızgara). |
| `limit` | `6` | Gösterilecek araç sayısı. |
| `autoplay` | `1` | Slider otomatik oynatma (`1`: Açık, `0`: Kapalı). |
| `interval` | `5000` | Slider geçiş süresi (ms cinsinden). |

### `[rentiva_vehicle_details]`
**İşlev:** Araç Detayları
Tek bir aracın teknik özellikleri, galerisi ve fiyatını içerir. Genellikle araç detay sayfasında otomatik kullanılır.

### `[rentiva_vehicles_grid]`
**İşlev:** Araç Izgarası
Araçları ızgara (grid) düzeninde listeler. `[rentiva_vehicles_list]` ile benzer parametreleri destekler ancak `layout="grid"` varsayılan olarak gelir.

### `[rentiva_search_results]`
**İşlev:** Arama Sonuçları
Arama motorundan gelen sonuçların listelendiği sayfadır.

### `[rentiva_vehicle_comparison]`
**İşlev:** Araç Karşılaştırma
Seçilen araçların özelliklerini yan yana kıyaslar.

---

## 3. Birleşik Arama (Unified Search)

### `[rentiva_unified_search]`
**İşlev:** Birleşik Arama Widget'ı
Araç kiralama ve VIP transfer aramalarını tek panelde birleştiren gelişmiş arama motoru.

**Görünüm Parametreleri:**
| Parametre | Varsayılan | Seçenekler | Açıklama |
| :--- | :--- | :--- | :--- |
| `layout` | `horizontal` | `horizontal`, `vertical`, `compact` | Widget yerleşimi (Yatay, Dikey, Kompakt). |
| `style` | `glass` | `glass`, `solid` | Tasarım stili (Cam efekti veya Katı renk). |
| `default_tab` | `rental` | `rental`, `transfer` | Varsayılan açılacak sekme. |

**İşlevsel Parametreler:**
| Parametre | Varsayılan | Açıklama |
| :--- | :--- | :--- |
| `show_rental_tab` | `1` | Araç Kiralama sekmesini göster/gizle. |
| `show_transfer_tab` | `1` | Transfer sekmesini göster/gizle. |
| `show_location_select` | `1` | Lokasyon seçimini göster/gizle. |
| `redirect_page` | *(Boş)* | Arama sonrası yönlendirilecek özel sayfa URL'si. |

---

## 4. Transfer Hizmetleri (Transfer)

### `[rentiva_transfer_search]`
**İşlev:** Transfer Arama
Sadece transfer hizmetleri için özelleşmiş arama formu.

### `[rentiva_transfer_results]`
**İşlev:** Transfer Sonuçları
Transfer arama sonuçlarını listeler. Dashicon yerine SVG ikonlar kullanır.

---

## 5. Müşteri Hesabı (Account)

Bu kısa kodlar **giriş yapmış kullanıcılar** içindir.
- `[rentiva_my_bookings]`: Rezervasyonlarım listesi.
- `[rentiva_my_favorites]`: Favori araçlar listesi.
- `[rentiva_payment_history]`: Ödeme geçmişi.
- `[rentiva_messages]`: Mesajlaşma paneli.

---

## 6. Destek (Support)

- `[rentiva_contact]`: İletişim formu.
- `[rentiva_testimonials]`: Müşteri yorumları slider'ı.
- `[rentiva_vehicle_rating_form]`: Araç değerlendirme formu.

---

## 7. Elite Edition (Planlanan Özellikler)

Aşağıdaki kısa kodlar henüz geliştirme aşamasındadır:
- `[rentiva_hero_section]`: Komple Hero Mizanpajı.
- `[rentiva_trust_stats]`: Dinamik Güven İstatistikleri.
- `[rentiva_brand_scroller]`: Lüks Marka Bandı.
- `[rentiva_comparison_table]`: Gelişmiş Teknik Karşılaştırma Tablosu.
- `[rentiva_extra_services]`: İnteraktif Ekstra Paneli.

---
*Son Güncelleme: 08.02.2026 - Tüm parametreler kod tabanından doğrulanmıştır.*
