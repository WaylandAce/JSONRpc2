<?php

namespace pavelk\JsonRPC\Server\Exception;


class ServerErrorException extends ServerException
{
    protected $code = -32600;

    /**
     * ServerErrorException constructor.
     * @param string $message
     */
    public function __construct($message = 'Server error')
    {
        parent::__construct($message, $this->code);
    }

}