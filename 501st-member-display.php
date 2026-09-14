<?php
/*
Plugin Name: 501st Legion Member Display
Description: Displays a 501st Legion garrison roster (officers and members) on WordPress using data from the 501st.com API.
Version: 2.1
Author: Brendan Cowan ST84218
Text Domain: 501st-member-display
Domain Path: /lang
*/

defined('ABSPATH') || exit;

/** =========================================================================
 * Constants
 * ========================================================================= */
if (!defined('NZG_API_BASE')) {
    define('NZG_API_BASE', 'https://api.501st.com');
}
define('NZG_API_OPTION_GARRISON_ID',       'nzg_api_garrison_id');
define('NZG_API_OPTION_API_USER',          'nzg_api_username');
define('NZG_API_OPTION_API_PASS',          'nzg_api_password');
define('NZG_API_TRANSIENT_PREFIX',         'nzg_api_response_');
define('NZG_API_GARRISONS_LIST_TRANSIENT', 'nzg_api_garrisons_list');
define('NZG_API_LAST_REFRESH_PREFIX',      'nzg_api_last_refresh_');
define('NZG_API_SETTINGS_SLUG',            'nzg-api-settings');

/** =========================================================================
 * Utilities
 * ========================================================================= */
function nzg_get_selected_garrison_id(): int {
    $id = absint(get_option(NZG_API_OPTION_GARRISON_ID));
    return $id > 0 ? $id : 54;
}

/**
 * Format a Unix timestamp using WordPress's configured timezone.
 */
function nzg_format_timestamp(int $ts): string {
    return wp_date('Y-m-d H:i:s', $ts);
}

/**
 * Build the request args array for wp_safe_remote_get, including Basic Auth
 * headers if credentials have been saved.
 */
function nzg_api_request_args(): array {
    $args = ['timeout' => 20];

    $user = get_option(NZG_API_OPTION_API_USER, '');
    $pass = get_option(NZG_API_OPTION_API_PASS, '');

    if ($user !== '' && $pass !== '') {
        $args['headers'] = [
            'Authorization' => 'Basic ' . base64_encode($user . ':' . $pass),
        ];
    }

    return $args;
}

/**
 * Fetch and cache the garrisons list from the API.
 */
function nzg_get_garrisons_list(): array {
    $cached = get_transient(NZG_API_GARRISONS_LIST_TRANSIENT);
    if (is_array($cached)) {
        return $cached;
    }

    $resp = wp_safe_remote_get(NZG_API_BASE . '/garrisons', nzg_api_request_args());
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        return [];
    }

    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($json)) {
        return [];
    }

    $list = [];

    // Handle both shapes: flat array [ {...}, {...} ] or wrapped { "garrisons": [...] }
    $garrisons = $json;
    if (isset($json['garrisons']) && is_array($json['garrisons'])) {
        $garrisons = $json['garrisons'];
    }

    foreach ($garrisons as $g) {
        if (!is_array($g)) {
            continue;
        }
        // New API uses "garrisonId", old used "id"
        $gid = (int) ($g['garrisonId'] ?? $g['id'] ?? 0);
        $name = (string) ($g['name'] ?? '');
        if ($gid <= 0 || $name === '') {
            continue;
        }
        $list[] = [
            'id'   => $gid,
            'name' => $name,
            'type' => (string) ($g['garrisonType'] ?? $g['unitType'] ?? ''),
        ];
    }

    usort($list, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });

    set_transient(NZG_API_GARRISONS_LIST_TRANSIENT, $list, WEEK_IN_SECONDS);
    return $list;
}

/** =========================================================================
 * CSS
 * ========================================================================= */
add_action('wp_enqueue_scripts', 'nzg_enqueue_styles');
function nzg_enqueue_styles() {
    wp_register_style(
        'nzg-api-font',
        'https://fonts.googleapis.com/css?family=Monda&display=swap',
        [],
        null
    );
    wp_register_style(
        'nzg-api-style',
        plugins_url('css/501st-member-display.css', __FILE__),
        ['nzg-api-font'],
        '2.1'
    );
}

/** =========================================================================
 * Shortcode
 * ========================================================================= */
