# MHM Rentiva - WordPress Vehicle Rental Plugin

![Version](https://img.shields.io/badge/version-4.3.8-blue.svg)
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
- **Payment Processing**: Multiple payment gateways (Stripe, PayPal, PayTR, Offline) with receipt management
- **Customer Portal**: Full-featured customer account system with booking history, favorites, and messaging
- **Analytics & Reporting**: Comprehensive analytics dashboard with revenue, customer, and vehicle insights
- **Email System**: Automated email notifications with customizable HTML templates
- **Messaging System**: Built-in customer support messaging with thread management
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
  - Daily, weekly, monthly pricing
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

**Supported Payment Gateways:**

1. **Stripe** (Credit/Debit Cards)
   - Supported currencies: USD, EUR, GBP, TRY
   - Payment Intents API
   - Refund support
   - Webhook integration

2. **PayPal** (PayPal & Credit Cards)
   - Supported currencies: USD, EUR, TRY, GBP, CAD, AUD
   - PayPal Orders API
   - Refund support
   - Sandbox/Live mode

3. **PayTR** (Turkish Payment Gateway)
   - Local payment system for Turkey
   - Credit card and bank transfer support
   - Turkish Lira (TRY) support

4. **Offline Payment**
   - Bank transfer, cash, or other offline methods
   - Receipt upload system
   - Admin approval workflow
   - Automatic booking cancellation if not approved within deadline

**Payment Features:**
- Multiple payment methods per booking
- Partial payments support
- Deposit system (fixed or percentage)
- Refund management
- Payment status tracking
- Receipt verification system
- Payment history for customers
- Email notifications for payment events

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
- `[rentiva_my_account]` - Main account dashboard
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

**Report Features:**
- Real-time data updates
- Custom date range selection
- Export to Excel/CSV
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
- **Payment Gateway Integration**: Currency support per payment gateway

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

**Available Widgets:**
- **Vehicle Card Widget**: Single vehicle display
- **Vehicles List Widget**: List of vehicles
- **Booking Form Widget**: Booking form integration

**Widget Features:**
- Native Elementor integration
- Custom widget category
- Live preview
- Drag & drop builder
- Responsive controls

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

**Payment Gateway Restrictions:**
- ✅ **Offline Payment**: Available (bank transfer, cash, etc.)
- ✅ **PayPal**: Available
- ❌ **Stripe**: Not available (Pro only)
- ❌ **PayTR**: Not available (Pro only)

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
- ✅ **Offline Payment**: Available
- ✅ **PayPal**: Available
- ✅ **Stripe**: Available (credit/debit cards)
- ✅ **PayTR**: Available (Turkish payment gateway)

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
| **Payment Gateways** | Offline + PayPal | All (Stripe, PayPal, PayTR, Offline) |
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
- All payment gateways available
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
- **System Status**: Plugin health indicators

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
- My Account page (use `[rentiva_my_account]` shortcode)
- Booking Form page (use `[rentiva_booking_form]` shortcode)
- Vehicles List/Grid page (use `[rentiva_vehicles_grid]` or `[rentiva_vehicles_list]`)

**Optional Pages:**
- Search page (use `[rentiva_search]` shortcode)
- Contact page (use `[rentiva_contact]` shortcode)
- Login page (use `[rentiva_login_form]` shortcode)
- Registration page (use `[rentiva_register_form]` shortcode)

### Step 4: Configure Payment Gateways

1. Go to **Rentiva > Settings > Payment**
2. Configure your payment gateways:
   - **Stripe**: Enter API keys (Test/Live mode)
   - **PayPal**: Enter Client ID and Secret
   - **PayTR**: Enter Merchant ID, Key, and Salt
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

**Location**: `Rentiva > Settings > Payment`

**Stripe Settings:**
- Test/Live mode toggle
- Publishable key
- Secret key
- Webhook secret
- Webhook URL (auto-generated)

**PayPal Settings:**
- Sandbox/Live mode toggle
- Client ID
- Client Secret
- Supported currencies

**PayTR Settings:**
- Merchant ID
- Merchant Key
- Merchant Salt
- Test/Live mode

**Offline Payment Settings:**
- Enable receipt upload
- Receipt approval deadline
- Auto-cancel if not approved

### Email Settings

**Location**: `Rentiva > Settings > Email`

- **From Name**: Email sender name
- **From Email**: Email sender address
- **Reply To**: Reply-to address
- **Template Customization**: Override email subjects and content
- **Email Templates**: 19 customizable HTML email templates

### Frontend & Display Settings

**Location**: `Rentiva > Settings > Frontend & Display`

- **URL Settings**: 40+ frontend URL settings
- **Text Settings**: Customizable frontend text
- **Display Options**: Show/hide various elements
- **Comments Settings**: Enable/disable vehicle comments

### Integration Settings

**Location**: `Rentiva > Settings > Integration`

- **REST API Settings**:
  - Enable/disable REST API
  - Rate limiting
  - API key management
  - Endpoint list
  - Security settings (HTTPS enforcement, IP whitelist/blacklist)

---

## 📖 Usage Guide

### For Administrators

#### Adding a Vehicle

1. Go to **Vehicles > Add New**
2. Enter vehicle title and description
3. Upload images (up to 10) using WordPress Media Library
4. Set pricing (daily, weekly, monthly)
5. Add vehicle specifications
6. Configure features and equipment
7. Set deposit (fixed amount or percentage)
8. Publish

#### Creating a Manual Booking

1. Go to **Bookings > Add New**
2. Select customer (or create new)
3. Select vehicle
4. Set pickup and return dates
5. Add addon services (optional)
6. Set payment status
7. Publish

#### Managing Bookings

1. Go to **Bookings** to see all bookings
2. Use filters to find specific bookings
3. Click on booking to edit
4. Change status, add notes, process refunds
5. View booking calendar for visual overview

#### Viewing Reports

1. Go to **Rentiva > Reports**
2. Select report type:
   - Revenue Analytics
   - Booking Analytics
   - Vehicle Analytics
   - Customer Analytics
3. Set date range
4. Export data if needed

#### Managing Customers

1. Go to **Rentiva > Customers**
2. View customer list with statistics
3. Click customer to view details
4. View booking history
5. Send messages

#### Managing Messages

1. Go to **Rentiva > Messages**
2. View all customer messages
3. Click message to view thread
4. Reply to customer
5. Change status or priority

### For Customers

#### Creating an Account

1. Visit registration page (with `[rentiva_register_form]` shortcode)
2. Fill in registration form
3. Account created automatically
4. Receive welcome email

#### Making a Booking

1. Browse vehicles (grid or list view)
2. Click on vehicle to view details
3. Click "Book Now" or visit booking form
4. Select pickup and return dates
5. Add addon services (optional)
6. Fill in contact information
7. Review booking summary
8. Complete payment
9. Receive confirmation email

#### Managing Bookings

1. Log in to account
2. Go to "My Bookings"
3. View booking history
4. View booking details
5. Cancel booking (if allowed)
6. Upload receipt (for offline payments)

#### Viewing Account

1. Log in to account
2. Dashboard shows:
   - Recent bookings
   - Favorite vehicles
   - Account statistics
3. Navigate to:
   - My Bookings
   - My Favorites
   - Payment History
   - Account Details
   - Messages

---

## 🎯 Shortcodes Reference

### Account Management Shortcodes

#### `[rentiva_my_account]`
**Purpose**: Main customer account dashboard

**Usage**:
```php
[rentiva_my_account]
```

**Features**:
- Account overview
- Recent bookings
- Quick links to all account sections
- Account statistics

---

#### `[rentiva_my_bookings]`
**Purpose**: Display customer booking history

**Usage**:
```php
[rentiva_my_bookings]
```

**Features**:
- List of all customer bookings
- Filter by status
- Booking details
- Cancel booking option
- Receipt upload for offline payments

---

#### `[rentiva_my_favorites]`
**Purpose**: Display customer favorite vehicles

**Usage**:
```php
[rentiva_my_favorites]
```

**Features**:
- Grid/list of favorite vehicles
- Remove from favorites
- Quick booking from favorites

---

#### `[rentiva_payment_history]`
**Purpose**: Display payment transaction history

**Usage**:
```php
[rentiva_payment_history]
```

**Features**:
- List of all payments
- Payment status
- Receipts
- Refund information

---

#### `[rentiva_account_details]`
**Purpose**: Account information editing page

**Usage**:
```php
[rentiva_account_details]
```

**Features**:
- Edit profile information
- Change password
- Update contact details

---

#### `[rentiva_login_form]`
**Purpose**: Customer login form

**Usage**:
```php
[rentiva_login_form]
```

**Features**:
- WordPress standard login
- Remember me option
- Password reset link
- Registration link

---

#### `[rentiva_register_form]`
**Purpose**: New customer registration form

**Usage**:
```php
[rentiva_register_form]
```

**Features**:
- First name, last name
- Email address
- Password
- Username auto-generated
- Email verification

---

### Booking Shortcodes

#### `[rentiva_booking_form]`
**Purpose**: Main booking form for vehicle rental

**Usage**:
```php
[rentiva_booking_form vehicle_id="123"]
```

**Parameters**:
- `vehicle_id` (optional): Pre-select vehicle by ID

**Features**:
- Date range selection
- Vehicle selection
- Addon services
- Customer information form
- Price calculation
- Payment integration
- Terms and conditions

---

#### `[rentiva_availability_calendar]`
**Purpose**: Display vehicle availability calendar

**Usage**:
```php
[rentiva_availability_calendar vehicle_id="123"]
```

**Parameters**:
- `vehicle_id` (required): Vehicle ID to display

**Features**:
- Monthly calendar view
- Available/unavailable dates
- Booked dates
- Price display
- Click to book functionality

---

#### `[rentiva_booking_confirmation]`
**Purpose**: Booking confirmation page

**Usage**:
```php
[rentiva_booking_confirmation booking_id="456"]
```

**Parameters**:
- `booking_id` (optional): Booking ID to display

**Features**:
- Booking details summary
- Payment status
- Next steps
- Contact information

---

### Vehicle Display Shortcodes

#### `[rentiva_vehicle_details]`
**Purpose**: Single vehicle detail page

**Usage**:
```php
[rentiva_vehicle_details vehicle_id="123"]
```

**Parameters**:
- `vehicle_id` (required): Vehicle ID to display

**Features**:
- Vehicle images gallery
- Full specifications
- Pricing information
- Features list
- Booking form integration
- Availability calendar
- Related vehicles

---

#### `[rentiva_vehicles_grid]`
**Purpose**: Display vehicles in grid layout

**Usage**:
```php
[rentiva_vehicles_grid columns="3" limit="12" category="economy"]
```

**Parameters**:
- `columns` (optional): Number of columns (default: 3)
- `limit` (optional): Number of vehicles (default: -1 for all)
- `category` (optional): Filter by category slug
- `featured` (optional): Show only featured vehicles (true/false)

**Features**:
- Responsive grid layout
- Vehicle cards with images
- Quick view
- Add to favorites
- Filter by category

---

#### `[rentiva_vehicles_list]`
**Purpose**: Display vehicles in list layout

**Usage**:
```php
[rentiva_vehicles_list limit="10" category="luxury"]
```

**Parameters**:
- `limit` (optional): Number of vehicles (default: -1 for all)
- `category` (optional): Filter by category slug
- `featured` (optional): Show only featured vehicles (true/false)

**Features**:
- List layout with details
- Vehicle images
- Specifications summary
- Price display
- Quick booking

---

#### `[rentiva_vehicle_comparison]`
**Purpose**: Compare multiple vehicles side-by-side

**Usage**:
```php
[rentiva_vehicle_comparison vehicle_ids="123,456,789"]
```

**Parameters**:
- `vehicle_ids` (required): Comma-separated vehicle IDs

**Features**:
- Side-by-side comparison
- Specifications comparison
- Price comparison
- Features comparison
- Add/remove vehicles

---

#### `[rentiva_search]`
**Purpose**: Vehicle search form

**Usage**:
```php
[rentiva_search]
```

**Features**:
- Search by keyword
- Filter by category
- Filter by price range
- Filter by features
- Date availability filter
- Search results integration

---

#### `[rentiva_search_results]`
**Purpose**: Display search results with filters

**Usage**:
```php
[rentiva_search_results]
```

**Features**:
- Search results display
- Sidebar filters
- Sort options
- Pagination
- Grid/list view toggle

---

### Support Shortcodes

#### `[rentiva_contact]`
**Purpose**: Contact form for customer inquiries

**Usage**:
```php
[rentiva_contact type="general"]
```

**Parameters**:
- `type` (optional): Message type (general, booking, payment, technical)

**Features**:
- Contact form
- Message categories
- File attachments
- Email notifications

---

#### `[rentiva_testimonials]`
**Purpose**: Display customer testimonials/reviews

**Usage**:
```php
[rentiva_testimonials limit="6"]
```

**Parameters**:
- `limit` (optional): Number of testimonials (default: 6)

**Features**:
- Customer reviews
- Star ratings
- Vehicle ratings
- Filter by vehicle

---

#### `[rentiva_vehicle_rating_form]`
**Purpose**: Form for customers to rate vehicles

**Usage**:
```php
[rentiva_vehicle_rating_form vehicle_id="123"]
```

**Parameters**:
- `vehicle_id` (required): Vehicle ID to rate

**Features**:
- Star rating
- Review text
- Photo upload
- Only for customers who booked

---

## 🔌 REST API Documentation

### Base URL

```
/wp-json/mhm-rentiva/v1
```

### Authentication

REST API requires authentication via API keys. Generate API keys from:
**Rentiva > Settings > Integration > REST API > API Keys**

### Available Endpoints

#### Availability

**Check Vehicle Availability**
```
GET /availability
```

**Parameters**:
- `vehicle_id` (required): Vehicle ID
- `start_date` (required): Start date (YYYY-MM-DD)
- `end_date` (required): End date (YYYY-MM-DD)

**Response**:
```json
{
  "available": true,
  "message": "Vehicle is available"
}
```

#### Bookings

**Create Booking**
```
POST /bookings
```

**Create Booking (Customer)**
```
POST /bookings/customer
```

**Get Booking**
```
GET /bookings/{id}
```

**Update Booking**
```
PUT /bookings/{id}
```

**Cancel Booking**
```
DELETE /bookings/{id}
```

#### Payments

**Stripe Payment Intent**
```
POST /payments/stripe/intent
```

**PayPal Order**
```
POST /payments/paypal/order
```

**PayTR Token**
```
POST /payments/paytr/token
```

**Process Payment**
```
POST /payments/process
```

**Refund Payment**
```
POST /payments/refund
```

#### Messages

**Get Messages**
```
GET /messages
```

**Get Message Thread**
```
GET /messages/{id}
```

**Send Reply (Customer)**
```
POST /messages/customer/reply
```

**Send Reply (Admin)**
```
POST /messages/admin/reply
```

**Create Message (Customer)**
```
POST /messages/customer/create
```

### Rate Limiting

Default rate limit: **100 requests per minute** (configurable)

### API Key Management

**Location**: `Rentiva > Settings > Integration > REST API > API Keys`

- Create new API keys
- Revoke existing keys
- View API key usage
- Set permissions per key

---

## 💳 Payment Gateways

### Stripe

**Setup**:
1. Get API keys from Stripe Dashboard
2. Go to `Rentiva > Settings > Payment > Stripe`
3. Enter Publishable Key and Secret Key
4. Set Test/Live mode
5. Configure webhook URL in Stripe Dashboard

**Supported Currencies**: USD, EUR, GBP, TRY

**Features**:
- Payment Intents API
- 3D Secure support
- Refund support
- Webhook integration

### PayPal

**Setup**:
1. Create PayPal App in PayPal Developer Dashboard
2. Get Client ID and Secret
3. Go to `Rentiva > Settings > Payment > PayPal`
4. Enter credentials
5. Set Sandbox/Live mode

**Supported Currencies**: USD, EUR, TRY, GBP, CAD, AUD

**Features**:
- PayPal Orders API
- Credit card support
- Refund support
- Sandbox testing

### PayTR

**Setup**:
1. Get credentials from PayTR
2. Go to `Rentiva > Settings > Payment > PayTR`
3. Enter Merchant ID, Key, and Salt
4. Set Test/Live mode

**Features**:
- Turkish payment gateway
- Credit card support
- Bank transfer support
- TRY currency

### Offline Payment

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

## 📁 Project Structure

```
mhm-rentiva/
│
├── assets/                          # Frontend assets
│   ├── css/
│   │   ├── admin/                  # Admin panel styles (27 files)
│   │   ├── components/             # Component styles (5 files)
│   │   ├── core/                   # Core styles (4 files)
│   │   ├── frontend/                # Frontend styles (24 files)
│   │   └── payment/                # Payment styles (1 file)
│   ├── images/                     # Images
│   └── js/
│       ├── admin/                  # Admin JavaScript (27 files)
│       ├── components/             # Component JavaScript (3 files)
│       ├── core/                   # Core JavaScript (7 files)
│       ├── frontend/               # Frontend JavaScript (14 files)
│       ├── payment/                # Payment JavaScript (1 file)
│       └── vendor/                 # Third-party libraries
│
├── languages/                       # Translation files
│   ├── mhm-rentiva-en_US.*
│   ├── mhm-rentiva-tr_TR.*
│   └── mhm-rentiva.pot
│
├── src/                            # PHP source code
│   ├── Admin/
│   │   ├── About/                  # About page (6 files)
│   │   ├── Actions/               # Admin actions (1 file)
│   │   ├── Addons/                 # Addon system (6 files)
│   │   ├── Auth/                  # Authentication (3 files)
│   │   ├── Booking/               # Booking system
│   │   │   ├── Actions/           # Booking actions (1 file)
│   │   │   ├── Addons/            # Addon integration (1 file)
│   │   │   ├── Core/              # Booking core (3 files)
│   │   │   ├── Exceptions/        # Booking exceptions (1 file)
│   │   │   ├── Helpers/           # Booking helpers (4 files)
│   │   │   ├── ListTable/         # Booking list table (1 file)
│   │   │   ├── Meta/              # Booking meta boxes (6 files)
│   │   │   └── PostType/          # Booking post type (1 file)
│   │   ├── CLI/                   # WP-CLI commands (1 file)
│   │   ├── Core/                  # Core utilities
│   │   │   ├── CurrencyHelper.php # Currency management
│   │   │   ├── LanguageHelper.php # Language management
│   │   │   ├── AssetManager.php   # Asset management
│   │   │   ├── Exceptions/        # Exception classes (2 files)
│   │   │   ├── MetaBoxes/         # Meta box system (1 file)
│   │   │   ├── PostTypes/        # Post type system (1 file)
│   │   │   ├── ShortcodeServiceProvider.php
│   │   │   ├── ShortcodeUrlManager.php
│   │   │   ├── Tabs/              # Tab system (1 file)
│   │   │   ├── Traits/            # Common traits (1 file)
│   │   │   └── Utilities/         # Utility classes (20+ files)
│   │   ├── Customers/             # Customer management (4 files)
│   │   ├── Emails/                # Email system
│   │   │   ├── Core/              # Email core (4 files)
│   │   │   ├── Notifications/     # Email notifications (4 files)
│   │   │   ├── PostTypes/        # Email log (1 file)
│   │   │   ├── Settings/         # Email settings (2 files)
│   │   │   └── Templates/       # Email templates (4 files)
│   │   ├── Frontend/             # Frontend system
│   │   │   ├── Account/           # Account system (4 files)
│   │   │   ├── Blocks/           # Gutenberg blocks
│   │   │   │   ├── Base/         # Base block (1 file)
│   │   │   │   └── Gutenberg/    # Gutenberg blocks (4 files)
│   │   │   ├── Shortcodes/       # Shortcode system (12 files)
│   │   │   └── Widgets/         # Elementor widgets (5 files)
│   │   ├── Licensing/            # License system (4 files)
│   │   ├── Messages/             # Messaging system
│   │   │   ├── Admin/           # Admin messages (1 file)
│   │   │   ├── Core/            # Message core (4 files)
│   │   │   ├── Frontend/        # Frontend messages (1 file)
│   │   │   ├── Monitoring/      # Message monitoring (3 files)
│   │   │   ├── Notifications/   # Message notifications (1 file)
│   │   │   ├── REST/            # Message REST API (14 files)
│   │   │   ├── Settings/        # Message settings (1 file)
│   │   │   └── Utilities/       # Message utilities (1 file)
│   │   ├── Notifications/       # Notification system (1 file)
│   │   ├── Payment/             # Payment system
│   │   │   ├── Core/            # Payment core (2 files)
│   │   │   ├── Gateways/        # Payment gateways
│   │   │   │   ├── Offline/     # Offline payment (3 files)
│   │   │   │   ├── PayPal/      # PayPal gateway (6 files)
│   │   │   │   ├── PayTR/       # PayTR gateway (6 files)
│   │   │   │   └── Stripe/      # Stripe gateway (4 files)
│   │   │   ├── Refunds/         # Refund system (6 files)
│   │   │   ├── REST/            # Payment REST API (27 files)
│   │   │   └── Settings/        # Payment settings (4 files)
│   │   ├── PostTypes/           # Custom post types
│   │   │   ├── Logs/            # Log system (3 files)
│   │   │   ├── Maintenance/     # Maintenance (4 files)
│   │   │   ├── Message/         # Message post type (1 file)
│   │   │   └── Utilities/       # Post type utilities (1 file)
│   │   ├── Privacy/             # Privacy/GDPR (2 files)
│   │   ├── Reports/             # Reporting system
│   │   │   ├── BusinessLogic/   # Report logic (3 files)
│   │   │   ├── Charts.php
│   │   │   ├── Reports.php
│   │   │   └── Settings/        # Report settings
│   │   ├── REST/                # REST API
│   │   │   ├── APIKeyManager.php
│   │   │   ├── Availability.php
│   │   │   ├── EndpointListHelper.php
│   │   │   ├── ErrorHandler.php
│   │   │   ├── Helpers/         # REST helpers (3 files)
│   │   │   └── Settings/        # REST settings (1 file)
│   │   ├── Security/            # Security system (1 file)
│   │   ├── Settings/             # Settings system
│   │   │   ├── APIKeysPage.php
│   │   │   ├── Comments/        # Comment settings (1 file)
│   │   │   ├── Core/            # Settings core (4 files)
│   │   │   ├── Groups/          # Setting groups (14 files)
│   │   │   ├── Settings.php
│   │   │   ├── ShortcodePages.php
│   │   │   ├── ShortcodeSettings.php
│   │   │   └── Testing/         # Settings testing (1 file)
│   │   ├── Testing/             # Testing system (8 files)
│   │   ├── Utilities/            # Admin utilities
│   │   │   ├── Actions/         # Admin actions (1 file)
│   │   │   ├── Cron/            # Cron jobs (2 files)
│   │   │   ├── Dashboard/       # Dashboard (1 file)
│   │   │   ├── Database/         # Database utilities (4 files)
│   │   │   ├── Export/          # Export utilities (5 files)
│   │   │   ├── ListTable/       # List table utilities (2 files)
│   │   │   ├── Menu/            # Menu system (1 file)
│   │   │   ├── Performance/     # Performance (1 file)
│   │   │   └── Uninstall/       # Uninstall (2 files)
│   │   └── Vehicle/             # Vehicle management
│   │       ├── Deposit/          # Deposit system (2 files)
│   │       ├── Frontend/         # Frontend vehicle (1 file)
│   │       ├── ListTable/       # Vehicle list table (1 file)
│   │       ├── Meta/             # Vehicle meta boxes (2 files)
│   │       ├── PostType/         # Vehicle post type (1 file)
│   │       ├── Reports/          # Vehicle reports (1 file)
│   │       ├── Settings/         # Vehicle settings (2 files)
│   │       ├── Taxonomies/       # Vehicle categories (1 file)
│   │       └── Templates/       # Vehicle templates (2 files)
│   └── Plugin.php                # Main plugin class
│
├── templates/                     # Template files
│   ├── account/                   # Account templates (10 files)
│   ├── admin/                    # Admin templates
│   │   └── booking-meta/         # Booking meta templates (4 files)
│   ├── emails/                   # Email templates (13 files)
│   ├── messages/                 # Message templates (5 files)
│   ├── shortcodes/               # Shortcode templates (13 files)
│   ├── archive-vehicle.php      # Vehicle archive
│   └── single-vehicle.php        # Single vehicle page
│
├── changelog.json                 # Change history
├── mhm-rentiva.php               # Main plugin file
├── mhm_dev_suite.yaml            # Development config
└── README.md                     # This file
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

### Latest Version: 4.3.8 (2025-01-28)

**🌍 CENTRALIZED CURRENCY & LANGUAGE SYSTEM:**
- CurrencyHelper class for unified currency management (47 currencies)
- LanguageHelper class for unified language management (60+ languages)
- All hardcoded lists replaced with centralized helpers
- Settings unification across all files

**💬 MESSAGES SYSTEM REFACTORING:**
- MessageUrlHelper for centralized URL management
- Fixed message cache and content display issues
- Enhanced thread display and reply functionality
- Complete i18n support

For complete changelog, see [changelog.json](changelog.json)

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
