# Test Suite Sayfası

`MHM Rentiva > Test Suite`, geliştirme veya kalite kontrol süreçlerinde kullanılan yerleşik test aracıdır. Yalnızca geliştirici modunda veya yetkili kullanıcılar için görünür.

## Ne İşe Yarar?

- **Ortam Doğrulama:** PHP sürümü, WordPress ayarları, cache/cron yapılandırması gibi temel gereksinimlerin hızlı kontrolü.
- **API & Gateway Testleri:** Ödeme gateway’leri için bağlantı testi, webhooks’ın yanıt verip vermediğini gösteren mini raporlar.
- **Rate Limit / Cache Kontrolü:** Rate limiter değerlerinin doğru çalışıp çalışmadığını teyit eden fonksiyonlar.
- **Debug Araçları:** Log dosyalarını görüntüleme veya belirli veritabanı kayıtlarını temizleme butonları bulunabilir (sürüme göre).

## Kullanım Önerileri

- Güncelleme veya canlıya alma öncesi tüm testleri çalıştırıp “Passed” sonuç aldığınızdan emin olun.
- Fail eden testlerde hata mesajı teşhis için yeterli değilse debug.log dosyasını inceleyin.
- Test Suite çıktısını kaydedip, QA sürecinin bir parçası olarak takım arkadaşlarınızla paylaşabilirsiniz.

## İlgili Dokümanlar

- [Checklist](../checklists/testing-checklists.md) – Manuel test adımları.
- [Core Utilities](core-utilities.md) – Cron ve bakım araçları.
- [Troubleshooting](../troubleshooting/) *(hazırlanacak)* – Hata giderme rehberi.



