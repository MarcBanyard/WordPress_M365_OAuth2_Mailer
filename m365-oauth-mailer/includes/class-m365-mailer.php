<?php
/**
 * M365 Mailer Class
 * 
 * Integrates with WordPress wp_mail() function
 */

if (!defined('ABSPATH')) {
    exit;
}

class M365_Mailer {
    
    private $graph_api;
    private $logger;
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
        $this->graph_api = new M365_Graph_API();
        $this->settings = get_option('m365_oauth_mailer_settings', array());
        
        // Hook into pre_wp_mail to intercept emails before PHPMailer processes them
        add_filter('pre_wp_mail', array($this, 'intercept_wp_mail'), 10, 2);
    }
    
    /**
     * Intercept wp_mail before PHPMailer processes it
     */
    public function intercept_wp_mail($null, $atts) {
        // Only handle if we have valid configuration
        if (!$this->is_configured()) {
            return $null;
        }
        
        // Extract email details from $atts
        $to = isset($atts['to']) ? $atts['to'] : '';
        $subject = isset($atts['subject']) ? $atts['subject'] : '';
        $message = isset($atts['message']) ? $atts['message'] : '';
        $headers = isset($atts['headers']) ? $atts['headers'] : '';
        $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();
        
        // Parse headers (matching WordPress's parsing logic)
        $from_email = '';
        $from_name = '';
        $cc_emails = array();
        $bcc_emails = array();
        $is_html = false;
        
        if (!empty($headers)) {
            if (!is_array($headers)) {
                $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $tempheaders = $headers;
            }
            
            foreach ($tempheaders as $header) {
                if (!str_contains($header, ':')) {
                    continue;
                }
                
                list($name, $content) = explode(':', trim($header), 2);
                $name = trim($name);
                $content = trim($content);
                
                switch (strtolower($name)) {
                    case 'from':
                        $bracket_pos = strpos($content, '<');
                        if (false !== $bracket_pos) {
                            if ($bracket_pos > 0) {
                                $from_name = substr($content, 0, $bracket_pos);
                                $from_name = str_replace('"', '', $from_name);
                                $from_name = trim($from_name);
                            }
                            $from_email = substr($content, $bracket_pos + 1);
                            $from_email = str_replace('>', '', $from_email);
                            $from_email = trim($from_email);
                        } elseif ('' !== trim($content)) {
                            $from_email = trim($content);
                        }
                        break;
                    case 'content-type':
                        if (str_contains($content, 'text/html')) {
                            $is_html = true;
                        }
                        break;
                    case 'cc':
                        $cc_emails = array_merge($cc_emails, array_map('trim', explode(',', $content)));
                        break;
                    case 'bcc':
                        $bcc_emails = array_merge($bcc_emails, array_map('trim', explode(',', $content)));
                        break;
                }
            }
        }
        
        // Use configured from email if set
        if (empty($from_email) && !empty($this->settings['from_email'])) {
            $from_email = $this->settings['from_email'];
        }
        
        // Use configured from name if set
        if (empty($from_name) && !empty($this->settings['from_name'])) {
            $from_name = $this->settings['from_name'];
        }
        
        // Default from email if still empty
        if (empty($from_email)) {
            $from_email = get_option('admin_email');
        }
        
        // Parse recipients
        if (!is_array($to)) {
            $to = array_map('trim', explode(',', $to));
        }
        
        // Parse attachments (they come as file paths)
        $attachment_files = array();
        if (!empty($attachments)) {
            if (!is_array($attachments)) {
                $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
            }
            foreach ($attachments as $attachment) {
                $attachment = trim($attachment);
                if (!empty($attachment) && file_exists($attachment)) {
                    $attachment_files[] = $attachment;
                }
            }
        }
        
        // Send email via our API
        $result = $this->send_email(
            $from_email,
            $to,
            $subject,
            $message,
            $is_html ? 'HTML' : 'Text',
            $cc_emails,
            $bcc_emails,
            $attachment_files,
            $from_name
        );
        
        // Log the result
        if ($this->logger && !empty($this->settings['enable_logging'])) {
            $this->logger->log_email($from_email, $to, $subject, $result);
        }
        
        // Return result to short-circuit wp_mail
        if (is_wp_error($result)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Send email via Microsoft Graph API
     */
    private function send_email($from_email, $to_emails, $subject, $body, $content_type, $cc_emails = array(), $bcc_emails = array(), $attachments = array(), $from_name = '') {
        // Validate from email
        if (empty($from_email)) {
            return new WP_Error('no_from_email', __('From email address is required', 'm365-oauth-mailer'));
        }
        
        // Validate recipients
        if (empty($to_emails)) {
            return new WP_Error('no_recipients', __('At least one recipient is required', 'm365-oauth-mailer'));
        }
        
        // Send via Graph API
        return $this->graph_api->send_mail(
            $from_email,
            $to_emails,
            $subject,
            $body,
            $content_type,
            $cc_emails,
            $bcc_emails,
            $attachments
        );
    }
    
    /**
     * Check if plugin is properly configured
     */
    private function is_configured() {
        return defined('M365_OAUTH2_SMTP_CLIENT_ID') 
            && defined('M365_OAUTH2_SMTP_CLIENT_SECRET')
            && !empty(M365_OAUTH2_SMTP_CLIENT_ID)
            && !empty(M365_OAUTH2_SMTP_CLIENT_SECRET);
    }
}
