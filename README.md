# Multi-Store Daily Cash Manager

A comprehensive, production-ready WordPress plugin for multi-store retail cash management. Track daily end-of-day entries, manage sales targets, generate reports, and leverage AI/ML insights across multiple store locations.

---

## Features

- **Daily End-of-Day Module** – Cash denomination breakdown, automatic totals, float management, payment types, payouts, and POS comparison.
- **Multi-Store Management** – Manage unlimited store locations with individual float amounts, targets, timezones, and currencies.
- **User Roles & Permissions** – Custom WordPress roles (Store User, Store Manager) with granular capability controls.
- **Sales Target Tracking** – Monthly/quarterly targets with visual progress bars and projected sales calculations.
- **Reporting Suite** – Daily EOD reports, store performance reports, consolidated multi-store reports, and target progress reports. Export to CSV and print to A4.
- **AI/ML Features** – Anomaly detection for discrepancies and unusual amounts, sales predictions, smart float suggestions, trend analysis, and business insights.
- **Dashboard Widgets** – Today's summary statistics, MTD sales tracking, missing entry alerts, and anomaly notifications.
- **Audit Logging** – Complete audit trail of all changes.
- **Email Notifications** – Daily reminders for missing entries and alerts for anomalies.
- **Geolocation Capture** – Track where entries are submitted.
- **Photo Uploads** – Attach receipt images to entries.
- **REST API** – External system integration endpoints.
- **Automated Backups** – Daily database backups with configurable retention.
- **Internationalization Ready** – Full i18n support with text domain `multi-store-cash-manager`.

---

## Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher (InnoDB recommended)

---

## Installation

### Via WordPress Admin (Recommended)

1. Download the plugin ZIP file.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP file and click **Install Now**.
4. After installation, click **Activate Plugin**.

### Manual Installation

1. Upload the `multi-store-cash-manager` folder to `/wp-content/plugins/`.
2. Log in to your WordPress admin panel.
3. Go to **Plugins → Installed Plugins**.
4. Find **Multi-Store Daily Cash Manager** and click **Activate**.

### What Happens on Activation

When you activate the plugin, it automatically:

1. Creates all required database tables.
2. Sets default plugin options.
3. Creates the **Store User** and **Store Manager** custom roles.
4. Adds MSCM capabilities to the Administrator role.
5. Schedules daily cron jobs for reminders (6:00 PM) and backups (2:00 AM).

---

## Initial Setup

### 1. Create Your First Store

1. In the WordPress admin, go to **Cash Manager → Stores**.
2. Click **Add New Store**.
3. Fill in the store name, location, address, contact details, and float amount.
4. Click **Save Store**.

### 2. Assign Users to Stores

1. Go to **Cash Manager → Stores** and open a store.
2. Under the **Users** section, assign WordPress users with the appropriate role (Store User or Store Manager).
3. Users must have the **Store User** or **Store Manager** role (set under **Users → Edit User**).

### 3. Configure Settings

Go to **Cash Manager → Settings** to configure:

- **General:** Currency symbol, default float amount, date format, entries per page.
- **Notifications:** Enable/disable email reminders, set reminder time and notification email address.
- **AI/ML:** Enable anomaly detection and sales predictions, set sensitivity threshold.
- **API:** Enable the REST API and manage the API key.
- **Backup:** Enable automated backups, set frequency and retention period.

### 4. Set Up Shortcode Pages

Create WordPress pages with the following shortcodes for frontend access:

| Shortcode | Description |
|-----------|-------------|
| `[mscm_dashboard]` | User dashboard with stats and recent entries |
| `[mscm_end_of_day]` | Daily end-of-day entry form |
| `[mscm_targets]` | Sales targets display with progress bars |
| `[mscm_history]` | Entry history table |

---

## Usage

### Submitting a Daily Entry

1. Navigate to the page containing `[mscm_end_of_day]`.
2. Select the date and your assigned store.
3. Enter the **cash denomination breakdown** (R200, R100, R50, R20, R10, R5, R2, R1, 50c, 20c, 10c).
4. The **Total Cash** and **Cash to Bank** are calculated automatically.
5. Enter **Credit Card**, **EFT**, and **Other Digital** payment amounts.
6. Add any **Payouts** or **Purchases/Expenses** as needed.
7. Enter the **POS System Total** for discrepancy calculation.
8. Review the summary and click **Submit Entry**.

