<?php

namespace Http\Client\Tests;

use Http\Message\MessageFactory;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Nerd\CartesianProduct\CartesianProduct;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class HttpBaseTest extends TestCase
{
    use PhpUnitBackwardCompatibleTrait;

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
        'statusCode' => 200,
        'reasonPhrase' => 'OK',
        'headers' => ['Content-Type' => 'text/html'],
        'body' => 'Ok',
    ];

    /**
     * @var array
     */
    protected $defaultHeaders = [
        'Connection' => 'close',
        'User-Agent' => 'PHP HTTP Adapter',
        'Content-Length' => '0',
    ];

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$logPath = PHPUnitUtility::getFile(true, 'php-http-adapter.log');
        self::$messageFactory = new GuzzleMessageFactory();
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$logPath)) {
            unlink(self::$logPath);
        }
    }

    public function requestProvider(): array
    {
        $sets = [
            'methods' => $this->getMethods(),
            'uris' => [$this->getUri()],
            'headers' => $this->getHeaders(),
            'body' => $this->getBodies(),
        ];

        $cartesianProduct = new CartesianProduct($sets);

        $cases = $cartesianProduct->compute();

        // Filter all TRACE requests with a body, as they're not HTTP spec compliant
        return array_filter($cases, function ($case) {
            if ('TRACE' === $case[0] && null !== $case[3]) {
                return false;
            }

            return true;
        });
    }

    public function requestWithOutcomeProvider(): array
    {
        $sets = [
            'urisAndOutcomes' => $this->getUrisAndOutcomes(),
            'protocolVersions' => $this->getProtocolVersions(),
            'headers' => $this->getHeaders(),
            'body' => $this->getBodies(),
        ];

        $cartesianProduct = new CartesianProduct($sets);

        return $cartesianProduct->compute();
    }

    private function getMethods(): array
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
                    'statusCode' => 400,
                    'reasonPhrase' => 'Bad Request',
                ],
            ],
            [
                $this->getUri(['server_error' => true]),
                [
                    'statusCode' => 500,
                    'reasonPhrase' => 'Internal Server Error',
                ],
            ],
            [
                $this->getUri(['redirect' => true]),
                [
                    'statusCode' => 302,
                    'reasonPhrase' => 'Found',
                    'body' => 'Redirect',
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

    protected function assertResponse(ResponseInterface $response, array $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        // The response version may be greater or equal to the request version. See https://tools.ietf.org/html/rfc2145#section-2.3
        $this->assertTrue(substr($options['protocolVersion'], 0, 1) === substr($response->getProtocolVersion(), 0, 1) && 1 !== version_compare($options['protocolVersion'], $response->getProtocolVersion()));
        $this->assertSame($options['statusCode'], $response->getStatusCode());
        $this->assertSame($options['reasonPhrase'], $response->getReasonPhrase());

        $this->assertNotEmpty($response->getHeaders());

        foreach ($options['headers'] as $name => $value) {
            $this->assertTrue($response->hasHeader($name));
            $this->assertStringStartsWith($value, $response->getHeaderLine($name));
        }

        if (null === $options['body']) {
            $this->assertEmpty($response->getBody()->__toString());
        } else {
            $this->assertStringContainsString($options['body'], $response->getBody()->__toString());
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

        $actualProtocolVersion = substr($request['SERVER']['SERVER_PROTOCOL'], 5);
        $this->assertTrue(substr($protocolVersion, 0, 1) === substr($actualProtocolVersion, 0, 1) && 1 !== version_compare($protocolVersion, $actualProtocolVersion));
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

            if ('TRACE' === $method && 'HTTP_CONTENT_LENGTH' === $name && !isset($request['SERVER'][$name])) {
                continue;
            }

            $this->assertArrayHasKey($name, $request['SERVER']);
            $this->assertSame($value, $request['SERVER'][$name], "Failed asserting value for {$name}.");
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
