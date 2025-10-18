<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

function verifyWebhook()
{
  $config = require __DIR__ . '/config.php';
  $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
  $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
  $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? null;

  if ($mode === 'subscribe' && $token === $config['VERIFY_TOKEN']) {
    http_response_code(200);
    echo $challenge;
    exit;
  } else {
    http_response_code(403);
    echo 'Verification failed';
    exit;
  }
}
