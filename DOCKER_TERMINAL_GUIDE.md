# 🐳 Docker + WordPress Geliştirme Ortamı
## Terminal Komutları – Başlangıç ve Günlük Kullanım Kılavuzu

Bu doküman, WordPress eklenti ve tema geliştirme sürecinde Docker kullanan geliştiriciler için en sık kullanılan terminal komutlarını ve ne işe yaradıklarını açıklar.
Yeni başlayanlar için adım adım anlatım içerir; günlük profesyonel kullanım için referans olarak tasarlanmıştır.

---

## Tüm Projelerin Port Haritası

> [!NOTE]
> Birden fazla proje aynı anda çalışabilir. Port çakışmasını önlemek için her projeye ayrı port aralığı atanmıştır.

| Proje | WordPress | phpMyAdmin | Mailpit UI | SMTP | MariaDB |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **rentiva-dev** | [8080](http://localhost:8080) | [8084](http://localhost:8084) | [8025](http://localhost:8025) | 1025 | 3307 |
| **rentiva-release** | [8082](http://localhost:8082) | [8086](http://localhost:8086) | [8026](http://localhost:8026) | 1026 | — |
| **rentiva-lisans** | [8083](http://localhost:8083) | [8087](http://localhost:8087) | [8027](http://localhost:8027) | 1027 | — |
| **excursionsworld** | [8088](http://localhost:8088) | [8089](http://localhost:8089) | [8028](http://localhost:8028) | 1028 | 3308 |
| **mirekskursii** | [8090](http://localhost:8090) | [8091](http://localhost:8091) | [8029](http://localhost:8029) | 1029 | 3309 |
| **maxhandmade** | [8092](http://localhost:8092) | [8093](http://localhost:8093) | [8030](http://localhost:8030) | 1030 | 3310 |
| **bozcon** | [8094](http://localhost:8094) | [8095](http://localhost:8095) | [8031](http://localhost:8031) | 1031 | 3311 |
| **oldbozcon** | [8096](http://localhost:8096) | [8097](http://localhost:8097) | [8032](http://localhost:8032) | 1032 | 3312 |

---

## 1. Yeni Proje Kurulum Kontrol Listesi

> [!IMPORTANT]
> Yeni bir proje Docker'a taşınırken aşağıdaki adımların **tamamı** yapılmalıdır. Herhangi birini atlarsanız site bozuk ya da yavaş çalışır.

### 1.1 Gerekli klasör yapısı (Windows tarafında)

```
proje-adi/
├── plugins/          ← Geliştirilen eklentiler (her eklenti ayrı klasör)
├── themes/           ← Aktif tema ve child tema klasörleri
├── mu-plugins/       ← Must-use plugins (mailpit-smtp.php, fs-direct.php)
├── uploads/          ← Medya dosyaları (canlıdan kopyalanmalı)
├── opcache.ini       ← OPcache ayarları
├── php.ini           ← PHP bellek ve upload limitleri
├── docker-compose.yml
├── Dockerfile.cli
└── .env
```

### 1.2 php.ini — Mutlaka olmalı

Elementor ve büyük eklentiler 128MB varsayılan limiti aşar, site boş açılır.

```ini
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
```

### 1.3 docker-compose.yml — Zorunlu mount'lar

```yaml
volumes:
  - wpdata:/var/www/html
  - ./plugins/<gelistirilen-eklenti>:/var/www/html/wp-content/plugins/<gelistirilen-eklenti>
  - ./themes/<aktif-tema>:/var/www/html/wp-content/themes/<aktif-tema>
  - ./mu-plugins:/var/www/html/wp-content/mu-plugins
  - ./uploads:/var/www/html/wp-content/uploads        # Resimler için zorunlu
  - ./opcache.ini:/usr/local/etc/php/conf.d/opcache.ini:ro
  - ./php.ini:/usr/local/etc/php/conf.d/php-custom.ini:ro
```

> [!WARNING]
> `./plugins` veya `./themes` gibi **tüm klasörü** mount ETME. Sadece geliştirdiğin eklenti/temayı mount et. Geniş mount Windows 9P dosya sistemi üzerinden tüm dosyaları okur ve siteyi çok yavaşlatır.

### 1.4 Canlıdan kopyalanması gereken klasörler

Docker volume'a yalnızca bu klasörlerin kopyalanması gerekir — bunlar volume içinde yaşar, Windows'tan mount edilmez:

| Klasör | Neden |
| :--- | :--- |
| `wp-content/plugins/` | Tüm kurulu eklentiler |
| `wp-content/themes/` | Tüm kurulu temalar |

Kopyalama komutu (projeye göre volume adını değiştir):

```bash
docker run --rm \
  -v <proje>_wpdata:/wp \
  -v "C:/projects/<proje>/plugins":/tmp/plugins \
  -v "C:/projects/<proje>/themes":/tmp/themes \
  alpine sh -c "cp -r /tmp/plugins/. /wp/wp-content/plugins/ && cp -r /tmp/themes/. /wp/wp-content/themes/ && echo TAMAM"
```

### 1.5 fs-direct.php — Admin panelinden güncelleme için zorunlu

`mu-plugins/` klasöründe bu dosya olmadan WordPress, admin panelinden eklenti/tema güncellemeye çalışınca FTP kimlik bilgileri ister ya da izin hatası verir.

```php
<?php
// mu-plugins/fs-direct.php
if ( ! defined( 'FS_METHOD' ) ) {
    define( 'FS_METHOD', 'direct' );
}
```

### 1.6 Canlıdan taşınırken: Elementor CSS yenile

URL değiştiğinde (canlı domain → localhost) Elementor CSS dosyaları geçersiz kalır, sayfa boş görünür:

```bash
docker exec <proje>-wpcli-1 bash -c "wp elementor flush-css --allow-root && wp cache flush --allow-root"
```

### 1.6 Sorun Giderme

| Belirti | Neden | Çözüm |
| :--- | :--- | :--- |
| Sayfa bomboş (admin bar görünüyor) | Elementor CSS yok | `wp elementor flush-css --allow-root` |
| Resimler kırık (broken image) | `uploads/` mount edilmemiş | docker-compose'a `./uploads` mount ekle, `up -d` |
| Site çok yavaş | Geniş klasör mount (./plugins tümü) | Sadece geliştirilen eklentiyi mount et |
| PHP fatal error (memory exhausted) | Bellek limiti 128MB yetersiz | `php.ini` ile `memory_limit = 512M` ayarla |
| Mount değişikliği yansımıyor | `restart` yetersiz | `docker compose up -d` kullan |
| Yeni eklenti/tema görünmüyor | Volume'da yok, mount da yok | Alpine kopyalama komutuyla volume'a ekle (bkz. 1.4) |
| Admin panelinden eklenti/tema güncellenemiyor | `FS_METHOD` tanımlı değil, WordPress FTP istiyor | `mu-plugins/fs-direct.php` oluştur (bkz. 1.5) |

---

## 2. Temel Kavramlar (Kısa Özet)

**Container:**  
Çalışan uygulama örneği (WordPress, MariaDB, phpMyAdmin, wp-cli vb.)

**Image (İmaj):**  
Container’ların üretildiği kalıp (ör. `wordpress:php8.2-apache`)

**Volume:**  
Kalıcı veriler için kullanılan depolama alanı (özellikle veritabanı)

**Network:**  
Container’ların birbiriyle haberleşmesini sağlayan sanal ağ

---

## 2. Yapılandırma ve Ortam Değişkenleri (.env)

Proje kök dizinindeki `.env` dosyası, Docker ortamının ve veritabanının kimlik bilgilerini tutar.

| Değişken | Varsayılan Değer | Açıklama |
| :--- | :--- | :--- |
| `MYSQL_DATABASE` | `wp` | WordPress veritabanı adı |
| `MYSQL_USER` | `wp` | Veritabanı kullanıcı adı |
| `MYSQL_PASSWORD` | `wp` | Veritabanı şifresi |
| `MYSQL_ROOT_PASSWORD` | `root` | MySQL root şifresi |

---

## 3. Projeyi Başlatma / Durdurma

### Ortamı başlatma
```bash
docker compose up -d
```
Tüm servisleri (WordPress, DB, phpMyAdmin, wpcli) arka planda başlatır.

### Ortamı durdurma
```bash
docker compose down
```
Container’ları kapatır, verileri (volume) silmez.

### Her şeyi sıfırlama (DİKKAT!)
```bash
docker compose down -v
```
Veritabanı dahil tüm veriler silinir. Sadece bilinçli olarak “temiz kurulum” yapmak istendiğinde kullanılmalıdır.

---

## 4. Veritabanı Yönetimi (Yedekleme ve Geri Yükleme)

### Veritabanı yedeği al (Export)
```bash
docker compose exec db mysqldump -u wp -pwp wp > backup.sql
```

### Veritabanı yedeğini yükle (Import)
```bash
docker compose exec -T db mysql -u wp -pwp wp < backup.sql
```

---

## 5. Container İçine Girme (wpcli / wordpress)

### wpcli container içine bash ile girme
```bash
docker compose exec wpcli bash
```

### wordpress container içine girme
```bash
docker compose exec wordpress bash
```
> [!NOTE]  
> Container içine girdiğinde Linux terminali açılır ve WordPress ortamında komut çalıştırabilirsin.

---

## 6. WP-CLI (WordPress CLI) Komutları

Aşağıdaki komutlar container içindeyken (veya dışarıdan `docker compose exec wpcli ...` olarak) çalıştırılmalıdır.

### Yüklü eklentileri listele
```bash
wp plugin list --allow-root
```

### Bir eklentiyi etkinleştir
```bash
wp plugin activate mhm-rentiva --allow-root
```

### Temaları listele
```bash
wp theme list --allow-root
```

---

## 7. Composer Scripts ve Testler

Geliştirme sürecinde `vendor/bin` dizinine gitmek yerine eklenti klasörü içindeki Composer script'lerini kullanabilirsin.

### Eklenti klasörüne git (Container içinde)
```bash
cd wp-content/plugins/mhm-rentiva
```

### Testleri ve analizleri çalıştır
```bash
composer test           # PHPUnit testlerini çalıştırır
composer phpcs          # Kod standartlarını kontrol eder
composer phpcbf         # Kod hatalarını otomatik düzeltir
composer check-release  # Release öncesi tüm kontrolleri (test+phpcs+check) yapar
```

---

## 8. PHPUnit Testlerini Docker Üzerinden Çalıştırma

> [!IMPORTANT]
> `vendor/bin/phpunit` komutu **doğrudan Windows terminalinde veya Git Bash'te çalışmaz** — WordPress test ortamı, veritabanı ve WP_TESTS_DIR Docker container'ı içindedir.
> Testler **mutlaka** `rentiva-dev-wpcli-1` container'ı üzerinden çalıştırılmalıdır.

---

### Sabit Değerler

| Değer | Açıklama |
| :--- | :--- |
| Container adı | `rentiva-dev-wpcli-1` |
| Plugin dizini (container içi) | `/var/www/html/wp-content/plugins/mhm-rentiva` |
| Temel komut | `vendor/bin/phpunit --no-coverage` |

---

### 8.1 Tüm Test Paketini Çalıştırma (Full Suite)

**Windows PowerShell veya Claude Bash aracı üzerinden:**
```bash
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage 2>&1'"
```

**Doğrudan Linux/Mac terminali veya container içinden:**
```bash
docker exec rentiva-dev-wpcli-1 bash -c \
  'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage 2>&1'
```

> [!NOTE]
> Tam suite yaklaşık 12–15 saniye sürer. `timeout` değeri en az `300000` (ms) olmalıdır.

---

### 8.2 Tek Bir Test Sınıfını Filtreleme

```bash
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter SettingsHandlerTest --no-coverage 2>&1'"
```

---

### 8.3 Tek Bir Test Metodunu Filtreleme

```bash
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter SettingsHandlerTest::it_handles_rest_settings_save_action --no-coverage 2>&1'"
```

---

### 8.4 Birden Fazla Sınıfı Aynı Anda Filtreleme

```bash
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --filter \"SettingsHandlerTest|SettingsServiceTest\" --no-coverage 2>&1'"
```

---

### 8.5 Sonuçları Okuma (Özet)

Testin sonundaki satıra bakarak durumu anla:

| Çıktı | Anlam |
| :--- | :--- |
| `OK (N tests, M assertions)` | Tüm testler geçti |
| `FAILURES! Tests: N, Failures: X` | X adet assertion başarısız |
| `ERRORS! Tests: N, Errors: X` | X adet PHP hatası (TypeError, sınıf bulunamadı vb.) |
| `Skipped: X` | X adet test atlandı (beklenen) |

---

### 8.6 Claude / LLM için Kullanım Notları

- **Claude Bash aracı** Windows ortamında çalışır; Docker komutları için `powershell -Command "..."` sarmalayıcısı gerekir.
- **Timeout:** Tam suite için `timeout: 300000`, tek test için `timeout: 60000` kullan.
- **`--no-coverage`** bayrağını her zaman ekle — aksi hâlde Xdebug coverage analizi testi 10× yavaşlatır.
- Çıktı kesilirse (`truncated`) sadece sonucu görmek için `| tail -60` eklenebilir:

```bash
powershell -Command "docker exec rentiva-dev-wpcli-1 bash -c 'cd /var/www/html/wp-content/plugins/mhm-rentiva && vendor/bin/phpunit --no-coverage 2>&1 | tail -60'"
```

---

## 9. Volume ve Temizlik İşlemleri

### Tüm volume’ları listele
```bash
docker volume ls
```

### Kullanılmayan her şeyi temizle (Pruge)
```bash
docker system prune -a --volumes
```

> [!WARNING]  
> Veritabanını tutan volume silinirse WordPress sıfırlanır.

---

## 10. Log (Hata Ayıklama)

### WordPress container loglarını izle
```bash
docker compose logs -f wordpress
```

### Veritabanı loglarını izle
```bash
docker compose logs -f db
```

---

## 11. Özet

Bu kılavuzdaki komutları öğrenerek:
- WordPress + Docker ortamını güvenle yönetebilir,
- Eklenti ve tema geliştirirken test ve kalite araçlarını çalıştırabilir,
- Docker altyapısını bilinçli şekilde kontrol edebilirsin.