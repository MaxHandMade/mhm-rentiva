# Repo Cleanup & Public Release Design
**Tarih:** 2026-03-24
**Durum:** Onaylandı

## Amaç

`MaxHandMade/mhm-rentiva` GitHub reposunu temizleyerek public yapma.
Eklenti lite/pro modelde — tam open source değil. Sadece WordPress plugin dosyaları public olacak.

## Yaklaşım

**Yeni temiz repo** (git history sıfırlama):
- Mevcut `.git` klasörünü sil
- `git init` ile baştan başla
- Sadece onaylı dosyaları commit et
- GitHub'a force push (`MaxHandMade/mhm-rentiva` adı korunuyor)
- Repo'yu public yap

Seçim gerekçesi: Başka kullanıcı/fork yok, geliştirme aşamasında, en temiz sonuç.

## Kalacak Dosyalar ✅

```
mhm-rentiva/
├── .github/
│   └── workflows/
│       └── testing.yml       # CI (actions @v5)
├── assets/                   # CSS, JS, görseller
├── languages/                # .pot, .po, .mo
├── src/                      # Tüm PHP sınıfları
├── templates/                # Template dosyaları
├── mhm-rentiva.php           # Ana plugin dosyası
├── uninstall.php
├── README.md                 # GitHub readme (EN)
├── README-tr.md              # GitHub readme (TR)
├── readme.txt                # WP.org readme
├── LICENSE
├── SHORTCODES.md             # Public shortcode dokümantasyonu
├── changelog.json            # Versiyon geçmişi (EN)
├── changelog-tr.json         # Versiyon geçmişi (TR)
├── .gitignore                # Güncellenmiş
├── .gitattributes
└── .distignore
```

## Kaldırılacak Klasör/Dosyalar ❌

| Öğe | Neden |
|-----|-------|
| `.agent/` | AI geliştirme araçları |
| `.agents/skills/mhm-architect/` | AI geliştirme araçları |
| `.ai/` | AI araçları |
| `bin/` | Geliştirme scriptleri |
| `docs/` (tümü) | İç mimari, audit, client spec |
| `tests/` | Pro/Lite yapısı — public değil |
| `tools/` | Geliştirme araçları |
| `vendor/squizlabs/...` | Stray vendor dosyası |
| `AGENTS.md` | AI araç dokümantasyonu |

## .gitignore Güncellemeleri

Şu an gitignore'da olmayan ama eklenmesi gerekenler:
- `docs/`
- `tests/`
- `.agent/`
- `.agents/`
- `.ai/`
- `bin/`
- `tools/`
- `AGENTS.md`

## Adımlar (Özet)

1. Tüm local değişiklikleri stash / yedekle
2. `.git` klasörünü sil
3. `git init && git remote add origin <url>`
4. `.gitignore`'u güncelle
5. Sadece kalacak dosyaları `git add`
6. `git commit -m "chore: initial public release — clean repository"`
7. `git push --force origin main`
8. GitHub'da repo'yu public yap

## Riskler

- Force push geri alınamaz (önceki history silinir) — kabul edildi
- `docs/plans/` bu işlemden sonra local'de kalacak, repoya girmeyecek
