<?php

namespace Tests\Jun\PhpSentryExample;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Promise;
use Jun\PhpSentryExample\SentryAsyncClientWrapper;
use Mockery;
use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\Dsn;
use ReflectionProperty;

class SentryAsyncClientWrapperTest extends TestCase
{
    /**
     * @var Dsn
     */
    private $dsn;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset the singleton instance between tests
        $reflectionProperty = new ReflectionProperty(SentryAsyncClientWrapper::class, 'instance');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, null);

        // Create real DSN
        $this->dsn = Dsn::createFromString('https://public-key@sentry.example.com/1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetInstance(): void
    {
        // Create a new client
        $client = new Client();
        
        // Test that getInstance returns the same instance
        $instance1 = SentryAsyncClientWrapper::getInstance($client);
        $instance2 = SentryAsyncClientWrapper::getInstance($client);

        $this->assertSame($instance1, $instance2);
    }

    public function testSendRequest(): void
    {
        // Create a mock client
        $mockClient = Mockery::mock(Client::class);
        
        // Create a wrapper instance
        $wrapper = SentryAsyncClientWrapper::getInstance($mockClient);
        
        // Create a real Request object
        $request = new Request();
        $request->setStringBody('test-body-data');
        
        // Create a real Options object
        $options = new Options([
            'dsn' => $this->dsn,
            'http_compression' => false
        ]);

        // Mock the async request
        $mockPromise = Mockery::mock(PromiseInterface::class);
        $mockPromise->shouldReceive('then')
            ->withAnyArgs()
            ->andReturnSelf();

        $mockClient->shouldReceive('sendAsync')
            ->withAnyArgs()
            ->andReturn($mockPromise);

        // Call the method
        $response = $wrapper->sendRequest($request, $options);

        // Verify the response
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSendRequestWithNoDsn(): void
    {
        // Create a mock client
        $mockClient = Mockery::mock(Client::class);
        
        // Create a wrapper instance
        $wrapper = SentryAsyncClientWrapper::getInstance($mockClient);
        
        // Create a real Request object
        $request = new Request();
        $request->setStringBody('test-body-data');
        
        // Create options with no DSN
        $options = new Options([
            'dsn' => null
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DSN option must be set to use the HttpClient.');

        $wrapper->sendRequest($request, $options);
    }

    public function testWait(): void
    {
        // Mock client to avoid real HTTP requests
        $mockClient = Mockery::mock(Client::class);
        
        // Create a wrapper instance
        $wrapper = SentryAsyncClientWrapper::getInstance($mockClient);
        
        // Add a promise directly to the promises array using reflection
        $reflectionProperty = new ReflectionProperty($wrapper, 'promises');
        $reflectionProperty->setAccessible(true);
        
        // Create a promise that will resolve
        $promise = new Promise(function() use (&$promise) {
            $promise->resolve('test');
        });
        
        // Set the promises
        $reflectionProperty->setValue($wrapper, [$promise]);
        
        // Verify there's a promise in the array
        $promises = $reflectionProperty->getValue($wrapper);
        $this->assertCount(1, $promises);
        
        // Call wait method
        $wrapper->wait();
        
        // Verify the promises array is now empty
        $promises = $reflectionProperty->getValue($wrapper);
        $this->assertCount(0, $promises);
    }
}
