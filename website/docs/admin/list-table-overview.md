# VehicleColumns (List Table) Notları

Bu notlar `src/Admin/Vehicle/ListTable/VehicleColumns.php` dosyasındaki ana fonksiyonları özetler. Dosya, araç listesi görünümünü geliştirmek ve ek arabirim bileşenleri eklemekten sorumludur.

## Başlıca Fonksiyonlar

### `register()`
WordPress yönetimindeki `vehicle` post listesine aşağıdaki geliştirmeleri ekler:
- Yeni kolonlar (`License Plate`, `Price/Day`, `Seats`, `Transmission`, `Fuel`, `Available`)
- Quick Edit alanları ve kayıt işlemi
- Araç istatistik kartları ve aylık takvim
- Filtreler (availability, sorting)
- Cache temizleme hook’ları

> Bu fonksiyon satır içi yorumlara sahip değildir; retro-uyumluluk için kod okunarak anlaşılabilir. Liste üstünde yeni özellik eklenecekse buraya hook eklenmesi gerekir.

### `columns()` / `render()`
Liste kolonlarının başlıklarını tanımlar ve satır içindeki verileri görüntüler. `render()` fonksiyonu, `Available` sütunu için renkli etiketler ve data attributelerini oluşturur.

### `quick_edit_fields()` / `save_quick_edit()`
Quick Edit formuna Rentiva’ya özel alanları ekler ve kaydetme sırasında meta alanlarını günceller. `_mhm_vehicle_status` ve `_mhm_vehicle_availability` meta alanları senkronize edilmiştir.

### `add_vehicle_stats_cards()` / `get_vehicle_stats()`
Sayfanın üst kısmına istatistik kartları ve aylık rezervasyon takvimi yerleştirir. `get_vehicle_stats()` veritabanından optimizasyon amaçlı pivot sorgu çalıştırır; legacy meta alanları ile yeni alanları birlikte değerlendirir.

### `availability_filter()` / `apply_availability_filter()`
Liste başındaki “All availability statuses” dropdown’ını oluşturur ve seçilen statüye göre sorguyu filtreler. (Legacy `passive` değeri tamamen kaldırıldı.)

### Yardımcı Fonksiyonlar
- `normalize_availability()`: Eski meta değerlerini yeni statülere map eder.
- `clear_vehicle_cache()` vb.: Araç metası değiştiğinde istatistik önbelleğini temizler.

## Diğer Dosyalar
`VehicleMeta`, `VehicleSettings`, `Vehicle PostType` gibi dosyalar benzer şekilde fonksiyon açıklamalarına sahiptir. Önemli kısımlar için README’lerde özetler bulabilirsiniz.



