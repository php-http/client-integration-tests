<?php

namespace Http\Client\Tests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
abstract class HttpClientTest extends HttpBaseTest
{
    /**
     * @var ClientInterface
     */
    protected $httpAdapter;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->httpAdapter = $this->createHttpAdapter();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        unset($this->httpAdapter);
    }

    abstract protected function createHttpAdapter(): ClientInterface;

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testSendRequest($method, $uri, array $headers, $body)
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

        $response = $this->httpAdapter->sendRequest($request);

        $this->assertResponse(
            $response,
            [
                'body' => 'HEAD' === $method ? null : 'Ok',
            ]
        );
        $this->assertRequest($method, $headers, $body, '1.1');
    }

    /**
     * @dataProvider requestWithOutcomeProvider
     * @group        integration
     */
    public function testSendRequestWithOutcome($uriAndOutcome, $protocolVersion, array $headers, $body)
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

        $response = $this->httpAdapter->sendRequest($request);

        $outcome = $uriAndOutcome[1];
        $outcome['protocolVersion'] = $protocolVersion;

        $this->assertResponse($response, $outcome);
        $this->assertRequest($method, $headers, $body, $protocolVersion);
    }

    /**
     * @group integration
     */
    public function testSendWithInvalidUri()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getInvalidUri(),
            $this->defaultHeaders
        );

        $this->expectException(NetworkExceptionInterface::class);
        $this->httpAdapter->sendRequest($request);
    }
}
