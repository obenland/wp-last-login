# WP Last Login

Contributors: obenland
Tags: admin, user, login, last login, login date
Donate link: <https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=K32M878XHREQC>
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 7
License: GPLv2 or later
License URI: <https://www.gnu.org/licenses/gpl-2.0.html>

Adds a sortable "Last Login" column to the Users screen — spot inactive accounts and see who's actually using your site.

## Description

**WP Last Login** records when each user signs in and surfaces it as a sortable column on the Users screen — so you can see who's active, identify dormant accounts, and confirm that real people are coming back.

### Features

* Sortable "Last Login" column on the Users screen, including the network admin user list on multisite.
* Hover any date to reveal the exact login time.
* Captures logins via the standard `wp_login` action, so it works with any login flow that triggers it — including the [Two Factor](https://wordpress.org/plugins/two-factor/) plugin, WooCommerce, BuddyBoss, and most social-login plugins.
* Users without a recorded login show a neutral em-dash (—) — never a misleading "never" — and still sort correctly when ordered by last login.
* Lightweight: one user meta key, no settings page, no extra database tables.
* Filter hooks let you customize the date format or hide the column from non-admin roles.


## Installation

1. Download WP Last Login.
2. Unzip the folder into the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.


## Frequently Asked Questions

### Why does the column show an em-dash (—) for all my users?

The plugin records a login when a user signs in *after* it's been activated. WordPress doesn't store historical login data, so existing accounts will start populating their "Last Login" the next time each user logs in. Hovering the dash shows a tooltip explaining the same.

### How do I change the date format or show the time?

The date follows your site's general date format (Settings → General); the exact time appears on hover. To override the date format, use the `wpll_date_format` filter:

    add_filter( 'wpll_date_format', function() {
        return 'Y-m-d H:i';
    } );

### How do I hide the column from non-admin users?

Filter `wpll_current_user_can` and return a capability check. Login tracking still happens — only column visibility is gated:

    add_filter( 'wpll_current_user_can', function() {
        return current_user_can( 'manage_options' );
    } );

### Does it work with multisite, Two Factor, WooCommerce, BuddyBoss, or social login plugins?

Yes. The plugin hooks into WordPress's standard `wp_login` action, so any login method that triggers it is captured. The Two Factor plugin (which interrupts `wp_login`) is supported via a dedicated hook.

### What happens to my data if I deactivate the plugin?

Deactivating leaves stored login timestamps intact, so reactivating preserves history. Uninstalling (deleting the plugin entirely) removes all of the plugin's user meta.


## Screenshots

1. Last login column in the user table.


## Changelog

### 7

* Added compatibility with Two Factor plugin. Props @bkno.
* Improved date display to display login time on hover.

### 6

* Revamped file structure to remove unnecessary files.
* Fixed a bug where login dates were overwritten on plugin reactivation. Props @richardbuff.

### 5

* Improved uninstall routine (no longer queries all users).
* Updated utility class.
* Tested with WordPress 6.1.

### 4

* Improved date display to account for the timezone of the site. Props @knutsp.

### 3

* Fixed a bug where users who haven't logged in disappear from user lists when ordering by last login. See <https://wordpress.org/support/topic/new-users-dont-get-the-meta-field/>

### 2

* Maintenance release.
* Updated code to adhere to WordPress Coding Standards.
* Tested with WordPress 5.0.

### 1.4.0

* Fixed a long standing bug, where sorting users by last login didn't work.
* Tested with WordPress 4.3.

### 1.3.0

* Maintenance release.
* Tested with WordPress 4.0.

### 1.2.1

* Reverts changes to wp_login() as the second argument seems not to be set at all times.

### 1.2.0

* Users are now sortable by last login!
* Updated utility class.
* Added Danish translation. Props thomasclausen.

### 1.1.2

* Fixed a bug where content of other custom columns were not displayed.

### 1.1.1

* Updated utility class.

### 1.1.0

* Made the display of the column filterable.
* Widened the column a bit to accommodate for large date strings.

### 1.0

* Initial Release.


## Upgrade Notice


## Plugin Filter Hooks

**wpll_current_user_can** (*boolean*)
> Whether the column is supposed to be shown.
> Default: true


**wpll_date_format** (*string*)
> The date format string for the date output.
