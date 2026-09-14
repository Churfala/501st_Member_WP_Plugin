# 501st Legion Member Display — WordPress Plugin

Displays a 501st Legion garrison roster on a WordPress site using data from the [501st.com API](https://api.501st.com): command staff (officers) with links to their Legion profiles, followed by a photo roster of active members.

## Requirements

- **WordPress 5.3+** (uses `wp_date()`)
- **PHP 7.0+**
- Outbound HTTPS access from the web server to `api.501st.com`
- **501st.com API credentials** (HTTP Basic Auth) — required to fetch member data

## Plugin structure

```
501st-member-display/
├── 501st-member-display.php    # main plugin file
├── css/
│   └── 501st-member-display.css   # roster styles (Monda font is enqueued by the plugin)
├── readme.txt                  # WordPress-format readme
└── README.md                   # this file
```

## Installation

1. Copy the plugin folder into `wp-content/plugins/` (or zip it and upload via **Plugins → Add New → Upload Plugin**).
2. Activate **501st Legion Member Display** on the Plugins screen.
3. Open **Settings → 501st Member Display**:
   - Enter your API username and password → **Save Credentials** → **Test Connection**.
   - Reload the page, choose your garrison/outpost from the dropdown → **Save Settings**.
4. Add the shortcode to any page or post.

## Usage

| Shortcode | Result |
|---|---|
| `[501st_member_display]` | Roster for the garrison chosen in Settings (defaults to #54) |
| `[501st_member_display garrison_id="140"]` | Roster for a specific garrison, overriding the global setting |
| `[nzg_external_data]` | Legacy shortcode — still works as an alias of `[501st_member_display]` |

## Caching

- Roster responses are cached per garrison for **24 hours**; the garrison list for **7 days** (WordPress transients).
- **Clear Cache** on the settings page forces a fresh fetch; **Refresh Garrison List** reloads the dropdown.
- The settings page shows the timestamp of the last successful live fetch, in the site's configured timezone.

## Behaviour notes

- Members with a status of **In Memoriam**, **Reserve**, or **Retired** are excluded from the roster and the member count.
- Both API response shapes are supported: the legacy flat array and the current `{"garrison": {...}}` wrapper (`garrisonId` and `id` fields both work).
- Styling lives in `css/501st-member-display.css` (classes: `.command`, `.roster`, `.caption`, `.officerbreak`). The stylesheet and the Monda Google Font are only enqueued on pages that render the shortcode.
- API credentials are stored in the WordPress options table and sent only to `api.501st.com` over HTTPS. Anyone with admin or database access can read them, so prefer dedicated API credentials over a personal password.

## Author

Brendan Cowan — ST-84218