### Verifying an Entry (Store Managers)

1. Go to **Cash Manager → Daily Entries** in the admin.
2. Find a **Pending** entry and click **Verify**.
3. The entry status changes to **Verified**.

### Generating Reports

1. Go to **Cash Manager → Reports**.
2. Select the **Report Type**: EOD, Store Performance, Consolidated, or Target Progress.
3. Choose the **Date Range** and **Store** (if applicable).
4. Click **Generate Report**.
5. Use the **Export CSV** button or **Print** to produce a hard copy.

### Managing Sales Targets

1. Go to **Cash Manager → Sales Targets**.
2. Click **Add Target** for a store.
3. Set the **Period Type** (monthly or quarterly), the **Period** dates, and the **Target Amount**.
4. Progress bars on the targets page update in real time as entries are submitted.

### Reviewing Anomalies

1. Go to **Cash Manager → Anomalies**.
2. Review detected anomalies with their severity, type, and description.
3. Click **Resolve** once an anomaly has been investigated.

---

## Automatic Calculations

The plugin performs the following calculations automatically:

| Field | Formula |
|-------|---------|
| **Total Cash** | Sum of all denomination quantities × values |
| **Cash to Bank** | Total Cash − Float Amount |
| **Total Sales** | Cash to Bank (after float) + Credit Card + EFT + Other Digital |
| **Discrepancy** | Calculated Sales − POS Reported Sales |
| **Net Banking** | Cash to Bank − Payouts − Purchases |

---

## Database Structure

All tables are prefixed with your WordPress table prefix (default `wp_`).

### `mscm_stores`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint UNSIGNED | Primary key |
| name | varchar(255) | Store name |
| location | varchar(255) | Location |
| address | text | Full address |
| phone | varchar(50) | Phone number |
| email | varchar(100) | Email address |
| manager_id | bigint UNSIGNED | Assigned manager user ID |
| float_amount | decimal(10,2) | Daily float amount |
| target_amount | decimal(10,2) | Monthly sales target |
| currency | varchar(10) | Currency code (default: ZAR) |
| timezone | varchar(50) | Store timezone |
| is_active | tinyint(1) | Active status |
| created_at | datetime | Creation timestamp |
| updated_at | datetime | Last update timestamp |

### `mscm_user_stores`
Manages user–store assignments with role information.

### `mscm_daily_entries`
Stores daily end-of-day entries including all denomination counts, payment type totals, float amount, calculated totals, discrepancy, POS total, status, and submission metadata.

### `mscm_payouts`
Payout records linked to daily entries (amount, category, description).

### `mscm_purchases`
Purchase/expense records linked to daily entries.

### `mscm_targets`
Sales targets by store and period (monthly or quarterly).

### `mscm_settings`
Key–value plugin settings per store.

### `mscm_audit_log`
Complete audit trail: table affected, record ID, action, old/new values, user, IP address, timestamp.

### `mscm_ml_predictions`
Machine learning prediction data: store, prediction type, value, confidence, validity period.

### `mscm_anomalies`
Detected anomalies: store, entry, type, severity, description, resolution status.

### `mscm_notifications`
User notification records: type, message, read status, link.

---

## REST API

The REST API is available at `/wp-json/mscm/v1/` when enabled in **Settings → API**.

### Authentication

Include your API key in the request header:

```
X-MSCM-API-Key: your-api-key-here
```

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/mscm/v1/stores` | List all stores |
| GET | `/wp-json/mscm/v1/stores/{id}` | Get a single store |
| GET | `/wp-json/mscm/v1/entries` | List daily entries |
| GET | `/wp-json/mscm/v1/entries/{id}` | Get a single entry |
| GET | `/wp-json/mscm/v1/targets` | List sales targets |
| GET | `/wp-json/mscm/v1/reports/summary` | Get a store summary report |

### Example Request

```bash
curl -H "X-MSCM-API-Key: your-key" \
     https://yoursite.com/wp-json/mscm/v1/stores
