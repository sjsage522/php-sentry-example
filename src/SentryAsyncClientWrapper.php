<?php

namespace Jun\PhpSentryExample;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Sentry\Client as SentryClient;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\HttpClient\Response;

class SentryAsyncClientWrapper implements HttpClientInterface
{
    private static ?self $instance = null;

    private Client $client;

    private array $promises = [];

    private string $sdkIdentifier = SentryClient::SDK_IDENTIFIER;
    private string $sdkVersion = SentryClient::SDK_VERSION;

    private function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function getInstance(Client $client): self
    {
        if (self::$instance === null) {
            self::$instance = new self($client);
        }

        return self::$instance;
    }

    public function sendRequest(Request $request, Options $options): Response
    {
        $dsn = $options->getDsn();
        if ($dsn === null) {
            throw new \RuntimeException('The DSN option must be set to use the HttpClient.');
        }

        $requestData = $request->getStringBody();
        if ($requestData === '') {
            throw new \RuntimeException('The request data is empty.');
        }

        $sentry_version = SentryClient::PROTOCOL_VERSION;
        $sentry_client = "{$this->sdkIdentifier}/{$this->sdkVersion}";
        $sentry_key = $dsn->getPublicKey();
        $requestHeaders['sentry_version'] = $sentry_version;
        $requestHeaders['sentry_client'] = $sentry_client;
        $requestHeaders['sentry_key'] = $sentry_key;
        $requestHeaders['Content-Type'] = 'application/x-sentry-envelope';
        $authHeader = [
            'sentry_version=' . $sentry_version,
            'sentry_client=' . $sentry_client,
            'sentry_key=' . $sentry_key,
        ];
        $requestHeaders['X-Sentry-Auth'] = 'Sentry ' . implode(', ', $authHeader);

        if (\extension_loaded('zlib') && $options->isHttpCompressionEnabled()) {
            $requestData = gzcompress($requestData, -1, \ZLIB_ENCODING_GZIP);
            $requestHeaders['Content-Encoding'] = 'gzip';
        }

        $this->promises[] = $this->client->sendAsync(new Psr7Request(
            'POST',
            $dsn->getEnvelopeApiEndpointUrl(),
            $requestHeaders,
            $requestData
        ))->then(
            function ($response) {
                // callback for when the request succeeds
                echo 'Request succeeded: ' . $response->getBody() . PHP_EOL;
            },
            function ($reason) {
                // callback for when the request fails
                echo 'Request failed: ' . $reason . PHP_EOL;
            }
        );

        return new Response(200, [], '');
    }

    public function wait(): void
    {
        if (!empty($this->promises)) {
            Utils::settle($this->promises)->wait();
            $this->promises = [];
        }
    }
}
