<?php
/**
 * Plugin Name: M365 OAuth Mailer
 * Plugin URI: https://github.com/MarcBanyard/WordPress_M365_OAuth2_Mailer
 * Description: Send WordPress emails via Microsoft 365 using OAuth2 App Registration. Works with shared mailboxes and user mailboxes using application permissions.
 * Version: 1.0.0
 * Author: Marc Banyard
 * Author URI: https://banyard.me.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: m365-oauth-mailer
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('M365_OAUTH_MAILER_VERSION', '1.0.0');
define('M365_OAUTH_MAILER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('M365_OAUTH_MAILER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('M365_OAUTH_MAILER_PLUGIN_FILE', __FILE__);

// Include required files
require_once M365_OAUTH_MAILER_PLUGIN_DIR . 'includes/class-m365-graph-api.php';
require_once M365_OAUTH_MAILER_PLUGIN_DIR . 'includes/class-m365-logger.php';
require_once M365_OAUTH_MAILER_PLUGIN_DIR . 'includes/class-m365-mailer.php';
require_once M365_OAUTH_MAILER_PLUGIN_DIR . 'admin/class-m365-admin.php';

/**
 * Main plugin class
 */
class M365_OAuth_Mailer {
    
    private static $instance = null;
    private $mailer;
    private $logger;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Check if required constants are defined
        add_action('admin_notices', array($this, 'check_config'));
        
        // Initialize logger
        $this->logger = new M365_Logger();
        
        // Initialize mailer (it will hook into pre_wp_mail itself)
        $this->mailer = new M365_Mailer($this->logger);
        
        // Initialize admin
        if (is_admin()) {
            new M365_Admin($this->logger);
        }
        
        // Schedule cleanup of old logs
        add_action('m365_oauth_mailer_cleanup_logs', array($this->logger, 'cleanup_old_logs'));
        if (!wp_next_scheduled('m365_oauth_mailer_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'm365_oauth_mailer_cleanup_logs');
        }
    }
    
    /**
     * Check if required configuration constants are defined
     */
    public function check_config() {
        if (!defined('M365_OAUTH2_SMTP_CLIENT_ID') || !defined('M365_OAUTH2_SMTP_CLIENT_SECRET')) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('M365 OAuth Mailer:', 'm365-oauth-mailer'); ?></strong>
                    <?php esc_html_e('Please define M365_OAUTH2_SMTP_CLIENT_ID and M365_OAUTH2_SMTP_CLIENT_SECRET in your wp-config.php file.', 'm365-oauth-mailer'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get mailer instance
     */
    public function get_mailer() {
        return $this->mailer;
    }
    
    /**
     * Get logger instance
     */
    public function get_logger() {
        return $this->logger;
    }
}

/**
 * Activation hook
 */
function m365_oauth_mailer_activate() {
    // Define plugin directory if not already defined
    if (!defined('M365_OAUTH_MAILER_PLUGIN_DIR')) {
        define('M365_OAUTH_MAILER_PLUGIN_DIR', plugin_dir_path(__FILE__));
    }
    
    // Include required files for activation
    require_once M365_OAUTH_MAILER_PLUGIN_DIR . 'includes/class-m365-logger.php';
    
    // Create logs table
    M365_Logger::create_table();
    
    // Set default options
    $defaults = array(
        'log_retention_days' => 30,
        'enable_logging' => true,
        'from_email' => '',
        'from_name' => ''
    );
    
    add_option('m365_oauth_mailer_settings', $defaults);
}

/**
 * Deactivation hook
 */
function m365_oauth_mailer_deactivate() {
    // Clear scheduled cleanup
    wp_clear_scheduled_hook('m365_oauth_mailer_cleanup_logs');
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'm365_oauth_mailer_activate');
register_deactivation_hook(__FILE__, 'm365_oauth_mailer_deactivate');

// Initialize plugin after WordPress is loaded
add_action('plugins_loaded', function() {
    M365_OAuth_Mailer::get_instance();
}, 10);
