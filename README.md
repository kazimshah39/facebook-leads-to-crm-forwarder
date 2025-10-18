# Facebook Lead Ads Webhook Handler

A PHP-based webhook handler for Facebook Lead Ads that automatically captures lead data, maps it to your CRM format, and forwards it to your CRM system. Includes automatic token refresh, comprehensive error logging, and Sentry integration for monitoring.

## Features

- **Webhook Verification**: Secure webhook verification for Facebook Lead Ads
- **Automatic Lead Fetching**: Retrieves lead data from Facebook Graph API
- **Data Mapping**: Flexible field mapping system to transform Facebook lead data to your CRM format
- **CRM Integration**: Forwards mapped leads to your CRM endpoint
- **Token Management**: Automatic access token validation and refresh
- **Error Monitoring**: Integrated Sentry error tracking and monitoring
- **Logging**: Optional file-based logging for debugging
- **Security**: HMAC signature verification for incoming webhooks

## Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Composer for dependency management
- Facebook App with Lead Ads permissions
- Sentry account (optional, for error monitoring)

## Installation

1. **Clone or download this repository**

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Configure the application**

   Rename `config.example.php` to `config.php`:

   ```bash
   cp config.example.php config.php
   ```

4. **Edit `config.php` with your credentials**

   ```php
   return [
     'SITE_NAME'       => 'yoursite.com',
     'VERIFY_TOKEN'    => 'your_secure_verify_token',
     'APP_ID'          => 'your_facebook_app_id',
     'APP_SECRET'      => 'your_facebook_app_secret',
     'ACCESS_TOKEN'    => 'your_page_access_token',
     'FORWARD_URL'     => 'https://your-crm-endpoint.com/api/leads',
     'SENTRY_DSN'      => 'your_sentry_dsn', // Optional
     'ENABLE_LOGS'     => true, // Set to false in production
     'LOG_FILE'        => __DIR__ . '/facebook_webhook_log.txt',
   ];
   ```

## Facebook App Setup

1. **Create a Facebook App**

   - Go to [Facebook Developers](https://developers.facebook.com/)
   - Create a new app or use an existing one
   - Add "Lead Ads" product to your app

2. **Get Your Credentials**

   - **App ID**: Found in App Settings → Basic
   - **App Secret**: Found in App Settings → Basic
   - **Access Token**: Generate from Facebook Business Manager or Graph API Explorer
     - Token needs `pages_manage_ads` and `leads_retrieval` permissions

3. **Configure Webhook**

   - Go to Webhooks in your Facebook App
   - Subscribe to Page webhooks
   - Callback URL: `https://yourdomain.com/index.php`
   - Verify Token: Same as `VERIFY_TOKEN` in your config
   - Subscribe to `leadgen` field

4. **Subscribe Your Page**
   - Subscribe your Facebook Page to receive leadgen events

## Configuration Options

| Option         | Description                                    | Required |
| -------------- | ---------------------------------------------- | -------- |
| `SITE_NAME`    | Identifier for your site (used in Sentry tags) | Yes      |
| `VERIFY_TOKEN` | Token for webhook verification                 | Yes      |
| `APP_ID`       | Facebook App ID                                | Yes      |
| `APP_SECRET`   | Facebook App Secret                            | Yes      |
| `ACCESS_TOKEN` | Facebook Page Access Token                     | Yes      |
| `FORWARD_URL`  | Your CRM endpoint URL                          | Yes      |
| `SENTRY_DSN`   | Sentry DSN for error tracking                  | No       |
| `ENABLE_LOGS`  | Enable/disable file logging                    | No       |
| `LOG_FILE`     | Path to log file                               | No       |

## Data Mapping

The webhook handler maps Facebook Lead Ads fields to CRM fields. Edit `data_mapper.php` to customize field mapping:

```php
case 'full_name':
  $mapped_data['full_name'] = $value;
  break;
case 'email':
  $mapped_data['email'] = $value;
  break;
// Add your custom mappings here
```

## How It Works

1. **Webhook Verification (GET request)**

   - Facebook sends a verification request
   - Script validates the verify token
   - Returns challenge to confirm subscription

2. **Lead Processing (POST request)**

   - Receives webhook notification from Facebook
   - Validates HMAC signature for security
   - Extracts `leadgen_id` from payload
   - Fetches full lead data from Graph API
   - Maps fields to CRM format
   - Forwards to configured CRM endpoint
   - Logs success/failure to Sentry and local logs

3. **Token Management**
   - Automatically validates access token before API calls
   - Refreshes expired tokens using token exchange
   - Updates `config.php` with new token
   - Alerts via Sentry if refresh fails

## Monitoring & Logging

### File Logging

Enable file logging in `config.php` for debugging:

```php
'ENABLE_LOGS' => true,
'LOG_FILE' => __DIR__ . '/facebook_webhook_log.txt',
```

### Sentry Integration

Sentry provides real-time error tracking with context:

- Token expiration alerts
- API failures
- CRM forwarding errors
- Invalid webhook signatures
- Exception tracking

All events are tagged with your `SITE_NAME` for multi-site setups.

## Troubleshooting

### Webhook Verification Fails

- Ensure `VERIFY_TOKEN` matches the token set in Facebook App settings
- Check server logs for incoming parameters

### Lead Data Not Fetching

- Verify `ACCESS_TOKEN` has correct permissions
- Check token validity in [Graph API Explorer](https://developers.facebook.com/tools/explorer)
- Review Sentry for API error messages

### Token Auto-Refresh Fails

- Ensure `config.php` is writable by the web server
- Verify `APP_SECRET` is correct
- Check Sentry for refresh error details

### CRM Not Receiving Data

- Verify `FORWARD_URL` is accessible
- Check CRM endpoint logs
- Review mapped data format in logs

### Invalid Signature Errors

- Ensure `APP_SECRET` is correct
- Verify Facebook is sending to the correct endpoint

## Security Considerations

1. **Keep credentials secure**: Never commit `config.php` to version control
2. **Use HTTPS**: Always use SSL/TLS for webhook endpoint
3. **Validate signatures**: Script validates all incoming webhooks
4. **Restrict file permissions**: Set appropriate file permissions on production
5. **Rotate tokens**: Regularly rotate access tokens and app secrets

## File Structure

```
.
├── composer.json           # Dependencies
├── config.example.php      # Configuration template
├── config.php             # Your configuration (create this)
├── index.php              # Main webhook handler
├── verifier.php           # Webhook verification logic
├── lead_fetcher.php       # Facebook API interaction
├── token_manager.php      # Token validation & refresh
├── data_mapper.php        # Field mapping logic
├── crm_forwarder.php      # CRM integration
├── sentry_handler.php     # Sentry logging utilities
├── logger.php             # File logging utilities
└── README.md              # This file
```

## License

This project is provided as-is for use with Facebook Lead Ads integration.

## Support

For issues related to:

- **Facebook API**: Check [Facebook Developers Documentation](https://developers.facebook.com/docs/marketing-api/guides/lead-ads)
- **Sentry**: Visit [Sentry Documentation](https://docs.sentry.io/)
- **This Script**: Review logs and Sentry events for debugging information
