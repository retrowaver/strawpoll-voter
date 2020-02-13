<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Retrowaver\Strawpoll\Strawpoll;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class StrawpollTest extends TestCase
{
    protected function setUp(): void
    {
        //
        $this->handlerSuccess = new MockHandler([
            new Response(200, [], '{"success":"success"}'),
            new Response(200, [], '{"success":"success"}'),
            new Response(200, [], '{"success":"success"}'),
            new Response(200, [], '{"success":"success"}'),
            new Response(200, [], '{"success":"success"}'),
            new Response(200, [], '{"success":"success"}'),
            new Response(200, [], '{"success":"success"}')
        ]);
        $this->clientSuccess = new Client(['handler' => HandlerStack::create($this->handlerSuccess), 'http_errors' => false]);

        //
        $this->handlerFailure = new MockHandler([
            new Response(502),
            new Response(503),
            new Response(400),
            new Response(502),
            new Response(200, [], '{"success":"failed"}'),
            new Response(200, [], '{"foo":"bar"}'),
            new Response(200, [], 'something else')
        ]);
        $this->clientFailure = new Client(['handler' => HandlerStack::create($this->handlerFailure), 'http_errors' => false]);

        //
        $this->container = [];
        $history = Middleware::history($this->container);

        $handlerStack = HandlerStack::create($this->handlerSuccess);
        $handlerStack->push($history);

        $this->clientMiddleware = new Client(['handler' => $handlerStack, 'http_errors' => false]);
    }

    public function testCanBeCreated()
    {
        $this->assertInstanceOf(
            Strawpoll::class,
            new Strawpoll
        );
    }

    public function testVoteReturnsValidSuccessCountOnSuccess()
    {
        $strawpoll = new Strawpoll($this->clientSuccess);

        $this->assertEquals(
            7,
            $strawpoll->vote('foo', 'bar', ['1', '2', '3', '4', '5', '6', '7'])
        );
    }

    public function testVoteReturnsValidSuccessCountOnFailure()
    {
        $strawpoll = new Strawpoll($this->clientFailure);

        $this->assertEquals(
            0,
            $strawpoll->vote('foo', 'bar', ['1', '2', '3', '4', '5', '6', '7'])
        );
    }

    public function testVoteSendsRequestWithValidUrl()
    {
        $strawpoll = new Strawpoll($this->clientMiddleware);
        $strawpoll->vote('foo', 'bar', ['127.0.0.1:1234']);

        $this->assertEquals(
            'https://www.strawpoll.me/foo',
            (string)$this->container[0]['request']->getUri()
        );
    }

    public function testVoteSendsRequestWithValidBody()
    {
        $strawpoll = new Strawpoll($this->clientMiddleware);
        $strawpoll->vote('foo', 'bar', ['127.0.0.1:1234']);

        $this->assertEquals(
            'options=bar',
            (string)$this->container[0]['request']->getBody()
        );
    }

    public function testVoteSendsRequestUsingValidProxy()
    {
        $strawpoll = new Strawpoll($this->clientMiddleware);
        $strawpoll->vote('foo', 'bar', ['127.0.0.1:1234']);

        $this->assertEquals(
            '127.0.0.1:1234',
            (string)$this->container[0]['options']['proxy']
        );
    }

    public function testVoteSendsRequestUsingDefaultTimeout20()
    {
        $strawpoll = new Strawpoll($this->clientMiddleware);
        $strawpoll->vote('foo', 'bar', ['127.0.0.1:1234']);

        $this->assertEquals(
            20,
            (string)$this->container[0]['options']['timeout']
        );
    }

    public function testVoteSendsRequestUsingValidTimeout()
    {
        $strawpoll = new Strawpoll($this->clientMiddleware);
        $strawpoll->vote('foo', 'bar', ['127.0.0.1:1234'], 50, ['timeout' => 50]);

        $this->assertEquals(
            50,
            (string)$this->container[0]['options']['timeout']
        );
    }

    public function testVoteUsesDifferentProxyEachTime()
    {
        $strawpoll = new Strawpoll($this->clientMiddleware);
        $strawpoll->vote('foo', 'bar', ['127.0.0.1:1234', '127.0.0.1:2345', '127.0.0.1:3456']);

        $proxies = [];
        foreach ($this->container as $transaction) {
            $proxies[] = $transaction['options']['proxy'];
        }

        $this->assertCount(
            3,
            array_unique($proxies)
        );
    }
}
