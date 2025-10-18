<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sentry_handler.php';
require_once __DIR__ . '/verifier.php';
require_once __DIR__ . '/lead_fetcher.php';
require_once __DIR__ . '/data_mapper.php';
require_once __DIR__ . '/crm_forwarder.php';

// Initialize Sentry at the start
initSentry();

$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  verifyWebhook();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
  $expected = 'sha256=' . hash_hmac('sha256', $raw, $config['APP_SECRET']);

  if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    log_message('Invalid signature.');
    logToSentry(
      'Invalid webhook signature received',
      'warning',
      [
        'received_signature' => $signature,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ],
      ['component' => 'webhook_verification']
    );
    exit('Invalid signature');
  }

  log_message("Received payload: " . $raw);
  $data = json_decode($raw, true);
  $leadgen_id = $data['entry'][0]['changes'][0]['value']['leadgen_id'] ?? null;

  if (!$leadgen_id) {
    http_response_code(400);
    log_message('No leadgen_id found.');
    logToSentry(
      'No leadgen_id found in webhook payload',
      'warning',
      ['payload' => $raw],
      ['component' => 'webhook_processing']
    );
    exit('No leadgen_id found');
  }

  try {
    $lead_data = fetchLeadData($leadgen_id);
    $mapped_data = mapLeadData($lead_data);
    log_message("Mapped data ready for CRM: " . json_encode($mapped_data));
    forwardToCRM($mapped_data);

    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
  } catch (Exception $e) {
    log_message("Exception occurred: " . $e->getMessage());
    logExceptionToSentry($e, [
      'leadgen_id' => $leadgen_id,
      'component' => 'webhook_processing'
    ]);
    http_response_code(500);
    exit('Internal error');
  }
}

http_response_code(400);
echo 'Unsupported method';
exit;
