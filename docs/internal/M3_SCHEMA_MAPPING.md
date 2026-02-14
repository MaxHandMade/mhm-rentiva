# M3 — Featured & Status Meta Schema Mapping

Bu döküman, MHM Rentiva projesindeki dağınık meta anahtarlarının standardizasyon planını içerir.

## Mevcut Durum Analizi (Discovery)

Veritabanı denetimi sonucunda "Öne Çıkarılan" (Featured) ve "Durum" (Status/Availability) bilgileri için birden fazla anahtar kullanıldığı tespit edilmiştir.

### 1. Featured (Öne Çıkarılan) Grubu
| Meta Key | Durum | Kullanım Amacı |
| :--- | :--- | :--- |
| `_mhm_rentiva_featured` | **HEDEF (Standard)** | Mevcut `MetaKeys::VEHICLE_FEATURED` sabiti. Değer: '1' veya '0'. |
| `_mhm_rentiva_is_featured` | **Legacy / Duplicate** | Eski sürümlerden kalma veya hatalı isimlendirme. |

### 2. Status & Availability (Durum) Grubu
| Meta Key | Durum | Kullanım Amacı |
| :--- | :--- | :--- |
| `_mhm_vehicle_status` | **HEDEF (Standard)** | Mevcut standard. Değerler: 'active', 'inactive', 'maintenance'. |
| `_mhm_vehicle_availability`| **Legacy (Deprecated)**| `MetaKeys.php` içinde legacy olarak işaretlenmiş. |
| `_mhm_rentiva_availability`| **Legacy / Untracked** | Bazı araçlarda bulunan dağınık veri. |

## Göç (Migration) Stratejisi

### Hedef Yapı
Tüm araçlar (vehicle) için aşağıdaki anahtarlar tek otorite olacaktır:
1. `_mhm_rentiva_featured` (bool string: '1', '0')
2. `_mhm_vehicle_status` (enum: 'active', 'inactive', 'maintenance')

### Dönüşüm Kuralları (Mapping Rules)

#### Featured Migration
- Eğer `_mhm_rentiva_is_featured` mevcutsa ve `_mhm_rentiva_featured` boşsa; değeri taşı ve eskiyi sil.
- Eğer her ikisi de varsa; `_mhm_rentiva_featured` değerini koru, eskiyi sil.

#### Status Migration
- `_mhm_vehicle_availability` veya `_mhm_rentiva_availability` içindeki '1' veya 'active' değerlerini `_mhm_vehicle_status` -> 'active' olarak taşı.
- '0' veya 'passive' değerlerini `_mhm_vehicle_status` -> 'inactive' olarak taşı.
- Taşıma sonrası eski anahtarları temizle.

## Deterministic Conflict Policy (Çakışma Çözüm Politikası)

Eğer bir araçta hem HEDEF anahtar hem de LEGACY anahtar farklı değerlerle mevcutsa aşağıdaki kurallar uygulanır:

### 1. Featured Conflict (featured vs is_featured)
- **Senaryo:** `_mhm_rentiva_featured = 0` VE `_mhm_rentiva_is_featured = 1`.
- **Kural:** **Standard Key Wins**. HEDEF anahtardaki değer (`0`) korunur.
- **İşlem:** `_mhm_rentiva_is_featured` silinir, hedef anahtar güncellenmez.

### 2. Logging & Reporting
- Migration script'i her çakışmayı araç ID'si ile birlikte `[CONFLICT] Vehicle ID #XXX: Standard key wins` formatında loglamalıdır.
- Dry-run raporunda özet bilgi sunulmalıdır:
    - **Conflicts Detected:** [Count]
    - **Conflicts Detected:** [Count]
    - **Resolution Policy:** Standard key wins

## Status Enum Validation Layer (Veri Güvenliği)

Eski verilerden (`_mhm_vehicle_availability`) gelen değerlerin hatalı veya beklenmedik olması durumunda (ör. 'yes', 'true', 'available'), sistemin bozulmaması için şu kurallar uygulanır:

- **İzin Verilen Değerler (Allowed):** `['active', 'inactive', 'maintenance']`
- **İşlem Akışı:**
    1. Legacy değer okunur ve hedefe (`active/inactive`) map edilir.
    2. Map edilen değer `in_array($mapped_value, $allowed, true)` kontrolünden geçirilir.
    3. Eğer değer listede yoksa; işlem **SKIP** edilir, `[INVALID_STATUS] Vehicle ID #XXX: Value 'YYY' is not allowed` şeklinde loglanır.

Bu katman sayesinde silent corruption (sessiz veri bozulması) riski tamamen ortadan kaldırılır.

## Risk Analizi
- **Veri Kaybı:** migration işlemi öncesi `wp db export` zorunludur.
- **Cache Drift:** Migration sonrası `CacheManager::clear_all_vehicles()` tetiklenmelidir.
- **Third-party Sync:** Elementor widget'ları ve özel sorgular (WP_Query) bu yeni anahtarları kullanacak şekilde güncellenmelidir.
