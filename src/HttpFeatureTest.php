<?php

namespace Http\Client\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

abstract class HttpFeatureTest extends TestCase
{
    protected static RequestFactoryInterface $messageFactory;
    protected static StreamFactoryInterface $streamFactory;

    public static function setUpBeforeClass(): void
    {
        self::$messageFactory = self::$streamFactory = new HttpFactory();
    }

    abstract protected function createClient(): ClientInterface;

    /**
     * @feature Send a GET Request
     */
    public function testGet(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/get'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Send a POST Request
     */
    public function testPost(): void
    {
        $testData = 'Test data';
        $request = self::$messageFactory->createRequest('POST', 'https://httpbin.org/post');
        $request = $request->withHeader('Content-Length', strlen($testData));
        $request = $request->withBody(self::$streamFactory->createStream($testData));

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());

        $contents = json_decode($response->getBody()->__toString());

        $this->assertEquals($testData, $contents->data);
    }

    /**
     * @feature Send a PATCH Request
     */
    public function testPatch(): void
    {
        $request = self::$messageFactory->createRequest(
            'PATCH',
            'https://httpbin.org/patch'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Send a PUT Request
     */
    public function testPut(): void
    {
        $request = self::$messageFactory->createRequest(
            'PUT',
            'https://httpbin.org/put'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Send a DELETE Request
     */
    public function testDelete(): void
    {
        $request = self::$messageFactory->createRequest(
            'DELETE',
            'https://httpbin.org/delete'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Auto fixing content length header
     */
    public function testAutoSetContentLength(): void
    {
        $testData = 'Test data';
        $request = self::$messageFactory->createRequest(
            'POST',
            'https://httpbin.org/post',
        );
        $request = $request->withBody(self::$streamFactory->createStream($testData));

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());

        $contents = json_decode($response->getBody()->__toString());

        $this->assertEquals($testData, $contents->data);
    }

    /**
     * @feature Encoding in UTF8
     */
    public function testEncoding(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/encoding/utf8'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('€', $response->getBody()->__toString());
    }

    /**
     * @feature Gzip content decoding
     */
    public function testGzip(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/gzip'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('gzip', $response->getBody()->__toString());
    }

    /**
     * @feature Deflate content decoding
     */
    public function testDeflate(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/deflate'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('deflate', $response->getBody()->__toString());
    }

    /**
     * @feature Follow redirection
     */
    public function testRedirect(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/redirect/1'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @feature Dechunk stream body
     */
    public function testChunked(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/stream/1'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());

        $content = @json_decode($response->getBody()->__toString());

        $this->assertNotNull($content);
    }

    /**
     * @feature Ssl connection
     */
    public function testSsl(): void
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            'https://httpbin.org/get'
        );

        $response = $this->createClient()->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
    }
}
