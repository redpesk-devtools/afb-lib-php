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

        $requestId = $afb->send('hello/call', ['class' => 'a']);

        $messages = $afb->receive();
        while (yield $messages->advance()) {
            $message = $messages->getCurrent();

            list(, $id) = $message;
            if ($id === $requestId) {
                $this->onAssert(function () use ($afb, $message) {
                    $this->assertSame($afb->getMessageStatus($message), AFBWebsocket::RETERR);
                }, [$afb, 'close']);
            }
        }
    }
}
