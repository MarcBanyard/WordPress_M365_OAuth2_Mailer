# M365 OAuth Mailer

A WordPress plugin that sends emails via Microsoft 365 using OAuth2 App Registration with application permissions. Works seamlessly with shared mailboxes and user mailboxes.

## Features

- ✅ **OAuth2 App Registration** - Uses client credentials flow (application permissions)
- ✅ **Shared Mailbox Support** - Works with shared mailboxes and user mailboxes
- ✅ **No Hardcoded Settings** - All configuration via wp-config.php
- ✅ **Email Logging** - Comprehensive logging with configurable retention
- ✅ **WordPress Integration** - Seamlessly integrates with wp_mail() function
- ✅ **Admin Interface** - Easy-to-use settings page with comprehensive controls
- ✅ **Test Email** - Built-in test email functionality

## Installation

1. Upload the `m365-oauth-mailer` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Microsoft 365 App Registration credentials in `wp-config.php`

## Configuration

Add the following constants to your `wp-config.php` file:

```php
/** M365_Oauth2-SMTP Email Plugin Settings **/
define( 'M365_OAUTH2_SMTP_TENANT_ID', 'your-tenant-id-here' ); // Or 'common' for multi-tenant
define( 'M365_OAUTH2_SMTP_CLIENT_ID', 'your-client-id-here' );
define( 'M365_OAUTH2_SMTP_CLIENT_SECRET', 'your-client-secret-here' );
```

### Microsoft 365 App Registration Setup

1. Go to Azure Portal → Microsoft Entra ID → App registrations
2. Create a new app registration or use an existing one
3. Go to **API permissions** → Add a permission → Microsoft Graph → **Application permissions**
4. Add the following permissions:
   - `Mail.Send` (to send emails)
5. Click **Grant admin consent** to approve the permissions
6. Go to **Certificates & secrets** → New client secret
7. Copy the Tenant ID, Client ID, and Client Secret to your `wp-config.php`

## Usage

Once configured, the plugin automatically intercepts all WordPress emails sent via `wp_mail()` and sends them through Microsoft 365 Graph API.

### Settings Page

Navigate to **Settings → M365 OAuth Mailer** to:

- Configure default From email and name
- Enable/disable email logging
- Set log retention period (in days)
- View email logs
- Send test emails

### Email Logs

The plugin logs all email attempts with:
- From/To addresses
- Subject
- Status (success/failed)
- Error messages (if failed)
- Timestamp

Logs are automatically cleaned up based on your retention setting.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Microsoft 365 account with Exchange Online
- Azure App Registration with Mail.Send application permission

## License

GPL v2 or later

## Support

For issues and feature requests, please visit the plugin repository.
