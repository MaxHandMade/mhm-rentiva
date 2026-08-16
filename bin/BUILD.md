# MHM Rentiva release ZIP'i

Bu proje için tek kanonik paketleme yolu Docker içindeki
`bin/build-release.py` betiğidir. Host üzerinde Python, `Compress-Archive`,
`git archive` veya elle ZIP üretmek release kanıtı sayılmaz.

## Güncel release sözleşmesi

- Eklenti sürümü: `6.0.6`
- Sürüm sabiti: `MHMRENTIVA_VERSION`
- ZIP içindeki tek kök: `mhm-rentiva/`
- Kanonik host çıktısı:
  `C:\tmp\plugin-builds\mhm-rentiva.6.0.6.zip`
- 16 Ağustos 2026 tarihli allowlist ölçümü: `555` dosya

Dosya sayısı sabit bir hedef değildir. Her paketlemeden hemen önce aşağıdaki
komutla yeniden ölçülür; kaynak ağaç değiştiyse bu belge ve release ledger'ı
gerçek sonuçla güncellenir.

```powershell
docker run --rm -v "C:/projects/rentiva-dev/plugins/mhm-rentiva:/src" -w /src python:3-slim python bin/build-release.py --list-shipped | Measure-Object -Line
```

Güncel 555 dosyalık yüzey kaynak ağaçtan türetilmiştir:

| Kök | Dosya |
|---|---:|
| `assets/` | 152 |
| `build/` | 16 |
| `languages/` | 24 |
| `src/` | 263 |
| `src-react/` | 46 |
| `templates/` | 41 |
| `vendor/` | 6 |
| Kök dosyalar | 7 |
| Toplam | 555 |

`build/admin/` çalıştırma zamanı asset'lerini, `src-react/` bu asset'lerin
kaynak ve paylaşılan stil yüzeyini, `vendor/mhm/ui-core/` ise eklentinin
çalıştırma zamanı bağımlılığını içerdiği için sevk edilir. Allowlist'in gerçek
kaynağı bu tablo değil, `bin/build-release.py --list-shipped` çıktısıdır.

## Kanonik Docker build akışı

Önce kaynak ağaçtaki bütün zorunlu kapılar çalıştırılır. Testler geçmeden ZIP
üretilmez. Aşağıdaki komut yalnız bu kapılardan sonra çalıştırılır:

```powershell
docker run --rm -v "C:/projects/rentiva-dev/plugins/mhm-rentiva:/src" -w /src python:3-slim python bin/build-release.py

New-Item -ItemType Directory -Force -Path 'C:\tmp\plugin-builds' | Out-Null
Remove-Item -LiteralPath 'C:\tmp\plugin-builds\mhm-rentiva.6.0.1.zip' -Force -ErrorAction SilentlyContinue
Copy-Item -LiteralPath 'C:\projects\rentiva-dev\plugins\mhm-rentiva\build\mhm-rentiva.6.0.1.zip' -Destination 'C:\tmp\plugin-builds\mhm-rentiva.6.0.1.zip'
```

`build-release.py` staging alanını temizler, `.distignore` ve kendi açık
allowlist kurallarından sevk yüzeyini üretir, POSIX yollarla ZIP oluşturur ve
tek kök klasörü doğrular. Bind mount nedeniyle ZIP zaten hosttaki `build/`
dizinine yazılır; kanonik artefakt oradan `C:\tmp\plugin-builds` konumuna
kopyalanır.

## ZIP sonrası zorunlu doğrulama

Kaynak testlerinin yeşil olması ZIP'in doğru olduğunu kanıtlamaz. Doğrulamalar
kanonik ZIP üzerinde yeniden çalıştırılır:

1. ZIP adı ve eklenti başlığındaki sürüm `6.0.1` olmalıdır.
2. Tek kök `mhm-rentiva/` olmalı ve hiçbir üye `\` içermemelidir.
3. ZIP'teki dosya listesi `--list-shipped` allowlist'iyle birebir eşleşmelidir.
4. `tests/`, `docs/`, `bin/`, geliştirme yapılandırmaları, günlükler ve başka
   ZIP'ler pakette bulunmamalıdır.
5. Plugin Check ve ZIP'e yönelik PHPCS/WPCS kontrolleri kanonik artefakta karşı
   çalıştırılmalıdır.
6. Temiz WordPress kurulumunda ZIP'ten kurulum, aktivasyon, kritik kullanıcı
   akışları, tarayıcı konsolu ve network istekleri fiziksel olarak sınanmalıdır.

Kanonik dosya dışındaki eski `build/` ZIP'leri başvuru artefaktı değildir.
GitHub Release veya WordPress.org yüklemesinden önce son doğrulanan dosya her
zaman `C:\tmp\plugin-builds\mhm-rentiva.6.0.1.zip` olmalıdır.

## Yasak yollar

- Host üzerinde `python bin/build-release.py`
- PowerShell `Compress-Archive`
- `git archive`
- Explorer/Finder ile elle sıkıştırma
- Kaynak testleri bitmeden ZIP üretme
- Repo içindeki eski bir `build/*.zip` dosyasını kanonik artefakt sayma
- Canlı kurulum doğrulanmadan release yayımlama

Bu yollar aynı kaynak ağacı kullansa bile çalışma zamanı, yol ayırıcıları,
hariç tutma kuralları veya stale artefakt nedeniyle farklı bir paket
üretebilir.
