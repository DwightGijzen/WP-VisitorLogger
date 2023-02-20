<?php 
/*
Plugin Name: Visitor Log
Plugin URI: http://example.com/visitor-log/
Description: Records every visitor's IP address and browser in a table on pages you select. IP addresses are only recorded once. Once an IP Address exists in the table, no other entries from that IP Address are recorded.
Version: 1.3
Author: Dwight Gijzen
Author URI: http://example.com/
License: GPL2
*/

function create_visitor_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_log';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ip_address varchar(45) NOT NULL,
        browser varchar(255) NOT NULL,
        timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        country varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_visitor_log_table');

function record_visitor_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_log';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $browser = $_SERVER['HTTP_USER_AGENT'];
    $timestamp = current_time('mysql');
    $country = get_country_from_ip($ip_address);
    $allowed_pages = get_option('visitor_log_pages', array());
    if (is_array($allowed_pages) && count($allowed_pages) > 0 && in_array(get_the_ID(), $allowed_pages)) {
        $existing_record = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE ip_address = %s", $ip_address));
        if (!$existing_record) {
            $wpdb->insert($table_name, array(
                'ip_address' => $ip_address,
                'browser' => $browser,
                'timestamp' => $timestamp,
                'country' => $country,
            ));
        }
    }
}
add_filter('wp', 'record_visitor_data');

function get_country_from_ip($ip_address) {
    $url = 'https://ipapi.co/' . $ip_address . '/country/';
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return '';
    }
    $country = wp_remote_retrieve_body($response);
    return $country;
}

function visitor_log_menu() {
    add_menu_page('Visitor Log', 'Visitor Log', 'manage_options', 'visitor-log', 'display_visitor_log');
    add_submenu_page('visitor-log', 'Settings', 'Settings', 'manage_options', 'visitor-log-settings', 'display_visitor_log_settings');
}
add_action('admin_menu', 'visitor_log_menu');

function display_visitor_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'visitor_log';
    $ip_address = isset($_REQUEST['ip_address']) ? sanitize_text_field($_REQUEST['ip_address']) : '';
    $where_clause = $ip_address ? "WHERE ip_address LIKE '%$ip_address%'" : '';
    $results = $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY timestamp DESC");
    echo '<div class="wrap">';
    echo '<h1>Visitor Log</h1>';
	echo '<form method="get">';
    echo '<input type="text" name="ip_address" placeholder="Search by IP address" value="' . $ip_address . '">';
    echo '<input type="submit" class="button" value="Search">';
    echo '</form>';
    if (count($results) > 0) {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th>IP Address</th>';
        echo '<th>Browser</th>';
        echo '<th>Date/Time</th>';
        echo '<th>Country</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . $row->ip_address . '</td>';
            echo '<td>' . $row->browser . '</td>';
            echo '<td>' . $row->timestamp . '</td>';
            echo '<td>' . $row->country . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No results found.</p>';
    }
    echo '</div>';
}

function display_visitor_log_settings() {
    if (isset($_POST['visitor_log_settings'])) {
        $allowed_pages = isset($_POST['allowed_pages']) ? $_POST['allowed_pages'] : array();
        update_option('visitor_log_pages', $allowed_pages);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
    $allowed_pages = get_option('visitor_log_pages', array());
    $pages = get_pages();
    echo '<div class="wrap">';
    echo '<h1>Visitor Log Settings</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    echo '<tr><th><label for="allowed_pages">Allowed Pages:</label></th><td>';
    foreach ($pages as $page) {
        echo '<label><input type="checkbox" name="allowed_pages[]" value="' . $page->ID . '"' . (in_array($page->ID, $allowed_pages) ? ' checked' : '') . '> ' . $page->post_title . '</label><br>';
    }
    echo '</td></tr>';
    echo '</table>';
    echo '<p><input type="submit" class="button button-primary" value="Save Settings" name="visitor_log_settings"></p>';
    echo '</form>';
    echo '</div>';
}