add_shortcode('501st_member_display', 'nzg_get_send_data');
// Back-compat alias for pages still using the old shortcode.
add_shortcode('nzg_external_data', 'nzg_get_send_data');

function nzg_get_send_data($atts = []) {
    wp_enqueue_style('nzg-api-style');

    $atts = shortcode_atts(['garrison_id' => ''], $atts, '501st_member_display');

    $garrison_id = $atts['garrison_id'] !== '' ? absint($atts['garrison_id']) : nzg_get_selected_garrison_id();
    if ($garrison_id <= 0) {
        $garrison_id = 54;
    }

    $cache_key     = NZG_API_TRANSIENT_PREFIX . $garrison_id;
    $timestamp_key = NZG_API_LAST_REFRESH_PREFIX . $garrison_id;
    $url           = NZG_API_BASE . '/garrisons/' . $garrison_id . '/members';

    $response = get_transient($cache_key);

    if ($response === false) {
        $response = wp_safe_remote_get($url, nzg_api_request_args());

        if (is_wp_error($response)) {
            return '<p>' . esc_html__('Error: ', '501st-member-display') . esc_html($response->get_error_message()) . '</p>';
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return '<p>' . esc_html(sprintf(__('API returned status %d. Please try again later.', '501st-member-display'), $status)) . '</p>';
        }

        set_transient($cache_key, $response, DAY_IN_SECONDS);
        update_option($timestamp_key, time());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || empty($data)) {
        return '<p>' . esc_html__('No data available.', '501st-member-display') . '</p>';
    }

    // New API wraps everything inside {"garrison": {...}}
    // Unwrap if present, then normalise into an array for the loop.
    if (isset($data['garrison']) && is_array($data['garrison'])) {
        $data = $data['garrison'];
    }

    if (isset($data['officers']) || isset($data['members'])) {
        $data = [$data];
    }

    ob_start();

    foreach ($data as $row) {
        // Filter out In Memoriam, Reserve, and Retired members
        $hidden_statuses = ['In Memoriam', 'Reserve', 'Retired'];
        $active_members  = [];
        if (!empty($row['members']) && is_array($row['members'])) {
            foreach ($row['members'] as $member) {
                $status = $member['memberStatus'] ?? '';
                if (!in_array($status, $hidden_statuses, true)) {
                    $active_members[] = $member;
                }
            }
        }
        $memberCount = count($active_members);

        echo '<div class="command">';
        echo '<h2>' . esc_html__('Officers:', '501st-member-display') . '</h2>';

        if (!empty($row['officers']) && is_array($row['officers'])) {
            foreach ($row['officers'] as $officer) {
                $officer_profile = isset($officer['legionId'])
                    ? 'https://www.501st.com/member/' . intval($officer['legionId']) . '/'
                    : '#';
                printf(
                    '<span>%s:<br class="officerbreak"><a href="%s" target="_blank" rel="noopener noreferrer">%s (%s)</a></span><br/>',
                    esc_html($officer['office']    ?? 'Unknown'),
                    esc_url($officer_profile),
                    esc_html($officer['fullName']  ?? 'Unknown'),
                    esc_html($officer['formattedLegionId'] ?? $officer['legionId'] ?? 'Unknown')
                );
            }
        } else {
            echo '<p>' . esc_html__('No officers listed.', '501st-member-display') . '</p>';
        }

        echo '</div><br/><h3>' . esc_html__('Members', '501st-member-display') . ' (' . esc_html($memberCount) . ')</h3><div>';

        if (!empty($active_members)) {
            foreach ($active_members as $member) {
                $member_profile = isset($member['legionId'])
                    ? 'https://www.501st.com/member/' . intval($member['legionId']) . '/'
                    : '#';
                printf(
                    '<div class="roster"><a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="%s" loading="lazy"></a><span class="caption">%s</span></div>',
                    esc_url($member_profile),
                    esc_url($member['primaryThumbnail']     ?? $member['thumbnail'] ?? ''),
                    esc_attr($member['formattedLegionId']  ?? 'Member'),
                    esc_html($member['formattedLegionId']  ?? 'N/A')
                );
            }
        } else {
            echo '<p>' . esc_html__('No members listed.', '501st-member-display') . '</p>';
        }

        echo '</div>';
    }

    return ob_get_clean();
}

