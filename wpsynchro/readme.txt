=== WP Synchro – WordPress Migration, Clone, Backup & Sync Plugin ===
Contributors: wpsynchro
Donate link: https://daev.tech/wpsynchro/?utm_source=wordpress.org&utm_medium=referral&utm_campaign=donate
Tags: migrate, clone, files, database, migration, backup, sync, staging, development, wordpress migration, site migration, move wordpress, transfer wordpress
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.13.0
Requires PHP: 7.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0

WordPress migration plugin to easily migrate, clone, backup, and synchronize your WordPress site, including database, media, plugins, themes, and files. Perfect for moving WordPress sites between development, staging, and production environments.

== Description ==

**WP Synchro is the ultimate WordPress migration plugin for professionals and agencies.**

Easily migrate, clone, backup, and synchronize your WordPress site, including database, media, plugins, themes, and custom files. WP Synchro is designed for fast, secure, and customizable migrations between local, staging, and production environments.

**Key Features (Free):**
* One-click WordPress database migration (pull/push)
* Search/replace in database data (supports serialized data)
* Handles migration of database table prefixes between sites
* Select specific database tables or migrate all
* Automatic cache clearing after migration for popular cache plugins
* Secure, encrypted data transfer – no third-party servers
* Set up once, run multiple times – perfect for development, staging, and production

**PRO Features:**
* File migration: media, plugins, themes, custom files/folders
* Only migrate changed files for faster sync
* User confirmation before making file changes
* Customize migrations down to a single file or folder
* Support for basic authentication (.htaccess)
* Email notifications on migration success or failure
* Database backup before migration
* WP CLI command for scheduled migrations (cron)
* 14-day free trial for PRO features

**Typical Use Cases:**
* Move or clone WordPress sites between servers or hosts
* Push local development or staging sites to production
* Pull a copy of a live site for debugging or development
* Keep staging and production in sync
* Back up your WordPress site

== Quick Start ==

1. Install WP Synchro on both source and destination WordPress sites.
2. Activate the plugin via the Plugins menu.
3. Create a new migration job in WP Synchro.
4. Select what to migrate: database, files, or both.
5. Run the migration and watch your site move!
6. Rerun the same migration anytime with one click.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wpsynchro` directory, or install the plugin through the WordPress plugins screen directly.
2. Install WP Synchro on both sites involved in the migration.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Configure migration settings in WP Synchro > Setup.
5. Add your first migration job and configure it.
6. Run the migration.
7. Enjoy fast, easy migrations and repeat as needed.

== Frequently Asked Questions ==

= How do I migrate my WordPress site with WP Synchro? =

Install WP Synchro on both sites, create a migration job, select what to migrate (database, files, or both), and run the migration. See the Quick Start section above.

= Can I move my WordPress site to a new host or domain? =

Yes! WP Synchro handles migrations between any WordPress sites, including different hosts or domains. It automatically updates URLs and handles database table prefixes.

= Does WP Synchro support file migration? =

Yes, file migration (media, plugins, themes, custom files) is available in the PRO version.

= Is my data secure during migration? =

Yes, all data is transferred directly between your sites and is encrypted. No third-party servers are involved.

= Can I schedule migrations? =

Yes, with the PRO version you can use the built-in WordPress scheduling or you can use WP CLI to schedule migrations via cron or other triggers.

= Does WP Synchro support multisite? =

Not yet. Multisite support is planned for a future release.

= Where can I get support or report bugs? =

Contact us at <support@daev.tech>.

== Screenshots ==

1. Overview of plugin, where you start and delete migration jobs
2. Add/edit screen for setting up a migration job
3. Plugin setup screen
4. WP Synchro performing a database migration

== Support & Documentation ==

For detailed documentation and support, visit [WP Synchro Documentation](https://daev.tech/wpsynchro/docs).

== Changelog ==

= 1.13.0 =
 * Bugfix: Optimize the handling of database queries, which no longer has the same tendency to cause sort buffer errors in certain conditions
 * Bugfix: When verifying migrations, give better debug information when it fails, instead of a generic JS message

= 1.12.0 =
 * Improvement: Extend cron scheduling system, so migrations can be run at intervals automatically without user intervention and without WP CLI
 * Improvement: Prevent unwanted background update from PRO version to FREE version for some users
 * Improvement: Make it possible to only delete a single log from the "Logs" menu, instead of all or nothing
 * Improvement: Make it possible to download the database backup from a pull migration in "Logs" menu
 * Bugfix: No longer use ini_restore() native PHP function, because some hosting does not allow it

= 1.11.5 =
 * Bugfix: Fix links for usage reporting dialog, leading to a non-existing page

= 1.11.4 =
 * Change: Bump minimum PHP requirement to 7.2 from 7.0
 * Change: Bump minimum WP requirement to 5.8 from 5.2
 * Change: Bump minimum MySQL requirement to 5.7 from 5.5
 * Change: Bump supported WP version to 6.5
 * Bugfix: Fix some issues causing menu to generate PHP deprecation issues, even though it just triggered it in WP core functions

= 1.11.3 =
 * Change: Change all service URLs from wpsynchro.com to daev.tech, as we have moved the plugin there
 * Bugfix: Fixed a minor CSRF issue reported by Patchstack - Not a risk to be worried about.

= 1.11.2 =
 * Bugfix: Fix PHP timeout issue caused by serialized data, kind of like 1.11.1 hotfix, but caused by other data.
 * Improvement: Added more safety against timeout issues in serialized data, so it wont happen again

= 1.11.1 =
 * Bugfix: Fix PHP timeout issue caused by serialized string search/replace handler, that goes into endless loop for defective serialized strings
 * Bugfix: Fix issue with some tables not being migrated when source database is MariaDB and when table does not have a primary key
 * Improvement: Improve the error reporting when database server gives errors

** Only showing the last few releases - See rest of changelog in changelog.txt or in menu "Changelog" **
