<?php

namespace pavelk\JsonRPC\Server\Exception;


class ParseErrorException extends ServerException
{
    protected $code = -32700;

    /**
     * ParseErrorException constructor.
     * @param string $message
     */
    public function __construct($message = 'Parse error')
    {
        parent::__construct($message, $this->code);
    }

}