<?php

use Amp\Deferred;
use Amp\Producer;
use Amp\Promise;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Client\ConnectionException;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\ClosedException;
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
        return Amp\call(function () use ($uri) {
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
     * @return AFBRequest The request being processed by the server.
     * @throws ClosedException
     */
    public function send(AFBRequest $request): AFBRequest
    {
        $request->setId(uniqid());
        $this->connection->send(json_encode($request->getCall(), JSON_UNESCAPED_SLASHES));

        $deferred = new Deferred();
        $this->pendingRequests[$request->getId()] = $deferred;
        $request->setPromise($deferred->promise());

        return $request;
    }

    /**
     * Produce a message each time server sent message.
     *
     * @return Producer
     */
    public function receive(): Producer
    {
        return new Producer(function (callable $emit) {
            while ($message = yield $this->connection->receive()) {
                $response = AFBResponse::fromJson(yield $message->buffer());

                /** @var Deferred $deferred */
                if (array_key_exists($response->getId(), $this->pendingRequests)
                    && $deferred = $this->pendingRequests[$response->getId()]) {

                    if ($response->getCode() !== AFBWebsocket::RETOK) {
                        // Todo : maybe it would be better to have an exception to retrieve the server response.
                        $deferred->fail(new Exception("Bad response from server."));
                    } else {
                        $deferred->resolve($response);
                    }
                }

                yield $emit($response);
            }
        });
    }

    /**
     * Extract the status of the message.
     *
     * @param array $message
     * @return int
     * @deprecated use AFBResponse instead
     */
    public function getMessageStatus(array $message): int
    {
        return count($message) > 0 ? $message[0] : self::RETERR;
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
