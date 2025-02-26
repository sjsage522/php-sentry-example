<?php

namespace Jun\PhpSentryExample;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$asyncClient = SentryAsyncClientWrapper::getInstance(new \GuzzleHttp\Client([
    'timeout' => 1,
]));

\Sentry\init([
    'dsn' => $_ENV['DSN'],
    'http_client' => $asyncClient,
]);

\Sentry\captureMessage('test', \Sentry\Severity::warning());
// ...additional sentry calls

// Simulate a long running process
sleep(5);

// non-blocking multiple requests
$asyncClient->wait();
