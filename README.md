# MHM Rentiva - WordPress Vehicle Rental Plugin

<div align="right">

**🌐 Language / Dil:** 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![TR](https://img.shields.io/badge/Language-Turkce-red)](README-tr.md) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json) 
[![Degisiklikler TR](https://img.shields.io/badge/Changelog-TR-orange)](changelog-tr.json)

</div>

<p align="center">
  <img src=".wordpress-org/banner-1544x500.png" alt="MHM Rentiva — Car Rental Booking for WordPress" width="800">
</p>

![Version](https://img.shields.io/badge/version-6.1.2-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

**Vehicle rental management for WordPress.** Manage your fleet, availability, bookings and customers from the WordPress admin; WooCommerce handles frontend payments, so your existing gateways keep working. Built with WordPress best practices, fully internationalized, and ready for global markets.

Everything documented here works in full. There are no vehicle, booking or listing caps, no feature timers, and no locked screens.

---

## Editions — Lite vs Pro

**This repository is MHM Rentiva (Lite)** — the free edition published on WordPress.org. It is a complete rental system, not a trial: everything in the **Lite** column below works in full, with no vehicle, booking or listing caps, no feature timers and no locked screens. Nothing in Lite is withheld or limited to promote Pro, and Lite never advertises Pro inside your WordPress admin.

**MHM Rentiva Pro** is a *separate paid add-on plugin* installed alongside Lite. It does not replace Lite — it adds the marketplace, transfer and compliance layer on top of it.

| Capability | Lite (this repo) | Pro add-on |
| --- | :---: | :---: |
| Fleet & vehicle management — unlimited | ✅ | ✅ |
| Availability, booking engine & admin calendar | ✅ | ✅ |
| Customers, booking history, customer CSV | ✅ | ✅ |
| WooCommerce checkout + offline manual bookings | ✅ | ✅ |
| Email notifications with editable templates | ✅ | ✅ |
| Customer account pages — bookings, favourites, payment history | ✅ | ✅ |
| 16 shortcodes · 16 Gutenberg blocks · 17 Elementor widgets | ✅ | ✅ |
| Ratings, testimonials, vehicle comparison, contact form | ✅ | ✅ |
| REST API — availability, customers, dashboard (+ API keys) | ✅ | ✅ |
| **Multi-vendor marketplace** — vendor onboarding, vendor panel, vendor listings | — | ✅ |
| **Vendor payouts, commission & ledger** | — | ✅ |
| **Vendor reports & disputes** | — | ✅ |
| **VIP transfers + location-based routes** | — | ✅ |
| **Customer messaging** | — | ✅ |
| **Advanced reports** | — | ✅ |
| **Dedicated export screen** | — | ✅ |
| **GDPR / data-retention tools** | — | ✅ |

Pro capabilities are gated on a valid licence rather than merely hidden — with Pro installed but unlicensed, those surfaces stay off.

Pro is available at **[wpalemi.com/rentiva](https://wpalemi.com/rentiva/)**. Full per-feature documentation for both editions lives at [the documentation site](https://maxhandmade.github.io/mhm-rentiva-docs/).

> This file is developer-facing and is **not** shipped in the distributed plugin (see `.distignore`). The WordPress.org listing is driven by `readme.txt`, which — per the project's Karar A — carries no comparison table and no purchase call to action.

---

## Table of Contents

- [Editions — Lite vs Pro](#editions--lite-vs-pro)
- [Overview](#overview)
- [Key Features](#key-features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Shortcodes Reference](#shortcodes-reference)
- [REST API Documentation](#rest-api-documentation)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Development](#development)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)
- [Developer](#developer)
- [Support](#support)
- [Star This Project](#star-this-project)

---

## Overview

MHM Rentiva is a comprehensive WordPress plugin designed for vehicle rental businesses. Whether you're running a car rental company, motorcycle rental service, or a multi-location fleet operation, this plugin provides everything you need to manage your operations efficiently.

### What This Plugin Does

- **Vehicle Management**: Complete vehicle inventory management with galleries, categories, pricing, and availability
- **Booking System**: Real-time availability checking, booking management, and automatic cancellation
- **Payment Processing**: Secure payment processing via WooCommerce integration for all frontend bookings
- **Customer Portal**: Customer account system with booking history, favorites, and payment history
- **Reporting**: Dashboard with revenue, customer, and vehicle insights
- **Email System**: Automated email notifications with customizable HTML templates
- **REST API**: REST API for third-party integrations and mobile apps

### Who Is This For?

- **Car Rental Companies**: Manage fleet, bookings, and customer relationships
- **Motorcycle Rentals**: Track availability and process payments
- **Multi-location Rentals**: Support for multiple locations and currencies
- **Global Businesses**: Full internationalization with 60+ languages and 47 currencies

---

## Key Features

### 🚗 Vehicle Management System

**Core Vehicle Features:**
- **Custom Post Type**: Native WordPress post type for vehicles
- **Vehicle Gallery**: Upload images per vehicle using the WordPress Media Library (the maximum is a setting, default 50)
- **Drag & Drop Sorting**: Reorder vehicle images with intuitive drag-and-drop interface
- **Vehicle Categories**: Hierarchical taxonomy system for organizing vehicles
- **Vehicle Metadata**: 
  - Flexible daily pricing
  - Vehicle specifications (make, model, year, fuel type, transmission, etc.)
  - Features and equipment lists
  - Deposit settings (fixed or percentage)
  - Availability status
  - Featured vehicle option
- **Quick Edit**: Bulk edit vehicles from list table
- **Search & Filter**: Advanced filtering by category, status, price range
- **Vehicle Comparison**: Compare multiple vehicles side-by-side

**Vehicle Display Options:**
- Grid view with customizable columns
- List view with detailed information
- Single vehicle detail pages
- Search results with advanced filters
- Availability calendar integration

### 📅 Booking System

**Booking Management:**
- **Real-time Availability**: Automatic conflict detection and prevention
- **Database Locking**: Prevents double-booking with row-level locking
- **Booking Statuses**: 
  - Pending (awaiting payment)
  - Confirmed (payment received)
  - Active (currently rented)
  - Completed (returned)
  - Cancelled
  - Refunded
- **Automatic Cancellation**: Configurable auto-cancel for unpaid bookings (default: 30 minutes)
- **Manual Bookings**: Admin can create bookings directly from WordPress admin
- **Booking Calendar**: Visual calendar view of all bookings
- **Booking History**: Complete booking history for customers and admin

**Booking Features:**
- Date range selection with validation
- Vehicle selection with availability check
- Addon services integration
- Customer information collection
- Payment processing integration
- Receipt upload for offline payments
- Email confirmations
- Booking reminders

### 💳 Payment System

**1. Frontend Payments (via WooCommerce)**
- **WooCommerce Integration**: All frontend bookings are processed securely via WooCommerce.
- **Payment Methods**: Accept any payment method supported by WooCommerce (Credit Card, Bank Transfer, PayPal, Cash on Delivery, etc.).
- **Automatic Status Updates**: Booking statuses are automatically updated based on WooCommerce order status.

**2. Manual Payments (Admin Only)**
- **Manual Payment Recording**: Administrators can manually record payments (Cash/Transfer) for bookings created in the backend.
- **Receipt Management**: Admins can attach receipt proofs to manual bookings.

**Payment Features:**
- Multiple payment methods per booking
- Partial payments support (Deposit system)
- **Pay Remaining Amount**: Customers with deposit bookings can pay the outstanding balance directly from My Account → Booking Detail — any active WooCommerce payment gateway works without code changes
- Refund management via WooCommerce
- Payment status tracking
- Secure transaction handling

### 👥 Customer Management

**Customer Account System:**
- **WordPress Native Integration**: Uses standard WordPress user system
- **Customer Role**: Automatic assignment of WordPress "Customer" role
- **My Account Dashboard**: WooCommerce-like account management interface
- **Account Features**:
  - Dashboard with statistics
  - Booking history with filters
  - Favorite vehicles
  - Payment history
  - Account details editing
  - Password management

**Customer Portal Shortcodes:**
- `[rentiva_user_dashboard]` - Main account dashboard (Login/Register/Account)
- `[rentiva_my_bookings]` - Booking history
- `[rentiva_my_favorites]` - Favorite vehicles
- `[rentiva_payment_history]` - Payment transactions

**Customer Features:**
- Automatic account creation on booking
- Username generation from name (not email)
- Email verification
- Password reset functionality
- Booking notifications
- Email notifications

### 📊 Reporting & Analytics

Analytics live on the **Rentiva Dashboard** (`Rentiva > Dashboard`), a React admin page fed by
`/mhm-rentiva/v1/dashboard/*`.

**Dashboard Data:**
- **Revenue**: Total and monthly revenue, counted from completed and confirmed bookings only
- **Bookings**: Totals, status distribution, and a recent-bookings table
- **Vehicles**: Fleet totals and availability statistics
- **Customers**: Customer totals and detail statistics
- **Deposits & Payments**: Deposit statistics and outstanding/pending payments
- **Notifications**: System notices surfaced on the dashboard

**Features:**
- Fresh data on every load (dashboard metrics are read uncached)
- Responsive charts
- No date-range or row limits of any kind

### 📧 Email Notification System

**Email Templates:**
1. **Booking Emails**:
   - New Booking (Customer)
   - New Booking (Admin)
   - Booking Status Changed
   - Booking Cancelled (Manual/Auto)
   - Booking Reminder
   - Welcome Email

2. **Refund Emails**:
   - Refund Processed Notification

**Email Features:**
- **Modern HTML Templates**: Responsive design with liquid-like placeholders.
- **Customization**: Admin can customize subjects and body content from settings.
- **Logging**: All sent emails are logged via `EmailLog` post type for delivery tracking.

### 🌍 Internationalization & Localization

**Language Support:**
- **57 Locales**: Full support for 57 WordPress locales.
- **Centralized Management**: `LanguageHelper` class for unified language management.
- **Automatic Detection**: Uses WordPress `get_locale()` to detect site language.
- **JavaScript Localization**: Locale conversion for JS-based components (e.g., `en-US`).

**Currency Support:**
- **47 Currencies**: Support for 47 different currencies
- **Centralized Management**: `CurrencyHelper` class for unified currency management
- **Currency Symbols**: Proper symbol display for all currencies
- **Currency Position**: Configurable currency symbol position (left/right with/without space)
- **Supported Gateways**: All WooCommerce Payment Gateways (Frontend), Native Offline (Admin Manual Only)

**Supported Currencies:**
TRY, USD, EUR, GBP, JPY, CAD, AUD, CHF, CNY, INR, BRL, RUB, KRW, MXN, SGD, HKD, NZD, SEK, NOK, DKK, PLN, CZK, HUF, RON, BGN, HRK, RSD, UAH, BYN, KZT, UZS, KGS, TJS, TMT, AZN, GEL, AMD, AED, SAR, QAR, KWD, BHD, OMR, JOD, LBP, EGP, ILS

### 🔒 Security Features

MHM Rentiva follows WordPress Coding Standards (WPCS) and strict security protocols:

- **Sanitization**: All input is sanitized using `sanitize_text_field()`, `absint()`, and the `Sanitizer::text_field_safe()` helper.
- **Escaping**: All output is contextually escaped using `esc_html()`, `esc_attr()`, or `SecurityHelper::safe_output()`.
- **SQLi Prevention**: Database queries strictly use `$wpdb->prepare()` for parametrized execution.
- **Nonce Verification**: All AJAX and form submissions are protected via `wp_verify_nonce()` and `SecurityHelper::verify_ajax_request()`.
- **Capability Checks**: Sensitive operations are gated behind `current_user_can('manage_options')` or specific booking roles.

### ⚡ Performance Features

**Optimization:**
- **On-Demand Loading**: CSS and JS are only loaded on pages where they are needed
- **Minified Assets**: All production CSS and JS files are minified
- **Browser Caching**: Optimized headers for better browser cache utilization
- **Object Caching**: Support for WordPress Object Cache (Redis/Memcached)
- **Database Indexing**: Optimized database schema with proper indexing for fast queries
- **Image Optimization**: Responsive image sizes and lazy loading support
- **Row Locking**: Atomic database locking to prevent double-bookings under high load

**Maintenance & Tools:**
- **Database Cleanup Tool**: Remove old logs, expired transients, and orphaned metadata to keep your database lean.
- **Performance Monitor**: Track execution times and resource usage in real-time.
- **Email Log Retention**: Automatically clean up old email logs after a configurable period.
- **System Info**: One-click system status report for debugging and support.
- **WP-CLI Support**: Management commands for advanced terminal-based operations.
- **Conditional Asset Loading**: CSS/JS only loaded when needed
- **Cache System**: Transient API + Object Cache integration
- **Database Optimization**: Optimized queries, batch loading
- **Lazy Loading**: Images and content loaded on demand
- **Asset Minification**: Minified CSS and JavaScript
- **Background Processing**: Long-running tasks in background
- **Queue System**: Task queue for heavy operations

### 🎁 Addon Services System

**Additional Services Management:**
- **Custom Post Type**: `vehicle_addon` for addon services
- **Addon Features**:
  - Title, description, and price
  - Enable/disable per addon
  - Display order settings
  - Price display options
  - Multiple selection support
- **Booking Integration**: Addons automatically added to booking totals
- **Bulk Actions**: Enable/disable/add/remove multiple addons at once
- **Addon Settings**: Global settings for addon display and behavior

**Default Addons** (can be created automatically):
- GPS Navigation
- Child Seat
- Extra Driver
- Full Insurance
- And more...

### 📤 Data Export

**Customer Export:**
- **Format**: CSV (`customers-YYYY-MM-DD.csv`), streamed straight to the browser
- **Scope**: Export your whole customer list, the current search results, or a selection of rows
- **Location**: `Rentiva > Customers`
- **No limits**: No row cap and no date-range restriction

### 🗄️ Database Maintenance

**Database Cleanup:**
- **Orphaned Postmeta**: Clean orphaned vehicle and booking metadata
- **Orphaned Usermeta**: Clean orphaned user metadata
- **Expired Transients**: Remove expired cached data
- **Old Logs**: Clean logs older than specified days (default: 30)
- **Invalid Meta Keys**: Clean invalid or unused meta keys
- **Autoload Optimization**: Optimize WordPress options autoload
- **Table Optimization**: MySQL table optimization

**Database Migration:**
- **Automatic Migrations**: Version-based database schema updates
- **Performance Indexes**: Automatic index creation for performance
- **Schema Updates**: Automatic table structure updates
- **Version Tracking**: Track database version for migrations

**WP-CLI Commands:**
```bash
wp mhm-rentiva cleanup analyze          # Analyze database
wp mhm-rentiva cleanup orphaned --execute  # Clean orphaned data
wp mhm-rentiva cleanup transients --execute  # Clean expired transients
wp mhm-rentiva cleanup full --execute   # Full cleanup
```

**Admin Interface:**
- Database cleanup page in Settings
- Dry-run mode (preview before cleanup)
- Backup system before cleanup
- Detailed cleanup reports

### 🧩 Gutenberg Blocks Integration

**20 Available Blocks:**
- Availability Calendar, Booking Form, Contact, Featured Vehicles
- My Bookings, My Favorites, Payment History, Search Results
- Testimonials, Unified Search, User Dashboard, Vehicle Comparison
- Vehicle Details, Vehicle Rating Form, Vehicles Grid, Vehicles List

All 16 blocks delegate to their shortcode renderer via `do_shortcode()` (Render Parity architecture), ensuring identical output across Gutenberg, Elementor, and shortcode usage.

**Block Features:**
- Visual block editor integration
- Custom block category "MHM Rentiva"
- Block attributes mapped via Canonical Attribute Mapper (CAM) pipeline
- Preview in editor
- Responsive design

### 🎨 Elementor Widgets Integration

**Complete Widget Suite (17 Widgets):**

**Vehicle Widgets (10):**
- **Unified Search Widget**: Vehicle search with date, location and category filters
- **Vehicle Card Widget**: Single vehicle display with customizable layout
- **Vehicles Grid Widget**: Responsive grid view of vehicles with advanced query options
- **Vehicles List Widget**: List view of vehicles with details
- **Vehicle Details Widget**: Detailed vehicle information display
- **Vehicle Comparison Widget**: Side-by-side vehicle comparison
- **Vehicle Rating Widget**: Vehicle rating and review form
- **Search Results Widget**: Search results display with filtering
- **Availability Calendar Widget**: Interactive availability calendar
- **Featured Vehicles Widget**: Featured vehicles slider or grid

**Booking Widgets (1):**
- **Booking Form Widget**: Complete booking form with vehicle selection, addons, and payment options

**Account Widgets (4):**
- **User Dashboard Widget**: Customer account dashboard
- **My Bookings Widget**: Customer booking history
- **My Favorites Widget**: Favorite vehicles management
- **Payment History Widget**: Payment transaction history

**Other Widgets (2):**
- **Contact Form Widget**: Contact form integration
- **Testimonials Widget**: Customer testimonials display

**Widget Features:**
- **Native Elementor Integration**: Full compatibility with Elementor 3.5+ API
- **Dedicated Category**: All widgets organized under "MHM Rentiva" category
- **Advanced Controls**: Comprehensive styling options including typography, colors, borders, shadows, and spacing
- **Live Preview**: Real-time preview in Elementor editor
- **Drag & Drop Builder**: Easy widget placement and arrangement
- **Responsive Controls**: Full responsive design with Elementor's responsive controls
- **Custom Styling**: Complete control over widget appearance and layout
- **Query Options**: Advanced query settings for vehicle widgets (categories, tags, featured, etc.)
- **Layout Options**: Multiple layout choices (grid, list, card, etc.)
- **Asset Management**: Automatic CSS/JS loading only when widgets are used

### 📊 About Page & System Information

**About Page Features:**
- **General Information**: Plugin overview, version, license type
- **Features List**: Comprehensive feature list (100+ features)
- **System Information**: 
  - WordPress version
  - PHP version and extensions
  - Database information
  - Server information
  - Active plugins and themes
  - Memory limits
  - File permissions
- **Support Tab**: Changelog, documentation links, support information
- **Developer Tab**: System information for developers

### 🧪 Testing System

**Automated Test Suite:**
- **PHPUnit**: 2,527 tests / 9,196 assertions (v6.1.2)
- **CI Matrix**: PHP 8.1/8.2/8.3 x WP 6.7/latest = 6 jobs
- **PHPCS**: Full WordPress Coding Standards compliance
- **Test Admin Page**: Accessible from Rentiva menu
- **Test Reports**: Downloadable test reports
- **Test Runner**: Automated test execution

### ⏰ Cron Job System

**Cron Jobs:**
- **Automatic Cancellation**: Cancel unpaid bookings (default: 30 minutes)
- **Data Retention Cleanup**: Scheduled cleanup of old data
- **Email Log Retention**: Cleanup old email logs
- **Log Retention**: Cleanup old system logs
- **Reconcile**: Data reconciliation tasks

**Cron Monitoring:**
- **Cron Monitor Page**: View all scheduled cron jobs
- **Cron Status**: Check if cron jobs are running
- **Cron History**: View cron execution history
- **Manual Trigger**: Manually trigger cron jobs for testing

### 📝 Logging System

**Log Features:**
- **Custom Post Type**: `mhm_rentiva_log` for system logs
- **Log Categories**: System, Booking, Payment, Email, Error
- **Log Levels**: Info, Warning, Error, Debug
- **Log Retention**: Automatic cleanup of old logs
- **Log Viewing**: View logs in admin with filters
- **Log Export**: Export logs for analysis

### 🗑️ Uninstall System

**Uninstall Features:**
- **Data Cleanup Option**: Option to remove all plugin data on uninstall
- **Selective Cleanup**: Choose what to delete:
  - Vehicles
  - Bookings
  - Customer data
  - Settings
  - Logs
- **Backup Reminder**: Warning before data deletion
- **Uninstall Confirmation**: Confirmation page before deletion

### 📈 Admin Dashboard

**Dashboard Features:**
- **Statistics Cards**: 
  - Total bookings
  - Total revenue
  - Active vehicles
  - Total customers
- **Revenue Charts**: Visual revenue representation
- **Recent Activity**: Latest bookings and payments
- **Quick Actions**: Quick links to common tasks

### ⚛️ Modern React Admin Interface (v4.36.0+)

All major admin pages have been migrated from legacy jQuery/WP_List_Table to React SPAs backed by REST API endpoints. The migration is complete as of v4.49.0.

**Migrated Pages:**

| Page | Version | React Components | REST Endpoints |
| :--- | :---: | :--- | :--- |
| **Dashboard** | v4.36.0 | DashboardPage, StatsCards, RecentBookings, QuickActions | `/mhm-rentiva/v1/dashboard/*` |
| **Customers** | v4.39.0 | CustomerTable, CustomerPanel, SearchBar, FilterBar, Pagination | `/mhm-rentiva/v1/customers`, `/customers/{id}`, `/customers/bulk` |
| **Shortcode Pages** | v4.49.0 | ShortcodePagesPage, ShortcodeTable, StatusBadge | `/mhm-rentiva/v1/shortcode-pages/*` |
| **About** | v5.0.0 | AboutPage, TabNav, GeneralTab, SystemTab, SupportTab, DeveloperTab | `/mhm-rentiva/v1/about` |

**Architecture Highlights:**
- **REST API First**: All data fetched via authenticated WP REST API endpoints (manage_options capability)
- **Shared Component Library**: `shared/admin.css` — stats grid, KPI boxes, status badges, pagination shared across all pages
- **Zero jQuery Dependency**: All pages use React 18 hooks, fetch API, and wp.i18n for translations
- **Mobile Responsive**: All admin pages fully responsive at WP admin breakpoints (782px / 480px)
- **WP Flash Pattern**: Post-action notices delivered via `wp_localize_script` flash key (not URL params, which `common.js` strips before React loads)
- **Build Pipeline**: Webpack + `npm run build` compiles `src-react/` → `build/admin/` per page

---

## Installation

### Step 1: Install the plugin

MHM Rentiva is published on the WordPress.org plugin directory, so the usual route is the
one WordPress offers itself:

1. In your WordPress admin, go to **Plugins > Add New**
2. Search for **MHM Rentiva**, then click **Install Now**
3. Click **Activate**

Directory listing: https://wordpress.org/plugins/mhm-rentiva/

To install a specific build instead — a release from this repository, or a version you built
yourself — upload it by hand:

1. Download the ZIP from this repository's [Releases](https://github.com/MaxHandMade/mhm-rentiva/releases) page
2. Go to **Plugins > Add New > Upload Plugin**, choose the ZIP, and click **Install Now**
3. Click **Activate**

WooCommerce is required and is checked on activation: the plugin will not activate without it.

### Step 2: Initial Setup

1. Go to **WordPress Admin > Rentiva > Settings**
2. Configure basic settings:
   - **Currency**: Select your default currency
   - **Date Format**: Set your preferred date format
   - **Company Information**: Add your company details
   - **Email Settings**: Configure email sender information

### Step 3: Create Required Pages

The plugin will automatically create pages for shortcodes, or you can create them manually:

**Required Pages:**
- My Account page (Managed by WooCommerce)
- Booking Form page (use `[rentiva_booking_form]` shortcode)
- Vehicles List/Grid page (use `[rentiva_vehicles_grid]` or `[rentiva_vehicles_list]`)

**Optional Pages:**
- Search page (use `[rentiva_unified_search]` shortcode)
- Search results page (use `[rentiva_search_results]` shortcode)
- Contact page (use `[rentiva_contact]` shortcode)
- Favorites page (use `[rentiva_my_favorites]` shortcode)
- Vehicle comparison page (use `[rentiva_vehicle_comparison]` shortcode)

> Login and registration are handled by the WooCommerce My Account page; `[rentiva_user_dashboard]`
> renders the login/register/account views on a page of your own if you prefer.

The `Rentiva > Shortcode Pages` tool creates any of these for you in one click.

### Step 4: Configure Payment Gateways

1. Go to **Rentiva > Settings > Payment**
2. Configure your payment gateways:
   - **Payment**: Configure currency and position
   - **Offline**: Configure receipt upload settings

### Step 5: Add Vehicles

1. Go to **Vehicles > Add New**
2. Fill in vehicle information:
   - Title, description, images
   - Pricing (daily, weekly, monthly)
   - Vehicle specifications
   - Features and equipment
   - Deposit settings
3. Publish the vehicle

### Step 6: Test Booking Flow

1. Visit your booking form page
2. Select dates and vehicle
3. Fill in customer information
4. Complete a test booking
5. Verify email notifications

---

## Configuration

### General Settings

**Location**: `Rentiva > Settings > General`

- **Currency**: Select default currency (47 currencies supported)
- **Currency Position**: Left/Right with or without space
- **Date Format**: Customize date display format
- **Default Rental Days**: Minimum rental period
- **Company Information**: Name, website, email, support email
- **Site URLs**: Booking, login, register, account URLs

### Booking Settings

**Location**: `Rentiva > Settings > Booking`

- **Cancellation Deadline**: Hours before booking start (default: 24)
- **Payment Deadline**: Minutes to complete payment (default: 30)
- **Auto Cancel Enabled**: Automatically cancel unpaid bookings
- **Send Confirmation Emails**: Enable/disable booking emails
- **Send Reminder Emails**: Enable booking reminders
- **Admin Notifications**: Notify admin of new bookings
### Payment Settings

**Offline Payment Settings:**

**Setup**:
1. Go to `Rentiva > Settings > Payment > Offline`
2. Enable offline payments
3. Configure receipt upload settings
4. Set approval deadline

**Features**:
- Receipt upload system
- Admin approval workflow
- Automatic cancellation if not approved
- Email notifications

---

## Shortcodes Reference

The plugin registers **16 shortcodes** across booking, account and supporting surfaces. Every shortcode has a paired Gutenberg block and Elementor widget that delegate to the same canonical renderer (Render Parity — identical output across all three).

### Booking & Vehicle Display (9)
- `[rentiva_booking_form]` — Main booking form (accepts `vehicle_id` parameter).
- `[rentiva_vehicles_grid]` — Vehicle catalog in a responsive grid layout.
- `[rentiva_vehicles_list]` — Vehicle catalog in a list layout with details.
- `[rentiva_featured_vehicles]` — Featured vehicles (Swiper slider / grid).
- `[rentiva_vehicle_details]` — Single-vehicle detail page with gallery and booking CTA.
- `[rentiva_search_results]` — Active search results page renderer.
- `[rentiva_unified_search]` — Search box with date, location and category filters.
- `[rentiva_availability_calendar]` — Visual availability calendar.
- `[rentiva_vehicle_comparison]` — Side-by-side vehicle comparison.

### Customer Account (4)
- `[rentiva_user_dashboard]` — Customer main dashboard.
- `[rentiva_my_bookings]` — Customer's current and past bookings (WC My Account sub-route).
- `[rentiva_my_favorites]` — Customer's favorite vehicles (WC My Account sub-route).
- `[rentiva_payment_history]` — Payment history and receipt details.

### Supporting Surfaces (3)
- `[rentiva_contact]` — Site contact form.
- `[rentiva_testimonials]` — Customer testimonials slider (reads from `vehicle_booking` ratings).
- `[rentiva_vehicle_rating_form]` — Post-booking vehicle review/rating form.

---

## REST API Documentation

### Base URL
```
/wp-json/mhm-rentiva/v1
```

### Authentication (Auth)
The REST API is secured via `AuthHelper` with multiple layers:
- **X-WP-Nonce**: Standard WordPress nonce for logged-in sessions.
- **Secure Tokens**: Time-limited customer tokens generated via `SecureToken`.
- **API Keys**: Manageable via `Rentiva > Settings > Integration` for third-party apps.

### Rate Limiting
Protected against Brute Force via the `RateLimiter` system:
- **Default Limit**: 60 requests per minute.
- **Sensitive Endpoints**: Stricter limits for booking creation and payment processing.

### Key Endpoints
- `GET /vehicles` — List and filter vehicles.
- `GET /availability` — Check vehicle availability for specific dates.
- `POST /bookings` — Create a new booking.
- `GET /locations` — List active rental locations.
- `GET /orders` — View customer order details.

---

## Project Structure

```text
mhm-rentiva/
├── assets/                 # Frontend & admin assets (CSS, JS, images)
├── build/                  # Webpack-built React admin bundles + CSS
├── src-react/              # React source — admin SPA components (Webpack input)
├── bin/                    # Build tooling (build-release.py for WP.org ZIP)
├── languages/              # i18n: .pot, .po, .mo, .l10n.php, JED .json
├── src/                    # PSR-4 PHP source (MHMRentiva\*)
│   ├── Admin/              # Admin module controllers & services
│   ├── Api/                # Custom REST API endpoints
│   ├── Blocks/             # Gutenberg block definitions (16 blocks)
│   ├── CLI/                # WP-CLI commands
│   ├── Core/               # Financial engine, attribute pipeline, base services
│   ├── Helpers/            # Sanitization, security, utility classes
│   ├── Integrations/       # External bridges (WooCommerce, etc.)
│   └── Plugin.php          # Main initialization class
├── templates/              # Frontend partials & email templates
├── tests/                  # PHPUnit suite (2,491 tests, 9,131 assertions)
├── vendor/                 # Composer dependencies (autoloader)
├── changelog.json          # Structured version history (English)
├── changelog-tr.json       # Structured version history (Turkish)
├── mhm-rentiva.php         # Main plugin entry point
├── uninstall.php           # Cleanup on plugin deletion
├── readme.txt              # WordPress.org plugin directory metadata
├── README.md               # Developer documentation (this file)
├── README-tr.md            # Developer documentation (Turkish)
└── LICENSE                 # GPLv2-or-later license
```

---

## Requirements

### WordPress & PHP
- **WordPress**: 6.7 minimum (Tested up to 7.1)
- **PHP**: 8.1 minimum (8.2+ recommended)
- **Memory Limit**: 128MB minimum (256MB recommended)

### Required Extensions
- `json` — For API and settings processing.
- `curl` — For license and external integrations.
- `mbstring` — For multi-language support.
- `openssl` — For secure data encryption.

---

## Development

### Development Setup

```bash
# Clone repository
git clone [repository-url] mhm-rentiva
cd mhm-rentiva

# Enable development mode in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SCRIPT_DEBUG', true);
```

### Code Standards

- **WordPress Coding Standards (WPCS)**: Full compliance
- **PSR-4 Autoloading**: Namespace-based autoloading
- **Type Hinting**: PHP 8.0+ type declarations
- **Strict Types**: `declare(strict_types=1)` in all files
- **Namespace**: `MHMRentiva\Admin\*`

### Architecture

- **Modular Design**: Each feature in its own directory
- **Separation of Concerns**: Core, Admin, Frontend separation
- **Singleton Pattern**: Used where appropriate
- **Factory Pattern**: For creating instances
- **Observer Pattern**: WordPress hooks system

### Adding New Features

1. Create feature directory in appropriate location
2. Create main class file
3. Implement `register()` static method
4. Register in `Plugin.php`
5. Add hooks in `register()` method
6. Follow WordPress coding standards

### Testing

**Manual Testing**:
- Test in WordPress admin
- Test frontend functionality
- Test payment flows
- Test email notifications

**Automated Testing**:
- PHPUnit suite: 2,491 tests / 9,131 assertions (unit + WP_UnitTestCase integration)

---

## Contributing

We welcome contributions! Please follow these guidelines:

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/NewFeature`
3. **Follow coding standards**: WordPress Coding Standards
4. **Write clear commit messages**: Use conventional commits
5. **Test thoroughly**: Test all functionality
6. **Submit pull request**: Include description of changes

---

## Changelog

Full release history is maintained in [`changelog.json`](changelog.json) (English) and [`changelog-tr.json`](changelog-tr.json) (Türkçe), and rendered as blog posts at [mhm-rentiva-docs/blog](https://maxhandmade.github.io/mhm-rentiva-docs/blog).

For GitHub releases with assets, see [Releases](https://github.com/MaxHandMade/mhm-rentiva/releases).

---

## License

This project is licensed under the **GPL-2.0+** license. See the [LICENSE](LICENSE) file for details.

---

## Developer

**MaxHandMade**
- Website: [wpalemi.com](https://wpalemi.com)
- Support: support@wpalemi.com

---

## Support

For questions, issues, or feature requests:
- **Email**: support@wpalemi.com
- **Website**: https://wpalemi.com

---

## Star This Project

If you find this plugin useful, please consider giving it a star on GitHub!

---

**Made with ❤️ for the WordPress community**
