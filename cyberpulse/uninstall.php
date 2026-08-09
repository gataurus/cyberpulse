<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Remove all plugin options
delete_option('secwall_settings');
delete_option('secwall_tracked_pages');
delete_option('secwall_blocked_subnets');
delete_option('secwall_bot_detection_options');
delete_option('secwall_custom_whitelist');
delete_option('secwall_temp_blocks');
delete_option('secwall_daily_stats');
delete_option('secwall_brute_data');
delete_option('secwall_repeat_offenders');
delete_option('secwall_permanent_blocked_subnets');
delete_option('secwall_blocked_user_agents');
delete_option('secwall_ua_stats');
delete_option('secwall_file_hashes');

// Delete transients
global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cybersec_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cybersec_%'");
// phpcs:enable

// Helper function to recursively delete directory
function cybersec_uninstall_rmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? cybersec_uninstall_rmdir($path) : wp_delete_file($path);
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
    rmdir($dir);
}

// Delete log files and directory
$cybersec_upload_dir = wp_upload_dir();
$cybersec_log_dir = $cybersec_upload_dir['basedir'] . '/cyber-pulse-logs/';
cybersec_uninstall_rmdir($cybersec_log_dir);
