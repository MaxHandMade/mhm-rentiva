name: mhm-git-ops
description: GitHub repo senkronizasyonu, Çift Repo Yönetimi ve Docusaurus uyumlu commit işlemleri.
---

# MHM Git Operations Skill

Bu proje **İKİ AYRI** Git deposundan oluşmaktadır. Agent, Docusaurus yapısına saygı duymalı ve gereksiz build dosyalarını repoya göndermemelidir.

## 1. Mimari ve Konumlar (Architecture)

### 🅰️ Plugin Reposu (Ana Kod)
- **Konum:** `./` (Proje Kök Dizini)
- **Repo Adı:** `MaxHandMade/mhm-rentiva`
- **Kapsam:** `src/`, `assets/`, `includes/`, `languages/`, `mhm-rentiva.php` ve kökteki diğer dosyalar.

### 🅱️ Documentation Reposu (Web Sitesi)
- **Konum:** `./website`
- **Repo Adı:** `MaxHandMade/mhm-rentiva-docs`
- **Kapsam (Sadece Kaynak Dosyalar):**
  - `docs/` (Markdown içerikler)
  - `blog/` (Blog yazıları)
  - `src/` (React bileşenleri ve sayfalar)
  - `static/` (Görseller)
  - `docusaurus.config.js`, `sidebars.js`, `package.json`
- **YASAKLI ALANLAR (Ignore):**
  - `node_modules/` (ASLA commit etme)
  - `.docusaurus/` (Cache dosyaları)
  - `build/` (Output dosyaları)
- **Kritik Kural:** Bu klasördeki işlemler için **MUTLAKA** önce `cd website` komutu çalıştırılmalı, işlem bitince `cd ..` ile geri dönülmelidir.

## 2. Akıllı Commit Kuralları (Conventional Commits)
- `feat:` Yeni özellik (Örn: Yeni widget, yeni API endpoint)
- `fix:` Hata düzeltme (Örn: USD hatası, Nonce fix)
- `docs:` Dökümantasyon (Örn: .md dosyası güncelleme)
- `chore:` Yapılandırma, npm paket güncellemesi veya build ayarları

## 3. Komut Zincirleri (Command Chains)

Agent, aşağıdaki senaryolara göre ilgili komut bloklarını **sırasıyla** çalıştırmalıdır.

### Senaryo 1: Sadece Plugin Kodları Değiştiyse
```bash
# Ana dizinde olduğundan emin ol
cd .
git status
git add .
git commit -m "fix: [Ajanın oluşturduğu mantıklı mesaj]"
git push origin main
```
### Senaryo 2: Sadece Dökümanlar Değiştiyse (Docusaurus)
```bash
# Website klasörüne gir
cd website
# Gereksiz dosyaların (node_modules vb.) eklenmediğinden emin olmak için status kontrolü şart
git status
git add .
git commit -m "docs: [Ajanın oluşturduğu mantıklı mesaj]"
git push origin main
# MUTLAKA ANA DİZİNE DÖN
cd ..
```
### Senaryo 3: Her İkisi De Değiştiyse (Full Sync)
- Sıralama Önemlidir: Önce Alt Repo (Docs), Sonra Ana Repo (Plugin).
```bash
# Adım 1: Docs Reposunu Gönder
cd website
git add .
git commit -m "docs: update documentation content"
git push origin main
cd ..

# Adım 2: Plugin Reposunu Gönder
git add .
git commit -m "feat/fix: update plugin logic"
git push origin main
```

## 4. Güvenlik ve Kontrol Listesi
1. Dizin Kontrolü: website klasörüne git add . yapmadan önce, .gitignore dosyasının node_modules klasörünü engellediğinden emin ol.
2. Geri Dönüş: website içindeki işlem biter bitmez cd .. ile ana dizine dön.
3. Branch: Push yapmadan önce hangi branch'te olduğunu git branch ile kontrol et (Genelde main).
4. Hata Yönetimi: Eğer git push hata verirse (conflict), git pull --rebase dene.
