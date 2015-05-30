<?php

/*
 * This file is part of the Http Adapter package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Http\Adapter\Tests;

use Http\Adapter\HttpAdapter;
use Http\Adapter\HttpAdapterException;
use Http\Adapter\Exception\MultiHttpAdapterException;
use Http\Message\MessageFactory;
use Http\Common\Message\MessageFactoryGuesser;
use Nerd\CartesianProduct\CartesianProduct;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
abstract class HttpAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private static $logPath;

    /**
     * @var MessageFactory
     */
    protected static $messageFactory;

    /**
     * @var HttpAdapter
     */
    protected $httpAdapter;

    /**
     * @var array
     */
    protected $defaultOptions;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        self::$logPath = PHPUnitUtility::getFile(true, 'php-http-adapter.log');
        self::$messageFactory = MessageFactoryGuesser::guess();
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass()
    {
        if (file_exists(self::$logPath)) {
            unlink(self::$logPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->defaultOptions = [
            'protocolVersion' => '1.1',
            'statusCode'      => 200,
            'reasonPhrase'    => 'OK',
            'headers'         => ['Content-Type' => 'text/html'],
            'body'            => 'Ok',
        ];

        $this->httpAdapter = $this->createHttpAdapter();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->httpAdapter);
    }

    abstract public function testGetName();

    /**
     * @return HttpAdapter
     */
    abstract protected function createHttpAdapter();

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testSendRequest($method, $uri, $protocolVersion, array $headers, $body)
    {
        $request = self::$messageFactory->createRequest(
            $method,
            $uri,
            $protocolVersion,
            $headers,
            $body
        );

        $response = $this->httpAdapter->sendRequest($request);

        $this->assertResponse(
            $response,
            [
                'protocolVersion' => $protocolVersion,
                'body'            => $method === 'HEAD' ? null : $body,
            ]
        );
        $this->assertRequest($method, $headers, $body);
    }

    /**
     * @dataProvider requestsProvider
     * @group        integration
     */
    public function testSendRequests(array $requests)
    {
        $responses = $this->httpAdapter->sendRequests($requests);

        $this->assertMultiResponses($responses, $requests);
    }

    /**
     * @dataProvider erroredRequestProvider
     * @group        integration
     */
    public function testSendErroredRequests(array $requests, array $erroredRequests)
    {
        try {
            $this->httpAdapter->sendRequests(array_merge($requests, $erroredRequests));
            $this->fail();
        } catch (MultiHttpAdapterException $e) {
            $this->assertMultiResponses($e->getResponses(), $requests);
            $this->assertMultiExceptions($e->getExceptions(), $erroredRequests);
        }
    }

    /**
     * @group integration
     */
    public function testSendWithClientError()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getClientErrorUri()
        );

        $response = $this->httpAdapter->sendRequest($request);

        $this->assertResponse(
            $response,
            [
                'statusCode'   => 400,
                'reasonPhrase' => 'Bad Request',
            ]
        );

        $this->assertRequest($method);
    }

    /**
     * @group integration
     */
    public function testSendWithServerError()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getServerErrorUri()
        );

        $response = $this->httpAdapter->sendRequest($request);

        $this->assertResponse(
            $response,
            [
                'statusCode'   => 500,
                'reasonPhrase' => 'Internal Server Error',
            ]
        );

        $this->assertRequest($method);
    }

    /**
     * @group integration
     */
    public function testSendWithRedirect()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getRedirectUri()
        );

        $response = $this->httpAdapter->sendRequest($request);

        $this->assertResponse(
            $response,
            [
                'statusCode'   => 302,
                'reasonPhrase' => 'Found',
                'body'         => 'Redirect',
            ]
        );

        $this->assertRequest($method);
    }

    /**
     * @expectedException \Http\Adapter\Exception\HttpAdapterException
     * @group             integration
     */
    public function testSendWithInvalidUri()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getInvalidUri()
        );

        $this->httpAdapter->sendRequest($request);
    }

    /**
     * @dataProvider      timeoutProvider
     * @expectedException \Http\Adapter\Exception\HttpAdapterException
     * @group             integration
     */
    // public function testSendWithTimeoutExceeded($timeout)
    // {
    //     $this->httpAdapter->setOption('timeout', $timeout);
    //     $this->httpAdapter->send('GET', $this->getDelayUri($timeout));
    // }

    /**
     * @return array
     */
    public function requestProvider()
    {
        $sets = [
            'methods'          => $this->getMethods(),
            'uri'              => [$this->getUri()],
            'protocolVersions' => $this->getProtocolVersions(),
            'headers'          => [[], $this->getHeaders()],
            'body'             => [null, http_build_query($this->getData(), null, '&')],
        ];

        $cartesianProduct = new CartesianProduct($sets);

        return $cartesianProduct->compute();
    }

    /**
     * @return array
     */
    public function requestsProvider()
    {
        $requests = [];
        $messageFactory = MessageFactoryGuesser::guess();

        foreach ($this->requestProvider() as $request) {
            $requests[] = $messageFactory->createRequest(
                $request[0],
                $request[1],
                $request[3],
                $request[4]
            );
        }

        return array_chunk($requests, 3);
    }

    /**
     * @return array
     */
    public function erroredRequestsProvider()
    {
        $requests = [];
        $erroredRequests = [];
        $requestList = [];
        $messageFactory = MessageFactoryGuesser::guess();

        foreach ($this->requestProvider() as $request) {
            if ($request[0] !== 'GET') {
                continue;
            }

            $requests[] = $messageFactory->createRequest(
                $request[0],
                $request[1],
                $request[3],
                $request[4]
            );

            $erroredRequests[] = $messageFactory->createRequest(
                $request[0],
                $this->getInvalidUri(),
                $request[3],
                $request[4]
            );
        }

        $requests = array_chunk($requests, 3);
        $erroredRequests = array_chunk($erroredRequests, 3);

        foreach ($requests as $key => $threeRequests) {
            $requestList[] = [
                $threeRequests,
                $erroredRequests[$key],
            ];
        }

        return $requestList;
    }

    /**
     * @return array
     */
    public function timeoutProvider()
    {
        return [[0.5], [1]];
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return [
            'GET',
            'HEAD',
            'TRACE',
            'POST',
            'PUT',
            'DELETE',
            'OPTIONS',
        ];
    }

    /**
     * @param string[] $query
     *
     * @return string|null
     */
    private function getUri(array $query = [])
    {
        return !empty($query)
            ? PHPUnitUtility::getUri().'?'.http_build_query($query, null, '&')
            : PHPUnitUtility::getUri();
    }

    /**
     * @return array
     */
    public function getProtocolVersions()
    {
        return ['1.1', '1.0'];
    }

    /**
     * @return string
     */
    private function getInvalidUri()
    {
        return 'http://invalid.php-http.org';
    }

    /**
     * @return string
     */
    private function getClientErrorUri()
    {
        return $this->getUri(['client_error' => true]);
    }

    /**
     * @return string
     */
    private function getServerErrorUri()
    {
        return $this->getUri(['server_error' => true]);
    }

    /**
     * @param float $delay
     *
     * @return string
     */
    private function getDelayUri($delay = 1.0)
    {
        return $this->getUri(['delay' => $delay + 0.01]);
    }

    /**
     * @return string
     */
    private function getRedirectUri()
    {
        return $this->getUri(['redirect' => true]);
    }

    /**
     * @return string[]
     */
    private function getHeaders()
    {
        return ['Accept-Charset' => 'utf-8', 'Accept-Language:fr'];
    }

    /**
     * @return array
     */
    private function getData()
    {
        return ['param1' => 'foo', 'param2' => ['bar', ['baz']]];
    }

    /**
     * @param ResponseInterface $response
     * @param array             $options
     */
    protected function assertResponse($response, array $options = [])
    {
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);

        $options = array_merge($this->defaultOptions, $options);

        $this->assertSame($options['protocolVersion'], $response->getProtocolVersion());
        $this->assertSame($options['statusCode'], $response->getStatusCode());
        $this->assertSame($options['reasonPhrase'], $response->getReasonPhrase());

        $this->assertNotEmpty($response->getHeaders());

        foreach ($options['headers'] as $name => $value) {
            $this->assertTrue($response->hasHeader($name));
            $this->assertStringStartsWith($value, $response->getHeaderLine($name));
        }

        if ($options['body'] === null) {
            $this->assertEmpty($response->getBody()->getContents());
            $this->assertEmpty((string) $response->getBody());
        } else {
            $this->assertContains($options['body'], $response->getBody()->getContents());
            $this->assertContains($options['body'], (string) $response->getBody());
        }
    }

    /**
     * @param string   $method
     * @param string[] $headers
     * @param string   $body
     * @param string   $protocolVersion
     */
    protected function assertRequest(
        $method,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ) {
        $request = $this->getRequest();

        $this->assertSame($protocolVersion, substr($request['SERVER']['SERVER_PROTOCOL'], 5));
        $this->assertSame($method, $request['SERVER']['REQUEST_METHOD']);

        $defaultHeaders = [
            'Connection' => 'close',
            'User-Agent' => 'PHP HTTP Adapter',
        ];

        $headers = array_merge($defaultHeaders, $headers);

        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                list($name, $value) = explode(':', $value);
            }

            $name = strtoupper(str_replace('-', '_', 'http-'.$name));

            $this->assertArrayHasKey($name, $request['SERVER']);
            $this->assertSame($value, $request['SERVER'][$name]);
        }
    }

    /**
     * @param ResponseInterface[] $responses
     * @param array               $requests
     */
    private function assertMultiResponses(array $responses, array $requests)
    {
        $this->assertCount(count($requests), $responses);
    }

    /**
     * @param HttpAdapterException[] $exceptions
     * @param array                  $requests
     */
    private function assertMultiExceptions(array $exceptions, array $requests)
    {
        $this->assertCount(count($requests), $exceptions);

        foreach ($exceptions as $exception) {
            $this->assertTrue($exception->hasRequest());
            $this->assertInstanceOf(
                'Psr\Http\Message\RequestInterface',
                $exception->getRequest()
            );
        }
    }

    /**
     * @return array
     */
    private function getRequest()
    {
        $file = fopen(self::$logPath, 'r');
        flock($file, LOCK_EX);
        $request = json_decode(stream_get_contents($file), true);
        flock($file, LOCK_UN);
        fclose($file);

        return $request;
    }
}
