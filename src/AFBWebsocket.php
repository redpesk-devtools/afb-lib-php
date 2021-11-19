<?php

use Amp\Deferred;
use Amp\Producer;
use Amp\Promise;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Client\ConnectionException;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\ClosedException;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\Iterator\map;
use function Amp\Websocket\Client\connect;

/**
 * Class AFBWebsocket
 */
class AFBWebsocket
{
    const CALL = 2, RETOK = 3, RETERR = 4, EVENT = 5, PROTO1 = "x-afb-ws-json1";

    /**
     * @var Connection to websocket
     */
    private Connection $connection;

    /**
     * @var array
     */
    private array $pendingRequests;

    /**
     * Open connection to afb-binder.
     */
    public function connect(string $uri): Promise
    {
        return call(function () use ($uri) {
            try {
                $this->connection = yield connect(new Handshake($uri, null, ['Sec-WebSocket-Protocol' => self::PROTO1]));
            } catch (ConnectionException $e) {
                $message = $e->getMessage();
                throw new Exception("Cannot connect to server (ConnectionException). $message");
            }
        });
    }

    /**
     * Send request to afb-binder.
     *
     * @param AFBRequest $request
     * @return Promise
     * @throws ClosedException
     */
    public function send(AFBRequest $request): Promise
    {
        $request->setId(uniqid());

        $this->connection->send(json_encode($request->getCall(), JSON_UNESCAPED_SLASHES));

        $deferred = new Deferred();
        $this->pendingRequests[$request->getId()] = $deferred;
        $request->setPromise($deferred->promise());

        return $request->getPromise();
    }

    /**
     * Produce a message each time the server sent message.
     *
     * @return Producer
     */
    private function receive(): Producer
    {
        return new Producer(function (callable $emit) {
            while ($message = yield $this->connection->receive()) {
                yield $emit(AFBResponse::fromJson(yield $message->buffer()));
            }
        });
    }

    /**
     * Handle responses from server.
     * Need to be called in Event loop.
     * @throws Throwable
     */
    public function handleResponses()
    {
        asyncCall(function () {
            yield map($this->receive(), function (AFBResponse $response) {
                /** @var Deferred $deferred */
                if (array_key_exists($response->getId(), $this->pendingRequests)
                    && $deferred = $this->pendingRequests[$response->getId()]) {
                    if ($response->getCode() !== AFBWebsocket::RETOK) {
                        $deferred->fail(new AFBResponseException($response));
                    } else {
                        $deferred->resolve($response);
                    }
                }
            })->advance();
        });
    }

    /**
     * Close connection to afb-binder.
     */
    public function close()
    {
        $this->connection->close();
    }

    /**
     * @return bool
     */
    public function isConnected() : bool
    {
        return $this->connection->isConnected();
    }
}
