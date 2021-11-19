<?php declare(strict_types=1);

use Amp\PHPUnit\AsyncTestCase;
use Amp\Websocket\ClosedException;

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
    public function testSendRequestToServer()
    {
        $afb = new AFBWebsocket();
        yield $afb->connect('ws://localhost:21213/api');

        $afb
            ->send(new AFBRequest('hello/call', ['class' => 'a']))
            ->getPromise()->onResolve(function ($response) use ($afb) {
                $this->onAssert(function () use($response) {
                    $this->assertSame($response->getCode(), AFBWebsocket::RETERR);
                }, [$afb, 'close']);
            });

        $afb->receive();
    }
}
