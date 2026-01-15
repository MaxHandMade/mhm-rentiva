# Shortcodes (Kısa Kodlar)

MHM Rentiva, sitenizin çeşitli yerlerinde araç listeleri, formlar ve diğer özellikleri görüntülemek için kapsamlı bir kısa kod seti sunar. Aşağıda mevcut tüm kısa kodların bir listesi ve kullanım detayları bulunmaktadır.

## Araç Listeleme ve Görünüm

### 1. Araç Izgarası (Grid)
Araçları ızgara (grid) düzeninde listeler.

**Kısa Kod:** `[rentiva_vehicles_grid]`

**Parametreler:**
*   `posts_per_page`: Sayfada gösterilecek araç sayısı. Varsayılan: `9`.
*   `orderby`: Sıralama kriteri (`date`, `title`, `price`, `rand`). Varsayılan: `date`.
*   `order`: Sıralama yönü (`ASC`, `DESC`). Varsayılan: `DESC`.
*   `columns`: Sütun sayısı (masaüstü için). Varsayılan: `3`.
*   `show_filters`: Filtreleri göster (`1` veya `0`). Varsayılan: `1`.
*   `category`: Belirli bir kategorideki araçları filtrelemek için kategori slug'ı.

**Örnek:**
```
[rentiva_vehicles_grid columns="4" posts_per_page="12" show_filters="0"]
```

### 2. Araç Listesi (List)
Araçları liste görünümünde gösterir.

**Kısa Kod:** `[rentiva_vehicles_list]`

**Parametreler:**
*   `posts_per_page`: Sayfada gösterilecek araç sayısı. Varsayılan: `10`.
*   `orderby`: Sıralama kriteri. Varsayılan: `date`.
*   `order`: Sıralama yönü. Varsayılan: `DESC`.
*   `show_filters`: Filtreleri göster. Varsayılan: `1`.

**Örnek:**
```
[rentiva_vehicles_list posts_per_page="5"]
```

### 3. Arama Sonuçları
Araç arama sonuçlarını listeler. Arama formu tarafından yönlendirilen sayfada kullanılır.

**Kısa Kod:** `[rentiva_search_results]`

**Parametreler:**
Bu kısa kod genellikle parametre almaz, URL üzerinden gelen arama sorgularını (`GET` parametreleri) işler.

### 4. Araç Detayları
Tek bir aracın detaylarını gösterir. Genellikle `single-vehicle.php` şablonu içinde veya özel bir sayfada belirli bir aracı göstermek için kullanılır.

**Kısa Kod:** `[rentiva_vehicle_details]`

**Parametreler:**
*   `vehicle_id`: Gösterilecek aracın ID'si. Belirtilmezse mevcut sayfanın veya döngünün ID'sini kullanır.
*   `show_gallery`: Galeriyi göster (`1` veya `0`). Varsayılan: `1`.
*   `show_features`: Özellikleri göster. Varsayılan: `1`.
*   `show_pricing`: Fiyatı göster. Varsayılan: `1`.
*   `show_booking`: Rezervasyon formunu/butonunu göster. Varsayılan: `1`.
*   `show_calendar`: Müsaitlik takvimini göster. Varsayılan: `1`.

**Örnek:**
```
[rentiva_vehicle_details vehicle_id="123"]
```

### 5. Araç Karşılaştırma
Seçilen araçları karşılaştırmalı bir tablo veya kart görünümünde sunar.

**Kısa Kod:** `[rentiva_vehicle_comparison]`

**Parametreler:**
*   `vehicle_ids`: Karşılaştırılacak araçların virgülle ayrılmış ID'leri.
*   `show_features`: Gösterilecek özellikler (`all`, `basic`, `detailed`). Varsayılan: `all`.
*   `max_vehicles`: Maksimum karşılaştırılabilecek araç sayısı. Varsayılan: `4`.
*   `layout`: Görünüm düzeni (`table`, `cards`). Varsayılan: `table`.

**Örnek:**
```
[rentiva_vehicle_comparison vehicle_ids="101,102,103" layout="table"]
```

## Rezervasyon ve Müsaitlik

### 6. Rezervasyon Formu
Gelişmiş rezervasyon formunu görüntüler.

**Kısa Kod:** `[rentiva_booking_form]`

**Parametreler:**
*   `vehicle_id`: Belirli bir araç için form oluşturur.
*   `show_vehicle_selector`: Araç seçim alanını göster (`1` veya `0`). Varsayılan: `1`.
*   `show_addons`: Ek hizmetleri göster. Varsayılan: `1`.
*   `enable_deposit`: Depozito sistemini aktif et. Varsayılan: `1`.
*   `redirect_url`: Başarılı işlem sonrası yönlendirilecek URL.

