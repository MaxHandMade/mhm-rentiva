# MHM Rentiva - Modül Mimarisi ve Teknik Dökümantasyon

<div align="right">

**🌐 Language / Dil:** 
[![EN](https://img.shields.io/badge/Language-English-blue)](MODULES.md) 
[![TR](https://img.shields.io/badge/Dil-Türkçe-red)](MODULES-tr.md)

</div>

![Version](https://img.shields.io/badge/version-4.6.2-blue.svg)
![Modules](https://img.shields.io/badge/modules-22-green.svg)
![Security](https://img.shields.io/badge/security-WPCS%20Compliant-brightgreen.svg)
![Last Audit](https://img.shields.io/badge/last%20audit-2026--01--21-blue.svg)

> **Technical Constitution** - This document serves as the architectural reference for MHM Rentiva plugin, detailing all 22 modules, their responsibilities, relationships, and security status.

---

## 📋 Table of Contents

- [Architecture Overview](#architecture-overview)
- [Module Categories](#module-categories)
- [Business Logic Modules](#-business-logic-modules)
- [User Operations Modules](#-user-operations-modules)
- [System Infrastructure Modules](#-system-infrastructure-modules)
- [Security & API Modules](#-security--api-modules)
- [Module Relationships Graph](#module-relationships-graph)
- [Security Audit Summary](#security-audit-summary)

---

## 🏗️ Architecture Overview

MHM Rentiva follows a **modular monolith** architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────────┐
│                        WORDPRESS CORE                           │
├─────────────────────────────────────────────────────────────────┤
│                     MHM RENTIVA PLUGIN                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                   BUSINESS LOGIC                         │   │
│  │   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐       │   │
│  │   │ Booking │ │ Vehicle │ │Transfer │ │ Payment │       │   │
│  │   └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘       │   │
│  │        │           │           │           │             │   │
│  │        └───────────┴─────┬─────┴───────────┘             │   │
│  │                          │                                │   │
│  │  ┌───────────────────────┴───────────────────────┐       │   │
│  │  │              CORE INFRASTRUCTURE              │       │   │
│  │  │  Settings │ Emails │ Reports │ Utilities     │       │   │
│  │  └───────────────────────────────────────────────┘       │   │
│  │                          │                                │   │
│  │  ┌───────────────────────┴───────────────────────┐       │   │
│  │  │              USER OPERATIONS                  │       │   │
│  │  │  Customers │ Messages │ Frontend │ Account   │       │   │
│  │  └───────────────────────────────────────────────┘       │   │
│  │                          │                                │   │
│  │  ┌───────────────────────┴───────────────────────┐       │   │
│  │  │              SECURITY & API                   │       │   │
│  │  │   Auth │ Privacy │ Licensing │ REST │ Addons │       │   │
│  │  └───────────────────────────────────────────────┘       │   │
│  └─────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                    WOOCOMMERCE (Optional)                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📂 Module Categories

| Category | Modules | Description |
|----------|---------|-------------|
| **Business Logic** | 4 | Core rental operations (Booking, Vehicle, Transfer, Payment) |
| **User Operations** | 4 | Customer-facing features (Customers, Messages, Frontend, Account) |
| **System Infrastructure** | 8 | Backend services (Settings, Emails, Reports, Utilities, Core, Setup, PostTypes, About) |
| **Security & API** | 6 | Security and integration (Auth, Privacy, Licensing, REST, Addons, Testing) |

---

## 💼 Business Logic Modules

### 📦 Booking
* **Directory:** `src/Admin/Booking/`
* **Type:** Core Business
* **Description:** Manages the complete booking lifecycle from creation to completion, including availability checks, pricing calculations, and status management.
* **Critical Files:**
  - `Core/Handler.php` - Main booking form handler
  - `Core/BookingManager.php` - Booking CRUD operations
  - `Actions/DepositManagementAjax.php` - Deposit payment handling
  - `Meta/BookingMeta.php` - Booking metadata management
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 21 locations
* **Relationships:** Vehicle, Payment, Customers, WooCommerce, Emails

---

### 📦 Vehicle
* **Directory:** `src/Admin/Vehicle/`
* **Type:** Core Business
* **Description:** Complete vehicle inventory management including galleries, specifications, availability rules, and pricing tiers.
* **Critical Files:**
  - `Meta/VehicleMeta.php` - Vehicle metadata handler
  - `Meta/VehicleGallery.php` - Image gallery management
  - `Settings/VehicleSettings.php` - Vehicle configuration
  - `ListTable/VehicleColumns.php` - Admin list customization
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 12 locations
* **Relationships:** Booking, Frontend, PostTypes

---

### 📦 Transfer (VIP)
* **Directory:** `src/Admin/Transfer/`
* **Type:** Core Business
* **Description:** Point-to-point chauffeur service with location/route management, distance-based pricing, and vehicle selection.
* **Critical Files:**
  - `Frontend/TransferShortcodes.php` - Search form and results
  - `Integration/TransferCartIntegration.php` - WooCommerce cart bridge
  - `TransferSearchEngine.php` - Route and vehicle matching
  - `TransferAdmin.php` - Admin panel management
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 7 locations
* **Relationships:** WooCommerce, Vehicle, Payment, Frontend

---

### 📦 Payment
* **Directory:** `src/Admin/Payment/`
* **Type:** Core Business
* **Description:** Payment processing with WooCommerce integration, deposit system, refunds, and multiple payment gateway support.
* **Critical Files:**
  - `WooCommerce/WooCommerceBridge.php` - WooCommerce integration
  - `Refunds/Service.php` - Refund processing
  - `DepositCalculator.php` - Deposit amount calculation
  - `PaymentGatewayManager.php` - Gateway management
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 2 locations
* **Relationships:** Booking, Transfer, WooCommerce, Emails

---

## 👥 User Operations Modules

### 📦 Customers
* **Directory:** `src/Admin/Customers/`
* **Type:** User Operations
* **Description:** Customer management system with profile handling, booking history, and administrative tools.
* **Critical Files:**
  - `CustomersPage.php` - Admin customer list
  - `AddCustomerPage.php` - Customer creation form
  - `CustomerProfile.php` - Profile management
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 3 locations
* **Relationships:** Booking, Messages, Frontend

---

### 📦 Messages
* **Directory:** `src/Admin/Messages/`
* **Type:** User Operations
* **Description:** Internal messaging system between customers and administrators with thread management and notifications.
* **Critical Files:**
  - `Core/Messages.php` - Core messaging logic
  - `Core/MessageUrlHelper.php` - URL generation
  - `Monitoring/MessageLogger.php` - Message audit logs
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 2 locations
* **Relationships:** Customers, Emails, Frontend

---

### 📦 Frontend
* **Directory:** `src/Admin/Frontend/`
* **Type:** User Operations
* **Description:** All frontend-facing components including shortcodes, account pages, blocks, and customer portal.
* **Critical Files:**
  - `Shortcodes/BookingForm.php` - Booking form shortcode
  - `Shortcodes/VehiclesList.php` - Vehicle listing with ratings
  - `Shortcodes/VehicleRatingForm.php` - Customer rating system
  - `Account/AccountController.php` - My Account page controller
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 18 locations
* **Relationships:** Booking, Vehicle, Customers, Messages, WooCommerce

---

### 📦 Account
* **Directory:** `src/Admin/Frontend/Account/`
* **Type:** User Operations
* **Description:** Customer self-service portal with booking management, favorites, profile editing, and document uploads.
* **Critical Files:**
  - `AccountController.php` - Main account coordinator
  - `Tabs/BookingsTab.php` - Booking history view
  - `Tabs/FavoritesTab.php` - Favorite vehicles
  - `Tabs/ProfileTab.php` - Profile editing
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Relationships:** Frontend, Customers, Booking, Messages

---

## ⚙️ System Infrastructure Modules

### 📦 Settings
* **Directory:** `src/Admin/Settings/`
* **Type:** System Infrastructure
* **Description:** Centralized configuration management with grouped settings, sanitization, and validation.
* **Critical Files:**
  - `Core/SettingsCore.php` - Core settings API
  - `SettingsHandler.php` - Settings save/reset handler
  - `Groups/*.php` - Grouped settings definitions
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 4 locations
* **Relationships:** All modules (central configuration)

---

### 📦 Emails
* **Directory:** `src/Admin/Emails/`
* **Type:** System Infrastructure
* **Description:** Automated email notification system with customizable HTML templates and triggered sending.
* **Critical Files:**
  - `Core/EmailTemplates.php` - Template management
  - `Sender/EmailSender.php` - Email dispatch
  - `Templates/*.php` - Individual email templates
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 1 location
* **Relationships:** Booking, Customers, Messages, Settings

---

### 📦 Reports
* **Directory:** `src/Admin/Reports/`
* **Type:** System Infrastructure
* **Description:** Comprehensive analytics dashboard with revenue, customer, and vehicle insights.
* **Critical Files:**
  - `Reports.php` - Main reports coordinator
  - `BusinessLogic/RevenueReport.php` - Revenue analytics
  - `BusinessLogic/BookingReport.php` - Booking statistics
  - `Charts.php` - Chart.js integration
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 0 (no form submissions)
* **Relationships:** Booking, Vehicle, Payment, Settings

---

### 📦 Utilities
* **Directory:** `src/Admin/Utilities/`
* **Type:** System Infrastructure
* **Description:** System maintenance tools including export, database cleanup, cron monitoring, and uninstall handlers.
* **Critical Files:**
  - `Export/Export.php` - Data export functionality
  - `Actions/Actions.php` - Utility actions (log purge, etc.)
  - `Database/DatabaseCleanupPage.php` - DB maintenance
  - `Cron/CronMonitorPage.php` - Scheduled task monitoring
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 4 locations
* **Relationships:** All modules (maintenance operations)

---

### 📦 Core
* **Directory:** `src/Admin/Core/`
* **Type:** System Infrastructure
* **Description:** Shared infrastructure components including abstract classes, traits, helpers, and base utilities.
* **Critical Files:**
  - `Utilities/AbstractListTable.php` - Base list table class
  - `Traits/AdminHelperTrait.php` - Common admin helpers
  - `MetaBoxes/AbstractMetaBox.php` - Base meta box class
  - `Helpers/Sanitizer.php` - Central sanitization utilities
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 3 locations
* **Relationships:** All modules (base infrastructure)

---

### 📦 Setup
* **Directory:** `src/Admin/Setup/`
* **Type:** System Infrastructure
* **Description:** Plugin installation wizard and initial configuration guidance.
* **Critical Files:**
  - `SetupWizard.php` - Step-by-step setup
* **Security Status:** ✅ Verified (v4.6.2 - No nonce operations)
* **Nonce Patches:** 0
* **Relationships:** Settings, PostTypes

---

### 📦 PostTypes
* **Directory:** `src/Admin/PostTypes/`
* **Type:** System Infrastructure
* **Description:** Custom post type and taxonomy registration for vehicles and bookings.
* **Critical Files:**
  - `VehiclePostType.php` - Vehicle CPT
  - `BookingPostType.php` - Booking CPT
  - `Taxonomies.php` - Custom taxonomies
* **Security Status:** ✅ Verified (v4.6.2 - No nonce operations)
* **Nonce Patches:** 0
* **Relationships:** Vehicle, Booking

---

### 📦 About
* **Directory:** `src/Admin/About/`
* **Type:** System Infrastructure
* **Description:** Plugin information page with system diagnostics, feature overview, and support resources.
* **Critical Files:**
  - `About.php` - Main about page
  - `SystemInfo.php` - System diagnostics
  - `Tabs/*.php` - Information tabs
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 1 location
* **Relationships:** Settings, Licensing

---

## 🔒 Security & API Modules

### 📦 Auth
* **Directory:** `src/Admin/Auth/`
* **Type:** Security & API
* **Description:** Two-factor authentication system for enhanced account security.
* **Critical Files:**
  - `TwoFactorManager.php` - 2FA implementation (TOTP)
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 3 locations
* **Relationships:** Customers, Frontend, Settings

---

### 📦 Privacy
* **Directory:** `src/Admin/Privacy/`
* **Type:** Security & API
* **Description:** GDPR compliance features including data export, deletion, and consent management.
* **Critical Files:**
  - `GDPRManager.php` - GDPR operations handler
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 3 locations
* **Relationships:** Customers, Settings, Booking

---

### 📦 Licensing
* **Directory:** `src/Admin/Licensing/`
* **Type:** Security & API
* **Description:** License key management for Pro features activation and validation.
* **Critical Files:**
  - `LicenseManager.php` - License API integration
  - `LicenseAdmin.php` - Admin license page
  - `Mode.php` - Pro/Lite mode detection
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 3 locations
* **Relationships:** Settings, About, All Pro features

---

### 📦 REST
* **Directory:** `src/Admin/REST/`
* **Type:** Security & API
* **Description:** Complete REST API for third-party integrations and mobile applications.
* **Critical Files:**
  - `VehicleEndpoint.php` - Vehicle API
  - `BookingEndpoint.php` - Booking API
  - `AuthEndpoint.php` - Authentication API
* **Security Status:** ✅ Verified (v4.6.2 - Uses WordPress REST nonce system)
* **Nonce Patches:** 0 (WordPress REST handles authentication)
* **Relationships:** All public-facing modules

---

### 📦 Addons
* **Directory:** `src/Admin/Addons/`
* **Type:** Security & API
* **Description:** Booking addons/extras management with pricing and integration.
* **Critical Files:**
  - `AddonManager.php` - Addon CRUD and pricing
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 2 locations
* **Relationships:** Booking, Payment, Vehicle

---

### 📦 Testing
* **Directory:** `src/Admin/Testing/`
* **Type:** Security & API
* **Description:** Development and QA tools for shortcode testing and security validation.
* **Critical Files:**
  - `ShortcodeTestHandler.php` - Shortcode testing
  - `SecurityTest.php` - Security audit tools
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 1 location
* **Relationships:** Frontend, All shortcode modules

---

### 📦 Actions
* **Directory:** `src/Admin/Actions/`
* **Type:** Security & API
* **Description:** Global admin actions including refunds, log management, and page creation.
* **Critical Files:**
  - `Actions.php` - Global action handlers
* **Security Status:** ✅ Verified (v4.6.2 - WPCS & Nonce Hardened)
* **Nonce Patches:** 1 location
* **Relationships:** Booking, Payment, Settings, Utilities

---

## 🔗 Module Relationships Graph

```
                              ┌──────────────┐
                              │   SETTINGS   │
                              └──────┬───────┘
                                     │ (configures all)
         ┌───────────────────────────┼───────────────────────────┐
         │                           │                           │
    ┌────┴────┐                ┌─────┴─────┐              ┌──────┴─────┐
    │ VEHICLE │◄───────────────│  BOOKING  │──────────────►│  PAYMENT  │
    └────┬────┘                └─────┬─────┘              └──────┬─────┘
         │                           │                           │
         │    ┌──────────────────────┼──────────────────────┐   │
         │    │                      │                      │   │
         ▼    ▼                      ▼                      ▼   ▼
    ┌─────────────┐            ┌───────────┐         ┌───────────────┐
    │  FRONTEND   │◄───────────│ CUSTOMERS │────────►│  WOOCOMMERCE  │
    │ (Shortcodes)│            └─────┬─────┘         └───────────────┘
    └──────┬──────┘                  │
           │                         │
           ▼                         ▼
    ┌─────────────┐            ┌───────────┐
    │  TRANSFER   │            │ MESSAGES  │
    │   (VIP)     │            └─────┬─────┘
    └──────┬──────┘                  │
           │                         ▼
           └────────────────────►┌───────────┐
                                 │  EMAILS   │
                                 └───────────┘
```

---

## 🛡️ Security Audit Summary

### v4.6.2 Security Patches (2026-01-21)

| Metric | Value |
|--------|-------|
| **Total Modules Audited** | 22 |
| **Total Files Modified** | 59+ |
| **Nonce Hardening Patches** | 91 |
| **WPCS Compliance** | 100% |
| **Critical Vulnerabilities** | 0 |

### Security Standards Applied

1. **Nonce Verification**: All `wp_verify_nonce()` calls wrapped with `sanitize_text_field(wp_unslash())`
2. **Input Sanitization**: Consistent use of `Sanitizer::text_field_safe()` helper
3. **SQL Injection Prevention**: All queries use prepared statements
4. **XSS Prevention**: All output properly escaped with `esc_html()`, `esc_attr()`, `wp_kses_post()`
5. **CSRF Protection**: All forms and AJAX handlers protected with nonces

### Module Security Matrix

| Module | Nonce | Sanitize | Escape | Prepared SQL |
|--------|:-----:|:--------:|:------:|:------------:|
| Booking | ✅ | ✅ | ✅ | ✅ |
| Vehicle | ✅ | ✅ | ✅ | ✅ |
| Transfer | ✅ | ✅ | ✅ | ✅ |
| Payment | ✅ | ✅ | ✅ | ✅ |
| Customers | ✅ | ✅ | ✅ | ✅ |
| Messages | ✅ | ✅ | ✅ | ✅ |
| Frontend | ✅ | ✅ | ✅ | ✅ |
| Settings | ✅ | ✅ | ✅ | ✅ |
| Emails | ✅ | ✅ | ✅ | ✅ |
| Reports | ✅ | ✅ | ✅ | ✅ |
| Utilities | ✅ | ✅ | ✅ | ✅ |
| Core | ✅ | ✅ | ✅ | ✅ |
| Auth | ✅ | ✅ | ✅ | ✅ |
| Privacy | ✅ | ✅ | ✅ | ✅ |
| Licensing | ✅ | ✅ | ✅ | ✅ |
| REST | ✅ | ✅ | ✅ | ✅ |
| Addons | ✅ | ✅ | ✅ | ✅ |
| Testing | ✅ | ✅ | ✅ | ✅ |
| Actions | ✅ | ✅ | ✅ | ✅ |
| About | ✅ | ✅ | ✅ | ✅ |
| Setup | N/A | ✅ | ✅ | N/A |
| PostTypes | N/A | ✅ | ✅ | N/A |

---

## 📚 Additional Resources

- [README.md](README.md) - Main documentation
- [README-tr.md](README-tr.md) - Turkish documentation
- [changelog.json](changelog.json) - Version history (EN)
- [changelog-tr.json](changelog-tr.json) - Version history (TR)
- [readme.txt](readme.txt) - WordPress.org metadata

---

## 📝 Document Metadata

| Property | Value |
|----------|-------|
| **Document Version** | 1.0.0 |
| **Plugin Version** | 4.6.2 |
| **Last Updated** | 2026-01-21 |
| **Author** | MHM Development Team |
| **License** | GPL-2.0+ |

---

*This document is automatically generated and maintained as part of the MHM Rentiva development process.*
