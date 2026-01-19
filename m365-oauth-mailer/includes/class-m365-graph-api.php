<?php
/**
 * Microsoft Graph API Handler
 * 
 * Handles OAuth2 authentication and API requests to Microsoft Graph
 */

if (!defined('ABSPATH')) {
    exit;
}

class M365_Graph_API {
    
    private $tenant_id;
    private $client_id;
    private $client_secret;
    private $access_token;
    private $token_expires;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get tenant ID, default to 'common' if not set
        $this->tenant_id = defined('M365_OAUTH2_SMTP_TENANT_ID') 
            ? M365_OAUTH2_SMTP_TENANT_ID 
            : 'common';
        
        $this->client_id = defined('M365_OAUTH2_SMTP_CLIENT_ID') 
            ? M365_OAUTH2_SMTP_CLIENT_ID 
            : '';
        
        $this->client_secret = defined('M365_OAUTH2_SMTP_CLIENT_SECRET') 
            ? M365_OAUTH2_SMTP_CLIENT_SECRET 
            : '';
    }
    
    /**
     * Get access token using client credentials flow
     * 
     * @return string|WP_Error Access token or error
     */
    public function get_access_token() {
        // Check if we have a valid cached token
        if ($this->access_token && $this->token_expires && time() < ($this->token_expires - 300)) {
            return $this->access_token;
        }
        
        // Validate configuration
        if (empty($this->client_id) || empty($this->client_secret)) {
            return new WP_Error('missing_config', __('M365 OAuth Mailer: Client ID and Client Secret must be defined in wp-config.php', 'm365-oauth-mailer'));
        }
        
        // Build token request
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $body = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials',
            'scope' => 'https://graph.microsoft.com/.default'
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_message = isset($response_data['error_description']) 
                ? $response_data['error_description'] 
                : __('Failed to obtain access token', 'm365-oauth-mailer');
            
            return new WP_Error('token_error', sprintf(__('M365 OAuth Mailer: %s', 'm365-oauth-mailer'), $error_message), $response_data);
        }
        
        if (!isset($response_data['access_token'])) {
            return new WP_Error('no_token', __('M365 OAuth Mailer: No access token in response', 'm365-oauth-mailer'));
        }
        
        // Cache the token
        $this->access_token = $response_data['access_token'];
        $this->token_expires = time() + (isset($response_data['expires_in']) ? $response_data['expires_in'] : 3600);
        
        return $this->access_token;
    }
    
    /**
     * Send email via Microsoft Graph API
     * 
     * @param string $from_email Sender email address
     * @param string $to_email Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param string $content_type Content type (HTML or Text)
     * @param array $cc_emails CC recipients (optional)
     * @param array $bcc_emails BCC recipients (optional)
     * @param array $attachments Attachment file paths (optional)
     * 
     * @return array|WP_Error Response data or error
     */
    public function send_mail($from_email, $to_email, $subject, $body, $content_type = 'HTML', $cc_emails = array(), $bcc_emails = array(), $attachments = array()) {
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Build email message
        $message = array(
            'message' => array(
                'subject' => $subject,
                'body' => array(
                    'contentType' => $content_type,
                    'content' => $body
                ),
                'from' => array(
                    'emailAddress' => array(
                        'address' => $from_email
                    )
                ),
                'toRecipients' => array()
            )
        );
        
        // Add recipients
        if (is_array($to_email)) {
            foreach ($to_email as $email) {
                $message['message']['toRecipients'][] = array(
                    'emailAddress' => array('address' => $email)
                );
            }
        } else {
            $message['message']['toRecipients'][] = array(
                'emailAddress' => array('address' => $to_email)
            );
        }
        
        // Add CC recipients
        if (!empty($cc_emails)) {
            $message['message']['ccRecipients'] = array();
            foreach ($cc_emails as $email) {
                $message['message']['ccRecipients'][] = array(
                    'emailAddress' => array('address' => $email)
                );
            }
        }
        
        // Add BCC recipients
        if (!empty($bcc_emails)) {
            $message['message']['bccRecipients'] = array();
            foreach ($bcc_emails as $email) {
                $message['message']['bccRecipients'][] = array(
                    'emailAddress' => array('address' => $email)
                );
            }
        }
        
        // Handle attachments
        if (!empty($attachments)) {
            $message['message']['attachments'] = array();
            foreach ($attachments as $attachment_path) {
                if (file_exists($attachment_path)) {
                    $file_content = file_get_contents($attachment_path);
                    $file_name = basename($attachment_path);
                    $file_size = filesize($attachment_path);
                    $mime_type = mime_content_type($attachment_path);
                    
                    $message['message']['attachments'][] = array(
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'name' => $file_name,
                        'contentType' => $mime_type,
                        'contentBytes' => base64_encode($file_content),
                        'size' => $file_size
                    );
                }
            }
        }
        
        // Send email via Graph API
        $endpoint = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($from_email) . "/sendMail";
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => wp_json_encode($message),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            // Success - return response headers if available
            $headers = wp_remote_retrieve_headers($response);
            return array(
                'success' => true,
                'code' => $response_code,
                'headers' => $headers->getAll()
            );
        } else {
            // Error response
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : __('Failed to send email', 'm365-oauth-mailer');
            
            $error_code = isset($error_data['error']['code']) 
                ? $error_data['error']['code'] 
                : 'unknown_error';
            
            return new WP_Error($error_code, sprintf(__('M365 OAuth Mailer: %s', 'm365-oauth-mailer'), $error_message), array(
                'code' => $response_code,
                'response' => $error_data
            ));
        }
    }
}
