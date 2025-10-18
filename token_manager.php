<?php
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sentry_handler.php';

/**
 * Check token validity and expiration time
 * Returns array with 'is_valid' and 'expires_at' or false on error
 */
function checkAccessToken($access_token, $app_id, $app_secret)
{
  $url = "https://graph.facebook.com/v24.0/debug_token?input_token={$access_token}&access_token={$app_id}|{$app_secret}";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);

  $response = curl_exec($ch);

  if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    log_message("Error checking token validity: {$error}");
    logToSentry("Error checking token validity", 'warning', ['error' => $error]);
    return false;
  }

  curl_close($ch);
  $data = json_decode($response, true);

  if (isset($data['data'])) {
    $token_data = [
      'is_valid' => $data['data']['is_valid'] ?? false,
      'expires_at' => $data['data']['expires_at'] ?? null,
      'data_access_expires_at' => $data['data']['data_access_expires_at'] ?? null,
    ];

    log_message("Token check - Valid: " . ($token_data['is_valid'] ? 'Yes' : 'No') .
      ", Expires at: " . ($token_data['expires_at'] ? date('Y-m-d H:i:s', $token_data['expires_at']) : 'N/A'));

    return $token_data;
  }

  log_message("Unable to retrieve token data");
  return false;
}

/**
 * Check if token needs refresh (expires within threshold)
 * Default threshold: 7 days before expiry
 */
function needsRefresh($expires_at, $threshold_days = 7)
{
  if (!$expires_at) {
    return false;
  }

  $threshold_seconds = $threshold_days * 24 * 60 * 60;
  $current_time = time();
  $time_until_expiry = $expires_at - $current_time;

  if ($time_until_expiry <= 0) {
    log_message("Token has already expired");
    return true;
  }

  if ($time_until_expiry <= $threshold_seconds) {
    $days_left = round($time_until_expiry / (24 * 60 * 60), 1);
    log_message("Token expires in {$days_left} days - needs refresh");
    return true;
  }

  $days_left = round($time_until_expiry / (24 * 60 * 60), 1);
  log_message("Token is valid for {$days_left} more days - no refresh needed");
  return false;
}

/**
 * Refresh the access token to get a long-lived token
 */
function refreshAccessToken($access_token, $app_id, $app_secret)
{
  $url = "https://graph.facebook.com/v24.0/oauth/access_token?grant_type=fb_exchange_token&client_id={$app_id}&client_secret={$app_secret}&fb_exchange_token={$access_token}";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);

  $response = curl_exec($ch);

  if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    log_message("Error refreshing access token: {$error}");
    logToSentry("Error refreshing access token", 'error', ['error' => $error]);
    return null;
  }

  curl_close($ch);
  $data = json_decode($response, true);

  if (isset($data['access_token'])) {
    $expires_in = $data['expires_in'] ?? 'unknown';
    $expires_in_days = is_numeric($expires_in) ? round($expires_in / (24 * 60 * 60), 1) : 'unknown';

    log_message("Access token refreshed successfully - New token expires in: {$expires_in_days} days");
    logToSentry(
      "Access token refreshed successfully",
      'info',
      [
        'expires_in_seconds' => $expires_in,
        'expires_in_days' => $expires_in_days
      ]
    );

    return [
      'access_token' => $data['access_token'],
      'expires_in' => $expires_in
    ];
  }

  // Handle error response
  if (isset($data['error'])) {
    $error_msg = $data['error']['message'] ?? 'Unknown error';
    $error_code = $data['error']['code'] ?? 'N/A';
    $error_subcode = $data['error']['error_subcode'] ?? 'N/A';

    log_message("Failed to refresh access token: {$error_msg} (Code: {$error_code}, Subcode: {$error_subcode})");
    logToSentry(
      "Failed to refresh access token",
      'error',
      [
        'error_message' => $error_msg,
        'error_code' => $error_code,
        'error_subcode' => $error_subcode,
        'full_error' => $data['error']
      ]
    );
  } else {
    log_message("Failed to refresh access token: Unknown error");
    logToSentry("Failed to refresh access token - Unknown error", 'error', ['response' => $response]);
  }

  return null;
}

