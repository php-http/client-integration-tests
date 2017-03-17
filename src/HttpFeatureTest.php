<?php

namespace Http\Client\Tests;

use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use PHPUnit\Framework\TestCase;

abstract class HttpFeatureTest extends TestCase
{
    /**
     * @var MessageFactory
     */
    protected static $messageFactory;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        self::$messageFactory = new GuzzleMessageFactory();
    }

    /**
     * @return HttpClient
     */
    abstract protected function createClient();

    /**
     * @feature Send a GET Request
     */
    public function testGet()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'http://httpbin.org/get'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Send a POST Request
     */
    public function testPost()
    {
        $testData = 'Test data';
        $request = self::$messageFactory->createRequest(
            'POST',
            'http://httpbin.org/post',
            ['Content-Length' => strlen($testData)],
            $testData
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());

        $contents = json_decode($response->getBody()->getContents());

        $this->assertEquals($testData, $contents->data);
    }

    /**
     * @feature Send a PATCH Request
     */
    public function testPatch()
    {
        $request = self::$messageFactory->createRequest(
            'PATCH',
            'http://httpbin.org/patch'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Send a PUT Request
     */
    public function testPut()
    {
        $request = self::$messageFactory->createRequest(
            'PUT',
            'http://httpbin.org/put'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Send a DELETE Request
     */
    public function testDelete()
    {
        $request = self::$messageFactory->createRequest(
            'DELETE',
            'http://httpbin.org/delete'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Auto fixing content length header
     */
    public function testAutoSetContentLength()
    {
        $testData = 'Test data';
        $request = self::$messageFactory->createRequest(
            'POST',
            'http://httpbin.org/post',
            [],
            $testData
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());

        $contents = json_decode($response->getBody()->getContents());

        $this->assertEquals($testData, $contents->data);
    }

    /**
     * @feature Encoding in UTF8
     */
    public function testEncoding()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'http://httpbin.org/encoding/utf8'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('€', $response->getBody()->getContents());
    }

    /**
     * @feature Gzip content decoding
     */
    public function testGzip()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'http://httpbin.org/gzip'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('gzip', $response->getBody()->getContents());
    }

    /**
     * @feature Deflate content decoding
     */
    public function testDeflate()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'http://httpbin.org/deflate'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('deflate', $response->getBody()->getContents());
    }

    /**
     * @feature Follow redirection
     */
    public function testRedirect()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'http://httpbin.org/redirect/1'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Dechunk stream body
     */
    public function testChunked()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'http://httpbin.org/stream/1'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());

        $content = @json_decode($response->getBody()->getContents());

        $this->assertNotNull($content);
    }

    /**
     * @feature Ssl connection
     */
    public function testSsl()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/get'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
        $this->assertSame(200, $response->getStatusCode());
    }
}
