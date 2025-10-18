<?php
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sentry_handler.php';

/**
 * Check if the access token is still valid
 */
function isAccessTokenValid($access_token, $app_id, $app_secret)
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

  if (isset($data['data']['is_valid']) && $data['data']['is_valid']) {
    log_message("Access token is valid");
    return true;
  }

  log_message("Access token is invalid or expired");
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
    log_message("Access token refreshed successfully");
    logToSentry("Access token refreshed successfully", 'info');
    return $data['access_token'];
  }

  $error_msg = isset($data['error']) ? json_encode($data['error']) : 'Unknown error';
  log_message("Failed to refresh access token: {$error_msg}");
  logToSentry("Failed to refresh access token", 'error', ['error_details' => $error_msg]);
  return null;
}

/**
 * Get valid access token, refreshing if necessary
 */
function getValidAccessToken($config)
{
  $access_token = $config['ACCESS_TOKEN'];
  $app_id = $config['APP_ID'];
  $app_secret = $config['APP_SECRET'];

  // Check if token is valid
  if (isAccessTokenValid($access_token, $app_id, $app_secret)) {
    return $access_token;
  }

  // Token is invalid, try to refresh
  log_message("Access token is invalid, attempting to refresh...");
  logToSentry("Access token expired, attempting auto-refresh", 'info');

  $new_token = refreshAccessToken($access_token, $app_id, $app_secret);

  if ($new_token) {
    // Update the token in config file
    updateConfigToken($new_token);
    return $new_token;
  }

  // Failed to refresh
  logToSentry("Failed to refresh expired access token - Manual intervention required", 'critical');
  return null;
}

/**
 * Update the access token in config.php file
 */
function updateConfigToken($new_token)
{
  $config_file = __DIR__ . '/config.php';
  $config_content = file_get_contents($config_file);

  // Replace the ACCESS_TOKEN value
  $pattern = "/'ACCESS_TOKEN'\s*=>\s*'[^']*'/";
  $replacement = "'ACCESS_TOKEN'    => '{$new_token}'";
  $new_content = preg_replace($pattern, $replacement, $config_content);

  if ($new_content && $new_content !== $config_content) {
    file_put_contents($config_file, $new_content);
    log_message("Config file updated with new access token");
    return true;
  }

  log_message("Failed to update config file with new token");
  logToSentry("Failed to update config file with new token", 'error');
  return false;
}