/**
 * Get valid access token, refreshing proactively if expiring soon
 * 
 * @param array $config Configuration array
 * @param int $refresh_threshold_days Days before expiry to trigger refresh (default: 7)
 * @return string|null Valid access token or null on failure
 */
function getValidAccessToken($config, $refresh_threshold_days = null)
{
  $access_token = $config['ACCESS_TOKEN'];
  $app_id = $config['APP_ID'];
  $app_secret = $config['APP_SECRET'];

  // Use config value if not provided as parameter
  if ($refresh_threshold_days === null) {
    $refresh_threshold_days = $config['TOKEN_REFRESH_THRESHOLD_DAYS'] ?? 7;
  }

  // Check token status
  $token_info = checkAccessToken($access_token, $app_id, $app_secret);

  if (!$token_info) {
    log_message("Unable to check token status");
    logToSentry("Unable to check token status - API error", 'error');
    return $access_token; // Return current token and hope for the best
  }

  // If token is not valid, it's already expired
  if (!$token_info['is_valid']) {
    log_message("Access token is INVALID/EXPIRED - Cannot refresh expired tokens!");
    logToSentry(
      "Access token is expired - Manual token generation required",
      'critical',
      [
        'expires_at' => $token_info['expires_at'] ? date('Y-m-d H:i:s', $token_info['expires_at']) : 'N/A'
      ]
    );
    return null;
  }

  // Token is valid, check if it needs refresh
  if (!needsRefresh($token_info['expires_at'], $refresh_threshold_days)) {
    log_message("Token is valid and doesn't need refresh yet");
    return $access_token;
  }

  // Token is valid but expiring soon - refresh it proactively
  log_message("Proactively refreshing access token before expiry...");
  logToSentry(
    "Proactively refreshing access token",
    'info',
    [
      'expires_at' => date('Y-m-d H:i:s', $token_info['expires_at']),
      'days_until_expiry' => round(($token_info['expires_at'] - time()) / (24 * 60 * 60), 1)
    ]
  );

  $refresh_result = refreshAccessToken($access_token, $app_id, $app_secret);

  if ($refresh_result && isset($refresh_result['access_token'])) {
    $new_token = $refresh_result['access_token'];

    // Update the token in config file
    if (updateConfigToken($new_token)) {
      log_message("Successfully refreshed and updated access token");
      return $new_token;
    } else {
      log_message("Token refreshed but failed to update config file - using new token in memory");
      return $new_token;
    }
  }

  // Failed to refresh but token is still valid
  log_message("Failed to refresh token, but current token is still valid - continuing with current token");
  logToSentry(
    "Failed to refresh token proactively - Current token still valid but manual attention needed",
    'warning',
    [
      'expires_at' => date('Y-m-d H:i:s', $token_info['expires_at']),
      'days_until_expiry' => round(($token_info['expires_at'] - time()) / (24 * 60 * 60), 1)
    ]
  );

  return $access_token;
}

/**
 * Update the access token in config.php file
 */
function updateConfigToken($new_token)
{
  $config_file = __DIR__ . '/config.php';

  if (!is_writable($config_file)) {
    log_message("Config file is not writable - cannot update token");
    logToSentry("Config file is not writable", 'error', ['file' => $config_file]);
    return false;
  }

  $config_content = file_get_contents($config_file);

  // Replace the ACCESS_TOKEN value
  $pattern = "/'ACCESS_TOKEN'\s*=>\s*'[^']*'/";
  $replacement = "'ACCESS_TOKEN'    => '{$new_token}'";
  $new_content = preg_replace($pattern, $replacement, $config_content);

  if ($new_content && $new_content !== $config_content) {
    if (file_put_contents($config_file, $new_content)) {
      log_message("Config file updated with new access token");
      return true;
    }
  }

  log_message("Failed to update config file with new token");
  logToSentry("Failed to update config file with new token", 'error');
  return false;
}
