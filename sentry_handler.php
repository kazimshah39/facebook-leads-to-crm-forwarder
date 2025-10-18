<?php
require_once __DIR__ . '/vendor/autoload.php';

use Sentry\Severity;
use Sentry\State\Scope;

/**
 * Initialize Sentry
 */
function initSentry()
{
  static $initialized = false;

  if ($initialized) {
    return;
  }

  $config = require __DIR__ . '/config.php';

  if (empty($config['SENTRY_DSN'])) {
    return;
  }

  \Sentry\init([
    'dsn' => $config['SENTRY_DSN'],
    'environment' => getenv('APP_ENV') ?: 'production',
    'traces_sample_rate' => 1.0,
    'tags' => [
      'site' => $config['SITE_NAME'] ?? 'Unknown Site',
    ],
  ]);

  $initialized = true;
}

/**
 * Log message to Sentry with context
 * 
 * @param string $message The message to log
 * @param string $level Severity level: 'debug', 'info', 'warning', 'error', 'critical'
 * @param array $context Additional context data
 * @param array $tags Optional tags for filtering in Sentry
 */
function logToSentry($message, $level = 'error', $context = [], $tags = [])
{
  initSentry();

  $config = require __DIR__ . '/config.php';

  // Map string levels to Sentry Severity
  $severityMap = [
    'debug' => Severity::debug(),
    'info' => Severity::info(),
    'warning' => Severity::warning(),
    'error' => Severity::error(),
    'critical' => Severity::fatal(),
  ];

  $severity = $severityMap[$level] ?? Severity::error();

  \Sentry\withScope(function (Scope $scope) use ($message, $severity, $context, $tags, $config) {
    // Add site identifier tag
    $scope->setTag('site', $config['SITE_NAME'] ?? 'Unknown Site');

    // Add context data
    if (!empty($context)) {
      $scope->setContext('additional_info', $context);
    }

    // Add site info to context
    $scope->setContext('site_info', [
      'site_name' => $config['SITE_NAME'] ?? 'Unknown Site',
      'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
      'script_path' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    ]);

    // Add tags
    foreach ($tags as $key => $value) {
      $scope->setTag($key, $value);
    }

    // Set level
    $scope->setLevel($severity);

    // Capture message
    \Sentry\captureMessage($message);
  });
}

/**
 * Log exception to Sentry
 * 
 * @param Throwable $exception The exception to log
 * @param array $context Additional context data
 */
function logExceptionToSentry($exception, $context = [])
{
  initSentry();

  $config = require __DIR__ . '/config.php';

  \Sentry\withScope(function (Scope $scope) use ($exception, $context, $config) {
    // Add site identifier tag
    $scope->setTag('site', $config['SITE_NAME'] ?? 'Unknown Site');

    if (!empty($context)) {
      $scope->setContext('additional_info', $context);
    }

    // Add site info to context
    $scope->setContext('site_info', [
      'site_name' => $config['SITE_NAME'] ?? 'Unknown Site',
      'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
      'script_path' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    ]);

    \Sentry\captureException($exception);
  });
}
