<?php
/**
 * M365 Admin Class
 * 
 * Handles admin interface and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class M365_Admin {
    
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('M365 OAuth Mailer', 'm365-oauth-mailer'),
            __('M365 OAuth Mailer', 'm365-oauth-mailer'),
            'manage_options',
            'm365-oauth-mailer',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('m365_oauth_mailer_settings', 'm365_oauth_mailer_settings', array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['enable_logging'] = isset($input['enable_logging']) ? 1 : 0;
        $sanitized['log_retention_days'] = isset($input['log_retention_days']) ? absint($input['log_retention_days']) : 30;
        $sanitized['from_email'] = isset($input['from_email']) ? sanitize_email($input['from_email']) : '';
        $sanitized['from_name'] = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : '';
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_m365-oauth-mailer') {
            return;
        }
        
        wp_enqueue_style('m365-admin-style', M365_OAUTH_MAILER_PLUGIN_URL . 'admin/css/admin.css', array(), M365_OAUTH_MAILER_VERSION);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'm365-oauth-mailer'));
        }
        
        $settings = get_option('m365_oauth_mailer_settings', array());
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=m365-oauth-mailer&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'm365-oauth-mailer'); ?>
                </a>
                <a href="?page=m365-oauth-mailer&tab=logs" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Email Logs', 'm365-oauth-mailer'); ?>
                </a>
                <a href="?page=m365-oauth-mailer&tab=test" class="nav-tab <?php echo $tab === 'test' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Test Email', 'm365-oauth-mailer'); ?>
                </a>
            </nav>
            
            <div class="m365-admin-content">
                <?php
                if ($tab === 'settings') {
                    $this->render_settings_tab($settings);
                } elseif ($tab === 'logs') {
                    $this->render_logs_tab();
                } elseif ($tab === 'test') {
                    $this->render_test_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab($settings) {
        // Handle form submission
        if (isset($_POST['m365_save_settings']) && check_admin_referer('m365_save_settings')) {
            // Process and save settings using the sanitize function
            if (isset($_POST['m365_oauth_mailer_settings'])) {
                $sanitized = $this->sanitize_settings($_POST['m365_oauth_mailer_settings']);
                update_option('m365_oauth_mailer_settings', $sanitized);
                echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'm365-oauth-mailer') . '</p></div>';
                // Reload settings after save
                $settings = get_option('m365_oauth_mailer_settings', array());
            }
        }
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('m365_save_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Configuration', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <div class="m365-config-info">
                            <p>
                                <?php esc_html_e('Configure your Microsoft 365 App Registration credentials in wp-config.php:', 'm365-oauth-mailer'); ?>
                            </p>
                            <ul>
                                <li><code>M365_OAUTH2_SMTP_TENANT_ID</code> - <?php esc_html_e('Your Azure Tenant ID (or "common" for multi-tenant)', 'm365-oauth-mailer'); ?></li>
                                <li><code>M365_OAUTH2_SMTP_CLIENT_ID</code> - <?php esc_html_e('Your App Registration Client ID', 'm365-oauth-mailer'); ?></li>
                                <li><code>M365_OAUTH2_SMTP_CLIENT_SECRET</code> - <?php esc_html_e('Your App Registration Client Secret', 'm365-oauth-mailer'); ?></li>
                            </ul>
                            <p>
                                <strong><?php esc_html_e('Status:', 'm365-oauth-mailer'); ?></strong>
                                <?php
                                $configured = defined('M365_OAUTH2_SMTP_CLIENT_ID') && defined('M365_OAUTH2_SMTP_CLIENT_SECRET');
                                if ($configured) {
                                    echo '<span style="color: green;">✓ ' . esc_html__('Configured', 'm365-oauth-mailer') . '</span>';
                                } else {
                                    echo '<span style="color: red;">✗ ' . esc_html__('Not Configured', 'm365-oauth-mailer') . '</span>';
                                }
                                ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="from_email"><?php esc_html_e('Default From Email', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="from_email" name="m365_oauth_mailer_settings[from_email]" 
                               value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('Default email address to send from. Leave empty to use WordPress default.', 'm365-oauth-mailer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="from_name"><?php esc_html_e('Default From Name', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="from_name" name="m365_oauth_mailer_settings[from_name]" 
                               value="<?php echo esc_attr($settings['from_name'] ?? ''); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('Default name to send from. Leave empty to use WordPress default.', 'm365-oauth-mailer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="enable_logging"><?php esc_html_e('Enable Logging', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable_logging" name="m365_oauth_mailer_settings[enable_logging]" 
                                   value="1" <?php checked(!empty($settings['enable_logging'])); ?> />
                            <?php esc_html_e('Log all email attempts', 'm365-oauth-mailer'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="log_retention_days"><?php esc_html_e('Log Retention (Days)', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="log_retention_days" name="m365_oauth_mailer_settings[log_retention_days]" 
                               value="<?php echo esc_attr($settings['log_retention_days'] ?? 30); ?>" 
                               min="0" max="365" class="small-text" />
                        <p class="description">
                            <?php esc_html_e('How many days to keep email logs. Set to 0 to keep logs indefinitely.', 'm365-oauth-mailer'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Settings', 'm365-oauth-mailer'), 'primary', 'm365_save_settings'); ?>
        </form>
        <?php
    }
    
    /**
     * Render logs tab
     */
    private function render_logs_tab() {
        // Handle log deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['log_id']) && check_admin_referer('delete_log')) {
            $this->logger->delete_log(absint($_GET['log_id']));
            echo '<div class="notice notice-success"><p>' . esc_html__('Log deleted successfully!', 'm365-oauth-mailer') . '</p></div>';
        }
        
        if (isset($_POST['delete_all_logs']) && check_admin_referer('delete_all_logs')) {
            $this->logger->delete_all_logs();
            echo '<div class="notice notice-success"><p>' . esc_html_e('All logs deleted successfully!', 'm365-oauth-mailer') . '</p></div>';
        }
        
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $logs = $this->logger->get_logs($per_page, $offset, $status_filter);
        $total_logs = $this->logger->get_log_count($status_filter);
        $total_pages = ceil($total_logs / $per_page);
        
        ?>
        <div class="m365-logs-header">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="m365-oauth-mailer" />
                <input type="hidden" name="tab" value="logs" />
                <select name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'm365-oauth-mailer'); ?></option>
                    <option value="success" <?php selected($status_filter, 'success'); ?>><?php esc_html_e('Success', 'm365-oauth-mailer'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'm365-oauth-mailer'); ?></option>
                </select>
                <?php submit_button(__('Filter', 'm365-oauth-mailer'), 'secondary', '', false); ?>
            </form>
            
            <form method="post" style="display: inline-block; margin-left: 10px;">
                <?php wp_nonce_field('delete_all_logs'); ?>
                <?php submit_button(__('Delete All Logs', 'm365-oauth-mailer'), 'delete', 'delete_all_logs', false, array('onclick' => "return confirm('" . esc_js(__('Are you sure you want to delete all logs?', 'm365-oauth-mailer')) . "');")); ?>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'm365-oauth-mailer'); ?></th>
                    <th><?php esc_html_e('From', 'm365-oauth-mailer'); ?></th>
                    <th><?php esc_html_e('To', 'm365-oauth-mailer'); ?></th>
                    <th><?php esc_html_e('Subject', 'm365-oauth-mailer'); ?></th>
                    <th><?php esc_html_e('Status', 'm365-oauth-mailer'); ?></th>
                    <th><?php esc_html_e('Date', 'm365-oauth-mailer'); ?></th>
                    <th><?php esc_html_e('Actions', 'm365-oauth-mailer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No logs found.', 'm365-oauth-mailer'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['id']); ?></td>
                            <td><?php echo esc_html($log['from_email']); ?></td>
                            <td><?php echo esc_html($log['to_emails']); ?></td>
                            <td><?php echo esc_html($log['subject']); ?></td>
                            <td>
                                <span class="m365-status m365-status-<?php echo esc_attr($log['status']); ?>">
                                    <?php echo esc_html(ucfirst($log['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'log_id' => $log['id'])), 'delete_log')); ?>" 
                                   class="delete" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'm365-oauth-mailer'); ?>');">
                                    <?php esc_html_e('Delete', 'm365-oauth-mailer'); ?>
                                </a>
                                <?php if ($log['status'] === 'failed' && !empty($log['error_message'])): ?>
                                    <br><small style="color: red;"><?php echo esc_html($log['error_message']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render test tab
     */
    private function render_test_tab() {
        $test_result = null;
        
        if (isset($_POST['send_test_email']) && check_admin_referer('send_test_email')) {
            $to_email = sanitize_email($_POST['test_email']);
            $subject = sanitize_text_field($_POST['test_subject']);
            $message = sanitize_textarea_field($_POST['test_message']);
            
            if (!empty($to_email)) {
                $test_result = wp_mail($to_email, $subject, $message);
                
                if ($test_result) {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Test email sent successfully!', 'm365-oauth-mailer') . '</p></div>';
                } else {
                    global $phpmailer;
                    $error = $phpmailer ? $phpmailer->ErrorInfo : __('Failed to send test email', 'm365-oauth-mailer');
                    echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a valid email address.', 'm365-oauth-mailer') . '</p></div>';
            }
        }
        
        $settings = get_option('m365_oauth_mailer_settings', array());
        $default_from = !empty($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('send_test_email'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email"><?php esc_html_e('To Email', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="test_email" name="test_email" 
                               value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                               class="regular-text" required />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="test_subject"><?php esc_html_e('Subject', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="test_subject" name="test_subject" 
                               value="<?php esc_attr_e('Test Email from M365 OAuth Mailer', 'm365-oauth-mailer'); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="test_message"><?php esc_html_e('Message', 'm365-oauth-mailer'); ?></label>
                    </th>
                    <td>
                        <textarea id="test_message" name="test_message" rows="5" class="large-text"><?php esc_html_e('This is a test email sent via Microsoft 365 OAuth2 App Registration.', 'm365-oauth-mailer'); ?></textarea>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Send Test Email', 'm365-oauth-mailer'), 'primary', 'send_test_email'); ?>
        </form>
        
        <div class="m365-test-info">
            <h3><?php esc_html_e('Test Information', 'm365-oauth-mailer'); ?></h3>
            <p>
                <?php esc_html_e('This will send a test email using your Microsoft 365 App Registration configuration.', 'm365-oauth-mailer'); ?>
            </p>
            <p>
                <strong><?php esc_html_e('From Email:', 'm365-oauth-mailer'); ?></strong> 
                <?php echo esc_html($default_from); ?>
            </p>
        </div>
        <?php
    }
}
