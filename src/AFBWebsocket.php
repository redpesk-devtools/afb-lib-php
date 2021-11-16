<?php

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

    public Connection $connection;

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
     * @param string $method
     * @param array $data
     * @return string AFB binder request id
     * @throws ClosedException
     */
    public function send(string $method, array $data): string
    {
        $id = uniqid();
        $call = [self::CALL, $id, $method, $data];
        $this->connection->send(json_encode($call, JSON_UNESCAPED_SLASHES));
        return $id;
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
                yield $emit(json_decode(yield $message->buffer(), JSON_UNESCAPED_SLASHES));
            }
        });
    }

    /**
     * Extract the status of the message.
     *
     * @param array $message
     * @return int
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
