# Export Sayfası

`MHM Rentiva > Export`, sistemdeki verileri dışa aktarmak için kullanılan araçtır. Rezervasyon, araç, müşteri, log gibi verileri CSV, JSON ve desteklenen diğer formatlarda indirebilirsiniz.

## Adım Adım Kullanım

1. **Veri Tipi Seçimi:** “Data Type” alanından `Bookings`, `Vehicles`, `Customers`, `Logs` vb. seçeneklerden birini belirleyin.
2. **Tarih / Durum Filtreleri:** Tarih aralığı, durum (ör. `Confirmed bookings`), araç veya müşteri gibi ek filtreler tanımlayın.
3. **Format Seçimi:** CSV, JSON, Excel (pro sürüm), XML gibi formatlar arasından seçim yapın.
4. **Alan Seçimi (Opsiyonel):** Dahil edilecek kolonları belirleyerek daha hafif dosyalar üretebilirsiniz.
5. **Export İşlemi:** `Start Export` düğmesine basın; işlem tamamlandığında indirme bağlantısı sunulur. Büyük veri setleri için süreç arka planda çalışabilir ve “Export History” sekmesinden takip edilebilir.

## Export History

- Daha önce gerçekleştirdiğiniz export işlemlerini listeler.
- İstenirse aynı export tekrar indirilebilir veya silinebilir.
- İşlem süresi ve satır sayısı gibi performans bilgilerini görüntüleyebilirsiniz.

## En İyi Pratikler

- **Filtre Kullanımı:** Büyük veri setlerinde filtre kullanarak dosya boyutunu düşürün; aksi halde sunucu süresi aşılabilir.
- **UTF-8/Excel Uyumu:** CSV export’larını Excel’de açarken UTF-8 kodlamasını korumak için “Data Import” özelliğini kullanın.
- **Planlı Export:** API entegrasyonu yerine periyodik raporlar için export history kaydını kullanarak otomasyona gidebilirsiniz (örn. cron + WP-CLI).

## İlgili Dokümanlar

- [Reports](reports.md): Aynı verilerin grafik formda sunumu.
- [Settings > Performance](settings.md#performance-ve-cache-ayarlari): Export sırasında kullanılan cache ayarları.
- [Checklist](../checklists/testing-checklists.md#7-raporlama-ve-export): Export süreçlerinin manuel testi.



