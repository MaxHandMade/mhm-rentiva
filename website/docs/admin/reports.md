# Reports Sayfası

`MHM Rentiva > Reports`, işletmenizin performansını görselleştiren kontrol panelidir. Gelir, rezervasyon ve araç kullanım istatistikleri farklı grafikler ve tablolarda sunulur.

## Ana Bölümler

- **Tarih Seçici:** Üst kısımdaki filtre ile günlük, haftalık, aylık veya özel tarih aralığı seçebilirsiniz.
- **Gelir Grafiği:** Seçilen dönem için toplam gelir, ödeme yöntemi kırılımı ve trend yüzdeleri.
- **Rezervasyon Durum Grafiği:** Pending, Confirmed, Completed, Cancelled gibi statülerin oranları.
- **Araç Performansı:** En çok kiralanan araçlar, gelir bazında sıralama ve doluluk yüzdesi.
- **Müşteri Segmentasyonu:** Yeni müşteri, sadık müşteri, VIP gibi segmentlere ait istatistikler (lite/pro sürüme göre değişebilir).

## Veri Kaynakları

- Grafik verileri doğrudan rezervasyon kayıtlarından ve ödeme metalarından üretilir.
- İyi performans için grafik verileri kısa süreli önbelleğe alınır; `Settings > Performance` bölümünden TTL değerlerini ayarlayabilirsiniz.
- Raporlar, yalnızca “publish” durumundaki rezervasyonları dikkate alır.

## İpuçları

- **Filtreleri Kullanma:** Tarih aralığını daraltarak belirli kampanya dönemlerini analiz edebilirsiniz.
- **CSV/Export:** Ayrıntılı veri gerekiyorsa `Export` sayfasından aynı tarih aralığında CSV çekin.
- **Önbellek Temizliği:** Raporlarda güncel bilgiler görünmüyorsa `Settings > Performance` altındaki “Reset cache” seçeneklerini kullanın.

## İlgili Dokümanlar

- [Export](export.md): Raporlarda kullanılan ham verileri dışa aktarma.
- [Settings](settings.md#performance-ve-cache-ayarlari): Rapor cache sürelerinin yönetimi.
- [Checklist](../checklists/testing-checklists.md#7-raporlama-ve-export): Raporlama doğrulama adımları.



