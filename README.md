# MHM Rentiva - WordPress Vehicle Rental Plugin

<div align="right">

**🌐 Language / Dil:** 
[![EN](https://img.shields.io/badge/Language-English-blue)](README.md) 
[![TR](https://img.shields.io/badge/Dil-Türkçe-red)](README-tr.md) 
[![Changelog](https://img.shields.io/badge/Changelog-EN-green)](changelog.json) 
[![Değişiklikler](https://img.shields.io/badge/Değişiklikler-TR-orange)](changelog-tr.json)

</div>

![Version](https://img.shields.io/badge/version-4.6.7-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
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

MHM Rentiva is a comprehensive WordPress plugin designed for vehicle rental businesses. Whether you're running a car rental company, bike rental service, or any vehicle-based rental business, this plugin provides everything you need to manage your operations efficiently.

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
- **Bike/Motorcycle Rentals**: Track availability and process payments
- **Equipment Rental Businesses**: Rent any type of vehicle or equipment
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
- `[rentiva_my_bookings]` - Booking history
- `[rentiva_my_favorites]` - Favorite vehicles
- `[rentiva_payment_history]` - Payment transactions
- `[rentiva_account_details]` - Profile editing
- `[rentiva_login_form]` - Login form
- `[rentiva_register_form]` - Registration form

**Customer Features:**
- Automatic account creation on booking
- Username generation from name (not email)
- Email verification
- Password reset functionality
- Booking notifications
- Email notifications
- Message notifications

### 📊 Reporting & Analytics

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
| **Maximum Vehicles** | 3 Vehicles | **Unlimited** |
| **Maximum Bookings** | 50 Bookings | **Unlimited** |
| **Maximum Customers** | 3 Customers | **Unlimited** |
| **Additional Services (Addons)** | 4 Services | **Unlimited** |
| **VIP Transfer Routes** | 3 Routes | **Unlimited** |
| **Gallery Images** | 3 Images / Vehicle | **Unlimited** |
| **Report Date Range** | Last 30 Days | **Unlimited** |
| **Report Rows** | 500 Rows | **Unlimited** |
| **Messaging System** | ❌ Not available | ✅ Available |
| **Export Formats** | CSV Only | CSV, JSON |
| **Payment Gateways** | WooCommerce | WooCommerce |
| **REST API Access** | Limited | Full API |
| **Advanced Reports** | ❌ Limited | ✅ Full Access |

> **Note:** Lite version is designed for small businesses and testing. For unlimited access, please check the Pro version.

**Report Features:**
- Real-time data updates
- Custom date range selection
- Export to CSV (Lite) and CSV/JSON (Pro)
- Visual charts and graphs
- Responsive design
- Print-friendly views

### 📧 Email Notification System

**Email Templates:**
1. **Booking Emails**:
   - Booking created (customer)
   - Booking created (admin)
   - Booking cancelled
   - Booking status changed
   - Booking reminder

2. **Payment Emails**:
   - Payment received
   - Receipt uploaded (admin notification)
   - Receipt approved (customer)
   - Receipt rejected (customer)
   - Refund processed

3. **Account Emails**:
   - Welcome email
   - Account created
   - Password reset

4. **Message Emails**:
   - New message received (admin)
   - Message replied (customer)
   - Message status changed

**Email Features:**
- **Modern HTML Templates**: Responsive design, works on all email clients
- **Customizable**: Admin can override subject and content from settings
- **Multi-language**: Support for multiple languages
- **Template System**: Easy to customize with template overrides
- **Email Logging**: All emails logged for debugging

### 💬 Messaging System

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
- **Distance-Based Pricing**: Calculate costs based on route distance or fixed zone-to-zone rates.
- **Vehicle Selection**: Assign specific vehicles for transfer services with different capacities.
- **Buffer Time**: Operational buffer between bookings to ensure vehicle readiness.
- **AJAX Search**: Modern transfer search interface with real-time results.
- **WooCommerce Integration**: Seamlessly add transfer bookings to cart (Deposit or Full Payment).
- **Frontend Tracking**: Customers can view transfer details in their "My Account" area.

**Transfer Display Options:**
- Dedicated search shortcode: `[mhm_rentiva_transfer_search]`
- Transfer details view in customer account
- Admin transfer management dashboard

### 🌍 Internationalization & Localization

**Language Support:**
- **60+ Languages**: Full support for 60+ WordPress locales
- **Centralized Management**: `LanguageHelper` class for unified language management
- **Automatic Detection**: Uses WordPress locale setting
- **JavaScript Localization**: Locale conversion for JavaScript date/time libraries
- **Translation Ready**: All strings use WordPress translation functions

**Currency Support:**
- **47 Currencies**: Support for 47 different currencies
- **Centralized Management**: `CurrencyHelper` class for unified currency management
- **Currency Symbols**: Proper symbol display for all currencies
- **Currency Position**: Configurable currency symbol position (left/right with/without space)
- **Supported Gateways**: All WooCommerce Payment Gateways (Frontend), Native Offline (Admin Manual Only)

**Supported Currencies:**
TRY, USD, EUR, GBP, JPY, CAD, AUD, CHF, CNY, INR, BRL, RUB, KRW, MXN, SGD, HKD, NZD, SEK, NOK, DKK, PLN, CZK, HUF, RON, BGN, HRK, RSD, UAH, BYN, KZT, UZS, KGS, TJS, TMT, AZN, GEL, AMD, AED, SAR, QAR, KWD, BHD, OMR, JOD, LBP, EGP, ILS

### 🔒 Security Features

**Security Measures:**
- **XSS Protection**: All output properly escaped
- **SQL Injection Prevention**: Prepared statements for all database queries
- **CSRF Protection**: Nonce verification for all forms
- **Input Sanitization**: All user input sanitized
- **File Upload Security**: Secure file upload with type validation
- **Rate Limiting**: API rate limiting to prevent abuse
- **Permission Checks**: Capability checks for all admin operations
- **Security Headers**: Proper security headers
- **GDPR Compliance**: Data retention and privacy controls

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

### 🔒 Privacy & GDPR Compliance

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

**Available Blocks:**
- **Vehicle Card Block**: Display single vehicle in card format
- **Vehicles List Block**: Display list of vehicles
- **Booking Form Block**: Embedded booking form

**Block Features:**
- Visual block editor integration
- Custom block category "MHM Rentiva"
- Block attributes configuration
- Preview in editor
- Responsive design

### 🎨 Elementor Widgets Integration

**Complete Widget Suite (19 Widgets):**

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
- **Activation Tests**: 9 tests for plugin activation
- **Security Tests**: 8 tests for security compliance
- **Functional Tests**: 6 tests for core functionality
- **Performance Tests**: 6 tests for performance
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
- **Vehicles**: Maximum **3 vehicles** (publish, pending, private status)
- **Bookings**: Maximum **50 bookings** (publish, pending, private status)
- **Customers**: Maximum **3 customers** (WordPress users with bookings)
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
|---------|--------------|-------------|
| **Maximum Vehicles** | 3 | Unlimited |
| **Maximum Bookings** | 50 | Unlimited |
| **Maximum Customers** | 3 | Unlimited |
| **Maximum Addons** | 4 | Unlimited |
| **Frontend Payments** | Via WooCommerce | Via WooCommerce |
| **Manual Payments** | Native Offline | Native Offline |
| **Export Formats** | CSV, JSON | CSV, JSON, Excel, XML, PDF |
| **Report Date Range** | 30 days max | Unlimited |
| **Report Rows** | 500 max | Unlimited |
| **Advanced Reports** | ❌ | ✅ (FEATURE_REPORTS_ADV) |
| **Messaging System** | ❌ | ✅ (FEATURE_MESSAGES) |
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

## 📝 Changelog

### Version: 4.5.5 (2026-01-15)

**🎨 FRONTEND POLISH & UI FIXES**
- **Vehicle Details**: Fixed "Out of Use" badge logic and standardizing placement. Fixed mobile overflow issues.
- **Search Results**: Standardized button colors (blue theme) and added "Out of Use" status indicators.
- **Comparison Page**: Improved card alignment, button styling, and removed inline styles.
- **My Favorites**: Added "Out of Use" badge and disabled booking button for unavailable vehicles.
- **Bookings Page**: Optimized table layout (shortened headers, compact buttons) to prevent horizontal scrolling.
- **Localization**: Added missing strings to POT file.

### Version: 4.5.4 (2026-01-15)

**🚀 REFACTORING & USER EXPERIENCE**
- **Settings Refactoring**: Major refactoring of settings core for better modularity and maintainability.
- **WooCommerce Compatibility**: Payment Settings tab now correctly hides when WooCommerce is active.
- **Email Settings**: Fixed issue where email settings were inaccessible when WooCommerce was active; now MHM Rentiva notification settings (messages etc.) are accessible.
- **Bug Fix**: Fixed Fatal Error in Offline Payment Emails tab (missing `OfflinePayment.php` class).
- **Localization**: Updated POT file with new strings.- **System Status**: Plugin health indicators

### 🔧 Shortcode Management

**Shortcode Pages:**
- **Auto Page Creation**: Automatically create pages for shortcodes
- **Page Detection**: Find pages containing shortcodes
- **URL Management**: Centralized URL generation for shortcode pages
- **Shortcode Settings**: Enable/disable individual shortcodes
- **Conditional Loading**: Assets only load when shortcodes are used

**Shortcode Settings:**
- Active/inactive shortcode toggle
- Shortcode page assignment
- URL customization
- Asset loading optimization

### 🔄 Maintenance Tasks

**Automatic Maintenance:**
- **Auto Cancel**: Automatically cancel unpaid bookings
- **Reconcile**: Data reconciliation and consistency checks
- **Log Retention**: Automatic cleanup of old logs
- **Email Log Retention**: Cleanup old email logs
- **Database Optimization**: Scheduled database maintenance

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

## 🎯 Shortcode Reference

### Account Management Shortcodes

#### `[rentiva_my_bookings]`
**Purpose**: Display customer booking history

#### `[rentiva_booking_form]`
**Purpose**: Main booking form for vehicle rental

**Usage**:
```php
[rentiva_booking_form vehicle_id="123"]
```

#### `[rentiva_my_favorites]`
**Purpose**: Display customer favorite vehicles list

**Usage**:
```php
[rentiva_my_favorites columns="3" limit="12"]
```

#### `[rentiva_vehicles_grid]`
**Purpose**: Display vehicle inventory in a grid layout

**Usage**:
```php
[rentiva_vehicles_grid columns="3" limit="12"]
```

#### `[mhm_rentiva_transfer_search]`
**Purpose**: VIP Transfer and chauffeured vehicle search form.

---

## 📁 Project Structure

```text
mhm-rentiva/
├── changelog.json                 # Version history (English)
├── changelog-tr.json              # Version history (Turkish)
├── LICENSE                        # GPL License information
├── mhm-rentiva.php                # Main entry file
├── readme.txt                     # WordPress.org metadata
├── README.md                      # Documentation (English)
├── README-tr.md                   # Documentation (Turkish)
├── uninstall.php                  # Cleanup logic on deletion
├── assets/
│   ├── css/
│   │   ├── admin/
│   │   │   ├── about.css
│   │   │   ├── addon-admin.css
│   │   │   ├── addon-list.css
│   │   │   ├── admin-reports.css
│   │   │   ├── booking-calendar.css
│   │   │   ├── booking-edit-meta.css
│   │   │   ├── booking-list.css
│   │   │   ├── booking-meta.css
│   │   │   ├── customers.css
│   │   │   ├── dark-mode.css
│   │   │   ├── dashboard-tooltips.css
│   │   │   ├── dashboard.css
│   │   │   ├── database-cleanup.css
│   │   │   ├── deposit-management.css
│   │   │   ├── elementor-editor.css
│   │   │   ├── email-templates.css
│   │   │   ├── export.css
│   │   │   ├── gutenberg-blocks-editor.css
│   │   │   ├── log-metabox.css
│   │   │   ├── manual-booking-meta.css
│   │   │   ├── message-list.css
│   │   │   ├── messages-admin.css
│   │   │   ├── messages-settings.css
│   │   │   ├── monitoring.css
│   │   │   ├── reports-stats.css
│   │   │   ├── rest-api-keys.css
│   │   │   ├── settings-testing.css
│   │   │   ├── settings.css
│   │   │   ├── test-suite.css
│   │   │   ├── vehicle-card-fields.css
│   │   │   └── vehicle-gallery.css
│   │   ├── components/
│   │   │   ├── addon-booking.css
│   │   │   ├── calendars.css
│   │   │   ├── simple-calendars.css
│   │   │   ├── stats-cards.css
│   │   │   └── vehicle-meta.css
│   │   ├── core/
│   │   │   ├── animations.css
│   │   │   ├── core.css
│   │   │   ├── css-variables.css
│   │   │   └── ux-notifications.css
│   │   ├── frontend/
│   │   │   ├── availability-calendar.css
│   │   │   ├── booking-confirmation.css
│   │   │   ├── booking-detail.css
│   │   │   ├── booking-form.css
│   │   │   ├── bookings-page.css
│   │   │   ├── contact-form.css
│   │   │   ├── customer-messages-standalone.css
│   │   │   ├── customer-messages.css
│   │   │   ├── deposit-system.css
│   │   │   ├── elementor-widgets.css
│   │   │   ├── gutenberg-blocks.css
│   │   │   ├── integrated-account.css
│   │   │   ├── my-account.css
│   │   │   ├── search-results.css
│   │   │   ├── testimonials.css
│   │   │   ├── vehicle-comparison.css
│   │   │   ├── vehicle-details.css
│   │   │   ├── vehicle-rating-form.css
│   │   │   ├── vehicle-search-compact.css
│   │   │   ├── vehicle-search.css
│   │   │   ├── vehicles-grid.css
│   │   │   └── vehicles-list.css
│   │   ├── payment/
│   │   │   └── woocommerce-checkout.css
│   │   └── transfer.css
│   ├── images/
│   │   ├── mhm-logo.png
│   │   └── placeholder-avatar.svg
│   └── js/
│       ├── admin/
│       │   ├── about.js
│       │   ├── addon-admin.js
│       │   ├── addon-list.js
│       │   ├── addon-settings.js
│       │   ├── booking-bulk-actions.js
│       │   ├── booking-calendar.js
│       │   ├── booking-edit-meta.js
│       │   ├── booking-email-send.js
│       │   ├── booking-filters.js
│       │   ├── booking-list-filters.js
│       │   ├── booking-meta.js
│       │   ├── cron-monitor.js
│       │   ├── customers-calendar.js
│       │   ├── customers.js
│       │   ├── dark-mode.js
│       │   ├── dashboard.js
│       │   ├── database-cleanup.js
│       │   ├── deposit-management.js
│       │   ├── elementor-editor.js
│       │   ├── email-templates.js
│       │   ├── export.js
│       │   ├── gutenberg-blocks.js
│       │   ├── log-metabox.js
│       │   ├── manual-booking-meta.js
│       │   ├── message-list.js
│       │   ├── messages-admin.js
│       │   ├── messages-settings.js
│       │   ├── monitoring.js
│       │   ├── reports-charts.js
│       │   ├── reports.js
│       │   ├── rest-api-keys.js
│       │   ├── settings-form-handler.js
│       │   ├── settings.js
│       │   ├── uninstall.js
│       │   ├── vehicle-card-fields.js
│       │   └── vehicle-gallery.js
│       ├── components/
│       │   ├── addon-booking.js
│       │   ├── vehicle-meta.js
│       │   └── vehicle-quick-edit.js
│       ├── core/
│       │   ├── admin-notices.js
│       │   ├── charts.js
│       │   ├── core.js
│       │   ├── i18n.js
│       │   ├── module-loader.js
│       │   ├── performance.js
│       │   └── utilities.js
│       ├── frontend/
│       │   ├── account-messages.js
│       │   ├── account-privacy.js
│       │   ├── availability-calendar.js
│       │   ├── booking-cancellation.js
│       │   ├── booking-confirmation.js
│       │   ├── booking-form.js
│       │   ├── contact-form.js
│       │   ├── customer-messages.js
│       │   ├── elementor-widgets.js
│       │   ├── my-account.js
│       │   ├── privacy-controls.js
│       │   ├── search-results.js
│       │   ├── testimonials.js
│       │   ├── vehicle-comparison.js
│       │   ├── vehicle-details.js
│       │   ├── vehicle-rating-form.js
│       │   ├── vehicle-search-compact.js
│       │   ├── vehicle-search.js
│       │   ├── vehicles-grid.js
│       │   └── vehicles-list.js
│       ├── vendor/
│       │   └── chart.min.js
│       └── mhm-rentiva-transfer.js
├── languages/
│   ├── mhm-rentiva.pot
│   ├── mhm-rentiva-tr_TR.mo
│   └── mhm-rentiva-tr_TR.po
├── src/
│   ├── Admin/
│   │   ├── About/
│   │   │   ├── Tabs/
│   │   │   │   ├── DeveloperTab.php
│   │   │   │   ├── FeaturesTab.php
│   │   │   │   ├── GeneralTab.php
│   │   │   │   ├── SupportTab.php
│   │   │   │   └── SystemTab.php
│   │   │   ├── About.php
│   │   │   ├── Helpers.php
│   │   │   └── SystemInfo.php
│   │   ├── Actions/
│   │   │   └── Actions.php
│   │   ├── Addons/
│   │   │   ├── AddonListTable.php
│   │   │   ├── AddonManager.php
│   │   │   ├── AddonMenu.php
│   │   │   ├── AddonMeta.php
│   │   │   ├── AddonPostType.php
│   │   │   └── AddonSettings.php
│   │   ├── Auth/
│   │   │   ├── LockoutManager.php
│   │   │   ├── SessionManager.php
│   │   │   └── TwoFactorManager.php
│   │   ├── Booking/
│   │   │   ├── Actions/
│   │   │   │   └── DepositManagementAjax.php
│   │   │   ├── Addons/
│   │   │   │   └── AddonBooking.php
│   │   │   ├── Core/
│   │   │   │   ├── Handler.php
│   │   │   │   ├── Hooks.php
│   │   │   │   └── Status.php
│   │   │   ├── Exceptions/
│   │   │   │   └── BookingException.php
│   │   │   ├── Helpers/
│   │   │   │   ├── Cache.php
│   │   │   │   ├── CancellationHandler.php
│   │   │   │   ├── Locker.php
│   │   │   │   └── Util.php
│   │   │   ├── ListTable/
│   │   │   │   └── BookingColumns.php
│   │   │   ├── Meta/
│   │   │   │   ├── BookingDepositMetaBox.php
│   │   │   │   ├── BookingEditMetaBox.php
│   │   │   │   ├── BookingMeta.php
│   │   │   │   ├── BookingPortalMetaBox.php
│   │   │   │   ├── BookingRefundMetaBox.php
│   │   │   │   └── ManualBookingMetaBox.php
│   │   │   └── PostType/
│   │   │       └── Booking.php
│   │   ├── CLI/
│   │   │   └── DatabaseCleanupCommand.php
│   │   ├── Core/
│   │   │   ├── Exceptions/
│   │   │   │   ├── MHMException.php
│   │   │   │   └── ValidationException.php
│   │   │   ├── Helpers/
│   │   │   │   └── Sanitizer.php
│   │   │   ├── MetaBoxes/
│   │   │   │   └── AbstractMetaBox.php
│   │   │   ├── PostTypes/
│   │   │   │   └── AbstractPostType.php
│   │   │   ├── Tabs/
│   │   │   │   └── AbstractTab.php
│   │   │   ├── Traits/
│   │   │   │   └── AdminHelperTrait.php
│   │   │   ├── Utilities/
│   │   │   │   ├── AbstractListTable.php
│   │   │   │   ├── BookingQueryHelper.php
│   │   │   │   ├── CacheManager.php
│   │   │   │   ├── DatabaseCleaner.php
│   │   │   │   ├── DatabaseMigrator.php
│   │   │   │   ├── DebugHelper.php
│   │   │   │   ├── ErrorHandler.php
│   │   │   │   ├── I18nHelper.php
│   │   │   │   ├── License.php
│   │   │   │   ├── MetaQueryHelper.php
│   │   │   │   ├── ObjectCache.php
│   │   │   │   ├── QueueManager.php
│   │   │   │   ├── RateLimiter.php
│   │   │   │   ├── RestApiFixer.php
│   │   │   │   ├── Styles.php
│   │   │   │   ├── TaxonomyMigrator.php
│   │   │   │   ├── Templates.php
│   │   │   │   ├── TypeValidator.php
│   │   │   │   ├── UXHelper.php
│   │   │   │   └── WordPressOptimizer.php
│   │   │   ├── AssetManager.php
│   │   │   ├── CurrencyHelper.php
│   │   │   ├── LanguageHelper.php
│   │   │   ├── MetaKeys.php
│   │   │   ├── PerformanceHelper.php
│   │   │   ├── ProFeatureNotice.php
│   │   │   ├── SecurityHelper.php
│   │   │   ├── ShortcodeServiceProvider.php
│   │   │   └── ShortcodeUrlManager.php
│   │   ├── Customers/
│   │   │   ├── AddCustomerPage.php
│   │   │   ├── CustomersListPage.php
│   │   │   ├── CustomersOptimizer.php
│   │   │   └── CustomersPage.php
│   │   ├── Emails/
│   │   │   ├── Core/
│   │   │   │   ├── BookingDataProviderInterface.php
│   │   │   │   ├── BookingQueryHelperAdapter.php
│   │   │   │   ├── EmailFormRenderer.php
│   │   │   │   ├── EmailTemplates.php
│   │   │   │   ├── Mailer.php
│   │   │   │   └── Templates.php
│   │   │   ├── Notifications/
│   │   │   │   ├── BookingNotifications.php
│   │   │   │   ├── RefundNotifications.php
│   │   │   │   └── ReminderScheduler.php
│   │   │   ├── PostTypes/
│   │   │   │   └── EmailLog.php
│   │   │   ├── Settings/
│   │   │   │   ├── EmailTemplateTestAction.php
│   │   │   │   └── EmailTestAction.php
│   │   │   └── Templates/
│   │   │       ├── BookingNotifications.php
│   │   │       ├── EmailPreview.php
│   │   │       ├── OfflinePayment.php
│   │   │       └── RefundEmails.php
│   │   ├── Frontend/
│   │   │   ├── Account/
│   │   │   │   ├── AccountAssets.php
│   │   │   │   ├── AccountController.php
│   │   │   │   ├── AccountRenderer.php
│   │   │   │   └── WooCommerceIntegration.php
│   │   │   ├── Blocks/
│   │   │   │   ├── Base/
│   │   │   │   │   └── GutenbergBlockBase.php
│   │   │   │   └── Gutenberg/
│   │   │   │       ├── BookingFormBlock.php
│   │   │   │       ├── GutenbergIntegration.php
│   │   │   │       ├── VehicleCardBlock.php
│   │   │   │       └── VehiclesListBlock.php
│   │   │   ├── Shortcodes/
│   │   │   │   ├── Core/
│   │   │   │   │   └── AbstractShortcode.php
│   │   │   │   ├── AvailabilityCalendar.php
│   │   │   │   ├── BookingConfirmation.php
│   │   │   │   ├── BookingForm.php
│   │   │   │   ├── ContactForm.php
│   │   │   │   ├── SearchResults.php
│   │   │   │   ├── Testimonials.php
│   │   │   │   ├── VehicleComparison.php
│   │   │   │   ├── VehicleDetails.php
│   │   │   │   ├── VehicleRatingForm.php
│   │   │   │   ├── VehiclesGrid.php
│   │   │   │   └── VehiclesList.php
│   │   │   └── Widgets/
│   │   │       ├── Base/
│   │   │       │   └── ElementorWidgetBase.php
│   │   │       └── Elementor/
│   │   │           ├── AvailabilityCalendarWidget.php
│   │   │           ├── BookingConfirmationWidget.php
│   │   │           ├── BookingFormWidget.php
│   │   │           ├── ContactFormWidget.php
│   │   │           ├── ElementorIntegration.php
│   │   │           ├── LoginFormWidget.php
│   │   │           ├── MyAccountWidget.php
│   │   │           ├── MyBookingsWidget.php
│   │   │           ├── MyFavoritesWidget.php
│   │   │           ├── PaymentHistoryWidget.php
│   │   │           ├── RegisterFormWidget.php
│   │   │           ├── SearchResultsWidget.php
│   │   │           ├── TestimonialsWidget.php
│   │   │           ├── VehicleCardWidget.php
│   │   │           ├── VehicleComparisonWidget.php
│   │   │           ├── VehicleDetailsWidget.php
│   │   │           ├── VehicleRatingWidget.php
│   │   │           ├── VehicleSearchWidget.php
│   │   │           └── VehiclesListWidget.php
│   │   ├── Licensing/
│   │   │   ├── LicenseAdmin.php
│   │   │   ├── LicenseManager.php
│   │   │   ├── Mode.php
│   │   │   └── Restrictions.php
│   │   ├── Messages/
│   │   │   ├── Admin/
│   │   │   │   └── MessageListTable.php
│   │   │   ├── Core/
│   │   │   │   ├── MessageCache.php
│   │   │   │   ├── MessageQueryHelper.php
│   │   │   │   ├── Messages.php
│   │   │   │   └── MessageUrlHelper.php
│   │   │   ├── Frontend/
│   │   │   │   └── CustomerMessages.php
│   │   │   ├── Monitoring/
│   │   │   │   ├── MessageLogger.php
│   │   │   │   ├── MonitoringManager.php
│   │   │   │   └── PerformanceMonitor.php
│   │   │   ├── Notifications/
│   │   │   │   └── MessageNotifications.php
│   │   │   ├── REST/
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── GetMessage.php
│   │   │   │   │   ├── GetMessages.php
│   │   │   │   │   ├── ReplyToMessage.php
│   │   │   │   │   └── UpdateStatus.php
│   │   │   │   ├── Customer/
│   │   │   │   │   ├── CloseMessage.php
│   │   │   │   │   ├── GetBookings.php
│   │   │   │   │   ├── GetMessages.php
│   │   │   │   │   ├── GetThread.php
│   │   │   │   │   ├── SendMessage.php
│   │   │   │   │   └── SendReply.php
│   │   │   │   ├── Helpers/
│   │   │   │   │   ├── Auth.php
│   │   │   │   │   ├── MessageFormatter.php
│   │   │   │   │   └── MessageQuery.php
│   │   │   │   └── Messages.php
│   │   │   ├── Settings/
│   │   │   │   └── MessagesSettings.php
│   │   │   └── Utilities/
│   │   │       └── MessageUtilities.php
│   │   ├── Notifications/
│   │   │   └── NotificationManager.php
│   │   ├── Payment/
│   │   │   ├── Core/
│   │   │   │   ├── PaymentException.php
│   │   │   │   └── PaymentGatewayInterface.php
│   │   │   ├── Gateways/
│   │   │   │   └── Offline/
│   │   │   │       └── API/
│   │   │   ├── Refunds/
│   │   │   │   ├── RefundCalculator.php
│   │   │   │   ├── RefundValidator.php
│   │   │   │   └── Service.php
│   │   │   └── WooCommerce/
│   │   │       └── WooCommerceBridge.php
│   │   ├── PostTypes/
│   │   │   ├── Logs/
│   │   │   │   ├── AdvancedLogger.php
│   │   │   │   ├── MetaBox.php
│   │   │   │   └── PostType.php
│   │   │   ├── Maintenance/
│   │   │   │   ├── AutoCancel.php
│   │   │   │   ├── EmailLogRetention.php
│   │   │   │   └── LogRetention.php
│   │   │   ├── Message/
│   │   │   │   └── Message.php
│   │   │   └── Utilities/
│   │   │       └── ClientUtilities.php
│   │   ├── Privacy/
│   │   │   ├── DataRetentionManager.php
│   │   │   └── GDPRManager.php
│   │   ├── Reports/
│   │   │   ├── BusinessLogic/
│   │   │   │   ├── BookingReport.php
│   │   │   │   ├── CustomerReport.php
│   │   │   │   └── RevenueReport.php
│   │   │   ├── Repository/
│   │   │   │   └── ReportRepository.php
│   │   │   ├── BackgroundProcessor.php
│   │   │   ├── Charts.php
│   │   │   └── Reports.php
│   │   ├── REST/
│   │   │   ├── Helpers/
│   │   │   │   ├── AuthHelper.php
│   │   │   │   ├── SecureToken.php
│   │   │   │   └── ValidationHelper.php
│   │   │   ├── Settings/
│   │   │   │   └── RESTSettings.php
│   │   │   ├── APIKeyManager.php
│   │   │   ├── Availability.php
│   │   │   ├── EndpointListHelper.php
│   │   │   └── ErrorHandler.php
│   │   ├── Security/
│   │   │   └── SecurityManager.php
│   │   ├── Settings/
│   │   │   ├── Comments/
│   │   │   │   └── CommentsSettings.php
│   │   │   ├── Core/
│   │   │   │   ├── RateLimiter.php
│   │   │   │   ├── SettingsCore.php
│   │   │   │   ├── SettingsHelper.php
│   │   │   │   └── SettingsSanitizer.php
│   │   │   ├── Groups/
│   │   │   │   ├── AddonSettings.php
│   │   │   │   ├── BookingSettings.php
│   │   │   │   ├── CommentsSettingsGroup.php
│   │   │   │   ├── CoreSettings.php
│   │   │   │   ├── CustomerManagementSettings.php
│   │   │   │   ├── EmailSettings.php
│   │   │   │   ├── GeneralSettings.php
│   │   │   │   ├── LicenseSettings.php
│   │   │   │   ├── LogsSettings.php
│   │   │   │   ├── MaintenanceSettings.php
│   │   │   │   ├── PaymentSettings.php
│   │   │   │   ├── ReconcileSettings.php
│   │   │   │   ├── SecuritySettings.php
│   │   │   │   ├── VehicleComparisonSettings.php
│   │   │   │   └── VehicleManagementSettings.php
│   │   │   ├── Testing/
│   │   │   │   └── SettingsTester.php
│   │   │   ├── APIKeysPage.php
│   │   │   ├── Settings.php
│   │   │   ├── SettingsHandler.php
│   │   │   ├── SettingsView.php
│   │   │   └── ShortcodePages.php
│   │   ├── Setup/
│   │   │   └── SetupWizard.php
│   │   ├── Testing/
│   │   │   ├── ActivationTest.php
│   │   │   ├── FunctionalTest.php
│   │   │   ├── IntegrationTest.php
│   │   │   ├── PerformanceAnalyzer.php
│   │   │   ├── PerformanceTest.php
│   │   │   ├── SecurityTest.php
│   │   │   ├── ShortcodeTestHandler.php
│   │   │   ├── TestAdminPage.php
│   │   │   └── TestRunner.php
│   │   ├── Transfer/
│   │   │   ├── Engine/
│   │   │   │   └── TransferSearchEngine.php
│   │   │   ├── Frontend/
│   │   │   │   └── TransferShortcodes.php
│   │   │   ├── Integration/
│   │   │   │   ├── TransferBookingHandler.php
│   │   │   │   └── TransferCartIntegration.php
│   │   │   ├── TransferAdmin.php
│   │   │   └── VehicleTransferMetaBox.php
│   │   ├── Utilities/
│   │   │   ├── Actions/
│   │   │   │   └── Actions.php
│   │   │   ├── Cron/
│   │   │   │   ├── CronMonitor.php
│   │   │   │   └── CronMonitorPage.php
│   │   │   ├── Dashboard/
│   │   │   │   └── DashboardPage.php
│   │   │   ├── Database/
│   │   │   │   ├── DatabaseCleanupPage.php
│   │   │   │   ├── DatabaseInitialization.php
│   │   │   │   └── MetaKeysDocumentation.php
│   │   │   ├── Export/
│   │   │   │   ├── Export.php
│   │   │   │   ├── ExportFilters.php
│   │   │   │   ├── ExportHistory.php
│   │   │   │   ├── ExportReports.php
│   │   │   │   └── ExportStats.php
│   │   │   ├── ListTable/
│   │   │   │   ├── CustomersListTable.php
│   │   │   │   └── LogColumns.php
│   │   │   ├── Menu/
│   │   │   │   └── Menu.php
│   │   │   ├── Performance/
│   │   │   │   └── AdminOptimizer.php
│   │   │   └── Uninstall/
│   │   │       ├── Uninstaller.php
│   │   │       └── UninstallPage.php
│   │   └── Vehicle/
│   │       ├── Deposit/
│   │       │   ├── DepositAjax.php
│   │       │   └── DepositCalculator.php
│   │       ├── Frontend/
│   │       │   └── VehicleSearch.php
│   │       ├── Helpers/
│   │       │   ├── VehicleDataHelper.php
│   │       │   └── VehicleFeatureHelper.php
│   │       ├── ListTable/
│   │       │   └── VehicleColumns.php
│   │       ├── Meta/
│   │       │   ├── VehicleGallery.php
│   │       │   └── VehicleMeta.php
│   │       ├── PostType/
│   │       │   └── Vehicle.php
│   │       ├── Reports/
│   │       │   └── VehicleReport.php
│   │       ├── Settings/
│   │       │   ├── VehiclePricingSettings.php
│   │       │   └── VehicleSettings.php
│   │       ├── Taxonomies/
│   │       │   └── VehicleCategory.php
│   │       └── Templates/
│   │           ├── vehicle-gallery.php
│   │           └── vehicle-meta.php
│   └── Plugin.php
└── templates/
    ├── account/
    │   ├── account-details.php
    │   ├── booking-detail.php
    │   ├── bookings.php
    │   ├── dashboard.php
    │   ├── favorites.php
    │   ├── login-form.php
    │   ├── messages.php
    │   ├── navigation.php
    │   ├── payment-history.php
    │   └── register-form.php
    ├── admin/
    │   ├── booking-meta/
    │   │   ├── booking-status.php
    │   │   ├── offline-box.php
    │   │   ├── payment-box.php
    │   │   └── receipt-box.php
    │   └── reports/
    │       ├── bookings.php
    │       ├── customers.php
    │       ├── overview.php
    │       ├── revenue.php
    │       ├── stats-cards.php
    │       └── vehicles.php
    ├── emails/
    │   ├── booking-cancelled.html.php
    │   ├── booking-created-admin.html.php
    │   ├── booking-created-customer.html.php
    │   ├── booking-reminder-customer.html.php
    │   ├── booking-status-changed-admin.html.php
    │   ├── booking-status-changed-customer.html.php
    │   ├── message-received-admin.html.php
    │   ├── message-replied-customer.html.php
    │   ├── offline-receipt-uploaded-admin.html.php
    │   ├── offline-verified-approved-customer.html.php
    │   ├── offline-verified-rejected-customer.html.php
    │   ├── receipt-status-email.html.php
    │   ├── refund-admin.html.php
    │   ├── refund-customer.html.php
    │   └── welcome-customer.html.php
    ├── messages/
    │   ├── admin-message-email.html.php
    │   ├── customer-reply-email.html.php
    │   ├── customer-status-change-email.html.php
    │   ├── message-reply-form.html.php
    │   └── message-thread-view.html.php
    ├── shortcodes/
    │   ├── availability-calendar.php
    │   ├── booking-confirmation.php
    │   ├── booking-form.php
    │   ├── contact-form.php
    │   ├── search-results.php
    │   ├── testimonials.php
    │   ├── thank-you.php
    │   ├── vehicle-comparison.php
    │   ├── vehicle-details.php
    │   ├── vehicle-rating-form.php
    │   ├── vehicle-search-compact.php
    │   ├── vehicle-search.php
    │   ├── vehicles-grid.php
    │   └── vehicles-list.php
    ├── archive-vehicle.php
    └── single-vehicle.php
```
```

---

## 📋 Requirements

### WordPress
- **Minimum Version**: 5.0
- **Tested Up To**: 6.8
- **Multisite**: Supported

### PHP
- **Minimum Version**: 7.4
- **Recommended**: 8.0 or higher
- **Required Extensions**:
  - `json`
  - `curl`
  - `mbstring`
  - `openssl`

### Database
- **MySQL**: 5.7 or higher
- **MariaDB**: 10.3 or higher

### Server
- **HTTPS**: Recommended for payment processing
- **Memory Limit**: 128MB minimum (256MB recommended)
- **Upload Size**: 10MB minimum for receipt uploads

### WordPress Permissions
- `manage_options` - Required for admin settings
- `edit_posts` - Required for booking management
- `upload_files` - Required for vehicle images and receipts

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


## 🔌 REST API Documentation

### Base URL

```
/wp-json/mhm-rentiva/v1
```

### Authentication & Security

The REST API is secured by **API Keys**, **IP Restriction**, and **Rate Limiting**.

1.  **API Keys:** Generate API keys from `Rentiva > Settings > Integration > REST API`.
2.  **Rate Limiting:** Default limit is 60 requests/minute. Exceeding this will result in `429 Too Many Requests`.
3.  **Authentication:** Pass the API key in the `X-API-Key` header.

For full developer documentation, including endpoint details and parameters, see [Developer Docs](https://maxhandmade.github.io/mhm-rentiva-docs/docs/developer/rest-api).

---

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/NewFeature`
3. **Follow coding standards**: WordPress Coding Standards
4. **Write clear commit messages**: Use conventional commits
5. **Test thoroughly**: Test all functionality
6. **Submit pull request**: Include description of changes

### Commit Message Format

```
[Type] Short description

Detailed description (optional)

Fixes #123
```

**Types**: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`

---

## 📝 Changelog
 
### Latest Version: 4.6.7 (2026-02-01)

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
- **AJAX Search**: New shortcode `[mhm_rentiva_transfer_search]` for real-time results.
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
