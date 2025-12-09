<?php

namespace Http\Client\Tests;

use Http\Client\Exception;
use Http\Client\HttpAsyncClient;
use Http\Promise\Promise;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

abstract class HttpAsyncClientTest extends HttpBaseTest
{
    protected HttpAsyncClient $httpAsyncClient;

    protected function setUp(): void
    {
        $this->httpAsyncClient = $this->createHttpAsyncClient();
    }

    protected function tearDown(): void
    {
        unset($this->httpAdapter);
    }

    abstract protected function createHttpAsyncClient(): HttpAsyncClient;

    public function testSuccessiveCallMustUseResponseInterface(): void
    {
        $request = self::$requestFactory->createRequest('GET', self::getUri());
        foreach (self::$defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }


        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf(Promise::class, $promise);

        $response = null;
        $promise->then()->then()->then(function ($r) use (&$response) {
            $response = $r;

            return $r;
        });

        $promise->wait(false);
        $this->assertResponse(
            $response,
            [
                'body' => 'Ok',
            ]
        );
    }

    public function testSuccessiveInvalidCallMustUseException(): void
    {
        $request = self::$requestFactory->createRequest('GET', $this->getInvalidUri());
        foreach (self::$defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf(Promise::class, $promise);

        $exception = null;
        $response = null;
        $promise->then()->then()->then(function ($r) use (&$response) {
            $response = $r;

            return $r;
        }, function ($e) use (&$exception) {
            $exception = $e;

            throw $e;
        });

        $promise->wait(false);

        $this->assertNull($response);
        $this->assertNotNull($exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    #[DataProvider('requestProvider')]
    #[Group('integration')]
    public function testAsyncSendRequest(string $method, string $uri, array $headers, ?string $body): void
    {
        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $request = self::$requestFactory->createRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $body) {
            $request = $request->withBody(self::$streamFactory->createStream($body));
        }

        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf(Promise::class, $promise);

        $response = null;
        $promise->then(function ($r) use (&$response) {
            $response = $r;

            return $response;
        });

        $promise->wait();
        $this->assertResponse(
            $response,
            [
                'body' => 'HEAD' === $method ? null : 'Ok',
            ]
        );
        $this->assertRequest($method, $headers, $body, '1.1');
    }

    /**
     * @group integration
     */
    #[Group('integration')]
    public function testSendAsyncWithInvalidUri(): void
    {
        $request = self::$requestFactory->createRequest('GET', $this->getInvalidUri());
        foreach (self::$defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $exception = null;
        $response = null;
        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf(Promise::class, $promise);

        $promise->then(function ($r) use (&$response) {
            $response = $r;

            return $response;
        }, function ($e) use (&$exception) {
            $exception = $e;

            throw $e;
        });
        $promise->wait(false);

        $this->assertNull($response);
        $this->assertNotNull($exception);
        $this->assertInstanceOf(Exception::class, $exception);
    }

    /**
     * @dataProvider requestWithOutcomeProvider
     * @group        integration
     */
    #[Group('integration')]
    #[DataProvider('requestWithOutcomeProvider')]
    public function testSendAsyncRequestWithOutcome(array $uriAndOutcome, string $protocolVersion, array $headers, ?string $body): void
    {
        if ('1.0' === $protocolVersion) {
            $body = null;
        }

        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $request = self::$requestFactory->createRequest($method = 'GET', $uriAndOutcome[0]);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $body) {
            $request = $request->withBody(self::$streamFactory->createStream($body));
        }
        $request = $request->withProtocolVersion($protocolVersion);

        $outcome = $uriAndOutcome[1];
        $outcome['protocolVersion'] = $protocolVersion;

        $response = null;
        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $promise->then(function ($r) use (&$response) {
            $response = $r;

            return $response;
        });

        $this->assertInstanceOf(Promise::class, $promise);
        $promise->wait();
        $this->assertResponse(
            $response,
            $outcome
        );
        $this->assertRequest($method, $headers, $body, $protocolVersion);
    }
}