```

---

## AJAX Actions

The following AJAX actions are registered for authenticated users:

| Action | Description |
|--------|-------------|
| `mscm_save_daily_entry` | Save a new daily entry |
| `mscm_get_store_data` | Get store details |
| `mscm_get_dashboard_stats` | Get dashboard statistics |
| `mscm_save_store` | Create or update a store |
| `mscm_delete_store` | Delete a store |
| `mscm_verify_entry` | Verify an entry |
| `mscm_generate_report` | Generate a report |
| `mscm_export_data` | Export data as CSV |
| `mscm_save_target` | Create or update a sales target |
| `mscm_get_ml_insights` | Get AI/ML insights |
| `mscm_assign_user_store` | Assign a user to a store |
| `mscm_remove_user_store` | Remove a user from a store |
| `mscm_resolve_anomaly` | Resolve an anomaly |
| `mscm_get_entry` | Get entry details |
| `mscm_save_settings` | Save plugin settings |
| `mscm_get_notifications` | Get user notifications |
| `mscm_mark_notification_read` | Mark a notification as read |

---

## Plugin Constants

| Constant | Description |
|----------|-------------|
| `MSCM_VERSION` | Plugin version (`1.0.0`) |
| `MSCM_PLUGIN_DIR` | Absolute path to the plugin directory |
| `MSCM_PLUGIN_URL` | URL to the plugin directory |
| `MSCM_PLUGIN_BASENAME` | Plugin basename |
| `MSCM_DB_VERSION` | Database schema version |

---

## User Roles & Capabilities

### Store User (`store_user`)

| Capability | Description |
|-----------|-------------|
| `mscm_submit_entry` | Submit daily entries |
| `mscm_view_own_entries` | View own submitted entries |
| `mscm_view_own_store` | View assigned store details |
| `mscm_view_targets` | View sales targets |
| `mscm_view_dashboard` | Access the user dashboard |

### Store Manager (`store_manager`)

Includes all Store User capabilities, plus:

| Capability | Description |
|-----------|-------------|
| `mscm_verify_entry` | Verify pending entries |
| `mscm_view_store_entries` | View all entries for managed store |
| `mscm_manage_store` | Edit store settings |
| `mscm_manage_users` | Assign users to managed store |
| `mscm_set_targets` | Create and update targets |
| `mscm_view_reports` | View store reports |
| `mscm_export_reports` | Export reports to CSV |
| `mscm_view_anomalies` | View detected anomalies |

### Administrator

Has all Store Manager capabilities, plus:

| Capability | Description |
|-----------|-------------|
| `mscm_manage_all_stores` | Full access to all stores |
| `mscm_manage_users` | Assign users to stores |
| `mscm_manage_settings` | Change plugin settings |
| `mscm_view_all_reports` | View all store reports |
| `mscm_manage_api` | Manage REST API settings |
| `mscm_delete_entries` | Delete entries |
| `mscm_manage_targets` | Manage all targets |
| `mscm_view_audit_log` | View the audit log |
| `mscm_manage_backups` | Manage database backups |
| `mscm_resolve_anomalies` | Resolve detected anomalies |

---

## Colour Scheme

The plugin uses a modern, professional colour palette:

| Name | Hex | Usage |
|------|-----|-------|
| Primary | `#2563eb` | Buttons, links, active states |
| Success | `#10b981` | Positive values, verified badges |
| Warning | `#f59e0b` | Pending states, caution indicators |
| Danger | `#ef4444` | Errors, negative discrepancies, delete actions |
| Info | `#06b6d4` | Informational badges and alerts |
| Dark | `#1f2937` | Headings and body text |
| Gray | `#6b7280` | Muted text, borders |
| Light | `#f9fafb` | Backgrounds, cards |

---

## Troubleshooting

### Database tables were not created

- Deactivate and reactivate the plugin. This re-runs the `mscm_activate` function.
- Ensure your database user has `CREATE TABLE` privileges.
- Check your PHP error log for database errors.

### Custom roles are not available

