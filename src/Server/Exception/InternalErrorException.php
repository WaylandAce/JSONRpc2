<?php

namespace pavelk\JsonRPC\Server\Exception;


class InternalErrorException extends ServerException
{
    protected $code = -32603;

    /**
     * InternalErrorException constructor.
     * @param string $message
     */
    public function __construct($message = 'Internal error')
    {
        parent::__construct($message, $this->code);
    }

}