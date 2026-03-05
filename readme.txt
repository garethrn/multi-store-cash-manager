=== Multi-Store Daily Cash Manager ===
Contributors: garethrn
Tags: cash management, retail, multi-store, end-of-day, reporting
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive multi-store retail cash management plugin with daily end-of-day entries, sales targets, reporting, and AI/ML features.

== Description ==

Multi-Store Daily Cash Manager is a production-ready WordPress plugin for multi-store retail businesses. It enables store staff to submit daily end-of-day cash counts, tracks sales targets, and provides managers with rich reporting across all locations.

= Key Features =

* **Daily End-of-Day Module** – Cash denomination breakdown, automatic totals, float management, payment types, payouts, and POS comparison.
* **Multi-Store Management** – Manage unlimited store locations with individual float amounts, targets, timezones, and currencies.
* **User Roles & Permissions** – Custom WordPress roles (Store User, Store Manager) with granular capability controls.
* **Sales Target Tracking** – Monthly/quarterly targets with visual progress bars.
* **Reporting Suite** – Daily EOD reports, store performance, consolidated reports. Export to CSV.
* **AI/ML Features** – Anomaly detection, sales predictions, smart float suggestions, and trend analysis.
* **Audit Logging** – Complete audit trail of all changes.
* **Email Notifications** – Daily reminders for missing entries and anomaly alerts.
* **REST API** – External system integration endpoints.

== Installation ==

= Via WordPress Admin (Recommended) =

1. In your WordPress admin panel, go to **Plugins → Add New → Upload Plugin**.
2. Click **Choose File**, select the plugin ZIP file, and click **Install Now**.
3. After installation completes, click **Activate Plugin**.

= Manual Installation =

1. Upload the `multi-store-cash-manager` folder to the `/wp-content/plugins/` directory.
2. Go to **Plugins → Installed Plugins** in your WordPress admin.
3. Find **Multi-Store Daily Cash Manager** and click **Activate**.

= What Happens on Activation =

When you activate the plugin, it automatically:

1. Creates all required database tables.
2. Sets default plugin options.
3. Creates the **Store User** and **Store Manager** custom WordPress roles.
4. Adds MSCM capabilities to the Administrator role.
5. Schedules daily cron jobs for entry reminders (6:00 PM) and backups (2:00 AM).

== Frequently Asked Questions ==

= What database tables does the plugin create? =

The plugin creates the following tables (using your WordPress table prefix):

* `mscm_stores` – Store locations and settings
* `mscm_user_stores` – User-to-store assignments
* `mscm_daily_entries` – Daily end-of-day entries
* `mscm_payouts` – Payout records linked to entries
* `mscm_purchases` – Purchase/expense records linked to entries
* `mscm_targets` – Sales targets by store and period
* `mscm_settings` – Key-value plugin settings
* `mscm_audit_log` – Complete audit trail
* `mscm_ml_predictions` – AI/ML prediction data
* `mscm_anomalies` – Detected anomalies
* `mscm_notifications` – User notification records

= How do I give a user access to submit daily entries? =

1. Go to **Users → Edit User** in the WordPress admin.
2. Set the user's role to **Store User** or **Store Manager**.
3. Go to **Cash Manager → Stores**, open the relevant store, and assign the user.

= How do I create the frontend entry form? =

Create a new WordPress page and add the shortcode `[mscm_end_of_day]`. The form will be displayed to logged-in users who are assigned to a store.

= Can I use this plugin for multiple currencies? =

Yes. Each store can be configured with its own currency code and symbol in the store settings.

= Will uninstalling the plugin delete my data? =

Yes. Deleting the plugin removes all database tables and plugin options. Back up your data before uninstalling.

== Screenshots ==

1. Admin dashboard showing summary statistics and charts.
2. Daily end-of-day entry form with denomination breakdown.
3. Store management interface.
4. Reports page with date range and export options.
5. Sales target progress page.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
