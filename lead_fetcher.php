<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sentry_handler.php';
require_once __DIR__ . '/token_manager.php';

function fetchLeadData($leadgen_id)
{
  $config = require __DIR__ . '/config.php';

  // Get valid access token (will auto-refresh if needed)
  $access_token = getValidAccessToken($config);

  if (!$access_token) {
    log_message('Unable to get valid access token');
    logToSentry(
      'Unable to get valid access token to fetch lead data',
      'critical',
      ['leadgen_id' => $leadgen_id],
      ['component' => 'lead_fetcher']
    );
    http_response_code(500);
    exit('Error: Invalid access token');
  }

  $graph_url = "https://graph.facebook.com/v24.0/{$leadgen_id}?access_token={$access_token}";
  $response = @file_get_contents($graph_url);

  if ($response === false) {
    $error_message = error_get_last()['message'] ?? 'Unknown error fetching lead';
    log_message('Error fetching lead: ' . $error_message);
    logToSentry(
      'Error fetching lead from Facebook API',
      'error',
      [
        'leadgen_id' => $leadgen_id,
        'error' => $error_message,
        'graph_url' => str_replace($access_token, '[REDACTED]', $graph_url)
      ],
      ['component' => 'lead_fetcher']
    );
    http_response_code(500);
    exit('Error fetching lead');
  }

  $lead_data = json_decode($response, true);

  if (isset($lead_data['error'])) {
    $error_info = $lead_data['error'];
    log_message('Error fetching lead: ' . json_encode($error_info));
    logToSentry(
      'Facebook API returned error',
      'error',
      [
        'leadgen_id' => $leadgen_id,
        'error_code' => $error_info['code'] ?? null,
        'error_message' => $error_info['message'] ?? null,
        'error_type' => $error_info['type'] ?? null
      ],
      ['component' => 'lead_fetcher']
    );
    http_response_code(500);
    exit('Error fetching lead');
  }

  log_message('Lead fetched: ' . json_encode($lead_data, JSON_PRETTY_PRINT));
  return $lead_data;
}
