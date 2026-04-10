# Release ZIP Nasıl Oluşturulur

> **TL;DR:** `python bin/build-release.py` çalıştır → `build/mhm-rentiva.<version>.zip` hazır → doğrudan WordPress admin'den yüklenebilir.

Bu doküman mhm-rentiva eklentisinin **WordPress'e kurulabilir** bir release ZIP'ini nasıl ürettiğimizi, neden bu yöntemi seçtiğimizi ve ZIP'in yayınlanmadan önce nasıl doğrulandığını anlatır.

## Kullanım

Python 3.8+ gerektirir. Harici bağımlılık yoktur (sadece stdlib).

```bash
cd plugins/mhm-rentiva
python bin/build-release.py
```

Betik şunları yapar:

1. `mhm-rentiva.php` içinden `MHM_RENTIVA_VERSION` sabitini regex ile okur.
2. `.distignore` dosyasındaki desenleri yükler.
3. `build/zip-staging/mhm-rentiva/` altına temiz bir kopya çıkarır.
4. `build/mhm-rentiva.<version>.zip` dosyasını **POSIX (eğik çizgi)** yolları ile üretir.
5. ZIP içinde **tek bir kök klasör** (`mhm-rentiva/`) olduğunu doğrular.

Beklenen çıktı:

```text
[build] Plugin   : mhm-rentiva
[build] Version  : 4.26.2
[build] Patterns : 58 from .distignore
[build] Staged   : 756 files -> .../build/zip-staging/mhm-rentiva
[build] SUCCESS  : .../build/mhm-rentiva.4.26.2.zip
[build] Size     : 2.55 MB
[build] Verified : single root 'mhm-rentiva/'
```

## Neden Python — Neden `Compress-Archive` veya `git archive` değil

### PowerShell `Compress-Archive` neden iş görmüyor

Windows'ta `Compress-Archive` bir ZIP oluşturur ama yol ayırıcı olarak **ters eğik çizgi (`\`)** kullanır. Bu, ZIP spec'e uygun değildir — spec POSIX (eğik çizgi `/`) ister.

Sonuçlar:

- WordPress core'un `unzip_file()` fonksiyonu dosyayı açar ama log'a **uyarı** basar.
- Bazı hosting panellerinin "Plugin Yükle" akışları bu ZIP'i **reddeder**.
- WordPress.org plugin incelemesi bunu standart dışı kabul eder.

Python'un `zipfile` modülü her platformda **POSIX** yolları üretir. Bu yüzden tercih edilen yol budur.

### `git archive` neden yeterli değil

`git archive` sadece git'e commit edilmiş dosyaları içerir. Geliştirme sırasında oluşturulan `.distignore` kapsamındaki dosyaların (örn. `phpunit.xml`, `build_debug.txt`) çalışma ağacında olup olmaması ZIP'i etkiler. Ayrıca `.distignore` okumaz — yalnızca `.gitattributes` `export-ignore`'a bakar. İki ayrı hariç tutma kaynağını senkronize tutmak fazla gürültü.

### Eski `build-release.ps1` ne durumda

`bin/build-release.ps1` PowerShell betiği hâlâ durur ama **kullanılmıyor**:

- `Compress-Archive` yukarıdaki yol ayırıcı sorununu üretir.
- `composer run check-release` adımı hardcoded bir XAMPP WordPress kurulumuna bağlıdır.
- Release için resmi yol `bin/build-release.py`.

## WordPress'e Kurulabilir ZIP Neye Benzemeli

WordPress admin → Eklentiler → Yeni Ekle → Eklenti Yükle yoluyla yüklenen bir ZIP şu kurallara uymalıdır:

| Kural | Doğru | Yanlış |
|---|---|---|
| Kök klasör | **Tek** klasör, eklenti slug'ı ile (`mhm-rentiva/`) | Versiyonlu isim (`mhm-rentiva.4.26.2/`), iç içe ZIP, dağınık dosyalar |
| Ana dosya | `mhm-rentiva/mhm-rentiva.php` | Kök düzeyinde `mhm-rentiva.php` |
| Yol ayırıcı | `/` (POSIX) | `\` (Windows) |
| ZIP dosya adı | Serbest (`mhm-rentiva.4.26.2.zip` vs) | — ZIP dosya adı **kurulum klasör adını etkilemez** |

**Önemli:** WordPress, plugin klasörü adını **ZIP içindeki tek kök klasörden** alır, ZIP dosya adından değil. Bu yüzden `mhm-rentiva.4.26.2.zip` dosya adı bir sorun teşkil etmez — içindeki tek kök `mhm-rentiva/` olduğu sürece WP kurulum sonrası `wp-content/plugins/mhm-rentiva/` olarak yerleştirir.

### "Ana paket" anti-pattern'i

Geçmişte release paketlerinde kullanılan "ana paket" (ZIP içinde ZIP) yaklaşımı **kırık bir yaklaşımdır**. Kullanıcı indirdiğinde içindeki asıl ZIP'i manuel çıkarmak zorunda kalır. WordPress admin bu yapıyı tanımaz.

`build-release.py` **her zaman** düz yapıda, doğrudan yüklenebilir ZIP üretir. İç içe ZIP yoktur.

## `.distignore` Nasıl Çalışıyor

ZIP'te **olmaması gereken** her şey `.distignore` içine yazılır. Desen formatı WordPress.org SVN `.distignore` standardı ile aynıdır:

```text
# Comment satırları ve boş satırlar yok sayılır.

# Plain name → herhangi bir yol segmentine eşleşirse dışlanır.
.git
vendor
node_modules

# Klasör → tüm alt ağacı dışlar.
tests/
docs/
build/

