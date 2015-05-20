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

use Http\Adapter\CoreHttpAdapter;
use Http\Adapter\HttpAdapterException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Http\Adapter\Exception\MultiHttpAdapterException;

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
     * @var CoreHttpAdapter
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
            'protocol_version' => '1.1',
            'status_code'      => 200,
            'reason_phrase'    => 'OK',
            'headers'          => ['Content-Type' => 'text/html'],
            'body'             => 'Ok',
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
     * @dataProvider simpleProvider
     * @group integration
     */
    public function testGet($uri, array $headers = [])
    {
        $this->assertResponse($this->httpAdapter->get($uri, $headers));
        $this->assertRequest('GET', $headers);
    }

    /**
     * @dataProvider simpleProvider
     * @group integration
     */
    public function testHead($uri, array $headers = [])
    {
        $this->assertResponse($this->httpAdapter->head($uri, $headers), ['body' => null]);
        $this->assertRequest('HEAD', $headers);
    }

    /**
     * @dataProvider simpleProvider
     * @group integration
     */
    public function testTrace($uri, array $headers = [])
    {
        $this->assertResponse($this->httpAdapter->trace($uri, $headers));
        $this->assertRequest('TRACE', $headers);
    }

    /**
     * @dataProvider fullProvider
     * @group integration
     */
    public function testPost($uri, array $headers = [], array $data = [], array $files = [])
    {
        $this->assertResponse($this->httpAdapter->post($uri, $headers, $data, $files));
        $this->assertRequest('POST', $headers, $data, $files);
    }

    /**
     * @dataProvider fullProvider
     * @group integration
     */
    public function testPut($uri, array $headers = [], array $data = [], array $files = [])
    {
        $this->assertResponse($this->httpAdapter->put($uri, $headers, $data, $files));
        $this->assertRequest('PUT', $headers, $data, $files);
    }

    /**
     * @dataProvider fullProvider
     * @group integration
     */
    public function testPatch($uri, array $headers = [], array $data = [], array $files = [])
    {
        $this->assertResponse($this->httpAdapter->patch($uri, $headers, $data, $files));
        $this->assertRequest('PATCH', $headers, $data, $files);
    }

    /**
     * @dataProvider fullProvider
     * @group integration
     */
    public function testDelete($uri, array $headers = [], array $data = [], array $files = [])
    {
        $this->assertResponse($this->httpAdapter->delete($uri, $headers, $data, $files));
        $this->assertRequest('DELETE', $headers, $data, $files);
    }

    /**
     * @dataProvider fullProvider
     * @group integration
     */
    public function testOptions($uri, array $headers = [], array $data = [], array $files = [])
    {
        $this->assertResponse($this->httpAdapter->options($uri, $headers, $data, $files));
        $this->assertRequest('OPTIONS', $headers, $data, $files);
    }

    /**
     * @dataProvider requestProvider
     * @group integration
     */
    public function testSendRequest($uri, $method, array $headers = [], array $data = [])
    {
        $request = $this->httpAdapter->getMessageFactory()->createRequest(
            $method,
            $uri,
            '1.1',
            $headers,
            http_build_query($data, null, '&')
        );

        $this->assertResponse(
            $this->httpAdapter->sendRequest($request),
            $method === 'HEAD' ? ['body' => null] : []
        );

        $this->assertRequest($method, $headers, $data);
    }

    /**
     * @dataProvider internalRequestProvider
     * @group integration
     */
    public function testSendInternalRequest($uri, $method, array $headers = [], array $data = [], array $files = [])
    {
        $request = $this->httpAdapter->getMessageFactory()->createInternalRequest(
            $method,
            $uri,
            '1.1',
            $headers,
            $data,
            $files
        );

        $this->assertResponse(
            $this->httpAdapter->sendRequest($request),
            $method === 'HEAD' ? ['body' => null] : []
        );

        $this->assertRequest($method, $headers, $data, $files);
    }

    /**
     * @group integration
     */
    public function testSendRequests()
    {
        $this->assertMultiResponses($this->httpAdapter->sendRequests($requests = $this->requestsProvider()), $requests);
    }

    /**
     * @group integration
     */
    public function testSendErroredRequests()
    {
        $requests = $this->requestsProvider();
        $erroredRequests = $this->erroredRequestsProvider();

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
    public function testSendWithCustomArgSeparatorOutput()
    {
        $argSeparatorOutput = ini_get('arg_separator.output');
        ini_set('arg_separator.output', '&amp;');

        $this->assertResponse($this->httpAdapter->post(
            $this->getUri(),
            $headers = $this->getHeaders(),
            $data = $this->getData()
        ));

        $this->assertRequest('POST', $headers, $data);

        ini_set('arg_separator.output', $argSeparatorOutput);
    }

    /**
     * @group integration
     */
    public function testSendWithProtocolVersion10()
    {
        $this->httpAdapter->setOption('protocolVersion', $protocolVersion = '1.0');

        $this->assertResponse(
            $this->httpAdapter->send($method = 'GET', $this->getUri()),
            ['protocol_version' => $protocolVersion]
        );

        $this->assertRequest($method, [], [], [], $protocolVersion);
    }

    /**
     * @group integration
     */
    public function testSendWithUserAgent()
    {
        $this->httpAdapter->setOption('userAgent', $userAgent = 'foo');

        $this->assertResponse($this->httpAdapter->send($method = 'GET', $this->getUri()));
        $this->assertRequest($method, ['User-Agent' => $userAgent]);
    }

    /**
     * @group integration
     */
    public function testSendWithClientError()
    {
        $this->assertResponse(
            $this->httpAdapter->send($method = 'GET', $uri = $this->getClientErrorUri()),
            [
                'status_code'   => 400,
                'reason_phrase' => 'Bad Request',
            ]
        );

        $this->assertRequest($method);
    }

    /**
     * @group integration
     */
    public function testSendWithServerError()
    {
        $this->assertResponse(
            $this->httpAdapter->send($method = 'GET', $uri = $this->getServerErrorUri()),
            [
                'status_code'   => 500,
                'reason_phrase' => 'Internal Server Error',
            ]
        );

        $this->assertRequest($method);
    }

    /**
     * @group integration
     */
    public function testSendWithRedirect()
    {
        $this->assertResponse(
            $this->httpAdapter->send($method = 'GET', $uri = $this->getRedirectUri()),
            [
                'status_code'   => 302,
                'reason_phrase' => 'Found',
                'body'          => 'Redirect',
            ]
        );

        $this->assertRequest($method);
    }

    /**
     * @expectedException \Http\Adapter\Exception\HttpAdapterException
     * @group integration
     */
    public function testSendWithInvalidUri()
    {
        $this->httpAdapter->send('GET', $this->getInvalidUri());
    }

    /**
     * @dataProvider timeoutProvider
     * @expectedException \Http\Adapter\Exception\HttpAdapterException
     * @group integration
     */
    public function testSendWithTimeoutExceeded($timeout)
    {
        $this->httpAdapter->setOption('timeout', $timeout);
        $this->httpAdapter->send('GET', $this->getDelayUri($timeout));
    }

    /**
     * @return array
     */
    public function simpleProvider()
    {
        return [
            [$this->getUri()],
            [$this->getUri(), $this->getHeaders()],
        ];
    }

    /**
     * @return array
     */
    public function fullProvider()
    {
        return array_merge(
            $this->simpleProvider(),
            [
                [$this->getUri(), $this->getHeaders(), $this->getData()],
                [$this->getUri(), $this->getHeaders(), $this->getData(), $this->getFiles()],
            ]
        );
    }

    /**
     * @return array
     */
    public function requestProvider()
    {
        $requests = [];

        foreach ($this->internalRequestProvider() as $request) {
            if (!isset($request[4])) {
                $requests[] = $request;
            }
        }

        return $requests;
    }

    /**
     * @return array
     */
    public function internalRequestProvider()
    {
        return [
            [$this->getUri(), 'GET'],
            [$this->getUri(), 'GET', $this->getHeaders()],
            [$this->getUri(), 'HEAD'],
            [$this->getUri(), 'HEAD', $this->getHeaders()],
            [$this->getUri(), 'TRACE'],
            [$this->getUri(), 'TRACE', $this->getHeaders()],
            [$this->getUri(), 'POST'],
            [$this->getUri(), 'POST', $this->getHeaders()],
            [$this->getUri(), 'POST', $this->getHeaders(), $this->getData()],
            [
                $this->getUri(),
                'POST',
                $this->getHeaders(),
                $this->getData(),
                $this->getFiles(),
            ],
            [$this->getUri(), 'PUT'],
            [$this->getUri(), 'PUT', $this->getHeaders()],
            [$this->getUri(), 'PUT', $this->getHeaders(), $this->getData()],
            [
                $this->getUri(),
                'PUT',
                $this->getHeaders(),
                $this->getData(),
                $this->getFiles(),
            ],
            [$this->getUri(), 'PATCH'],
            [$this->getUri(), 'PATCH', $this->getHeaders()],
            [$this->getUri(), 'PATCH', $this->getHeaders(), $this->getData()],
            [
                $this->getUri(),
                'PATCH',
                $this->getHeaders(),
                $this->getData(),
                $this->getFiles(),
            ],
            [$this->getUri(), 'DELETE'],
            [$this->getUri(), 'DELETE', $this->getHeaders()],
            [$this->getUri(), 'DELETE', $this->getHeaders(), $this->getData()],
            [
                $this->getUri(),
                'DELETE',
                $this->getHeaders(),
                $this->getData(),
                $this->getFiles(),
            ],
            [$this->getUri(), 'OPTIONS'],
            [$this->getUri(), 'OPTIONS', $this->getHeaders()],
            [$this->getUri(), 'OPTIONS', $this->getHeaders(), $this->getData()],
            [
                $this->getUri(),
                'OPTIONS',
                $this->getHeaders(),
                $this->getData(),
                $this->getFiles(),
            ],
        ];
    }

    /**
     * @return array
     */
    public function requestsProvider()
    {
        $requests = [['GET', $this->getUri()]];

        foreach ($this->requestProvider() as $request) {
            $requests[] = [
                $request[1],
                $request[0],
                '1.1',
                isset($request[2]) ? $request[2] : [],
                isset($request[3]) ? $request[3] : [],
                isset($request[4]) ? $request[4] : [],
            ];
        }

        foreach ($this->requestProvider() as $request) {
            $requests[] = $this->httpAdapter->getMessageFactory()->createRequest(
                $request[1],
                $request[0],
                '1.1',
                isset($request[2]) ? $request[2] : [],
                http_build_query(isset($request[3]) ? $request[3] : [], null, '&')
            );
        }

        foreach ($this->requestProvider() as $request) {
            $requests[] = $this->httpAdapter->getMessageFactory()->createInternalRequest(
                $request[1],
                $request[0],
                '1.1',
                isset($request[2]) ? $request[2] : [],
                isset($request[3]) ? $request[3] : [],
                isset($request[4]) ? $request[4] : []
            );
        }

        return $requests;
    }

    /**
     * @return array
     */
    public function erroredRequestsProvider()
    {
        return [['GET', $this->getInvalidUri()]];
    }

    /**
     * @return array
     */
    public function timeoutProvider()
    {
        return [[0.5], [1]];
    }

    /**
     * @return CoreHttpAdapter
     */
    abstract protected function createHttpAdapter();

    /**
     * @param ResponseInterface $response
     * @param array             $options
     */
    protected function assertResponse($response, array $options = [])
    {
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);

        $options = array_merge($this->defaultOptions, $options);

        $this->assertSame($options['protocol_version'], $response->getProtocolVersion());
        $this->assertSame($options['status_code'], $response->getStatusCode());
        $this->assertSame($options['reason_phrase'], $response->getReasonPhrase());

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

        $parameters = [];

        if (isset($options['redirect_count'])) {
            $parameters['redirect_count'] = $options['redirect_count'];
        }

        if (isset($options['effective_uri'])) {
            $parameters['effective_uri'] = $options['effective_uri'];
        }

        // $this->assertSame($parameters, $response->getParameters());
    }

    /**
     * @param string   $method
     * @param string[] $headers
     * @param array    $data
     * @param array    $files
     * @param string   $protocolVersion
     */
    protected function assertRequest(
        $method,
        array $headers = [],
        array $data = [],
        array $files = [],
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

        $inputMethods = [
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
        ];

        if (in_array($method, $inputMethods)) {
            $this->assertRequestInputData($request, $data, !empty($files));
            $this->assertRequestInputFiles($request, $files);
        } else {
            $this->assertRequestData($request, $data);
            $this->assertRequestFiles($request, $files);
        }
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
     * @return array
     */
    private function getFiles()
    {
        $fixturePath = realpath(__DIR__.'/../fixture/files');

        return [
            'file1' => [$fixturePath.'/file1.txt'],
            'file2' => [
                $fixturePath.'/file2.txt',
                [$fixturePath.'/file3.txt'],
            ],
        ];
    }

    /**
     * @param array $request
     * @param array $data
     */
    private function assertRequestData(array $request, array $data)
    {
        foreach ($data as $name => $value) {
            $this->assertArrayHasKey($name, $request['POST']);
            $this->assertSame($value, $request['POST'][$name]);
        }
    }

    /**
     * @param array   $request
     * @param array   $data
     * @param boolean $multipart
     */
    private function assertRequestInputData(array $request, array $data, $multipart)
    {
        if ($multipart) {
            foreach ($data as $name => $value) {
                $this->assertRequestMultipartData($request, $name, $value);
            }
        } else {
            parse_str($request['INPUT'], $request['POST']);
            $this->assertRequestData($request, $data);
        }
    }

    /**
     * @param array        $request
     * @param string       $name
     * @param array|string $data
     */
    private function assertRequestMultipartData(array $request, $name, $data)
    {
        if (is_array($data)) {
            foreach ($data as $subName => $subValue) {
                $this->assertRequestMultipartData($request, $name.'['.$subName.']', $subValue);
            }
        } else {
            $this->assertRegExp(
                '/Content-Disposition: form-data; name="'.preg_quote($name).'"\s+'.preg_quote($data).'/',
                $request['INPUT']
            );
        }
    }

    /**
     * @param array $request
     * @param array $files
     */
    private function assertRequestFiles(array $request, array $files)
    {
        foreach ($files as $name => $file) {
            $this->assertRequestFile($request, $name, $file);
        }
    }

    /**
     * @param array  $request
     * @param string $name
     * @param string $file
     */
    private function assertRequestFile(array $request, $name, $file)
    {
        if (is_array($file)) {
            foreach ($file as $subName => $subFile) {
                $this->assertRequestFile($request, $name.'['.$subName.']', $subFile);
            }
        } else {
            if (!preg_match('/^([^\[]+)/', $name, $nameMatches)) {
                $this->fail();
            }

            $this->assertArrayHasKey($nameMatches[1], $request['FILES']);

            $fileRequest = $request['FILES'][$nameMatches[1]];
            $fileName = basename($file);
            $fileSize = strlen(file_get_contents($file));
            $levels = preg_match_all('/\[(\d+)\]/', $name, $indexMatches) ? $indexMatches[1] : array();

            $this->assertRequestPropertyFile($fileName, 'name', $fileRequest, $levels);
            $this->assertRequestPropertyFile($fileSize, 'size', $fileRequest, $levels);
            $this->assertRequestPropertyFile(0, 'error', $fileRequest, $levels);
        }
    }

    /**
     * @param mixed  $expected
     * @param string $property
     * @param array  $file
     * @param array  $levels
     */
    private function assertRequestPropertyFile($expected, $property, array $file, array $levels = [])
    {
        if (!empty($levels)) {
            $this->assertRequestPropertyFile($expected, $levels[0], $file[$property], array_slice($levels, 1));
        } else {
            $this->assertSame($expected, $file[$property]);
        }
    }

    /**
     * @param array $request
     * @param array $files
     */
    private function assertRequestInputFiles(array $request, array $files)
    {
        foreach ($files as $name => $file) {
            $this->assertRequestInputFile($request, $name, $file);
        }
    }

    /**
     * @param array        $request
     * @param string       $name
     * @param array|string $file
     */
    private function assertRequestInputFile(array $request, $name, $file)
    {
        if (is_array($file)) {
            foreach ($file as $subName => $subFile) {
                $this->assertRequestInputFile($request, $name.'['.$subName.']', $subFile);
            }
        } else {
            $namePattern = '; name="'.preg_quote($name).'"';
            $filenamePattern = '; filename=".*'.preg_quote(basename($file)).'"';

            $subPattern = '('.$namePattern.$filenamePattern.'|'.$filenamePattern.$namePattern.')';
            $pattern = '/Content-Disposition: form-data'.$subPattern.'.*'.preg_quote(file_get_contents($file)).'/sm';

            $this->assertRegExp($pattern, $request['INPUT']);
        }
    }

    /**
     * @param ResponseInterface[] $responses
     * @param array               $requests
     */
    private function assertMultiResponses(array $responses, array $requests)
    {
        $this->assertCount(count($requests), $responses);

        foreach ($responses as $response) {
            $this->assertTrue($response->hasParameter('request'));
            $this->assertInstanceOf(
                'Http\Adapter\Message\InternalRequest',
                $response->getParameter('request')
            );
        }
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
                'Http\Adapter\Message\InternalRequest',
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