**Örnek:**
```
[rentiva_booking_form vehicle_id="55" show_vehicle_selector="0"]
```

### 7. Müsaitlik Takvimi
Bir aracın müsaitlik durumunu takvim üzerinde gösterir.

**Kısa Kod:** `[rentiva_availability_calendar]`

**Parametreler:**
*   `vehicle_id`: Takvimi gösterilecek araç ID'si.
*   `months_to_show`: Gösterilecek ay sayısı. Varsayılan: `1` (Filtre ile `3` olabilir).
*   `show_pricing`: Günlük fiyatları göster. Varsayılan: `1`.
*   `theme`: Takvim teması (`default`, `compact`).

**Örnek:**
```
[rentiva_availability_calendar vehicle_id="123" months_to_show="3"]
```

### 8. Rezervasyon Onayı
Rezervasyon tamamlandıktan sonra gösterilen onay sayfası içeriğidir.

**Kısa Kod:** `[rentiva_booking_confirmation]`

**Parametreler:**
*   `booking_id`: Onay detayları gösterilecek rezervasyon ID'si (URL parametresi olarak da alınabilir).



## Diğer İşlevler

### 10. İletişim Formları
Çeşitli amaçlar için (genel, rezervasyon, destek) iletişim formları oluşturur.

**Kısa Kod:** `[rentiva_contact]`

**Parametreler:**
*   `type`: Form tipi (`general`, `booking`, `support`, `feedback`). Varsayılan: `general`.

**Örnek:**
```
[rentiva_contact type="support"]
```

### 11. Müşteri Yorumları (Testimonials)
Müşteri yorumlarını listeler.

**Kısa Kod:** `[rentiva_testimonials]`

**Parametreler:**
*   `limit`: Gösterilecek yorum sayısı. Varsayılan: `5`.
*   `rating`: Belirli bir puana sahip yorumları filtreler (Örn: `5` yıldızlılar).
*   `vehicle_id`: Sadece belirli bir araca ait yorumları gösterir.
*   `layout`: Görünüm düzeni (`grid`, `slider`, `list`). Varsayılan: `grid`.
*   `columns`: Grid görünümü için sütun sayısı (3 veya 4). Varsayılan: `3`.
*   `show_rating`: Yıldız puanlamasını göster (`1` veya `0`). Varsayılan: `1`.
*   `show_date`: Yorum tarihini göster (`1` veya `0`). Varsayılan: `1`.
*   `show_vehicle`: Araç ismini göster (`1` veya `0`). Varsayılan: `1`.
*   `show_customer`: Müşteri ismini/baş harflerini göster (`1` veya `0`). Varsayılan: `1`.
*   `auto_rotate`: Slider için otomatik geçiş süresi (ms cinsinden, 0 = kapalı). Varsayılan: `0`.

**Örnek:**
```
[rentiva_testimonials limit="6" layout="slider" show_rating="1" auto_rotate="5000"]
```

### 12. Araç Puanlama Formu
Kullanıcıların araçlara puan vermesini ve yorum yapmasını sağlayan gelişmiş form. Kullanıcının rezervasyon geçmişini kontrol eder ve sadece kiralama yapmış kullanıcılara (veya ayarlara göre herkese) yorum izni verir.

**Kısa Kod:** `[rentiva_vehicle_rating_form]`

**Parametreler:**
*   `vehicle_id`: Puanlanacak araç ID'si. Belirtilmezse mevcut sayfanın ID'si kullanılır.
*   `show_rating_display`: Mevcut ortalama puanı ve yıldızları göster (`1` veya `0`). Varsayılan: `1`.
*   `show_form`: Yorum gönderme formunu göster (`1` veya `0`). Varsayılan: `1`.
*   `show_ratings_list`: Alt kısımda yapılan yorumları listele (`1` veya `0`). Varsayılan: `1`.

**Örnek:**
```
[rentiva_vehicle_rating_form show_ratings_list="1"]
```

### 13. Müşteri Mesajları
Müşterilerin yönetici ile iletişime geçebileceği ve mesaj geçmişini görüntüleyebileceği mesajlaşma merkezi. Sadece giriş yapmış kullanıcılar görebilir.

**Kısa Kod:** `[rentiva_messages]`
Sadece müşteri mesajlaşma merkezini gösterir.
