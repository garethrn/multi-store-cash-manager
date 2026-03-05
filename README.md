# Multi-Store Daily Cash Manager

A comprehensive, production-ready WordPress plugin for multi-store retail cash management. Track daily end-of-day entries, manage sales targets, generate reports, and leverage AI/ML insights across multiple store locations.

---

## Features

- **Daily End-of-Day Module** – Cash denomination breakdown, automatic totals, float management, payment types, payouts, and POS comparison.
- **Multi-Store Management** – Manage unlimited store locations with individual float amounts, targets, timezones, and currencies.
- **User Roles & Permissions** – Custom WordPress roles (Store User, Store Manager) with granular capability controls.
- **Sales Target Tracking** – Monthly/quarterly targets with visual progress bars and projected sales calculations.
- **Reporting Suite** – Daily EOD reports, store performance reports, consolidated multi-store reports, and target progress reports. Export to CSV and print to A4.
- **AI/ML Features** – Anomaly detection for discrepancies and unusual amounts, sales predictions, smart float suggestions, and trend analysis.
- **Dashboard Widgets** – Today's summary statistics, MTD sales tracking, missing entry alerts, and anomaly notifications.
- **Audit Logging** – Complete audit trail of all changes.
- **Email Notifications** – Daily reminders for missing entries and alerts for anomalies.
- **REST API** – External system integration endpoints.

---

## Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher (InnoDB recommended)

---

## Installation

### Via WordPress Admin (Recommended)

1. Download the plugin ZIP file from the [releases page](https://github.com/garethrn/multi-store-cash-manager/releases).
2. In your WordPress admin panel, go to **Plugins → Add New → Upload Plugin**.
3. Click **Choose File**, select the downloaded ZIP file, and click **Install Now**.
4. After installation completes, click **Activate Plugin**.

### Manual Installation

1. Download and unzip the plugin package.
2. Upload the `multi-store-cash-manager` folder to your `/wp-content/plugins/` directory via FTP or file manager.
3. Log in to your WordPress admin panel.
4. Go to **Plugins → Installed Plugins**.
5. Find **Multi-Store Daily Cash Manager** in the list and click **Activate**.

### What Happens on Activation

When you activate the plugin, it automatically:

1. Creates all required database tables (prefixed with your WordPress table prefix, e.g. `wp_mscm_*`).
2. Sets default plugin options.
3. Creates the **Store User** and **Store Manager** custom WordPress roles.
4. Adds MSCM capabilities to the Administrator role.
5. Schedules daily cron jobs for entry reminders (6:00 PM) and automated backups (2:00 AM).

---

## Initial Setup

### 1. Create Your First Store

1. In the WordPress admin, go to **Cash Manager → Stores**.
2. Click **Add New Store**.
3. Fill in the store name, location, address, contact details, and float amount.
4. Click **Save Store**.

### 2. Assign Users to Stores

1. Go to **Cash Manager → Stores** and open a store.
2. Under the **Users** section, assign WordPress users with the appropriate role (**Store User** or **Store Manager**).
3. Users must have the **Store User** or **Store Manager** role, which can be set under **Users → Edit User** in the WordPress admin.

### 3. Configure Settings

Go to **Cash Manager → Settings** to configure:

- **General:** Currency symbol, default float amount, date format, and entries per page.
- **Notifications:** Enable/disable email reminders, reminder time, and notification email address.
- **AI/ML:** Enable anomaly detection and sales predictions, and set the sensitivity threshold.
- **API:** Enable the REST API and manage the API key.
- **Backup:** Enable automated backups and configure frequency and retention period.

### 4. Set Up Frontend Pages

Create WordPress pages with the following shortcodes to give store users frontend access:

| Shortcode | Description |
|-----------|-------------|
| `[mscm_dashboard]` | User dashboard with stats and recent entries |
| `[mscm_end_of_day]` | Daily end-of-day cash entry form |
| `[mscm_targets]` | Sales targets display with progress bars |
| `[mscm_history]` | Entry history table |

---

## Usage

### Submitting a Daily Entry

1. Navigate to the page containing the `[mscm_end_of_day]` shortcode.
2. Select the **date** and your **assigned store**.
3. Enter the **cash denomination breakdown** (R200, R100, R50, R20, R10, R5, R2, R1, 50c).
4. **Total Cash** and **Cash to Bank** are calculated automatically.
5. Enter **Credit Card**, **EFT**, and **Other Digital** payment amounts.
6. Add any **Payouts** or **Purchases/Expenses** as needed.
7. Enter the **POS System Total** for discrepancy calculation.
8. Review the summary and click **Submit Entry**.

### Verifying an Entry (Store Managers)

1. Go to **Cash Manager → Daily Entries** in the WordPress admin.
2. Find a **Pending** entry and click **Verify**.
3. The entry status changes to **Verified**.

### Generating Reports

1. Go to **Cash Manager → Reports**.
2. Select the **Report Type**: EOD, Store Performance, Consolidated, or Target Progress.
3. Choose the **Date Range** and **Store** (if applicable).
4. Click **Generate Report**.
5. Use the **Export CSV** or **Print** buttons to produce a copy.

---

## Automatic Calculations

| Field | Formula |
|-------|---------|
| **Total Cash** | Sum of all denomination quantities × denomination values |
| **Cash to Bank** | Total Cash − Float Amount |
| **Total Sales** | Cash Total + Credit Card + EFT + Other Digital |
| **Discrepancy** | Calculated Sales − POS Reported Sales |
| **Net Banking** | Cash to Bank − Payouts − Purchases |

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
| `mscm_view_all_entries` | View all entries across assigned stores |
| `mscm_edit_entries` | Edit existing entries |
| `mscm_verify_entries` | Mark entries as verified |
| `mscm_view_reports` | Access the reports module |
| `mscm_view_store_users` | View users assigned to stores |
| `mscm_view_targets` | View and manage targets |

### Administrator

Has full access to all plugin features including store management, user assignments, settings, audit logs, and anomaly resolution.

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

---

## Uninstalling

To fully remove the plugin and all its data:

1. Go to **Plugins → Installed Plugins** in the WordPress admin.
2. Click **Deactivate** next to **Multi-Store Daily Cash Manager**.
3. Click **Delete**.

> ⚠️ **Warning:** Deleting the plugin will permanently remove all database tables and plugin data. This action cannot be undone. Back up your data before uninstalling.

---

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](http://www.gnu.org/licenses/gpl-2.0.txt).
