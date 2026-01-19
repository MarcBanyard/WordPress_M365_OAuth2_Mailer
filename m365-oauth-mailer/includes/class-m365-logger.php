<?php
/**
 * M365 Logger Class
 * 
 * Handles email logging with configurable retention
 */

if (!defined('ABSPATH')) {
    exit;
}

class M365_Logger {
    
    private static $table_name = 'm365_oauth_mailer_logs';
    
    /**
     * Create logs table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            from_email varchar(255) NOT NULL,
            to_emails text NOT NULL,
            subject varchar(500) NOT NULL,
            status varchar(50) NOT NULL,
            error_message text,
            response_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log email attempt
     */
    public function log_email($from_email, $to_emails, $subject, $result) {
        global $wpdb;
        
        $settings = get_option('m365_oauth_mailer_settings', array());
        
        // Check if logging is enabled
        if (empty($settings['enable_logging'])) {
            return;
        }
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Prepare data
        $to_emails_str = is_array($to_emails) ? implode(', ', $to_emails) : $to_emails;
        $status = is_wp_error($result) ? 'failed' : 'success';
        $error_message = is_wp_error($result) ? $result->get_error_message() : null;
        $response_data = is_wp_error($result) 
            ? wp_json_encode($result->get_error_data()) 
            : (is_array($result) ? wp_json_encode($result) : null);
        
        $wpdb->insert(
            $table_name,
            array(
                'from_email' => sanitize_email($from_email),
                'to_emails' => sanitize_text_field($to_emails_str),
                'subject' => sanitize_text_field($subject),
                'status' => $status,
                'error_message' => $error_message,
                'response_data' => $response_data,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get logs
     */
    public function get_logs($limit = 50, $offset = 0, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where = '';
        if ($status) {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $query = "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query = $wpdb->prepare($query, $limit, $offset);
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get log count
     */
    public function get_log_count($status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where = '';
        if ($status) {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $query = "SELECT COUNT(*) FROM $table_name $where";
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Cleanup old logs based on retention period
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $settings = get_option('m365_oauth_mailer_settings', array());
        $retention_days = isset($settings['log_retention_days']) ? (int) $settings['log_retention_days'] : 30;
        
        if ($retention_days <= 0) {
            return; // Don't delete if retention is disabled
        }
        
        $table_name = $wpdb->prefix . self::$table_name;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Delete all logs
     */
    public function delete_all_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
    
    /**
     * Delete log by ID
     */
    public function delete_log($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $wpdb->delete($table_name, array('id' => $log_id), array('%d'));
    }
}
