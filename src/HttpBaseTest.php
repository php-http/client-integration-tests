<?php

/*
 * This file is part of the Http Adapter package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Http\Client\Tests;

use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;
use Nerd\CartesianProduct\CartesianProduct;
use Psr\Http\Message\ResponseInterface;

abstract class HttpBaseTest extends \PHPUnit_Framework_TestCase
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
     * @var array
     */
    protected $defaultOptions = [
        'protocolVersion' => '1.1',
        'statusCode'      => 200,
        'reasonPhrase'    => 'OK',
        'headers'         => ['Content-Type' => 'text/html'],
        'body'            => 'Ok',
    ];

    /**
     * @var array
     */
    protected $defaultHeaders = [
        'Connection' => 'close',
        'User-Agent' => 'PHP HTTP Adapter',
        'Content-Length' => '0'
    ];

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        self::$logPath = PHPUnitUtility::getFile(true, 'php-http-adapter.log');
        self::$messageFactory = MessageFactoryDiscovery::find();
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
     * @return array
     */
    public function requestProvider()
    {
        $sets = [
            'methods' => $this->getMethods(),
            'uris'    => [$this->getUri()],
            'headers' => $this->getHeaders(),
            'body'    => $this->getBodies(),
        ];

        $cartesianProduct = new CartesianProduct($sets);

        return $cartesianProduct->compute();
    }

    /**
     * @return array
     */
    public function requestWithOutcomeProvider()
    {
        $sets = [
            'urisAndOutcomes'  => $this->getUrisAndOutcomes(),
            'protocolVersions' => $this->getProtocolVersions(),
            'headers'          => $this->getHeaders(),
            'body'             => $this->getBodies(),
        ];

        $cartesianProduct = new CartesianProduct($sets);

        return $cartesianProduct->compute();
    }

    /**
     * @return array
     */
    private function getMethods()
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
    protected function getUri(array $query = [])
    {
        return !empty($query)
            ? PHPUnitUtility::getUri().'?'.http_build_query($query, null, '&')
            : PHPUnitUtility::getUri();
    }

    /**
     * @return string
     */
    protected function getInvalidUri()
    {
        return 'http://invalid.php-http.org';
    }

    /**
     * @return array
     */
    private function getUrisAndOutcomes()
    {
        return [
            [
                $this->getUri(['client_error' => true]),
                [
                    'statusCode'   => 400,
                    'reasonPhrase' => 'Bad Request',
                ],
            ],
            [
                $this->getUri(['server_error' => true]),
                [
                    'statusCode'   => 500,
                    'reasonPhrase' => 'Internal Server Error',
                ],
            ],
            [
                $this->getUri(['redirect' => true]),
                [
                    'statusCode'   => 302,
                    'reasonPhrase' => 'Found',
                    'body'         => 'Redirect',
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    private function getProtocolVersions()
    {
        return ['1.1', '1.0'];
    }

    /**
     * @return string[]
     */
    private function getHeaders()
    {
        $headers = $this->defaultHeaders;
        $headers['Accept-Charset'] = 'utf-8';
        $headers['Accept-Language'] = 'en';

        return [
            $this->defaultHeaders,
            $headers,
        ];
    }

    /**
     * @return array
     */
    private function getBodies()
    {
        return [
            null,
            http_build_query($this->getData(), null, '&'),
        ];
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
        } else {
            $this->assertContains($options['body'], $response->getBody()->getContents());
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
     * @return array
     */
    protected function getRequest()
    {
        $file = fopen(self::$logPath, 'r');
        flock($file, LOCK_EX);
        $request = json_decode(stream_get_contents($file), true);
        flock($file, LOCK_UN);
        fclose($file);

        return $request;
    }
}