# Glob pattern → fnmatch ile eşleştirilir.
*.log
*.zip
languages/*-backup-*.po~
```

`build-release.py` bu dosyayı okur ve `stage_files()` sırasında her dosyayı filtreye sokar. Yeni bir "builddan çıkmalı" kategorisi varsa bu dosyaya ekle, betik değişmesi gerekmez.

## ZIP'i Yayınlamadan Önce Doğrulama

Betik kendi kendine tek kök klasör kontrolü yapar. Daha kapsamlı manuel kontrol için:

### 1. Yapıyı incele

```bash
python -c "import zipfile; zf=zipfile.ZipFile('build/mhm-rentiva.4.26.2.zip'); print('\n'.join(sorted({n.split('/')[0] for n in zf.namelist()})))"
```

Tek satır çıktı olmalı: `mhm-rentiva`

### 2. Yolların POSIX olduğunu doğrula

```bash
python -c "import zipfile; zf=zipfile.ZipFile('build/mhm-rentiva.4.26.2.zip'); print('POSIX:', all('\\\\' not in n for n in zf.namelist()))"
```

Çıktı: `POSIX: True`

### 3. WordPress'in kendi `unzip_file()` ile kurulumu simüle et

En net kanıt: WordPress'in dashboard'dan plugin yüklerken çağırdığı tam kodu manuel tetikle.

Bir Docker WP ortamında (örn. DemoSeed):

```bash
docker cp build/mhm-rentiva.4.26.2.zip <wp-container>:/tmp/test.zip
docker exec <wp-container> wp --allow-root eval '
require_once ABSPATH . "wp-admin/includes/file.php";
WP_Filesystem();
$result = unzip_file( "/tmp/test.zip", "/tmp/wp-unzip-test" );
if ( is_wp_error( $result ) ) {
    echo "ERROR: " . $result->get_error_message();
} else {
    $roots = array_diff( scandir( "/tmp/wp-unzip-test" ), array( ".", ".." ) );
    echo "Plugin folder: wp-content/plugins/" . reset( $roots ) . "/";
}
'
```

Beklenen çıktı:

```text
Plugin folder: wp-content/plugins/mhm-rentiva/
```

Bu çıktıyı gördükten sonra ZIP **kesinlikle** doğru kurulur.

## Ne Release ZIP'e Girer, Ne Girmez

### ZIP'in içinde olanlar (756 dosya ~ 2.55 MB)

- `mhm-rentiva.php` — ana plugin dosyası
- `readme.txt`, `README.md`, `LICENSE`, `composer.json`
- `changelog.json`, `changelog-tr.json` — changelog kaynakları
- `uninstall.php` — WP uninstall hook
- `src/` — tüm PHP class'ları
- `templates/` — admin + frontend şablonları
- `assets/` — blok/frontend JS + CSS
- `languages/` — sadece `.po`, `.mo` ve `.pot` (backup `.po~` hariç)

### Dışlananlar (`.distignore` ile)

- `vendor/`, `node_modules/`, `build/`, `bin/`, `stubs/`, `tools/`
- `tests/`, `phpunit.xml`, `.phpunit.result.cache`
- `docs/`, `specs/`, tüm iç dokümantasyon markdown'ları
- `.git/`, `.github/`, `.vscode/`, `.idea/`, IDE config'leri
- `composer.lock`, `package.json`, `package-lock.json`, `webpack.config.js`
- `*.log`, `*.zip`, `build_debug.txt`

## Release Yayınlama Akışı

```bash
# 1. Versiyonu bump et (mhm-rentiva.php, readme.txt Stable tag, changelog.json, changelog-tr.json)
# 2. Testler geçsin
docker exec rentiva-dev-wpcli-1 bash -c "cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage --colors"

# 3. ZIP üret
python bin/build-release.py

# 4. Commit + tag + push
git add -A && git commit -m "chore(release): v<version>"
git tag v<version>
git push origin main --tags

# 5. GitHub Release oluştur, ZIP'i ekle
gh release create v<version> build/mhm-rentiva.<version>.zip \
  --title "v<version>" \
  --notes-file /tmp/release-notes.md \
  --repo MaxHandMade/mhm-rentiva
```

Asset'i sonradan değiştirmek gerekirse:

```bash
gh release upload v<version> build/mhm-rentiva.<version>.zip --clobber --repo MaxHandMade/mhm-rentiva
```

## Hızlı Sorun Giderme

| Belirti | Sebep | Çözüm |
|---|---|---|
| ZIP içinde birden fazla kök klasör | `build-release.py` `Verified` adımında patlar | `.distignore`'a `build/` olduğundan emin ol; `build/zip-staging/` dışında bir şey kalmasın |
| WordPress "eklenti yüklenemedi" diyor | ZIP'te `\` var (manuel `Compress-Archive`) | **`build-release.py` kullan**, PowerShell ile sıkıştırma |
| Plugin klasörü `mhm-rentiva.4.26.2` olarak kuruluyor | ZIP içinde tek kök `mhm-rentiva/` yok (ya dosyalar kökte ya da yanlış isimli klasör) | Betiği yeniden çalıştır; `Verified : single root 'mhm-rentiva/'` satırını gör |
| ZIP'te `build_debug.txt`, `.phpunit.result.cache` gibi çöp | Yeni dosya `.distignore`'da değil | `.distignore`'a ekle, yeniden build et |
| `ERROR: could not find MHM_RENTIVA_VERSION` | `mhm-rentiva.php` içinde `define` satırı regex'e uymuyor | Regex: `define( 'MHM_RENTIVA_VERSION', 'x.y.z' );` formatına uymalı |
