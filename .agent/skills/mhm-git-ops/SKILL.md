---
name: mhm-git-ops
description: GitHub repo senkronizasyonu ve otomatik commit mesajı oluşturucu.
---

# MHM Git Ops Skill

Bu skill, yerel diskteki değişiklikleri GitHub repolarına (Plugin ve Docs) göndermek için kullanılır. En büyük özelliği, **Commit Mesajını** yapılan değişikliklere göre otomatik yazmasıdır.

## 1. Yetenekler

### A. Akıllı Commit (Smart Commit)
Kodlardaki değişikliği (`git diff`) okur ve "Bug fix yapıldı" yerine "TransferCartIntegration.php dosyasındaki Nonce hatası giderildi" gibi profesyonel bir mesaj oluşturur.

### B. Standart Commit Mesajları (Conventional Commits)
Otomatik changelog oluşturabilmek için aşağıdaki önekleri kullanır:
- `feat:` Yeni özellik (Minor versiyonu artırır)
- `fix:` Hata düzeltmesi (Patch versiyonu artırır)
- `docs:` Sadece dökümantasyon değişikliği
- `style:` Formatlama, noktalama (Kod çalışmasını etkilemez)
- `refactor:` Ne hata düzelten ne de özellik ekleyen kod değişikliği
- `chore:` Build süreçleri, kütüphane güncellemeleri

### C. Çift Repo Yönetimi
1. **Plugin:** `MaxHandMade/mhm-rentiva`
2. **Docs:** `MaxHandMade/mhm-rentiva-docs`

## 2. Kullanım Komutları (Prompt)

**Senaryo 1: Sadece Eklentiyi Yükle**
> "Eklentideki değişiklikleri `git-ops` ile gönder. Mesajı sen bul."

**Senaryo 2: Dökümanı Yükle**
> "Dökümantasyon sitesini güncelledim. `git-ops` ile docs reposuna pushla."

**Senaryo 3: Hepsini Gönder (Full Sync)**
> "Bugünkü çalışmayı bitirdim. Her iki repoyu da senkronize et ve bilgisayarı kapatmaya hazırla."

## 3. Çalışma Mantığı (Workflow)

Skill tetiklendiğinde şu adımları izler:

**A. Plugin Reposu İçin:**
1. `cd [root]`
2. `git status`
3. `git add .`
4. `git commit -m "[AI Message]"`
5. `git push origin [current_branch]`

**B. Docs Reposu İçin:**
1. `cd website`
2. `git status`
3. `git add .`
4. `git commit -m "[Conventional Message]"`
5. `git push origin [current_branch]`

## 4. Dallanma Stratejisi (Branching)
- **Ana Dallar:** `main` (Production), `develop` (Staging/Dev)
- **Geçici Dallar:**
  - `feature/[isim]`: Yeni özellikler için.
  - `fix/[isim]`: Bug fixler için.
  - `hotfix/[isim]`: Acil production düzeltmeleri.

## 5. Güvenlik ve Çakışma Önleme
Commit atılmadan önce her zaman `git status` kontrolü yapılır. Uzak sunucu ile çakışma ihtimaline karşı Push öncesi `git pull --rebase` önerilir.

## 6. Sürüm Etiketleme (Tagging)
Yeni bir sürüm yayınlandığında:
`git tag -a v4.5.x -m "Sürüm mesajı"`
`git push origin --tags`