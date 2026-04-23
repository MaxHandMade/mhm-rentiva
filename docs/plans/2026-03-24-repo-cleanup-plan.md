# Repo Cleanup & Public Release Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Sıfırdan temiz bir git history oluşturarak `MaxHandMade/mhm-rentiva` reposunu public yapacak hale getirmek.

**Architecture:** Mevcut `.git` klasörü silinir, `git init` ile yeniden başlatılır. Sadece onaylı WordPress plugin dosyaları commit edilir. GitHub'a force push yapılır. Repo public yapılır.

**Tech Stack:** Git, GitHub CLI (`gh`)

---

### ⚠️ ÖNEMLİ UYARILAR

- Bu işlem **geri alınamaz** — mevcut git history tamamen silinir
- Force push yapılacak — GitHub'daki 378 commit yok olacak
- İşlem öncesi local yedek alınacak
- Plugin path: `c:/projects/rentiva-dev/plugins/mhm-rentiva`

---

### Task 1: Local Yedek Al

**Files:**
- Hiç dosya değişmez — sadece yedek alınır

**Step 1: Yedek klasörü oluştur**

```bash
mkdir -p c:/projects/rentiva-dev/backups
```

**Step 2: Tüm plugin klasörünü yedekle**

```bash
cd c:/projects/rentiva-dev
tar -czf backups/mhm-rentiva-backup-$(date +%Y%m%d-%H%M).tar.gz plugins/mhm-rentiva/
```

**Step 3: Yedek boyutunu doğrula**

```bash
ls -lh c:/projects/rentiva-dev/backups/
```
Expected: `.tar.gz` dosyası oluşmuş, boyut > 0

---

### Task 2: .gitignore'u Güncelle

**Files:**
- Modify: `plugins/mhm-rentiva/.gitignore`

Bu adımı `.git` silmeden ÖNCE yapıyoruz — yeni history'de temiz gitignore olsun.

**Step 1: Aşağıdaki satırları .gitignore'a ekle (dosyanın sonuna)**

```
# ============================================================================
# SECTION 12: Public Repo — Developer-Only Directories
# ============================================================================
docs/
tests/
.agent/
.agents/
.ai/
bin/
tools/
AGENTS.md
build_debug.txt
test_output.txt
```

**Step 2: .gitignore'u doğrula**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
cat .gitignore | grep -E "^docs/|^tests/|^\.agent"
```
Expected: Bu satırlar görünüyor

---

### Task 3: Kalacak Dosyaları Doğrula

**Files:** Hiç değişmez — sadece kontrol

**Step 1: README-tr.md ve changelog-tr.json var mı kontrol et**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
ls README*.md changelog*.json SHORTCODES.md readme.txt LICENSE uninstall.php 2>&1
```
Expected: Tüm dosyalar mevcut (hata yok)

**Step 2: Kaldırılacak klasörleri listele (son kez)**

```bash
ls -la | grep -E "^\-|^d" | grep -E "\.agent|\.agents|\.ai|bin|docs|tests|tools|AGENTS"
```
Expected: Bunların hepsi mevcut (silmeden önce son kontrol)

---

### Task 4: Git History'yi Sıfırla

**⚠️ GERİ ALINAMAZ ADIM**

**Step 1: .git klasörünü sil**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
rm -rf .git
```

**Step 2: Yeni git repo başlat**

```bash
git init
git remote add origin https://github.com/MaxHandMade/mhm-rentiva.git
```

**Step 3: Doğrula**

```bash
git status
git remote -v
```
Expected: `On branch master` (veya `main`), remote `origin` görünüyor

**Step 4: Branch adını main yap**

```bash
git branch -M main
```

---

### Task 5: Dosyaları Stage Et

**Step 1: Kalacak dosya ve klasörleri add et**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git add .github/
git add assets/
git add languages/
git add src/
git add templates/
git add mhm-rentiva.php
git add uninstall.php
git add README.md
git add README-tr.md
git add readme.txt
git add LICENSE
git add SHORTCODES.md
git add changelog.json
git add changelog-tr.json
git add .gitignore
git add .gitattributes
git add .distignore
```

**Step 2: Stage edilen dosyaları doğrula**

```bash
git status
```
Expected: Sadece onaylı dosyalar "Changes to be committed" altında
Kaldırılacak klasörler (docs/, tests/, .agent/ vb.) görünmemeli

**Step 3: Kaldırılacak bir şey stage'e girdiyse kontrol et**

```bash
git diff --cached --name-only | grep -E "^docs/|^tests/|^\.agent|^\.ai/|^bin/|^tools/|^AGENTS"
```
Expected: Hiçbir çıktı (bu dosyalar stage'de olmamalı)

---

### Task 6: İlk Commit

**Step 1: Commit yap**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git commit -m "chore: initial public release — clean repository

Remove developer-only directories (docs, tests, .agent, .ai, bin, tools).
Keep only WordPress plugin source files.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

**Step 2: Commit'i doğrula**

```bash
git log --oneline
git show --stat HEAD | tail -20
```
Expected: 1 commit, sadece onaylı dosyalar listeleniyor

---

### Task 7: GitHub'a Push Et

**Step 1: Force push**

```bash
cd c:/projects/rentiva-dev/plugins/mhm-rentiva
git push --force origin main
```

**Step 2: Push'u doğrula**

```bash
git log --oneline origin/main
```
Expected: Tek commit görünüyor

---

### Task 8: Repo'yu Public Yap

**Step 1: GitHub CLI ile public yap**

```bash
gh repo edit MaxHandMade/mhm-rentiva --visibility public
```

**Step 2: Doğrula**

```bash
gh repo view MaxHandMade/mhm-rentiva --json visibility -q .visibility
```
Expected: `public`

---

### Task 9: GitHub Actions'ı Tetikle ve Doğrula

**Step 1: Workflow run'larını listele**

```bash
gh run list --repo MaxHandMade/mhm-rentiva --limit 5
```
Expected: Push ile tetiklenen "Testing" workflow görünüyor

**Step 2: Son run'ın sonucunu bekle**

```bash
gh run watch --repo MaxHandMade/mhm-rentiva
```
Expected: Tüm joblar yeşil ✅ (PHPCS + 6 PHPUnit matrix job)

---

### Task 10: Tema Repo'sunun CI Fix'ini Push Et

Tema repo'sunda sadece `ci.yml` değişti (actions @v4 → @v5).

**Step 1: Commit ve push**

```bash
cd c:/projects/rentiva-dev/themes/mhm-rentiva-theme
git add .github/workflows/ci.yml
git commit -m "ci: upgrade actions/checkout and actions/cache to v5

Fixes Node.js 20 deprecation warnings in GitHub Actions.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
git push origin main
```

**Step 2: Doğrula**

```bash
gh run list --repo MaxHandMade/mhm-rentiva-theme --limit 3
```

---

### Tamamlama Kriterleri

- [ ] `MaxHandMade/mhm-rentiva` repo'su public
- [ ] Repoda sadece WordPress plugin dosyaları var
- [ ] `docs/`, `tests/`, `.agent/`, `.ai/`, `bin/`, `tools/` görünmüyor
- [ ] GitHub Actions "Testing" workflow yeşil (7 job)
- [ ] README.md, README-tr.md, SHORTCODES.md görünüyor
- [ ] Tema repo'su CI uyarısız çalışıyor
