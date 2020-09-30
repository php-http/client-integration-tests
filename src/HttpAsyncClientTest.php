<?php

namespace Http\Client\Tests;

use Http\Client\HttpAsyncClient;

abstract class HttpAsyncClientTest extends HttpBaseTest
{
    /**
     * @var HttpAsyncClient
     */
    protected $httpAsyncClient;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->httpAsyncClient = $this->createHttpAsyncClient();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        unset($this->httpAdapter);
    }

    abstract protected function createHttpAsyncClient(): HttpAsyncClient;

    public function testSuccessiveCallMustUseResponseInterface()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getUri(),
            $this->defaultHeaders
        );

        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf('Http\Promise\Promise', $promise);

        $response = null;
        $promise->then()->then()->then(function ($r) use (&$response) {
            $response = $r;

            return $response;
        });

        $promise->wait(false);
        $this->assertResponse(
            $response,
            [
                'body' => 'Ok',
            ]
        );
    }

    public function testSuccessiveInvalidCallMustUseException()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getInvalidUri(),
            $this->defaultHeaders
        );

        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf('Http\Promise\Promise', $promise);

        $exception = null;
        $response = null;
        $promise->then()->then()->then(function ($r) use (&$response) {
            $response = $r;

            return $response;
        }, function ($e) use (&$exception) {
            $exception = $e;

            throw $e;
        });

        $promise->wait(false);

        $this->assertNull($response);
        $this->assertNotNull($exception);
        $this->assertInstanceOf('\Http\Client\Exception', $exception);
    }

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testAsyncSendRequest($method, $uri, array $headers, $body)
    {
        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $request = self::$messageFactory->createRequest(
            $method,
            $uri,
            $headers,
            $body
        );

        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf('Http\Promise\Promise', $promise);

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
     * @group             integration
     */
    public function testSendAsyncWithInvalidUri()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getInvalidUri(),
            $this->defaultHeaders
        );

        $exception = null;
        $response = null;
        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $this->assertInstanceOf('Http\Promise\Promise', $promise);

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
        $this->assertInstanceOf('\Http\Client\Exception', $exception);
    }

    /**
     * @dataProvider requestWithOutcomeProvider
     * @group        integration
     */
    public function testSendAsyncRequestWithOutcome($uriAndOutcome, $protocolVersion, array $headers, $body)
    {
        if ('1.0' === $protocolVersion) {
            $body = null;
        }

        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $request = self::$messageFactory->createRequest(
            $method = 'GET',
            $uriAndOutcome[0],
            $headers,
            $body,
            $protocolVersion
        );

        $outcome = $uriAndOutcome[1];
        $outcome['protocolVersion'] = $protocolVersion;

        $response = null;
        $promise = $this->httpAsyncClient->sendAsyncRequest($request);
        $promise->then(function ($r) use (&$response) {
            $response = $r;

            return $response;
        });

        $this->assertInstanceOf('Http\Promise\Promise', $promise);
        $promise->wait();
        $this->assertResponse(
            $response,
            $outcome
        );
        $this->assertRequest($method, $headers, $body, $protocolVersion);
    }
}
