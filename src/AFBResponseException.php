<?php

/**
 * Exception to encapsulate AFB Response when exception occur.
 */
class AFBResponseException extends Exception
{
    /**
     * @var AFBResponse
     */
    private AFBResponse $response;

    /**
     * @param AFBResponse $response
     */
    public function __construct(AFBResponse $response)
    {
        parent::__construct("Error response from server", AFBWebsocket::RETERR);
        $this->response = $response;
    }

    /**
     * @return AFBResponse
     */
    public function getResponse(): AFBResponse
    {
        return $this->response;
    }
}