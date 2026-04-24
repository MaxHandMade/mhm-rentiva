# MHM Rentiva - WordPress Vehicle Rental Plugin

<div align="right">

**🌐 Language / Dil:** 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![TR](https://img.shields.io/badge/Language-Turkce-red)](README-tr.md) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json) 
[![Degisiklikler TR](https://img.shields.io/badge/Changelog-TR-orange)](changelog-tr.json)

</div>

![Version](https://img.shields.io/badge/version-4.30.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

**Professional vehicle rental management system for WordPress.** A complete, enterprise-grade solution for managing vehicle rentals, bookings, payments, customers, and comprehensive reporting. Built with WordPress best practices, fully internationalized, and ready for global markets.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage Guide](#usage-guide)
- [Shortcodes Reference](#shortcodes-reference)
- [REST API Documentation](#rest-api-documentation)
- [Payment Gateways](#payment-gateways)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Development](#development)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

---

## 🎯 Overview

MHM Rentiva is a comprehensive WordPress plugin designed for vehicle rental businesses. Whether you're running a car rental company, motorcycle rental service, or a multi-location fleet operation, this plugin provides everything you need to manage your operations efficiently.

### What This Plugin Does

- **Vehicle Management**: Complete vehicle inventory management with galleries, categories, pricing, and availability
- **Booking System**: Real-time availability checking, booking management, and automatic cancellation
- **Payment Processing**: Secure payment processing via WooCommerce integration for all frontend bookings
- **Customer Portal**: Full-featured customer account system with booking history, favorites, and messaging
- **Analytics & Reporting**: Comprehensive analytics dashboard with revenue, customer, and vehicle insights
- **Email System**: Automated email notifications with customizable HTML templates
- **Messaging System**: Built-in customer support messaging with thread management
- **VIP Transfer Module**: Point-to-point booking system with distance-based pricing and vehicle selection
- **REST API**: Complete REST API for third-party integrations and mobile apps

### Who Is This For?

- **Car Rental Companies**: Manage fleet, bookings, and customer relationships
- **Motorcycle Rentals**: Track availability and process payments
- **Multi-location Rentals**: Support for multiple locations and currencies
- **Global Businesses**: Full internationalization with 60+ languages and 47 currencies

---

## ✨ Key Features

### 🚗 Vehicle Management System

**Core Vehicle Features:**
- **Custom Post Type**: Native WordPress post type for vehicles
- **Vehicle Gallery**: Upload up to 10 images per vehicle using WordPress Media Library
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
- **VIP Transfer Module Integration**: Seamless management of chauffeur services

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
  - Message center

**Customer Portal Shortcodes:**
- `[rentiva_user_dashboard]` - Main account dashboard (Login/Register/Account)
- `[rentiva_my_bookings]` - Booking history
- `[rentiva_my_favorites]` - Favorite vehicles
- `[rentiva_payment_history]` - Payment transactions
- `[rentiva_account_details]` - Profile editing

**Customer Features:**
- Automatic account creation on booking
- Username generation from name (not email)
- Email verification
- Password reset functionality
- Booking notifications
- Email notifications
- Message notifications

### 📊 Reporting & Analytics

> **Lite:** Basic reports with 30-day date range and 500-row limit. **Pro:** Unlimited date range, rows, and advanced report types.

**Analytics Dashboard:**
- **Revenue Analytics**: 
  - Total revenue
  - Revenue by period (daily, weekly, monthly, yearly)
  - Revenue by vehicle
  - Payment method breakdown
- **Booking Analytics**:
  - Total bookings
  - Booking status distribution
  - Booking trends
  - Peak booking periods
- **Vehicle Analytics**:
  - Most rented vehicles
  - Vehicle utilization rates
  - Revenue per vehicle
  - Availability statistics
- **Customer Analytics**:
  - Total customers
  - Customer segmentation
  - Customer lifecycle analysis
  - Repeat customer rate
  - Customer acquisition trends

### 🚀 Lite vs Pro Edition Comparison

| Feature | Lite (Free) | Pro (Premium) |
| :--- | :--- | :--- |
| **Maximum Vehicles** | 5 Vehicles | **Unlimited** |
| **Maximum Bookings** | 50 Bookings | **Unlimited** |
| **Maximum Customers** | 10 Customers | **Unlimited** |
| **Additional Services (Addons)** | 4 Services | **Unlimited** |
| **VIP Transfer Routes** | 3 Routes | **Unlimited** |
| **Gallery Images** | 5 Images / Vehicle | **Unlimited** |
| **Report Date Range** | Last 30 Days | **Unlimited** |
| **Report Rows** | 500 Rows | **Unlimited** |
| **Messaging System** | ❌ Not available | ✅ Available |
| **Export Formats** | CSV Only | CSV, JSON |
| **Payment Gateways** | WooCommerce | WooCommerce |
| **REST API Access** | Limited | Full API |
| **Advanced Reports** | ❌ Limited | ✅ Full Access |
| **Vendor Marketplace** | ❌ Not available | ✅ Available |

> **Note:** Lite version is designed for small businesses and testing. For unlimited access, please check the Pro version.

**Report Features:**
- Real-time data updates
- Custom date range selection
- Export to CSV (Lite) and CSV/JSON (Pro)
- Responive charts
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

3. **Vendor Notifications (Pro)**:
   - Application Submitted (Customer/Admin)
   - Vendor Approved/Rejected
   - Vehicle Approved/Rejected
   - Payout Approved/Rejected
   - IBAN Change Approved/Rejected

**Email Features:**
- **Modern HTML Templates**: Responsive design with liquid-like placeholders.
- **Customization**: Admin can customize subjects and body content from settings.
- **Logging**: All sent emails are logged via `EmailLog` post type for delivery tracking.
ging**: All emails logged for debugging

### 💬 Messaging System (Pro)

**Message Features:**
- **Thread-based Communication**: Conversations organized in threads
- **Message Categories**: General, Booking, Payment, Technical Support, Complaint, Suggestion
- **Message Statuses**: Pending, Answered, Closed, Urgent
- **Priority Levels**: Normal, High, Urgent
- **Admin Interface**: Full message management in WordPress admin
- **Customer Interface**: Frontend message center for customers
- **Email Notifications**: Automatic email notifications for new messages
- **REST API**: Complete REST API for message operations

**Message Management:**
- View all messages in admin
- Reply to customer messages
- Change message status
- Assign priorities
- Bulk actions (delete, mark as read)
- Search and filter messages
- Message statistics

### 🚐 VIP Transfer Module (Chauffeur Service)

**Core Transfer Features:**
- **Point-to-Point Booking**: Select pickup and drop-off locations from predefined zones.
- **Route-Based Pricing**: Define fixed prices for specific origin-destination pairs.
- **Passenger & Luggage Criteria**: Filter vehicles by passenger count and luggage capacity (Big/Small).
- **AJAX Search**: Modern transfer search interface with real-time results.
- **WooCommerce Integration**: Seamlessly add transfer bookings to cart (Deposit or Full Payment).
- **Admin Management**: Manage locations, routes, and export/import transfer data.
- **City → Point Hierarchy (v4.23.0)**: Each location has a city field; locations are filtered by city for vendors and search.
- **Vendor Route Pricing (v4.23.0)**: Vendors set per-route prices within admin min/max range; search engine uses vendor price with base_price fallback.
- **Route-Based Vehicle Filtering (v4.23.0)**: Transfer search engine filters vehicles by route assignment, passenger and luggage capacity.

**Transfer Shortcodes:**
- `[rentiva_transfer_search]` — Main transfer search form.
- `[rentiva_transfer_results]` — Transfer search results display.

### 🏪 Vendor Marketplace (Pro)

**Multi-Vendor Management:**
- **Vendor Role**: Custom `rentiva_vendor` WordPress role with isolated permissions
- **Vendor Application**: Frontend application form with document uploads (ID, license, address, insurance)
- **Onboarding Workflow**: Admin approve/reject/suspend vendor applications
- **IBAN Encryption**: AES-256-CBC encrypted bank account storage

**Vendor Vehicle Management:**
- **Frontend Vehicle Submission**: Vendors submit vehicles via `[rentiva_vehicle_submit]` shortcode
- **Vehicle Review**: Admin approve/reject with partial edit support (critical vs minor fields)
- **Media Isolation**: Per-vendor media library isolation
- **Ownership Enforcement**: Vendors can only edit their own vehicles

**Vendor Transfer Operations (v4.23.0):**
- **City-Based Filtering**: Vendors only see locations and routes in their city
- **Route Pricing**: Vendors set per-route prices within admin-defined min/max range
- **Transfer Search Integration**: Search engine uses vendor prices with base_price fallback

**Financial System:**
- **Commission Management**: Flexible commission rates per vendor
- **Ledger System**: Complete financial transaction history
- **Payout Requests**: Vendor payout tracking and approval
- **Refund Entries**: Automatic reverse ledger entries for cancellations

**Vendor Panel (`/panel/`):**
- Listings: Vehicle management with inline add form
- Booking Requests: Incoming reservation management
- Ledger & Payouts: Financial overview and payout requests

**Vendor Notifications (15 Email Templates):**
- Application submitted/approved/rejected
- Vehicle approved/rejected
- Payout approved/rejected
- Lifecycle: activated/paused/resumed/withdrawn/expired/expiry warnings/renewed/relisted

### 🔄 Vehicle Lifecycle Management (Pro, v4.24.0)

**State Machine:**
- **5 States**: Pending Review, Active, Paused, Expired, Withdrawn
- **Transition Rules**: Enforced state machine with allowed transitions
- **90-Day Listings**: Automatic listing duration with cron-based expiry

**Vendor Self-Service:**
- Pause/Resume: Temporarily hide listing (timer continues)
- Withdraw: Permanently remove with 7-day cooldown before relisting
- Renew: Extend active listing for another 90 days
- Relist: Resubmit withdrawn vehicle for admin review

**Progressive Penalties:**
- 1st withdrawal: Free
- 2nd withdrawal: 10% of monthly average revenue
- 3rd+ withdrawal: 25% of monthly average revenue
- Rolling 12-month window with ledger-integrated penalty recording

**Reliability Score (0-100):**
- Daily cron recalculation for all vendors
- Formula: Base 100, -5/cancellation, -10/withdrawal, -2/pause, +5/completion (max +20)
- Labels: Excellent (90+), Good (70+), Fair (50+), Poor (<50)
- Displayed on admin Users list and vehicle edit meta box

**Anti-Gaming Protection:**
- Vendor-cancelled booking dates re-blocked for 30 days
- Prevents price manipulation via cancel-and-relist tactics

**Admin UI:**
- Lifecycle status column on vehicle list table (colored badges with days remaining)
- Read-only lifecycle meta box on vehicle edit screen
- Vendor reliability score column on Users list (sortable)

**Automated Notifications:**
- 10-day and 3-day expiry warning emails
- Status change notifications (activated, paused, resumed, withdrawn, expired)
- Renewal and relist confirmation emails

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
- **Lite Version Limit**: Maximum 4 addons in Lite version (unlimited in Pro)
- **Bulk Actions**: Enable/disable/add/remove multiple addons at once
- **Addon Settings**: Global settings for addon display and behavior

**Default Addons** (can be created automatically):
- GPS Navigation
- Child Seat
- Additional Driver
- Insurance
- And more...

### 📤 Data Export System

**Export Formats:**
- **CSV**: Comma-separated values (free)
- **JSON**: JSON format (free)
- **Excel (XLS)**: Microsoft Excel format (Pro)
- **XML**: XML format (Pro)
- **PDF**: PDF reports (Pro - Advanced Reports feature)

**Exportable Data:**
- **Bookings**: All booking data with filters
- **Vehicles**: Vehicle inventory
- **Logs**: System logs
- **Reports**: Analytics data

**Export Features:**
- **Advanced Filtering**: Filter by date range, status, vehicle, customer
- **Export History**: Track all exports
- **Export Statistics**: View export usage
- **Bulk Export**: Export multiple data types at once
- **Custom Fields**: Include/exclude specific fields
- **Date Range Selection**: Flexible date filtering

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

### 🔒 Privacy & GDPR Compliance (Pro)

**GDPR Features:**
- **Data Retention**: Configurable data retention period (default: 2550 days)
- **Data Anonymization**: Anonymize user data instead of deletion
- **Data Export**: Customer can export all their data
- **Data Deletion**: Customer can request account deletion
- **Consent Management**: Track and manage user consent
- **Privacy Controls**: Customer dashboard with privacy controls

**Privacy Controls in Customer Dashboard:**
- Export personal data (JSON format)
- Withdraw consent for data processing
- Delete account and all associated data
- View privacy policy

**Automatic Cleanup:**
- Scheduled cleanup of inactive users
- Cleanup old completed/cancelled bookings
- Respects retention period settings
- Anonymization option before deletion

### 🧩 Gutenberg Blocks Integration

**19 Available Blocks:**
- Availability Calendar, Booking Form, Featured Vehicles, Login Form, Messages
- My Bookings, My Favorites, Payment History, Register Form, Search Results
- Testimonials, Thank You, Transfer Results, Transfer Search, Unified Search
- User Dashboard, Vehicle Comparison, Vehicle Details, Vehicles Grid

All 19 blocks delegate to their shortcode renderer via `do_shortcode()` (Render Parity architecture), ensuring identical output across Gutenberg, Elementor, and shortcode usage.

**Block Features:**
- Visual block editor integration
- Custom block category "MHM Rentiva"
- Block attributes mapped via Canonical Attribute Mapper (CAM) pipeline
- Preview in editor
- Responsive design

### 🎨 Elementor Widgets Integration

**Complete Widget Suite (20 Widgets):**

**Vehicle Widgets:**
- **Vehicle Search Widget**: Advanced vehicle search with filters
- **Vehicle Card Widget**: Single vehicle display with customizable layout
- **Vehicles List Widget**: Grid or list view of vehicles with advanced query options
- **Vehicle Details Widget**: Detailed vehicle information display
- **Vehicle Comparison Widget**: Side-by-side vehicle comparison
- **Vehicle Rating Widget**: Vehicle rating and review display
- **Search Results Widget**: Search results display with filtering
- **Availability Calendar Widget**: Interactive availability calendar

**Booking Widgets:**
- **Booking Form Widget**: Complete booking form with vehicle selection, addons, and payment options
- **Booking Confirmation Widget**: Booking confirmation display
- **Thank You Widget**: Thank you page after booking completion

**Account Widgets:**
- **My Account Widget**: Customer account dashboard
- **My Bookings Widget**: Customer booking history
- **My Favorites Widget**: Favorite vehicles management
- **Payment History Widget**: Payment transaction history
- **Account Details Widget**: Account profile editing

**Authentication Widgets:**
- **Login Form Widget**: Customer login form
- **Register Form Widget**: Customer registration form

**Other Widgets:**
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
- **PHPUnit**: 720 tests (v4.26.0)
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

### 🔐 Licensing System

MHM Rentiva uses a **freemium model** with Lite (free) and Pro (paid) versions. The plugin automatically detects license status and enables/restricts features accordingly.

#### License Management

**License Activation:**
- **Location**: `Rentiva > License`
- **License Key Format**: Alphanumeric with hyphens (e.g., `XXXX-XXXX-XXXX-XXXX`)
- **Activation Process**: 
  1. Enter license key in License page
  2. Click "Activate License"
  3. System validates with license server
  4. Pro features automatically enabled
- **License Validation**: Automatic daily validation via WordPress cron
- **License Expiration**: Warnings shown 14 days before expiration

**License Server Integration:**
- **API Endpoints**: 
  - `/licenses/activate` - Activate license
  - `/licenses/validate` - Validate license
  - `/licenses/deactivate` - Deactivate license
- **Site Hash**: Unique site identifier for license binding
- **Staging Support**: Automatic detection of staging environments
- **Multi-site Support**: License works across WordPress multisite

**Developer Mode:**
- **Automatic Detection**: Development environments automatically enable Pro features
- **Detection Criteria**:
  - Localhost domains (localhost, 127.0.0.1, ::1)
  - Local TLDs (.local, .test, .dev, .staging)
  - Development ports (8080, 8081, 3000, etc.)
  - XAMPP/WAMP/MAMP server software
  - WordPress debug mode (WP_DEBUG)
  - Development environment constant (WP_ENV)
- **Security**: Only works on localhost/development domains (secure)

#### Lite Version (Free) - Feature Limitations

**Quantity Limits:**
- **Vehicles**: Maximum **5 vehicles** (publish, pending, private status)
- **Bookings**: Maximum **50 bookings** (publish, pending, private status)
- **Customers**: Maximum **10 customers** (WordPress users with bookings)
- **Addons**: Maximum **4 addon services** (additional services)

**Payment Gateway:**
- ✅ **Frontend Payments**: Via WooCommerce (All gateways supported)
- ✅ **Manual Payments**: Native Offline Payment (Admin only)

**Export Restrictions:**
- ✅ **CSV Export**: Available (all versions)
- ✅ **JSON Export**: Available (all versions)
- ❌ **Excel Export (XLS)**: Not available (Pro only via FEATURE_EXPORT)
- ❌ **XML Export**: Not available (Pro only via FEATURE_EXPORT)
- ❌ **PDF Export**: Not available (Pro only via FEATURE_REPORTS_ADV)

**Report Restrictions:**
- **Date Range**: Maximum **30 days** (filtered automatically)
- **Report Rows**: Maximum **500 rows** per export
- **Advanced Reports**: Not available (Pro only)
- **Report Export**: Limited to CSV/JSON only

**Messaging System:**
- ❌ **Customer Messaging**: Not available (Pro only)
- ❌ **Admin Messaging**: Not available (Pro only)
- ❌ **Message Threads**: Not available (Pro only)

**Other Limitations:**
- **Advanced Reports Feature**: Not available (basic reports only)
- **Report Export Formats**: Limited to CSV/JSON for Lite reports

**Lite Version Restrictions UI:**
- Admin notices show current usage (e.g., "3/3 vehicles used")
- "Add New" buttons hidden when limits reached
- Pro-locked sections show "Pro" badge
- Upgrade prompts throughout admin interface

#### Pro Version - Full Feature Access

**Unlimited Quantities:**
- **Vehicles**: **Unlimited** vehicles
- **Bookings**: **Unlimited** bookings
- **Customers**: **Unlimited** customers
- **Addons**: **Unlimited** addon services

**All Payment Gateways:**
- ✅ **Frontend Payments**: Via WooCommerce (All gateways supported)
- ✅ **Manual Payments**: Native Offline Payment (Admin only)

**All Export Formats:**
- ✅ **CSV Export**: Available (all versions)
- ✅ **JSON Export**: Available (all versions)
- ✅ **Excel Export (XLS)**: Available (Pro only, requires FEATURE_EXPORT)
- ✅ **XML Export**: Available (Pro only, requires FEATURE_EXPORT)
- ✅ **PDF Export**: Available (Pro only, requires FEATURE_REPORTS_ADV, HTML-based table export)

**Advanced Reports (FEATURE_REPORTS_ADV):**
- **Unlimited Date Range**: No date restrictions (Lite: 30 days max)
- **Unlimited Rows**: No row limits (Lite: 500 rows max)
- **Report Types**: Revenue, Bookings, Vehicles, Customers reports
- **Dashboard Widgets**: Statistics and revenue chart widgets
- **Multi-format Export**: Export reports in all formats (CSV, JSON, Excel, XML, PDF)
- **Report Cache**: Automatic caching for performance

**Messaging System (FEATURE_MESSAGES):**
- ✅ **Customer Messaging**: Frontend customer message interface
- ✅ **Admin Messaging**: Admin message management interface
- ✅ **Message Threads**: Thread-based conversation system (UUID-based)
- ✅ **Email Notifications**: Automatic email notifications for new messages and replies
- ✅ **Message Status**: Open, In Progress, Closed status management
- ✅ **Message Categories**: Categorized message organization (General, Support, Booking, etc.)
- ✅ **Message Priority**: Priority levels (Low, Normal, High, Urgent)
- ✅ **REST API**: REST endpoints for message operations
- ✅ **Unlimited Messages**: No message limits in Pro version

**Additional Pro Features:**
- **REST API Access**: Full REST API endpoints for integration
- **Email Notifications**: Automatic email notifications for bookings
- **Logging System**: Comprehensive logging for debugging
- **GDPR Compliance**: Data export, anonymization, and deletion features
- **Database Maintenance**: WP-CLI commands for database cleanup
- **Cron Jobs**: Automated background tasks

#### Feature Comparison Table

| Feature | Lite Version | Pro Version |
|---------|--------------|-------------
| **Maximum Vehicles** | 5 | Unlimited |
| **Maximum Bookings** | 50 | Unlimited |
| **Maximum Customers** | 10 | Unlimited |
| **Maximum Addons** | 4 | Unlimited |
| **VIP Transfer Routes** | 3 | Unlimited |
| **Gallery Images** | 5 / Vehicle | Unlimited |
| **Frontend Payments** | Via WooCommerce | Via WooCommerce |
| **Manual Payments** | Native Offline | Native Offline |
| **Export Formats** | CSV, JSON | CSV, JSON, Excel, XML, PDF |
| **Report Date Range** | 30 days max | Unlimited |
| **Report Rows** | 500 max | Unlimited |
| **Advanced Reports** | ❌ | ✅ (FEATURE_REPORTS_ADV) |
| **Messaging System** | ❌ | ✅ (FEATURE_MESSAGES) |
| **Vendor Marketplace** | ❌ | ✅ (Pro) |
| **Vehicle Lifecycle Management** | ❌ | ✅ (Pro) |
| **API Access** | Limited | Full REST API |

#### License Administration

**License Page Location**: `Rentiva > License`

**License Status Display:**
- Pro License Active (green badge)
- Lite Version (yellow badge)
- Developer Mode (info badge)
- License expiration warnings
- Last validation timestamp

**License Actions:**
- **Activate License**: Enter license key and activate
- **Deactivate License**: Remove license from site
- **Validate License**: Manually check license status
- **Change License**: Deactivate old, activate new

**License Information Displayed:**
- License key (masked)
- License status (active/inactive)
- Expiration date
- Last validation time
- Site hash (for support)

**Restriction Enforcement:**
- **Automatic Limits**: System prevents exceeding Lite limits
- **Admin Notices**: Warnings when approaching limits
- **Feature Gates**: Pro features automatically disabled in Lite
- **UI Overlays**: Pro-locked sections visually marked
- **Upgrade Prompts**: Clear upgrade paths to Pro

#### License Validation

**Automatic Validation:**
- **Daily Cron Job**: Validates license every 24 hours
- **On Activation**: Validates immediately when activated
- **On Page Load**: Checks license status in admin
- **Before Pro Features**: Validates before enabling Pro features

**Validation Process:**
1. Sends license key to license server
2. Sends site hash for site binding
3. Server validates license status
4. Returns license information (status, expiration, plan)
5. Updates local license data
6. Enables/restricts features accordingly

**Site Binding:**
- **Site Hash**: Generated from site URL, WordPress version, PHP version
- **Prevents License Sharing**: License tied to specific site
- **Staging Support**: Staging sites don't count as separate activations
- **Multi-site**: License works across network sites

**License Expiration:**
- **Warning Period**: Shows warnings 14 days before expiration
- **Grace Period**: Features continue working after expiration (configurable)
- **Expiration Handling**: Automatic deactivation on expiration
- **Renewal**: Easy renewal process from license page

#### Developer Mode

**Automatic Developer Mode:**
- **Purpose**: Automatically enables Pro features in development
- **Security**: Only works on localhost/development domains
- **Detection**: Multiple criteria for reliable detection
- **No License Required**: No license key needed in development

**Development Environment Detection:**
1. **Host Check**: localhost, .local, .test, .dev, .staging domains
2. **Server Software**: XAMPP, WAMP, MAMP, LAMP detection
3. **Port Check**: Development ports (8080, 3000, etc.)
4. **WordPress Debug**: WP_DEBUG constant check
5. **Environment Constant**: WP_ENV development check

**Developer Mode Features:**
- All Pro features enabled
- No quantity limits
- All payment gateways available (via WooCommerce)
- All export formats available
- Full messaging system
- Advanced reports enabled

**Security Considerations:**
- Only works on localhost/development domains
- Cannot be activated on production sites
- Prevents license key sharing
- Secure automatic detection

#### License Troubleshooting

**Common Issues:**

1. **License Not Activating**
   - Check license key format
   - Verify license server connectivity
   - Check site hash generation
   - Verify staging environment detection

2. **Features Not Enabling**
   - Validate license manually
   - Check license expiration
   - Verify site hash matches
   - Check developer mode status

3. **License Expiration**
   - Renew license before expiration
   - Check expiration date in license page
   - Contact support for renewal issues

4. **Staging Environment**
   - Staging sites automatically detected
   - Don't count as separate activations
   - License works on staging sites

**Support:**
- License issues: Contact support with license key
- Feature questions: Check feature comparison table
- Upgrade inquiries: Visit license page for upgrade options

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

---

## 🚀 Installation

### Step 1: Upload Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/mhm-rentiva/`
3. Activate the plugin through WordPress admin panel

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
- Search page (use `[rentiva_search]` shortcode)
- Contact page (use `[rentiva_contact]` shortcode)
- Login page (use `[rentiva_login_form]` shortcode)
- Registration page (use `[rentiva_register_form]` shortcode)
- Favorites page (use `[rentiva_my_favorites]` shortcode)
- VIP Transfer Search (use `[mhm_rentiva_transfer_search]` shortcode)

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

## ⚙️ Configuration

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

## 🎯 Shortcodes Reference

The plugin provides a comprehensive set of shortcodes for flexible layout building.

### Booking & Vehicle Display
- `[rentiva_booking_form]` — Main booking form (accepts `vehicle_id` parameter).
- `[rentiva_vehicles_grid]` — Displays vehicles in a grid layout.
- `[rentiva_vehicles_list]` — Displays vehicles in a list layout.
- `[rentiva_featured_vehicles]` — Displays featured vehicles (slider/grid).
- `[rentiva_vehicle_details]` — Displays detailed information for a single vehicle.
- `[rentiva_search_results]` — Active search results page.
- `[rentiva_unified_search]` — Modern unified search box.
- `[rentiva_availability_calendar]` — Visual availability calendar.
- `[rentiva_testimonials]` — Customer testimonials slider.
- `[rentiva_vehicle_rating_form]` — Vehicle review/rating form.

### Customer Account
- `[rentiva_user_dashboard]` — Customer/Vendor main dashboard.
- `[rentiva_my_bookings]` — Lists customer's current and past bookings.
- `[rentiva_my_favorites]` — Lists customer's favorite vehicles.
- `[rentiva_payment_history]` — Displays payment history and receipt details.
- `[rentiva_messages]` — Internal messaging system (Pro).

### Vendor & Transfer
- `[rentiva_vendor_apply]` — New vendor application form.
- `[rentiva_vehicle_submit]` — Frontend vehicle submission form (Vendor).
- `[rentiva_vendor_ledger]` — Vendor financial ledger and balance table (Vendor).
- `[rentiva_transfer_search]` — VIP Transfer / Chauffeur search form.
- `[rentiva_transfer_results]` — Transfer search results display.

---

## 🔌 REST API Documentation

> **Lite:** Limited API access. **Pro:** Full REST API with all endpoints.

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

## 📁 Project Structure

```text
mhm-rentiva/
├── assets/                 # CSS, JS, Images (Minified)
├── docs/                   # Technical documentation & API guides
├── languages/              # i18n (.pot, .po)
├── src/                    # PSR-4 Core PHP (MHMRentiva\*)
│   ├── Admin/              # Admin Module Controllers & Services
│   ├── Api/                # Custom REST API Endpoints
│   ├── Blocks/             # Gutenberg Block definitions
│   ├── CLI/                # WP-CLI Commands
│   ├── Core/               # Financial engine & Base Services
│   ├── Helpers/            # Utility & Sanitization classes
│   ├── Integrations/       # External bridges (WooCommerce, etc.)
│   └── Plugin.php          # Main initialization class
├── templates/              # HTML & Email templates
├── mhm-rentiva.php         # Main entry point
└── uninstall.php           # Cleanup on deletion
```

---

## 📋 Requirements

### WordPress & PHP
- **WordPress**: 6.7 minimum (Tested up to 6.9)
- **PHP**: 8.1 minimum (8.2+ recommended)
- **Memory Limit**: 128MB minimum (256MB recommended)

### Required Extensions
- `json` — For API and settings processing.
- `curl` — For license and external integrations.
- `mbstring` — For multi-language support.
- `openssl` — For secure data encryption.

---

## 🛠 Development

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
- Activation tests
- Security tests
- Functional tests
- Performance tests

---

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/NewFeature`
3. **Follow coding standards**: WordPress Coding Standards
4. **Write clear commit messages**: Use conventional commits
5. **Test thoroughly**: Test all functionality
6. **Submit pull request**: Include description of changes

---

## 📝 Changelog

### Version 4.26.0 (2026-04-07)
- **Pay Remaining Amount**: Customers with deposit bookings can now pay the remaining balance directly from My Account → Booking Detail via a "Pay Remaining Amount" button.
- **WooCommerce Native Flow**: A minimal WC order is created programmatically for the remaining amount and the customer is redirected to the native WC order-pay page — any active payment gateway works without code changes.
- **Duplicate Guard**: If a pending remaining-payment order already exists, the same order is reused instead of creating a new one (`_mhm_remaining_order_id` meta).
- **Fix**: CSS scoping — all generic account page classes now scoped under `.mhm-rentiva-account-page` wrapper to prevent theme conflicts.

### Version 4.25.1 (2026-04-07)
- **Fix**: CSS scoping — all generic account page classes now scoped under `.mhm-rentiva-account-page` wrapper to prevent WoodMart and other theme conflicts.
- **Fix**: WC My Account grid layout fix (`grid-column: 1/-1`) for integrated mode.

### Version 4.25.0 (2026-04-01)
- **Vendor Email Templates**: 22 vendor notification templates are now editable from the admin panel.
- **Email Layout**: Improved gradient rendering in Gold Standard layout.
- **Accessibility**: Amber CTA button contrast raised from 2.97:1 to 6.8:1 (WCAG AA).
- **i18n**: 56 new Turkish translations for vendor email admin UI.
- **Fix**: Site Instance Check-in cron now visible in Cron Job Monitor.
- **Fix**: Plugin now explicitly registers the `weekly` WP cron interval.

### Version 4.23.0 (2026-03-26)
- **Architecture**: Vendor Transfer Location system with city→point hierarchy
- **Pricing**: Vendor per-route pricing within admin min/max range
- **Search**: Transfer search engine with route-based vehicle filtering
- **Database**: v3.4.0 migration (city column, max_price column)
- **Dashboard**: 11 widget fixes (timezone, cache, WC email, stats design, Lite gating)
- **Export**: 4 bug fixes (post_type, record count, history delete, PHP 8 strict types)
- **Elementor**: 7 widget attribute improvements
- **Tests**: 567 tests, 2036 assertions

### Version 4.22.2 (2026-03-25)
- **Notices**: Standardized all Lite limit notices with unified percentage format
- **Limits**: Gallery images limit updated to 5, comparison table redesigned
- **Fixes**: Emoji corruption and export property bugs fixed

### Version 4.22.0 (2026-03-24)
- **Audit**: Comprehensive AllowlistRegistry, BlockRegistry, and Elementor widget audit
- **Tests**: 13 shortcode render test files, 4 SettingsSanitizer test files
- **Fixes**: 6 PHPUnit failures resolved, block.json defaults corrected

### Version 4.21.2 (2026-03-11)
- **Security**: Hardened REST API with `SecurityHelper` and `AuthHelper`.
- **Shortcodes**: Consolidated all shortcodes via `ShortcodeServiceProvider`.
- **Infrastructure**: Standardized PHP 8.1+ and WP 6.7+ requirements.
- **VIP Transfer**: Introduced point-to-point route pricing engine.

### Version 4.9.8 (2026-02-09)

**Stability & CI Standardization**
- Synced plugin version sources to 4.9.8 (header + constant).
- Standardized Composer/CI command usage (composer test, composer phpcs).
- Added low-risk performance hardening (asset versioning, localization guards, bootstrap scoping).

### Version: 4.6.7 (2026-02-01)

**🛡️ SECURITY & STANDARDS**
- **Safe Redirection**: Replaced `wp_redirect` with `wp_safe_redirect` project-wide to ensure internal redirection safety.
- **Asset Standards**: Refactored admin inline styles to use the official `wp_add_inline_style` API.
- **Performance Caching**: Migrated direct SQL cache cleanup to a versioned system using native `delete_transient()`.
- **Security Helper**: Enhanced `SecurityHelper::safe_output` with robust context validation and JSON support.

### Version: 4.6.6 (2026-01-28)

**🐛 BUG FIXES & UI IMPROVEMENTS**
- **Vehicle Icons**: Resolved issue with disappearing vehicle feature icons (fuel, transmission, etc.) in the Booking Form.
- **UI Optimization**: Improved icon sizing and visual presentation in the Booking Form.
- **Logic Fix**: Corrected data processing logic for vehicle feature SVGs.
- **Cleanup**: Removed conflicting legacy CSS to ensure consistent styling.

### Version: 4.6.5 (2026-01-26)

**🛡️ SECURITY & STANDARDS**
- **WPCS Compliance**: Resolved 50+ security issues related to Input Sanitization and Database Interpolation.
- **XSS Hardening**: Hardened `Handler.php` and `AccountController.php` against sophisticated XSS attacks.
- **Automated Refactoring**: Successfully applied 110,000+ automated style fixes across the entire project for strict WordPress Coding Standards (WPCS) alignment.
- **Logging Engine**: Transitioned from legacy `error_log` to the new high-performance `AdvancedLogger`.

### Version: 4.6.4 (2026-01-26)

**🛡️ SECURITY & DATA INTEGRITY**
- **Output Escaping**: Hardened admin tabs and system info screens using `esc_html`.
- **Sanitization**: Improved price field and ID sanitization in meta boxes.
- **Localization**: Completed English translation of administrative strings.

### Version: 4.6.3 (2026-01-25)

**🛡️ SECURITY & RELIABILITY**
- **SQL Hardening**: Protection against SQL Injection in message searches.
- **AJAX Hooks**: Improved reliability for backend integration settings.

### Version: 4.6.2 (2026-01-21)

**🛡️ SECURITY AUDIT**
- **Nonce Hardening**: WPCS compliant nonce verification applied project-wide.

### Version: 4.6.1 (2026-01-21)

**🛡️ CRITICAL & SECURITY**
- **DatabaseCleaner**: Added protection for 40+ critical meta keys (WooCommerce orders, payment details) to prevent data loss.
- **SQL Security**: Hardened SQL queries with `wpdb->prepare()` in `BookingColumns` and `ExportStats`.

**🛍️ WOOCOMMERCE & PAYMENTS**
- **Atomic Overlap Lock**: Implemented locking mechanism to prevent race conditions and duplicate bookings via WooCommerce.
- **Tax Calculation**: Fixed tax calculation logic to correctly calculate tax on the *total* booking amount, even for deposit payments.
- **Payment Settings**: Added UI notice to Payment Settings when WooCommerce is active to guide users.

### Latest Version: 4.6.0 (2026-01-18)

** VIP TRANSFER MODULE**
- **Point-to-Point Booking**: Dynamic pickup/drop-off location management.
- **Pricing Engine**: Distance-based or fixed-rate route pricing.
- **WooCommerce Integration**: Support for transfer bookings in cart and checkout.
- **AJAX Search**: New shortcode `[rentiva_transfer_search]` for real-time results.
- **Operational Control**: Added Buffer Time logic for vehicle preparation.

### Version: 4.5.5 (2026-01-15)

**🎨 FRONTEND POLISH & UI FIXES**
- **Vehicle Details**: Fixed "Out of Use" badge logic and standardizing placement.
- **Search Results**: Standardized button colors and added status indicators.
- **Comparison Page**: Improved card alignment and mobile styling.
- **Bookings Page**: Optimized table layout to prevent horizontal scrolling.

### Version: 4.5.4 (2026-01-15)

**🚀 REFACTORING & USER EXPERIENCE**
- **Settings Refactoring**: Major refactoring of settings core for better modularity.
- **WooCommerce Compatibility**: Payment Settings tab correctly hides when WooCommerce is active.
- **Bug Fix**: Fixed Fatal Error in Offline Payment Emails tab.

Full changelog available in [changelog.json](changelog.json).

---

## 📄 License

This project is licensed under the **GPL-2.0+** license. See the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Developer

**MaxHandMade**
- Website: [maxhandmade.com](https://maxhandmade.com)
- Support: info@maxhandmade.com

---

## 📞 Support

For questions, issues, or feature requests:
- **Email**: info@maxhandmade.com
- **Website**: https://maxhandmade.com

---

## ⭐ Star This Project

If you find this plugin useful, please consider giving it a star on GitHub!

---

**Made with ❤️ for the WordPress community**