/** =========================================================================
 * Admin Menu + Settings Page
 * ========================================================================= */
add_action('admin_menu', 'nzg_api_admin_menu');
function nzg_api_admin_menu() {
    add_options_page(
        '501st Member Display Settings',
        '501st Member Display',
        'manage_options',
        NZG_API_SETTINGS_SLUG,
        'nzg_api_settings_page'
    );
}

function nzg_api_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $current_id = nzg_get_selected_garrison_id();
    $old_id     = $current_id;
    $notice     = '';
    $error      = '';

    if (isset($_POST['nzg_action']) && check_admin_referer('nzg_api_settings_action')) {

        if ($_POST['nzg_action'] === 'save') {
            $new_id = isset($_POST['nzg_garrison_id']) ? absint($_POST['nzg_garrison_id']) : 0;
            if ($new_id > 0) {
                update_option(NZG_API_OPTION_GARRISON_ID, $new_id);
                $current_id = $new_id;
                delete_transient(NZG_API_TRANSIENT_PREFIX . $old_id);
                delete_transient(NZG_API_TRANSIENT_PREFIX . $new_id);
                $notice = 'Settings saved.';
            } else {
                $error = 'Please choose a valid garrison.';
            }
        }

        if ($_POST['nzg_action'] === 'save_auth') {
            $api_user = isset($_POST['nzg_api_user']) ? sanitize_text_field($_POST['nzg_api_user']) : '';
            $api_pass = isset($_POST['nzg_api_pass']) ? sanitize_text_field($_POST['nzg_api_pass']) : '';
            update_option(NZG_API_OPTION_API_USER, $api_user);
            update_option(NZG_API_OPTION_API_PASS, $api_pass);
            // Flush all caches so next request uses new credentials
            delete_transient(NZG_API_GARRISONS_LIST_TRANSIENT);
            delete_transient(NZG_API_TRANSIENT_PREFIX . $current_id);
            $notice = 'API credentials saved and caches cleared.';
        }

        if ($_POST['nzg_action'] === 'clear_cache') {
            delete_transient(NZG_API_TRANSIENT_PREFIX . $current_id);
            $notice = 'Cache cleared. Timestamp will update on the next live API fetch.';
        }

        if ($_POST['nzg_action'] === 'refresh_list') {
            delete_transient(NZG_API_GARRISONS_LIST_TRANSIENT);
            $notice = 'Garrison list cache cleared. It will reload on next page view.';
        }

        if ($_POST['nzg_action'] === 'test_api') {
            $test = wp_safe_remote_get(NZG_API_BASE . '/ping', nzg_api_request_args());
            if (is_wp_error($test)) {
                $error = 'Connection failed: ' . $test->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code($test);
                if ($code === 200) {
                    $notice = 'API connection successful (HTTP 200).';
                } elseif ($code === 401) {
                    $error = 'Authentication failed (HTTP 401). Check your username and password.';
                } else {
                    $error = sprintf('API returned HTTP %d.', $code);
                }
            }
        }
    }

    $garrisons    = nzg_get_garrisons_list();
    $last_refresh = get_option(NZG_API_LAST_REFRESH_PREFIX . $current_id);
    $saved_user   = get_option(NZG_API_OPTION_API_USER, '');
    $saved_pass   = get_option(NZG_API_OPTION_API_PASS, '');
    ?>
    <div class="wrap">
        <h1>501st Member Display Settings</h1>

        <?php if ($notice): ?>
            <div class="updated"><p><strong><?php echo esc_html($notice); ?></strong></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><p><strong><?php echo esc_html($error); ?></strong></p></div>
        <?php endif; ?>

        <!-- API Credentials -->
        <h2>API Authentication</h2>
        <form method="post">
            <?php wp_nonce_field('nzg_api_settings_action'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nzg_api_user">API Username</label></th>
                    <td>
                        <input type="text" name="nzg_api_user" id="nzg_api_user"
                               value="<?php echo esc_attr($saved_user); ?>" class="regular-text" autocomplete="off" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nzg_api_pass">API Password</label></th>
                    <td>
                        <input type="password" name="nzg_api_pass" id="nzg_api_pass"
                               value="<?php echo esc_attr($saved_pass); ?>" class="regular-text" autocomplete="off" />
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="nzg_action" value="save_auth">Save Credentials</button>
                <button class="button" name="nzg_action" value="test_api">Test Connection</button>
            </p>
        </form>

        <hr/>

        <!-- Garrison Selection -->
        <h2>Garrison Settings</h2>
        <form method="post">
            <?php wp_nonce_field('nzg_api_settings_action'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nzg_garrison_id">Garrison</label></th>
                    <td>
                        <?php if (!empty($garrisons)): ?>
                            <select name="nzg_garrison_id" id="nzg_garrison_id">
                                <?php foreach ($garrisons as $g): ?>
                                    <option value="<?php echo esc_attr($g['id']); ?>" <?php selected($current_id, $g['id']); ?>>
                                        <?php
                                            $label = $g['name'];
                                            if (!empty($g['type'])) $label .= ' (' . $g['type'] . ')';
                                            $label .= ' - #' . $g['id'];
                                            echo esc_html($label);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select which garrison/outpost to display.</p>
                        <?php else: ?>
                            <input type="number" name="nzg_garrison_id" id="nzg_garrison_id" min="1" step="1"
                                value="<?php echo esc_attr($current_id); ?>" />
                            <p class="description">Enter the numeric garrison ID if the list cannot be loaded.
                                <?php if ($saved_user === ''): ?>
                                    <br/><strong>Hint:</strong> Save your API credentials above first, then refresh this page.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="nzg_action" value="save">Save Settings</button>
                <button class="button" name="nzg_action" value="clear_cache">Clear Cache</button>
                <button class="button" name="nzg_action" value="refresh_list">Refresh Garrison List</button>
            </p>

            <p>
                <?php if ($last_refresh): ?>
                    <em>Last successful API fetch (garrison #<?php echo esc_html($current_id); ?>):
                        <?php echo esc_html(nzg_format_timestamp((int) $last_refresh)); ?>
                    </em>
                <?php else: ?>
                    <em>No successful API fetch recorded yet for garrison #<?php echo esc_html($current_id); ?>.</em>
                <?php endif; ?>
            </p>
        </form>

        <hr/>
        <h2>Usage</h2>
        <p><strong>Default shortcode:</strong> <code>[501st_member_display]</code></p>
        <p><strong>Override garrison:</strong> <code>[501st_member_display garrison_id="140"]</code></p>
        <p class="description">The legacy shortcode <code>[nzg_external_data]</code> still works on existing pages.</p>
        <p class="description">Use the override to display a different garrison on a specific page/post without changing the global setting.</p>
        <p><strong>API Base URL:</strong> <code><?php echo esc_html(NZG_API_BASE); ?></code></p>
    </div>
    <?php
}

/** =========================================================================
 * Admin notice — scoped to plugin settings page only
 * ========================================================================= */
add_action('admin_notices', 'nzg_api_admin_notice');
function nzg_api_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'settings_page_' . NZG_API_SETTINGS_SLUG) {
        return;
    }

    $gid          = nzg_get_selected_garrison_id();
    $last_refresh = get_option(NZG_API_LAST_REFRESH_PREFIX . $gid);

    if ($last_refresh) {
        printf(
            '<div class="notice notice-info"><p><strong>501st Member Display:</strong> Garrison #%s — last successful fetch: %s.</p></div>',
            esc_html($gid),
            esc_html(nzg_format_timestamp((int) $last_refresh))
        );
    } else {
        printf(
            '<div class="notice notice-warning"><p><strong>501st Member Display:</strong> No successful API fetch recorded yet for garrison #%s.</p></div>',
            esc_html($gid)
        );
    }
}

/** =========================================================================
 * Add "Settings" link on Plugins page
 * ========================================================================= */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'nzg_api_settings_link');
function nzg_api_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=' . NZG_API_SETTINGS_SLUG)) . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
