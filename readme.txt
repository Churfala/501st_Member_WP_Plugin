=== 501st Legion Member Display ===
Contributors: brendancowan
Tags: 501st, roster, garrison, shortcode, api
Requires at least: 5.3
Tested up to: 6.6
Requires PHP: 7.0
Stable tag: 2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays a 501st Legion garrison roster (officers and members) on your WordPress site using data from the 501st.com API.

== Description ==

This plugin pulls garrison data from the 501st.com API (https://api.501st.com) and renders it via a shortcode: command staff (officers) with links to their Legion profiles, followed by a photo roster of active members.

**Features**

* `[501st_member_display]` shortcode to display the roster anywhere (legacy `[nzg_external_data]` still supported).
* Per-page garrison override: `[501st_member_display garrison_id="140"]`.
* Settings page with a searchable garrison/outpost dropdown loaded live from the API.
* HTTP Basic Auth support for API credentials, with a built-in "Test Connection" button.
* Responses cached for 24 hours (garrison list for 7 days) using WordPress transients, with one-click cache clearing.
* Members with a status of In Memoriam, Reserve, or Retired are hidden from the roster.
* Handles both the legacy and current API response shapes.

== Installation ==

1. Upload the plugin folder (containing `501st-member-display.php` and the `css/` folder) to `/wp-content/plugins/`, or upload it as a .zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins screen.
3. Go to **Settings → 501st Member Display**.
4. Enter your 501st API username and password, click **Save Credentials**, then **Test Connection** to confirm.
5. Reload the page, pick your garrison from the dropdown, and click **Save Settings**.
6. Add `[501st_member_display]` to any page or post.

**Requirements**

* WordPress 5.3 or newer (uses `wp_date()`).
* PHP 7.0 or newer.
* Outbound HTTPS access from your web server to `api.501st.com`.
* 501st.com API credentials (required for member data).

== Frequently Asked Questions ==

= The garrison dropdown is empty =

Save your API credentials first, then reload the settings page. If it is still empty, use **Test Connection** to check for an authentication or connectivity problem, or enter the numeric garrison ID manually in the fallback field.

= The roster on my site is out of date =

Roster data is cached for 24 hours. Go to Settings → 501st Member Display and click **Clear Cache** to force a fresh fetch on the next page view.

= Can I show more than one garrison? =

Yes. Use the shortcode override on each page, e.g. `[501st_member_display garrison_id="140"]`. Each garrison is cached separately.

= Where are my API credentials stored? =

In the WordPress options table (`nzg_api_username` / `nzg_api_password`). They are only sent to `api.501st.com` over HTTPS. Anyone with database or admin access to your site can read them, so use dedicated API credentials rather than a personal password where possible.

== Changelog ==

= 2.1 =
* Renamed plugin to "501st Legion Member Display" (main file is now `501st-member-display.php`).
* New shortcode `[501st_member_display]`; the old `[nzg_external_data]` remains as a back-compat alias.
* Stylesheet renamed to `css/501st-member-display.css`.

= 2.0 =
* Settings page with garrison dropdown loaded from the API.
* HTTP Basic Auth credential support and connection test.
* Shortcode `garrison_id` override attribute.
* Support for the new API response shapes (`garrison` wrapper, `garrisonId` field).
* Filtering of In Memoriam, Reserve, and Retired members.
* Per-garrison caching and last-fetch timestamp.

= 1.0 =
* Initial release.
