<?php

namespace Http\Client\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use Nerd\CartesianProduct\CartesianProduct;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

abstract class HttpBaseTest extends TestCase
{
    private static string $logPath;

    protected static RequestFactoryInterface $requestFactory;
    protected static StreamFactoryInterface $streamFactory;

    protected array $defaultOptions = [
        'protocolVersion' => '1.1',
        'statusCode' => 200,
        'reasonPhrase' => 'OK',
        'headers' => ['Content-Type' => 'text/html'],
        'body' => 'Ok',
    ];

    protected static array $defaultHeaders = [
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
        self::$requestFactory = self::$streamFactory = new HttpFactory();
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

    public static function requestProvider(): array
    {
        $sets = [
            'methods' => self::getMethods(),
            'uris' => [self::getUri()],
            'headers' => self::getHeaders(),
            'body' => self::getBodies(),
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

    public static function requestWithOutcomeProvider(): array
    {
        $sets = [
            'urisAndOutcomes' => self::getUrisAndOutcomes(),
            'protocolVersions' => self::getProtocolVersions(),
            'headers' => self::getHeaders(),
            'body' => self::getBodies(),
        ];

        $cartesianProduct = new CartesianProduct($sets);

        return $cartesianProduct->compute();
    }

    private static function getMethods(): array
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
    protected static function getUri(array $query = []): ?string
    {
        return !empty($query)
            ? PHPUnitUtility::getUri().'?'.http_build_query($query, '', '&')
            : PHPUnitUtility::getUri();
    }

    protected function getInvalidUri(): string
    {
        return 'http://invalid.php-http.org';
    }

    private static function getUrisAndOutcomes(): array
    {
        return [
            [
                self::getUri(['client_error' => true]),
                [
                    'statusCode' => 400,
                    'reasonPhrase' => 'Bad Request',
                ],
            ],
            [
                self::getUri(['server_error' => true]),
                [
                    'statusCode' => 500,
                    'reasonPhrase' => 'Internal Server Error',
                ],
            ],
            [
                self::getUri(['redirect' => true]),
                [
                    'statusCode' => 302,
                    'reasonPhrase' => 'Found',
                    'body' => 'Redirect',
                ],
            ],
        ];
    }

    private static function getProtocolVersions(): array
    {
        return ['1.1', '1.0'];
    }

    /**
     * @return string[]
     */
    private static function getHeaders(): array
    {
        $headers = self::$defaultHeaders;
        $headers['Accept-Charset'] = 'utf-8';
        $headers['Accept-Language'] = 'en';

        return [
            self::$defaultHeaders,
            $headers,
        ];
    }

    private static function getBodies(): array
    {
        return [
            null,
            http_build_query(self::getData(), '', '&'),
        ];
    }

    private static function getData(): array
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
            self::assertStringContainsString($options['body'], $response->getBody()->__toString());
        }
    }

    /**
     * @param string[] $headers
     */
    protected function assertRequest(
        string $method,
        array  $headers = [],
        ?string $body = null,
        string $protocolVersion = '1.1'
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

            if (in_array($method, ['TRACE', 'HEAD'], true) && 'HTTP_CONTENT_LENGTH' === $name && !isset($request['SERVER'][$name])) {
                continue;
            }

            $this->assertArrayHasKey($name, $request['SERVER']);
            $this->assertSame($value, $request['SERVER'][$name], "Failed asserting value for {$name}.");
        }
    }

    protected function getRequest(): array
    {
        $file = fopen(self::$logPath, 'r');
        flock($file, LOCK_EX);
        $request = json_decode(stream_get_contents($file), true);
        flock($file, LOCK_UN);
        fclose($file);

        return $request;
    }
}