- Deactivate and reactivate the plugin to re-register roles.
- Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

### AJAX requests failing (spinning loader or error messages)

- Check the browser console for JavaScript errors.
- Verify that the `mscm_public_nonce` or `mscm_admin_nonce` is being passed correctly.
- Confirm that the user is logged in with the required capability.
- Check for conflicts with caching plugins that may serve stale nonces.

### Emails not being sent

- Go to **Cash Manager → Settings → Notifications** and confirm the notification email address is set correctly.
- Install a plugin like [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) to configure your mail server.
- Check your server's PHP mail configuration or `wp_mail` debug logs.

### Reports returning no data

- Check that entries exist for the selected date range and store.
- Verify the user has the `mscm_view_reports` capability.
- Ensure the store is marked as **Active**.

### Cron jobs not running

- WordPress cron relies on site traffic. On low-traffic sites, install [WP-Crontrol](https://wordpress.org/plugins/wp-crontrol/) to verify and manually trigger cron jobs.
- Optionally, set up a server-level cron to call `wp-cron.php` on a schedule:
  ```
  0 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
  ```

---

## Uninstalling

To completely remove the plugin and all its data:

1. Go to **Plugins → Installed Plugins**.
2. Deactivate **Multi-Store Daily Cash Manager**.
3. Click **Delete**.

This triggers `uninstall.php`, which:

- Drops all plugin database tables.
- Deletes all plugin options.
- Removes custom user roles.
- Clears scheduled cron jobs.
- Removes uploaded files and backup directories.
- Cleans up user meta and transients.

> ⚠️ **Warning:** This action is irreversible. Back up your database before uninstalling.

---

## File Structure

```
multi-store-cash-manager/
├── multi-store-cash-manager.php   # Main plugin file
├── uninstall.php                  # Cleanup on deletion
├── README.md                      # This file
│
├── includes/
│   ├── class-db.php               # Database operations & table creation
│   ├── class-roles.php            # Custom roles & capabilities
│   ├── class-ajax.php             # All AJAX handlers
│   ├── class-reports.php          # Report generation
│   ├── class-ml.php               # AI/ML features
│   ├── class-api.php              # REST API endpoints
│   ├── class-notifications.php    # Email notifications
│   ├── class-export.php           # CSV export & backups
│   └── class-pdf-generator.php    # PDF generation
│
├── admin/
│   ├── admin-menu.php             # Admin menu registration
│   ├── admin-dashboard.php        # Admin dashboard
│   ├── admin-stores.php           # Store management
│   ├── admin-reports.php          # Reports interface
│   ├── admin-targets.php          # Targets interface
│   └── admin-settings.php         # Settings page
│
├── public/
│   ├── public-dashboard.php       # [mscm_dashboard] shortcode
│   ├── public-end-of-day.php      # [mscm_end_of_day] shortcode
│   ├── public-targets.php         # [mscm_targets] shortcode
│   └── public-history.php         # [mscm_history] shortcode
│
└── assets/
    ├── css/
    │   ├── admin-style.css        # Admin styles
    │   └── public-style.css       # Frontend styles
    └── js/
        ├── admin-script.js        # Admin JavaScript
        └── public-script.js       # Frontend JavaScript
```

---

## Changelog

### 1.0.0 – Initial Release

- Complete multi-store daily cash management plugin.
- Daily end-of-day entry form with denomination breakdown.
- Automatic calculation of totals, discrepancies, and net banking.
- Custom WordPress roles: Store User and Store Manager.
- Sales target tracking with progress visualization.
- Reporting suite: EOD, Store Performance, Consolidated, Target Progress.
- CSV export for all report types.
- AI/ML anomaly detection and sales prediction engine.
- Admin dashboard with statistics and Chart.js charts.
- REST API with API key authentication.
- Email notifications and daily reminders via WordPress cron.
- Geolocation capture on entry submission.
- Audit logging for all data changes.
- Automated database backups.
- Full internationalization support.

---

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](http://www.gnu.org/licenses/gpl-2.0.txt).

---

## Support

For support, feature requests, or bug reports, please open an issue in the project repository.
