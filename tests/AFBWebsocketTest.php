<?php declare(strict_types=1);

use Amp\PHPUnit\AsyncTestCase;
use Amp\Websocket\ClosedException;
use function Amp\call;

final class AFBWebsocketTest extends AsyncTestCase
{
    /**
     * Executes the deferred code after the assertion whether it failed or not.
     *
     * This is a workaround to patch this : https://github.com/amphp/phpunit-util/issues/12
     *
     * @param callable $assertion
     * @param callable $defer
     */
    private function onAssert(callable $assertion, callable $defer)
    {
        try {
            $assertion();
        } finally {
            $defer();
        }
    }

    public function testConnectToServer()
    {
        $afb = new AFBWebsocket();
        yield $afb->connect('ws://localhost:21213/api');

        $this->onAssert(fn () => $this->assertSame(true, $afb->isConnected()), [$afb, 'close']);
    }

    /**
     * @throws ClosedException
     * @throws Throwable
     */
    public function testSendGoodRequestToServer()
    {
        $afb = new AFBWebsocket();

        yield $afb->connect('ws://localhost:21213/api');


        call(function () use ($afb) {
            try {
                /** @var AFBResponse $response */
                $response = yield $afb->send(new AFBRequest('redis/ts_mget', ['class' => 'a']));
                // Correct response from server
                $this->onAssert(function () use ($response) {
                    $this->assertSame($response->getCode(), AFBWebsocket::RETOK);
                }, [$afb, 'close']);
            } catch(AFBResponseException $exception) {
                $this->hasFailed();
            }
        });

        try {
            $afb->handleResponses();
        } catch (ClosedException $ex) {
            // Disconnect by binder
            $this->hasFailed();
        }
    }

    /**
     * @throws ClosedException
     * @throws Throwable
     */
    public function testSendBadRequestToServer()
    {
        $afb = new AFBWebsocket();

        yield $afb->connect('ws://localhost:21213/api');


        call(function () use ($afb) {
            try {
                /** @var AFBResponse $response */
                $response = yield $afb->send(new AFBRequest('hello/dummy', ['class' => 'a']));
                // Correct response from server
            } catch(AFBResponseException $exception) {
                $response = $exception->getResponse();
                $this->onAssert(function () use ($response) {
                    $this->assertSame($response->getCode(), AFBWebsocket::RETERR);
                }, [$afb, 'close']);
            }
        });

        try {
            $afb->handleResponses();
        } catch (ClosedException $ex) {
            // Disconnect by binder
            $this->hasFailed();
        }
    }
}
