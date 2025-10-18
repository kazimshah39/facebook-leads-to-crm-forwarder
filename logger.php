<?php
function log_message($message)
{
  $config = require __DIR__ . '/config.php';
  if ($config['ENABLE_LOGS']) {
    file_put_contents($config['LOG_FILE'], date('c') . " - " . $message . PHP_EOL, FILE_APPEND);
  }
}
