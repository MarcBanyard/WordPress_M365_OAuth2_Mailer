<?php
/**
 * Uninstall script for M365 OAuth Mailer
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('m365_oauth_mailer_settings');

// Drop logs table
global $wpdb;
$table_name = $wpdb->prefix . 'm365_oauth_mailer_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear scheduled events
wp_clear_scheduled_hook('m365_oauth_mailer_cleanup_logs');
