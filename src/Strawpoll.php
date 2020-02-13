<?php

namespace Retrowaver\Strawpoll;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class Strawpoll
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var int
     */
    protected $successCount;

    /**
     * @param ClientInterface|null $client
     */
    public function __construct(?ClientInterface $client = null)
    {
        if ($client === null) {
            $client = new Client(['http_errors' => false]);
        }
        $this->client = $client;
    }

    /**
     * @param string $pollUri
     * @param string $optionId
     * @param array $proxies
     * @param int|null $concurrency
     * @param array|null $requestOptions
     * @return int
     */
    public function vote(
        string $pollUri,
        string $optionId,
        array $proxies,
        ?int $concurrency = 50,
        ?array $requestOptions = []
    ): int {
        $this->successCount = 0;
        
        $request = $this->getRequest($pollUri, $optionId);
        $generator = $this->getPromiseGenerator($proxies, $request, $requestOptions);
        $eachPromise = $this->getEachPromise($generator, $concurrency);
        
        $eachPromise->promise()->wait();

        return $this->successCount;
    }

    /**
     * @param string $pollUri
     * @param string $optionId
     * @return RequestInterface
     */
    protected function getRequest(string $pollUri, string $optionId): RequestInterface
    {
        return new Request(
            'POST',
            sprintf('https://www.strawpoll.me/%s', $pollUri),
            ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
            http_build_query([
                'options' => $optionId
            ])
        );
    }

    /**
     * @param array $proxies
     * @param RequestInterface $request
     * @param array $requestOptions
     * @return \Closure
     */
    protected function getPromiseGenerator(
        array $proxies,
        RequestInterface $request,
        array $requestOptions
    ): \Closure {
        return function () use ($proxies, $request, $requestOptions): \Generator {
            foreach ($proxies as $proxy) {
                yield $this->client->sendAsync(
                    $request,
                    ['proxy' => $proxy] + $requestOptions + $this->getDefaultRequestOptions()
                );
            }
        };
    }

    /**
     * @param \Closure $generator
     * @param int $concurrency
     * @return EachPromise
     */
    protected function getEachPromise(\Closure $generator, int $concurrency): EachPromise
    {
        return new EachPromise($generator(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response): void {
                if (($json = json_decode((string)$response->getBody())) === null) {
                    return;
                }

                if (!isset($json->success) || $json->success !== 'success') {
                    return;
                }

                $this->successCount++;
            }
        ]);
    }

    /**
     * @return array
     */
    protected function getDefaultRequestOptions(): array
    {
        return ['timeout' => 20];
    }
}
