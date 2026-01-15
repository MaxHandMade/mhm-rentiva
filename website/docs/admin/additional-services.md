# Additional Services Sayfası

`MHM Rentiva > Additional Services`, araç kiralamalarına ek olarak sunabileceğiniz ekstra hizmetleri (addon) yönetmenizi sağlar. GPS, çocuk koltuğu, ek sürücü gibi ücretli/ücretsiz seçenekler bu ekrandan oluşturulur ve rezervasyon formuna bağlanır.

## Arayüz Öğeleri

- **Addon Listesi:** Hizmet adı, fiyat, durum (Active/Inactive) ve kısa açıklama sütunlarından oluşur.
- **Hızlı Filtreler:** Durum veya kategoriye göre addon’ları listeleyebilirsiniz.
- **Bulk Actions:** Seçili hizmetleri “Enable”, “Disable” veya “Delete” işlemlerine tabi tutar.

## Yeni Hizmet Ekleme

`Add New Service` butonu ile açılan formda:
- **Başlık & Açıklama:** Rezervasyon formunda gösterilecek bilgiler.
- **Fiyatlandırma:** Sabit fiyat, günlük fiyat veya ücretsiz seçenekleri belirleyebilirsiniz.
- **KDV / Para Birimi:** Genel ayarlardan gelen para birimi kullanılır; vergi dahil/dahil değil ayarı `Settings > Pricing` sekmesinden yönetilir.
- **Görsel:** (Opsiyonel) Hizmete ait ikon veya görseller.
- **Durum:** Aktif olmayan hizmetler rezervasyon formunda görülmez.

## Rezervasyon Entegrasyonu

- `[rentiva_booking_form]` kısa kodu ve admin formu, burada tanımlanan aktif addon’ları otomatik listeler.
- Manuel rezervasyon oluştururken (Bookings > Add New) aynı hizmet listesi kullanılabilir.
- Fiyat hesaplaması addon fiyatlarını toplam tutara ekler; bu tutar ödeme kayıtlarına yansır.

## İpuçları

- **Hizmet Grupları:** Benzer addon’lar için kategori/etiket kullanımı müşterinin seçim yapmasını kolaylaştırır.
- **Stok/Sayısı:** Gelişmiş sürümlerde bir addon’ın maksimum adetini belirleyebilirsiniz (örn. en fazla 2 çocuk koltuğu).
- **Çeviri:** Hizmet adlarının çoklu dil desteği için standard WordPress çeviri mekanizmasını kullanın (`__()` fonksiyonları).

## İlgili Dokümanlar

- [Vehicle Settings](vehicle-settings.md): Araç detay formundaki addon alanlarının yönetimi.
- [Bookings](bookings.md): Addon seçimlerinin rezervasyon formuna yansıması.
- [Test Checklist](../checklists/testing-checklists.md#8-add-on-hizmetleri): Addon iş akışının manuel testi.



