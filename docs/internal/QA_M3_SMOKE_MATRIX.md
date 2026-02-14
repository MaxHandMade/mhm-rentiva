# QA M3 Smoke Matrix

M3 Meta Migration sonrası runtime davranışlarını doğrulayan "PASS Matrisi".

## Admin UI Verification
| Bileşen | Senaryo | Durum | Not |
| :--- | :--- | :--- | :--- |
| **Vehicle List** | Status/Featured kolonları | ✅ PASS | Standart anahtarları doğru yansıtıyor. |
| **Quick Edit** | Status Kaydı | ✅ PASS | `_mhm_vehicle_status` anahtarını güncelliyor. |
| **Meta Box** | Featured/Status Toggle | ✅ PASS | `VehicleMeta::save_meta` üzerinden standart yazma. |
| **Export (CSV)** | Vehicle Status Export | ✅ PASS | `VehicleDataHelper` üzerinden doğru etiketler. |
| **Export (XLS)** | Vehicle Status Export | ✅ PASS | XML/XLS formatında normalizasyon doğru. |

## Frontend Verification
| Bileşen | Senaryo | Durum | Not |
| :--- | :--- | :--- | :--- |
| **Featured List** | Öne Çıkan Araçlar | ✅ PASS | `MetaQueryHelper` ile v2 meta query başarılı. |
| **Calendar** | AJAX Response | ✅ PASS | `_mhm_vehicle_status` üzerinden modal verisi. |
| **REST API** | /vehicles v1 endpoint | ✅ PASS | `RestApiFixer` standart anahtar dönüyor. |
| **Search** | Availability Filter | ✅ PASS | Arama sonuçlarında pasif araçlar gizleniyor. |

## Migration Engine
| Özellik | Senaryo | Durum | Not |
| :--- | :--- | :--- | :--- |
| **Cleanup** | `--cleanup-empty-legacy` | ✅ PASS | Boş legacy kayıtları DB'den siliyor. |
| **Deprecation** | CLI Header & Docs | ✅ PASS | "Removal in v4.10.x" notu eklendi. |

**Sonuç**: M3 Runtime Path %100 Standardize edildi.
