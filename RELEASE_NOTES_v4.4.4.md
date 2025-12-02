# Release v4.4.4 - Major Improvements and WooCommerce Refund Integration

**Release Date:** November 25, 2025

## 🎉 Major Features

### 🚗 Vehicle Management Enhancements
- **Configurable Vehicle Limits:** All hardcoded vehicle limits are now configurable
  - Engine size: 0.0-20.0L (configurable)
  - Seats: Maximum 100 (configurable)
  - Doors: Maximum 20 (configurable)
  - Gallery: Maximum 50 images (configurable)

- **New Fuel Types:** Added support for alternative fuel vehicles
  - LPG (Liquefied Petroleum Gas)
  - CNG (Compressed Natural Gas)
  - Hydrogen

- **New Transmission Types:** Added modern transmission options
  - Semi-Automatic
  - CVT (Continuously Variable Transmission)

### 💳 WooCommerce Integration Improvements
- **Complete Refund Integration:** Automatic booking status updates when refunds are processed in WooCommerce
- **Refund System:** RefundValidator now supports WooCommerce payments (previously only offline)
- **Dynamic Currency:** Currency defaults now dynamically retrieved from WooCommerce or plugin settings (no hardcoded TRY)

### ✅ Booking System Enhancements
- **Thank You Page:** New dedicated thank you page shortcode `[rentiva_thank_you]` for booking confirmations
- **Booking Reference:** Added unique booking reference number with BK- prefix
- **Booking Type:** Added booking type field (Online/Manual)
- **Special Notes:** Added special notes/requests field for customer communication
- **Vehicle Selection:** Vehicle field in booking edit is now editable with license plate display to avoid confusion

### 📱 Form Improvements
- **Form Cleanup:** Removed redundant fields from booking form (Contact Info, Payment Method, Terms checkbox) - now handled by WooCommerce
- **Checkout Integration:** Payment type options (Deposit/Full Payment) moved to WooCommerce checkout page
- **Single Column Layout:** Booking form redesigned as single-column layout for better mobile experience
- **Time Fields:** Pickup time is now mandatory, return time automatically matches pickup time and is non-editable

### 🎨 UI/UX Improvements
- **Modern UI:** Availability check result area redesigned with modern gradients, icons, and animations
- **Better Mobile Experience:** Improved responsive design for mobile devices

### 🔒 System Reliability
- **Duplicate Prevention:** Enhanced atomic overlap checking to prevent duplicate reservations for same vehicle/dates
- **Auto-Cancel:** Fixed automatic cancellation system for unpaid bookings (30-minute deadline)
- **Meta Key Standardization:** Unified date/time meta keys (`_mhm_start_date`, `_mhm_end_date`, `_mhm_start_time`, `_mhm_end_time`) with backward compatibility
- **Availability Validation:** Added final availability check before WooCommerce payment processing to prevent payment for unavailable vehicles

### 📧 Email System
- **Template Auto-Loading:** Fixed email template auto-loading in admin booking edit page

### 🌍 Internationalization
- **Translation Updates:** Updated .pot file with all new translatable strings

## 📊 Statistics
- **67 files changed**
- **17,505 insertions**
- **15,318 deletions**
- **3 new files added**
- **8 files removed** (offline payment gateway cleanup)

## 🔄 Migration Notes
- All existing bookings will continue to work with backward compatibility
- Meta keys have been standardized but old keys are still supported
- No database migration required

## 🐛 Bug Fixes
- Fixed automatic cancellation system for unpaid bookings
- Fixed email template auto-loading in admin
- Fixed duplicate reservation prevention
- Fixed availability validation before payment processing

## 📝 Full Changelog
See `changelog.json` or `changelog-tr.json` for complete list of changes.

## 🔗 Links
- [Repository](https://github.com/MaxHandMade/mhm-rentiva)
- [Documentation](https://github.com/MaxHandMade/mhm-rentiva/wiki)

