# Async Sentry Logging Example

This repository provides an example of how to send Sentry alerts asynchronously using Guzzle to minimize latency and improve application performance.

## Installation

```sh
git clone https://github.com/YOUR_GITHUB_USERNAME/async-sentry-logging-example.git
cd async-sentry-logging-example
composer install
```

## Configuration
1. Create a .env file in the project root:
    ```sh
    cp .env.example .env
    ```
2. Edit the .env file and set your Sentry DSN:
    ```ìni
    DSN=https://your-public-key@o0.ingest.sentry.io/0
    ```

## Usage
1. Initialize the Asynchronous Sentry Client
The SentryAsyncClientWrapper class wraps Guzzle's asynchronous HTTP client to send Sentry logs in a non-blocking manner.
    ```php
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
    ```
2. Ensure All Requests Are Dispatched
    ```php
    // Simulate a long-running process
    sleep(5);

    // Ensure all asynchronous Sentry requests are completed
    $asyncClient->wait();
    ```

## How It Works
- SentryAsyncClientWrapper extends Sentry\HttpClient\HttpClientInterface
- When sendRequest() is called, it pushes a promise into an array instead of blocking execution.
- The wait() method settles all pending promises before the script exits.