# Facebook Leads to CRM Forwarder

A PHP-based webhook handler for Facebook Lead Ads that automatically captures lead data, maps it to your CRM format, and forwards it to your CRM system. Includes **proactive token refresh**, automatic token management, comprehensive error logging, and Sentry integration for monitoring.

**Repository:** [kazimshah39/facebook-leads-to-crm-forwarder](https://github.com/kazimshah39/facebook-leads-to-crm-forwarder)

## Features

- **Webhook Verification**: Secure webhook verification for Facebook Lead Ads
- **Automatic Lead Fetching**: Retrieves lead data from Facebook Graph API
- **Data Mapping**: Flexible field mapping system to transform Facebook lead data to your CRM format
- **CRM Integration**: Forwards mapped leads to your CRM endpoint
- **Proactive Token Management**: Automatically refreshes access tokens **before** expiry (Facebook doesn't allow refreshing expired tokens)
- **Token Health Monitoring**: Continuously checks token validity and expiration status
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

1. **Clone the repository**

   ```bash
   git clone https://github.com/kazimshah39/facebook-leads-to-crm-forwarder.git
   cd facebook-leads-to-crm-forwarder
   ```

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
     'TOKEN_REFRESH_THRESHOLD_DAYS' => 7, // Refresh token when < 7 days until expiry
   ];
   ```

5. **Set proper file permissions**

   The `config.php` file must be writable by the web server for automatic token updates:

   ```bash
   chmod 664 config.php
   chown www-data:www-data config.php  # Adjust user/group for your server
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
     - Generate a long-lived token (60 days validity)

3. **Configure Webhook**

   - Go to Webhooks in your Facebook App
   - Subscribe to Page webhooks
   - Callback URL: `https://yourdomain.com/index.php`
   - Verify Token: Same as `VERIFY_TOKEN` in your config
   - Subscribe to `leadgen` field

4. **Subscribe Your Page**
   - Subscribe your Facebook Page to receive leadgen events

## Configuration Options

| Option                         | Description                                           | Required | Default |
| ------------------------------ | ----------------------------------------------------- | -------- | ------- |
| `SITE_NAME`                    | Identifier for your site (used in Sentry tags)        | Yes      | -       |
| `VERIFY_TOKEN`                 | Token for webhook verification                        | Yes      | -       |
| `APP_ID`                       | Facebook App ID                                       | Yes      | -       |
| `APP_SECRET`                   | Facebook App Secret                                   | Yes      | -       |
| `ACCESS_TOKEN`                 | Facebook Page Access Token                            | Yes      | -       |
| `FORWARD_URL`                  | Your CRM endpoint URL                                 | Yes      | -       |
| `SENTRY_DSN`                   | Sentry DSN for error tracking                         | No       | -       |
| `ENABLE_LOGS`                  | Enable/disable file logging                           | No       | false   |
| `LOG_FILE`                     | Path to log file                                      | No       | -       |
| `TOKEN_REFRESH_THRESHOLD_DAYS` | Days before expiry to trigger automatic token refresh | No       | 7       |

## Proactive Token Management

### How It Works

Facebook **does not allow** refreshing expired tokens. To prevent downtime, this system proactively refreshes tokens **before** they expire.

**Token Lifecycle:**

```
┌─────────────────────────────────────────────────────────────┐
│  Token Generated (60 days validity)                          │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Days 1-53: Token used normally                              │
│  ✅ Status: Valid and healthy                                │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Day 53: Automatic refresh triggered (7 days threshold)      │
│  🔄 Action: Exchange current token for new 60-day token      │
│  📝 Action: Update config.php automatically                  │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  New token active (60 days validity)                         │
│  ✅ Process repeats automatically                            │
└─────────────────────────────────────────────────────────────┘
```

### Refresh Threshold Settings

Configure when automatic refresh triggers:

```php
'TOKEN_REFRESH_THRESHOLD_DAYS' => 7,  // Recommended: Refresh 7 days before expiry
```

**Recommended Values:**

- **7 days** (default): Balanced approach, gives buffer for issues
- **14 days**: Conservative, more time for manual intervention if needed
- **3 days**: Aggressive, closer to expiry (not recommended)

### Token Health Checks

Every webhook request automatically:

1. Checks if token is valid
2. Retrieves expiration timestamp
3. Calculates days until expiry
4. Triggers refresh if within threshold
5. Updates config.php with new token
6. Logs all actions to Sentry

### What Happens When...

| Scenario                          | System Behavior                                 | Alert Level |
| --------------------------------- | ----------------------------------------------- | ----------- |
| Token has 30+ days                | ✅ Uses current token normally                  | None        |
| Token has <7 days                 | 🔄 Automatically refreshes token                | Info        |
| Refresh succeeds                  | ✅ Updates config.php, continues normally       | Info        |
| Refresh fails (token still valid) | ⚠️ Uses current token, logs warning             | Warning     |
| Token already expired             | ❌ Cannot refresh, requires manual intervention | Critical    |
| Config.php not writable           | ⚠️ Uses new token in memory, cannot persist     | Error       |

## Data Mapping

The webhook handler maps Facebook Lead Ads fields to CRM fields. Edit `data_mapper.php` to customize field mapping:

```php
case 'full_name':
  $mapped_data['full_name'] = $value;
  break;
case 'email':
  $mapped_data['email'] = $value;
  break;
case 'custom_question':
  $mapped_data['your_crm_field'] = $value;
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
   - **Checks token health and refreshes if needed**
   - Extracts `leadgen_id` from payload
   - Fetches full lead data from Graph API
   - Maps fields to CRM format
   - Forwards to configured CRM endpoint
   - Logs success/failure to Sentry and local logs

3. **Token Management (Automatic)**
   - Validates access token before API calls
   - Checks expiration timestamp
   - Refreshes token proactively when within threshold
   - Updates `config.php` with new token automatically
   - Alerts via Sentry for all token events

## Monitoring & Logging

### File Logging

Enable file logging in `config.php` for debugging:

```php
'ENABLE_LOGS' => true,
'LOG_FILE' => __DIR__ . '/facebook_webhook_log.txt',
```

**Log entries include:**

- Token validity checks and expiration dates
- Proactive refresh triggers and results
- Lead fetching operations
- CRM forwarding status
- Error details

### Sentry Integration

Sentry provides real-time error tracking with context:

**Token Management Events:**

- ℹ️ **Info**: Successful token refresh with new expiration
- ⚠️ **Warning**: Refresh failed but token still valid
- 🚨 **Critical**: Token expired, manual intervention required
- ❌ **Error**: API errors, config write failures

**Other Events:**

- API failures
- CRM forwarding errors
- Invalid webhook signatures
- Exception tracking

All events are tagged with your `SITE_NAME` for multi-site setups.

## Testing the Token Refresh System

### 1. Check Current Token Status

Add this temporarily to `index.php` after token validation:

```php
$token_info = checkAccessToken($config['ACCESS_TOKEN'], $config['APP_ID'], $config['APP_SECRET']);
log_message("Token Status: " . json_encode($token_info));
```

This will log your token's expiration date and validity.

### 2. Simulate Expiring Token

Temporarily set a high threshold to trigger immediate refresh:

```php
'TOKEN_REFRESH_THRESHOLD_DAYS' => 60, // Will trigger refresh immediately
```

Trigger a webhook event and check logs for:

```
Token expires in X days - needs refresh
Access token refreshed successfully - New token expires in: Y days
Config file updated with new access token
```

### 3. Verify Config Update

After successful refresh, check `config.php` to confirm the `ACCESS_TOKEN` value has been updated with a new token.

### 4. Monitor Sentry

Check Sentry dashboard for:

- "Access token refreshed successfully" (Info level)
- Token expiration details in event context

## Troubleshooting

### Token Expired Before Refresh

**Symptoms:**

- Critical Sentry alert: "Token expired - Manual token generation required"
- Leads not being fetched
- "Session has expired" errors in logs

**Solutions:**

1. Lower `TOKEN_REFRESH_THRESHOLD_DAYS` to 14 or higher
2. Ensure webhook is receiving regular traffic (token checks happen on requests)
3. Generate new token manually from Facebook:
   - Go to Graph API Explorer
   - Select your app and page
   - Get long-lived token with `pages_manage_ads` and `leads_retrieval` permissions
   - Update `ACCESS_TOKEN` in `config.php`

### Token Refresh Keeps Failing

**Check:**

- ✅ Is `APP_SECRET` correct in config.php?
- ✅ Is current token still valid (not expired)?
- ✅ Network connectivity to Facebook API
- ✅ Facebook API status (check Facebook Developer Status)

**Debug:**

```bash
# Check logs for detailed error
tail -f facebook_webhook_log.txt

# Look for error codes in Sentry
# Error 190 / Subcode 463 = Token expired (manual action needed)
# Error 190 / Subcode 460 = Password changed (regenerate token)
```

### Config File Not Updating

**Symptoms:**

- Token refreshed successfully in logs
- But `config.php` still has old token
- Warning: "Config file is not writable"

**Solutions:**

```bash
# Check file permissions
ls -la config.php

# Set proper permissions
chmod 664 config.php

# Set proper ownership (adjust for your server)
chown www-data:www-data config.php

# Check disk space
df -h
```

### Lead Data Not Fetching

**Check:**

- ✅ Verify `ACCESS_TOKEN` has correct permissions
- ✅ Check token validity in [Graph API Explorer](https://developers.facebook.com/tools/explorer)
- ✅ Review Sentry for API error messages
- ✅ Check logs for token status before API call

### CRM Not Receiving Data

**Check:**

- ✅ Verify `FORWARD_URL` is accessible
- ✅ Check CRM endpoint logs
- ✅ Review mapped data format in logs
- ✅ Test CRM endpoint with curl:
  ```bash
  curl -X POST https://your-crm.com/api/leads \
    -H "Content-Type: application/json" \
    -d '{"facebook_lead_id":"test","email":"test@example.com"}'
  ```

### Invalid Signature Errors

**Check:**

- ✅ Ensure `APP_SECRET` is correct
- ✅ Verify Facebook is sending to the correct endpoint
- ✅ Check for middleware/proxy modifying request body

## Security Considerations

1. **Keep credentials secure**: Never commit `config.php` to version control
2. **Use HTTPS**: Always use SSL/TLS for webhook endpoint
3. **Validate signatures**: Script validates all incoming webhooks using HMAC-SHA256
4. **Restrict file permissions**:
   ```bash
   chmod 664 config.php  # Readable by owner/group, writable by owner
   chmod 644 *.php       # Readable by all, writable by owner only
   ```
5. **Rotate tokens**: System handles automatic rotation, but monitor for failures
6. **Monitor Sentry**: Set up alerts for critical errors
7. **Keep logs private**: Don't expose log files via web server

## File Structure

```
.
├── composer.json           # Dependencies (Sentry)
├── config.example.php      # Configuration template
├── config.php             # Your configuration (create this)
├── index.php              # Main webhook handler
├── verifier.php           # Webhook verification logic
├── lead_fetcher.php       # Facebook API interaction
├── token_manager.php      # Token validation & proactive refresh ⭐
├── data_mapper.php        # Field mapping logic
├── crm_forwarder.php      # CRM integration
├── sentry_handler.php     # Sentry logging utilities
├── logger.php             # File logging utilities
└── README.md              # This file
```

## Maintenance Checklist

### Daily

- [ ] No critical alerts in Sentry
- [ ] Leads being forwarded successfully

### Weekly

- [ ] Review Sentry for warning-level events
- [ ] Check token expiration date in logs
- [ ] Verify CRM is receiving all leads

### Monthly

- [ ] Confirm token was auto-refreshed (check Sentry/logs)
- [ ] Review any failed refresh attempts
- [ ] Clear old log files if disk space is limited

### Quarterly

- [ ] Review and update field mappings if CRM changes
- [ ] Test webhook verification with Facebook
- [ ] Update dependencies: `composer update`

## Advanced Configuration

### Multiple Sites/Pages

If managing multiple Facebook pages:

1. Create separate config files:

   ```
   config_site1.php
   config_site2.php
   ```

2. Use different `SITE_NAME` values for Sentry filtering:

   ```php
   'SITE_NAME' => 'site1.com',  // Site 1
   'SITE_NAME' => 'site2.com',  // Site 2
   ```

3. Deploy separate instances or use routing logic in `index.php`

### Custom Token Refresh Logic

To add custom behavior on token refresh, modify `token_manager.php`:

```php
function updateConfigToken($new_token)
{
  // ... existing code ...

  // Add your custom logic here
  // Example: Send email notification
  mail('admin@yoursite.com', 'Token Refreshed', 'New token generated successfully');

  // Example: Update database
  // $db->query("UPDATE settings SET fb_token = ? WHERE site = ?", [$new_token, $site]);

  return true;
}
```

## API Reference

### Facebook Graph API Endpoints Used

**1. Debug Token** (Check validity and expiration)

```
GET https://graph.facebook.com/v24.0/debug_token
  ?input_token={token_to_check}
  &access_token={app_id}|{app_secret}
```

**Response:**

```json
{
  "data": {
    "is_valid": true,
    "expires_at": 1760770800,
    "app_id": "123456789"
  }
}
```

**2. Token Exchange** (Refresh token)

```
GET https://graph.facebook.com/v24.0/oauth/access_token
  ?grant_type=fb_exchange_token
  &client_id={app_id}
  &client_secret={app_secret}
  &fb_exchange_token={current_token}
```

**Success Response:**

```json
{
  "access_token": "new_long_lived_token",
  "token_type": "bearer",
  "expires_in": 5183939
}
```

**Error Response:**

```json
{
  "error": {
    "message": "Error validating access token: Session has expired...",
    "type": "OAuthException",
    "code": 190,
    "error_subcode": 463
  }
}
```

**3. Fetch Lead** (Get lead data)

```
GET https://graph.facebook.com/v24.0/{leadgen_id}
  ?access_token={page_access_token}
```

## Performance Considerations

- Token validation happens on every webhook request (adds ~100-200ms)
- Token refresh adds ~500ms-1s when triggered
- Consider caching token validity in memory for high-traffic scenarios
- Config file updates are atomic (race conditions handled by filesystem)

## Upgrade Notes

### From Version Without Proactive Refresh

1. Update `token_manager.php` with new version
2. Add `TOKEN_REFRESH_THRESHOLD_DAYS` to `config.php`
3. Ensure `config.php` is writable: `chmod 664 config.php`
4. Monitor first refresh cycle in logs/Sentry
5. Old behavior: Tried refreshing expired tokens (failed)
6. New behavior: Refreshes before expiry (succeeds)

## License

This project is open source and available under the MIT License.

## Author

Created and maintained by [Kazim Shah](https://github.com/kazimshah39)

## Support

For issues related to:

- **This Project**: Open an issue on [GitHub](https://github.com/kazimshah39/facebook-leads-to-crm-forwarder/issues)
- **Facebook API**: Check [Facebook Developers Documentation](https://developers.facebook.com/docs/marketing-api/guides/lead-ads)
- **Token Management**: Review [Facebook Access Tokens Guide](https://developers.facebook.com/docs/facebook-login/guides/access-tokens)
- **Sentry**: Visit [Sentry Documentation](https://docs.sentry.io/)
- **Debugging**: Review logs and Sentry events for debugging information

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request to the [repository](https://github.com/kazimshah39/facebook-leads-to-crm-forwarder).

When contributing:

1. Test token refresh logic thoroughly
2. Ensure backward compatibility
3. Update README with configuration changes
4. Add Sentry logging for new features
5. Follow existing code style
