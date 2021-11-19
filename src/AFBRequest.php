<?php

use Amp\Promise;

/**
 * Class which encapsulates the request to be sent to the AFB-Binder with the corresponding promise.
 */
class AFBRequest
{
    /**
     * @var int|null
     */
    private ?int $id;

    /**
     * @var string
     */
    private string $method;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var Promise|null
     */
    private ?Promise $promise;

    /**
     * @param string $method
     * @param array $data
     */
    public function __construct(string $method, array $data)
    {
        $this->method = $method;
        $this->data = $data;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return AFBRequest
     */
    public function setId(?int $id): AFBRequest
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     * @return AFBRequest
     */
    public function setMethod(string $method): AFBRequest
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     * @return AFBRequest
     */
    public function setData(array $data): AFBRequest
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return Promise|null
     */
    public function getPromise(): ?Promise
    {
        return $this->promise;
    }

    /**
     * @param Promise|null $promise
     * @return AFBRequest
     */
    public function setPromise(?Promise $promise): AFBRequest
    {
        $this->promise = $promise;
        return $this;
    }

    /**
     * @return array
     */
    public function getCall() : array
    {
        return [AFBWebsocket::CALL, $this->id, $this->method, $this->data];
    }
}