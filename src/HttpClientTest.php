<?php

namespace Http\Client\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
abstract class HttpClientTest extends HttpBaseTest
{
    protected ClientInterface $httpAdapter;

    protected function setUp(): void
    {
        $this->httpAdapter = $this->createHttpAdapter();
    }

    protected function tearDown(): void
    {
        unset($this->httpAdapter);
    }

    abstract protected function createHttpAdapter(): ClientInterface;

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    #[Group('integration')]
    #[DataProvider('requestProvider')]
    public function testSendRequest(string $method, string $uri, array $headers, ?string $body)
    {
        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $request = self::$requestFactory->createRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $body) {
            $request = $request->withBody(self::$streamFactory->createStream($body));
        }


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
    #[Group('integration')]
    #[DataProvider('requestWithOutcomeProvider')]
    public function testSendRequestWithOutcome(array $uriAndOutcome, string $protocolVersion, array $headers, ?string $body): void
    {
        if ('1.0' === $protocolVersion) {
            $body = null;
        }

        if (null != $body) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        $request = self::$requestFactory->createRequest($method = 'GET', $uriAndOutcome[0]);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $body) {
            $request = $request->withBody(self::$streamFactory->createStream($body));
        }
        $request->withProtocolVersion($protocolVersion);

        $response = $this->httpAdapter->sendRequest($request);

        $outcome = $uriAndOutcome[1];
        $outcome['protocolVersion'] = $protocolVersion;

        $this->assertResponse($response, $outcome);
        $this->assertRequest($method, $headers, $body, $protocolVersion);
    }

    /**
     * @group integration
     */
    #[Group('integration')]
    public function testSendWithInvalidUri(): void
    {
        $request = self::$requestFactory->createRequest(
            'GET',
            $this->getInvalidUri(),
        );
        foreach (self::$defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->expectException(NetworkExceptionInterface::class);
        $this->httpAdapter->sendRequest($request);
    }
}
