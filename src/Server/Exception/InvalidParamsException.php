<?php

namespace pavelk\JsonRPC\Server\Exception;


class InvalidParamsException extends ServerException
{
    protected $code = -32602;

    /**
     * InvalidParamsException constructor.
     * @param string $message
     */
    public function __construct($message = 'Invalid params')
    {
        parent::__construct($message, $this->code);
    }

}