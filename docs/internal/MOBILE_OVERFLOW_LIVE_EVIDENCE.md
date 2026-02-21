# Mobile Overflow Live Evidence (Chrome DevTools MCP)

Date: 2026-02-20  
Environment: `localhost/otokira`  
Emulation: `430 x 932`, `isMobile=true`, `hasTouch=true`, `deviceScaleFactor=3`

## Results

| Page | URL | clientWidth | scrollWidth | Horizontal Overflow |
| :--- | :--- | ---: | ---: | :--- |
| Featured Vehicles | `/featured-vehicles/` | 430 | 430 | No |
| Vehicles Grid | `/vehicles-grid/` | 430 | 430 | No |
| Vehicles List | `/arac-listesi/` | 430 | 430 | No |
| Transfer Search Results | `/transfer-search-results/` | 430 | 430 | No |
| Unified Search | `/arac-sekme-arama/` | 430 | 430 | No |

## Wrapper Measurements (Key)

### Featured Vehicles
- `.mhm-rentiva-featured-wrapper`: `left=0`, `right=430`, `width=430`, `paddingLeft=13px`, `paddingRight=13px`, `overflowX=clip`
- `.mhm-featured-grid`: `left=13`, `right=417`, `width=404`
- `.mhm-vehicle-card`: `left=13`, `right=417`, `width=404`

### Vehicles Grid
- Page renders without fatal after fix.
- Sample content confirms normal card rendering (`SEDAN`, price, CTA) and no `ARRAY` label.

### Vehicles List
- `.rv-vehicles-list-container`: `left=0`, `right=430`, `width=430`, `paddingLeft=13px`, `paddingRight=13px`

### Transfer Search Results
- `.mhm-transfer-results-page`: `left=0`, `right=430`, `width=430`, `paddingLeft=13px`, `paddingRight=13px`

### Unified Search
- `.rv-unified-search`: `left=0`, `right=430`, `width=430`, `paddingLeft=24px`, `paddingRight=24px`

## Notes
- `Featured Vehicles` horizontal scrollbar issue is not reproducible in this live MCP run after latest CSS updates.
- `Unified Search` has internal wrapper padding (`24px`) even when outer block wrapper has `0px`.
- `Vehicles Grid` fatal was fixed by hardening `VehiclesGrid` attribute defaults and image size fallback:
  - `src/Admin/Frontend/Shortcodes/VehiclesGrid.php`
  - `templates/partials/vehicle-card-base.php`
