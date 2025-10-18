<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sentry_handler.php';

function forwardToCRM($mapped_data)
{
  $config = require __DIR__ . '/config.php';
  $ch = curl_init($config['FORWARD_URL']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mapped_data));
  $crm_response = curl_exec($ch);
  $crm_status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $curl_error = curl_error($ch);
  curl_close($ch);

  if ($crm_status !== 200) {
    $msg = "Failed to forward to CRM. Status: {$crm_status}, Error: {$curl_error}, Response: {$crm_response}";
    log_message($msg);
    logToSentry(
      'Failed to forward lead to CRM',
      'error',
      [
        'status_code' => $crm_status,
        'curl_error' => $curl_error,
        'crm_response' => $crm_response,
        'forward_url' => $config['FORWARD_URL'],
        'mapped_data' => $mapped_data
      ],
      ['component' => 'crm_forwarder']
    );
  } else {
    log_message("Forwarded to CRM ({$config['FORWARD_URL']}), Status: {$crm_status}, Response: {$crm_response}");
  }
}
