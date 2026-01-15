# Core Utilities ve Yardımcı Sayfalar

Rentiva, yönetim paneline bakım ve tanılama amaçlı birkaç yardımcı sayfa ekler. Bu sayfa, ilgili menülere nereden ulaşacağınızı ve ne işe yaradıklarını özetler.

## Cron Monitor
- Bulunacağı yer: `MHM Rentiva > Settings > Cron Monitor` veya ayrı alt menü (sürüme bağlı).
- Amaç: WordPress cron görevlerinin durumunu, son çalışma zamanını ve bir sonraki tetiklemeyi gösterir.
- Kullanım: Cron görevi tıkanmışsa burada “Run Now” veya “Clear” seçenekleriyle manuel müdahale yapabilirsiniz.

## Database Cleanup
- Bulunacağı yer: `MHM Rentiva > Settings > Database Cleanup`.
- Amaç: Eski rezervasyon logları, yetim meta kayıtları, cache tablolarını temizlemek.
- Özellikler: “Dry Run” ile temizlik öncesi rapor, “Execute” ile kalıcı temizlik. İşlem öncesi yedek almak tavsiye edilir.

## API Keys / Integration
- Bulunacağı yer: `MHM Rentiva > Settings > API Keys` (beta/opsiyonel).
- Amaç: Dış sistemlerle entegrasyon için API anahtarı üretmek, iptal etmek.
- Yetkilendirme: Yalnızca yönetici yetkisi olan kullanıcılar anahtar oluşturabilir.

## System Info
- Bulunacağı yer: `MHM Rentiva > About > System Info` veya ilgili panel.
- Amaç: Site URL, PHP/WordPress versiyonları, aktif eklentiler gibi teknik bilgileri tek ekranda toplar.
- Kullanım: Destek talebi açarken bu bilgiler kopyalanarak paylaşılabilir.

## Uninstall / Reset
- Bulunacağı yer: `MHM Rentiva > Settings > Uninstall`.
- Amaç: Eklentiyi kaldırırken veritabanı tablolarını temizlemek veya sıfırlamak.
- Uyarı: Geri döndürülemez işlemler içerir; yalnızca test veya yeni kurulum öncesi kullanılmalıdır.

## İlgili Dokümanlar

- [Settings](settings.md) – Yardımcı sayfalardaki ayarların kökeni.
- [Test Suite](test-suite.md) – Tanılama için otomatik testler.
- [Troubleshooting](../troubleshooting/) *(hazırlanacak)* – Sorun çözme rehberi.



