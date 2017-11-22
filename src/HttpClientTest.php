<?php

namespace Http\Client\Tests;

use Http\Client\HttpClient;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
abstract class HttpClientTest extends HttpBaseTest
{
    /**
     * @var HttpClient
     */
    protected $httpAdapter;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->httpAdapter = $this->createHttpAdapter();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->httpAdapter);
    }

    /**
     * @return HttpClient
     */
    abstract protected function createHttpAdapter();

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
     * @expectedException \Http\Client\Exception
     * @group             integration
     */
    public function testSendWithInvalidUri()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getInvalidUri(),
            $this->defaultHeaders
        );

        $this->httpAdapter->sendRequest($request);
    }
}
